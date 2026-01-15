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
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->timeout(30)->get($searchUrl);

            if ($response->failed()) {
                Log::warning("Failed to probe URL: {$searchUrl} - Status: " . $response->status());
                return 0;
            }

            $html = $response->body();

            // Try to extract total from PAGE_MODEL JSON
            if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\});/s', $html, $matches)) {
                $json = json_decode($matches[1], true);
                if ($json && isset($json['pagination']['total'])) {
                    return (int) $json['pagination']['total'];
                }
                if ($json && isset($json['resultCount'])) {
                    return (int) str_replace(',', '', $json['resultCount']);
                }
            }

            // Fallback: look for result count in HTML using multiple patterns
            $patterns = [
                '/(\d{1,3}(?:,\d{3})*)\s*(?:properties|results)\s*for sale/i',
                '/(\d{1,3}(?:,\d{3})*)\s*(?:properties|results)/i',
                '/<span>(\d{1,3}(?:,\d{3})*)<\/span>\s*(?:properties|results)/i',
                '/"resultCount"\s*:\s*"?(\d+)"?/'
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $html, $matches)) {
                    $val = str_replace(',', '', $matches[1]);
                    if (is_numeric($val) && $val > 0) {
                        return (int) $val;
                    }
                }
            }

            Log::warning("Could not extract result count from HTML: {$searchUrl}");
            return 0;

        } catch (\Exception $e) {
            Log::error("Error probing result count for {$searchUrl}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Scrape property URLs from a search results page
     * Used by ImportChunkJob to get URLs for a specific price range
     * 
     * @param string $searchUrl The Rightmove search URL
     * @return array Array of URL data with 'id' and 'url' keys
     */
    public function scrapePropertyUrls(string $searchUrl): array
    {
        $allUrls = [];
        $seenIds = [];
        $currentPage = 0;
        $maxPages = 42; // Rightmove limit

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
                $newQuery = str_replace('%2C', ',', $newQuery); // Rightmove likes literal commas
                
                $pageUrl = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.rightmove.co.uk') . ($parts['path'] ?? '/property-for-sale/find.html') . '?' . $newQuery;
                
                Log::info("Scraping property URLs from: {$pageUrl}");
                $html = $this->fetchWithRetry($pageUrl);
                
                if (empty($html)) {
                    Log::warning("Page {$currentPage} (index {$index}) returned empty HTML. Stopping pagination.");
                    break;
                }

                $foundOnPage = 0;
                $json = $this->parseJsonData($html);
                
                if ($json) {
                    Log::info("JSON PAGE_MODEL found on page {$currentPage}");
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
                            Log::info("Found properties in: " . implode('.', $path));
                            break;
                        }
                    }
                    
                    // If properties still empty, try to find any 'properties' key deep
                    if (empty($properties)) {
                        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($json));
                        foreach ($it as $key => $value) {
                            if ($key === 'properties' && is_array($value)) {
                                $properties = $value;
                                Log::info("Found properties in deep key: properties");
                                break;
                            }
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

                // Fallback: extract from HTML links
                if ($foundOnPage === 0) {
                    preg_match_all('/href="(\/properties\/(\d+)[^"]*)"/', $html, $linkMatches);
                    
                    if (!empty($linkMatches[2])) {
                        foreach ($linkMatches[2] as $index => $propId) {
                            if (!in_array($propId, $seenIds)) {
                                $seenIds[] = $propId;
                                $allUrls[] = [
                                    'id' => $propId,
                                    'url' => 'https://www.rightmove.co.uk' . $linkMatches[1][$index],
                                ];
                                $foundOnPage++;
                            }
                        }
                    }
                }

                Log::info("Page {$currentPage}: Found {$foundOnPage} new properties (total: " . count($allUrls) . ")");

                // If no new properties found on this page, or we've reached the end
                if ($foundOnPage === 0) {
                    break;
                }

                $currentPage++;
                
                // Be gentle
                usleep(500000); 
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
