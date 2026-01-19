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
        
        // Run on the imports queue to match server queue worker
        $this->onQueue('imports');
    }

    /**
     * Execute the job.
     */
    public function handle(RightmoveScraperService $scraperService): void
    {
        // DEBUG: Log that we actually entered the handle method
        Log::emergency("=== MASTER JOB HANDLE() CALLED: Session ID {$this->importSessionId} ===");
        
        // IMPORTANT: Fetch session fresh from database (not serialized)
        // This ensures progress updates work on server with cron-based queue
        $importSession = ImportSession::find($this->importSessionId);
        
        if (!$importSession) {
            Log::error("Import session {$this->importSessionId} not found, aborting master job");
            return;
        }
        
        Log::emergency("=== MASTER IMPORT JOB: Starting for session {$this->importSessionId} ===");
        Log::emergency("URL: {$this->baseUrl}");
        
        // Mark session as processing
        $importSession->start();
        
        // CLEAR OLD ASSOCIATIONS for this search before starting a fresh import
        // This prevents property accumulation from old imports and fixes the count discrepancy (e.g. 96 vs 51)
        /* 
        // INCREMENTAL IMPORT - COMMENTED OUT DELETION
        // We now keep existing associations to allow skipping already imported properties.
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
        */
        
        try {
            // PAGE-BASED CHUNKING: Split into multiple jobs by page ranges
            // Each job handles 10 pages (~240 properties) to avoid source website blocking
            Log::info("=== PAGE-BASED IMPORT: Splitting by page ranges ===");
            Log::info("URL: {$this->baseUrl}");
            
            // Get ACTUAL total count from source website (No caching, always get fresh count)
            // $cacheKey = 'import_total_' . md5($this->baseUrl);
            $actualTotalCount = $scraperService->probeResultCount($this->baseUrl);
            
            // CRITICAL FIX: If probe returns 0, it might be a network/parsing error, not actually 0 properties
            // Use a fallback estimate to allow import to proceed
            if ($actualTotalCount === 0) {
                Log::warning("=== MASTER: Initial probe returned 0. Assuming LARGE dataset (blocking detected). Defaulting to 50,000. ===");
                // Default to HIGH number to force Static Price Band strategy (safest when blocked)
                $actualTotalCount = 50000; 
            }
            
            $importSession->setTotalProperties($actualTotalCount);
            Log::info("=== MASTER: Total properties from source: {$actualTotalCount} ===");
            
            // PRICE PARTITIONING STRATEGY (For >1000 results)
            $totalDispatched = 0; // Track total jobs dispatched across all paths
            $pendingChunks = []; // CRITICAL: Initialize the array to store planned chunks
            
            if ($actualTotalCount > 1000) {
                Log::info("=== MASTER: Large search detected (>1000). Property count: {$actualTotalCount} ===");
                
                $allChunks = [];
                $useSimpleFallback = false;
                
                // MEDIUM-LARGE IMPORTS (1000+): Use recursive probing with delays
                // We split strategy based on size:
                // <= 2000: use Recursion (accurate, fast enough)
                // > 2000: use Static Bands (safer, avoids deep recursion timeout)
                
                if ($actualTotalCount <= 2000) {
                     // RECURSIVE STRATEGY
                    try {
                        $seenIds = [];
                        $splitStats = ['total_splits' => 0, 'max_depth' => 0, 'split_details' => []];
                        
                        // Extract bounds from URL or default
                        [$minPrice, $maxPrice] = $this->extractPriceRangeFromUrl($this->baseUrl);
                        $minPrice = $minPrice ?? 0;
                        $maxPrice = $maxPrice ?? 2000000;
                        
                        if ($maxPrice < 1000 && $this->baseUrl) { 
                             // If max price is incredibly low/unset, default to high value
                             Log::info("MasterImportJob: Max price detected as {$maxPrice}, defaulting to 50M");
                             $maxPrice = 50000000;
                        }
                        if ($minPrice === 0 && $maxPrice === 2000000) {
                             // Default range often implies no specific upper limit
                             Log::info("MasterImportJob: Default range detected (0-2M), extending to 50M for partitioning");
                             $maxPrice = 50000000; 
                        }

                        // Increase max depth to 10 to ensure we can split dense price ranges enough
                        $this->collectChunks(
                            $this->baseUrl,
                            $minPrice,
                            $maxPrice,
                            1,
                            10, // Max Depth
                            $allChunks,
                            $seenIds,
                            $splitStats,
                            $scraperService
                        );
                        
                        Log::info("=== MASTER: Partitioning complete. Created " . count($allChunks) . " price-band chunks. ===");
                        
                    } catch (\Exception $e) {
                        Log::warning("=== MASTER: Price partitioning failed: " . $e->getMessage() . ". Using simple fallback. ===");
                        $useSimpleFallback = true;
                        $allChunks = [];
                    }
                } else {
                    // STATIC BAND STRATEGY needed for very large imports to avoid Rate Limiting/Timeouts
                    Log::info("=== MASTER: Very large import (>2000). Using STATIC PRICE BANDS to ensure stability. ===");
                    $allChunks = $this->getStaticPriceBandChunks($this->baseUrl);
                }
                
                // FALLBACK: If partitioning failed or produced no chunks, use simple 42-page approach
                if ($useSimpleFallback || empty($allChunks)) {
                    Log::info("=== MASTER: Using simple fallback - importing first 1000 properties only. ===");
                    
                    $totalPages = 42; // Max pages = ~1000 properties
                    $numChunks = ceil($totalPages / 10);
                    $importSession->addJobs($numChunks);
                    $totalDispatched = $numChunks; // Track dispatched jobs
                    
                    for ($i = 0; $i < $numChunks; $i++) {
                        $startPage = $i * 10;
                        $endPage = min(($i + 1) * 10 - 1, $totalPages - 1);
                        $estimatedInChunk = min(($endPage - $startPage + 1) * 24, 1000);
                        
                        // Store job parameters for later execution
                        $pendingChunks[] = [
                            'url' => $this->baseUrl,
                            'start_page' => $startPage,
                            'end_page' => $endPage,
                            'estimated' => $estimatedInChunk,
                            'saved_search_id' => $this->savedSearchId,
                            'mode' => $this->mode
                        ];
                    }
                    
                } else {
                    // Partitioning succeeded - dispatch jobs for each price chunk
                    Log::emergency("=== MASTER: Starting job dispatch for " . count($allChunks) . " chunks ===");
                    
                    $totalJobsToDispatch = 0;
                    foreach ($allChunks as $chunkData) {
                        $cTotal = $chunkData['estimated_count'];
                        $cPages = min(42, ceil($cTotal / 24));
                        $pagesPerJob = 10;
                        $cJobs = ceil($cPages / $pagesPerJob);
                        $totalJobsToDispatch += $cJobs;
                    }
                    
                    Log::emergency("=== MASTER: Will dispatch {$totalJobsToDispatch} total jobs ===");
                    
                    $importSession->addJobs($totalJobsToDispatch);
                    $totalDispatched = $totalJobsToDispatch; // Track dispatched jobs
                    $globalJobIndex = 0;

                    foreach ($allChunks as $chunkData) {
                        $chunkUrl = $chunkData['url'];
                        $cTotal = $chunkData['estimated_count'];
                        
                        $cPages = min(42, ceil($cTotal / 24));
                        $cNumChunks = ceil($cPages / 10);
                        
                        for ($i = 0; $i < $cNumChunks; $i++) {
                            $startPage = $i * 10;
                            $endPage = min(($i + 1) * 10 - 1, $cPages - 1);
                            $subEstimated = min(($endPage - $startPage + 1) * 24, $cTotal);
                            
                            $delay = $globalJobIndex * 1;
                            
                            // Store job parameters for later execution
                            $pendingChunks[] = [
                                'url' => $chunkUrl,
                                'start_page' => $startPage,
                                'end_page' => $endPage,
                                'estimated' => $subEstimated,
                                'saved_search_id' => $this->savedSearchId,
                                'mode' => $this->mode
                            ];
                            
                            $globalJobIndex++;
                        }
                    }
                }
            } else {
                // STANDARD IMPORT (<1000 results)
                Log::info("=== MASTER: Standard import (<1000 properties). ===");
                
                // Calculate number of pages needed (24 properties per page)
                $totalPages = min(42, ceil($actualTotalCount / 24)); // Max 42 pages (Rightmove limit)
                $pagesPerChunk = 10; // Each job handles 10 pages (~240 properties)
                $numChunks = ceil($totalPages / $pagesPerChunk);
                
                Log::info("=== MASTER: {$totalPages} pages, splitting into {$numChunks} chunk jobs ===");
                
                // Add jobs count to session
                $importSession->addJobs($numChunks);
                $totalDispatched = $numChunks; // Track dispatched jobs
                
                // Dispatch page-based chunk jobs with delays
                for ($chunk = 0; $chunk < $numChunks; $chunk++) {
                    $startPage = $chunk * $pagesPerChunk;
                    $endPage = min(($chunk + 1) * $pagesPerChunk - 1, $totalPages - 1);
                    $estimatedInChunk = min(($endPage - $startPage + 1) * 24, $actualTotalCount);
                    $delay = $chunk * 2; // 2 seconds between each job
                    
                    // Store job parameters for later execution
                    $pendingChunks[] = [
                        'url' => $this->baseUrl,
                        'start_page' => $startPage,
                        'end_page' => $endPage,
                        'estimated' => $estimatedInChunk,
                        'saved_search_id' => $this->savedSearchId,
                        'mode' => $this->mode
                    ];
                    
                    Log::info("Planned chunk job {$chunk}: pages {$startPage}-{$endPage} (~{$estimatedInChunk} properties)");
                }
            }
            
            // SAVE PLANNED CHUNKS TO SESSION & DISPATCH TO QUEUE
            $totalJobs = count($pendingChunks);
            Log::emergency("=== MASTER IMPORT JOB: Planning Complete - {$totalJobs} chunks generated ===");
            
            if ($totalJobs > 0) {
                // Initialize session job count explicitly
                $importSession->update(['total_jobs' => $totalJobs]);
                
                Log::emergency("=== MASTER: Dispatching {$totalJobs} jobs to BACKGROUND QUEUE ===");
                
                foreach ($pendingChunks as $jobData) {
                    \App\Jobs\ImportChunkJob::dispatch(
                        $importSession,
                        $jobData['url'],
                        $jobData['start_page'],
                        $jobData['end_page'],
                        $jobData['estimated'],
                        $jobData['saved_search_id'],
                        $jobData['mode']
                    );
                }
                
                Log::emergency("=== MASTER: Dispatched all jobs to queue. ===");
            } else {
                Log::warning("=== MASTER: No chunks generated. Marking complete. ===");
                $importSession->markCompleted();
            }
            
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
        
        // Rate limiting protection: delay between probes to avoid Rightmove blocking
        usleep(500000); // 0.5 second delay
        
        // CRITICAL FIX: If probe returns 0, it might be a network error, not actually 0 results
        // Treat as potentially valid range with unknown count
        if ($totalResults === 0 && $depth === 1) {
            Log::warning("{$indent}[Depth {$depth}] Probe returned 0 for range {$rangeLabel}. Treating as potentially valid range.");
            $totalResults = 500; // Assume moderate count to allow further splitting if needed
        }
        
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
     * Get static price band chunks for VERY LARGE imports (>5000 properties)
     * Uses predefined price bands without probing to avoid timeout/rate limiting
     * Each chunk will process up to 1000 properties (42 pages max per band)
     */
    protected function getStaticPriceBandChunks(string $baseUrl): array
    {
        // Extract bounds from the user's search URL so we don't scan irrelevant prices
        [$urlMin, $urlMax] = $this->extractPriceRangeFromUrl($baseUrl);
        $minPrice = $urlMin ?? 0;
        $maxPrice = $urlMax ?? 50000000; // Default to 50M if no max set
        
        // Get strict, non-overlapping bands that fit within the user's range
        $bands = $this->getUKPriceBands($minPrice, $maxPrice);
        
        $chunks = [];
        foreach ($bands as $band) {
            $chunks[] = [
                'url' => $this->buildUrlWithPriceRange($baseUrl, $band['min'], $band['max']),
                'min_price' => $band['min'],
                'max_price' => $band['max'],
                'estimated_count' => 500, // Estimate lower since bands are granular
                'depth' => 1
            ];
        }
        
        Log::info("Created " . count($chunks) . " granular static chunks for range £{$minPrice}-£{$maxPrice}");
        return $chunks;
    }

    /**
     * Extract min and max price from a Rightmove URL
     * Returns [min, max] or [null, null]
     */
    protected function extractPriceRangeFromUrl(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return [null, null];
        }

        parse_str($query, $params);
        
        $min = isset($params['minPrice']) ? (int) $params['minPrice'] : null;
        $max = isset($params['maxPrice']) ? (int) $params['maxPrice'] : null;
        
        return [$min, $max];
    }

    /**
     * Build URL with specific price range parameters
     */
    protected function buildUrlWithPriceRange(string $baseUrl, int $min, int $max): string
    {
        $parsed = parse_url($baseUrl);
        $query = [];
        
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        
        // Override/Set price params
        $query['minPrice'] = $min;
        $query['maxPrice'] = $max;
        
        // Rebuild query string
        $queryString = http_build_query($query);
        
        // Reconstruct URL
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] . '://' : '';
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        
        // CRITICAL: Rightmove requires literal ^ and , in URLs (not encoded)
        $queryString = str_replace(['%2C', '%5E', '%5e'], [',', '^', '^'], $queryString);
        
        return $scheme . $host . $path . '?' . $queryString;
    }
    
    /**
     * Get predefined UK property price bands
     * Uses Rightmove's exact thresholds with NON-OVERLAPPING ranges
     * to prevent duplicate properties at boundary prices
     */
    protected function getUKPriceBands(int $minPrice, int $maxPrice): array
    {
        // GRANULAR THRESHOLDS: Fine-grained steps (10k-15k) in dense price ranges (£100k-£600k)
        // to capture 54,000+ properties for large cities like London.
        // Each band captures up to 1,000 properties (Rightmove limit).
        // ~80 bands × 1,000 = ~80,000 max properties.
        $thresholds = [
            0, 50000, 75000, 100000,
            // Dense range: £100k-£300k (10k steps - most affordable London properties)
            110000, 120000, 130000, 140000, 150000,
            160000, 170000, 180000, 190000, 200000,
            210000, 220000, 230000, 240000, 250000,
            260000, 270000, 280000, 290000, 300000,
            // Dense range: £300k-£500k (15k steps - mid-range London properties)
            315000, 330000, 345000, 360000, 375000, 390000,
            405000, 420000, 435000, 450000, 465000, 480000, 500000,
            // Dense range: £500k-£800k (20k steps)
            520000, 540000, 560000, 580000, 600000,
            625000, 650000, 675000, 700000, 725000, 750000, 775000, 800000,
            // Higher prices: £800k-£2M (50k-100k steps)
            850000, 900000, 950000, 1000000,
            1100000, 1200000, 1300000, 1400000, 1500000,
            1600000, 1700000, 1800000, 1900000, 2000000,
            // Luxury: £2M+ (larger steps)
            2250000, 2500000, 2750000, 3000000,
            3500000, 4000000, 5000000, 6000000, 7500000,
            10000000, 15000000, 20000000, 50000000
        ];
        
        $bands = [];
        for ($i = 0; $i < count($thresholds) - 1; $i++) {
            // First band uses exact threshold, subsequent bands use threshold+1 to avoid overlap
            $bandMin = ($i === 0) ? $thresholds[$i] : $thresholds[$i] + 1;
            $bandMax = $thresholds[$i + 1];
            
            // Strictly check overlap with user's requested range
            // Band must start BEFORE user's max, and end AFTER user's min
            if ($bandMin <= $maxPrice && $bandMax >= $minPrice) {
                // Clip the band to match the user's bounds exactly
                $actualMin = max($bandMin, $minPrice);
                $actualMax = min($bandMax, $maxPrice);
                
                // Ensure valid range
                if ($actualMin <= $actualMax) {
                    $bands[] = [
                        'min' => $actualMin,
                        'max' => $actualMax
                    ];
                }
            }
        }
        
        // Fallback: if no bands found (e.g. range is tiny and fits within one threshold gap), add strictly
        if (empty($bands)) {
             $bands[] = ['min' => $minPrice, 'max' => $maxPrice];
        }
        
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
