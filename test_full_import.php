<?php

use App\Services\InternalPropertyService;
use App\Models\Url;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== FULL IMPORT TEST ===\n\n";

// Get the first URL from database
$urlModel = Url::first();

if (!$urlModel) {
    die("No URLs in database. Please import URLs first.\n");
}

$testUrl = $urlModel->url;
echo "Testing with URL: {$testUrl}\n\n";

$service = new InternalPropertyService();

// Fetch properties (this calls fetchPropertiesConcurrently which uses extractPropertyDetails)
$result = $service->fetchPropertiesConcurrently([['url' => $testUrl]]);

echo "Result:\n";
print_r($result);

if (!empty($result['properties'])) {
    $prop = $result['properties'][0];
    echo "\n=== EXTRACTED DATA ===\n";
    echo "URL: " . ($prop['url'] ?? 'N/A') . "\n";
    echo "Address: " . ($prop['address'] ?? 'N/A') . "\n";
    echo "Price: " . ($prop['price'] ?? 'N/A') . "\n";
    echo "Description: " . (empty($prop['description']) ? 'EMPTY' : substr($prop['description'], 0, 100) . '...') . "\n";
    echo "Key Features: " . (empty($prop['key_features']) ? 'EMPTY' : count($prop['key_features']) . ' features') . "\n";
    echo "Sold Link: " . ($prop['sold_link'] ?? 'NULL') . "\n";
    echo "Council Tax: " . ($prop['council_tax'] ?? 'NULL') . "\n";
    echo "Parking: " . ($prop['parking'] ?? 'NULL') . "\n";
    echo "Garden: " . ($prop['garden'] ?? 'NULL') . "\n";
    echo "Accessibility: " . ($prop['accessibility'] ?? 'NULL') . "\n";
    echo "Ground Rent: " . ($prop['ground_rent'] ?? 'NULL') . "\n";
    echo "Service Charge: " . ($prop['annual_service_charge'] ?? 'NULL') . "\n";
    echo "Lease Length: " . ($prop['lease_length'] ?? 'NULL') . "\n";
} else {
    echo "\nNO PROPERTIES EXTRACTED!\n";
}
