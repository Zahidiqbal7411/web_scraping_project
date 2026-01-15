<?php

namespace App\Jobs;

use App\Models\ImportSession;
use App\Models\SavedSearch;
use App\Services\RightmoveScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * MasterImportJob - Orchestrates the import process
 * 
 * This job probes the search URL, determines if splitting is needed,
 * and dispatches appropriate ImportChunkJob instances.
 */
class MasterImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    // Store session ID instead of model for reliability on server
    protected int $importSessionId = 0;
    protected string $baseUrl = '';
    protected ?int $savedSearchId = null;
    protected string $mode = 'full'; // 'full', 'urls_only', 'fetch_details'

    /**
     * Create a new job instance.
     */
    public function __construct(ImportSession $importSession, string $baseUrl, ?int $savedSearchId = null, string $mode = 'full')
    {
        // Store ID instead of model for better reliability on cron-based systems
        $this->importSessionId = $importSession->id;
        $this->baseUrl = $baseUrl;
        $this->savedSearchId = $savedSearchId;
        $this->mode = $mode;
        
        // Run on a high priority queue to ensure orchestration starts immediately
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(RightmoveScraperService $scraperService): void
    {
        // IMPORTANT: Fetch session fresh from database (not serialized)
        // This ensures progress updates work on server with cron-based queue
        $importSession = ImportSession::find($this->importSessionId);
        
        if (!$importSession) {
            Log::error("Import session {$this->importSessionId} not found, aborting master job");
            return;
        }
        
        Log::info("=== MASTER IMPORT JOB: Starting for session {$this->importSessionId} ===");
        Log::info("URL: {$this->baseUrl}");
        
        // Mark session as processing
        $importSession->start();
        
        // CLEAR OLD ASSOCIATIONS for this search before starting a fresh import
        // This prevents property accumulation from old imports and fixes the count discrepancy (e.g. 96 vs 51)
        if ($this->savedSearchId) {
            try {
                Log::info("MASTER: Clearing old associations in pivot table for Search ID: {$this->savedSearchId}");
                \DB::table('property_saved_search')->where('saved_search_id', $this->savedSearchId)->delete();
                Log::info("MASTER: Successfully cleared old associations.");
            } catch (\Exception $e) {
                Log::error("MASTER: Failed to clear old associations: " . $e->getMessage());
                // Continue with import anyway
            }
        }
        
        try {
            // Extract initial price range from URL (or use defaults)
            [$currentMin, $currentMax] = $this->extractPriceRangeFromUrl($this->baseUrl);
            $minPrice = $currentMin ?? 0;
            $maxPrice = $currentMax ?? 15000000; // £15M max
            
            // ALWAYS use predefined UK property price bands for comprehensive coverage
            // This bypasses the unreliable probeResultCount and ensures ALL properties are scraped
            $priceBands = $this->getUKPriceBands($minPrice, $maxPrice);
            
            Log::info("=== FORCED PRICE SPLITTING: Using " . count($priceBands) . " price bands ===");
            
            $allChunks = [];
            $splitStats = [
                'total_splits' => count($priceBands),
                'max_depth' => 0,
                'split_details' => []
            ];
            
            // Create chunks for each price band
            foreach ($priceBands as $index => $band) {
                $rangeUrl = $this->buildUrlWithPriceRange($this->baseUrl, $band['min'], $band['max']);
                $rangeLabel = "£" . number_format($band['min']) . " - £" . number_format($band['max']);
                
                $allChunks[] = [
                    'url' => $rangeUrl,
                    'min_price' => $band['min'],
                    'max_price' => $band['max'],
                    'estimated_count' => 500, // Estimate per band
                    'depth' => 0
                ];
                
                $splitStats['split_details'][] = [
                    'range' => $rangeLabel,
                    'depth' => 0,
                    'total_in_range' => 'Unknown',
                    'capped' => false
                ];
                
                Log::info("Band " . ($index + 1) . ": {$rangeLabel}");
            }
            
            // Update session with split stats
            $importSession->updateSplitStats(
                $splitStats['total_splits'],
                $splitStats['max_depth'],
                $splitStats['split_details']
            );
            
            // Get ACTUAL total count from source website instead of estimation
            // This ensures the progress bar shows the correct number
            // Use cache to avoid redundant probes during job retries
            $probeUrl = $this->baseUrl;
            // Do not force includeSSTC=true. Respect the user's URL.
            // If the user wants STC, it will be in the URL.
            /* 
            if (strpos($probeUrl, 'includeSSTC=true') === false) {
                $separator = (strpos($probeUrl, '?') === false) ? '?' : '&';
                $probeUrl .= $separator . 'includeSSTC=true';
            }
            */

            $cacheKey = 'import_total_' . md5($probeUrl);
            $actualTotalCount = cache()->remember($cacheKey, 3600, function() use ($scraperService, $probeUrl) {
                return $scraperService->probeResultCount($probeUrl);
            });
            
            $importSession->setTotalProperties($actualTotalCount);
            Log::info("=== MASTER: Actual total properties from source: {$actualTotalCount} ===");
            
            // Add jobs count to session
            $importSession->addJobs(count($allChunks));
            
            Log::info("=== MASTER: Dispatching " . count($allChunks) . " chunk jobs ===");
            
            // Dispatch a chunk job for each price band with delays
            foreach ($allChunks as $index => $chunk) {
                $delay = $index * 30; // 30 seconds between each job (faster than before)
                
                ImportChunkJob::dispatch(
                    $importSession,
                    $chunk['url'],
                    $chunk['min_price'],
                    $chunk['max_price'],
                    $chunk['estimated_count'],
                    $this->savedSearchId,
                    $this->mode
                )->onQueue('imports')->delay(now()->addSeconds($delay));
                
                Log::info("Dispatched chunk job {$index} for range: £" . 
                    number_format($chunk['min_price']) . " - £" . 
                    number_format($chunk['max_price']) . " (delay: {$delay}s)");
            }
            
            Log::info("=== MASTER IMPORT JOB: Completed dispatching for session {$this->importSessionId} ===");
            
        } catch (\Exception $e) {
            Log::error("Master import job failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            // Fetch session fresh for error handling
            $session = ImportSession::find($this->importSessionId);
            if ($session) {
                $session->markFailed($e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Recursively collect chunks for processing
     */
    protected function collectChunks(
        string $baseUrl,
        int $minPrice,
        int $maxPrice,
        int $depth,
        int $maxDepth,
        array &$allChunks,
        array &$seenIds,
        array &$splitStats,
        RightmoveScraperService $scraperService
    ): void {
        $indent = str_repeat("  ", $depth);
        $rangeLabel = "£" . number_format($minPrice) . " - £" . number_format($maxPrice);
        
        Log::info("{$indent}[Depth {$depth}] Probing range: {$rangeLabel}");
        
        // Track max depth
        if ($depth > $splitStats['max_depth']) {
            $splitStats['max_depth'] = $depth;
        }
        
        // Build URL with current price range
        $rangeUrl = $this->buildUrlWithPriceRange($baseUrl, $minPrice, $maxPrice);
        
        // Probe to get total results for this range
        $totalResults = $scraperService->probeResultCount($rangeUrl);
        
        Log::info("{$indent}[Depth {$depth}] Range {$rangeLabel}: {$totalResults} results");
        
        // BASE CASE: Results are under limit or max depth reached
        if ($totalResults <= 1000 || $depth >= $maxDepth) {
            if ($depth >= $maxDepth && $totalResults > 1000) {
                Log::warning("{$indent}[Depth {$depth}] MAX DEPTH REACHED! Range has {$totalResults} results, will cap at ~1000");
            }
            
            // Add this chunk to be processed
            $allChunks[] = [
                'url' => $rangeUrl,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'estimated_count' => min($totalResults, 1000),
                'depth' => $depth
            ];
            
            $splitStats['split_details'][] = [
                'range' => $rangeLabel,
                'depth' => $depth,
                'total_in_range' => $totalResults,
                'capped' => $totalResults > 1000
            ];
            
            return;
        }
        
        // RECURSIVE CASE: Too many results, split the price range
        $splitStats['total_splits']++;
        
        $priceSpan = $maxPrice - $minPrice;
        
        // Don't split if range is too small
        if ($priceSpan < 5000) {
            Log::warning("{$indent}[Depth {$depth}] Price range too narrow to split ({$priceSpan}). Adding as-is.");
            
            $allChunks[] = [
                'url' => $rangeUrl,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'estimated_count' => min($totalResults, 1000),
                'depth' => $depth
            ];
            return;
        }
        
        // Calculate midpoint with weighted split (40%) for UK property distribution
        $midPrice = $minPrice + (int) ($priceSpan * 0.4);
        
        // Round to nearest sensible price point
        if ($priceSpan > 100000) {
            $midPrice = (int) (round($midPrice / 10000) * 10000);
        } elseif ($priceSpan > 10000) {
            $midPrice = (int) (round($midPrice / 1000) * 1000);
        }
        
        Log::info("{$indent}[Depth {$depth}] Splitting at £" . number_format($midPrice));
        
        // Recurse for lower half
        $this->collectChunks(
            $baseUrl,
            $minPrice,
            $midPrice,
            $depth + 1,
            $maxDepth,
            $allChunks,
            $seenIds,
            $splitStats,
            $scraperService
        );
        
        // Recurse for upper half
        $this->collectChunks(
            $baseUrl,
            $midPrice + 1,
            $maxPrice,
            $depth + 1,
            $maxDepth,
            $allChunks,
            $seenIds,
            $splitStats,
            $scraperService
        );
    }

    /**
     * Extract price range from URL
     */
    protected function extractPriceRangeFromUrl(string $url): array
    {
        $minPrice = null;
        $maxPrice = null;
        
        if (preg_match('/minPrice=(\d+)/', $url, $matches)) {
            $minPrice = (int) $matches[1];
        }
        if (preg_match('/maxPrice=(\d+)/', $url, $matches)) {
            $maxPrice = (int) $matches[1];
        }
        
        return [$minPrice, $maxPrice];
    }

    /**
     * Build URL with price range parameters
     */
    protected function buildUrlWithPriceRange(string $baseUrl, int $minPrice, int $maxPrice): string
    {
        // Parse URL components
        $parts = parse_url($baseUrl);
        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        // Check if includeSSTC was in original params
        $includeSSTC = isset($queryParams['includeSSTC']) ? $queryParams['includeSSTC'] : null;
        if (!$includeSSTC && isset($queryParams['_includeSSTC'])) {
             // Handle potential weird param naming
             $includeSSTC = $queryParams['_includeSSTC']; 
        }

        // Remove parameters we want to override
        unset($queryParams['minPrice']);
        unset($queryParams['maxPrice']);
        unset($queryParams['index']);
        // Don't unset SSTC yet, we want to preserve it if we captured it
        unset($queryParams['includeSSTC']);
        unset($queryParams['_includeSSTC']);
        
        // Add our parameters
        $queryParams['minPrice'] = $minPrice;
        $queryParams['maxPrice'] = $maxPrice;
        
        // Preserve original SSTC setting if present, otherwise default to false (standard Rightmove behavior)
        if ($includeSSTC) {
            $queryParams['includeSSTC'] = $includeSSTC;
        }
        $queryParams['sortType'] = $queryParams['sortType'] ?? '2'; // Sort by newest if not set

        // Rebuild query string
        $newQuery = http_build_query($queryParams);
        
        // Handle URL encoding - Rightmove requires certain characters to be unencoded
        // %2C = comma, %5E = ^ (used in locationIdentifier like REGION^123)
        $newQuery = str_replace(['%2C', '%5E'], [',', '^'], $newQuery);

        $url = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.rightmove.co.uk') . ($parts['path'] ?? '/property-for-sale/find.html') . '?' . $newQuery;

        return $url;
    }

    /**
     * Get predefined UK property price bands
     * Uses Rightmove's exact thresholds with NON-OVERLAPPING ranges
     * to prevent duplicate properties at boundary prices
     */
    protected function getUKPriceBands(int $minPrice, int $maxPrice): array
    {
        // Rightmove's ACTUAL supported price thresholds
        // We use these exact values but offset minPrice by 1 to avoid boundary duplicates
        // e.g. Band 1: 0-100000, Band 2: 100001-150000 (not 100000-150000)
        $thresholds = [
            0, 50000, 100000, 150000, 200000, 250000, 300000, 350000, 400000, 
            450000, 500000, 600000, 700000, 800000, 900000, 1000000, 
            1250000, 1500000, 2000000, 2500000, 3000000, 5000000, 10000000, 15000000
        ];
        
        $bands = [];
        for ($i = 0; $i < count($thresholds) - 1; $i++) {
            // First band uses exact threshold, subsequent bands use threshold+1 to avoid overlap
            $bandMin = ($i === 0) ? $thresholds[$i] : $thresholds[$i] + 1;
            $bandMax = $thresholds[$i + 1];
            
            // Only include bands that overlap with the user's range
            if ($bandMax > $minPrice && $bandMin < $maxPrice) {
                $bands[] = [
                    'min' => max($bandMin, $minPrice),
                    'max' => min($bandMax, $maxPrice)
                ];
            }
        }
        
        // If no bands match (shouldn't happen), use the full range
        if (empty($bands)) {
            $bands = [['min' => $minPrice, 'max' => $maxPrice]];
        }
        
        Log::info("Generated " . count($bands) . " NON-OVERLAPPING price bands from £" . number_format($minPrice) . " to £" . number_format($maxPrice));
        
        return $bands;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("MasterImportJob failed permanently: " . $exception->getMessage());
        
        // Fetch session fresh from database for failure handling
        $session = ImportSession::find($this->importSessionId);
        if ($session) {
            $session->markFailed("Master job failed: " . $exception->getMessage());
        }
    }
}
