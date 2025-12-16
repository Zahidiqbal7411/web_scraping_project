<?php

use App\Models\Url;
use App\Models\Property;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "--- Debugging Property Matching ---" . PHP_EOL;

// Get first 20 URLs
$urls = Url::take(20)->get();
$matchedCount = 0;
$totalChecked = 0;

foreach ($urls as $urlRecord) {
    $totalChecked++;
    $url = $urlRecord->url;
    echo "Checking URL: " . substr($url, 0, 60) . "..." . PHP_EOL;
    
    if (preg_match('/properties\/(\d+)/', $url, $matches)) {
        $propId = $matches[1];
        echo "  Extracted ID: " . $propId . PHP_EOL;
        
        $prop = Property::where('property_id', $propId)->first();
        if ($prop) {
            echo "  [MATCH] Found in Properties table! Price: " . $prop->price . PHP_EOL;
            $matchedCount++;
        } else {
            echo "  [FAIL] ID extracted but NOT found in Properties table." . PHP_EOL;
        }
    } else {
        echo "  [FAIL] Could not extract ID from URL." . PHP_EOL;
    }
    echo "------------------------" . PHP_EOL;
}

echo "Summary: Matched $matchedCount out of $totalChecked checked." . PHP_EOL;

// Check if we have properties that DON'T match this pattern?
$firstProp = Property::first();
if ($firstProp) {
    echo "Sample Property in DB -> ID: " . $firstProp->property_id . ", Loc: " . $firstProp->location . PHP_EOL;
}
