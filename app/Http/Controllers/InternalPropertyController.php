<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\InternalPropertyService;
use App\Models\SavedSearch;
use Illuminate\Support\Facades\Log;

class InternalPropertyController extends Controller
{
    private $propertyService;

    public function __construct(InternalPropertyService $propertyService)
    {
        $this->propertyService = $propertyService;
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
            
            // Check cache first
            $cacheKey = 'property_urls_list';
            $cached = \Cache::get($cacheKey);
            
            if ($cached && isset($cached['urls']) && count($cached['urls']) > 0) {
                $allUrls = $cached['urls'];
                $total = count($allUrls);
                
                // Calculate pagination
                $offset = ($page - 1) * $perPage;
                $urlsPage = array_slice($allUrls, $offset, $perPage);
                
                Log::info("Returning page {$page} with " . count($urlsPage) . " URLs from cache");
                
                return response()->json([
                    'success' => true,
                    'message' => 'Property URLs loaded from cache',
                    'urls' => $urlsPage,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'total_pages' => ceil($total / $perPage),
                        'has_more' => $offset + $perPage < $total
                    ],
                    'cached' => true
                ]);
            }
            
            // If no cache and page 1, trigger URL fetch
            if ($page === 1) {
                Log::info("Cache miss - triggering URL fetch from PropertyController");
                
                // Try to return partial data if available while fetching in background
                $propertyController = new \App\Http\Controllers\PropertyController();
                $response = $propertyController->sync();
                $data = $response->getData(true);
                
                if ($data['success'] && isset($data['urls']) && count($data['urls']) > 0) {
                    // Cache the result
                    \Cache::put($cacheKey, $data, 1800);
                    
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
            
            // Generate cache key based on search context
            $cacheKey = $searchId ? "property_urls_search_{$searchId}" : 'property_urls_list';
            
            // Check cache first (30 minutes TTL)
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
            
            Log::info("Cache miss - fetching fresh URLs from PropertyController");
            
            // If no cache, fetch from PropertyController
            set_time_limit(600); // 10 minutes
            
            $propertyController = new \App\Http\Controllers\PropertyController();
            
            if ($searchId) {
                $search = SavedSearch::find($searchId);
                if ($search && $search->updates_url) {
                    Log::info("Fetching URLs for Saved Search #{$searchId}: {$search->updates_url}");
                    $response = $propertyController->scrapeProperties($search->updates_url);
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
                // Add cache timestamp
                $data['cached_at'] = now()->toIso8601String();
                
                // Cache the successful result for 30 minutes
                \Cache::put($cacheKey, $data, 1800);
                Log::info("Cached " . count($data['urls']) . " URLs for future requests");
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
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            Log::info("Concurrent batch fetch complete in {$duration}s. Processed: {$result['processed']}, Failed: {$result['failed']}");

            return response()->json([
                'success' => true,
                'message' => "Fetched {$result['processed']} properties successfully in {$duration}s",
                'total' => count($urls),
                'processed' => $result['processed'],
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
}
