<?php

/**
 * Verification script to check if property data import is working correctly
 * This script checks:
 * 1. Properties table data
 * 2. Property images
 * 3. URLs table
 * 4. Sold properties
 * 5. Sold prices
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PROPERTY DATA IMPORT VERIFICATION ===\n\n";

// 1. Check Properties
echo "1. PROPERTIES TABLE:\n";
echo str_repeat("-", 50) . "\n";
$properties = \App\Models\Property::with('images')->take(5)->get();
echo "Total Properties: " . \App\Models\Property::count() . "\n";
echo "Sample (first 5):\n";
foreach ($properties as $prop) {
    echo "  - Property ID: {$prop->property_id}\n";
    echo "    Location: {$prop->location}\n";
    echo "    Price: {$prop->price}\n";
    echo "    Bedrooms: {$prop->bedrooms}\n";
    echo "    Bathrooms: {$prop->bathrooms}\n";
    echo "    Property Type: {$prop->property_type}\n";
    echo "    Size: {$prop->size}\n";
    echo "    Tenure: {$prop->tenure}\n";
    echo "    Council Tax: {$prop->council_tax}\n";
    echo "    Parking: {$prop->parking}\n";
    echo "    Garden: {$prop->garden}\n";
    echo "    Accessibility: {$prop->accessibility}\n";
    echo "    Ground Rent: {$prop->ground_rent}\n";
    echo "    Service Charge: {$prop->annual_service_charge}\n";
    echo "    Lease Length: {$prop->lease_length}\n";
    echo "    Key Features: " . (is_array($prop->key_features) ? count($prop->key_features) . " features" : "None") . "\n";
    echo "    Description: " . (strlen($prop->description) > 0 ? "Yes (" . strlen($prop->description) . " chars)" : "None") . "\n";
    echo "    Sold Link: " . ($prop->sold_link ? "Yes" : "No") . "\n";
    echo "    Images: " . $prop->images->count() . "\n";
    echo "\n";
}

// 2. Check Property Images
echo "\n2. PROPERTY IMAGES TABLE:\n";
echo str_repeat("-", 50) . "\n";
$totalImages = \App\Models\PropertyImage::count();
echo "Total Images: {$totalImages}\n";
if ($totalImages > 0) {
    $sampleImage = \App\Models\PropertyImage::first();
    echo "Sample Image:\n";
    echo "  - Property ID: {$sampleImage->property_id}\n";
    echo "  - Image Link: " . substr($sampleImage->image_link, 0, 80) . "...\n";
}

// 3. Check URLs
echo "\n3. URLS TABLE:\n";
echo str_repeat("-", 50) . "\n";
$totalUrls = \App\Models\Url::count();
echo "Total URLs: {$totalUrls}\n";
if ($totalUrls > 0) {
    $sampleUrl = \App\Models\Url::first();
    echo "Sample URL:\n";
    echo "  - Filter ID: {$sampleUrl->filter_id}\n";
    echo "  - URL: " . substr($sampleUrl->url, 0, 80) . "...\n";
}

// 4. Check Sold Properties
echo "\n4. PROPERTIES_SOLD TABLE:\n";
echo str_repeat("-", 50) . "\n";
$totalSold = \App\Models\PropertySold::count();
echo "Total Sold Properties: {$totalSold}\n";
if ($totalSold > 0) {
    $soldProps = \App\Models\PropertySold::with('prices')->take(3)->get();
    echo "Sample (first 3):\n";
    foreach ($soldProps as $sold) {
        echo "  - Sold Property ID: {$sold->id}\n";
        echo "    Rightmove ID: {$sold->property_id}\n";
        echo "    Location: {$sold->location}\n";
        echo "    Property Type: {$sold->property_type}\n";
        echo "    Bedrooms: {$sold->bedrooms}\n";
        echo "    Bathrooms: {$sold->bathrooms}\n";
        echo "    Tenure: {$sold->tenure}\n";
        echo "    Price Records: {$sold->prices->count()}\n";
        if ($sold->prices->count() > 0) {
            foreach ($sold->prices as $price) {
                echo "      * {$price->sold_price} on {$price->sold_date}\n";
            }
        }
        echo "\n";
    }
}

// 5. Check Sold Prices
echo "\n5. PROPERTIES_SOLD_PRICES TABLE:\n";
echo str_repeat("-", 50) . "\n";
$totalPrices = \App\Models\PropertySoldPrice::count();
echo "Total Sold Price Records: {$totalPrices}\n";
if ($totalPrices > 0) {
    $samplePrice = \App\Models\PropertySoldPrice::first();
    echo "Sample Price:\n";
    echo "  - Sold Property ID: {$samplePrice->sold_property_id}\n";
    echo "  - Sold Price: {$samplePrice->sold_price}\n";
    echo "  - Sold Date: {$samplePrice->sold_date}\n";
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY:\n";
echo "  Properties with complete data: " . \App\Models\Property::whereNotNull('bedrooms')
    ->whereNotNull('property_type')
    ->whereNotNull('description')
    ->count() . " / " . \App\Models\Property::count() . "\n";
echo "  Properties with images: " . \App\Models\Property::has('images')->count() . " / " . \App\Models\Property::count() . "\n";
echo "  Properties with sold link: " . \App\Models\Property::whereNotNull('sold_link')->count() . " / " . \App\Models\Property::count() . "\n";
echo "  Total sold properties imported: {$totalSold}\n";
echo "  Total sold price records: {$totalPrices}\n";
echo str_repeat("=", 50) . "\n";
