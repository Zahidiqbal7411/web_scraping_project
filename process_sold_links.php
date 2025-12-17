<?php

/**
 * Script to process all sold_link URLs from properties table
 * and populate properties_sold and properties_sold_prices tables
 * 
 * Usage: php process_sold_links.php [limit]
 * Example: php process_sold_links.php 10  (process only 10 properties)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Property;
use App\Models\PropertySold;
use App\Models\PropertySoldPrice;
use App\Services\InternalPropertyService;
use Illuminate\Support\Facades\Log;

echo "=== PROCESSING SOLD LINKS FROM PROPERTIES TABLE ===\n\n";

// Get limit from command line argument
$limit = isset($argv[1]) ? (int)$argv[1] : null;

// Get all properties with sold_link
$query = Property::whereNotNull('sold_link')->where('sold_link', '!=', '');

if ($limit) {
    $query->limit($limit);
    echo "Processing up to {$limit} properties...\n";
}

$properties = $query->get();
$totalProperties = $properties->count();

echo "Found {$totalProperties} properties with sold_link\n\n";

if ($totalProperties === 0) {
    echo "No properties with sold_link found. Exiting.\n";
    exit(0);
}

$service = new InternalPropertyService();
$processedCount = 0;
$soldPropertiesCount = 0;
$soldPricesCount = 0;
$errors = [];

foreach ($properties as $index => $property) {
    $current = $index + 1;
    echo "[{$current}/{$totalProperties}] Processing property ID: {$property->property_id}\n";
    echo "  Location: {$property->location}\n";
    echo "  Sold Link: {$property->sold_link}\n";
    
    try {
        // Scrape sold properties from the sold link
        $soldData = $service->scrapeSoldProperties($property->sold_link, $property->property_id);
        
        if (empty($soldData)) {
            echo "  ⚠️ No sold data found\n\n";
            continue;
        }
        
        echo "  Found " . count($soldData) . " sold properties\n";
        
        foreach ($soldData as $soldProp) {
            try {
                // Validate we have minimum required data
                if (empty($soldProp['property_id']) && empty($soldProp['location'])) {
                    echo "    Skipping: No ID or location\n";
                    continue;
                }
                
                // Save to properties_sold
                $soldRecord = PropertySold::updateOrCreate(
                    ['property_id' => $soldProp['property_id']],
                    [
                        'location' => $soldProp['location'] ?? '',
                        'property_type' => $soldProp['property_type'] ?? '',
                        'bedrooms' => $soldProp['bedrooms'],
                        'bathrooms' => $soldProp['bathrooms'],
                        'tenure' => $soldProp['tenure'] ?? ''
                    ]
                );
                
                $soldPropertiesCount++;
                
                // Save transaction history
                if (!empty($soldProp['transactions'])) {
                    // Clear old transactions
                    PropertySoldPrice::where('sold_property_id', $soldRecord->id)->delete();
                    
                    foreach ($soldProp['transactions'] as $trans) {
                        if (!empty($trans['price']) || !empty($trans['date'])) {
                            PropertySoldPrice::create([
                                'sold_property_id' => $soldRecord->id,
                                'sold_price' => $trans['price'] ?? '',
                                'sold_date' => $trans['date'] ?? ''
                            ]);
                            $soldPricesCount++;
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $errors[] = "Property {$property->property_id}: " . $e->getMessage();
            }
        }
        
        $processedCount++;
        echo "  ✓ Completed\n\n";
        
        // Small delay to be respectful to the server
        usleep(500000); // 0.5 second
        
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $errors[] = "Property {$property->property_id}: " . $e->getMessage();
    }
}

echo str_repeat("=", 50) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 50) . "\n";
echo "Properties processed: {$processedCount}/{$totalProperties}\n";
echo "Sold properties saved: {$soldPropertiesCount}\n";
echo "Sold prices saved: {$soldPricesCount}\n";

if (!empty($errors)) {
    echo "\nErrors (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\nDone!\n";
