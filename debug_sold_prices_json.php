<?php
/**
 * Debug Script: Analyze Rightmove House Prices JSON Structure
 * This script fetches a sold prices URL and dumps its JSON structure
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use GuzzleHttp\Client;

// Example sold prices URL - replace with actual sold link from your properties
$soldPricesUrl = 'https://www.rightmove.co.uk/house-prices/london-102959.html';

echo "=== Rightmove House Prices JSON Debug ===" . PHP_EOL . PHP_EOL;
echo "URL: {$soldPricesUrl}" . PHP_EOL . PHP_EOL;

try {
    $client = new Client([
        'verify' => false,
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ]
    ]);
    
    echo "[1] Fetching page..." . PHP_EOL;
    $response = $client->request('GET', $soldPricesUrl);
    $html = $response->getBody()->getContents();
    
    echo "[2] Page fetched (" . strlen($html) . " bytes)" . PHP_EOL . PHP_EOL;
    
    // Try to extract JSON
    echo "[3] Searching for JSON data..." . PHP_EOL . PHP_EOL;
    
    // Method 1: window.PAGE_MODEL
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        echo "✓ Found window.PAGE_MODEL" . PHP_EOL;
        $json = json_decode($matches[1], true);
        
        if ($json) {
            echo "JSON structure keys: " .implode(', ', array_keys($json)) . PHP_EOL . PHP_EOL;
            
            // Save to file for inspection
            file_put_contents('sold_prices_json_structure.json', json_encode($json, JSON_PRETTY_PRINT));
            echo "Full JSON saved to: sold_prices_json_structure.json" . PHP_EOL . PHP_EOL;
            
            // Try to locate properties/results
            echo "[4] Analyzing structure for sold properties..." . PHP_EOL;
            
            $pathsToCheck = [
                'propertyData' => isset($json['propertyData']),
                'propertyData.soldPricesData' => isset($json['propertyData']['soldPricesData']),
                'propertyData.soldPricesData.properties' => isset($json['propertyData']['soldPricesData']['properties']),
                'props' => isset($json['props']),
                'props.pageProps' => isset($json['props']['pageProps']),
                'props.pageProps.results' => isset($json['props']['pageProps']['results']),
                'props.pageProps.properties' => isset($json['props']['pageProps']['properties']),
                'results' => isset($json['results']),
                'properties' => isset($json['properties']),
                'searchResult' => isset($json['searchResult']),
                'searchResult.properties' => isset($json['searchResult']['properties']),
            ];
            
            foreach ($pathsToCheck as $path => $exists) {
                if ($exists) {
                    echo "   ✓ {$path} EXISTS" . PHP_EOL;
                    
                    // Try to get the data
                    $parts = explode('.', $path);
                    $data = $json;
                    foreach ($parts as $part) {
                        $data = $data[$part] ?? null;
                    }
                    
                    if (is_array($data)) {
                        if (isset($data[0])) {
                            echo "      Type: Array with " . count($data) . " items" . PHP_EOL;
                            echo "      First item keys: " . implode(', ', array_keys($data[0])) . PHP_EOL;
                        } else {
                            echo "      Type: Object with keys: " . implode(', ', array_keys($data)) . PHP_EOL;
                        }
                    }
                } else {
                    echo "   ✗ {$path} NOT FOUND" . PHP_EOL;
                }
            }
        } else {
            echo "❌ Failed to parse JSON" . PHP_EOL;
        }
    } else {
        echo "❌ window.PAGE_MODEL not found" . PHP_EOL;
        
        // Try script tag
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            echo "✓ Found __NEXT_DATA__ script tag" . PHP_EOL;
            $json = json_decode($matches[1], true);
            if ($json) {
                echo "JSON structure keys: " . implode(', ', array_keys($json)) . PHP_EOL;
                file_put_contents('sold_prices_json_structure.json', json_encode($json, JSON_PRETTY_PRINT));
                echo "JSON saved to: sold_prices_json_structure.json" . PHP_EOL;
            }
        } else {
            echo "❌ __NEXT_DATA__ not found either" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "Debug complete." . PHP_EOL;
