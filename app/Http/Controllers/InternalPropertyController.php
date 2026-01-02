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
     * OPTIMIZED for fast loading with all sold data
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadPropertiesFromDatabase(Request $request)
    {
        try {
            set_time_limit(300); // 5 minutes for large datasets
            
        $searchId = $request->input('search_id');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 999999); // SET TO UNLIMITED (effectively)
        
        Log::info("loadPropertiesFromDatabase: search_id=" . ($searchId ?? 'all') . ", page={$page}, per_page={$perPage}");
            
            // Build query for properties
            $query = \App\Models\Property::query();
            
            if ($searchId) {
                $query->where('filter_id', $searchId);
            }
            
            // Get total count before pagination
            $totalCount = $query->count();
            Log::info("loadPropertiesFromDatabase: Total matching properties found: {$totalCount}");
            
            if ($totalCount === 0) {
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
            
            // OPTIMIZED: Use select to only get needed columns, efficient eager loading
            $properties = $query
                ->select(['id', 'location', 'house_number', 'road_name', 'price', 'sold_link', 
                         'filter_id', 'bedrooms', 'bathrooms', 'property_type', 'size', 'tenure',
                         'council_tax', 'parking', 'garden', 'accessibility', 'ground_rent',
                         'annual_service_charge', 'lease_length', 'key_features', 'description'])
                ->with([
                    'images:id,property_id,image_link',
                    'soldProperties' => function($q) {
                        $q->select(['id', 'property_id', 'location', 'property_type', 'bedrooms', 'bathrooms', 'tenure', 'image_url', 'map_url', 'detail_url']);
                    },
                    'soldProperties.prices:id,sold_property_id,sold_price,sold_date'
                ])
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
            
            // Log stats
            $propsWithSold = $properties->filter(fn($p) => $p->soldProperties->count() > 0)->count();
            Log::info("loadPropertiesFromDatabase: Page {$page} loaded {$properties->count()} properties, {$propsWithSold} with sold data");
            
            // Format properties using shared formatting method
            $formattedProperties = $this->formatPropertiesForResponse($properties);
            
            $totalPages = ceil($totalCount / $perPage);
            $hasMore = $page < $totalPages;
            
            return response()->json([
                'success' => true,
                'message' => "Loaded {$properties->count()} properties (page {$page}/{$totalPages})",
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
            $url = $urlMap[$prop->id] ?? "https://www.rightmove.co.uk/properties/{$prop->id}";
            
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
            $avgSoldPrice = 0;
            $discountMetric = null;
            $salesCountInPeriod = 0;
            
            if ($prop->soldProperties && $prop->soldProperties->count() > 0) {
                Log::info("Property {$prop->id} has {$prop->soldProperties->count()} sold properties via relationship");
                
                // Group by location to find the unique properties
                $uniqueLatestSalesInPeriod = [];
                $uniqueLatestSalesSameType = []; // Specifically for same-type benchmarking
                
                $subjectType = strtolower($prop->property_type ?? '');
                
                $soldProperties = $prop->soldProperties
                    ->unique('location')  // Deduplicate by location for the list
                    ->values()
                    ->map(function($sold) use (&$uniqueLatestSalesInPeriod, &$uniqueLatestSalesSameType, $subjectType) {
                    $latestSaleInPeriod = null;
                    $latestTimestamp = 0;
                    
                    $prices = $sold->prices ? $sold->prices->map(function($price) use (&$latestSaleInPeriod, &$latestTimestamp) {
                        // Parse date for period check (Jan 2020 - 2025)
                        $timestamp = strtotime($price->sold_date);
                        if ($timestamp) {
                            $year = (int)date('Y', $timestamp);
                            if ($year >= 2020 && $year <= 2025) {
                                if ($timestamp > $latestTimestamp) {
                                    $latestTimestamp = $timestamp;
                                    // Extract numeric value robustly
                                    $priceStr = str_replace(',', '', $price->sold_price);
                                    if (preg_match('/(\d+(?:\.\d+)?)/', $priceStr, $matches)) {
                                        $numericPrice = floatval($matches[1]);
                                        $latestSaleInPeriod = $numericPrice;
                                    }
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
                        
                        // Check if types match using BROAD CATEGORIES for better matching
                        $soldType = strtolower($sold->property_type ?? '');
                        
                        // Define property type categories
                        $houseTypes = ['house', 'detached', 'semi-detached', 'terraced', 'bungalow', 'cottage', 'villa', 'end terrace', 'mid terrace'];
                        $flatTypes = ['flat', 'apartment', 'maisonette', 'studio', 'penthouse'];
                        
                        $isSubjectHouse = false;
                        $isSubjectFlat = false;
                        $isSoldHouse = false;
                        $isSoldFlat = false;
                        
                        foreach ($houseTypes as $ht) {
                            if (str_contains($subjectType, $ht)) $isSubjectHouse = true;
                            if (str_contains($soldType, $ht)) $isSoldHouse = true;
                        }
                        foreach ($flatTypes as $ft) {
                            if (str_contains($subjectType, $ft)) $isSubjectFlat = true;
                            if (str_contains($soldType, $ft)) $isSoldFlat = true;
                        }
                        
                        // Match if both are houses or both are flats
                        if (($isSubjectHouse && $isSoldHouse) || ($isSubjectFlat && $isSoldFlat)) {
                            $uniqueLatestSalesSameType[] = $latestSaleInPeriod;
                        }
                    }
                    
                    return [
                        'id' => $sold->id,
                        'property_id' => $sold->property_id,
                        'location' => $sold->location,
                        'house_number' => $sold->house_number,
                        'road_name' => $sold->road_name,
                        'image_url' => $sold->image_url,
                        'map_url' => $sold->map_url,
                        'property_type' => $sold->property_type,
                        'bedrooms' => $sold->bedrooms,
                        'bathrooms' => $sold->bathrooms,
                        'tenure' => $sold->tenure,
                        'detail_url' => $sold->detail_url,
                        'prices' => $prices
                    ];
                })->toArray();
                
                // Use same-type average if available (much more accurate), otherwise area average
                $benchmarkSales = count($uniqueLatestSalesSameType) > 0 ? $uniqueLatestSalesSameType : $uniqueLatestSalesInPeriod;
                
                if (count($benchmarkSales) > 0) {
                    $avgSoldPrice = round(array_sum($benchmarkSales) / count($benchmarkSales));
                    $salesCountInPeriod = count($benchmarkSales);
                    
                    // Parse advertised price - handle "Offers over", "Guide price", etc.
                    $advertisedPriceStr = $prop->price ?? '';
                    $advertisedPrice = 0;
                    if (preg_match('/([\d,]+(?:\.\d+)?)/', $advertisedPriceStr, $matches)) {
                        $advertisedPrice = floatval(str_replace(',', '', $matches[1]));
                    }
                    
                    if ($advertisedPrice > 0 && $avgSoldPrice > 0) {
                        // SWAPPED logic as per user request:
                        // (Advertised - Average) / Average
                        // Positive result = DISCOUNT (priced above average - GREEN)
                        // Negative result = PREMIUM (priced below average - RED)
                        $discountMetric = (($advertisedPrice - $avgSoldPrice) / $avgSoldPrice) * 100;
                        
                        // Debug log the calculation
                        Log::info("DISCOUNT CALC for Property {$prop->id}: Advertised=£{$advertisedPrice}, AvgSold=£{$avgSoldPrice}, Discount=" . round($discountMetric, 2) . "% (" . ($discountMetric >= 0 ? 'DISCOUNT' : 'PREMIUM') . ")");
                    } else {
                        Log::warning("DISCOUNT CALC FAILED for Property {$prop->id}: Advertised={$advertisedPrice}, AvgSold={$avgSoldPrice}");
                    }
                }
            } else {
                // Debug: Log why no sold properties are found
                Log::info("Property {$prop->id} has NO sold properties. sold_link: " . ($prop->sold_link ?? 'NULL'));
            }
            
            // Extract house number and road name using more robust logic
            $address = $prop->location ?? 'Unknown location';
            $houseNumber = '';
            $roadName = $address;
            
            // Try to use already parsed database values if available
            if (!empty($prop->house_number) || !empty($prop->road_name)) {
                $houseNumber = $prop->house_number ?? '';
                $roadName = $prop->road_name ?? $address;
            } else {
                // On-the-fly parsing if columns are empty
                if (preg_match('/^(Flat|Apartment|Suite|Unit)\s+([^\s,]+),\s*(.+)$/i', $address, $matches)) {
                    $houseNumber = $matches[1] . ' ' . $matches[2];
                    $roadName = $matches[3];
                } elseif (preg_match('/^([0-9a-z\/-]+),\s*(.+)$/i', $address, $matches)) {
                    $houseNumber = $matches[1];
                    $roadName = $matches[2];
                }
            }

            return [
                'id' => $prop->id,
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
                'discount_metric' => $discountMetric !== null ? round($discountMetric, 1) : null,
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
                // We now replace ONLY the data for the specific filter ID being imported
                // to avoid erasing the overall data from the database.
                try {
                    Log::info("Performing scoped deletion for filter_id: " . ($searchId ?? 'NULL'));
                    
                    // 1. Delete URLs for this filter
                    if ($searchId) {
                        \App\Models\Url::where('filter_id', $searchId)->delete();
                    } else {
                        // If no searchId, only delete URLs that also have no filter_id
                        \App\Models\Url::whereNull('filter_id')->delete();
                    }
                    
                    // 2. Find properties for this filter to clean up related data
                    $propQuery = \App\Models\Property::query();
                    if ($searchId) {
                        $propQuery->where('filter_id', $searchId);
                    } else {
                        $propQuery->whereNull('filter_id');
                    }
                    $propertyIds = $propQuery->pluck('id');
                    
                    if ($propertyIds->isNotEmpty()) {
                        Log::info("Found " . $propertyIds->count() . " properties to clear for filter_id: " . ($searchId ?? 'NULL'));
                        
                        // 3. Delete Sold Prices (no direct cascade from Property to SoldPrice)
                        $soldIds = \App\Models\PropertySold::whereIn('property_id', $propertyIds)->pluck('id');
                        if ($soldIds->isNotEmpty()) {
                            \App\Models\PropertySoldPrice::whereIn('sold_property_id', $soldIds)->delete();
                            \App\Models\PropertySold::whereIn('id', $soldIds)->delete();
                        }
                        
                        // 4. Delete Images (should cascade but being explicit for safety)
                        \App\Models\PropertyImage::whereIn('property_id', $propertyIds)->delete();
                        
                        // 5. Delete Properties
                        \App\Models\Property::whereIn('id', $propertyIds)->delete();
                    }
                    
                    Log::info("Scoped deletion complete. Old data for this filter has been replaced.");
                    
                } catch (\Exception $e) {
                    Log::error("Error during scoped deletion: " . $e->getMessage());
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
            set_time_limit(0); // UNLIMITED time for large batch processing (was 600 = 10 min)
            ini_set('memory_limit', '512M'); // Increase memory limit for large batches
            
            $request->validate([
                'urls' => 'required|array',
                'urls.*.url' => 'required|url',
                'filter_id' => 'nullable|integer|exists:saved_searches,id'
            ]);

            $urls = $request->input('urls');
            $filterId = $request->input('filter_id');
            
            // New parameter: whether to skip sold history scraping for speed during mass imports
            $skipSold = $request->input('skip_sold', false) === true || $request->input('skip_sold', 'true') === true;
            
            // AUTO-SKIP sold data if importing MORE than 200 properties to prevent timeouts
            if (count($urls) > 200) {
                $skipSold = true;
                Log::info("Mass import detected (" . count($urls) . " properties). Auto-skipping sold data scraping for speed.");
            }

            $startTime = microtime(true);
            
            Log::info("Starting CONCURRENT batch fetch for " . count($urls) . " properties" . ($skipSold ? " (Skipping sold data)" : ""));

            // Use the service's concurrent fetch method for MASSIVE speed improvement
            $result = $this->propertyService->fetchPropertiesConcurrently($urls);
            
            // SAVE FETCHED PROPERTIES TO DATABASE
            $savedCount = 0;
            if ($result['processed'] > 0 && !empty($result['properties'])) {
                foreach ($result['properties'] as $propData) {
                    try {
                        // Extract ID from scraped data or URL
                        $propId = $propData['id'] ?? null;
                        if (!$propId && preg_match('/properties\/(\d+)/', $propData['url'], $matches)) {
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
                                ['id' => $propId],
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
                            
                            if ($property) {
                                $savedCount++;
                                Log::debug("Successfully saved property {$propId} to database.");
                            } else {
                                Log::error("Failed to save property {$propId} to database (updateOrCreate returned null)");
                            }
                            
                            // SCRAPE & SAVE SOLD HISTORY DURING IMPORT
                            // ONLY if not explicitly skipped for speed
                            if (!$skipSold && !empty($propData['sold_link'])) {
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
                                                        'image_url' => $soldProp['image_url'] ?? null,
                                                        'map_url' => $soldProp['map_url'] ?? null,
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
                    $dbProperties = \App\Models\Property::whereIn('id', $propertyIds)
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
            
            $limit = $request->input('limit', 10);
            $propertyId = $request->input('property_id');
            
            // Get properties with sold_link or specific property
            $query = \App\Models\Property::query();

            // If finding generic ones to process, prefer those with sold_link, but strict only if bulk
            if (!$propertyId) {
                $query->whereNotNull('sold_link')->where('sold_link', '!=', '');
            }
                
            if ($propertyId) {
                $query->where('id', $propertyId);
            } elseif ($limit) {
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
                    Log::info("Processing sold link for property {$property->id}: " . ($property->sold_link ?? 'Missing'));
                    
                    // Deep Search Algorithm: If sold_link is missing, try to construct it from location
                    if (empty($property->sold_link)) {
                        $postcode = null;
                        // Extract postcode from location (e.g. "Bath, BA1 1AA" or "BA1 1AA")
                        if (preg_match('/([A-Z]{1,2}[0-9][0-9A-Z]?\s*[0-9][A-Z]{2})/i', $property->location, $matches)) {
                            $postcode = strtoupper(str_replace(' ', '', $matches[1])); // Normalize to BA11AA
                        }
                        
                        if ($postcode && strlen($postcode) >= 5) {
                            $outcode = substr($postcode, 0, strlen($postcode) - 3);
                            $incode = substr($postcode, -3);
                            $derivedLink = "https://www.rightmove.co.uk/house-prices/{$outcode}-{$incode}.html";
                            
                            Log::info("Deep Search: Derived sold link for property {$property->id} from postcode {$postcode}: {$derivedLink}");
                            
                            // Save this derived link to the property so we don't have to guess again
                            $property->sold_link = $derivedLink;
                            $property->save();
                        } else {
                            Log::warning("Deep Search Failed: Could not extract valid postcode from location: " . $property->location);
                            continue;
                        }
                    }
                    
                    // Scrape sold properties from the sold link
                    $soldData = $this->propertyService->scrapeSoldProperties($property->sold_link, $property->id);
                    
                    if (empty($soldData)) {
                        Log::info("No sold data found for property {$property->id}");
                        continue;
                    }
                    
                    Log::info("Found " . count($soldData) . " sold properties for property {$property->id}");
                    
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
                                    'property_id' => $property->id,
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
                            // Deep Scrape for missing details if this is a targeted import
                            if ($propertyId && !empty($soldProp['detail_url']) && 
                                (empty($soldRecord->bedrooms) || empty($soldRecord->bathrooms) || empty($soldRecord->image_url) || $soldRecord->image_url === 'https://via.placeholder.com/80x60/eee/999?text=No+Photo')) {
                                
                                Log::info("Deep scraping details for sold property: " . $soldRecord->location);
                                try {
                                    $deepData = $this->propertyService->fetchPropertyData($soldProp['detail_url']);
                                    if ($deepData['success']) {
                                        $soldRecord->update([
                                            'bedrooms' => $deepData['bedrooms'] ?: $soldRecord->bedrooms,
                                            'bathrooms' => $deepData['bathrooms'] ?: $soldRecord->bathrooms,
                                            'tenure' => $deepData['tenure'] ?: $soldRecord->tenure,
                                            'image_url' => (!empty($deepData['images']) ? $deepData['images'][0] : $soldRecord->image_url),
                                        ]);
                                    }
                                    
                                    // small pause between deep scratches
                                    usleep(100000); // 0.1s
                                } catch (\Exception $deepEx) {
                                    Log::warning("Failed deep scrape for {$soldProp['detail_url']}: " . $deepEx->getMessage());
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
                    Log::error("Error processing sold link for property {$property->id}: " . $e->getMessage());
                    $errors[] = "Property {$property->id}: " . $e->getMessage();
                }
            }
            
            Log::info("Completed processing sold links. Processed: {$processedCount}, Sold: {$soldPropertiesCount}, Prices: {$soldPricesCount}");
            
            $responseData = [
                'success' => true,
                'message' => "Processed {$processedCount} properties with sold links",
                'total_properties' => $totalProperties,
                'processed' => $processedCount,
                'sold_properties' => $soldPropertiesCount,
                'sold_prices' => $soldPricesCount,
                'errors' => count($errors) > 0 ? $errors : null
            ];

            if ($propertyId) {
                $updatedProperty = \App\Models\Property::with(['images', 'soldProperties.prices'])->find($propertyId);
                if ($updatedProperty) {
                    $responseData['property'] = $this->formatPropertiesForResponse(collect([$updatedProperty]))[0];
                }
            }

            return response()->json($responseData);
            
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
                'timeout' => 120, // INCREASED: 2 minutes to handle rate limiting
                'connect_timeout' => 60, // INCREASED: 1 minute for slow connections
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-GB,en;q=0.9',
                    'Cache-Control' => 'no-cache',
                ]
            ]);
            
            $allUrls = [];
            $maxPages = $fetchAll ? PHP_INT_MAX : 1; // UNLIMITED: Scrape all pages until no more results found
            $consecutiveEmptyPages = 0;
            $maxConsecutiveEmptyPages = 3; // Reduced to 3 for faster exit if empty
            
            // Increase memory and time for mass URL discovery
            set_time_limit(0); 
            ini_set('memory_limit', '1024M');
            
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
                            
                            // Capture first page data for total counts
                            if ($page === 0) {
                                $firstPageJsonData = $jsonData;
                            }
                            
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

                                    // Capture first page data for total counts (fallback)
                                    if ($page === 0 && !isset($firstPageJsonData)) {
                                        $firstPageJsonData = $jsonData;
                                    }
                                    
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
                            Log::info("Retrying in 5 seconds...");
                            sleep(5); // INCREASED: More respectful delay to avoid rate limiting
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
                    Log::info("Waiting 2 seconds before next page...");
                    sleep(2); // INCREASED: More delay between pages to avoid rate limiting
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

// Extract total result count from the first page's data
            $totalResultCount = null;
            if (isset($firstPageJsonData['props']['pageProps']['searchResults']['resultCount'])) {
                $totalResultCount = str_replace(',', '', $firstPageJsonData['props']['pageProps']['searchResults']['resultCount']);
            }

            Log::info("Successfully fetched " . count($uniqueUrls) . " unique property URLs. Total Results on Source: " . ($totalResultCount ?? 'Unknown'));

            return response()->json([
                'success' => true,
                'message' => 'Property URLs fetched successfully',
                'count' => count($uniqueUrls),
                'total_result_count' => $totalResultCount,
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

    public function importSoldPropertyDetails(Request $request)
    {
        try {
            $request->validate([
                'sold_property_id' => 'required|exists:properties_sold,id',
                'detail_url' => 'required|url'
            ]);

            $soldPropertyId = $request->input('sold_property_id');
            $detailUrl = $request->input('detail_url');

            Log::info("Importing details for sold property ID: {$soldPropertyId} from: {$detailUrl}");

            // Fetch data from source
            $scrapedData = $this->propertyService->fetchPropertyData($detailUrl);

            if (!$scrapedData['success']) {
                throw new \Exception($scrapedData['error'] ?? 'Failed to fetch sold property details');
            }

            // Update the sold property record
            $soldProperty = PropertySold::findOrFail($soldPropertyId);
            $soldProperty->update([
                'property_type' => $scrapedData['property_type'] ?? $soldProperty->property_type,
                'bedrooms' => $scrapedData['bedrooms'] ?? $soldProperty->bedrooms,
                'bathrooms' => $scrapedData['bathrooms'] ?? $soldProperty->bathrooms,
                'tenure' => $scrapedData['tenure'] ?? $soldProperty->tenure,
                'image_url' => (!empty($scrapedData['images']) ? $scrapedData['images'][0] : $soldProperty->image_url),
            ]);

            // Format transactions if available in scraped data
            // (Note: Usually sold_link page has transactions, detail page might have them too)
            
            return response()->json([
                'success' => true,
                'message' => 'Sold property details imported successfully',
                'sold_property' => $soldProperty->load('prices')
            ]);

        } catch (\Exception $e) {
            Log::error("Error importing sold property details: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
