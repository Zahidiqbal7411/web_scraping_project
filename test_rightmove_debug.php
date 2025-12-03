<?php
// Test script to debug Rightmove scraping
require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

$client = new Client([
    'verify' => false,
    'timeout' => 30,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-GB,en;q=0.9',
        'Cache-Control' => 'no-cache',
    ]
]);

// Remove the fragment and query parameters
$testUrl = 'https://www.rightmove.co.uk/properties/169746464';
echo "Fetching: $testUrl\n\n";

try {
    $response = $client->request('GET', $testUrl);
    $html = $response->getBody()->getContents();
    
    echo "HTML length: " . strlen($html) . " bytes\n\n";
    
    // Check for __NEXT_DATA__
    if (strpos($html, '__NEXT_DATA__') !== false) {
        echo "✓ Found __NEXT_DATA__ in HTML\n\n";
        
        $crawler = new Crawler($html);
        $nextDataScript = $crawler->filter('script#__NEXT_DATA__')->first();
        
        if ($nextDataScript->count() > 0) {
            echo "✓ Found script#__NEXT_DATA__ element\n\n";
            $jsonString = $nextDataScript->html();
            echo "JSON length: " . strlen($jsonString) . " bytes\n";
            
            $jsonData = json_decode($jsonString, true);
            if ($jsonData) {
                echo "✓ JSON parsed successfully\n\n";
                
                // Check for expected data structure
                if (isset($jsonData['props']['pageProps']['propertyData'])) {
                    echo "✓ Found propertyData!\n\n";
                    $propertyData = $jsonData['props']['pageProps']['propertyData'];
                    
                    echo "Property details found:\n";
                    echo "- Address: " . ($propertyData['address']['displayAddress'] ?? 'N/A') . "\n";
                    echo "- Price: " . ($propertyData['prices']['primaryPrice'] ?? 'N/A') . "\n";
                    echo "- Bedrooms: " . ($propertyData['bedrooms'] ?? 'N/A') . "\n";
                    echo "- Images: " . count($propertyData['images'] ?? []) . "\n";
                } else {
                    echo "✗ propertyData not found in expected location\n";
                    echo "Available keys: " . implode(', ', array_keys($jsonData['props']['pageProps'] ?? [])) . "\n";
                }
            } else {
                echo "✗ JSON parsing failed: " . json_last_error_msg() . "\n";
            }
        } else {
            echo "✗ script#__NEXT_DATA__ element not found\n";
            echo "Trying window.__NEXT_DATA__ pattern...\n\n";
            
            if (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $html, $matches)) {
                echo "✓ Found window.__NEXT_DATA__\n";
                $jsonData = json_decode($matches[1], true);
                if ($jsonData && isset($jsonData['props']['pageProps']['propertyData'])) {
                    echo "✓ Data found via regex pattern!\n";
                }
            } else {
                echo "✗ window.__NEXT_DATA__ pattern not found\n";
            }
        }
    } else {
        echo "✗ __NEXT_DATA__ string NOT found in HTML at all\n\n";
        echo "Searching for alternative data sources...\n\n";
        
        // Look for application/json script tags
        $crawler = new Crawler($html);
        $jsonScripts = $crawler->filter('script[type="application/json"]');
        
        echo "Found " . $jsonScripts->count() . " application/json script tags\n\n";
        
        if ($jsonScripts->count() > 0) {
            $jsonScripts->each(function (Crawler $node, $i) {
                echo "=== Script #" . ($i + 1) . " ===\n";
                $id = $node->attr('id');
                echo "ID: " . ($id ?: 'none') . "\n";
                
                $content = $node->html();
                $contentLength = strlen($content);
                echo "Content length: $contentLength bytes\n";
                
                if ($contentLength > 0 && $contentLength < 50000) {
                    $jsonData = json_decode($content, true);
                    if ($jsonData) {
                        echo "✓ Valid JSON\n";
                        echo "Top-level keys: " . implode(', ', array_keys($jsonData)) . "\n";
                        
                        // Check for property data
                        if (isset($jsonData['propertyData'])) {
                            echo "✓✓✓ Found propertyData!\n";
                        } elseif (isset($jsonData['props']['pageProps']['propertyData'])) {
                            echo "✓✓✓ Found propertyData in props.pageProps!\n";
                        }
                    } else {
                        echo "✗ Invalid JSON: " . json_last_error_msg() . "\n";
                    }
                } else {
                    echo "Content preview: " . substr($content, 0, 200) . "...\n";
                }
                echo "\n";
            });
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
