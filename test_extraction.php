<?php

use App\Services\InternalPropertyService;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$json = json_decode(file_get_contents('rightmove_data.json'), true);

if (!$json) {
    die("Could not load rightmove_data.json\n");
}

$service = new InternalPropertyService();

// logical way to test private method
$reflection = new ReflectionClass(InternalPropertyService::class);
$method = $reflection->getMethod('extractPropertyDetails');
$method->setAccessible(true);

$details = $method->invokeArgs($service, [$json]);

echo "Extracted Details:\n";
print_r($details);

// Check specific fields
$missing = [];
$required = ['key_features', 'description', 'sold_link', 'council_tax', 'parking', 'garden', 'accessibility', 'ground_rent', 'annual_service_charge', 'lease_length'];

foreach ($required as $field) {
    if (empty($details[$field])) {
        // Some might be null/empty if not in JSON, but we check if logic tried.
        // For this specific JSON, we expect them to be present (except maybe some if missing in JSON)
        // parking/accessibility in example JSON were empty arrays in 'features', so they might be 'Ask agent' or similar default.
        echo "Field '$field': " . (is_array($details[$field]) ? json_encode($details[$field]) : $details[$field]) . "\n";
    } else {
        echo "Field '$field': " . (is_array($details[$field]) ? count($details[$field]) . " items" : "Present") . "\n";
    }
}
