<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DATABASE COUNTS ===\n";
echo "Properties: " . \App\Models\Property::count() . "\n";
echo "PropertySold: " . \App\Models\PropertySold::count() . "\n";
echo "PropertySoldPrice: " . \App\Models\PropertySoldPrice::count() . "\n\n";

echo "=== SCHEMA CHECK ===\n";
// Check properties_sold table structure
$soldColumns = DB::select("DESCRIBE properties_sold");
echo "properties_sold columns:\n";
foreach ($soldColumns as $col) {
    echo "  - {$col->Field}: {$col->Type}\n";
}

echo "\n=== SAMPLE PROPERTY ===\n";
$property = \App\Models\Property::first();
if ($property) {
    echo "Property ID: {$property->property_id} (type: " . gettype($property->property_id) . ")\n";
    echo "Has sold_link: " . ($property->sold_link ? "YES - {$property->sold_link}" : "NO") . "\n";
    echo "sold_link value: " . var_export($property->sold_link, true) . "\n";
    
    echo "\nRelationship Test:\n";
    $soldProps = $property->soldProperties;
    echo "soldProperties count from relationship: {$soldProps->count()}\n";
    
    // Direct query test
    $directCount = \App\Models\PropertySold::where('property_id', $property->property_id)->count();
    echo "Direct query for property_id={$property->property_id}: {$directCount} records\n";
    
    // Check if any sold records exist at all
    $anySold = \App\Models\PropertySold::first();
    if ($anySold) {
        echo "\nFirst PropertySold record:\n";
        echo "  ID: {$anySold->id}\n";
        echo "  property_id: {$anySold->property_id} (type: " . gettype($anySold->property_id) . ")\n";
        echo "  location: {$anySold->location}\n";
    } else {
        echo "\nNo PropertySold records found in database!\n";
    }
} else {
    echo "No properties found!\n";
}

echo "\n=== SOLD LINK ANALYSIS ===\n";
$withSoldLink = \App\Models\Property::whereNotNull('sold_link')->count();
echo "Properties with sold_link: {$withSoldLink}\n";
if ($withSoldLink > 0) {
    $sample = \App\Models\Property::whereNotNull('sold_link')->first();
    echo "Sample sold_link: {$sample->sold_link}\n";
}
