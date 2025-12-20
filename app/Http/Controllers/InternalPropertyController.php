<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InternalPropertyService;
use App\Services\RightmoveScraperService;
use App\Models\SavedSearch;
use App\Models\Url;
use App\Models\PropertySold;
use App\Models\PropertySoldPrice;
use Illuminate\Support\Facades\Log;

class InternalPropertyController extends Controller
{
    private $propertyService;
    private $scraperService;

    public function __construct(InternalPropertyService $propertyService, RightmoveScraperService $scraperService)
    {
        $this->propertyService = $propertyService;
        $this->scraperService = $scraperService;
    }

    /**
     * Display the internal properties listing view
     * 
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('internal-property.index', ['search' => null]);
    }

    /**
     * Display internal properties for a specific saved search
     */
    public function show($id)
    {
        $search = SavedSearch::findOrFail($id);
        return view('internal-property.index', ['search' => $search]);
    }

    /**
     * Load full property data from database (used on page refresh)
     * This avoids re-scraping from the source website
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadPropertiesFromDatabase(Request $request)
    {
        try {
            $searchId = $request->input('search_id');
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 50); // Reduced to 50 to prevent timeout with sold data
            
            Log::info("loadPropertiesFromDatabase: Loading properties for search_id: " . ($searchId ?? 'all') . ", page: {$page}, per_page: {$perPage}");
            
            // Build query for properties
            $query = \App\Models\Property::query();
            
            if ($searchId) {
                $query->where('filter_id', $searchId);
                Log::info("loadPropertiesFromDatabase: Filtering by filter_id = {$searchId}");
            } else {
                Log::info("loadPropertiesFromDatabase: No filter, loading all properties");
            }
            
            // Get total count before pagination
            $totalCount = $query->count();
            Log::info("loadPropertiesFromDatabase: Total {$totalCount} properties in database");
            
            if ($totalCount === 0) {
                Log::info("loadPropertiesFromDatabase: No properties found in database");
                return response()->json([
                    'success' => true,
                    'message' => 'No properties found in database',
                    'count' => 0,
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_pages' => 0,
                    'has_more' => false,
                    'properties' => [],
                    'source' => 'database'
                ]);
            }
            
            // Apply pagination and load with relationships
            // Note: Loading sold properties can be heavy, so using smaller page size
            $properties = $query->with(['images', 'soldProperties.prices'])
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            // Debug: Log sold properties for first property
            if ($properties->count() > 0) {
                $firstProp = $properties->first();
                Log::info("loadPropertiesFromDatabase: First property ID={$firstProp->property_id}, Images={$firstProp->images->count()}, Sold={$firstProp->soldProperties->count()}");
            }
            
            Log::info("loadPropertiesFromDatabase: Loaded {$properties->count()} properties (page {$page})");
            
            // Format properties using shared formatting method
            $formattedProperties = $this->formatPropertiesForResponse($properties);
            
            $totalPages = ceil($totalCount / $perPage);
            $hasMore = $page < $totalPages;
            
            return response()->json([
                'success' => true,
                'message' => "Loaded {$properties->count()} properties from database (page {$page} of {$totalPages})",
                'count' => count($formattedProperties),
                'total' => $totalCount,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'has_more' => $hasMore,
                'properties' => $formattedProperties,
                'source' => 'database'
            ]);
            
        } catch (\Exception $e) {
            Log::error("loadPropertiesFromDatabase error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error loading properties from database',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format properties collection for JSON response
     * Ensures consistent data structure between loading from DB and after scraping
     * 
     * @param \Illuminate\Database\Eloquent\Collection $properties Properties with images and soldProperties.prices loaded
     * @return array Formatted properties array
     */
    /**
     * Format properties for response, ensuring original URLs are preserved
     * 
     * @param \Illuminate\Support\Collection $properties
     * @param array $urlMap Mapping of property_id to original URL
     * @return array
     */
    private function formatPropertiesForResponse($properties, $urlMap = [])
    {
        return $properties->map(function($prop) use ($urlMap) {
            // Use the original URL if available in the map, otherwise build one
            $url = $urlMap[$prop->property_id] ?? "https://www.rightmove.co.uk/properties/{$prop->property_id}";
            
            // Get images
            $images = $prop->images ? $prop->images->pluck('image_link')->toArray() : [];
            
            // Parse key_features if stored as JSON
            $keyFeatures = [];
            if ($prop->key_features) {
                // key_features is already cast to array by Laravel model, no need to json_decode
                if (is_array($prop->key_features)) {
                    $keyFeatures = $prop->key_features;
                } elseif (is_string($prop->key_features)) {
                    // Only decode if it's still a string
                    $decoded = json_decode($prop->key_features, true);
                    $keyFeatures = is_array($decoded) ? $decoded : [$prop->key_features];
                }
            }
            
            // Format sold properties with their prices for JavaScript consumption
            // Also deduplicate by location to prevent same property showing multiple times
            $soldProperties = [];
            $totalSalesInPeriod = 0;
            $salesCountInPeriod = 0;
            $avgSoldPrice = 0;
            $discountMetric = 0;
            
            if ($prop->soldProperties && $prop->soldProperties->count() > 0) {
                Log::info("Property {$prop->property_id} has {$prop->soldProperties->count()} sold properties via relationship");
                
                // Group by location to find the unique properties
                $uniqueLatestSalesInPeriod = [];
                
                $soldProperties = $prop->soldProperties
                    ->unique('location')  // Deduplicate by location for the list
                    ->values()
                    ->map(function($sold) use (&$uniqueLatestSalesInPeriod) {
                    $latestSaleInPeriod = null;
                    $latestTimestamp = 0;
                    
                    $prices = $sold->prices ? $sold->prices->map(function($price) use (&$latestSaleInPeriod, &$latestTimestamp) {
                        // Parse date for period check (Jan 2020 - Dec 2025)
                        $timestamp = strtotime($price->sold_date);
                        if ($timestamp) {
                            $year = (int)date('Y', $timestamp);
                            if ($year >= 2020 && $year <= 2025) {
                                if ($timestamp > $latestTimestamp) {
                                    $latestTimestamp = $timestamp;
                                    $numericPrice = floatval(preg_replace('/[^\d.]/', '', $price->sold_price));
                                    $latestSaleInPeriod = $numericPrice;
                                }
                            }
                        }
                        
                        return [
                            'sold_price' => $price->sold_price,
                            'sold_date' => $price->sold_date
                        ];
                    })->toArray() : [];
                    
                    if ($latestSaleInPeriod) {
                        $uniqueLatestSalesInPeriod[] = $latestSaleInPeriod;
                    }
                    
                    return [
                        'id' => $sold->id,
                        'property_id' => $sold->property_id,
                        'location' => $sold->location,
                        'house_number' => $sold->house_number,
                        'road_name' => $sold->road_name,
                        'image_url' => $sold->image_url,
                        'property_type' => $sold->property_type,
                        'bedrooms' => $sold->bedrooms,
                        'bathrooms' => $sold->bathrooms,
                        'tenure' => $sold->tenure,
                        'detail_url' => $sold->detail_url,
                        'prices' => $prices
                    ];
                })->toArray();
                
                if (count($uniqueLatestSalesInPeriod) > 0) {
                    $avgSoldPrice = round(array_sum($uniqueLatestSalesInPeriod) / count($uniqueLatestSalesInPeriod));
                    $salesCountInPeriod = count($uniqueLatestSalesInPeriod);
                    
                    // Parse advertised price
                    $advertisedPriceStr = $prop->price ?? '';
                    $advertisedPrice = floatval(preg_replace('/[^\d.]/', '', $advertisedPriceStr));
                    
                    if ($advertisedPrice > 0 && $avgSoldPrice > 0) {
                        $discountMetric = (($avgSoldPrice - $advertisedPrice) / $avgSoldPrice) * 100;
                    }
                }
            } else {
                // Debug: Log why no sold properties are found
                Log::info("Property {$prop->property_id} has no sold properties. sold_link: " . ($prop->sold_link ?? 'NULL'));
            }
            
            // Extract house number and road name
            $address = $prop->location ?? 'Unknown location';
            $houseNumber = '';
            $roadName = $address;
            if (preg_match('/^(\d+[A-Za-z]?),\s*(.*)$/', $address, $matches)) {
                $houseNumber = $matches[1];
                $roadName = $matches[2];
            }

            return [
                'id' => $prop->property_id,
                'url' => $url,
                'address' => $address,
                'house_number' => $houseNumber,
                'road_name' => $roadName,
                'price' => $prop->price ?? 'Price on request',
                'property_type' => $prop->property_type ?? '',
                'bedrooms' => $prop->bedrooms ?? '',
                'bathrooms' => $prop->bathrooms ?? '',
                'size' => $prop->size ?? '',
                'tenure' => $prop->tenure ?? '',
                'council_tax' => $prop->council_tax ?? '',
                'parking' => $prop->parking ?? '',
                'garden' => $prop->garden ?? '',
                'accessibility' => $prop->accessibility ?? '',
                'ground_rent' => $prop->ground_rent ?? '',
                'annual_service_charge' => $prop->annual_service_charge ?? '',
                'lease_length' => $prop->lease_length ?? '',
                'description' => $prop->description ?? '',
                'key_features' => $keyFeatures,
                'sold_link' => $prop->sold_link ?? null,
                'sold_properties' => $soldProperties,
                'average_sold_price' => $avgSoldPrice,
                'sales_count_in_period' => $salesCountInPeriod,
                'discount_metric' => round($discountMetric, 1),
                'images' => $images,
                'loading' => false, // Data is fully loaded from DB
                'from_database' => true
            ];
        })->toArray();
    }

