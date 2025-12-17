<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

echo "Checking if linked_property_id column exists...\n";

if (!Schema::hasColumn('properties_sold', 'linked_property_id')) {
    echo "Column does not exist. Adding it now...\n";
    
    Schema::table('properties_sold', function (Blueprint $table) {
        $table->unsignedBigInteger('linked_property_id')->nullable()->after('property_id')->index();
    });
    
    echo "SUCCESS: linked_property_id column added!\n";
} else {
    echo "Column already exists.\n";
}

// Verify
$columns = Schema::getColumnListing('properties_sold');
echo "\nCurrent columns in properties_sold:\n";
print_r($columns);
