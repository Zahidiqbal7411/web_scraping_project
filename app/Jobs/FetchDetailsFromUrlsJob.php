<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Models\Url;
use App\Models\Schedule;
use App\Services\InternalPropertyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FetchDetailsFromUrlsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 3600;

    protected int $importSessionId;
    protected int $savedSearchId;

    public function __construct(ImportSession $importSession, int $savedSearchId)
    {
        $this->importSessionId = $importSession->id;
        $this->savedSearchId = $savedSearchId;
        $this->onQueue('imports');
    }

    public function handle(InternalPropertyService $propertyService): void
    {
        $importSession = ImportSession::find($this->importSessionId);
        if (!$importSession) return;

        Log::info("=== FETCH DETAILS JOB: Starting for search #{$this->savedSearchId} ===");

        // Find all pending URLs for this search
        $urls = Url::where('filter_id', $this->savedSearchId)
            ->where('status', 'pending')
            ->get();

        if ($urls->isEmpty()) {
            Log::info("No pending URLs found for search #{$this->savedSearchId}");
            $importSession->markCompleted();
            
            $schedule = Schedule::where('import_session_id', $this->importSessionId)->first();
            if ($schedule) {
                $schedule->markPropertyImportComplete();
                $schedule->markAsCompleted();
            }
            return;
        }

        Log::info("Found " . $urls->count() . " pending URLs to fetch details for");
        
        // Update session total properties if needed
        $importSession->update(['total_properties' => $urls->count()]);

        // Process in chunks of 20 to avoid timeouts and rate limits
        $chunks = $urls->chunk(20);
        
        foreach ($chunks as $chunk) {
            $urlsToFetch = $chunk->map(function ($url) {
                return [
                    'id' => $url->id,
                    'url' => $url->url,
                    'rightmove_id' => $url->rightmove_id
                ];
            })->toArray();

            try {
                $results = $propertyService->fetchPropertiesConcurrently($urlsToFetch);
                
                $importedCount = 0;
                foreach ($results['properties'] ?? [] as $property) {
                    try {
                        $this->saveProperty($property);
                        $importedCount++;
                        
                        // Mark URL as completed
                        Url::where('rightmove_id', $property['id'])->update(['status' => 'completed']);
                        
                        // Step 3: Automatically trigger sold data import
                        if (!empty($property['sold_link'])) {
                            ImportSoldJob::dispatch($property['id'])
                                ->onQueue('imports')
                                ->delay(now()->addSeconds(rand(5, 60)));
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to save property: " . $e->getMessage());
                    }
                }

                $importSession->increment('imported_properties', $importedCount);
                
                // Progress update
                Log::info("Imported {$importedCount} properties details");

                // Rate limiting delay
                usleep(500000); 

            } catch (\Exception $e) {
                Log::error("Failed to fetch concurrent properties: " . $e->getMessage());
            }
        }

        $importSession->markCompleted();
        
        $schedule = Schedule::where('import_session_id', $this->importSessionId)->first();
        if ($schedule) {
            $schedule->markPropertyImportComplete();
            $schedule->markAsCompleted();
        }

        Log::info("=== FETCH DETAILS JOB COMPLETE ===");
    }

    protected function saveProperty(array $property): void
    {
        DB::table('properties')->updateOrInsert(
            ['id' => $property['id'] ?? null],
            [
                'location' => $property['address'] ?? null,
                'house_number' => $property['house_number'] ?? null,
                'road_name' => $property['road_name'] ?? null,
                'price' => $property['price'] ?? null,
                'bedrooms' => $property['bedrooms'] ?? null,
                'bathrooms' => $property['bathrooms'] ?? null,
                'property_type' => $property['property_type'] ?? null,
                'size' => $property['size'] ?? null,
                'tenure' => $property['tenure'] ?? null,
                'council_tax' => $property['council_tax'] ?? null,
                'parking' => $property['parking'] ?? null,
                'garden' => $property['garden'] ?? null,
                'key_features' => json_encode($property['key_features'] ?? []),
                'description' => $property['description'] ?? null,
                'sold_link' => $property['sold_link'] ?? null,
                'filter_id' => $this->savedSearchId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (!empty($property['images'])) {
            $propertyId = $property['id'];
            DB::table('property_images')->where('property_id', $propertyId)->delete();
            foreach ($property['images'] as $imageLink) {
                DB::table('property_images')->insert([
                    'property_id' => $propertyId,
                    'image_link' => $imageLink,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
