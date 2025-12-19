<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use App\Models\Url;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SavedSearchController extends Controller
{
    public function showPage()
    {
        return view('searchproperties.index');
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'searches' => SavedSearch::latest()->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'updates_url' => 'required|url',
        ]);

        $url = $validated['updates_url'];
        
        // Parse the URL parameters
        $parsedUrl = parse_url($url);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        // Initialize data array with the URL
        $data = [
            'updates_url' => $url,
            'area' => $request->input('area') // Get area directly from request
        ];

        // Map URL parameters to database fields
        if (isset($queryParams['searchLocation']) && empty($data['area'])) {
            $data['area'] = $queryParams['searchLocation'];
        }
        
        if (isset($queryParams['minPrice'])) {
            $data['min_price'] = $queryParams['minPrice'];
        }
        
        if (isset($queryParams['maxPrice'])) {
            $data['max_price'] = $queryParams['maxPrice'];
        }
        
        if (isset($queryParams['minBedrooms'])) {
            $data['min_bed'] = $queryParams['minBedrooms'];
        }
        
        if (isset($queryParams['maxBedrooms'])) {
            $data['max_bed'] = $queryParams['maxBedrooms'];
        }

        // Check for bathrooms (though not always in URL)
        if (isset($queryParams['minBathrooms'])) {
            $data['min_bath'] = $queryParams['minBathrooms'];
        }
        
        if (isset($queryParams['maxBathrooms'])) {
            $data['max_bath'] = $queryParams['maxBathrooms'];
        }

        if (isset($queryParams['propertyTypes'])) {
            $data['property_type'] = $queryParams['propertyTypes'];
        }

        // Parse new filters
        if (isset($queryParams['tenureTypes'])) {
            $data['tenure_types'] = $queryParams['tenureTypes'];
        }

        if (isset($queryParams['mustHave'])) {
            $data['must_have'] = $queryParams['mustHave'];
        }

        if (isset($queryParams['dontShow'])) {
            $data['dont_show'] = $queryParams['dontShow'];
        }

        $search = SavedSearch::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Search saved successfully',
            'search' => $search
        ]);
    }

    public function destroy($id)
    {
        $search = SavedSearch::find($id);
        if ($search) {
            $search->delete();
            // Delete associated URLs
            Url::where('filter_id', $id)->delete();
            return response()->json(['success' => true, 'message' => 'Search deleted']);
        }
        return response()->json(['success' => false, 'message' => 'Search not found'], 404);
    }

    public function update(Request $request, $id)
    {
        $search = SavedSearch::find($id);
        if (!$search) {
            return response()->json(['success' => false, 'message' => 'Search not found'], 404);
        }

        $validated = $request->validate([
            'updates_url' => 'required|url',
        ]);

        $url = $validated['updates_url'];
        
        // Parse the URL parameters
        $parsedUrl = parse_url($url);
        $queryParams = [];
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }

        // Update data
        $search->updates_url = $url;
        
        // Delete old URLs associated with this search since the URL/criteria changed
        Url::where('filter_id', $id)->delete();
        if ($request->has('area')) {
            $search->area = $request->input('area');
        }
        
        if (isset($queryParams['searchLocation']) && !$request->has('area')) {
            $search->area = $queryParams['searchLocation'];
        }
        
        if (isset($queryParams['minPrice'])) {
            $search->min_price = $queryParams['minPrice'];
        }
        
        if (isset($queryParams['maxPrice'])) {
            $search->max_price = $queryParams['maxPrice'];
        }
        
        if (isset($queryParams['minBedrooms'])) {
            $search->min_bed = $queryParams['minBedrooms'];
        }
        
        if (isset($queryParams['maxBedrooms'])) {
            $search->max_bed = $queryParams['maxBedrooms'];
        }

        if (isset($queryParams['propertyTypes'])) {
            $search->property_type = $queryParams['propertyTypes'];
        }

        // Parse new filters
        if (isset($queryParams['tenureTypes'])) {
            $search->tenure_types = $queryParams['tenureTypes'];
        }

        if (isset($queryParams['mustHave'])) {
            $search->must_have = $queryParams['mustHave'];
        }

        if (isset($queryParams['dontShow'])) {
            $search->dont_show = $queryParams['dontShow'];
        }

        $search->save();

        return response()->json([
            'success' => true,
            'message' => 'Search updated successfully',
            'search' => $search
        ]);
    }

    /**
     * Get all UK areas - Pre-defined list for instant loading
     * Data sourced from Rightmove major-cities page
     */
    public function getAreas()
    {
        // Pre-defined areas with their Rightmove REGION identifiers (sorted A-Z)
        $areas = [
            ['name' => 'Aberdeen', 'identifier' => 'REGION^18'],
            ['name' => 'Basingstoke', 'identifier' => 'REGION^113'],
            ['name' => 'Bath', 'identifier' => 'REGION^116'],
            ['name' => 'Bedford', 'identifier' => 'REGION^131'],
            ['name' => 'Belfast', 'identifier' => 'REGION^143'],
            ['name' => 'Birmingham', 'identifier' => 'REGION^162'],
            ['name' => 'Blackpool', 'identifier' => 'REGION^171'],
            ['name' => 'Bolton', 'identifier' => 'REGION^179'],
            ['name' => 'Bournemouth', 'identifier' => 'REGION^185'],
            ['name' => 'Bradford', 'identifier' => 'REGION^194'],
            ['name' => 'Brentwood', 'identifier' => 'REGION^202'],
            ['name' => 'Brighton', 'identifier' => 'REGION^204'],
            ['name' => 'Bristol', 'identifier' => 'REGION^219'],
            ['name' => 'Bury St. Edmunds', 'identifier' => 'REGION^247'],
            ['name' => 'Cambridge', 'identifier' => 'REGION^265'],
            ['name' => 'Canterbury', 'identifier' => 'REGION^274'],
            ['name' => 'Cardiff', 'identifier' => 'REGION^277'],
            ['name' => 'Carlisle', 'identifier' => 'REGION^288'],
            ['name' => 'Chelmsford', 'identifier' => 'REGION^347'],
            ['name' => 'Cheltenham', 'identifier' => 'REGION^353'],
            ['name' => 'Chester', 'identifier' => 'REGION^351'],
            ['name' => 'Coventry', 'identifier' => 'REGION^430'],
            ['name' => 'Derby', 'identifier' => 'REGION^453'],
            ['name' => 'Derry', 'identifier' => 'REGION^457'],
            ['name' => 'Doncaster', 'identifier' => 'REGION^474'],
            ['name' => 'Dundee', 'identifier' => 'REGION^548'],
            ['name' => 'Durham', 'identifier' => 'REGION^2828'],
            ['name' => 'Eastbourne', 'identifier' => 'REGION^493'],
            ['name' => 'Edinburgh', 'identifier' => 'REGION^550'],
            ['name' => 'Exeter', 'identifier' => 'REGION^517'],
            ['name' => 'Glasgow', 'identifier' => 'REGION^664'],
            ['name' => 'Gloucester', 'identifier' => 'REGION^665'],
            ['name' => 'Guildford', 'identifier' => 'REGION^700'],
            ['name' => 'Harrogate', 'identifier' => 'REGION^722'],
            ['name' => 'Huddersfield', 'identifier' => 'REGION^750'],
            ['name' => 'Hull', 'identifier' => 'REGION^755'],
            ['name' => 'Ipswich', 'identifier' => 'REGION^768'],
            ['name' => 'Kent', 'identifier' => 'REGION^27738'],
            ['name' => 'Lancaster', 'identifier' => 'REGION^792'],
            ['name' => 'Leamington Spa', 'identifier' => 'REGION^799'],
            ['name' => 'Leeds', 'identifier' => 'REGION^802'],
            ['name' => 'Leicester', 'identifier' => 'REGION^806'],
            ['name' => 'Lincoln', 'identifier' => 'REGION^824'],
            ['name' => 'Liverpool', 'identifier' => 'REGION^835'],
            ['name' => 'London', 'identifier' => 'REGION^93965'],
            ['name' => 'Luton', 'identifier' => 'REGION^847'],
            ['name' => 'Maidstone', 'identifier' => 'REGION^867'],
            ['name' => 'Manchester', 'identifier' => 'REGION^886'],
            ['name' => 'Milton Keynes', 'identifier' => 'REGION^928'],
            ['name' => 'Newcastle Upon Tyne', 'identifier' => 'REGION^910'],
            ['name' => 'Newport', 'identifier' => 'REGION^962'],
            ['name' => 'Northampton', 'identifier' => 'REGION^979'],
            ['name' => 'Norwich', 'identifier' => 'REGION^983'],
            ['name' => 'Nottingham', 'identifier' => 'REGION^981'],
            ['name' => 'Oxford', 'identifier' => 'REGION^1051'],
            ['name' => 'Peterborough', 'identifier' => 'REGION^1080'],
            ['name' => 'Plymouth', 'identifier' => 'REGION^1092'],
            ['name' => 'Poole', 'identifier' => 'REGION^1099'],
            ['name' => 'Portsmouth', 'identifier' => 'REGION^1108'],
            ['name' => 'Preston', 'identifier' => 'REGION^1113'],
            ['name' => 'Reading', 'identifier' => 'REGION^1140'],
            ['name' => 'Sheffield', 'identifier' => 'REGION^1190'],
            ['name' => 'Slough', 'identifier' => 'REGION^1208'],
            ['name' => 'Solihull', 'identifier' => 'REGION^1218'],
            ['name' => 'Southampton', 'identifier' => 'REGION^1234'],
            ['name' => 'Southend-On-Sea', 'identifier' => 'REGION^1236'],
            ['name' => 'St. Albans', 'identifier' => 'REGION^1245'],
            ['name' => 'Stoke-On-Trent', 'identifier' => 'REGION^1255'],
            ['name' => 'Sunderland', 'identifier' => 'REGION^1260'],
            ['name' => 'Swansea', 'identifier' => 'REGION^1268'],
            ['name' => 'Swindon', 'identifier' => 'REGION^1261'],
            ['name' => 'Taunton', 'identifier' => 'REGION^1284'],
            ['name' => 'Tunbridge Wells', 'identifier' => 'REGION^1332'],
            ['name' => 'Warrington', 'identifier' => 'REGION^1362'],
            ['name' => 'Watford', 'identifier' => 'REGION^1369'],
            ['name' => 'Winchester', 'identifier' => 'REGION^1389'],
            ['name' => 'Wolverhampton', 'identifier' => 'REGION^1396'],
            ['name' => 'Worcester', 'identifier' => 'REGION^1398'],
            ['name' => 'Worthing', 'identifier' => 'REGION^1408'],
            ['name' => 'York', 'identifier' => 'REGION^1425'],
            // Counties & Regions
            ['name' => 'Cornwall', 'identifier' => 'REGION^24568'],
            ['name' => 'Devon', 'identifier' => 'REGION^27195'],
            ['name' => 'Dorset', 'identifier' => 'REGION^27147'],
            ['name' => 'Essex', 'identifier' => 'REGION^27180'],
            ['name' => 'Hampshire', 'identifier' => 'REGION^27245'],
            ['name' => 'Hertfordshire', 'identifier' => 'REGION^27278'],
            ['name' => 'Lancashire', 'identifier' => 'REGION^27303'],
            ['name' => 'Norfolk', 'identifier' => 'REGION^27348'],
            ['name' => 'Oxfordshire', 'identifier' => 'REGION^27381'],
            ['name' => 'Somerset', 'identifier' => 'REGION^27414'],
            ['name' => 'Suffolk', 'identifier' => 'REGION^27462'],
            ['name' => 'Surrey', 'identifier' => 'REGION^27480'],
            ['name' => 'Sussex', 'identifier' => 'REGION^27669'],
            ['name' => 'Wiltshire', 'identifier' => 'REGION^27552'],
            ['name' => 'Yorkshire', 'identifier' => 'REGION^27585'],
        ];

        // Sort alphabetically (already sorted but ensuring)
        usort($areas, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'areas' => $areas
        ]);
    }

    /**
     * Check if an area exists on Rightmove using their typeahead API
     */
    public function checkArea(Request $request)
    {
        $request->validate([
            'area' => 'required|string|min:2'
        ]);

        $searchTerm = trim($request->input('area'));

        // Check if user entered a locationIdentifier directly (e.g., REGION^93965 or OUTCODE^1234)
        if (preg_match('/^(REGION|OUTCODE|STATION|POSTCODE)\^[\d]+$/i', $searchTerm)) {
            return response()->json([
                'success' => true,
                'found' => true,
                'identifier' => strtoupper($searchTerm),
                'name' => $searchTerm,
                'type' => 'DIRECT'
            ]);
        }

        // Strategy 1: Try Rightmove API FIRST for specific/compound locations
        // This ensures "Weston, Bath, Somerset" gets the correct specific identifier
        // AND ensures we get the correct ID for regions like "Cheshire" if our hardcoded list is wrong
        $apiResult = $this->callRightmoveTypeahead($searchTerm);
        
        if ($apiResult !== null && !empty($apiResult['typeAheadLocations'])) {
            $firstMatch = $apiResult['typeAheadLocations'][0];
            return response()->json([
                'success' => true,
                'found' => true,
                'identifier' => $firstMatch['locationIdentifier'],
                'name' => $firstMatch['displayName'],
                'type' => $firstMatch['locationType'] ?? 'UNKNOWN'
            ]);
        }

        $predefinedAreas = $this->getPredefinedAreas();
        
        // Strategy 2: Exact match only in predefined areas (Fallback if API fails)
        foreach ($predefinedAreas as $area) {
            if (strcasecmp($area['name'], $searchTerm) === 0) {
                Log::info("Area matched via Strategy 2 (Exact Match): {$searchTerm} -> {$area['name']}");
                return response()->json([
                    'success' => true,
                    'found' => true,
                    'identifier' => $area['identifier'],
                    'name' => $area['name'],
                    'type' => 'REGION'
                ]);
            }
        }
        
            if (strpos($searchTerm, ',') !== false) {
            $firstPart = trim(explode(',', $searchTerm)[0]);
            foreach ($predefinedAreas as $area) {
                if (strcasecmp($area['name'], $firstPart) === 0) {
                    Log::info("Area matched via Strategy 3 (First Part Match): {$searchTerm} -> {$area['name']}");
                    return response()->json([
                        'success' => true,
                        'found' => true,
                        'identifier' => $area['identifier'],
                        'name' => $area['name'],
                        'type' => 'REGION'
                    ]);
                }
            }
        }
        
        // Strategy 4: Check if input starts with any predefined area name
        $searchLower = strtolower($searchTerm);
        foreach ($predefinedAreas as $area) {
            $areaLower = strtolower($area['name']);
            if (strpos($searchLower, $areaLower) === 0 || strpos($areaLower, $searchLower) === 0) {
                Log::info("Area matched via Strategy 4 (Prefix/Start Match): {$searchTerm} -> {$area['name']}");
                return response()->json([
                    'success' => true,
                    'found' => true,
                    'identifier' => $area['identifier'],
                    'name' => $area['name'],
                    'type' => 'REGION'
                ]);
            }
        }
        
        // Strategy 5: Partial match in predefined areas - REMOVED due to incorrect matches
        // (e.g., searching "field" matched "Chesterfield")
        
        // Strategy 6: Check if search term contains any predefined area name (fallback when API fails)
        // e.g., "Weston, Bath, Somerset" contains "Bath"
        foreach ($predefinedAreas as $area) {
            if (stripos($searchTerm, $area['name']) !== false) {
                Log::info("Area matched via Strategy 6 (Contains Area Name): {$searchTerm} -> {$area['name']}");
                return response()->json([
                    'success' => true,
                    'found' => true,
                    'identifier' => $area['identifier'],
                    'name' => $area['name'],
                    'type' => 'REGION'
                ]);
            }
        }

        // Final fallback: Provide instructions
        return response()->json([
            'success' => false,
            'found' => false,
            'message' => 'Network timeout. Tip: Go to rightmove.co.uk, search your area, then copy the locationIdentifier from the URL (e.g., REGION^93965)'
        ], 500);
    }

    /**
     * Call Rightmove typeahead API with multiple fallback methods
     */
    private function callRightmoveTypeahead($searchTerm)
    {
        $url = 'https://www.rightmove.co.uk/typeAhead/uknolocaliseh/?' . http_build_query([
            'searchType' => 'SALE',
            'query' => $searchTerm
        ]);

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
                    ]
                ];
                
                // Try with CA certificate first, then fallback to no verification
                $caFile = 'C:/xampp/php/extras/ssl/cacert.pem';
                if (file_exists($caFile)) {
                    $curlOpts[CURLOPT_CAINFO] = $caFile;
                    $curlOpts[CURLOPT_SSL_VERIFYPEER] = true;
                    $curlOpts[CURLOPT_SSL_VERIFYHOST] = 2;
                } else {
                    $curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
                    $curlOpts[CURLOPT_SSL_VERIFYHOST] = false;
                }
                
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
                }
                
                // Log the error for debugging
                if ($curlError) {
                    Log::warning('cURL error: ' . $curlError);
                }
            } catch (\Exception $e) {
                Log::warning('cURL method failed: ' . $e->getMessage());
            }
        }

        // Method 2: Try file_get_contents with stream context
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                                "Accept: application/json\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $response = @file_get_contents($url, false, $context);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        } catch (\Exception $e) {
            Log::warning('file_get_contents method failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Get pre-defined areas list
     */
    private function getPredefinedAreas()
    {
        return [
            ['name' => 'Aberdeen', 'identifier' => 'REGION^18'],
            ['name' => 'Basingstoke', 'identifier' => 'REGION^113'],
            ['name' => 'Bath', 'identifier' => 'REGION^116'],
            ['name' => 'Bedford', 'identifier' => 'REGION^131'],
            ['name' => 'Belfast', 'identifier' => 'REGION^143'],
            ['name' => 'Birmingham', 'identifier' => 'REGION^162'],
            // Bath sub-areas
            ['name' => 'Weston, Bath', 'identifier' => 'REGION^26459'],
            ['name' => 'Weston', 'identifier' => 'REGION^26459'],
            ['name' => 'Bathwick', 'identifier' => 'REGION^119'],
            ['name' => 'Batheaston', 'identifier' => 'REGION^117'],
            ['name' => 'Bathampton', 'identifier' => 'REGION^118'],
            ['name' => 'Combe Down', 'identifier' => 'REGION^413'],
            ['name' => 'Larkhall', 'identifier' => 'REGION^795'],
            ['name' => 'Oldfield Park', 'identifier' => 'REGION^1039'],
            ['name' => 'Twerton', 'identifier' => 'REGION^1339'],
            ['name' => 'Charlcombe, Bath', 'identifier' => 'REGION^6033'],
            ['name' => 'Charlcombe', 'identifier' => 'REGION^6033'],
            ['name' => 'Blackpool', 'identifier' => 'REGION^171'],
            ['name' => 'Bolton', 'identifier' => 'REGION^179'],
            ['name' => 'Bournemouth', 'identifier' => 'REGION^185'],
            ['name' => 'Bradford', 'identifier' => 'REGION^194'],
            ['name' => 'Brentwood', 'identifier' => 'REGION^202'],
            ['name' => 'Brighton', 'identifier' => 'REGION^204'],
            ['name' => 'Bristol', 'identifier' => 'REGION^219'],
            ['name' => 'Bury St. Edmunds', 'identifier' => 'REGION^247'],
            ['name' => 'Cambridge', 'identifier' => 'REGION^265'],
            ['name' => 'Canterbury', 'identifier' => 'REGION^274'],
            ['name' => 'Cardiff', 'identifier' => 'REGION^277'],
            ['name' => 'Carlisle', 'identifier' => 'REGION^288'],
            ['name' => 'Chelmsford', 'identifier' => 'REGION^347'],
            ['name' => 'Cheltenham', 'identifier' => 'REGION^353'],
            ['name' => 'Chester', 'identifier' => 'REGION^351'],
            ['name' => 'Cornwall', 'identifier' => 'REGION^24568'],
            ['name' => 'Coventry', 'identifier' => 'REGION^430'],
            ['name' => 'Derby', 'identifier' => 'REGION^453'],
            ['name' => 'Derry', 'identifier' => 'REGION^457'],
            ['name' => 'Devon', 'identifier' => 'REGION^27195'],
            ['name' => 'Doncaster', 'identifier' => 'REGION^474'],
            ['name' => 'Dorset', 'identifier' => 'REGION^27147'],
            ['name' => 'Dundee', 'identifier' => 'REGION^548'],
            ['name' => 'Durham', 'identifier' => 'REGION^2828'],
            ['name' => 'Durham', 'identifier' => 'REGION^2828'],
            ['name' => 'Edinburgh', 'identifier' => 'REGION^550'],
            ['name' => 'Essex', 'identifier' => 'REGION^27180'],
            ['name' => 'Exeter', 'identifier' => 'REGION^517'],
            ['name' => 'Glasgow', 'identifier' => 'REGION^664'],
            ['name' => 'Gloucester', 'identifier' => 'REGION^665'],
            ['name' => 'Guildford', 'identifier' => 'REGION^700'],
            ['name' => 'Hampshire', 'identifier' => 'REGION^27245'],
            ['name' => 'Harrogate', 'identifier' => 'REGION^722'],
            ['name' => 'Hertfordshire', 'identifier' => 'REGION^27278'],
            ['name' => 'Huddersfield', 'identifier' => 'REGION^750'],
            ['name' => 'Hull', 'identifier' => 'REGION^755'],
            ['name' => 'Ipswich', 'identifier' => 'REGION^768'],
            ['name' => 'Kent', 'identifier' => 'REGION^27738'],
            ['name' => 'Lancashire', 'identifier' => 'REGION^27303'],
            ['name' => 'Lancaster', 'identifier' => 'REGION^792'],
            ['name' => 'Leamington Spa', 'identifier' => 'REGION^799'],
            ['name' => 'Leeds', 'identifier' => 'REGION^802'],
            ['name' => 'Leicester', 'identifier' => 'REGION^806'],
            ['name' => 'Lincoln', 'identifier' => 'REGION^824'],
            ['name' => 'Liverpool', 'identifier' => 'REGION^835'],
            ['name' => 'London', 'identifier' => 'REGION^93965'],
            ['name' => 'Luton', 'identifier' => 'REGION^847'],
            ['name' => 'Maidstone', 'identifier' => 'REGION^867'],
            ['name' => 'Manchester', 'identifier' => 'REGION^886'],
            ['name' => 'Milton Keynes', 'identifier' => 'REGION^928'],
            ['name' => 'Newcastle Upon Tyne', 'identifier' => 'REGION^910'],
            ['name' => 'Newport', 'identifier' => 'REGION^962'],
            ['name' => 'Norfolk', 'identifier' => 'REGION^27348'],
            ['name' => 'Northampton', 'identifier' => 'REGION^979'],
            ['name' => 'Norwich', 'identifier' => 'REGION^983'],
            ['name' => 'Nottingham', 'identifier' => 'REGION^981'],
            ['name' => 'Oxford', 'identifier' => 'REGION^1051'],
            ['name' => 'Oxfordshire', 'identifier' => 'REGION^27381'],
            ['name' => 'Peterborough', 'identifier' => 'REGION^1080'],
            ['name' => 'Plymouth', 'identifier' => 'REGION^1092'],
            ['name' => 'Poole', 'identifier' => 'REGION^1099'],
            ['name' => 'Portsmouth', 'identifier' => 'REGION^1108'],
            ['name' => 'Preston', 'identifier' => 'REGION^1113'],
            ['name' => 'Reading', 'identifier' => 'REGION^1140'],
            ['name' => 'Sheffield', 'identifier' => 'REGION^1190'],
            ['name' => 'Slough', 'identifier' => 'REGION^1208'],
            ['name' => 'Solihull', 'identifier' => 'REGION^1218'],
            ['name' => 'Somerset', 'identifier' => 'REGION^27414'],
            ['name' => 'Southampton', 'identifier' => 'REGION^1234'],
            ['name' => 'Southend-On-Sea', 'identifier' => 'REGION^1236'],
            ['name' => 'St. Albans', 'identifier' => 'REGION^1245'],
            ['name' => 'Stoke-On-Trent', 'identifier' => 'REGION^1255'],
            ['name' => 'Suffolk', 'identifier' => 'REGION^27462'],
            ['name' => 'Sunderland', 'identifier' => 'REGION^1260'],
            ['name' => 'Surrey', 'identifier' => 'REGION^27480'],
            ['name' => 'Sussex', 'identifier' => 'REGION^27669'],
            ['name' => 'Swansea', 'identifier' => 'REGION^1268'],
            ['name' => 'Swindon', 'identifier' => 'REGION^1261'],
            ['name' => 'Taunton', 'identifier' => 'REGION^1284'],
            ['name' => 'Tunbridge Wells', 'identifier' => 'REGION^1332'],
            ['name' => 'Warrington', 'identifier' => 'REGION^1362'],
            ['name' => 'Watford', 'identifier' => 'REGION^1369'],
            ['name' => 'Wiltshire', 'identifier' => 'REGION^27552'],
            ['name' => 'Winchester', 'identifier' => 'REGION^1389'],
            ['name' => 'Wolverhampton', 'identifier' => 'REGION^1396'],
            ['name' => 'Worcester', 'identifier' => 'REGION^1398'],
            ['name' => 'Worthing', 'identifier' => 'REGION^1408'],
            ['name' => 'York', 'identifier' => 'REGION^1425'],
            ['name' => 'Yorkshire', 'identifier' => 'REGION^27585'],
            // Greater Manchester Areas
            ['name' => 'Greater Manchester', 'identifier' => 'REGION^27214'],
            ['name' => 'Altrincham', 'identifier' => 'REGION^47'],
            ['name' => 'Ashton-under-Lyne', 'identifier' => 'REGION^70'],
            ['name' => 'Bury', 'identifier' => 'REGION^252'],
            ['name' => 'Oldham', 'identifier' => 'REGION^1036'],
            ['name' => 'Rochdale', 'identifier' => 'REGION^1153'],
            ['name' => 'Sale', 'identifier' => 'REGION^1177'],
            ['name' => 'Salford', 'identifier' => 'REGION^1179'],
            ['name' => 'Stockport', 'identifier' => 'REGION^1253'],
            ['name' => 'Tameside', 'identifier' => 'REGION^27494'],
            ['name' => 'Trafford', 'identifier' => 'REGION^27511'],
            ['name' => 'Wigan', 'identifier' => 'REGION^1387'],
            // More Counties
            ['name' => 'Bedfordshire', 'identifier' => 'REGION^27062'],
            ['name' => 'Berkshire', 'identifier' => 'REGION^27081'],
            ['name' => 'Buckinghamshire', 'identifier' => 'REGION^27102'],
            ['name' => 'Cambridgeshire', 'identifier' => 'REGION^27111'],
            ['name' => 'Cheshire', 'identifier' => 'REGION^27122'],
            ['name' => 'Cumbria', 'identifier' => 'REGION^27135'],
            ['name' => 'Derbyshire', 'identifier' => 'REGION^27141'],
            ['name' => 'East Sussex', 'identifier' => 'REGION^27171'],
            ['name' => 'Gloucestershire', 'identifier' => 'REGION^27210'],
            ['name' => 'Herefordshire', 'identifier' => 'REGION^27268'],
            ['name' => 'Leicestershire', 'identifier' => 'REGION^27319'],
            ['name' => 'Lincolnshire', 'identifier' => 'REGION^27330'],
            ['name' => 'Merseyside', 'identifier' => 'REGION^27239'],
            ['name' => 'North Yorkshire', 'identifier' => 'REGION^27365'],
            ['name' => 'Northamptonshire', 'identifier' => 'REGION^27356'],
            ['name' => 'Northumberland', 'identifier' => 'REGION^27375'],
            ['name' => 'Nottinghamshire', 'identifier' => 'REGION^27377'],
            ['name' => 'Shropshire', 'identifier' => 'REGION^27393'],
            ['name' => 'South Yorkshire', 'identifier' => 'REGION^27431'],
            ['name' => 'Staffordshire', 'identifier' => 'REGION^27441'],
            ['name' => 'Tyne and Wear', 'identifier' => 'REGION^27524'],
            ['name' => 'Warwickshire', 'identifier' => 'REGION^27534'],
            ['name' => 'West Midlands', 'identifier' => 'REGION^27543'],
            ['name' => 'West Sussex', 'identifier' => 'REGION^27546'],
            ['name' => 'West Yorkshire', 'identifier' => 'REGION^27549'],
            ['name' => 'Worcestershire', 'identifier' => 'REGION^27571'],
            ['name' => 'Worcestershire', 'identifier' => 'REGION^27571'],
            // London Boroughs & Areas
            ['name' => 'Bromley', 'identifier' => 'REGION^93977'],
            ['name' => 'Croydon', 'identifier' => 'REGION^93953'],
            ['name' => 'Ealing', 'identifier' => 'REGION^93941'],
            ['name' => 'Harrow', 'identifier' => 'REGION^93962'],
            ['name' => 'Hounslow', 'identifier' => 'REGION^93947'],
            ['name' => 'Kingston upon Thames', 'identifier' => 'REGION^93974'],
            ['name' => 'Richmond upon Thames', 'identifier' => 'REGION^93944'],
            ['name' => 'Sutton', 'identifier' => 'REGION^93971'],
            // More Towns
            ['name' => 'Aylesbury', 'identifier' => 'REGION^86'],
            ['name' => 'Barnsley', 'identifier' => 'REGION^107'],
            ['name' => 'Blackburn', 'identifier' => 'REGION^167'],
            ['name' => 'Burnley', 'identifier' => 'REGION^237'],
            ['name' => 'Chesterfield', 'identifier' => 'REGION^355'],
            ['name' => 'Colchester', 'identifier' => 'REGION^405'],
            ['name' => 'Crawley', 'identifier' => 'REGION^437'],
            ['name' => 'Darlington', 'identifier' => 'REGION^448'],
            ['name' => 'Dudley', 'identifier' => 'REGION^546'],
            ['name' => 'Gateshead', 'identifier' => 'REGION^661'],
            ['name' => 'Grimsby', 'identifier' => 'REGION^698'],
            ['name' => 'Halifax', 'identifier' => 'REGION^713'],
            ['name' => 'Hartlepool', 'identifier' => 'REGION^726'],
            ['name' => 'Hastings', 'identifier' => 'REGION^729'],
            ['name' => 'High Wycombe', 'identifier' => 'REGION^745'],
            ['name' => 'Kettering', 'identifier' => 'REGION^778'],
            ['name' => 'Mansfield', 'identifier' => 'REGION^889'],
            ['name' => 'Middlesbrough', 'identifier' => 'REGION^923'],
            ['name' => 'Rotherham', 'identifier' => 'REGION^1160'],
            ['name' => 'Scunthorpe', 'identifier' => 'REGION^1183'],
            ['name' => 'Stevenage', 'identifier' => 'REGION^1251'],
            ['name' => 'Telford', 'identifier' => 'REGION^1291'],
            ['name' => 'Wakefield', 'identifier' => 'REGION^1355'],
            ['name' => 'Walsall', 'identifier' => 'REGION^1359'],
            ['name' => 'West Bromwich', 'identifier' => 'REGION^1376'],
            ['name' => 'Woking', 'identifier' => 'REGION^1395'],
            // Scottish Regions
            ['name' => 'Ayrshire', 'identifier' => 'REGION^27042'],
            ['name' => 'East Ayrshire', 'identifier' => 'REGION^27163'],
            ['name' => 'North Ayrshire', 'identifier' => 'REGION^27359'],
            ['name' => 'South Ayrshire', 'identifier' => 'REGION^27423'],
            ['name' => 'Fife', 'identifier' => 'REGION^27203'],
            ['name' => 'Highlands', 'identifier' => 'REGION^27287'],
            ['name' => 'Inverness', 'identifier' => 'REGION^766'],
            ['name' => 'Stirling', 'identifier' => 'REGION^1252'],
            ['name' => 'Perth', 'identifier' => 'REGION^1079'],
            ['name' => 'Falkirk', 'identifier' => 'REGION^521'],
            ['name' => 'Airdrie', 'identifier' => 'REGION^35'],
            ['name' => 'Dunfermline', 'identifier' => 'REGION^549'],
            ['name' => 'Kilmarnock', 'identifier' => 'REGION^784'],
            ['name' => 'Paisley', 'identifier' => 'REGION^1053'],
            ['name' => 'East Kilbride', 'identifier' => 'REGION^498'],
            ['name' => 'Cumbernauld', 'identifier' => 'REGION^442'],
            ['name' => 'Livingston', 'identifier' => 'REGION^838'],
            ['name' => 'Hamilton', 'identifier' => 'REGION^720'],
            ['name' => 'Motherwell', 'identifier' => 'REGION^935'],
            ['name' => 'Coatbridge', 'identifier' => 'REGION^401'],
            // Wales Regions
            ['name' => 'Anglesey', 'identifier' => 'REGION^27038'],
            ['name' => 'Bridgend', 'identifier' => 'REGION^27099'],
            ['name' => 'Caerphilly', 'identifier' => 'REGION^27108'],
            ['name' => 'Carmarthenshire', 'identifier' => 'REGION^27118'],
            ['name' => 'Ceredigion', 'identifier' => 'REGION^27120'],
            ['name' => 'Conwy', 'identifier' => 'REGION^27130'],
            ['name' => 'Denbighshire', 'identifier' => 'REGION^27138'],
            ['name' => 'Flintshire', 'identifier' => 'REGION^27206'],
            ['name' => 'Gwynedd', 'identifier' => 'REGION^27242'],
            ['name' => 'Monmouthshire', 'identifier' => 'REGION^27343'],
            ['name' => 'Neath Port Talbot', 'identifier' => 'REGION^27344'],
            ['name' => 'Pembrokeshire', 'identifier' => 'REGION^27385'],
            ['name' => 'Powys', 'identifier' => 'REGION^27388'],
            ['name' => 'Rhondda Cynon Taf', 'identifier' => 'REGION^27390'],
            ['name' => 'Torfaen', 'identifier' => 'REGION^27507'],
            ['name' => 'Vale of Glamorgan', 'identifier' => 'REGION^27529'],
            ['name' => 'Wrexham', 'identifier' => 'REGION^27574'],
            // Northern Ireland
            ['name' => 'Antrim', 'identifier' => 'REGION^27039'],
            ['name' => 'Armagh', 'identifier' => 'REGION^27043'],
            ['name' => 'Down', 'identifier' => 'REGION^27155'],
            ['name' => 'Fermanagh', 'identifier' => 'REGION^27200'],
            ['name' => 'Londonderry', 'identifier' => 'REGION^27332'],
            ['name' => 'Tyrone', 'identifier' => 'REGION^27527'],
            // More English Regions
            ['name' => 'East Anglia', 'identifier' => 'REGION^27165'],
            ['name' => 'East Midlands', 'identifier' => 'REGION^27167'],
            ['name' => 'West Midlands', 'identifier' => 'REGION^27543'],
            ['name' => 'South East England', 'identifier' => 'REGION^27418'],
            ['name' => 'South West England', 'identifier' => 'REGION^27427'],
            ['name' => 'North East England', 'identifier' => 'REGION^27352'],
            ['name' => 'North West England', 'identifier' => 'REGION^27364'],
        ];
    }
}
