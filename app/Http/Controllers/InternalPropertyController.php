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
            
            Log::info("loadPropertiesFromDatabase: Loading properties for search_id: " . ($searchId ?? 'all'));
            
            // Build query for properties
            $query = \App\Models\Property::query();
            
            if ($searchId) {
                $query->where('filter_id', $searchId);
            }
            
            // Get properties with their images and sold properties
            $properties = $query->with(['images', 'soldProperties.prices'])->get();
            
            if ($properties->count() === 0) {
                Log::info("loadPropertiesFromDatabase: No properties found in database");
                return response()->json([
                    'success' => true,
                    'message' => 'No properties found in database',
                    'count' => 0,
                    'properties' => [],
                    'source' => 'database'
                ]);
            }
            
            Log::info("loadPropertiesFromDatabase: Found " . $properties->count() . " properties in database");
            
            // Format properties to match the expected frontend format
            $formattedProperties = $properties->map(function($prop) {
                // Build URL from property_id
                $url = "https://www.rightmove.co.uk/properties/{$prop->property_id}";
                
                // Get images
                $images = $prop->images ? $prop->images->pluck('image_link')->toArray() : [];
                
                // Parse key_features if stored as JSON
                $keyFeatures = [];
                if ($prop->key_features) {
                    $decoded = json_decode($prop->key_features, true);
                    if (is_array($decoded)) {
                        $keyFeatures = $decoded;
                    } elseif (is_string($prop->key_features)) {
                        $keyFeatures = [$prop->key_features];
                    }
                }
                
                // Format sold properties with their prices for JavaScript consumption
                // Also deduplicate by location to prevent same property showing multiple times
                $soldProperties = [];
                if ($prop->soldProperties && $prop->soldProperties->count() > 0) {
                    Log::info("Property {$prop->property_id} has {$prop->soldProperties->count()} sold properties via relationship");
                    $soldProperties = $prop->soldProperties
                        ->unique('location')  // Deduplicate by location
                        ->values()
                        ->map(function($sold) {
                        return [
                            'id' => $sold->id,
                            'property_id' => $sold->property_id,
                            'location' => $sold->location,
                            'property_type' => $sold->property_type,
                            'bedrooms' => $sold->bedrooms,
                            'bathrooms' => $sold->bathrooms,
                            'tenure' => $sold->tenure,
                            'detail_url' => $sold->detail_url,
                            'prices' => $sold->prices ? $sold->prices->map(function($price) {
                                return [
                                    'sold_price' => $price->sold_price,
                                    'sold_date' => $price->sold_date
                                ];
                            })->toArray() : []
                        ];
                    })->toArray();
                } else {
                    // Debug: Log why no sold properties are found
                    Log::info("Property {$prop->property_id} has no sold properties. sold_link: " . ($prop->sold_link ?? 'NULL'));
                }
                
                return [
                    'id' => $prop->property_id,
                    'url' => $url,
                    'address' => $prop->location ?? 'Unknown location',
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
                    'images' => $images,
                    'loading' => false, // Data is fully loaded from DB
                    'from_database' => true
                ];
            })->toArray();
            
            return response()->json([
                'success' => true,
                'message' => 'Properties loaded from database',
                'count' => count($formattedProperties),
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
                Log::info("Cache miss - triggering URL fetch from PropertyController");
                
                // Try to return partial data if available while fetching in background
                $propertyController = new \App\Http\Controllers\PropertyController();
                $response = $propertyController->sync();
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
     * Fetch URLs from PropertyController (optimized)
     * Uses caching to avoid long-running scraping on every request
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchUrls(Request $request)
    {
        try {
            $searchId = $request->input('search_id');
            $import = $request->input('import') === 'true' || $request->input('import') === true;
            
            // Cache key removed
            // $cacheKey = $searchId ? "property_urls_search_{$searchId}" : 'property_urls_list';
            
            // IF NOT IMPORT: Check DB first, then cache
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
                            'id' => null, // We might not have ID stored in 'urls' table, but we can try to extract or join with 'properties' table if needed
                            // Ideally, we should join with 'properties' table to get more info if available
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

                // If not in DB, cache check removed
                /*
                $cached = \Cache::get($cacheKey);
                
                if ($cached && isset($cached['urls']) && count($cached['urls']) > 0) {
                    Log::info("Returning " . count($cached['urls']) . " URLs from cache");
                    return response()->json([
                        'success' => true,
                        'message' => 'Property URLs loaded from cache',
                        'count' => count($cached['urls']),
                        'urls' => $cached['urls'],
                        'cached' => true,
                        'cached_at' => $cached['cached_at'] ?? null
                    ]);
                }
                */
            }
            
            // If explicit import OR no data found, proceed to fetch
            
            // If explicit import requested, we continue.
            // If NOT import (just page load) and no data, we might want to return empty 
            // instead of triggering a long scrape automatically?
            // User requested: "when i import data... i donot further need to import directly... i want to show the imported data"
            // So if checking DB failed, and it's NOT an import request, we should probably return empty so user sees "Empty State"
            if (!$import) {
                 return response()->json([
                    'success' => true, // Success but empty
                    'message' => 'No properties found in database.',
                    'count' => 0,
                    'urls' => [],
                    'source' => 'database'
                ]);
            }

            Log::info("Fetching fresh URLs from PropertyController (Import: Yes)");
            
            // If no cache or importing, fetch from PropertyController
            set_time_limit(600); // 10 minutes
            
            $propertyController = new \App\Http\Controllers\PropertyController();
            
            if ($searchId) {
                $search = SavedSearch::find($searchId);
                if ($search && $search->updates_url) {
                    Log::info("Fetching URLs for Saved Search #{$searchId}: {$search->updates_url}");
                    // Always fetch all properties when searching/importing
                    $response = $propertyController->scrapeProperties($search->updates_url, true);
                } else {
                    // Fallback if search not found or no URL
                    $response = $propertyController->sync();
                }
            } else {
                $response = $propertyController->sync();
            }
            
            // Get the JSON data from the response
            $data = $response->getData(true);
            
            if ($data['success'] && isset($data['urls']) && count($data['urls']) > 0) {
                // Add cache timestamp (kept for info but not used for caching)
                $data['cached_at'] = now()->toIso8601String();
                
                // Cache put removed
                // \Cache::put($cacheKey, $data, 1800);
                // Log::info("Cached " . count($data['urls']) . " URLs for future requests");

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
                        
                        // Only clear existing URLs and Properties if we have a specific filter context
                        if ($filterId) {
                            Log::info("Clearing old data for filter ID: {$filterId}");
                            // Delete properties associated with this search
                            \App\Models\Property::where('filter_id', $filterId)->delete();
                            // Delete URLs associated with this search
                            Url::where('filter_id', $filterId)->delete();
                        }
                        
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
            }
            
            return $response;
            
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
                'urls.*.url' => 'required|url'
            ]);

            $urls = $request->input('urls');
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
                            $filterId = null; // Can we pass filter_id here? The batch request doesn't send it currently.
                            // We can try to look it up from the URL table? Or assume global.
                            // For now, save as global or try to find existing URL record.
                            $existingUrl = Url::where('url', $propData['url'])->first();
                            $filterId = $existingUrl ? $existingUrl->filter_id : null;

                            // Update Property
                            $property = \App\Models\Property::updateOrCreate(
                                ['property_id' => $propId],
                                [
                                    'location' => $propData['address'] ?? '',
                                    'price' => $propData['price'] ?? '',
                                    'key_features' => json_encode($propData['key_features'] ?? []),
                                    'description' => $propData['description'] ?? '', 
                                    'sold_link' => $propData['sold_link'] ?? null,
                                    'filter_id' => $filterId,
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
                            
                            // SCRAPE & SAVE SOLD HISTORY
                            if (!empty($propData['sold_link'])) {
                                Log::info("Found sold link for property {$propId}: " . $propData['sold_link']);
                                $soldData = $this->propertyService->scrapeSoldProperties($propData['sold_link'], $propId);
                                
                                if (!empty($soldData)) {
                                    Log::info("Scraped " . count($soldData) . " sold properties for property {$propId}");
                                    
                                    foreach ($soldData as $soldProp) {
                                        try {
                                            // Validate we have minimum required data
                                            if (empty($soldProp['property_id']) && empty($soldProp['location'])) {
                                                Log::warning("Skipping sold property with no ID or location");
                                                continue;
                                            }
                                            
                                            // Save to properties_sold - use updateOrCreate to avoid duplicates
                                            // Using source_sold_link for URL-based matching (simpler than ID)
                                            $soldRecord = PropertySold::updateOrCreate(
                                                [
                                                    'property_id' => $soldProp['property_id'],
                                                    'source_sold_link' => $propData['sold_link']
                                                ],
                                                [
                                                    'location' => $soldProp['location'],
                                                    'property_type' => $soldProp['property_type'],
                                                    'bedrooms' => $soldProp['bedrooms'],
                                                    'bathrooms' => $soldProp['bathrooms'],
                                                    'tenure' => $soldProp['tenure'],
                                                    'detail_url' => $soldProp['detail_url'] ?? null
                                                ]
                                            );
                                            
                                            Log::info("Saved sold property record ID: {$soldRecord->id}, Rightmove ID: {$soldProp['property_id']}");
                                            
                                            // Save transactions (price history)
                                            if (!empty($soldProp['transactions'])) {
                                                // Clear old transactions for this sold property
                                                PropertySoldPrice::where('sold_property_id', $soldRecord->id)->delete();
                                                
                                                foreach ($soldProp['transactions'] as $trans) {
                                                    if (!empty($trans['price']) || !empty($trans['date'])) {
                                                        $priceRecord = PropertySoldPrice::create([
                                                            'sold_property_id' => $soldRecord->id,
                                                            'sold_price' => $trans['price'],
                                                            'sold_date' => $trans['date']
                                                        ]);
                                                        Log::info("Saved sold price: {$trans['price']} on {$trans['date']}");
                                                    }
                                                }
                                                Log::info("Saved " . count($soldProp['transactions']) . " price records for sold property {$soldRecord->id}");
                                            } else {
                                                Log::warning("No transactions found for sold property {$soldRecord->id}");
                                            }
                                        } catch (\Exception $soldEx) {
                                            Log::error("Failed to save sold property data: " . $soldEx->getMessage());
                                            Log::error("Sold property data: " . json_encode($soldProp));
                                        }
                                    }
                                    Log::info("Completed saving sold history for property {$propId}");
                                } else {
                                    Log::warning("No sold data returned from scrapeSoldProperties for property {$propId}");
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

            // Remove description from response to reduce payload size as per user request
            if (!empty($result['properties'])) {
                foreach ($result['properties'] as &$prop) {
                    unset($prop['description']);
                    if (isset($prop['all_details']['description'])) {
                        unset($prop['all_details']['description']);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Fetched {$result['processed']} properties successfully in {$duration}s",
                'total' => count($urls),
                'processed' => $result['processed'],
                'saved_to_db' => $savedCount,
                'failed' => $result['failed'],
                'duration' => $duration,
                'properties' => $result['properties']
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
                            if (empty($soldProp['property_id']) && empty($soldProp['location'])) {
                                continue;
                            }
                            
                            // Save to properties_sold
                            // Using source_sold_link for URL-based matching
                            $soldRecord = PropertySold::updateOrCreate(
                                [
                                    'property_id' => $soldProp['property_id'],
                                    'source_sold_link' => $property->sold_link
                                ],
                                [
                                    'location' => $soldProp['location'] ?? '',
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
}
