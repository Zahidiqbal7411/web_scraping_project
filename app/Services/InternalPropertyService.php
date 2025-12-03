<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class InternalPropertyService
{
    private $client;
    private $maxConcurrent = 15; // Process 15 properties simultaneously
    private $cacheEnabled = true;
    private $cacheDuration = 3600; // 1 hour cache

    public function __construct()
    {
        $this->client = new Client([
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
    }

    /**
     * Fetch multiple properties concurrently using GuzzleHttp Promises
     * This is SIGNIFICANTLY faster than fetching one-by-one
     * 
     * @param array $urls Array of URL data (each containing 'url', 'id', etc.)
     * @return array Results with properties, processed count, and failed count
     */
    public function fetchPropertiesConcurrently(array $urls)
    {
        $properties = [];
        $processed = 0;
        $failed = 0;
        $chunks = array_chunk($urls, $this->maxConcurrent);
        
        Log::info("Processing " . count($urls) . " properties in " . count($chunks) . " chunks of {$this->maxConcurrent}");
        
        foreach ($chunks as $chunkIndex => $chunk) {
            $promises = [];
            
            // Create promises for this chunk
            foreach ($chunk as $index => $urlData) {
                $propertyUrl = $urlData['url'];
                $urlKey = $urlData['id'] ?? $index;
                
                // Check cache first
                if ($this->cacheEnabled) {
                    $cacheKey = 'property_' . md5($propertyUrl);
                    $cached = Cache::get($cacheKey);
                    
                    if ($cached) {
                        $properties[] = array_merge($cached, [
                            'id' => $urlKey,
                            'original_data' => $urlData
                        ]);
                        $processed++;
                        continue;
                    }
                }
                
                // Create async promise
                $promises[$urlKey] = $this->client->getAsync($propertyUrl)
                    ->then(
                        function ($response) use ($propertyUrl, $urlData, $urlKey) {
                            return [
                                'success' => true,
                                'url' => $propertyUrl,
                                'html' => $response->getBody()->getContents(),
                                'urlKey' => $urlKey,
                                'urlData' => $urlData
                            ];
                        },
                        function (RequestException $e) use ($propertyUrl, $urlKey) {
                            Log::warning("Failed to fetch property {$propertyUrl}: " . $e->getMessage());
                            return [
                                'success' => false,
                                'url' => $propertyUrl,
                                'error' => $e->getMessage(),
                                'urlKey' => $urlKey
                            ];
                        }
                    );
            }
            
            // Wait for all promises in this chunk to complete
            $results = Promise\Utils::settle($promises)->wait();
            
            // Process results
            foreach ($results as $urlKey => $result) {
                if ($result['state'] === 'fulfilled') {
                    $data = $result['value'];
                    
                    if ($data['success']) {
                        // Parse the HTML into property data
                        $propertyData = $this->parsePropertyFromHtml($data['html'], $data['url']);
                        
                        if ($propertyData['success']) {
                            $finalProperty = array_merge($propertyData, [
                                'id' => $data['urlKey'],
                                'original_data' => $data['urlData']
                            ]);
                            
                            $properties[] = $finalProperty;
                            $processed++;
                            
                            // Cache the result
                            if ($this->cacheEnabled) {
                                Cache::put('property_' . md5($data['url']), $propertyData, $this->cacheDuration);
                            }
                        } else {
                            $failed++;
                        }
                    } else {
                        $failed++;
                    }
                } else {
                    $failed++;
                }
            }
            
            // Small delay between chunks to be respectful
            if ($chunkIndex < count($chunks) - 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        return [
            'properties' => $properties,
            'processed' => $processed,
            'failed' => $failed
        ];
    }
    
    /**
     * Parse property data from HTML content
     * 
     * @param string $html HTML content
     * @param string $propertyUrl Property URL
     * @return array Property data
     */
    private function parsePropertyFromHtml($html, $propertyUrl)
    {
        try {
            // Parse JSON data from the page
            $jsonData = $this->parseJsonData($html);
            
            if (!$jsonData) {
                return ['success' => false, 'error' => 'Unable to extract property data'];
            }
            
            // Extract all the property information
            $images = $this->extractImages($jsonData);
            $details = $this->extractPropertyDetails($jsonData);
            
            return [
                'success' => true,
                'url' => $propertyUrl,
                'images' => $images,
                'title' => $details['title'],
                'price' => $details['price'],
                'address' => $details['address'],
                'property_type' => $details['property_type'],
                'bedrooms' => $details['bedrooms'],
                'bathrooms' => $details['bathrooms'],
                'size' => $details['size'],
                'tenure' => $details['tenure'],
                'reduced_on' => $details['reduced_on'],
                'all_details' => $details
            ];
            
        } catch (\Exception $e) {
            Log::error("Error parsing property HTML: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fetch property data from a given URL (kept for backwards compatibility)
     * 
     * @param string $propertyUrl The full property URL
     * @return array Property data including images, title, price, details
     */
    public function fetchPropertyData($propertyUrl)
    {
        try {
            Log::info("Fetching property data from: " . $propertyUrl);
            
            $response = $this->client->request('GET', $propertyUrl);
            $html = $response->getBody()->getContents();
            
            return $this->parsePropertyFromHtml($html, $propertyUrl);
            
        } catch (\Exception $e) {
            Log::error("Error fetching property data: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Parse JSON data from window.PAGE_MODEL or __NEXT_DATA__
     * 
     * @param string $html The HTML content
     * @return array|null Parsed JSON data
     */
    private function parseJsonData($html)
    {
        try {
            // Method 1: Look for window.PAGE_MODEL (NEW Rightmove format as of Dec 2024)
            if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
                Log::info("Found window.PAGE_MODEL");
                return json_decode($matches[1], true);
            }
            
            // Method 2: Fallback - Look for script tag with id="__NEXT_DATA__" (old format)
            $crawler = new Crawler($html);
            $nextDataScript = $crawler->filter('script#__NEXT_DATA__')->first();
            
            if ($nextDataScript->count() > 0) {
                Log::info("Found script#__NEXT_DATA__");
                $jsonString = $nextDataScript->html();
                return json_decode($jsonString, true);
            }
            
            // Method 3: Look for window.__NEXT_DATA__ pattern (old format)
            if (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $html, $matches)) {
                Log::info("Found window.__NEXT_DATA__");
                return json_decode($matches[1], true);
            }
            
            Log::warning("No JSON data found in any expected format");
            return null;
            
        } catch (\Exception $e) {
            Log::error("Error parsing JSON data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract images from JSON data
     * 
     * @param array $jsonData The parsed JSON data
     * @return array Array of image URLs
     */
    private function extractImages($jsonData)
    {
        $images = [];
        
        try {
            // Navigate to the images in the JSON structure
            $propertyData = $jsonData['propertyData'] ?? $jsonData['props']['pageProps']['propertyData'] ?? null;
            
            if ($propertyData && isset($propertyData['images'])) {
                foreach ($propertyData['images'] as $image) {
                    // Handle both srcUrl and url keys
                    if (isset($image['srcUrl'])) {
                        $images[] = $image['srcUrl'];
                    } elseif (isset($image['url'])) {
                        $images[] = $image['url'];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::warning("Error extracting images: " . $e->getMessage());
        }
        
        return $images;
    }

    /**
     * Extract property details from JSON data
     * 
     * @param array $jsonData The parsed JSON data
     * @return array Property details
     */
    private function extractPropertyDetails($jsonData)
    {
        $details = [
            'title' => '',
            'price' => '',
            'address' => '',
            'property_type' => '',
            'bedrooms' => '',
            'bathrooms' => '',
            'size' => '',
            'tenure' => '',
            'reduced_on' => ''
        ];
        
        try {
            // Handle both new and old JSON structures
            $propertyData = $jsonData['propertyData'] ?? $jsonData['props']['pageProps']['propertyData'] ?? null;
            
            if (!$propertyData) {
                return $details;
            }
            
            // Extract basic information
            $details['title'] = $propertyData['text']['pageTitle'] ?? 
                               $propertyData['propertyTypeFullDescription'] ?? 
                               'Property for sale';
            
            $details['address'] = $propertyData['address']['displayAddress'] ?? 
                                 $propertyData['displayAddress'] ?? '';
            
            // Extract price
            if (isset($propertyData['prices']['primaryPrice'])) {
                $details['price'] = $propertyData['prices']['primaryPrice'];
            } elseif (isset($propertyData['price']['displayPrices'][0]['displayPrice'])) {
                $details['price'] = $propertyData['price']['displayPrices'][0]['displayPrice'];
            }
            
            // Extract property details
            $details['bedrooms'] = $propertyData['bedrooms'] ?? '';
            $details['bathrooms'] = $propertyData['bathrooms'] ?? '';
            
            // Property type
            $details['property_type'] = $propertyData['propertySubType'] ?? 
                                       $propertyData['propertyType'] ?? 
                                       'Retirement Property';
            
            // Size/Area
            if (isset($propertyData['sizings'])) {
                foreach ($propertyData['sizings'] as $sizing) {
                    if (isset($sizing['unit']) && isset($sizing['value'])) {
                        $details['size'] = $sizing['value'] . ' ' . $sizing['unit'];
                        break;
                    }
                }
            }
            
            // Tenure
            $details['tenure'] = $propertyData['tenure']['tenureType'] ?? 
                                $propertyData['tenure'] ?? 
                                'Freehold';
            
            // Reduced date
            if (isset($propertyData['firstVisibleDate'])) {
                $details['reduced_on'] = date('d/m/Y', strtotime($propertyData['firstVisibleDate']));
            } elseif (isset($propertyData['reducedOn'])) {
                $details['reduced_on'] = $propertyData['reducedOn'];
            }
            
            // Get key features if available
            if (isset($propertyData['keyFeatures'])) {
                $details['key_features'] = $propertyData['keyFeatures'];
            }
            
        } catch (\Exception $e) {
            Log::warning("Error extracting property details: " . $e->getMessage());
        }
        
        return $details;
    }
}
