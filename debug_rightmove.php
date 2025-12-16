<?php

use App\Services\InternalPropertyService;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = new InternalPropertyService();
$url = 'https://www.rightmove.co.uk/properties/170126447';

echo "Fetching URL: $url\n";

// We need to access the private/protected methods or just copy the logic.
// Since we can't easily access private methods, let's just use Guzzle directly to fetch HTML 
// and then use the regex logic from the service to see what we match.

$client = new \GuzzleHttp\Client([
    'verify' => false,
    'timeout' => 30,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-GB,en;q=0.9',
    ]
]);

try {
    $response = $client->get($url);
    $html = $response->getBody()->getContents();

    echo "HTML Length: " . strlen($html) . "\n";

    $jsonData = null;
    
    // Method 1: PAGE_MODEL
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        echo "Found window.PAGE_MODEL\n";
        $jsonData = json_decode($matches[1], true);
    } elseif (preg_match('/window\.PAGE_MODEL\s*=\s*({.*})\s*$/m', $html, $matches)) {
         // Try a looser regex if the first one fails
         echo "Found window.PAGE_MODEL (loose regex)\n";
         $jsonData = json_decode($matches[1], true);
    }
    
    // Method 3: __NEXT_DATA__
    if (!$jsonData && preg_match('/<script id="__NEXT_DATA__" type="application\/json">([^<]*)<\/script>/', $html, $matches)) {
         echo "Found __NEXT_DATA__ script tag\n";
         $jsonData = json_decode($matches[1], true);
    }

    if ($jsonData) {
        $propertyData = $jsonData['propertyData'] ?? $jsonData['props']['pageProps']['propertyData'] ?? null;
        
        if ($propertyData) {
            echo "Property Data Found!\n";
            echo "Key Features keys: " . (isset($propertyData['keyFeatures']) ? 'Yes' : 'No') . "\n";
            if (isset($propertyData['keyFeatures'])) {
                print_r($propertyData['keyFeatures']);
            }
            
            echo "Description keys: \n";
            echo "text.description: " . (isset($propertyData['text']['description']) ? 'Yes' : 'No') . "\n";
            echo "description: " . (isset($propertyData['description']) ? 'Yes' : 'No') . "\n";
            echo "propertyDescription: " . (isset($propertyData['propertyDescription']) ? 'Yes' : 'No') . "\n";
            
            // Dump available keys to see where description might be
            echo "Available top-level keys in propertyData:\n";
            print_r(array_keys($propertyData));

        } else {
            echo "propertyData NOT found in JSON.\n";
            echo "JSON Keys: " . implode(', ', array_keys($jsonData)) . "\n";
             if (isset($jsonData['props'])) {
                  echo "props keys: " . implode(', ', array_keys($jsonData['props'])) . "\n";
                  if (isset($jsonData['props']['pageProps'])) {
                       echo "pageProps keys: " . implode(', ', array_keys($jsonData['props']['pageProps'])) . "\n";
                  }
             }
        }
    } else {
        echo "NO JSON DATA FOUND in HTML.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
