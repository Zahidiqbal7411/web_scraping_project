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
     * Resets all schedules and clears all property data
     */
    public function index()
    {
        try {
            Log::info("Schedule page accessed: Triggering full reset and cleanup.");

            // 1. Clear all property-related tables
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('properties')->truncate();
            DB::table('property_images')->truncate();
            DB::table('properties_sold')->truncate();
            DB::table('properties_sold_prices')->truncate();
            DB::table('urls')->truncate();
            DB::table('import_sessions')->truncate();
            
            // Also delete any failed jobs if they exist to start fresh
            DB::table('jobs')->truncate();
            DB::table('failed_jobs')->truncate();
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // 2. Reset all schedules to pending status
            Schedule::query()->update([
                'status' => Schedule::STATUS_PENDING,
                'total_properties' => 0,
                'imported_count' => 0,
                'current_page' => 0,
                'total_pages' => 0,
                'import_session_id' => null,
                'url_import_completed' => false,
                'property_import_completed' => false,
                'sold_import_completed' => false,
                'error_message' => null,
                'started_at' => null,
                'completed_at' => null,
            ]);

            // 3. Auto-schedule all saved searches that don't have pending/importing schedules
            $savedSearches = SavedSearch::orderBy('area')->get();
            
            foreach ($savedSearches as $search) {
                // Since we just reset everything, we just need to ensure all active searches have a schedule record
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
            
            Log::info("Full reset and cleanup completed.");

        } catch (\Exception $e) {
            Log::error("Failed to reset schedules and clear data: " . $e->getMessage());
        }

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
                'mode' => 'urls_only', // Use the two-pass approach as requested
            ]);

            // Link schedule to session
            $schedule->update([
                'status' => Schedule::STATUS_IMPORTING,
                'import_session_id' => $importSession->id,
                'started_at' => now(),
            ]);

            // Auto-start queue worker if not running
            $this->startQueueWorkerIfNeeded();

            // Dispatch the MasterImportJob in 'urls_only' mode
            MasterImportJob::dispatch($importSession, $schedule->url, $schedule->saved_search_id, 'urls_only')
                ->onQueue('imports');

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
        try {
            // Find currently importing schedule or get next pending
            $schedule = Schedule::where('status', Schedule::STATUS_IMPORTING)
                ->orderBy('id', 'asc')
                ->first();
            
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
                
                // Start this schedule via Queue
                Log::info("Schedule #{$schedule->id}: Triggering queued import for {$schedule->name}");
                
                $importSession = ImportSession::create([
                    'saved_search_id' => $schedule->saved_search_id,
                    'base_url' => $schedule->url,
                    'status' => ImportSession::STATUS_PENDING,
                    'mode' => 'urls_only',
                ]);

                $schedule->update([
                    'status' => Schedule::STATUS_IMPORTING,
                    'import_session_id' => $importSession->id,
                    'started_at' => now(),
                ]);

                // Auto-start queue worker if not running
                $this->startQueueWorkerIfNeeded();

                MasterImportJob::dispatch($importSession, $schedule->url, $schedule->saved_search_id, 'urls_only')
                    ->onQueue('imports');

                return response()->json([
                    'success' => true,
                    'done' => false,
                    'schedule_name' => $schedule->name,
                    'progress_percentage' => 0,
                    'message' => "Started queued import for {$schedule->name}"
                ]);
            }

            // If already importing, just return progress
            $session = $schedule->importSession;

            // If no worker is running, try to start one (non-blocking)
            if ($session && !$this->isQueueWorkerRunning()) {
                Log::info("No queue worker detected for session {$session->id}. Starting worker in background.");
                $this->startQueueWorkerIfNeeded();
                // Refresh session to get latest status
                $session->refresh();
            }

            // FALLBACK: Detect stuck URL phase and trigger fetch details directly
            // This handles the case where URL chunks completed but FetchDetailsFromUrlsJob never ran
            if ($session && $session->mode === 'urls_only' && $session->total_jobs > 0) {
                if ($session->completed_jobs >= $session->total_jobs) {
                    Log::info("Detected completed URLs phase stuck at {$session->completed_jobs}/{$session->total_jobs}. Triggering fetch details phase.");
                    
                    $schedule->markUrlImportComplete();
                    $schedule->update(['status' => Schedule::STATUS_IMPORTING]);
                    
                    $session->update(['mode' => 'fetch_details', 'completed_jobs' => 0, 'total_jobs' => 1]);
                    
                    \App\Jobs\FetchDetailsFromUrlsJob::dispatch($session, $schedule->saved_search_id)
                        ->onQueue('imports');
                    
                    // Try to start worker again to process the dispatched job
                    $this->startQueueWorkerIfNeeded();
                    
                    return response()->json([
                        'success' => true,
                        'done' => false,
                        'schedule_id' => $schedule->id,
                        'schedule_name' => $schedule->name,
                        'progress_percentage' => 0,
                        'message' => "Starting property details fetch..."
                    ]);
                }
            }

            $progress = $session ? $session->getProgressPercentage() : $schedule->getProgressPercentage();
            
            if ($schedule->status === Schedule::STATUS_COMPLETED) {
                return response()->json([
                    'success' => true,
                    'done' => false,
                    'schedule_completed' => true,
                    'schedule_id' => $schedule->id,
                    'schedule_name' => $schedule->name,
                    'imported_count' => $schedule->imported_count,
                    'message' => "Completed: {$schedule->name}"
                ]);
            }

            $progress = $session ? $session->getProgressPercentage() : $schedule->getProgressPercentage();
            // Show the accurate total - use max of completed and total in case more jobs were added dynamically
            $displayTotal = $session ? max($session->completed_jobs, $session->total_jobs) : 0;
            $message = $session ? "Queued processing... ({$session->completed_jobs}/{$displayTotal} chunks)" : "Processing {$schedule->name}...";

            // If master job is still pending or initializing
            if ($session && $session->total_jobs === 0) {
                $message = "Initializing import patterns and price splitting...";
            }

            return response()->json([
                'success' => true,
                'done' => false,
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'progress_percentage' => $progress,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error("Schedule processing error: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            if (isset($schedule)) {
                $schedule->markAsFailed($e->getMessage());
            }

            return response()->json([
                'success' => false,
                'done' => false,
                'error' => $e->getMessage(),
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
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
     */
    private function isQueueWorkerRunning(): bool
    {
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: check for php.exe processes and look for queue:work
                // Use tasklist for better reliability in some environments
                $output = shell_exec('tasklist /v /fi "imagename eq php.exe" /fo csv 2>&1');
                return $output && strpos($output, 'queue:work') !== false;
            } else {
                exec('pgrep -f "artisan queue:work"', $output, $returnVar);
                return $returnVar === 0;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Start the queue worker as a background process if not already running
     */
    private function startQueueWorkerIfNeeded(): bool
    {
        if ($this->isQueueWorkerRunning()) {
            Log::debug("Queue worker is already running, skipping startup.");
            return false;
        }
        
        $projectPath = base_path();
        $logFile = storage_path('logs/queue-worker.log');
        
        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows: Use 'start' with /MIN to run minimized in background
                // Write output to log file for debugging
                $command = "cd /d \"{$projectPath}\" && start /MIN /B php artisan queue:work --queue=imports --tries=3 --timeout=120 >> \"{$logFile}\" 2>&1";
                pclose(popen($command, 'r'));
                Log::info("Started queue worker in background (Windows).");
            } else {
                // Unix/Linux: Use nohup with proper background handling
                $command = "cd \"{$projectPath}\" && nohup php artisan queue:work --queue=imports --tries=3 --timeout=120 >> \"{$logFile}\" 2>&1 &";
                exec($command);
                Log::info("Started queue worker in background (Unix).");
            }
            
            // Give worker a moment to spin up
            usleep(100000); // 100ms
            
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to start queue worker: " . $e->getMessage());
            return false;
        }
    }
}
