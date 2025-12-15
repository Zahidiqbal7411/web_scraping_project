<?php

function callRightmoveTypeahead($searchTerm)
{
    $url = 'https://www.rightmove.co.uk/typeAhead/uknolocaliseh/?' . http_build_query([
        'searchType' => 'SALE',
        'query' => $searchTerm
    ]);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                        "Accept: application/json\r\n"
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);

    try {
        $response = file_get_contents($url, false, $context);
        if ($response !== false) {
            return json_decode($response, true);
        }
    } catch (Exception $e) {
        return "Error: " . $e->getMessage();
    }
    return null;
}

echo "Searching for 'Eastbourne'...\n";
$eastbourne = callRightmoveTypeahead('Eastbourne');
print_r($eastbourne);

echo "\nSearching for 'Evesham'...\n";
$evesham = callRightmoveTypeahead('Evesham');
print_r($evesham);

// Also check what REGION^493 might be if possible, though the API is name-based.
// We can't query by ID easily, but the name search is sufficient.
