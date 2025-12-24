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
            $response = Http::withOptions(['cookies' => true])
            ->withHeaders([
                'User-Agent' => $this->getRandomUserAgent(),
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
            if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\})(?:\s*;|\s*<\/script>)/s', $html, $matches)) {
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
    private function getRandomUserAgent()
    {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36 Edg/121.0.0.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 OPR/106.0.0.0'
        ];
        
        return $userAgents[array_rand($userAgents)];
    }
}
