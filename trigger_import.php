$url = 'https://www.rightmove.co.uk/property-for-sale/find.html?searchLocation=Manchester%2C+Greater+Manchester&useLocationIdentifier=true&locationIdentifier=REGION%5E904&buy=For+sale&radius=0.0&_includeSSTC=on';
$session = \App\Models\ImportSession::create([
    'saved_search_id' => 1,
    'base_url' => $url,
    'status' => 'pending'
]);
\App\Jobs\MasterImportJob::dispatch($session, $url, 1)->onQueue('imports');
echo "Dispatched session: " . $session->id . PHP_EOL;
