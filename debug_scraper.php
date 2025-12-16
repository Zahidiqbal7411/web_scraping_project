<?php

use App\Services\RightmoveScraperService;
use App\Models\SavedSearch;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing Direct HTTP Request to: " . $url . "\n";

try {
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language' => 'en-US,en;q=0.9',
        'Cache-Control' => 'no-cache',
        'Pragma' => 'no-cache',
        'Sec-Ch-Ua' => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
        'Sec-Ch-Ua-Mobile' => '?0',
        'Sec-Ch-Ua-Platform' => '"Windows"',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
        'Upgrade-Insecure-Requests' => '1',
    ])->get($url);

    echo "Status: " . $response->status() . "\n";
    if ($response->successful()) {
        echo "Success! Content Length: " . strlen($response->body()) . "\n";
        // Check for PAGE_MODEL
        if (preg_match('/window\.PAGE_MODEL\s*=\s*(\{.*?\});/s', $response->body())) {
            echo "PAGE_MODEL found.\n";
        } else {
            echo "PAGE_MODEL NOT found.\n";
            // Output first 500 chars to see what we got
            echo "Preview: " . substr($response->body(), 0, 500) . "\n";
        }
    } else {
        echo "Failed. Status: " . $response->status() . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
