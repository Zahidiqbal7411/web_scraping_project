<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\Property;

class PropertyController extends Controller
{
    public function index()
    {
        return view('properties.index');
    }


    public function getProperties()
    {
        $properties = Property::orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'count' => $properties->count(),
            'properties' => $properties
        ]);
    }

    public function test()
    {
        try {
            $client = new Client([
                'verify' => false,
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ]
            ]);
            
            $url = 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Bath%2C+Somerset&useLocationIdentifier=true&locationIdentifier=REGION%5E116';
            $response = $client->request('GET', $url);
            $html = $response->getBody()->getContents();
            
            return response()->json([
                'success' => true,
                'html_length' => strlen($html),
                'html_preview' => substr($html, 0, 500)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sync(Request $request = null)
    {
        // Increase execution time for this endpoint
        set_time_limit(300); // 5 minutes
        
        $baseUrl = 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Bath%2C+Somerset&useLocationIdentifier=true&locationIdentifier=REGION%5E116&radius=0.0&_includeSSTC=on';
        
        // If request is provided and has url, use it
        if ($request && $request->has('url')) {
            $baseUrl = $request->input('url');
        }

        return $this->scrapeProperties($baseUrl);
    }

    /**
     * Reusable method to scrape properties from a given Rightmove URL
     */
    public function scrapeProperties($baseUrl, $fetchAll = true) 
    {
        try {
            $client = new Client([
                'verify' => false,
                'timeout' => 30, // Increased timeout for more reliable requests
                'connect_timeout' => 15, // Increased connection timeout
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-GB,en;q=0.9',
                                        'Cache-Control' => 'no-cache',
                ]
            ]);
            
            $allUrls = [];
            
            // Scrape multiple pages until no more properties are found
            // If fetching only one page, set maxPages to 1
            $maxPages = $fetchAll ? 50 : 1; 
            $consecutiveEmptyPages = 0;
            $maxConsecutiveEmptyPages = 5; // Stop if we get 5 empty pages in a row (increased for reliability)
            
            for ($page = 0; $page < $maxPages; $page++) {
                $retryCount = 0;
                $maxRetries = 3;
                $pageSuccess = false;
                
                while ($retryCount < $maxRetries && !$pageSuccess) {
                    try {
                        $index = $page * 24;
                        
                        // Remove any existing index parameter from URL to avoid duplicates
                        $cleanUrl = preg_replace('/([?&])index=\d+(&|$)/', '$1', $baseUrl);
                        $cleanUrl = rtrim($cleanUrl, '&?'); // Clean trailing ? or &
                        
                        // Append index to URL correctly (check if query string exists)
                        $separator = (strpos($cleanUrl, '?') !== false) ? '&' : '?';
                        $url = $page === 0 ? $cleanUrl : $cleanUrl . $separator . 'index=' . $index;
                        
                        \Log::info("Fetching page: " . ($page + 1) . " (Attempt " . ($retryCount + 1) . ") - URL: " . $url);
                        
                        $response = $client->request('GET', $url);
                        $html = $response->getBody()->getContents();
                        $pageSuccess = true; // Mark as successful if we got here

                        // Extract JSON data from Next.js script tags
                        $crawler = new Crawler($html);
                        
                        $pagePropertiesCount = 0;
                        
                        // Method 1: Look for script tag with id="__NEXT_DATA__"
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
                                                'address' => $prop['displayAddress'] ?? 'Bath, Somerset',
                                            ];
                                        }
                                    } catch (\Exception $e) {
                                        \Log::warning("Error processing property: " . $e->getMessage());
                                        continue;
                                    }
                                }
                                
                                \Log::info("Page " . ($page + 1) . " processed. Found " . $pagePropertiesCount . " properties. Total so far: " . count($allUrls));
                            } else {
                                \Log::warning("No properties found in JSON data for page " . ($page + 1));
                            }
                        } else {
                            // Method 2: Fallback - Try to extract from other script tags
                            $scripts = $crawler->filter('script')->each(function (Crawler $node) {
                                return $node->html();
                            });
                            
                            foreach ($scripts as $script) {
                                // Look for window.__NEXT_DATA__ or similar patterns
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
                                                        'address' => $prop['displayAddress'] ?? 'Bath, Somerset',
                                                    ];
                                                }
                                            } catch (\Exception $e) {
                                                continue;
                                            }
                                        }
                                        
                                        \Log::info("Page " . ($page + 1) . " processed via fallback method. Found " . $pagePropertiesCount . " properties. Total so far: " . count($allUrls));
                                        break;
                                    }
                                }
                            }
                        }
                        
                        // Check if we found any properties on this page
                        if ($pagePropertiesCount === 0) {
                            $consecutiveEmptyPages++;
                            \Log::info("Empty page detected. Consecutive empty pages: " . $consecutiveEmptyPages);
                            
                            if ($consecutiveEmptyPages >= $maxConsecutiveEmptyPages) {
                                \Log::info("Reached " . $maxConsecutiveEmptyPages . " consecutive empty pages. Stopping pagination.");
                                break;
                            }
                        } else {
                            // Reset counter if we found properties
                            $consecutiveEmptyPages = 0;
                        }
                        
                    } catch (\Exception $e) {
                        \Log::error("Error fetching page " . ($page + 1) . " (Attempt " . ($retryCount + 1) . "): " . $e->getMessage());
                        $retryCount++;
                        
                        if ($retryCount < $maxRetries) {
                            \Log::info("Retrying in 2 seconds...");
                            sleep(2); // Wait 2 seconds before retry
                        }
                    }
                }
                
                // If all retries failed
                if (!$pageSuccess) {
                    $consecutiveEmptyPages++;
                    \Log::warning("Failed to fetch page " . ($page + 1) . " after " . $maxRetries . " attempts");
                    
                    if ($consecutiveEmptyPages >= $maxConsecutiveEmptyPages) {
                        \Log::info("Too many consecutive failures. Stopping pagination.");
                        break;
                    }
                    continue;
                }
                
                // Add delay between successful requests to avoid rate limiting
                if ($page < $maxPages - 1) {
                    \Log::info("Waiting 1 second before next page...");
                    sleep(1); // 1 second delay between pages
                }
            }
            
            if (empty($allUrls)) {
                \Log::error("No URLs found after scraping all pages");
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

            \Log::info("Successfully fetched " . count($uniqueUrls) . " unique property URLs");

            return response()->json([
                'success' => true,
                'message' => 'Property URLs fetched successfully',
                'count' => count($uniqueUrls),
                'urls' => $uniqueUrls
            ]);

        } catch (\Exception $e) {
            \Log::error("Sync error: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error occurred while fetching URLs',
                'error' => $e->getMessage(),
                'count' => 0
            ], 200);
        }
    }
}