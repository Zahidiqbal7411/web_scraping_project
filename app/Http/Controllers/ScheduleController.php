<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\SavedSearch;
use App\Models\Url;
use App\Services\RightmoveScraperService;
use App\Services\InternalPropertyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\Models\ImportSession;
use App\Jobs\MasterImportJob;

class ScheduleController extends Controller
{
    protected RightmoveScraperService $scraperService;
    protected InternalPropertyService $propertyService;

    public function __construct(
        RightmoveScraperService $scraperService,
        InternalPropertyService $propertyService
    ) {
        $this->scraperService = $scraperService;
        $this->propertyService = $propertyService;
    }

    /**
     * Display the schedule management page
     * Shows existing schedules without modifying any data
     */
    public function index()
    {
        // Get all saved searches for the dropdown
        $savedSearches = SavedSearch::orderBy('area')->get();
        
        // Get existing schedules
        $schedules = Schedule::orderBy('id', 'asc')->get();
        
        // Auto-create schedules for saved searches that don't have one yet
        foreach ($savedSearches as $search) {
            $existingSchedule = Schedule::where('saved_search_id', $search->id)->first();
            
            if (!$existingSchedule && $search->updates_url) {
                Schedule::create([
                    'saved_search_id' => $search->id,
                    'name' => $search->area ?? 'Search #' . $search->id,
                    'url' => $search->updates_url,
                    'status' => Schedule::STATUS_PENDING,
                ]);
                Log::info("Created schedule for saved search #{$search->id}: {$search->area}");
            }
        }
        
        // Refresh schedules list after potential additions
        $schedules = Schedule::orderBy('id', 'asc')->get();
        
        return view('schedules.index', compact('schedules', 'savedSearches'));
    }

