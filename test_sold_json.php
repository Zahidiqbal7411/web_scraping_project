<?php

/**
 * Test script to examine JSON structure of Rightmove House Prices page
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use GuzzleHttp\Client;

echo "=== TESTING SOLD PROPERTY JSON STRUCTURE ===\n\n";

// Use one of the sold links from the database
$soldUrl = "https://www.rightmove.co.uk/house-prices/nw1-4qp.html";

echo "Fetching: {$soldUrl}\n\n";

$client = new Client([
    'verify' => false,
    'timeout' => 30,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ]
]);

try {
    $response = $client->request('GET', $soldUrl);
    $html = $response->getBody()->getContents();
    
    echo "Page fetched successfully. Size: " . strlen($html) . " bytes\n\n";
    
    // Try to extract JSON data using the same method as InternalPropertyService
    $jsonData = null;
    
    // Method 1: window.PAGE_MODEL
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        echo "✓ Found window.PAGE_MODEL\n";
        $jsonData = json_decode($matches[1], true);
        if ($jsonData) {
            echo "✓ JSON parsed successfully\n\n";
        } else {
            echo "✗ Failed to parse JSON\n";
            echo "Raw JSON (first 500 chars): " . substr($matches[1], 0, 500) . "\n\n";
        }
    } else {
        echo "✗ window.PAGE_MODEL not found\n";
    }
    
    // Method 2: __NEXT_DATA__
    if (!$jsonData && preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
        echo "✓ Found __NEXT_DATA__ script tag\n";
        $jsonData = json_decode($matches[1], true);
        if ($jsonData) {
            echo "✓ JSON parsed successfully\n\n";
        }
    }
    
    if ($jsonData) {
        echo "=== JSON STRUCTURE ANALYSIS ===\n\n";
        
        // Show top-level keys
        echo "Top-level keys:\n";
        foreach (array_keys($jsonData) as $key) {
            echo "  - {$key}\n";
        }
        echo "\n";
        
        // Check various possible paths for sold data
        $pathsToCheck = [
            'propertyData.soldPricesData.properties',
            'props.pageProps.results',
            'props.pageProps.properties', 
            'props.pageProps.propertyData.soldPricesData.properties',
            'results',
            'properties',
            'soldPricesData.properties',
            'propertyData',
            'props'
        ];
        
        echo "Checking possible JSON paths:\n";
        foreach ($pathsToCheck as $path) {
            $keys = explode('.', $path);
            $current = $jsonData;
            $found = true;
            
            foreach ($keys as $key) {
                if (isset($current[$key])) {
                    $current = $current[$key];
                } else {
                    $found = false;
                    break;
                }
            }
            
            if ($found) {
                if (is_array($current)) {
                    $count = count($current);
                    echo "  ✓ {$path} - FOUND ({$count} items)\n";
                    
                    // Show first item structure
                    if ($count > 0 && is_array($current[0])) {
                        echo "    First item keys: " . implode(', ', array_keys($current[0])) . "\n";
                    }
                } else {
                    echo "  ✓ {$path} - FOUND (not an array)\n";
                }
            } else {
                echo "  ✗ {$path} - not found\n";
            }
        }
        
        echo "\n=== SAVING JSON STRUCTURE ===\n";
        file_put_contents('sold_page_json_structure.json', json_encode($jsonData, JSON_PRETTY_PRINT));
        echo "Full JSON saved to: sold_page_json_structure.json\n";
        echo "File size: " . filesize('sold_page_json_structure.json') . " bytes\n";
        
    } else {
        echo "✗ Could not extract JSON data from page\n";
        echo "Saving HTML for manual inspection...\n";
        file_put_contents('sold_page_debug.html', $html);
        echo "HTML saved to: sold_page_debug.html\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