    /**
     * Fetch URLs with pagination support for progressive loading
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchUrlsPaginated(Request $request)
    {
        try {
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 100);
            
            // Cache check removed
            if (false) { // Disabled cache block
                // Original cache logic removed
            }
            
            // If no cache and page 1, trigger URL fetch
            if ($page === 1) {
                Log::info("Cache miss - triggering URL fetch");
                
                // Try to return partial data if available while fetching in background
                $response = $this->syncUrls();
                $data = $response->getData(true);
                
                if ($data['success'] && isset($data['urls']) && count($data['urls']) > 0) {
                    // Cache removed
                    // \Cache::put($cacheKey, $data, 1800);
                    
                    $allUrls = $data['urls'];
                    $total = count($allUrls);
                    $urlsPage = array_slice($allUrls, 0, $perPage);
                    
                    return response()->json([
                        'success' => true,
                        'message' => 'Property URLs fetched successfully',
                        'urls' => $urlsPage,
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => $perPage,
                            'total' => $total,
                            'total_pages' => ceil($total / $perPage),
                            'has_more' => $perPage < $total
                        ],
                        'cached' => false
                    ]);
                }
            }
            
            // No cache and not page 1
            return response()->json([
                'success' => false,
                'message' => 'URLs not cached. Please fetch page 1 first.',
                'urls' => [],
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0,
                    'has_more' => false
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error fetching paginated URLs: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch property URLs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Fetch URLs (optimized)
     * Uses caching to avoid long-running scraping on every request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchUrls(Request $request)
    {
        try {
            $searchId = $request->input('search_id');
            $import = $request->input('import') === 'true' || $request->input('import') === true;
            
            // IF NOT IMPORT: Check DB first
            if (!$import) {
                Log::info("fetchUrls: Checking DB for search_id: " . ($searchId ?? 'null'));
                
                // Check Database
                $query = Url::query();
                if ($searchId) {
                    $search = SavedSearch::find($searchId);
                    // Filter by filter_id matches search_id
                   $query->where('filter_id', $searchId);
                }
                
                $dbUrls = $query->get(); 
                Log::info("fetchUrls: DB query found " . $dbUrls->count() . " records.");
                
                if ($dbUrls->count() > 0) {
                     Log::info("Returning " . $dbUrls->count() . " URLs from DATABASE");
                     
                     // Format to match Scraper response
                     $formattedUrls = $dbUrls->map(function($url) {
                        return [
                            'url' => $url->url,
                            'id' => null, 
                        ]; 
                     })->toArray();
                     
                     return response()->json([
                        'success' => true,
                        'message' => 'Property URLs loaded from database',
                        'count' => count($formattedUrls),
                        'urls' => $formattedUrls,
                        'cached' => false,
                        'source' => 'database'
                    ]);
                }
            }
            
            // If explicit import OR no data found, proceed to fetch
            if (!$import) {
                 return response()->json([
                    'success' => true, // Success but empty
                    'message' => 'No properties found in database.',
                    'count' => 0,
                    'urls' => [],
                    'source' => 'database'
                ]);
            }

            Log::info("Fetching fresh URLs (Import: Yes)");
            
            // FETCH URLS FIRST (Before wiping DB)
            set_time_limit(600); // 10 minutes
            
            $response = null;
            if ($searchId) {
                $search = SavedSearch::find($searchId);
                if ($search && $search->updates_url) {
                    Log::info("Fetching URLs for Saved Search #{$searchId}: {$search->updates_url}");
                    $response = $this->scrapePropertyUrls($search->updates_url, true);
                } else {
                    $response = $this->syncUrls();
                }
            } else {
                $response = $this->syncUrls();
            }
            
            // Get the JSON data from the response
            $data = $response->getData(true);
            
            // ONLY DELETE OLD DATA IF WE ACTUALLY FOUND NEW DATA
            if ($data['success'] && isset($data['urls']) && count($data['urls']) > 0) {
                Log::info("Found " . count($data['urls']) . " new URLs. Clearing old data...");

                // CLEAR OLD DATA (never touch saved_searches table)
                // If importing for a specific search, delete only that search's data
                // If global import, delete all data
                try {
                    if ($searchId) {
                        // SCOPED DELETE: Remove only data for this specific saved search
                        Log::info("Performing scoped delete for filter_id: {$searchId}");
                        
                        // Get all property IDs that belong to this filter
                        $propertyIds = \App\Models\Property::where('filter_id', $searchId)
                            ->pluck('property_id')
                            ->toArray();
                        
                        if (!empty($propertyIds)) {
                            // Delete in order: child tables first, then parent tables
                            // Delete sold prices for sold properties linked to these properties
                            $soldIds = PropertySold::whereIn('property_id', $propertyIds)
                                ->pluck('id')
                                ->toArray();
                            
                            if (!empty($soldIds)) {
                                $deletedPrices = PropertySoldPrice::whereIn('sold_property_id', $soldIds)->delete();
                                Log::info("Deleted {$deletedPrices} sold price records for filter_id: {$searchId}");
                            }
                            
                            // Delete sold properties
                            $deletedSold = PropertySold::whereIn('property_id', $propertyIds)->delete();
                            Log::info("Deleted {$deletedSold} sold property records for filter_id: {$searchId}");
                            
                            // Delete images
                            $deletedImages = \App\Models\PropertyImage::whereIn('property_id', $propertyIds)->delete();
                            Log::info("Deleted {$deletedImages} image records");
                            
                            // Delete properties
                            $deletedProps = \App\Models\Property::whereIn('property_id', $propertyIds)->delete();
                            Log::info("Deleted {$deletedProps} property records");
                        }
                        
                        // Delete URLs for this filter
                        $deletedUrls = Url::where('filter_id', $searchId)->delete();
                        Log::info("Deleted {$deletedUrls} URL records for filter_id: {$searchId}");
                        
                    } else {
                        // GLOBAL DELETE: Remove all data (but NOT saved_searches)
                        Log::info("Performing global delete (all data)");
                        
                        // Disable foreign key checks for clean truncation
                        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                        
                        try {
                            // Truncate in order: child tables first, then parent tables
                            \App\Models\PropertySoldPrice::truncate();
                            Log::info("Truncated properties_sold_prices table");
                            
                            \App\Models\PropertySold::truncate();
                            Log::info("Truncated properties_sold table");
                            
                            \App\Models\PropertyImage::truncate();
                            Log::info("Truncated property_images table");
                            
                            \App\Models\Url::truncate();
                            Log::info("Truncated urls table");
                            
                            \App\Models\Property::truncate();
                            Log::info("Truncated properties table");
                            
                            Log::info("Successfully truncated all data tables");
                        } catch (\Exception $truncateEx) {
                            Log::error("Error during truncation: " . $truncateEx->getMessage());
                            
                            // Fallback to delete if truncate fails
                            \App\Models\PropertySoldPrice::query()->delete();
                            \App\Models\PropertySold::query()->delete();
                            \App\Models\PropertyImage::query()->delete();
                            \App\Models\Url::query()->delete();
                            \App\Models\Property::query()->delete();
                            Log::info("Fallback: Deleted all data instead of truncation");
                        }
                        
                        // Re-enable foreign key checks
                        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                        
                        // Reset auto-increment counters to 1
                        \DB::statement('ALTER TABLE properties_sold_prices AUTO_INCREMENT = 1');
                        \DB::statement('ALTER TABLE properties_sold AUTO_INCREMENT = 1');
                        \DB::statement('ALTER TABLE property_images AUTO_INCREMENT = 1');
                        \DB::statement('ALTER TABLE urls AUTO_INCREMENT = 1');
                        Log::info("Reset all auto-increment counters to 1");
                    }
                    
                    Log::info("Old data cleared successfully. Saving new data.");
                    
                } catch (\Exception $e) {
                    \DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                    Log::error("Error clearing old data: " . $e->getMessage());
                    Log::error("Stack trace: " . $e->getTraceAsString());
                    // Continue with import anyway
                }

                // Add cache timestamp
                $data['cached_at'] = now()->toIso8601String();
                
                // SAVE TO DATABASE if search_id exists OR if importing
                if ($searchId || $import) {
                    try {
                        // Validate filter_id existence
                        $filterId = null;
                        if ($searchId) {
                            $exists = SavedSearch::where('id', $searchId)->exists();
                            if ($exists) {
                                $filterId = $searchId;
                            } else {
                                Log::warning("Saved Search ID {$searchId} not found. Saving as global import.");
                            }
                        }

                        Log::info("Saving imported URLs to database. Filter ID: " . ($filterId ?? 'NULL'));
                        
                        $recordsToInsert = [];
                        foreach ($data['urls'] as $urlData) {
                            $propertyUrl = $urlData['url'] ?? '';
                            
                            if ($propertyUrl) {
                                // Prepare URL record
                                $recordsToInsert[] = [
                                    'filter_id' => $filterId,
                                    'url' => $propertyUrl,
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ];
                            }
                        }
                        
                        // Bulk insert URLs
                        if (!empty($recordsToInsert)) {
                            // Chunk inserts to avoid query size limits
                            $chunks = array_chunk($recordsToInsert, 500);
                            foreach ($chunks as $chunk) {
                                Url::insert($chunk);
                            }
                            Log::info("Saved " . count($recordsToInsert) . " URLs to database.");
                        }
                        
                    } catch (\Exception $dbEx) {
                        Log::error("Failed to save URLs to database: " . $dbEx->getMessage());
                    }
                }
                
                return $response;
            } else {
                Log::info("Import returned 0 URLs. Keeping existing database intact.");
                // Return the empty response, but don't wipe the DB
                return response()->json([
                    'success' => true,
                    'message' => 'No properties found matching your search. Existing data preserved.',
                    'count' => 0,
                    'urls' => [],
                    'source' => 'scraper'
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error("Error fetching URLs: " . $e->getMessage());
            
            // Return helpful error message
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch property URLs. This may take 5-10 minutes on first run.',
                'error' => $e->getMessage(),
                'hint' => 'Try refreshing the page in a few minutes or check Laravel logs'
            ], 500);
        }
    }

    /**
     * Fetch multiple properties with full details
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchAllProperties(Request $request)
    {
        try {
            set_time_limit(600); // 10 minutes for batch processing
            
            $request->validate([
                'urls' => 'required|array',
                'urls.*.url' => 'required|url',
                'filter_id' => 'nullable|integer|exists:saved_searches,id'
            ]);

            $urls = $request->input('urls');
            $filterId = $request->input('filter_id');
            $startTime = microtime(true);
            
            Log::info("Starting CONCURRENT batch fetch for " . count($urls) . " properties");

            // Use the service's concurrent fetch method for MASSIVE speed improvement
            $result = $this->propertyService->fetchPropertiesConcurrently($urls);
            
            // SAVE FETCHED PROPERTIES TO DATABASE
            $savedCount = 0;
            if ($result['processed'] > 0 && !empty($result['properties'])) {
                foreach ($result['properties'] as $propData) {
                    try {
                        // Extract ID from URL if not present (fallback)
                        $propId = null;
                        if (preg_match('/properties\/(\d+)/', $propData['url'], $matches)) {
                            $propId = $matches[1];
                        }
                        
                        if ($propId) {
                            // Use request filter_id if available, otherwise try to find it
                            $targetFilterId = $filterId;
                            
                            if (!$targetFilterId) {
                                $existingUrl = Url::where('url', $propData['url'])->first();
                                $targetFilterId = $existingUrl ? $existingUrl->filter_id : null;
                            }

                            // Update Property
                            $property = \App\Models\Property::updateOrCreate(
                                ['property_id' => $propId],
                                [
                                    'location' => $propData['address'] ?? '',
                                    'house_number' => $propData['house_number'] ?? '',
                                    'road_name' => $propData['road_name'] ?? '',
                                    'price' => $propData['price'] ?? '',
                                    'key_features' => $propData['key_features'] ?? [],
                                    'description' => $propData['description'] ?? '', 
                                    'sold_link' => $propData['sold_link'] ?? null,
                                    'filter_id' => $targetFilterId,
                                    'bedrooms' => $propData['bedrooms'] ?? null,
                                    'bathrooms' => $propData['bathrooms'] ?? null,
                                    'property_type' => $propData['property_type'] ?? null,
                                    'size' => $propData['size'] ?? null,
                                    'tenure' => $propData['tenure'] ?? null,
                                    'council_tax' => $propData['council_tax'] ?? null,
                                    'parking' => $propData['parking'] ?? null,
                                    'garden' => $propData['garden'] ?? null,
                                    'accessibility' => $propData['accessibility'] ?? null,
                                    'ground_rent' => $propData['ground_rent'] ?? null,
                                    'annual_service_charge' => $propData['annual_service_charge'] ?? null,
                                    'lease_length' => $propData['lease_length'] ?? null
                                ]
                            );
                            
                            // SCRAPE & SAVE SOLD HISTORY DURING IMPORT
                            // This makes import slower but shows sold data immediately
                            if (!empty($propData['sold_link'])) {
                                Log::info("Found sold link for property {$propId}: " . $propData['sold_link']);
                                
                                try {
                                    $soldData = $this->propertyService->scrapeSoldProperties($propData['sold_link'], $propId);
                                    
                                    if (!empty($soldData)) {
                                        Log::info("Scraped " . count($soldData) . " sold properties for property {$propId}");
                                        
                                        \DB::beginTransaction();
                                        try {
                                            $savedSoldCount = 0;
                                            $savedPriceCount = 0;
                                            
                                            foreach ($soldData as $soldProp) {
                                                if (empty($soldProp['location'])) {
                                                    Log::warning("Skipping sold property with no location");
                                                    continue;
                                                }
                                                
                                                $matchCriteria = [
                                                    'property_id' => $propId,
                                                    'location' => $soldProp['location']
                                                ];

                                                $soldRecord = PropertySold::updateOrCreate(
                                                    $matchCriteria,
                                                    [
                                                        'source_sold_link' => $propData['sold_link'],
                                                        'property_type' => $soldProp['property_type'],
                                                        'bedrooms' => $soldProp['bedrooms'],
                                                        'bathrooms' => $soldProp['bathrooms'],
                                                        'tenure' => $soldProp['tenure'],
                                                        'detail_url' => $soldProp['detail_url'] ?? null
                                                    ]
                                                );
                                                $savedSoldCount++;
                                                
                                                if (!empty($soldProp['transactions'])) {
                                                    PropertySoldPrice::where('sold_property_id', $soldRecord->id)->delete();
                                                    
                                                    foreach ($soldProp['transactions'] as $trans) {
                                                        if (!empty($trans['price']) || !empty($trans['date'])) {
                                                            PropertySoldPrice::create([
                                                                'sold_property_id' => $soldRecord->id,
                                                                'sold_price' => $trans['price'],
                                                                'sold_date' => $trans['date']
                                                            ]);
                                                            $savedPriceCount++;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            \DB::commit();
                                            Log::info("Successfully saved {$savedSoldCount} sold properties with {$savedPriceCount} price records for property {$propId}");
                                        } catch (\Exception $transEx) {
                                            \DB::rollBack();
                                            Log::error("Transaction failed for sold data on property {$propId}: " . $transEx->getMessage());
                                            throw $transEx;
                                        }
                                    } else {
                                        Log::warning("No sold data returned from scrapeSoldProperties for property {$propId}. sold_link: " . $propData['sold_link']);
                                    }
                                } catch (\Exception $soldEx) {
                                    Log::error("Failed to scrape/save sold property data for {$propId}: " . $soldEx->getMessage());
                                }
                            } else {
                                Log::info("No sold link available for property {$propId}");
                            }
                            
                            // Update Images
                            if (!empty($propData['images'])) {
                                \App\Models\PropertyImage::where('property_id', $propId)->delete();
                                $imagesToInsert = [];
                                foreach ($propData['images'] as $imgUrl) {
                                    if ($imgUrl) {
                                        $imagesToInsert[] = [
                                            'property_id' => $propId,
                                            'image_link' => $imgUrl,
                                            'created_at' => now(),
                                            'updated_at' => now()
                                        ];
                                    }
                                }
                                if (!empty($imagesToInsert)) {
                                    \App\Models\PropertyImage::insert($imagesToInsert);
                                }
                            }
                            $savedCount++;
                        }
                    } catch (\Exception $e) {
                         Log::error("Failed to save property {$propData['url']}: " . $e->getMessage());
                    }
                }
                Log::info("Saved {$savedCount} properties to database during batch fetch.");
            }
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            Log::info("Concurrent batch fetch complete in {$duration}s. Processed: {$result['processed']}, Failed: {$result['failed']}");

            // RELOAD PROPERTIES FROM DATABASE with proper relationships
            // This ensures consistency between what's stored and what's displayed
            $reloadedProperties = [];
            if ($result['processed'] > 0) {
                Log::info("Reloading {$result['processed']} properties from database with relationships...");
                
                // Get the property IDs that were just processed
                $propertyIds = [];
                foreach ($result['properties'] as $propData) {
                    if (isset($propData['id']) && !empty($propData['id'])) {
                        $propertyIds[] = $propData['id'];
                    } elseif (preg_match('/properties\/(\d+)/', $propData['url'], $matches)) {
                        $propertyIds[] = $matches[1];
                    }
                }
                
                if (!empty($propertyIds)) {
                    // Create a map of property_id to original URL to ensure frontend matches correctly
                    $urlMap = [];
                    foreach ($result['properties'] as $propData) {
                        $id = $propData['id'] ?? null;
                        if (!$id && preg_match('/properties\/(\d+)/', $propData['url'], $matches)) {
                            $id = $matches[1];
                        }
                        if ($id) {
                            $urlMap[$id] = $propData['url'];
                        }
                    }

                    // Query properties with relationships
                    $dbProperties = \App\Models\Property::whereIn('property_id', $propertyIds)
                        ->with(['images', 'soldProperties.prices'])
                        ->get();
                    
                    Log::info("Found " . $dbProperties->count() . " properties in database for reload");
                    
                    // Format using the shared method, passing the urlMap
                    $reloadedProperties = $this->formatPropertiesForResponse($dbProperties, $urlMap);
                    
                    Log::info("Reloaded and formatted " . count($reloadedProperties) . " properties from database");
                } else {
                    Log::warning("No property IDs found for database reload");
                }
            }

            // ALWAYS use reloaded properties if available (they have full data with images and sold history)
            // If reload fails or is empty, we fall back to scraped data (though it will lack some details)
            $finalProperties = count($reloadedProperties) > 0 ? $reloadedProperties : $result['properties'];
            
            // Final check: if we're sending scraped data, warn that it might be incomplete
            if (count($reloadedProperties) === 0 && $result['processed'] > 0) {
                Log::warning("Returning scraped data instead of reloaded database data. Images and sold info might be missing.");
            }
            
            Log::info("Returning " . count($finalProperties) . " properties to frontend (" . (count($reloadedProperties) > 0 ? 'reloaded from DB' : 'scraped') . ")");
            
            return response()->json([
                'success' => true,
                'message' => "Fetched {$result['processed']} properties successfully in {$duration}s",
                'total' => count($urls),
                'processed' => $result['processed'],
                'saved_to_db' => $savedCount,
                'failed' => $result['failed'],
                'duration' => $duration,
                'properties' => $finalProperties
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error("Batch fetch error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during batch fetch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch property data from source website
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sync(Request $request)
    {
        try {
            // Validate the request
            $request->validate([
                'property_url' => 'required|url'
            ]);

            $propertyUrl = $request->input('property_url');
            
            Log::info("Syncing property data for URL: " . $propertyUrl);
            
            // Fetch property data using the service
            $propertyData = $this->propertyService->fetchPropertyData($propertyUrl);
            
            if ($propertyData['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Property data fetched successfully',
                    'data' => $propertyData
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch property data',
                    'error' => $propertyData['error'] ?? 'Unknown error'
                ], 200);
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid property URL',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error("Sync error: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching property data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process sold_link URLs from properties table and populate sold data tables
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processSoldLinks(Request $request)
    {
        try {
            set_time_limit(600); // 10 minutes
            
            $limit = $request->input('limit', null);
            
            // Get properties with sold_link
            $query = \App\Models\Property::whereNotNull('sold_link')
                ->where('sold_link', '!=', '');
                
            if ($limit) {
                $query->limit((int)$limit);
            }
            
            $properties = $query->get();
            $totalProperties = $properties->count();
            
            if ($totalProperties === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No properties with sold_link found',
                    'processed' => 0,
                    'sold_properties' => 0,
                    'sold_prices' => 0
                ]);
            }
            
            Log::info("Processing {$totalProperties} properties with sold_link");
            
            $processedCount = 0;
            $soldPropertiesCount = 0;
            $soldPricesCount = 0;
            $errors = [];
            
            foreach ($properties as $property) {
                try {
                    Log::info("Processing sold link for property {$property->property_id}: {$property->sold_link}");
                    
                    // Scrape sold properties from the sold link
                    $soldData = $this->propertyService->scrapeSoldProperties($property->sold_link, $property->property_id);
                    
                    if (empty($soldData)) {
                        Log::info("No sold data found for property {$property->property_id}");
                        continue;
                    }
                    
                    Log::info("Found " . count($soldData) . " sold properties for property {$property->property_id}");
                    
                    foreach ($soldData as $soldProp) {
                        try {
                            // Validate we have minimum required data
                            if (empty($soldProp['location'])) {
                                continue;
                            }
                            
                            // Save to properties_sold
                            // Link to parent property via property_id, use location for uniqueness
                            $soldRecord = PropertySold::updateOrCreate(
                                [
                                    'property_id' => $property->property_id,
                                    'location' => $soldProp['location'] ?? ''
                                ],
                                [
                                    'source_sold_link' => $property->sold_link,
                                    'house_number' => $soldProp['house_number'] ?? '',
                                    'road_name' => $soldProp['road_name'] ?? '',
                                    'image_url' => $soldProp['image_url'] ?? null,
                                    'property_type' => $soldProp['property_type'] ?? '',
                                    'bedrooms' => $soldProp['bedrooms'],
                                    'bathrooms' => $soldProp['bathrooms'],
                                    'tenure' => $soldProp['tenure'] ?? '',
                                    'detail_url' => $soldProp['detail_url'] ?? null
                                ]
                            );
                            
                            $soldPropertiesCount++;
                            
                            // Save transaction history
                            if (!empty($soldProp['transactions'])) {
                                // Clear old transactions
                                PropertySoldPrice::where('sold_property_id', $soldRecord->id)->delete();
                                
                                foreach ($soldProp['transactions'] as $trans) {
                                    if (!empty($trans['price']) || !empty($trans['date'])) {
                                        PropertySoldPrice::create([
                                            'sold_property_id' => $soldRecord->id,
                                            'sold_price' => $trans['price'] ?? '',
                                            'sold_date' => $trans['date'] ?? ''
                                        ]);
                                        $soldPricesCount++;
                                    }
                                }
                            }
                            
                        } catch (\Exception $e) {
                            Log::error("Error saving sold property: " . $e->getMessage());
                            $errors[] = $e->getMessage();
                        }
                    }
                    
                    $processedCount++;
                    
                    // Small delay to be respectful to the server
                    usleep(300000); // 0.3 second
                    
                } catch (\Exception $e) {
                    Log::error("Error processing sold link for property {$property->property_id}: " . $e->getMessage());
                    $errors[] = "Property {$property->property_id}: " . $e->getMessage();
                }
            }
            
            Log::info("Completed processing sold links. Processed: {$processedCount}, Sold: {$soldPropertiesCount}, Prices: {$soldPricesCount}");
            
            return response()->json([
                'success' => true,
                'message' => "Processed {$processedCount} properties with sold links",
                'total_properties' => $totalProperties,
                'processed' => $processedCount,
                'sold_properties' => $soldPropertiesCount,
                'sold_prices' => $soldPricesCount,
                'errors' => count($errors) > 0 ? $errors : null
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error processing sold links: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing sold links',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync/fetch property URLs from Rightmove (merged from PropertyController)
     * Uses default Bath URL for testing
     */
    public function syncUrls(Request $request = null)
    {
        set_time_limit(300); // 5 minutes
        
        $baseUrl = 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Bath%2C+Somerset&useLocationIdentifier=true&locationIdentifier=REGION%5E116&radius=0.0&_includeSSTC=on';
        
        if ($request && $request->has('url')) {
            $baseUrl = $request->input('url');
        }

        return $this->scrapePropertyUrls($baseUrl);
    }

