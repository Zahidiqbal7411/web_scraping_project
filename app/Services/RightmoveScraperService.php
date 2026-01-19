<?php

namespace App\Services;

use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\Url;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RightmoveScraperService
{
    public function scrapeAndStore(string $url, ?int $filterId = null)
    {
        try {
            // Check if URL already exists in urls table, if not create it
            // User requested: "url storing in its own table url"
            // Assuming this service might be called with a URL that needs to be tracked.
            // If the URL is just for a specific property, we might not need to check uniqueness strictly here unless required,
            // but satisfying the "store in table url" requirement implies we should ensure it's recorded.
            Url::firstOrCreate(
                ['url' => $url, 'filter_id' => $filterId]
            );

            // Fetch Page Content
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ])->get($url);

            if ($response->failed()) {
                Log::error("Failed to fetch property URL: {$url} - Status: " . $response->status());
                return null;
            }

            $html = $response->body();

            // Extract PAGE_MODEL JSON
            if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\});/s', $html, $matches)) {
                $json = $matches[1];
                $data = json_decode($json, true);

                if (!$data) {
                    Log::error("Failed to decode PAGE_MODEL JSON for URL: {$url}");
                    return null;
                }

                $propertyData = $data['propertyData'] ?? null;
                if (!$propertyData) {
                    Log::error("No propertyData found in PAGE_MODEL for URL: {$url}");
                    return null;
                }

                $propertyId = $propertyData['id'];

                // Prepare Data
                $textData = $propertyData['text'] ?? [];
                $priceData = $propertyData['prices'] ?? [];
                $addressData = $propertyData['address'] ?? [];
                
                $location = $addressData['displayAddress'] ?? '';
                $price = $priceData['primaryPrice'] ?? '';
                $description = $textData['description'] ?? '';
                $keyFeatures = $propertyData['keyFeatures'] ?? [];
                $soldLink = null; // Rightmove doesn't easily expose "sold link" in this model unless sold.
                // If the property is sold, status might be in marketStatus.
                // User asked for "sold link(text)". I'll check if there's a relevant field.
                // Usually sold history is a separate tab/link. For now, leaving null or putting status.

                // Store Property
                $property = Property::updateOrCreate(
                    ['property_id' => $propertyId],
                    [
                        'location' => $location,
                        'price' => $price,
                        'key_features' => json_encode($keyFeatures), // Cast array to json
                        'description' => $description,
                        'sold_link' => $soldLink,
                        'filter_id' => $filterId
                    ]
                );

                // Store Images
                $images = $propertyData['images'] ?? [];
                // Clear existing images to avoid duplicates if re-scraping?
                // Or updateOrCreate? Images might change.
                // simplest is delete existing for this property and re-add.
                PropertyImage::where('property_id', $propertyId)->delete();

                foreach ($images as $img) {
                    $src = $img['url'] ?? '';
                    if ($src) {
                        PropertyImage::create([
                            'property_id' => $propertyId,
                            'image_link' => $src
                        ]);
                    }
                }

                return $property;

            } else {
                Log::error("Could not find PAGE_MODEL in HTML for URL: {$url}");
                // Fallback to DOM parsing if regex fails? Rightmove relies heavily on this JSON.
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Error scraping property {$url}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Probe the total result count for a search URL without fetching all data
     * Used by MasterImportJob to determine if splitting is needed
     * 
     * @param string $searchUrl The Rightmove search URL
     * @return int Total number of results
     */
    public function probeResultCount(string $searchUrl): int
    {
        // CRITICAL: Normalize URL - decode %5E to ^ for Rightmove's locationIdentifier
        $searchUrl = str_replace(['%5E', '%5e'], '^', $searchUrl);
        
        Log::info("=== PROBE: Starting probe for URL: {$searchUrl} ===");
        
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile' => '?0',
                'Sec-Ch-Ua-Platform' => '"Windows"',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1',
                'Upgrade-Insecure-Requests' => '1',
            ])->timeout(30)->withOptions(['verify' => false])->get($searchUrl);

            // Log HTTP status
            Log::info("PROBE: HTTP Status: " . $response->status());
            
            if ($response->failed()) {
                Log::error("PROBE FAILED: HTTP {$response->status()} for URL: {$searchUrl}");
                return 0;
            }

            $html = $response->body();
            $htmlLen = strlen($html);
            
            // DEBUG: Log response size to detect empty/blocked responses
            Log::info("PROBE: Response size: {$htmlLen} bytes");
            
            // Check for blocking indicators
            $blockingIndicators = [
                'captcha' => stripos($html, 'captcha') !== false,
                'challenge' => stripos($html, 'challenge') !== false,
                'blocked' => stripos($html, 'access denied') !== false || stripos($html, 'blocked') !== false,
                'cloudflare' => stripos($html, 'cloudflare') !== false,
                'bot_detection' => stripos($html, 'bot') !== false && stripos($html, 'robot') !== false,
                'rate_limit' => stripos($html, 'rate limit') !== false || stripos($html, 'too many requests') !== false,
            ];
            
            $isBlocked = false;
            foreach ($blockingIndicators as $indicator => $found) {
                if ($found) {
                    Log::warning("PROBE: BLOCKING DETECTED - {$indicator} found in response!");
                    $isBlocked = true;
                }
            }
            
            // If response is very small or blocked, log HTML sample for debugging
            if ($htmlLen < 5000 || $isBlocked) {
                $sample = substr($html, 0, 2000);
                Log::warning("PROBE: Small/blocked response. HTML sample: " . $sample);
            }
            
            // Check for PAGE_MODEL presence
            $hasPageModel = strpos($html, 'PAGE_MODEL') !== false;
            Log::info("PROBE: PAGE_MODEL present: " . ($hasPageModel ? 'YES' : 'NO'));

            // CRITICAL FIX: If blocking is detected AND no PAGE_MODEL, return 0 immediately
            // This triggers the 50,000 property fallback in MasterImportJob for large imports
            // Without this, fallback regex may match random numbers from blocked HTML (e.g., 114)
            if ($isBlocked && !$hasPageModel) {
                Log::warning("PROBE: Blocking detected with no PAGE_MODEL - returning 0 to trigger large import strategy");
                return 0;
            }

            // Try to extract total from PAGE_MODEL JSON
            if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\});/s', $html, $matches)) {
                $json = json_decode($matches[1], true);
                
                if ($json) {
                    // Log available keys for debugging
                    $topKeys = array_keys($json);
                    Log::info("PROBE: PAGE_MODEL top-level keys: " . implode(', ', $topKeys));
                    
                    if (isset($json['pagination']['total'])) {
                        $count = (int) $json['pagination']['total'];
                        Log::info("PROBE SUCCESS: Found {$count} properties via pagination.total");
                        return $count;
                    }
                    if (isset($json['resultCount'])) {
                        $count = (int) str_replace(',', '', $json['resultCount']);
                        Log::info("PROBE SUCCESS: Found {$count} properties via resultCount");
                        return $count;
                    }
                    
                    // Try alternate paths
                    if (isset($json['searchResult']['pagination']['total'])) {
                        $count = (int) $json['searchResult']['pagination']['total'];
                        Log::info("PROBE SUCCESS: Found {$count} properties via searchResult.pagination.total");
                        return $count;
                    }
                    
                    Log::warning("PROBE: PAGE_MODEL found but no result count in expected locations");
                } else {
                    Log::warning("PROBE: PAGE_MODEL regex matched but JSON decode failed");
                }
            }

            // Fallback: look for result count in HTML using multiple patterns
            $patterns = [
                '/(\d{1,3}(?:,\d{3})*)\s*(?:properties|results)\s*for sale/i',
                '/(\d{1,3}(?:,\d{3})*)\s*(?:properties|results)/i',
                '/<span>(\d{1,3}(?:,\d{3})*)<\/span>\s*(?:properties|results)/i',
                '/"resultCount"\s*:\s*"?(\d+)"?/',
                '/"total"\s*:\s*(\d+)/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $val = str_replace(',', '', $matches[1]);
                    if (is_numeric($val) && $val > 0) {
                        Log::info("PROBE SUCCESS (fallback): Found {$val} properties via regex pattern");
                        return (int) $val;
                    }
                }
            }

            Log::warning("PROBE FAILED: Could not extract result count from HTML. URL: {$searchUrl}");
            Log::warning("PROBE: Response had PAGE_MODEL: " . ($hasPageModel ? 'yes' : 'no') . ", Size: {$htmlLen} bytes");
            return 0;

        } catch (\Exception $e) {
            Log::error("PROBE EXCEPTION: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Scrape property URLs from a search results page
     * Used by ImportChunkJob to get URLs for a specific page range
     * 
     * @param string $searchUrl The Rightmove search URL
     * @param int $startPage Start page (0-indexed, default 0)
     * @param int $endPage End page (0-indexed, inclusive, default 41 for all pages)
     * @return array Array of URL data with 'id' and 'url' keys
     */
    public function scrapePropertyUrls(string $searchUrl, int $startPage = 0, int $endPage = 41): array
    {
        // CRITICAL: Normalize URL - decode %5E to ^ for Rightmove's locationIdentifier
        $searchUrl = str_replace(['%5E', '%5e'], '^', $searchUrl);
        
        $allUrls = [];
        $seenIds = [];
        $currentPage = $startPage;
        $maxPages = min($endPage + 1, 42); // Rightmove limit is 42 pages

        Log::info("Scraping pages {$startPage} to {$endPage} (max: " . ($maxPages - 1) . ")");

        try {
            while ($currentPage < $maxPages) {
                // Construct page URL robustly
                $index = $currentPage * 24;
                
                $parts = parse_url($searchUrl);
                $queryParams = [];
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $queryParams);
                }
                
                $queryParams['index'] = $index;
                $newQuery = http_build_query($queryParams);
                // CRITICAL: Rightmove requires literal ^ and , in URLs (not encoded)
                $newQuery = str_replace(['%2C', '%5E', '%5e'], [',', '^', '^'], $newQuery);
                
                $pageUrl = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.rightmove.co.uk') . ($parts['path'] ?? '/property-for-sale/find.html') . '?' . $newQuery;
                
                Log::info("=== SCRAPE: Page {$currentPage}, URL: {$pageUrl} ===");
                $html = $this->fetchWithRetry($pageUrl);
                
                $htmlLen = strlen($html);
                Log::info("SCRAPE: Response size: {$htmlLen} bytes");
                
                if (empty($html)) {
                    Log::warning("SCRAPE: Page {$currentPage} (index {$index}) returned empty HTML. Stopping pagination.");
                    break;
                }
                
                // Check for blocking on search pages too
                if ($htmlLen < 5000) {
                    $sample = substr($html, 0, 1500);
                    Log::warning("SCRAPE: Small response detected. HTML sample: " . $sample);
                    
                    // Check for common blocking patterns
                    if (stripos($html, 'captcha') !== false || stripos($html, 'challenge') !== false) {
                        Log::error("SCRAPE: CAPTCHA/Challenge detected! Rightmove is blocking requests.");
                    }
                }

                $foundOnPage = 0;
                $json = $this->parseJsonData($html);
                
                if ($json) {
                    // Log top-level keys for debugging
                    $topKeys = array_keys($json);
                    Log::info("SCRAPE: PAGE_MODEL found on page {$currentPage}. Top-level keys: " . implode(', ', $topKeys));
                    
                    // Try multiple paths for properties - Rightmove structure can vary
                    $properties = [];
                    
                    $paths = [
                        ['properties'],
                        ['searchResult', 'properties'],
                        ['propertySearch', 'properties'],
                        ['results'],
                        ['props', 'pageProps', 'properties'],
                        ['props', 'pageProps', 'searchModel', 'properties']
                    ];

                    foreach ($paths as $path) {
                        $current = $json;
                        foreach ($path as $key) {
                            if (isset($current[$key])) {
                                $current = $current[$key];
                            } else {
                                $current = null;
                                break;
                            }
                        }
                        if (is_array($current) && !empty($current)) {
                            $properties = $current;
                            Log::info("SCRAPE: Found " . count($properties) . " properties in: " . implode('.', $path));
                            break;
                        }
                    }
                    
                    // If properties still empty, try to find any 'properties' key deep
                    if (empty($properties)) {
                        Log::warning("SCRAPE: No properties found in standard paths. Trying deep search...");
                        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($json));
                        foreach ($it as $key => $value) {
                            if ($key === 'properties' && is_array($value)) {
                                $properties = $value;
                                Log::info("SCRAPE: Found " . count($properties) . " properties in deep key: properties");
                                break;
                            }
                        }
                    }
                    
                    // If still empty, log what we have for debugging
                    if (empty($properties)) {
                        Log::warning("SCRAPE: PAGE_MODEL exists but NO PROPERTIES FOUND! This is likely a data structure issue.");
                        // Log some keys that might help debug
                        if (isset($json['resultCount'])) {
                            Log::info("SCRAPE: resultCount in JSON: " . $json['resultCount']);
                        }
                        if (isset($json['pagination'])) {
                            Log::info("SCRAPE: pagination exists: " . json_encode($json['pagination']));
                        }
                    }

                    foreach ($properties as $prop) {
                        $propId = $prop['id'] ?? $prop['propertyId'] ?? null;
                        $propUrl = $prop['propertyUrl'] ?? $prop['detailUrl'] ?? $prop['url'] ?? null;
                        
                        if ($propId && !in_array($propId, $seenIds)) {
                            $seenIds[] = $propId;
                            
                            // Build full URL
                            $fullUrl = $propUrl;
                            if ($propUrl && !str_starts_with($propUrl, 'http')) {
                                $fullUrl = 'https://www.rightmove.co.uk' . $propUrl;
                            }
                            
                            // If no URL but have ID, construct it
                            if (!$fullUrl && $propId) {
                                $fullUrl = 'https://www.rightmove.co.uk/properties/' . $propId;
                            }
                            
                            $allUrls[] = [
                                'id' => $propId,
                                'url' => $fullUrl,
                                'price' => $prop['price']['amount'] ?? $prop['price'] ?? null,
                                'displayPrice' => $prop['price']['displayPrices'][0]['displayPrice'] ?? $prop['displayPrice'] ?? null,
                                'propertyType' => $prop['propertySubType'] ?? $prop['propertyTypeFullDescription'] ?? $prop['propertyType'] ?? null,
                                'bedrooms' => $prop['bedrooms'] ?? null,
                                'address' => $prop['displayAddress'] ?? $prop['address'] ?? null,
                            ];
                            $foundOnPage++;
                        }
                    }
                }

                // Fallback: extract from HTML links using multiple patterns
                if ($foundOnPage === 0) {
                    Log::info("SCRAPE: PAGE_MODEL extraction failed, trying HTML link fallback...");
                    
                    // Pattern 1: Standard property links
                    preg_match_all('/href="\/properties\/(\d+)[^"]*"/', $html, $matches1);
                    
                    // Pattern 2: Property links with full URL
                    preg_match_all('/href="https:\/\/www\.rightmove\.co\.uk\/properties\/(\d+)[^"]*"/', $html, $matches2);
                    
                    // Pattern 3: Property IDs from data attributes
                    preg_match_all('/propertyId["\':]+\s*["\']?(\d{6,12})["\']?/', $html, $matches3);
                    
                    // Pattern 4: Property links in JSON-like structures
                    preg_match_all('/"id"\s*:\s*(\d{6,12})/', $html, $matches4);
                    
                    // Combine all matches
                    $allPropIds = array_merge(
                        $matches1[1] ?? [],
                        $matches2[1] ?? [],
                        $matches3[1] ?? [],
                        $matches4[1] ?? []
                    );
                    $allPropIds = array_unique($allPropIds);
                    
                    Log::info("SCRAPE: Fallback found " . count($allPropIds) . " property IDs via regex");
                    
                    foreach ($allPropIds as $propId) {
                        if (!in_array($propId, $seenIds) && strlen($propId) >= 6) {
                            $seenIds[] = $propId;
                            $allUrls[] = [
                                'id' => $propId,
                                'url' => 'https://www.rightmove.co.uk/properties/' . $propId,
                            ];
                            $foundOnPage++;
                        }
                    }
                    
                    Log::info("SCRAPE: Fallback extracted {$foundOnPage} new properties");
                }

                Log::info("Page {$currentPage}: Found {$foundOnPage} new properties (total: " . count($allUrls) . ")");

                // If no new properties found on this page, or we've reached the end
                if ($foundOnPage === 0) {
                    break;
                }

                $currentPage++;
                
                // Be gentle - Reduced delay to speed up import (was 500ms)
                // usleep(100000); 
            }

            Log::info("Total URLs scraped: " . count($allUrls));
            return $allUrls;

        } catch (\Exception $e) {
            Log::error("Error scraping property URLs: " . $e->getMessage());
            return $allUrls;
        }
    }

    /**
     * Fetch URL with retry logic
     * Enhanced with full browser headers for better anti-bot handling
     */
    public function fetchWithRetry(string $url, int $maxRetries = 3): string
    {
        $attempt = 0;
        $lastError = null;
        
        while ($attempt < $maxRetries) {
            try {
                // Use full browser headers to avoid blocking (same as scrapeAndStore)
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                    'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                    'Sec-Ch-Ua-Mobile' => '?0',
                    'Sec-Ch-Ua-Platform' => '"Windows"',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                    'Upgrade-Insecure-Requests' => '1',
                    'Connection' => 'keep-alive',
                ])
                ->timeout(45)
                ->withOptions([
                    'verify' => false, // Disable SSL verification for shared hosting compatibility
                ])
                ->get($url);

                if ($response->successful()) {
                    $body = $response->body();
                    Log::debug("fetchWithRetry succeeded for URL (length: " . strlen($body) . " bytes)");
                    return $body;
                }
                
                $lastError = "HTTP " . $response->status();
                Log::warning("fetchWithRetry attempt {$attempt}: HTTP {$response->status()} for URL: {$url}");

                $attempt++;
                if ($attempt < $maxRetries) {
                    usleep(1000000 * $attempt); // Exponential wait
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::warning("fetchWithRetry attempt {$attempt} exception: {$lastError}");
                $attempt++;
                if ($attempt >= $maxRetries) {
                    Log::error("fetchWithRetry FAILED after {$maxRetries} attempts: {$lastError}");
                    // Don't throw - return empty to allow graceful handling
                    return '';
                }
                usleep(1000000 * $attempt);
            }
        }
        
        Log::warning("fetchWithRetry returning empty after {$maxRetries} attempts. Last error: {$lastError}");
        return '';
    }

    /**
     * Parse JSON from HTML
     */
    public function parseJsonData(string $html): ?array
    {
        if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\});/s', $html, $matches)) {
            return json_decode($matches[1], true);
        }
        return null;
    }
}
