<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Property;
use App\Models\PropertySold;
use Illuminate\Support\Facades\DB;

echo "=== Populating source_sold_link for existing sold records ===\n\n";

// Get all properties with sold_link
$propertiesWithSoldLink = Property::whereNotNull('sold_link')
    ->where('sold_link', '!=', '')
    ->get();

echo "Found " . $propertiesWithSoldLink->count() . " properties with sold_link\n\n";

$updated = 0;
$skipped = 0;

foreach ($propertiesWithSoldLink as $prop) {
    $soldLink = $prop->sold_link;
    
    // Get base location from property (first line/street name)
    $mainLocation = $prop->location ?? '';
    if (empty($mainLocation)) {
        $skipped++;
        continue;
    }
    
    // Extract street name (first part before comma)
    $locationParts = explode(',', $mainLocation);
    $streetPart = trim($locationParts[0] ?? '');
    
    if (strlen($streetPart) < 3) {
        $skipped++;
        continue;
    }
    
    // Find sold records that match this location pattern and don't have source_sold_link
    $matchCount = PropertySold::where('location', 'LIKE', '%' . $streetPart . '%')
        ->whereNull('source_sold_link')
        ->update(['source_sold_link' => $soldLink]);
    
    if ($matchCount > 0) {
        echo "Property {$prop->property_id}: Matched $matchCount sold records to sold_link\n";
        $updated += $matchCount;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated sold records\n";
echo "Skipped: $skipped properties (no location)\n";

// Check final counts
$withLink = PropertySold::whereNotNull('source_sold_link')->count();
$total = PropertySold::count();
echo "\nTotal sold records with source_sold_link: $withLink / $total\n";
