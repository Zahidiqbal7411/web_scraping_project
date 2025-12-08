<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;

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
            'updates_url' => $url
        ];

        // Map URL parameters to database fields
        if (isset($queryParams['searchLocation'])) {
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
        
        if (isset($queryParams['searchLocation'])) {
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
}