    /**
     * Add a saved search to the schedule
     */
    public function store(Request $request)
    {
        $request->validate([
            'saved_search_id' => 'required|exists:saved_searches,id',
        ]);

        $savedSearch = SavedSearch::findOrFail($request->saved_search_id);

        // Check if already scheduled
        $existing = Schedule::where('saved_search_id', $savedSearch->id)
            ->whereIn('status', [Schedule::STATUS_PENDING, Schedule::STATUS_IMPORTING])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'This search is already scheduled or importing'
            ], 400);
        }

        $schedule = Schedule::create([
            'saved_search_id' => $savedSearch->id,
            'name' => $savedSearch->area ?? 'Search #' . $savedSearch->id,
            'url' => $savedSearch->updates_url,
            'status' => Schedule::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to schedule',
            'schedule' => $schedule
        ]);
    }

    /**
     * Add a custom URL to the schedule
     */
    public function storeUrl(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
        ]);

        $schedule = Schedule::create([
            'name' => $request->name,
            'url' => $request->url,
            'status' => Schedule::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Added to schedule',
            'schedule' => $schedule
        ]);
    }

    /**
     * Delete a schedule
     */
    public function destroy($id)
    {
        $schedule = Schedule::findOrFail($id);
        
        if ($schedule->isImporting()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a schedule that is currently importing'
            ], 400);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted'
        ]);
    }

    /**
     * Reset a failed schedule to pending
     */
    public function retry($id)
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->resetToPending();
        
        // Reset flags
        $schedule->update([
            'url_import_completed' => false,
            'property_import_completed' => false,
            'sold_import_completed' => false,
            'import_session_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Schedule reset to pending'
        ]);
    }

    /**
     * Start a non-blocking queued import for a schedule
     */
    public function startQueuedImport(Request $request, $id)
    {
        try {
            $schedule = Schedule::findOrFail($id);
            
            if ($schedule->status === Schedule::STATUS_IMPORTING) {
                return response()->json(['success' => false, 'message' => 'Schedule is already importing']);
            }

            Log::info("Starting queued import for schedule #{$schedule->id}: {$schedule->name}");

            // Create an import session for tracking
            $importSession = ImportSession::create([
                'saved_search_id' => $schedule->saved_search_id,
                'base_url' => $schedule->url,
                'status' => ImportSession::STATUS_PENDING,
                'mode' => 'full', // Use full mode to save property details to database
            ]);

            // Link schedule to session
            $schedule->update([
                'status' => Schedule::STATUS_IMPORTING,
                'import_session_id' => $importSession->id,
                'started_at' => now(),
            ]);

            // Auto-start queue worker if not running
            $this->startQueueWorkerIfNeeded();

            // Run MasterImportJob synchronously for reliability
            MasterImportJob::dispatchSync($importSession, $schedule->url, $schedule->saved_search_id, 'full');

            return response()->json([
                'success' => true,
                'message' => 'Import queued successfully',
                'session_id' => $importSession->id,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to start queued import for schedule #{$id}: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get current status of all schedules
     */
    public function getStatus()
    {
        $schedules = Schedule::orderBy('created_at', 'desc')->get()->map(function ($schedule) {
            $session = $schedule->importSession;
            // Start checking for completion
            $details = $session ? ($session->split_details ?? []) : [];
            $pendingChunks = $details['pending_chunks'] ?? [];
            $hasPendingChunks = count($pendingChunks) > 0;

            // Check if session is actually finished
            // CRITICAL: total_jobs MUST be > 0
            // AND there must be NO pending chunks left
            $isSessionFinished = $session && 
                $session->total_jobs > 0 && 
                ($session->completed_jobs + $session->failed_jobs >= $session->total_jobs) &&
                !$hasPendingChunks; // Safety check: verify Queue is empty
            
            // If session is finished, update schedule status to completed
            if ($isSessionFinished && $schedule->status === Schedule::STATUS_IMPORTING) {
                $schedule->update(['status' => Schedule::STATUS_COMPLETED, 'property_import_completed' => true]);
                $session->markCompleted(); // Ensure session is also marked completed
            }

            $progress = $session ? $session->getProgressPercentage() : $schedule->getProgressPercentage();
            
            return [
                'id' => $schedule->id,
                'name' => $schedule->name,
                'status' => $schedule->status,
                'status_label' => $schedule->getStatusLabel(),
                'status_color' => $schedule->getStatusColor(),
                'total_properties' => $session ? $session->total_properties : $schedule->total_properties,
                'imported_count' => $session ? $session->imported_properties : $schedule->imported_count,
                'progress_percentage' => $progress,
                'elapsed_time' => $schedule->getElapsedTime(),
                'error_message' => $schedule->error_message,
                'mode' => $session ? $session->mode : null,
                'is_queued' => $session !== null,
            ];
        });

        $importing = Schedule::where('status', Schedule::STATUS_IMPORTING)->first();
        $pendingCount = Schedule::where('status', Schedule::STATUS_PENDING)->count();

        return response()->json([
            'success' => true,
            'schedules' => $schedules,
            'is_processing' => $importing !== null,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Process the next chunk of data for the current or next pending schedule
     * This is called repeatedly by the browser via AJAX
     * Processes 2 pages per request to avoid timeouts
     */
    public function processChunk()
    {
        // Prevent timeout during heavy processing
        // Allow up to 1 hour for large synchronous imports
        set_time_limit(3600);
        
        // Ensure script continues running even if browser/client disconnects
        // This is CRITICAL for the synchronous import strategy
        ignore_user_abort(true);
        
        Log::emergency("=== DEBUG: processChunk called at " . now()->toDateTimeString() . " ===");
        try {
            // Find currently importing schedule or get next pending
            $schedule = Schedule::where('status', Schedule::STATUS_IMPORTING)
                ->orderBy('id', 'asc')
                ->first();
            
            // If no active import, check for pending
            if (!$schedule) {
                $schedule = Schedule::where('status', Schedule::STATUS_PENDING)
                    ->orderBy('id', 'asc')
                    ->first();
                
                if (!$schedule) {
                    return response()->json([
                        'success' => true,
                        'done' => true,
                        'message' => 'No pending schedules to process'
                    ]);
                }
                
                // Start this schedule
                Log::info("Starting schedule #{$schedule->id}: {$schedule->name}");
                
                $importSession = ImportSession::create([
                    'saved_search_id' => $schedule->saved_search_id,
                    'base_url' => $schedule->url,
                    'status' => ImportSession::STATUS_PENDING,
                    'mode' => 'full',
                    'total_jobs' => 0, // Explicitly init to 0 so increment works works reliably
                ]);

                $schedule->update([
                    'status' => Schedule::STATUS_IMPORTING,
                    'import_session_id' => $importSession->id,
                    'started_at' => now(),
                ]);
            }

            // Get the session (either just created or existing)
            $importSession = $schedule->importSession;
            if (!$importSession) {
                 // Should not happen, but safe fallback
                 $schedule->update(['status' => Schedule::STATUS_FAILED]);
                 return response()->json(['success' => false, 'message' => 'Missing session']);
            }

            // ============================================================
            // CORE IMPORT LOOP: PLAN OR EXECUTE CHUNK
            // ============================================================
            try {
                // STEP 1: PLANNING PHASE
                // If no jobs have been planned yet, run the MasterImportJob to generate the plan
                // Use empty() to catch both 0 and NULL (if DB default is null)
                Log::info("ProcessChunk Check: Session {$importSession->id} Total Jobs: " . ($importSession->total_jobs ?? 'NULL'));
                
                if (empty($importSession->total_jobs)) {
                    Log::info("DISPATCH DEBUG: Planning import for session {$importSession->id}...");
                    
                    try {
                        // Run Planner Synchronously
                        MasterImportJob::dispatchSync($importSession, $schedule->url, $schedule->saved_search_id, 'full');
                        Log::info("DISPATCH DEBUG: MasterImportJob finished execution.");
                    } catch (\Exception $e) {
                         Log::error("DISPATCH DEBUG: MasterImportJob CRASHED: " . $e->getMessage());
                    }
                    
                    // Refresh session to get the planned jobs
                    $importSession->refresh();
                    Log::info("DISPATCH DEBUG: After dispatch, Total Jobs: " . $importSession->total_jobs);
                    
                    if (empty($importSession->total_jobs)) {
                         // Safefuard: If planning returned 0 jobs (empty search or error), mark complete
                         // But if it's 0 because of error, we get stuck loop.
                         // Let's Log warning.
                         Log::warning("DISPATCH DEBUG: Total Jobs is STILL 0 after planner. Assuming failure or empty.");
                         
                         $importSession->markCompleted();
                         $schedule->update(['status' => Schedule::STATUS_COMPLETED]);
                         return response()->json([
                             'success' => true, 
                             'done' => true, 
                             'message' => 'Completed (No properties found during planning)'
                         ]);
                    }
                    
                    // Initial planning success
                    return response()->json([
                        'success' => true,
                        'done' => false,
                        'schedule_name' => $schedule->name,
                        'progress_percentage' => 0,
                        'message' => "Planning queued. {$importSession->total_jobs} chunks generated."
                    ]);
                }
                
                // STEP 2: MONITOR PROGRESS (QUEUE MODE)
                // The MasterImportJob has already dispatched all chunks to the Queue.
                // We just need to monitor the ImportSession progress here.
                
                $total = $importSession->total_jobs;
                $completed = $importSession->completed_jobs;
                $failed = $importSession->failed_jobs;
                $processed = $completed + $failed;
                
                $percent = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
                
                // Monitor Queue Size
                $queueSize = \Illuminate\Support\Facades\DB::table('jobs')->count();
                Log::info("POLLING: Session {$importSession->id} - Progress: {$percent}% ({$processed}/{$total}). Queue Size: {$queueSize}");
                
                if ($processed < $total) {
                    return response()->json([
                        'success' => true,
                        'done' => false,
                        'schedule_name' => $schedule->name,
                        'progress_percentage' => $percent,
                        'message' => "Processing in background... {$processed}/{$total} chunks done" . ($queueSize > 0 ? " (queue: {$queueSize})" : ""),
                        'debug_queue_size' => $queueSize
                    ]);
                } else {
                    // All jobs finished for THIS schedule
                    $importSession->markCompleted();
                    $schedule->update(['status' => Schedule::STATUS_COMPLETED, 'property_import_completed' => true]);
                    
                    Log::info("Schedule #{$schedule->id} ({$schedule->name}) completed successfully.");
                    
                    // CHECK FOR MORE PENDING SCHEDULES
                    // If there are more pending schedules, return done: false so polling continues
                    // This enables automatic sequential processing of all schedules
                    $pendingCount = Schedule::where('status', Schedule::STATUS_PENDING)->count();
                    
                    if ($pendingCount > 0) {
                        Log::info("Found {$pendingCount} more pending schedules. Continuing sequential processing...");
                        
                        return response()->json([
                            'success' => true,
                            'done' => false, // Keep polling to auto-start next schedule
                            'schedule_name' => $schedule->name,
                            'progress_percentage' => 100,
                            'message' => "Completed '{$schedule->name}'. Starting next schedule... ({$pendingCount} remaining)"
                        ]);
                    }
                    
                    // No more pending schedules - all done!
                    Log::info("All schedules completed. No more pending schedules.");
                    
                    return response()->json([
                        'success' => true,
                        'done' => true,
                        'schedule_name' => $schedule->name,
                        'progress_percentage' => 100,
                        'message' => "All imports completed successfully!"
                    ]);
                }

            } catch (\Throwable $e) {
                Log::error("DISPATCH ERROR: Import failed: " . $e->getMessage());
                Log::error($e->getTraceAsString());
                
                // Don't fail immediately on transient errors, but for now safe to fail
                $importSession->markFailed("Error: " . $e->getMessage());
                
                return response()->json([
                    'success' => false,
                    'message' => "Import error: " . $e->getMessage()
                ], 500);
            }
            
            // Legacy code removed - "Plan & Execute" logic handles everything now.
            return response()->json([
                'success' => true, 
                'done' => true, 
                'message' => 'No actions pending'
            ]);

        } catch (\Throwable $e) {
            Log::error("Schedule processing error: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            if (isset($schedule) && $schedule) {
                try {
                    $schedule->markAsFailed($e->getMessage());
                } catch (\Throwable $ex) {
                    // Ignore fail on marking fail
                }
            }

            return response()->json([
                'success' => false,
                'done' => false,
                'error' => $e->getMessage(),
                'message' => 'Error: ' . $e->getMessage()
            ], 200); // Return 200 OK even on error so frontend can catch and display message
        }
    }

    /**
     * Scrape one page of property URLs using RightmoveScraperService
     */
    private function scrapeOnePage(string $url): array
    {
        try {
            Log::info("Scraping page: {$url}");
            
            // Use RightmoveScraperService for better anti-bot handling
            $html = $this->scraperService->fetchWithRetry($url);
            
            if (empty($html)) {
                Log::error("Failed to fetch page: {$url}");
                return [];
            }

            $urls = [];

            // Use the scraper service to parse JSON
            $json = $this->scraperService->parseJsonData($html);
            
            if (!$json) {
                Log::error("Failed to parse PAGE_MODEL JSON from page: {$url}");
                return [];
            }
            
            // Try multiple locations for properties
            $properties = [];
            
            $paths = [
                ['properties'],
                ['propertySearch', 'properties'],
                ['searchResults', 'properties'],
                ['searchResult', 'properties'],
            ];

            foreach ($paths as $path) {
                $current = $json;
                foreach ($path as $key) {
                    if (isset($current[$key])) {
                        $current = $current[$key];
                    } else {
                        $current = null;
                        break;
                    }
                }
                if (is_array($current) && !empty($current)) {
                    $properties = $current;
                    Log::info("Found properties in " . implode('.', $path) . ": " . count($properties));
                    break;
                }
            }
            
            if (empty($properties)) {
                $keys = is_array($json) ? array_keys($json) : [];
                Log::warning("Could not find properties. Top-level keys: " . implode(', ', $keys));
                $properties = $this->findPropertiesRecursively($json);
                Log::info("Found properties via recursive search: " . count($properties));
            }

            foreach ($properties as $prop) {
                $propId = $prop['id'] ?? $prop['propertyId'] ?? null;
                $propUrl = $prop['propertyUrl'] ?? $prop['detailUrl'] ?? null;
                
                if ($propId) {
                    $fullUrl = $propUrl;
                    if ($propUrl && !str_starts_with($propUrl, 'http')) {
                        $fullUrl = 'https://www.rightmove.co.uk' . $propUrl;
                    }
                    if (!$fullUrl && $propId) {
                        $fullUrl = 'https://www.rightmove.co.uk/properties/' . $propId;
                    }
                    
                    $urls[] = [
                        'id' => $propId,
                        'url' => $fullUrl,
                    ];
                }
            }
            
            Log::info("Extracted " . count($urls) . " property URLs from page");

            return $urls;

        } catch (\Exception $e) {
            Log::error("Error scraping page: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Recursively find properties array in JSON structure
     */
    private function findPropertiesRecursively(array $data, int $depth = 0): array
    {
        if ($depth > 5) return []; // Max depth to prevent infinite loops
        
        foreach ($data as $key => $value) {
            if ($key === 'properties' && is_array($value) && !empty($value)) {
                // Check if this looks like a property array (has id or propertyId in first item)
                $firstItem = reset($value);
                if (is_array($firstItem) && (isset($firstItem['id']) || isset($firstItem['propertyId']))) {
                    return $value;
                }
            }
            
            if (is_array($value)) {
                $result = $this->findPropertiesRecursively($value, $depth + 1);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
        
        return [];
    }

    /**
     * Save property to database
     */
    private function saveProperty(array $property, ?int $filterId = null): void
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
                'filter_id' => $filterId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Save images
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

    /**
     * Check if a queue worker process is already running
     * Returns false on shared hosting where shell functions are disabled
     */
    private function isQueueWorkerRunning(): bool
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Use wmic to check command line arguments (more reliable than tasklist)
                if (!function_exists('shell_exec') || !$this->isShellFunctionEnabled('shell_exec')) {
                    Log::debug("shell_exec disabled, assuming no queue worker running (cron-based system)");
                    return false;
                }
                // WMIC command to get command line of all php.exe processes
                $output = @shell_exec('wmic process where "name=\'php.exe\'" get commandline 2>&1');
                
                // Check if any line contains "queue:work"
                return $output && (strpos($output, 'artisan queue:work') !== false || strpos($output, 'artisan  queue:work') !== false);
            } else {
                // Unix/Linux - check if exec is available
                if (!function_exists('exec') || !$this->isShellFunctionEnabled('exec')) {
                    Log::debug("exec disabled, assuming no queue worker running (cron-based system)");
                    return false;
                }
                @exec('pgrep -f "artisan queue:work"', $output, $returnVar);
                return $returnVar === 0;
            }
        } catch (\Exception $e) {
            Log::warning("Error checking queue worker status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Start the queue worker as a background process if not already running
     * On shared hosting (cPanel), shell functions may be disabled - queue runs via cron instead
     */
    private function startQueueWorkerIfNeeded(): bool
    {
        // Wrap entire logic in try-catch to prevent controller crashes
        try {
            if ($this->isQueueWorkerRunning()) {
                Log::debug("Queue worker is already running, skipping startup.");
                return false;
            }
            
            $projectPath = base_path();
            $logFile = storage_path('logs/queue-worker.log');
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Use 'start /B' to run in background without new window
                if (!function_exists('popen') || !$this->isShellFunctionEnabled('popen')) {
                    Log::info("popen disabled on this server. Queue worker must run via cron.");
                    return false;
                }
                
                $phpBinary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
                
                // use start /B with a title "QueueWorker" to correctly handle quoted paths
                $command = "cd /d \"{$projectPath}\" && start /B \"QueueWorker\" \"{$phpBinary}\" artisan queue:work --queue=high,imports,default --tries=3 --timeout=120 >> \"{$logFile}\" 2>&1";
                
                @pclose(@popen($command, 'r'));
                Log::info("Started prioritized queue worker in background (Windows) using: $phpBinary");
            } else {
                // Unix/Linux: Use nohup with proper background handling
                if (!function_exists('exec') || !$this->isShellFunctionEnabled('exec')) {
                    Log::info("exec disabled on this server. Queue worker must run via cron.");
                    return false;
                }
                $command = "cd \"{$projectPath}\" && nohup php artisan queue:work --queue=high,imports,default --tries=3 --timeout=120 >> \"{$logFile}\" 2>&1 &";
                @exec($command);
                Log::info("Started prioritized queue worker in background (Unix).");
            }
            
            // Give worker a moment to spin up
            usleep(100000); // 100ms
            
            return true;
        } catch (\Throwable $e) {
            // Catch ALL errors including fatal ones to prevent 500 responses
            Log::error("Failed to start queue worker: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a shell function is enabled (not in disabled_functions)
     */
    private function isShellFunctionEnabled(string $functionName): bool
    {
        $disabled = explode(',', ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
        return !in_array($functionName, $disabled);
    }

    /**
     * Test scraping directly without jobs - for diagnosing import issues
     * Access via: /schedules/test-scrape?url=YOUR_RIGHTMOVE_URL
     */
    public function testScrape(Request $request)
    {
        $url = $request->input('url');
        
        if (!$url) {
            // Use a default Manchester URL for testing
            $url = 'https://www.rightmove.co.uk/property-for-sale/find.html?locationIdentifier=REGION^87490&sortType=2';
        }
        
        // Decode URL-encoded characters (especially ^ which is %5E)
        $url = urldecode($url);
        
        $result = [
            'test_url' => $url,
            'timestamp' => now()->toDateTimeString(),
        ];
        
        try {
            // Step 1: Test probeResultCount
            Log::info("=== TEST SCRAPE: Starting diagnostic for URL: {$url} ===");
            
            $startTime = microtime(true);
            $probeCount = $this->scraperService->probeResultCount($url);
            $probeTime = round(microtime(true) - $startTime, 2);
            
            $result['probe'] = [
                'count' => $probeCount,
                'time_seconds' => $probeTime,
            ];
            
            // Step 2: Test fetchWithRetry to get raw HTML
            $startTime = microtime(true);
            $html = $this->scraperService->fetchWithRetry($url);
            $fetchTime = round(microtime(true) - $startTime, 2);
            
            $htmlLen = strlen($html);
            $hasPageModel = strpos($html, 'PAGE_MODEL') !== false;
            
            // Check for blocking
            $blockingIndicators = [];
            if (stripos($html, 'captcha') !== false) $blockingIndicators[] = 'captcha';
            if (stripos($html, 'challenge') !== false) $blockingIndicators[] = 'challenge';
            if (stripos($html, 'cloudflare') !== false) $blockingIndicators[] = 'cloudflare';
            if (stripos($html, 'blocked') !== false) $blockingIndicators[] = 'blocked';
            if (stripos($html, 'rate limit') !== false) $blockingIndicators[] = 'rate_limit';
            
            $result['fetch'] = [
                'html_size_bytes' => $htmlLen,
                'time_seconds' => $fetchTime,
                'has_page_model' => $hasPageModel,
                'is_blocked' => !empty($blockingIndicators),
                'blocking_indicators' => $blockingIndicators,
            ];
            
            // If HTML is small, include sample
            if ($htmlLen < 5000) {
                $result['fetch']['html_sample'] = substr($html, 0, 2000);
            }
            
            // Step 3: Test scrapePropertyUrls (just page 0)
            $startTime = microtime(true);
            $urls = $this->scraperService->scrapePropertyUrls($url, 0, 0);
            $scrapeTime = round(microtime(true) - $startTime, 2);
            
            $result['scrape'] = [
                'properties_found' => count($urls),
                'time_seconds' => $scrapeTime,
                'sample_properties' => array_slice($urls, 0, 5), // First 5 properties
            ];
            
            // Parse PAGE_MODEL if available
            if ($hasPageModel) {
                $json = $this->scraperService->parseJsonData($html);
                if ($json) {
                    $result['page_model'] = [
                        'top_level_keys' => array_keys($json),
                        'has_properties' => isset($json['properties']),
                        'has_pagination' => isset($json['pagination']),
                        'result_count' => $json['resultCount'] ?? $json['pagination']['total'] ?? null,
                    ];
                    
                    // Check all possible property paths
                    $paths = [
                        'properties' => isset($json['properties']) ? count($json['properties']) : 0,
                        'searchResult.properties' => isset($json['searchResult']['properties']) ? count($json['searchResult']['properties']) : 0,
                        'propertySearch.properties' => isset($json['propertySearch']['properties']) ? count($json['propertySearch']['properties']) : 0,
                    ];
                    $result['page_model']['property_paths'] = $paths;
                }
            }
            
            // Overall diagnosis
            if ($probeCount > 0 && count($urls) > 0) {
                $result['diagnosis'] = 'SUCCESS - Scraping is working correctly';
                $result['status'] = 'ok';
            } elseif ($probeCount === 0 && $htmlLen < 5000) {
                $result['diagnosis'] = 'BLOCKED - Rightmove is likely blocking requests (small response, no PAGE_MODEL)';
                $result['status'] = 'blocked';
            } elseif ($probeCount === 0 && !$hasPageModel) {
                $result['diagnosis'] = 'BLOCKED - No PAGE_MODEL in response. Getting error page or CAPTCHA';
                $result['status'] = 'blocked';
            } elseif ($probeCount === 0 && $hasPageModel) {
                $result['diagnosis'] = 'PARSE ERROR - PAGE_MODEL exists but count extraction failed';
                $result['status'] = 'parse_error';
            } elseif ($probeCount > 0 && count($urls) === 0) {
                $result['diagnosis'] = 'SCRAPE ERROR - Probe found properties but URL scraping returned 0';
                $result['status'] = 'scrape_error';
            } else {
                $result['diagnosis'] = 'UNKNOWN - Unexpected state';
                $result['status'] = 'unknown';
            }
            
            Log::info("=== TEST SCRAPE COMPLETE ===", $result);
            
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['trace'] = $e->getTraceAsString();
            $result['diagnosis'] = 'EXCEPTION - ' . $e->getMessage();
            $result['status'] = 'error';
        }
        
        return response()->json($result, 200, [], JSON_PRETTY_PRINT);
    }
}
