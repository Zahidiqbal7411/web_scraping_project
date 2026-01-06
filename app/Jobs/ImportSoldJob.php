<?php

namespace App\Jobs;

use App\Models\Property;
use App\Models\PropertySold;
use App\Models\PropertySoldPrice;
use App\Services\InternalPropertyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ImportSoldJob - Imports sold property history for a single property
 * 
 * This job scrapes sold property data from the property's sold_link
 * and saves it to the properties_sold and properties_sold_prices tables.
 */
class ImportSoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes per property

    /**
     * The property ID to import sold data for
     */
    protected int $propertyId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $propertyId)
    {
        $this->propertyId = $propertyId;
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(InternalPropertyService $propertyService): void
    {
        Log::info("ImportSoldJob: Starting for property {$this->propertyId}");

        $property = Property::find($this->propertyId);
        
        if (!$property) {
            Log::warning("ImportSoldJob: Property {$this->propertyId} not found");
            return;
        }

        // Check if sold_link exists, if not try to derive from location
        if (empty($property->sold_link)) {
            $soldLink = $this->deriveSoldLinkFromLocation($property->location);
            
            if (!$soldLink) {
                Log::warning("ImportSoldJob: Could not derive sold_link for property {$this->propertyId}");
                return;
            }
            
            // Save the derived link
            $property->sold_link = $soldLink;
            $property->save();
            Log::info("ImportSoldJob: Derived sold_link for property {$this->propertyId}: {$soldLink}");
        }

        try {
            // Scrape sold properties from the sold link
            $soldData = $propertyService->scrapeSoldProperties($property->sold_link, $property->id);

            if (empty($soldData)) {
                Log::info("ImportSoldJob: No sold data found for property {$this->propertyId}");
                return;
            }

            Log::info("ImportSoldJob: Found " . count($soldData) . " sold properties for property {$this->propertyId}");

            $soldCount = 0;
            $priceCount = 0;

            foreach ($soldData as $soldProp) {
                try {
                    if (empty($soldProp['location'])) {
                        continue;
                    }

                    // Save to properties_sold
                    $soldRecord = PropertySold::updateOrCreate(
                        [
                            'property_id' => $property->id,
                            'location' => $soldProp['location'] ?? ''
                        ],
                        [
                            'source_sold_link' => $property->sold_link,
                            'house_number' => $soldProp['house_number'] ?? '',
                            'road_name' => $soldProp['road_name'] ?? '',
                            'image_url' => $soldProp['image_url'] ?? null,
                            'map_url' => $soldProp['map_url'] ?? null,
                            'property_type' => $soldProp['property_type'] ?? '',
                            'bedrooms' => $soldProp['bedrooms'] ?? null,
                            'bathrooms' => $soldProp['bathrooms'] ?? null,
                            'tenure' => $soldProp['tenure'] ?? '',
                            'detail_url' => $soldProp['detail_url'] ?? null,
                        ]
                    );

                    $soldCount++;

                    // Save price history
                    if (!empty($soldProp['transactions'])) {
                        foreach ($soldProp['transactions'] as $transaction) {
                            PropertySoldPrice::updateOrCreate(
                                [
                                    'sold_property_id' => $soldRecord->id,
                                    'sold_date' => $transaction['date'] ?? null
                                ],
                                [
                                    'sold_price' => $transaction['price'] ?? null
                                ]
                            );
                            $priceCount++;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning("ImportSoldJob: Error saving sold record: " . $e->getMessage());
                }
            }

            Log::info("ImportSoldJob: Completed for property {$this->propertyId}. Saved {$soldCount} sold properties with {$priceCount} price records.");

        } catch (\Exception $e) {
            Log::error("ImportSoldJob: Failed for property {$this->propertyId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Derive sold link from property location (postcode extraction)
     */
    private function deriveSoldLinkFromLocation(?string $location): ?string
    {
        if (empty($location)) {
            return null;
        }

        // Extract postcode from location (e.g. "Bath, BA1 1AA" or "BA1 1AA")
        if (preg_match('/([A-Z]{1,2}[0-9][0-9A-Z]?\s*[0-9][A-Z]{2})/i', $location, $matches)) {
            $postcode = strtoupper(str_replace(' ', '', $matches[1])); // Normalize to BA11AA
            
            if (strlen($postcode) >= 5) {
                $outcode = substr($postcode, 0, strlen($postcode) - 3);
                $incode = substr($postcode, -3);
                return "https://www.rightmove.co.uk/house-prices/{$outcode}-{$incode}.html";
            }
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ImportSoldJob failed permanently for property {$this->propertyId}: " . $exception->getMessage());
    }
}