    /**
     * Scrape property URLs from Rightmove search results page (merged from PropertyController)
     * 
     * @param string $baseUrl The Rightmove search URL
     * @param bool $fetchAll Whether to fetch all pages or just the first one
     * @return \Illuminate\Http\JsonResponse
     */
    public function scrapePropertyUrls($baseUrl, $fetchAll = true) 
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'connect_timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-GB,en;q=0.9',
                    'Cache-Control' => 'no-cache',
                ]
            ]);
            
            $allUrls = [];
            $maxPages = $fetchAll ? 50 : 1; 
            $consecutiveEmptyPages = 0;
            $maxConsecutiveEmptyPages = 5;
            
            for ($page = 0; $page < $maxPages; $page++) {
                $retryCount = 0;
                $maxRetries = 3;
                $pageSuccess = false;
                
                while ($retryCount < $maxRetries && !$pageSuccess) {
                    try {
                        $index = $page * 24;
                        
                        $cleanUrl = preg_replace('/([?&])index=\d+(&|$)/', '$1', $baseUrl);
                        $cleanUrl = rtrim($cleanUrl, '&?');
                        
                        $separator = (strpos($cleanUrl, '?') !== false) ? '&' : '?';
                        $url = $page === 0 ? $cleanUrl : $cleanUrl . $separator . 'index=' . $index;
                        
                        Log::info("Fetching page: " . ($page + 1) . " (Attempt " . ($retryCount + 1) . ") - URL: " . $url);
                        
                        $response = $client->request('GET', $url);
                        $html = $response->getBody()->getContents();
                        $pageSuccess = true;

                        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
                        $pagePropertiesCount = 0;
                        
                        $nextDataScript = $crawler->filter('script#__NEXT_DATA__')->first();
                        
                        if ($nextDataScript->count() > 0) {
                            $jsonString = $nextDataScript->html();
                            $jsonData = json_decode($jsonString, true);
                            
                            if ($jsonData && isset($jsonData['props']['pageProps']['searchResults']['properties'])) {
                                $propertiesData = $jsonData['props']['pageProps']['searchResults']['properties'];
                                $pagePropertiesCount = count($propertiesData);
                                
                                foreach ($propertiesData as $prop) {
                                    try {
                                        if (isset($prop['propertyUrl'])) {
                                            $propertyUrl = 'https://www.rightmove.co.uk' . $prop['propertyUrl'];
                                            $propertyId = $prop['id'] ?? null;
                                            
                                            $allUrls[] = [
                                                'id' => $propertyId,
                                                'url' => $propertyUrl,
                                                'title' => $prop['propertyTypeFullDescription'] ?? 'Property for sale',
                                                'price' => $prop['price']['displayPrices'][0]['displayPrice'] ?? 'Price on request',
                                                'address' => $prop['displayAddress'] ?? 'Address not available',
                                            ];
                                        }
                                    } catch (\Exception $e) {
                                        Log::warning("Error processing property: " . $e->getMessage());
                                        continue;
                                    }
                                }
                                
                                Log::info("Page " . ($page + 1) . " processed. Found " . $pagePropertiesCount . " properties. Total so far: " . count($allUrls));
                            }
                        } else {
                            // Fallback method
                            $scripts = $crawler->filter('script')->each(function (\Symfony\Component\DomCrawler\Crawler $node) {
                                return $node->html();
                            });
                            
                            foreach ($scripts as $script) {
                                if (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $script, $matches)) {
                                    $jsonData = json_decode($matches[1], true);
                                    
                                    if ($jsonData && isset($jsonData['props']['pageProps']['searchResults']['properties'])) {
                                        $propertiesData = $jsonData['props']['pageProps']['searchResults']['properties'];
                                        $pagePropertiesCount = count($propertiesData);
                                        
                                        foreach ($propertiesData as $prop) {
                                            try {
                                                if (isset($prop['propertyUrl'])) {
                                                    $propertyUrl = 'https://www.rightmove.co.uk' . $prop['propertyUrl'];
                                                    $propertyId = $prop['id'] ?? null;
                                                    
                                                    $allUrls[] = [
                                                        'id' => $propertyId,
                                                        'url' => $propertyUrl,
                                                        'title' => $prop['propertyTypeFullDescription'] ?? 'Property for sale',
                                                        'price' => $prop['price']['displayPrices'][0]['displayPrice'] ?? 'Price on request',
                                                        'address' => $prop['displayAddress'] ?? 'Address not available',
                                                    ];
                                                }
                                            } catch (\Exception $e) {
                                                continue;
                                            }
                                        }
                                        
                                        Log::info("Page " . ($page + 1) . " processed via fallback. Found " . $pagePropertiesCount . " properties.");
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if ($pagePropertiesCount === 0) {
                            $consecutiveEmptyPages++;
                            Log::info("Empty page detected. Consecutive empty pages: " . $consecutiveEmptyPages);
                            
                            if ($consecutiveEmptyPages >= $maxConsecutiveEmptyPages) {
                                Log::info("Reached " . $maxConsecutiveEmptyPages . " consecutive empty pages. Stopping.");
                                break;
                            }
                        } else {
                            $consecutiveEmptyPages = 0;
                        }
                        
                    } catch (\Exception $e) {
                        Log::error("Error fetching page " . ($page + 1) . " (Attempt " . ($retryCount + 1) . "): " . $e->getMessage());
                        $retryCount++;
                        
                        if ($retryCount < $maxRetries) {
                            Log::info("Retrying in 2 seconds...");
                            sleep(2);
                        }
                    }
                }
                
                // CORRECTLY BREAK OUTER LOOP if too many empty pages
                if ($consecutiveEmptyPages >= $maxConsecutiveEmptyPages) {
                    Log::info("Stopping pagination loop due to consecutive empty pages.");
                    break;
                }
                
                if (!$pageSuccess) {
                    $consecutiveEmptyPages++;
                    Log::warning("Failed to fetch page " . ($page + 1) . " after " . $maxRetries . " attempts");
                    
                    if ($consecutiveEmptyPages >= $maxConsecutiveEmptyPages) {
                        Log::info("Too many consecutive failures. Stopping pagination.");
                        break;
                    }
                    continue;
                }
                
                if ($page < $maxPages - 1) {
                    Log::info("Waiting 1 second before next page...");
                    sleep(1);
                }
            }
            
            if (empty($allUrls)) {
                Log::error("No URLs found after scraping all pages");
                return response()->json([
                    'success' => false,
                    'message' => 'No property URLs found. The website structure may have changed.',
                    'count' => 0
                ], 200);
            }
            
            // Remove duplicates based on ID
            $uniqueUrls = [];
            $seenIds = [];
            foreach ($allUrls as $urlData) {
                if (!in_array($urlData['id'], $seenIds)) {
                    $uniqueUrls[] = $urlData;
                    $seenIds[] = $urlData['id'];
                }
            }

            Log::info("Successfully fetched " . count($uniqueUrls) . " unique property URLs");

            return response()->json([
                'success' => true,
                'message' => 'Property URLs fetched successfully',
                'count' => count($uniqueUrls),
                'urls' => $uniqueUrls
            ]);

        } catch (\Exception $e) {
            Log::error("Scrape error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error occurred while fetching URLs',
                'error' => $e->getMessage(),
                'count' => 0
            ], 200);
        }
    }
}
