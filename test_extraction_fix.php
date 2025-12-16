<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InternalPropertyService;

// Instantiate the service
$service = new InternalPropertyService();

$url = 'https://www.rightmove.co.uk/properties/135107819#/?channel=RES_BUY'; // Use a property known to have sold info/features

echo "Testing extraction for URL: $url\n";

try {
    // We can use fetchPropertyData to simulate the full flow
    $result = $service->fetchPropertyData($url);

    if ($result['success']) {
        echo "\nSuccess! Extracted Data:\n";
        
        echo "Title: " . $result['title'] . "\n";
        echo "Price: " . $result['price'] . "\n";
        
        echo "\n--- Key Features ---\n";
        if (!empty($result['key_features'])) {
            print_r($result['key_features']);
        } else {
            echo "EMPTY\n";
        }
        
        echo "\n--- Description ---\n";
        if (!empty($result['description'])) {
            echo substr($result['description'], 0, 100) . "... (length: " . strlen($result['description']) . ")\n";
        } else {
            echo "EMPTY\n";
        }
        
        echo "\n--- Sold Link ---\n";
        echo "Sold Link: " . ($result['sold_link'] ?? 'NULL') . "\n";

    } else {
        echo "Failed to fetch property: " . $result['error'] . "\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
