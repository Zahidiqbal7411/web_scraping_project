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
        
        // Run on a specific queue for imports
        $this->onQueue('imports');
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
            $cacheKey = 'import_total_' . md5($this->baseUrl);
            $actualTotalCount = cache()->remember($cacheKey, 3600, function() use ($scraperService) {
                return $scraperService->probeResultCount($this->baseUrl);
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
        $url = $baseUrl;
        
        // Remove existing price params
        $url = preg_replace('/&?minPrice=\d+/', '', $url);
        $url = preg_replace('/&?maxPrice=\d+/', '', $url);
        
        // Clean up any double ampersands or trailing ampersands
        $url = preg_replace('/&&+/', '&', $url);
        $url = rtrim($url, '&');
        
        // Add separator
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        
        // Add price range - Rightmove uses minPrice and maxPrice
        $url .= "{$separator}minPrice={$minPrice}&maxPrice={$maxPrice}";
        
        return $url;
    }

    /**
     * Get predefined UK property price bands
     * Uses realistic UK property price distribution for comprehensive coverage
     */
    protected function getUKPriceBands(int $minPrice, int $maxPrice): array
    {
        // Predefined UK property price bands
        // Smaller bands at lower prices (where most properties are), larger bands at higher prices
        $allBands = [
            ['min' => 0, 'max' => 50000],
            ['min' => 50000, 'max' => 100000],
            ['min' => 100000, 'max' => 150000],
            ['min' => 150000, 'max' => 200000],
            ['min' => 200000, 'max' => 250000],
            ['min' => 250000, 'max' => 300000],
            ['min' => 300000, 'max' => 350000],
            ['min' => 350000, 'max' => 400000],
            ['min' => 400000, 'max' => 450000],
            ['min' => 450000, 'max' => 500000],
            ['min' => 500000, 'max' => 600000],
            ['min' => 600000, 'max' => 700000],
            ['min' => 700000, 'max' => 800000],
            ['min' => 800000, 'max' => 1000000],
            ['min' => 1000000, 'max' => 1500000],
            ['min' => 1500000, 'max' => 2000000],
            ['min' => 2000000, 'max' => 3000000],
            ['min' => 3000000, 'max' => 5000000],
            ['min' => 5000000, 'max' => 15000000],
        ];
        
        // Filter bands that overlap with user's price range
        $filteredBands = [];
        foreach ($allBands as $band) {
            // Include band if it overlaps with user's range
            if ($band['max'] > $minPrice && $band['min'] < $maxPrice) {
                $filteredBands[] = [
                    'min' => max($band['min'], $minPrice),
                    'max' => min($band['max'], $maxPrice)
                ];
            }
        }
        
        // If no bands match (shouldn't happen), use the full range
        if (empty($filteredBands)) {
            $filteredBands = [['min' => $minPrice, 'max' => $maxPrice]];
        }
        
        return $filteredBands;
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
