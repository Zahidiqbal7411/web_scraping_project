<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing loadPropertiesFromDatabase endpoint...\n\n";

try {
    $controller = new \App\Http\Controllers\InternalPropertyController(
        new \App\Services\InternalPropertyService,
        new \App\Services\RightmoveScraperService
    );
    
    $request = new \Illuminate\Http\Request();
    $request->merge(['search_id' => 1, 'page' => 1, 'per_page' => 50]);
    
    echo "Calling loadPropertiesFromDatabase...\n";
    $response = $controller->loadPropertiesFromDatabase($request);
    
    echo "Response status: " . $response->getStatusCode() . "\n";
    echo "Response data:\n";
    print_r($response->getData());
    
}catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
