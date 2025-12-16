<?php

use App\Models\Url;
use App\Models\Property;
use App\Models\SavedSearch;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "--- Debugging Database Counts ---" . PHP_EOL;

// 1. Total URLs
$totalUrls = Url::count();
echo "Total URLs in DB: " . $totalUrls . PHP_EOL;

// 2. URLs by Filter ID
echo "URLs grouped by filter_id:" . PHP_EOL;
$urlsByFilter = Url::select('filter_id', \DB::raw('count(*) as total'))
    ->groupBy('filter_id')
    ->get();

foreach ($urlsByFilter as $group) {
    echo "  Filter ID: " . ($group->filter_id ?? 'NULL') . " - Count: " . $group->total . PHP_EOL;
}

// 3. Saved Searches
echo "Saved Searches:" . PHP_EOL;
$searches = SavedSearch::all();
foreach ($searches as $search) {
    echo "  ID: " . $search->id . " - Area: " . $search->area . " - URL: " . substr($search->updates_url, 0, 50) . "..." . PHP_EOL;
}

// 4. Properties
$totalProperties = Property::count();
echo "Total Properties in DB: " . $totalProperties . PHP_EOL;

echo "--- End Debug ---" . PHP_EOL;
