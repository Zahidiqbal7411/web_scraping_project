<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\Url;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\SavedSearch;

echo "--- Database Counts ---\n";
echo "Total URLs: " . Url::count() . "\n";
echo "Total Properties: " . Property::count() . "\n";
echo "Total Property Images: " . PropertyImage::count() . "\n";
echo "Total Saved Searches: " . SavedSearch::count() . "\n";

echo "\n--- First 5 URLs ---\n";
foreach (Url::take(5)->get() as $url) {
    echo "ID: " . $url->id . ", URL: " . $url->url . ", Filter ID: " . $url->filter_id . "\n";
}

echo "\n--- Saved Searches ---\n";
foreach (SavedSearch::all() as $search) {
    echo "ID: " . $search->id . ", URL: " . $search->updates_url . "\n";
    echo "Related URLs count: " . Url::where('filter_id', $search->id)->count() . "\n";
}
