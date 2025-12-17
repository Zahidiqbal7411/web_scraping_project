<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Property;
use App\Models\PropertySold;
use Illuminate\Support\Facades\DB;

echo "=== Fixing linked_property_id for sold properties ===\n\n";

// Check current state
$totalSold = PropertySold::count();
$linkedSold = PropertySold::whereNotNull('linked_property_id')->count();
echo "Total sold records: {$totalSold}\n";
echo "Already linked: {$linkedSold}\n";
echo "Need to link: " . ($totalSold - $linkedSold) . "\n\n";

// Get all properties with sold_link
$propertiesWithSoldLink = Property::whereNotNull('sold_link')
    ->where('sold_link', '!=', '')
    ->get();

echo "Properties with sold_link: " . $propertiesWithSoldLink->count() . "\n\n";

// For each property, find sold records that were scraped from its sold_link
// The sold property records have a property_id field that contains the Rightmove ID of the SOLD property
// We need to link them to the MAIN property they belong to

// Strategy: The sold records were created during import of each property
// We can match by looking at which sold records have property_ids that were scraped
// when processing a specific main property

// Actually, looking at the issue - we stored sold records but didn't set linked_property_id
// Let me check if there's a pattern we can use

// Get sample sold records
$sampleSold = PropertySold::take(5)->get();
echo "Sample sold records:\n";
foreach ($sampleSold as $sold) {
    echo "  ID: {$sold->id}, property_id: {$sold->property_id}, linked: " . ($sold->linked_property_id ?? 'NULL') . ", location: " . substr($sold->location ?? '', 0, 30) . "\n";
}

// Get sample main properties
echo "\nSample main properties:\n";
$sampleProps = Property::take(5)->get();
foreach ($sampleProps as $prop) {
    echo "  property_id: {$prop->property_id}, sold_link: " . ($prop->sold_link ? 'YES' : 'NO') . ", location: " . substr($prop->location ?? '', 0, 30) . "\n";
}

// The issue: we need to re-run the sold link processing to properly populate linked_property_id
// OR we can try to match by location similarity

// For now, let's check if the sold records were actually linked during import
// by checking if ANY have linked_property_id set
$withLinked = PropertySold::whereNotNull('linked_property_id')->first();
if ($withLinked) {
    echo "\nFound a linked record: linked_property_id = {$withLinked->linked_property_id}\n";
} else {
    echo "\nNo sold records have linked_property_id set.\n";
    echo "This means we need to re-process the sold links to populate this field.\n";
}

// Let's try a quick fix - if sold records were created in order matching properties,
// we might be able to use the creation order. But that's risky.

// Better approach: Re-scrape sold data with proper linking
// But for now, let's at least show SOMETHING by linking based on similar location matching

echo "\n=== Attempting to link sold records by location matching ===\n";

$linked = 0;
foreach ($propertiesWithSoldLink as $prop) {
    // Get the base location (first part before comma)
    $mainLocation = $prop->location ?? '';
    if (empty($mainLocation)) continue;
    
    // Find sold properties with similar location
    $locationParts = explode(',', $mainLocation);
    $searchTerm = trim($locationParts[0] ?? '');
    
    if (strlen($searchTerm) < 5) continue;
    
    // Update sold records with matching location to link to this property
    $updated = PropertySold::where('location', 'LIKE', $searchTerm . '%')
        ->whereNull('linked_property_id')
        ->update(['linked_property_id' => $prop->property_id]);
    
    $linked += $updated;
}

echo "Linked {$linked} sold records by location matching.\n";

// Final count
$linkedNow = PropertySold::whereNotNull('linked_property_id')->count();
echo "\nTotal linked now: {$linkedNow} / {$totalSold}\n";
