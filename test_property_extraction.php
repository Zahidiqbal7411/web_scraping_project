<?php
/**
 * Test Script: Verify Property Data Extraction and Sold Data Scraping
 * 
 * This script tests the fixed extraction logic by:
 * 1. Testing property details extraction
 * 2. Testing sold property scraping
 * 3. Verifying database insertion
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\InternalPropertyService;
use App\Models\Property;
use App\Models\PropertySold;
use App\Models\PropertySoldPrice;
use Illuminate\Support\Facades\Log;

echo "=== Property Data Extraction Test ===" . PHP_EOL . PHP_EOL;

// Test URL - Replace with an actual Rightmove property URL
$testPropertyUrl = 'https://www.rightmove.co.uk/properties/165671282#/?channel=RES_BUY';

echo "Testing with URL: {$testPropertyUrl}" . PHP_EOL . PHP_EOL;

try {
    $service = new InternalPropertyService();
    
    // Step 1: Fetch property data
    echo "[1] Fetching property data..." . PHP_EOL;
    $propertyData = $service->fetchPropertyData($testPropertyUrl);
    
    if (!$propertyData['success']) {
        echo "   ❌ Failed to fetch property data: " . ($propertyData['error'] ?? 'Unknown error') . PHP_EOL;
        exit(1);
    }
    
    echo "   ✓ Property data fetched successfully" . PHP_EOL . PHP_EOL;
    
    // Display extracted data
    echo "[2] Extracted Property Details:" . PHP_EOL;
    echo "   - Title: " . ($propertyData['title'] ?? 'N/A') . PHP_EOL;
    echo "   - Price: " . ($propertyData['price'] ?? 'N/A') . PHP_EOL;
    echo "   - Address: " . ($propertyData['address'] ?? 'N/A') . PHP_EOL;
    echo "   - Property Type: " . ($propertyData['property_type'] ?? 'N/A') . PHP_EOL;
    echo "   - Bedrooms: " . ($propertyData['bedrooms'] ?? 'N/A') . PHP_EOL;
    echo "   - Bathrooms: " . ($propertyData['bathrooms'] ?? 'N/A') . PHP_EOL;
    echo "   - Size: " . ($propertyData['size'] ?? 'N/A') . PHP_EOL;
    echo "   - Tenure: " . ($propertyData['tenure'] ?? 'N/A') . PHP_EOL;
    echo "   - Council Tax: " . ($propertyData['council_tax'] ?? 'N/A') . PHP_EOL;
    echo "   - Parking: " . ($propertyData['parking'] ?? 'N/A') . PHP_EOL;
    echo "   - Garden: " . ($propertyData['garden'] ?? 'N/A') . PHP_EOL;
    echo "   - Accessibility: " . ($propertyData['accessibility'] ?? 'N/A') . PHP_EOL;
    echo "   - Ground Rent: " . ($propertyData['ground_rent'] ?? 'N/A') . PHP_EOL;
    echo "   - Service Charge: " . ($propertyData['annual_service_charge'] ?? 'N/A') . PHP_EOL;
    echo "   - Lease Length: " . ($propertyData['lease_length'] ?? 'N/A') . PHP_EOL;
    echo "   - Key Features: " . count($propertyData['key_features'] ?? []) . " items" . PHP_EOL;
    echo "   - Description: " . (empty($propertyData['description']) ? 'EMPTY ❌' : 'Present ✓') . PHP_EOL;
    echo "   - Sold Link: " . ($propertyData['sold_link'] ?? 'N/A') . PHP_EOL;
    echo "   - Images: " . count($propertyData['images'] ?? []) . " images" . PHP_EOL;
    echo PHP_EOL;
    
    // Step 3: Test sold property scraping if sold link exists
    if (!empty($propertyData['sold_link'])) {
        echo "[3] Testing Sold Property Scraping..." . PHP_EOL;
        echo "   Sold Link: " . $propertyData['sold_link'] . PHP_EOL;
        
        $soldData = $service->scrapeSoldProperties($propertyData['sold_link'], 'test');
        
        if (empty($soldData)) {
            echo "   ⚠️  No sold property data found" . PHP_EOL;
        } else {
            echo "   ✓ Found " . count($soldData) . " sold properties" . PHP_EOL . PHP_EOL;
            
            foreach ($soldData as $index => $soldProp) {
                echo "   Sold Property #" . ($index + 1) . ":" . PHP_EOL;
                echo "      - Property ID: " . ($soldProp['property_id'] ?? 'N/A') . PHP_EOL;
                echo "      - Location: " . ($soldProp['location'] ?? 'N/A') . PHP_EOL;
                echo "      - Type: " . ($soldProp['property_type'] ?? 'N/A') . PHP_EOL;
                echo "      - Bedrooms: " . ($soldProp['bedrooms'] ?? 'N/A') . PHP_EOL;
                echo "      - Bathrooms: " . ($soldProp['bathrooms'] ?? 'N/A') . PHP_EOL;
                echo "      - Tenure: " . ($soldProp['tenure'] ?? 'N/A') . PHP_EOL;
                
                if (!empty($soldProp['transactions'])) {
                    echo "      - Transactions: " . count($soldProp['transactions']) . " records" . PHP_EOL;
                    foreach ($soldProp['transactions'] as $transIndex => $trans) {
                        echo "         " . ($transIndex + 1) . ". " . ($trans['price'] ?? 'N/A') . " on " . ($trans['date'] ?? 'N/A') . PHP_EOL;
                    }
                } else {
                    echo "      - Transactions: None ❌" . PHP_EOL;
                }
                echo PHP_EOL;
            }
        }
    } else {
        echo "[3] No sold link found - skipping sold property test" . PHP_EOL . PHP_EOL;
    }
    
    // Step 4: Check database counts
    echo "[4] Database Check:" . PHP_EOL;
    $propertyCount = Property::count();
    $soldCount = PropertySold::count();
    $priceCount = PropertySoldPrice::count();
    
    echo "   - Properties table: {$propertyCount} records" . PHP_EOL;
    echo "   - Properties Sold table: {$soldCount} records" . PHP_EOL;
    echo "   - Properties Sold Prices table: {$priceCount} records" . PHP_EOL;
    echo PHP_EOL;
    
    // Summary
    echo "=== Test Summary ===" . PHP_EOL;
    $issues = [];
    
    if (empty($propertyData['description'])) {
        $issues[] = "Description is empty";
    }
    if (empty($propertyData['key_features'])) {
        $issues[] = "Key features is empty";
    }
    if (empty($propertyData['sold_link'])) {
        $issues[] = "Sold link not found";
    }
    if ($soldCount == 0) {
        $issues[] = "No sold properties in database";
    }
    if ($priceCount == 0) {
        $issues[] = "No sold price records in database";
    }
    
    if (empty($issues)) {
        echo "✅ All tests passed!" . PHP_EOL;
    } else {
        echo "⚠️  Issues found:" . PHP_EOL;
        foreach ($issues as $issue) {
            echo "   - {$issue}" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Test failed with exception: " . $e->getMessage() . PHP_EOL;
    echo "   Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "Test completed." . PHP_EOL;
