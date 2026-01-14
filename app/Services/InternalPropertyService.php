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
    private $maxConcurrent = 10; // REDUCED: Process 10 properties simultaneously to avoid rate limiting
    private $maxRetries = 20; // NEW: Maximum retry attempts for failed requests
    private $cacheEnabled = true;
    private $cacheDuration = 3600; // 1 hour cache

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
            'timeout' => 80, // INCREASED: 60 seconds for slower responses
            'connect_timeout' => 60, // INCREASED: 30 seconds for connection
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
                            'url_key' => $urlKey,
                            'original_data' => $urlData
                        ]);
                        $processed++;
                        continue;
                    }
                }

                // Check DATABASE (New logic)
                // Try to extract ID from URL
                if (preg_match('/properties\/(\d+)/', $propertyUrl, $matches)) {
                    $propId = $matches[1];
                    $dbProperty = \App\Models\Property::with('images')->where('id', $propId)->first();
                    
                    if ($dbProperty && $dbProperty->images->count() > 0) {
                        // Always use DB record if found, even if incomplete
                        // We trust the import process to have done its best
                        Log::info("Found property with images in DB: {$propId}");
                        // Construct data format matching what parsePropertyFromHtml returns
                        $images = $dbProperty->images->pluck('image_link')->toArray();
                        $keyFeatures = json_decode($dbProperty->key_features, true) ?? [];
                        
                        $propertyData = [
                            'success' => true,
                            'url' => $propertyUrl,
                            'images' => $images,
                            'title' => $dbProperty->location . ' - ' . ($dbProperty->property_type ?? ''),
                            'price' => $dbProperty->price,
                            'address' => $dbProperty->location,
                            'property_type' => $dbProperty->property_type ?? '',
                            'bedrooms' => $dbProperty->bedrooms ?? '',
                            'bathrooms' => $dbProperty->bathrooms ?? '',
                            'size' => $dbProperty->size ?? '',
                            'tenure' => $dbProperty->tenure ?? '',
                            'reduced_on' => '',
                            'description' => $dbProperty->description,
                            'key_features' => $keyFeatures,
                            'sold_link' => $dbProperty->sold_link ?? null,
                            'council_tax' => $dbProperty->council_tax ?? '',
                            'parking' => $dbProperty->parking ?? '',
                            'garden' => $dbProperty->garden ?? '',
                            'accessibility' => $dbProperty->accessibility ?? '',
                            'ground_rent' => $dbProperty->ground_rent ?? '',
                            'annual_service_charge' => $dbProperty->annual_service_charge ?? '',
                            'lease_length' => $dbProperty->lease_length ?? '',
                            'all_details' => [
                                'key_features' => $keyFeatures,
                                'description' => $dbProperty->description
                            ]
                        ];

                        $finalProperty = array_merge($propertyData, [
                            'url_key' => $urlKey,
                            'original_data' => $urlData
                        ]);
                        
                        $properties[] = $finalProperty;
                        $processed++;
                        continue; // Skip HTTP request
                    }
                }
                
                // Create async promise WITH RETRY LOGIC
                $promises[$urlKey] = $this->fetchPropertyWithRetry($propertyUrl, $urlData, $urlKey);
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
                                'url_key' => $data['urlKey'],
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
            
            // INCREASED delay between chunks to avoid rate limiting
            if ($chunkIndex < count($chunks) - 1) {
                usleep(500000); // 0.5 second delay - more respectful to prevent blocking
            }
            
            // Log progress every 10 chunks for debugging
            if (($chunkIndex + 1) % 10 === 0 || $chunkIndex === count($chunks) - 1) {
                Log::info("Progress: Processed chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " | Success: {$processed} | Failed: {$failed}");
            }
        }
        
        return [
            'properties' => $properties,
            'processed' => $processed,
            'failed' => $failed
        ];
    }
    
    /**
     * Fetch property with retry logic and exponential backoff
     * 
     * @param string $propertyUrl The property URL to fetch
     * @param array $urlData Original URL data
     * @param mixed $urlKey URL identifier
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    private function fetchPropertyWithRetry($propertyUrl, $urlData, $urlKey)
    {
        $attempt = 0;
        
        $retryPromise = function() use (&$retryPromise, &$attempt, $propertyUrl, $urlData, $urlKey) {
            $attempt++;
            
            return $this->client->getAsync($propertyUrl)
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
                    function (RequestException $e) use (&$retryPromise, &$attempt, $propertyUrl, $urlKey) {
                        $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
                        
                        // Check if we should retry
                        $shouldRetry = $attempt < $this->maxRetries;
                        
                        // Detect rate limiting
                        $isRateLimited = in_array($statusCode, [429, 403]);
                        
                        if ($shouldRetry) {
                            // Exponential backoff: 1s, 2s, 4s
                            $delay = pow(2, $attempt - 1);
                            
                            // If rate limited, add extra delay
                            if ($isRateLimited) {
                                $delay *= 2;
                                Log::warning("Rate limit detected (HTTP {$statusCode}) for {$propertyUrl}. Retry {$attempt}/{$this->maxRetries} after {$delay}s");
                            } else {
                                Log::info("Retry {$attempt}/{$this->maxRetries} for {$propertyUrl} after {$delay}s: " . $e->getMessage());
                            }
                            
                            // Wait and retry
                            sleep($delay);
                            return $retryPromise();
                        }
                        
                        // Max retries exceeded
                        Log::error("Failed to fetch property {$propertyUrl} after {$this->maxRetries} attempts: " . $e->getMessage());
                        return [
                            'success' => false,
                            'url' => $propertyUrl,
                            'error' => $e->getMessage(),
                            'status_code' => $statusCode,
                            'urlKey' => $urlKey
                        ];
                    }
                );
        };
        
        return $retryPromise();
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
            
            // Extract ID from URL
            $propertyId = null;
            if (preg_match('/properties\/(\d+)/', $propertyUrl, $matches)) {
                $propertyId = $matches[1];
            }

            return [
                'success' => true,
                'id' => $propertyId, // Include Rightmove ID
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
                'key_features' => $details['key_features'] ?? [],
                'description' => $details['description'] ?? '',
                'sold_link' => $details['sold_link'] ?? null,
                // Extra details needed for database
                'council_tax' => $details['council_tax'] ?? null,
                'parking' => $details['parking'] ?? null,
                'garden' => $details['garden'] ?? null,
                'accessibility' => $details['accessibility'] ?? null,
                'ground_rent' => $details['ground_rent'] ?? null,
                'annual_service_charge' => $details['annual_service_charge'] ?? null,
                'lease_length' => $details['lease_length'] ?? null,
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
                    } elseif (isset($image['mediumImageUrl'])) {
                        $images[] = $image['mediumImageUrl'];
                    }
                }
                
                Log::info("Extracted " . count($images) . " images from property data");
            } else {
                Log::warning("No images found in JSON data. propertyData exists: " . (isset($propertyData) ? 'yes' : 'no'));
                
                // Debug: Log available keys to help identify new structure
                if ($propertyData) {
                    Log::debug("Available propertyData keys: " . implode(', ', array_keys($propertyData)));
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
            'house_number' => '',
            'road_name' => '',
            'property_type' => '',
            'bedrooms' => '',
            'bathrooms' => '',
            'size' => '',
            'tenure' => '',
            'reduced_on' => '',
            'key_features' => [],
            'description' => '',
            'sold_link' => null,
            'ground_rent' => '',
            'annual_service_charge' => '',
            'lease_length' => '',
            'council_tax' => '',
            'parking' => '',
            'garden' => '',
            'accessibility' => ''
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
            
            // Parse address for house number and road name
            $parsedAddr = $this->parseAddress($details['address']);
            $details['house_number'] = $parsedAddr['house_number'];
            $details['road_name'] = $parsedAddr['road_name'];
            
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
            
            // Tenure with leasehold details
            if (isset($propertyData['tenure'])) {
                $details['tenure'] = $propertyData['tenure']['tenureType'] ?? 'Freehold';
                if (isset($propertyData['tenure']['yearsRemainingOnLease'])) {
                     $details['lease_length'] = $propertyData['tenure']['yearsRemainingOnLease'] . ' years';
                }
                // Check legacy location for these
                if (isset($propertyData['tenure']['groundRent'])) {
                     $details['ground_rent'] = $propertyData['tenure']['groundRent'];
                }
                if (isset($propertyData['tenure']['annualServiceCharge'])) {
                     $details['annual_service_charge'] = $propertyData['tenure']['annualServiceCharge'];
                }
            }

            // Living Costs (Newer JSON structure)
            if (isset($propertyData['livingCosts'])) {
                if (isset($propertyData['livingCosts']['annualGroundRent'])) {
                    $details['ground_rent'] = $propertyData['livingCosts']['annualGroundRent'];
                }
                if (isset($propertyData['livingCosts']['annualServiceCharge'])) {
                    $details['annual_service_charge'] = $propertyData['livingCosts']['annualServiceCharge'];
                }
                if (isset($propertyData['livingCosts']['councilTaxBand'])) {
                    $details['council_tax'] = $propertyData['livingCosts']['councilTaxBand'];
                }
            }

            // Council Tax (Fallback)
            if (empty($details['council_tax'])) {
                 $details['council_tax'] = $propertyData['councilTaxBand'] ?? 'Ask agent';
            }

            // Extract Description and Key Features
            $details['description'] = $propertyData['text']['description'] ?? '';
            $details['key_features'] = $propertyData['keyFeatures'] ?? [];
            
            // Log empty fields for debugging
            if (empty($details['description'])) {
                Log::warning("Description is empty for property");
            }
            if (empty($details['key_features'])) {
                Log::warning("Key features is empty for property");
            }

            // Extract Sold Link - check multiple paths
            $soldUrl = null;
            if (isset($propertyData['propertyUrls']['nearbySoldPropertiesUrl'])) {
                $soldUrl = $propertyData['propertyUrls']['nearbySoldPropertiesUrl'];
            } elseif (isset($propertyData['soldNearby']['soldNearbyUrl'])) {
                $soldUrl = $propertyData['soldNearby']['soldNearbyUrl'];
            } elseif (isset($jsonData['props']['pageProps']['propertyData']['propertyUrls']['nearbySoldPropertiesUrl'])) {
                 $soldUrl = $jsonData['props']['pageProps']['propertyData']['propertyUrls']['nearbySoldPropertiesUrl'];
            } elseif (isset($propertyData['address']['outcode'])) {
                // Construct sold link from postcode
                $outcode = strtolower($propertyData['address']['outcode']);
                $incode = strtolower($propertyData['address']['incode'] ?? '');
                $soldUrl = "/house-prices/{$outcode}" . ($incode ? "-{$incode}" : '') . ".html";
                Log::info("Constructed sold link from postcode: {$soldUrl}");
            }
            
            if ($soldUrl) {
                // Ensure absolute URL
                if (strpos($soldUrl, 'http') === 0) {
                    $details['sold_link'] = $soldUrl;
                } else {
                    $details['sold_link'] = 'https://www.rightmove.co.uk' . $soldUrl;
                }
                Log::info("Found sold link: " . $details['sold_link']);
            } else {
                Log::warning("No sold link could be determined for property");
            }

            // Facilities (Parking, Garden, Accessibility)
            $details['parking'] = 'Ask agent';
            $details['garden'] = 'No';
            $details['accessibility'] = 'Ask agent';

            // Check features object (Newer JSON)
            if (isset($propertyData['features'])) {
                if (!empty($propertyData['features']['parking'])) $details['parking'] = 'Yes'; // Only says Yes if array not empty? Or specific values? usually array of strings
                if (!empty($propertyData['features']['garden'])) $details['garden'] = 'Yes';
                if (!empty($propertyData['features']['accessibility'])) $details['accessibility'] = 'Yes';
            }

            // Parse key features for more details if defaults
            if (!empty($details['key_features'])) {
                foreach ($details['key_features'] as $feature) {
                    $f = strtolower($feature);
                    if ($details['parking'] === 'Ask agent' && (strpos($f, 'parking') !== false || strpos($f, 'garage') !== false)) {
                        $details['parking'] = $feature;
                    }
                    if ($details['garden'] === 'No' && (strpos($f, 'garden') !== false || strpos($f, 'terrace') !== false || strpos($f, 'backyard') !== false)) {
                        $details['garden'] = 'Yes';
                    }
                    if ($details['accessibility'] === 'Ask agent' && (strpos($f, 'wheelchair') !== false || strpos($f, 'accessible') !== false || strpos($f, 'lift') !== false)) {
                        $details['accessibility'] = $feature;
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::warning("Error extracting property details: " . $e->getMessage());
        }
        
        return $details;
    }

    /**
     * Scrape sold property data from a Rightmove House Prices URL
     * Automatically paginates through ALL pages to get complete sold history
     * 
     * @param string $url The sold prices URL
     * @param int|string $linkPropertyId The ID of the main property this is linked to (for logging/association)
     * @return array Array of sold properties found across all pages
     */
    public function scrapeSoldProperties($url, $linkPropertyId = null)
    {
        try {
            Log::info("Scraping sold properties from: {$url}");
            
            $allSoldProperties = [];
            $maxPages = 100; // INCREASED: Allow scraping up to 100 pages for complete history
            
            // Paginate through all pages using pageNumber parameter
            for ($currentPage = 1; $currentPage <= $maxPages; $currentPage++) {
                // Construct URL for this page
                $pageUrl = $url;
                if ($currentPage > 1) {
                    // Rightmove uses pageNumber parameter for sold properties pagination
                    $separator = (strpos($url, '?') !== false) ? '&' : '?';
                    $pageUrl = $url . $separator . 'pageNumber=' . $currentPage;
                }
                
            Log::info("Fetching sold properties page {$currentPage} from: {$pageUrl}");
            
            try {
                $response = $this->client->request('GET', $pageUrl);
                $html = $response->getBody()->getContents();
                
                Log::info("✓ Successfully fetched page {$currentPage}, response size: " . strlen($html) . " bytes");
            } catch (\Exception $fetchError) {
                Log::error("❌ Failed to fetch page {$currentPage}: " . $fetchError->getMessage());
                break; // Stop pagination on fetch error
            }
            
            // Try to find JSON model first
            $jsonData = $this->parseJsonData($html);
            $soldPropertiesOnPage = [];

            if ($jsonData) {
                 // Rightmove Sold Prices JSON structure
                 // Structure varies by page type - try multiple paths
                 
                 $results = [];
                 
                 // Try different JSON paths - ordered by most likely first
                 if (isset($jsonData['searchResult']['properties'])) {
                     $results = $jsonData['searchResult']['properties'];
                     Log::info("Found results in: searchResult.properties");
                 } elseif (isset($jsonData['soldHouseData']['properties'])) {
                     $results = $jsonData['soldHouseData']['properties'];
                     Log::info("Found results in: soldHouseData.properties");
                 } elseif (isset($jsonData['housePrices']['properties'])) {
                     $results = $jsonData['housePrices']['properties'];
                     Log::info("Found results in: housePrices.properties");
                 } elseif (isset($jsonData['propertyData']['soldPricesData']['properties'])) {
                     $results = $jsonData['propertyData']['soldPricesData']['properties'];
                     Log::info("Found results in: propertyData.soldPricesData.properties");
                 } elseif (isset($jsonData['props']['pageProps']['searchResult']['properties'])) {
                     $results = $jsonData['props']['pageProps']['searchResult']['properties'];
                     Log::info("Found results in: props.pageProps.searchResult.properties");
                 } elseif (isset($jsonData['props']['pageProps']['results'])) {
                     $results = $jsonData['props']['pageProps']['results'];
                     Log::info("Found results in: props.pageProps.results");
                 } elseif (isset($jsonData['props']['pageProps']['properties'])) {
                     $results = $jsonData['props']['pageProps']['properties'];
                     Log::info("Found results in: props.pageProps.properties");
                 } elseif (isset($jsonData['results'])) {
                     $results = $jsonData['results'];
                     Log::info("Found results in: results");
                 } elseif (isset($jsonData['properties'])) {
                     $results = $jsonData['properties'];
                     Log::info("Found results in: properties");
                 } elseif (isset($jsonData['props']['pageProps']['propertyData']['soldPricesData']['properties'])) {
                     $results = $jsonData['props']['pageProps']['propertyData']['soldPricesData']['properties'];
                     Log::info("Found results in: props.pageProps.propertyData.soldPricesData.properties");
                 } else {
                     Log::warning("⚠️ Could not find results in expected JSON paths");
                     // Try to find any 'properties' key at any level (3 deep)
                     foreach ($jsonData as $key => $value) {
                         if ($key === 'properties' && is_array($value)) {
                             $results = $value;
                             Log::info("Found results in: properties (top)");
                             break;
                         }
                         if (is_array($value)) {
                             if (isset($value['properties']) && is_array($value['properties'])) {
                                 $results = $value['properties'];
                                 Log::info("Found results in: {$key}.properties");
                                 break;
                             }
                             // Go one level deeper
                             foreach ($value as $subKey => $subValue) {
                                 if (is_array($subValue) && isset($subValue['properties']) && is_array($subValue['properties'])) {
                                     $results = $subValue['properties'];
                                     Log::info("Found results in: {$key}.{$subKey}.properties");
                                     break 2;
                                 }
                             }
                         }
                     }
                 }
                 
                 Log::info("📊 Page {$currentPage}: Found " . count($results) . " sold properties");
                 
                 // If no results on this page, we've reached the end
                 if (empty($results)) {
                     Log::info("🛑 No more sold properties found on page {$currentPage}. Stopping pagination.");
                     break;
                 }
                 
                 foreach ($results as $result) {
                     // IMPORTANT: Each sold property needs its OWN unique identifier
                     $soldPropertyUniqueId = $result['uuid'] ?? $result['id'] ?? $result['propertyId'] ?? $result['encryptedUprn'] ?? uniqid('sold_');
                         
                         $propertyId = $soldPropertyUniqueId;
                         
                         // Get tenure from latest transaction if not at top level
                         $tenure = $result['tenure'] ?? '';
                         if (empty($tenure) && isset($result['latestTransaction']['tenure'])) {
                             $tenure = $result['latestTransaction']['tenure'];
                         }
                         
                         // Extract detail URL for individual sold property
                         $detailUrl = null;
                         if (isset($result['detailUrl'])) {
                             $detailUrl = $result['detailUrl'];
                         } elseif (isset($result['propertyDetailUrl'])) {
                             $detailUrl = $result['propertyDetailUrl'];
                         } elseif (isset($result['url'])) {
                             $detailUrl = $result['url'];
                         }
                         
                         // Ensure absolute URL
                         if ($detailUrl && strpos($detailUrl, 'http') !== 0) {
                             $detailUrl = 'https://www.rightmove.co.uk' . $detailUrl;
                         }
                         
                         $soldProp = [
                             'property_id' => $linkPropertyId, // The PARENT property ID
                             'location' => $result['address'] ?? $result['displayAddress'] ?? '',
                            'house_number' => '',
                            'road_name' => '',
                             'property_type' => $result['propertyType'] ?? $result['propertySubType'] ?? '',
                             'bedrooms' => $result['bedrooms'] ?? null,
                             'bathrooms' => $result['bathrooms'] ?? null,
                             'tenure' => $tenure,
                            'image_url' => $result['imageInfo']['mediumImageUrl'] ?? $result['imageInfo']['imageUrl'] ?? $result['image']['srcUrl'] ?? $result['image']['url'] ?? $result['mainImageSrc'] ?? $result['propertyImages'][0]['url'] ?? $result['propertyImages'][0] ?? null,
                            'map_url' => $result['staticMapUrls']['staticMapImgUrlDesktop'] ?? $result['staticMapUrls']['desktop'] ?? $result['staticMapUrl'] ?? $result['mapUrl'] ?? null,
                            'detail_url' => $detailUrl,
                             'transactions' => []
                         ];

                        // Parse sold property address
                        $parsedSoldAddr = $this->parseAddress($soldProp['location']);
                        $soldProp['house_number'] = $parsedSoldAddr['house_number'];
                        $soldProp['road_name'] = $parsedSoldAddr['road_name'];

                         // Transactions (Sold History) - check multiple possible field names
                         $transactions = $result['transactions'] ?? $result['soldPrices'] ?? $result['priceHistory'] ?? [];
                         
                         if (!empty($transactions)) {
                             foreach ($transactions as $trans) {
                                 $soldProp['transactions'][] = [
                                     'price' => $trans['displayPrice'] ?? $trans['price'] ?? $trans['soldPrice'] ?? '',
                                     'date' => $trans['dateSold'] ?? $trans['soldDate'] ?? $trans['date'] ?? ''
                                 ];


                             }
                         }
                         
                         // Only add if we have valid data
                         if ($soldProp['location']) {
                             $soldPropertiesOnPage[] = $soldProp;
                         }
                     }
                     
                     // Add this page's results to the total
                     $allSoldProperties = array_merge($allSoldProperties, $soldPropertiesOnPage);
                     
                     // If we got 0 results, we've definitely reached the end
                     if (count($results) === 0) {
                         Log::info("Got 0 results on page {$currentPage}. End of results.");
                         break;
                     }
                     
                     // Rightmove results can vary (sometimes 25, sometimes less depending on grouping)
                     // So we only break if we get 0, OR if we've reached a high page count.
                     // But we can check if the total results on page is significantly less than expected.
                     if (count($results) < 5 && $currentPage > 1) {
                         Log::info("Got very few results (" . count($results) . ") on page {$currentPage}. Assuming last page.");
                         break;
                     }
                     
                } else {
                    Log::warning("Could not extract JSON from Sold Prices page: {$pageUrl}");
                    break; // Stop if we can't parse the page
                }
                
                // Small delay between pages to be respectful to the server
                if ($currentPage < $maxPages) {
                    usleep(200000); // 0.2 second delay
                }
            }

            Log::info("TOTAL: Found " . count($allSoldProperties) . " sold properties across all pages.");
            return $allSoldProperties;

        } catch (\Exception $e) {
            Log::error("Error scraping sold properties: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse address string to extract house number and road name
     * 
     * @param string $address The full address string
     * @return array [house_number, road_name]
     */
    private function parseAddress($address)
    {
        if (empty($address)) {
            return ['house_number' => '', 'road_name' => ''];
        }

        $houseNumber = '';
        $roadName = '';

        // Clean the address
        $address = trim($address);
        
        // Pattern 1: Starts with "Flat X, Y Road" or "Apartment X, Y Road"
        if (preg_match('/^(Flat|Apartment|Suite|Unit)\s+([^\s,]+),\s*(.+)$/i', $address, $matches)) {
            $houseNumber = $matches[1] . ' ' . $matches[2];
            $roadName = $matches[3];
        }
        // Pattern 2: Starts with "X, Y Road" (comma separated number)
        elseif (preg_match('/^([0-9a-z\/-]+),\s*(.+)$/i', $address, $matches)) {
            $houseNumber = $matches[1];
            $roadName = $matches[2];
        }
        // Pattern 3: Starts with "X Y Road" (space separated number)
        elseif (preg_match('/^([0-9a-z\/-]+)\s+(.+)$/i', $address, $matches)) {
            $houseNumber = $matches[1];
            $roadName = $matches[2];
        }
        else {
            $roadName = $address;
        }

        // Further clean road name - take everything until the first comma
        if (strpos($roadName, ',') !== false) {
            $parts = explode(',', $roadName);
            $roadName = trim($parts[0]);
        }

        return [
            'house_number' => trim($houseNumber),
            'road_name' => trim($roadName)
        ];
    }
}
