<?php
// Test script to extract window.PAGE_MODEL from Rightmove
require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;

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

$testUrl = 'https://www.rightmove.co.uk/properties/169746464';
echo "Fetching: $testUrl\n\n";

try {
    $response = $client->request('GET', $testUrl);
    $html = $response->getBody()->getContents();
    
    // Extract window.PAGE_MODEL using regex
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        echo "✓ Found window.PAGE_MODEL\n\n";
        
        $jsonData = json_decode($matches[1], true);
        
        if ($jsonData) {
            echo "✓ JSON parsed successfully\n\n";
            echo "Top-level keys: " . implode(', ', array_keys($jsonData)) . "\n\n";
            
            // Check for property data
            if (isset($jsonData['propertyData'])) {
                echo "✓✓✓ Found propertyData!\n\n";
                $propertyData = $jsonData['propertyData'];
                
                echo "Property details:\n";
                echo "- Address: " . ($propertyData['address']['displayAddress'] ?? 'N/A') . "\n";
                echo "- Price: " . ($propertyData['prices']['primaryPrice'] ?? 'N/A') . "\n";
                echo "- Bedrooms: " . ($propertyData['bedrooms'] ?? 'N/A') . "\n";
                echo "- Bathrooms: " . ($propertyData['bathrooms'] ?? 'N/A') . "\n";
                echo "- Property Type: " . ($propertyData['propertySubType'] ?? 'N/A') . "\n";
                echo "- Images: " . count($propertyData['images'] ?? []) . "\n\n";
                
                if (!empty($propertyData['images'])) {
                    echo "First image URL: " . $propertyData['images'][0]['srcUrl'] . "\n";
                }
            } else {
                echo "Keys in JSON: " . implode(', ', array_keys($jsonData)) . "\n";
            }
        } else {
            echo "✗ JSON parsing failed: " . json_last_error_msg() . "\n";
            echo "Match content preview: " . substr($matches[1], 0, 500) . "\n";
        }
    } else {
        echo "✗ window.PAGE_MODEL not found with regex\n";
        echo "Trying multiline approach...\n\n";
        
        // Try more robust regex
        if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{(?:[^{}]|(?R))*\})/s', $html, $matches)) {
            echo "✓ Found with multiline regex\n";
            file_put_contents('page_model_output.json', $matches[1]);
            echo "Saved to page_model_output.json\n";
        } else {
            echo "Still not found. Trying simple string search...\n";
            $pos = strpos($html, 'window.PAGE_MODEL');
            if ($pos !== false) {
                echo "Found at position: $pos\n";
                echo "Context: " . substr($html, $pos, 500) . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
