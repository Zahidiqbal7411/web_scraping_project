<?php
// minimal_debug.php
require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

$url = 'https://www.rightmove.co.uk/properties/135107819#/?channel=RES_BUY';
$client = new Client(['verify' => false, 'headers' => ['User-Agent' => 'Mozilla/5.0']]);

try {
    $html = $client->get($url)->getBody()->getContents();
    
    if (preg_match('/window\.PAGE_MODEL\s*=\s*({.*?});?\s*$/m', $html, $matches)) {
        $data = json_decode($matches[1], true);
    } elseif (preg_match('/window\.__NEXT_DATA__\s*=\s*({.*?});/s', $html, $matches)) {
        $data = json_decode($matches[1], true);
    } else {
        $crawler = new Crawler($html);
        $data = json_decode($crawler->filter('script#__NEXT_DATA__')->html(), true);
    }

    $props = $data['propertyData'] ?? $data['props']['pageProps']['propertyData'] ?? [];
    
    // Check known potential keys specifically
    $soldUrl = $props['nearbySoldPropertiesUrl'] ?? 'NOT_FOUND';
    echo "nearbySoldPropertiesUrl: " . $soldUrl . "\n";
    
    $history = $props['soldPropertyHistory'] ?? 'NOT_FOUND';
    if (is_array($history)) { echo "soldPropertyHistory: FOUND (Array)\n"; }
    else { echo "soldPropertyHistory: " . $history . "\n"; }

    // Search keys for anything containing 'sold'
    echo "\nKeys containing 'sold':\n";
    foreach (array_keys($props) as $key) {
        if (stripos($key, 'sold') !== false) {
            echo "- $key\n";
        }
    }

} catch (Exception $e) { echo $e->getMessage(); }
