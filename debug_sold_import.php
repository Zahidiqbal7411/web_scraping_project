<?php

/**
 * Debug script to check why sold property data is not importing
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SOLD PROPERTY IMPORT DEBUG ===\n\n";

// 1. Check if properties have sold_link populated
echo "1. Checking properties for sold_link field:\n";
echo str_repeat("-", 60) . "\n";

$properties = \App\Models\Property::take(10)->get();
$withSoldLink = \App\Models\Property::whereNotNull('sold_link')->count();
$totalProperties = \App\Models\Property::count();

echo "Total properties: {$totalProperties}\n";
echo "Properties with sold_link: {$withSoldLink}\n";
echo "Properties without sold_link: " . ($totalProperties - $withSoldLink) . "\n\n";

if ($withSoldLink > 0) {
    echo "Sample properties WITH sold_link:\n";
    $samples = \App\Models\Property::whereNotNull('sold_link')->take(3)->get();
    foreach ($samples as $prop) {
        echo "  Property ID: {$prop->property_id}\n";
        echo "  Location: {$prop->location}\n";
        echo "  Sold Link: {$prop->sold_link}\n\n";
    }
} else {
    echo "⚠️  NO properties have sold_link populated!\n";
    echo "This means sold links are not being extracted during import.\n\n";
}

// 2. Check sold properties table
echo "\n2. Checking properties_sold table:\n";
echo str_repeat("-", 60) . "\n";
$soldCount = \App\Models\PropertySold::count();
echo "Total sold properties: {$soldCount}\n";

if ($soldCount > 0) {
    $sample = \App\Models\PropertySold::with('prices')->first();
    echo "Sample sold property:\n";
    echo "  ID: {$sample->id}\n";
    echo "  Property ID: {$sample->property_id}\n";
    echo "  Location: {$sample->location}\n";
    echo "  Price records: {$sample->prices->count()}\n";
}

// 3. Check sold prices table
echo "\n3. Checking properties_sold_prices table:\n";
echo str_repeat("-", 60) . "\n";
$pricesCount = \App\Models\PropertySoldPrice::count();
echo "Total sold price records: {$pricesCount}\n";

// 4. Test extraction from a real property URL
echo "\n4. Testing sold link extraction from sample property:\n";
echo str_repeat("-", 60) . "\n";

// Use one of your existing properties
$sampleProperty = \App\Models\Property::first();
if ($sampleProperty) {
    echo "Testing with property: {$sampleProperty->property_id}\n";
    echo "Location: {$sampleProperty->location}\n";
    echo "Current sold_link value: " . ($sampleProperty->sold_link ?? 'NULL') . "\n\n";
    
    // Try to fetch and extract sold link
    $propertyUrl = "https://www.rightmove.co.uk/properties/{$sampleProperty->property_id}";
    echo "Fetching property page: {$propertyUrl}\n";
    echo "(This will take a few seconds...)\n\n";
    
    try {
        $service = new \App\Services\InternalPropertyService();
        $data = $service->fetchPropertyData($propertyUrl);
        
        if ($data['success']) {
            echo "✓ Property data fetched successfully\n";
            echo "Sold Link found: " . ($data['sold_link'] ?? 'NOT FOUND') . "\n\n";
            
            if (!empty($data['sold_link'])) {
                echo "Testing sold property scraping...\n";
                $soldData = $service->scrapeSoldProperties($data['sold_link'], $sampleProperty->property_id);
                echo "Sold properties found: " . count($soldData) . "\n";
                
                if (!empty($soldData)) {
                    echo "\nFirst sold property:\n";
                    $first = $soldData[0];
                    echo "  Location: " . ($first['location'] ?? 'N/A') . "\n";
                    echo "  Property Type: " . ($first['property_type'] ?? 'N/A') . "\n";
                    echo "  Bedrooms: " . ($first['bedrooms'] ?? 'N/A') . "\n";
                    echo "  Transactions: " . count($first['transactions'] ?? []) . "\n";
                    
                    if (!empty($first['transactions'])) {
                        echo "\n  Price history:\n";
                        foreach ($first['transactions'] as $trans) {
                            echo "    - " . ($trans['price'] ?? 'N/A') . " on " . ($trans['date'] ?? 'N/A') . "\n";
                        }
                    }
                }
            } else {
                echo "⚠️  Sold link NOT extracted from property data!\n";
                echo "This indicates an issue with the extraction logic.\n";
            }
        } else {
            echo "✗ Failed to fetch property: " . ($data['error'] ?? 'Unknown error') . "\n";
        }
        
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
} else {
    echo "No properties in database to test with.\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "DIAGNOSIS:\n";
echo "If properties have sold_link but tables are empty:\n";
echo "  → Sold scraping logic is not being triggered during import\n";
echo "If properties DON'T have sold_link:\n";
echo "  → Sold link extraction is failing (JSON path issue)\n";
echo "If test extraction shows sold_link:\n";
echo "  → Import process is not saving sold_link to database\n";
echo str_repeat("=", 60) . "\n";
