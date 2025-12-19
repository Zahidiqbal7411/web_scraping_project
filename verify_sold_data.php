<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== SOLD DATA VERIFICATION ===\n\n";

echo "Table Counts:\n";
echo "- Properties: " . \App\Models\Property::count() . "\n";
echo "- PropertySold: " . \App\Models\PropertySold::count() . "\n";
echo "- PropertySoldPrice: " . \App\Models\PropertySoldPrice::count() . "\n\n";

echo "Properties with sold_link: " . \App\Models\Property::whereNotNull('sold_link')->count() . "\n\n";

if (\App\Models\Property::count() > 0) {
    // Find a property WITH sold data
    $propWithSold = \App\Models\Property::whereHas('soldProperties')->with('soldProperties.prices')->first();
    
    if ($propWithSold) {
        echo "✓ FOUND Property WITH Sold Data!\n";
        echo "  Property ID: {$propWithSold->property_id}\n";
        echo "  Location: {$propWithSold->location}\n";
        echo "  Sold Link: {$propWithSold->sold_link}\n";
        echo "  Sold Properties Count: {$propWithSold->soldProperties->count()}\n";
        
        $firstSold = $propWithSold->soldProperties->first();
        if ($firstSold) {
            echo "\n  First Sold Property:\n";
            echo "    - Location: {$firstSold->location}\n";
            echo "    - Property Type: {$firstSold->property_type}\n";
            echo "    - Bedrooms: {$firstSold->bedrooms}\n";
            echo "   - Prices Count: {$firstSold->prices->count()}\n";
            
            if ($firstSold->prices->count() > 0) {
                $firstPrice = $firstSold->prices->first();
                echo "    - First Price: {$firstPrice->sold_price} on {$firstPrice->sold_date}\n";
            }
        }
    } else {
        echo "✗ NO properties found with sold data!\n";
        
        // Check if ANY sold properties exist
        $anySold = \App\Models\PropertySold::first();
        if ($anySold) {
            echo "\n  But sold properties DO exist in database:\n";
            echo "  - Sold Property ID: {$anySold->id}\n";
            echo "  - Linked to Property ID: {$anySold->property_id}\n";
            echo "  - Location: {$anySold->location}\n";
            
            // Check if the parent property exists
            $parent = \App\Models\Property::where('property_id', $anySold->property_id)->first();
            if ($parent) {
                echo "  - Parent property EXISTS\n";
                echo "  - Parent sold_link: " . ($parent->sold_link ?? 'NULL') . "\n";
            } else {
                echo "  - Parent property NOT FOUND (orphaned sold property)\n";
            }
        }
    }
}

echo "\n=== SCHEMA CHECK ===\n";
$columns = DB::select("DESCRIBE properties_sold");
echo "properties_sold.property_id type: ";
foreach ($columns as $col) {
    if ($col->Field === 'property_id') {
        echo "{$col->Type}\n";
        break;
    }
}
