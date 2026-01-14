<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Models\Url;
use App\Services\InternalPropertyService;
use App\Services\RightmoveScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * ImportChunkJob - Processes a chunk of up to 1000 properties
 * 
 * This job scrapes properties from a specific URL/price range,
 * fetches full property details, and saves them to the database.
 */
class ImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying.
     */
    public int $backoff = 30;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes per chunk - reduced for shared hosting

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    // Store session ID instead of model for reliability on server
    protected int $importSessionId = 0;
    protected string $chunkUrl = '';
    protected int $minPrice = 0;
    protected int $maxPrice = 0;
    protected int $estimatedCount = 0;
    protected ?int $savedSearchId = null;
    protected string $mode = 'full'; // 'full' or 'urls_only'

    /**
     * Create a new job instance.
     */
    public function __construct(
        ImportSession $importSession,
        string $chunkUrl,
        int $minPrice,
        int $maxPrice,
        int $estimatedCount,
        ?int $savedSearchId = null,
        string $mode = 'full'
    ) {
        // Store ID instead of model for better reliability on cron-based systems
        $this->importSessionId = $importSession->id;
        $this->chunkUrl = $chunkUrl;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->estimatedCount = $estimatedCount;
        $this->savedSearchId = $savedSearchId;
        $this->mode = $mode;
        
        $this->onQueue('imports');
    }


    /**
     * Execute the job.
     */
    public function handle(
        RightmoveScraperService $scraperService,
        InternalPropertyService $propertyService
    ): void {
        $rangeLabel = "£" . number_format($this->minPrice) . " - £" . number_format($this->maxPrice);
        
        // IMPORTANT: Fetch session fresh from database (not serialized)
        // This ensures progress updates work on server with cron-based queue
        $importSession = ImportSession::find($this->importSessionId);
        
        if (!$importSession) {
            Log::error("Import session {$this->importSessionId} not found, skipping job");
            return;
        }
        
        Log::info("=== CHUNK JOB: Processing {$rangeLabel} for session {$this->importSessionId} ===");
        Log::info("URL: {$this->chunkUrl}");
        
        // Check if session is cancelled
        if ($importSession->status === ImportSession::STATUS_CANCELLED) {
            Log::info("Session cancelled, skipping chunk job");
            return;
        }
        
        try {
            // Step 1: Scrape property URLs from search results
            $urlsData = $scraperService->scrapePropertyUrls($this->chunkUrl);
            
            if (empty($urlsData)) {
                Log::warning("No URLs scraped for range {$rangeLabel}");
                $importSession->incrementCompleted(0, 0);
                return;
            }
            
            Log::info("Scraped " . count($urlsData) . " property URLs");
            
            // Step 2: Save URLs to database
            $savedCount = 0;
            $skippedCount = 0;
            $urlsToFetch = [];
            
            foreach ($urlsData as $urlData) {
                $propertyId = $urlData['id'] ?? null;
                $propertyUrl = $urlData['url'] ?? null;
                
                if (!$propertyUrl) {
                    continue;
                }
                
                // Check if URL already exists
                $existing = Url::where('url', $propertyUrl)->first();
                
                if ($existing) {
                    $propertyIdFromDb = $urlData['id'] ?? null;
                    $propertyExists = DB::table('properties')->where('id', $propertyIdFromDb)->exists();
                    
                    // Check if already linked to THIS search via pivot table
                    $alreadyLinked = DB::table('property_saved_search')
                        ->where('property_id', $propertyIdFromDb)
                        ->where('saved_search_id', $this->savedSearchId)
                        ->exists();
                    
                    if ($propertyExists && !$alreadyLinked && $this->savedSearchId) {
                        // FIX: Link existing property to this search immediately!
                        Log::info("Linking existing property {$propertyIdFromDb} to search {$this->savedSearchId}");
                        DB::table('property_saved_search')->updateOrInsert(
                            ['property_id' => $propertyIdFromDb, 'saved_search_id' => $this->savedSearchId],
                            ['updated_at' => now(), 'created_at' => now()]
                        );
                        $savedCount++; // Count as "imported" since it's now in this search
                    }

                    if ($propertyExists && $alreadyLinked) {
                        $skippedCount++;
                        Log::debug("Skipping property {$propertyIdFromDb} - already linked to search {$this->savedSearchId}");
                        continue;
                    }
                }
                
                // Save/Update URL record
                $url = Url::updateOrCreate(
                    ['url' => $propertyUrl],
                    [
                        'rightmove_id' => $propertyId,
                        'filter_id' => $this->savedSearchId,
                        'status' => 'pending',
                        'updated_at' => now()
                    ]
                );
                
                $urlsToFetch[] = [
                    'id' => $url->id,
                    'url' => $propertyUrl,
                    'rightmove_id' => $propertyId
                ];
            }
            
            Log::info("Saved " . count($urlsToFetch) . " new URLs, skipped {$skippedCount} existing");
            
            // Step 3: Fetch full property details for new URLs (if not urls_only)
            if ($this->mode === 'full' && !empty($urlsToFetch)) {
                $results = $propertyService->fetchPropertiesConcurrently($urlsToFetch);
                
                // Step 4: Save property details to database
                $importedCount = 0;
                
                foreach ($results['properties'] ?? [] as $property) {
                    try {
                        $wasNew = $this->saveProperty($property);
                        if ($wasNew) {
                            $importedCount++;
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to save property: " . $e->getMessage());
                    }
                }
                
                Log::info("Successfully imported {$importedCount} properties");
                $savedCount = $importedCount;

                // Step 5: Automatically trigger sold data import for these properties
                foreach ($results['properties'] ?? [] as $property) {
                    if (!empty($property['sold_link'])) {
                        Log::info("Dispatching ImportSoldJob for property {$property['id']}");
                        ImportSoldJob::dispatch($property['id'])
                            ->onQueue('imports')
                            ->delay(now()->addSeconds(rand(5, 30)));
                    }
                }
            }
            
            // Rate limiting delay between chunks
            usleep(500000); // 0.5 second
            
            // Update session progress - fetch fresh to ensure we have latest data
            $importSession->incrementCompleted($savedCount, $skippedCount);
            
            Log::info("=== CHUNK JOB COMPLETE: {$rangeLabel} ===");
            
        } catch (\Exception $e) {
            Log::error("Chunk job failed for {$rangeLabel}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            // Fetch session fresh for error handling
            $session = ImportSession::find($this->importSessionId);
            if ($session) {
                $session->incrementFailed("Chunk {$rangeLabel}: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Save property to database
     * @return bool True if this was a NEW property, false if it was an update
     */
    protected function saveProperty(array $property): bool
    {
        $propertyId = $property['id'] ?? null;
        if (!$propertyId) {
            return false;
        }

        // Check if property already exists (to determine if this is new)
        $existsBefore = DB::table('properties')->where('id', $propertyId)->exists();

        // Check if already linked to THIS search
        $alreadyLinkedToThisSearch = false;
        if ($this->savedSearchId) {
            $alreadyLinkedToThisSearch = DB::table('property_saved_search')
                ->where('property_id', $propertyId)
                ->where('saved_search_id', $this->savedSearchId)
                ->exists();
        }

        // If already exists AND already linked to this search, this is a TRUE duplicate
        if ($existsBefore && $alreadyLinkedToThisSearch) {
            Log::debug("Property {$propertyId} already exists and is linked to search {$this->savedSearchId} - skipping");
            return false;
        }

        // Use upsert to handle duplicates
        DB::table('properties')->updateOrInsert(
            ['id' => $propertyId],
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
                'accessibility' => $property['accessibility'] ?? null,
                'ground_rent' => $property['ground_rent'] ?? null,
                'annual_service_charge' => $property['annual_service_charge'] ?? null,
                'lease_length' => $property['lease_length'] ?? null,
                'key_features' => json_encode($property['key_features'] ?? []),
                'description' => $property['description'] ?? null,
                'sold_link' => $property['sold_link'] ?? null,
                'filter_id' => $this->savedSearchId, // Keep for backward compatibility/legacy
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // ATTACH TO SAVED SEARCH (Pivot Table)
        if ($this->savedSearchId) {
            DB::table('property_saved_search')->updateOrInsert(
                [
                    'property_id' => $propertyId,
                    'saved_search_id' => $this->savedSearchId
                ],
                [
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );
        }

        // Save Images
        if (!empty($property['images'])) {
            // Delete existing images for this property to avoid duplicates
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

        // Return true only if the property was NOT already linked to this search
        // (meaning this is a genuine new import for this search)
        return !$alreadyLinkedToThisSearch;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $rangeLabel = "£" . number_format($this->minPrice) . " - £" . number_format($this->maxPrice);
        Log::error("ImportChunkJob failed permanently for {$rangeLabel}: " . $exception->getMessage());
        
        // Fetch session fresh from database for failure handling
        $session = ImportSession::find($this->importSessionId);
        if ($session) {
            $session->incrementFailed("Chunk {$rangeLabel} failed: " . $exception->getMessage());
        }
    }

    /**
     * Calculate the number of seconds to wait before retrying.
     */
    public function retryAfter(): int
    {
        // Exponential backoff: 30s, 60s, 120s
        return $this->backoff * pow(2, $this->attempts() - 1);
    }
}
