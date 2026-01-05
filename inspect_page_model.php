<?php
$url = 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Manchester&locationIdentifier=REGION%5E904';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
$h = curl_exec($ch);
curl_close($ch);

$pos = strpos($h, 'window.PAGE_MODEL');
if ($pos !== false) {
    echo substr($h, $pos, 2000);
} else {
    echo "PAGE_MODEL not found. HTML length: " . strlen($h);
}
