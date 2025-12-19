<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Saved Searches ===\n";
$searches = \App\Models\SavedSearch::all(['id', 'area']);
echo "Total: {$searches->count()}\n\n";

foreach ($searches as $search) {
    echo "ID: {$search->id}, Area: {$search->area}\n";
}

// Check if ID 6 exists
$search6 = \App\Models\SavedSearch::find(6);
if ($search6) {
    echo "\nSaved Search ID=6 EXISTS: {$search6->area}\n";
} else {
    echo "\nSaved Search ID=6 does NOT exist\n";
}

echo "\n=== Properties ===\n";
$props = \App\Models\Property::selectRaw('filter_id, COUNT(*) as count')
    ->groupBy('filter_id')
    ->get();
    
foreach ($props as $p) {
    $fid = $p->filter_id ?? 'NULL';
    echo "filter_id={$fid}: {$p->count} properties\n";
}

echo "\n=== Solution ===\n";

if (!$search6) {
    echo "OPTION 1: Navigate to the saved search page that has ID=1\n";
    echo "OPTION 2: Create a new saved search for Manchester (it will get ID=6 or higher)\n";
} else {
    echo "Saved search ID=6 exists. Properties just need to be updated.\n";
    echo "Run: UPDATE properties SET filter_id=6 WHERE filter_id=1;\n";
}
