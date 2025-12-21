<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InternalPropertyService;

$service = new InternalPropertyService();
$url = 'https://www.rightmove.co.uk/house-prices/M30-0DU.html';

echo "Scraping sold properties from: $url\n";
$results = $service->scrapeSoldProperties($url);

echo "Found " . count($results) . " sold properties.\n\n";

foreach ($results as $index => $prop) {
    if (empty($prop['image_url']) && !empty($prop['map_url'])) {
        echo "Property " . ($index + 1) . " (NO IMAGE, HAS MAP):\n";
        echo "Location: " . $prop['location'] . "\n";
        echo "Map URL: " . $prop['map_url'] . "\n\n";
    } elseif (!empty($prop['image_url'])) {
        echo "Property " . ($index + 1) . " (HAS IMAGE):\n";
        echo "Location: " . $prop['location'] . "\n";
        echo "Image URL: " . $prop['image_url'] . "\n\n";
    }
}
