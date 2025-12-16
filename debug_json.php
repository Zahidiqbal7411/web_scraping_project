<?php

use App\Models\Url;
use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get a URL from DB
$urlModel = Url::first();

if (!$urlModel) {
    die("No URLs found in database. Please import some first.");
}

$url = $urlModel->url;
echo "Fetching: $url\n";

$response = Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
])->get($url);

if ($response->successful()) {
    $html = $response->body();
    
    // Pattern Match
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        echo "Found window.PAGE_MODEL\n";
        $json = $matches[1];
        file_put_contents('rightmove_data.json', $json);
        echo "Saved to rightmove_data.json\n";
    } elseif (preg_match('/script#__NEXT_DATA__.*?>(.*?)<\/script>/s', $html, $matches) || preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
        echo "Found __NEXT_DATA__\n";
         $json = $matches[1];
        file_put_contents('rightmove_data.json', $json);
        echo "Saved to rightmove_data.json\n";
    } else {
        echo "No JSON found. Dumped HTML to debug.html\n";
        file_put_contents('debug.html', $html);
    }
} else {
    echo "Failed to fetch URL: " . $response->status();
}
