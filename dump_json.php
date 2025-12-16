<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

$url = 'https://www.rightmove.co.uk/properties/135107819#/?channel=RES_BUY';

$client = new Client([
    'verify' => false,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]
]);

try {
    $response = $client->get($url);
    $html = $response->getBody()->getContents();

    $jsonData = null;
    
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        $jsonData = json_decode($matches[1], true);
    } elseif (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $html, $matches)) {
         $jsonData = json_decode($matches[1], true);
    }

    if ($jsonData) {
        // Save to file
        file_put_contents('property_data.json', json_encode($jsonData, JSON_PRETTY_PRINT));
        echo "Saved JSON to property_data.json\n";
    } else {
        echo "JSON extraction failed.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
