<?php

function callRightmoveTypeahead($searchTerm)
{
    $url = 'https://www.rightmove.co.uk/typeAhead/uknolocaliseh/?' . http_build_query([
        'searchType' => 'SALE',
        'query' => $searchTerm
    ]);

    echo "Fetching: $url\n";

    // Method 1: Try cURL with SSL certificate
    if (function_exists('curl_init')) {
        try {
            $ch = curl_init();
            $curlOpts = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ];
            
            curl_setopt_array($ch, $curlOpts);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    return $data;
                }
            } else {
                echo "cURL Failed. HTTP: $httpCode, Error: $curlError\n";
            }
        } catch (\Exception $e) {
            echo "cURL Exception: " . $e->getMessage() . "\n";
        }
    }

    return null;
}

$searchTerm = "Cheshire";
$result = callRightmoveTypeahead($searchTerm);

if ($result) {
    echo "API Success!\n";
    print_r($result);
} else {
    echo "API Failed.\n";
}
