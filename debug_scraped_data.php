<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$url = 'https://www.rightmove.co.uk/properties/135107819#/?channel=RES_BUY'; // Use a known sold/active property

$client = new Client([
    'verify' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]
]);

try {
    echo "Fetching URL: $url\n";
    $response = $client->get($url);
    $html = $response->getBody()->getContents();

    $jsonData = null;
    
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        $jsonData = json_decode($matches[1], true);
        echo "Found window.PAGE_MODEL\n";
    } elseif (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $html, $matches)) {
         $jsonData = json_decode($matches[1], true);
         echo "Found window.__NEXT_DATA__\n";
    }

    if ($jsonData) {
        $propertyData = $jsonData['propertyData'] ?? $jsonData['props']['pageProps']['propertyData'] ?? null;

        if ($propertyData) {
            echo "--- Key Features ---\n";
            print_r($propertyData['keyFeatures'] ?? 'NOT FOUND');

            echo "\n--- Description ---\n";
            // Check formatted text description
            if (isset($propertyData['text']['description'])) {
                 echo "Found at text.description (length: " . strlen($propertyData['text']['description']) . ")\n";
            } elseif (isset($propertyData['description'])) {
                 echo "Found at description (length: " . strlen($propertyData['description']) . ")\n";
            } else {
                echo "NOT FOUND\n";
            }

            echo "\n--- Sold / History Info ---\n";
            $interestKeys = ['nearbySoldPropertiesUrl', 'history', 'saleHistory', 'marketInfo'];
            foreach ($interestKeys as $key) {
                if (isset($propertyData[$key])) {
                    echo "[$key]: " . print_r($propertyData[$key], true) . "\n";
                }
            }
            
            // Search whole array for "house-prices" or similar in values
            echo "\n--- Searching for 'house-prices' links ---\n";
            array_walk_recursive($propertyData, function($value, $key) {
                if (is_string($value) && (strpos($value, 'house-prices') !== false || strpos($value, 'sold-prices') !== false)) {
                    echo "Found in [$key]: $value\n";
                }
            });

        } else {
            echo "propertyData not found.\n";
        }
    } else {
        echo "JSON extraction failed.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
