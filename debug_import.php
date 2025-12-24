<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "Starting Debug Script (Raw Mode)...\n";

$url = "https://www.rightmove.co.uk/properties/166390709#/?channel=RES_BUY"; // Using the URL from previous run

echo "Fetching URL: $url\n";

$client = new Client([
    'verify' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ]
]);

try {
    $response = $client->get($url);
    $html = $response->getBody()->getContents();
    echo "Fetched HTML. Length: " . strlen($html) . " bytes.\n";

    // 1. Test CURRENT Regex (Suspected Failure)
    echo "\n--- Testing CURRENT Regex ---\n";
    $currentRegex = '/window\.PAGE_MODEL\s*=\s*(\{.*?\})(?:\s*;|\s*<\/script>)/s';
    
    if (preg_match($currentRegex, $html, $matches)) {
        echo "Match Found!\n";
        echo "Match Length: " . strlen($matches[1]) . "\n";
        $json = json_decode($matches[1], true);
        if ($json) {
            echo "JSON Decode: SUCCESS\n";
            echo "Keys: " . implode(', ', array_keys($json)) . "\n";
        } else {
            echo "JSON Decode: FAILED (Error: " . json_last_error_msg() . ")\n";
            echo "Snippet of match end: " . substr($matches[1], -50) . "\n";
        }
    } else {
        echo "No Match Found.\n";
    }

    // 2. Test RECURSIVE Regex (Proposed Fix)
    echo "\n--- Testing RECURSIVE Regex ---\n";
    // This pattern matches balanced braces
    $recursiveRegex = '/window\.PAGE_MODEL\s*=\s*(\{(?:[^{}]++|(?1))*\})/s';
    
    if (preg_match($recursiveRegex, $html, $matches)) {
        echo "Match Found!\n";
        echo "Match Length: " . strlen($matches[1]) . "\n";
        
        // Trim slightly if needed (sometimes captures trailing stuff if regex is loose, but recursive should be exact)
        $jsonString = $matches[1];
        
        $json = json_decode($jsonString, true);
        if ($json) {
            echo "JSON Decode: SUCCESS\n";
            echo "Keys: " . implode(', ', array_keys($json)) . "\n";
            if (isset($json['propertyData'])) {
                echo "propertyData found! This is the fix.\n";
            }
        } else {
            echo "JSON Decode: FAILED (Error: " . json_last_error_msg() . ")\n";
             echo "Snippet of match end: " . substr($jsonString, -50) . "\n";
        }
    } else {
        echo "No Match Found.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
