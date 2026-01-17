<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InternalPropertyController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Home Page - Welcome Dashboard
    Route::get('/', function () {
        return view('welcome');
    })->name('dashboard');
    Route::get('/internal-property', [InternalPropertyController::class, 'index'])->name('internal-property.index');
    Route::get('/internal-property/{id}', [InternalPropertyController::class, 'show'])->name('internal-property.show');

    // Internal Properties Routes
    Route::prefix('internal-properties')->name('internal-properties.')->group(function () {
        Route::get('/load', [InternalPropertyController::class, 'loadPropertiesFromDatabase'])->name('load');
        Route::get('/fetch-urls', [InternalPropertyController::class, 'fetchUrls'])->name('fetch-urls');
        Route::post('/sync', [InternalPropertyController::class, 'sync'])->name('sync');
        Route::post('/process-sold', [InternalPropertyController::class, 'processSoldLinks'])->name('process-sold');
        Route::get('/fetch-paginated', [InternalPropertyController::class, 'fetchUrlsPaginated'])->name('fetch-paginated');
        Route::post('/fetch-all', [InternalPropertyController::class, 'fetchAllProperties'])->name('fetch-all');
        Route::post('/import-sold-details', [InternalPropertyController::class, 'importSoldPropertyDetails'])->name('import-sold-details');
        Route::get('/search/{id}', [InternalPropertyController::class, 'show'])->name('search');
        
        // Queue-based import routes (unlimited properties)
        Route::post('/import/start', [InternalPropertyController::class, 'startQueuedImport'])->name('import.start');
        Route::get('/import/progress/{session}', [InternalPropertyController::class, 'getImportProgress'])->name('import.progress');
        Route::post('/import/cancel/{session}', [InternalPropertyController::class, 'cancelImport'])->name('import.cancel');
        Route::get('/import/sessions', [InternalPropertyController::class, 'getImportSessions'])->name('import.sessions');
        Route::post('/import/sold', [InternalPropertyController::class, 'startQueuedSoldImport'])->name('import.sold');
    });

    // Saved Search / Filters Routes
    Route::prefix('searchproperties')->name('searchproperties.')->group(function () {
        Route::get('/', [SavedSearchController::class, 'showPage'])->name('index');
        Route::get('/all', [SavedSearchController::class, 'index'])->name('all');
        Route::get('/version', [SavedSearchController::class, 'version'])->name('version');
        Route::post('/store', [SavedSearchController::class, 'store'])->name('store');
        Route::delete('/{id}', [SavedSearchController::class, 'destroy'])->name('destroy');
        Route::match(['post', 'put'], '/update/{id}', [SavedSearchController::class, 'update'])->name('update');
        Route::get('/areas', [SavedSearchController::class, 'getAreas'])->name('getAreas');
        Route::post('/check-area', [SavedSearchController::class, 'checkArea'])->name('check-area');
        Route::get('/{id}/debug', [SavedSearchController::class, 'debugSearch'])->name('debug');
    });

    // Schedule Routes (for scheduled property imports)
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::post('/', [ScheduleController::class, 'store'])->name('store');
        Route::post('/url', [ScheduleController::class, 'storeUrl'])->name('store-url');
        Route::delete('/{id}', [ScheduleController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/retry', [ScheduleController::class, 'retry'])->name('retry');
        Route::post('/{id}/start-queued', [ScheduleController::class, 'startQueuedImport'])->name('start-queued');
        Route::get('/status', [ScheduleController::class, 'getStatus'])->name('status');
        Route::post('/process', [ScheduleController::class, 'processChunk'])->name('process');
        Route::get('/test-scrape', [ScheduleController::class, 'testScrape'])->name('test-scrape');
    });

    // TEMPORARY: Debug Route to diagnose 500 Error
    Route::get('/fix-db', function() {
        $report = [];
        $report[] = "<strong>System Diagnostic Report</strong>";
        $report[] = "PHP Version: " . phpversion();
        
        // 1. Check DB Connection
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $report[] = "<span style='color:green'>Database Connection: OK</span>";
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>Database Connection: FAILED - " . $e->getMessage() . "</span>";
            return implode("<br>", $report);
        }

        // 2. Check Tables
        try {
            $tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES'); // MySQL specific
            // If empty, try query for sqlite or pgsql if needed, but assuming MySQL based on xampp
            $tableNames = array_map(fn($t) => array_values((array)$t)[0], $tables);
            $report[] = "Tables found (" . count($tables) . "): " . implode(', ', $tableNames);
            
            $requiredTables = ['saved_searches', 'schedules', 'import_sessions'];
            foreach($requiredTables as $req) {
                if(in_array($req, $tableNames)) {
                     $report[] = "<span style='color:green'>Table '$req': Exists</span>";
                } else {
                     $report[] = "<span style='color:red; font-weight:bold'>Table '$req': MISSING</span>";
                }
            }
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>Listing Tables Failed: " . $e->getMessage() . "</span>";
        }

        // 3. Check Models and Queries
        try {
            if (!class_exists(\App\Models\SavedSearch::class)) throw new Exception("Class App\Models\SavedSearch not found");
            $count = \App\Models\SavedSearch::count();
            $report[] = "SavedSearch Count: " . $count;
            
            $items = \App\Models\SavedSearch::latest()->take(5)->get();
            foreach($items as $item) {
                 $report[] = "Search #{$item->id} ({$item->area}):";
                 
                 // Check Schedule Relationship
                 try {
                    $schedule = \App\Models\Schedule::where('saved_search_id', $item->id)->first();
                    if ($schedule) {
                        $report[] = "&nbsp;&nbsp;- Schedule: Found (ID: {$schedule->id}, Status: {$schedule->status})";
                        // Check Import Session Access (Potential crash point)
                        try {
                             $sess = $schedule->importSession;
                             $report[] = "&nbsp;&nbsp;- ImportSession Relationship: " . ($sess ? "Found" : "Null (OK)");
                        } catch(\Exception $e) {
                             $report[] = "&nbsp;&nbsp;- <span style='color:red'>ImportSession Access Failed: " . $e->getMessage() . "</span>";
                        }
                    } else {
                        $report[] = "&nbsp;&nbsp;- Schedule: None";
                    }
                 } catch(\Exception $e) {
                    $report[] = "&nbsp;&nbsp;- <span style='color:red'>Schedule Query Failed: " . $e->getMessage() . "</span>";
                 }
            }
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>SavedSearch Model Query Failed: " . $e->getMessage() . "</span>";
            $report[] = "<pre>" . $e->getTraceAsString() . "</pre>";
        }

        // 4. Run Migrations
        try {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $report[] = "<strong>Migrations Attempted:</strong> " . \Illuminate\Support\Facades\Artisan::output();
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>Migrations Failed: " . $e->getMessage() . "</span>";
        }

        return implode("<br>", $report);
    });

    // TEMPORARY: Simple test to verify file upload (NO database)
    Route::get('/test-api', function() {
        return response()->json([
            'success' => true,
            'message' => 'API is working! Files uploaded correctly.',
            'version' => '2026-01-15-v2',
            'php_version' => phpversion(),
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // TEMPORARY: Clear all caches
    Route::get('/clear-cache', function() {
        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            return 'All caches cleared! <a href="/searchproperties">Go to Saved Searches</a>';
        } catch (\Exception $e) {
            return 'Error clearing cache: ' . $e->getMessage();
        }
    });

    // DEBUG: Check pivot table and saved search property counts
    Route::get('/pivot-debug', function() {
        $report = [];
        $report[] = "<h2>Pivot Table Debug Report</h2>";
        
        try {
            // Get all saved searches with their property counts
            $searches = \App\Models\SavedSearch::withCount('properties')->get();
            
            $report[] = "<h3>Saved Searches & Property Counts:</h3>";
            $report[] = "<table border='1' style='border-collapse: collapse;'>";
            $report[] = "<tr><th>ID</th><th>Area</th><th>Properties Count</th><th>Test Link</th></tr>";
            
            foreach ($searches as $search) {
                $link = "/internal-properties/search/{$search->id}";
                $report[] = "<tr>";
                $report[] = "<td>{$search->id}</td>";
                $report[] = "<td>{$search->area}</td>";
                $report[] = "<td><strong>{$search->properties_count}</strong></td>";
                $report[] = "<td><a href='{$link}'>View Properties</a></td>";
                $report[] = "</tr>";
            }
            $report[] = "</table>";
            
            // Total properties in DB
            $totalProps = \App\Models\Property::count();
            $report[] = "<p>Total Properties in Database: <strong>{$totalProps}</strong></p>";
            
            // Pivot table summary
            $pivotCount = \DB::table('property_saved_search')->count();
            $report[] = "<p>Total Pivot Table Entries: <strong>{$pivotCount}</strong></p>";
            
        } catch (\Exception $e) {
            $report[] = "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
        }
        
        return implode("\n", $report);
    });

    // TEMPORARY: Queue Diagnostic - check why jobs aren't processing
    Route::get('/queue-debug', function() {
        $report = [];
        $report[] = "<h2>Queue Diagnostic Report</h2>";
        $report[] = "Time: " . now()->toDateTimeString();
        
        // 1. Check Queue Connection Setting
        $queueConnection = config('queue.default');
        $report[] = "<strong>QUEUE_CONNECTION:</strong> " . $queueConnection;
        
        if ($queueConnection !== 'database') {
            $report[] = "<span style='color:red; font-weight:bold'>⚠ WARNING: Queue is NOT set to 'database'. Jobs won't process!</span>";
            $report[] = "Fix: Add QUEUE_CONNECTION=database to your .env file";
        } else {
            $report[] = "<span style='color:green'>✓ Queue connection is correct</span>";
        }
        
        // 2. Check Jobs Table
        try {
            $pendingJobs = \DB::table('jobs')->count();
            $failedJobs = \DB::table('failed_jobs')->count();
            $report[] = "<strong>Pending Jobs:</strong> " . $pendingJobs;
            $report[] = "<strong>Failed Jobs:</strong> " . $failedJobs;
            
            if ($pendingJobs > 0) {
                $firstJob = \DB::table('jobs')->orderBy('id')->first();
                $report[] = "<strong>First Pending Job:</strong>";
                $report[] = "&nbsp;&nbsp;Queue: " . $firstJob->queue;
                $report[] = "&nbsp;&nbsp;Attempts: " . $firstJob->attempts;
                $payload = json_decode($firstJob->payload, true);
                $report[] = "&nbsp;&nbsp;Job Class: " . ($payload['displayName'] ?? 'Unknown');
            }
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>Jobs table error: " . $e->getMessage() . "</span>";
        }
        
        // 3. Check Schedule status
        try {
            $schedules = \App\Models\Schedule::all();
            $report[] = "<strong>Schedules:</strong> " . $schedules->count();
            foreach ($schedules as $s) {
                $statusLabel = ['Pending', 'Importing', 'Completed', 'Failed'][$s->status] ?? 'Unknown';
                $report[] = "&nbsp;&nbsp;#{$s->id} {$s->name}: {$statusLabel}";
            }
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>Schedule table error: " . $e->getMessage() . "</span>";
        }
        
        // 4. Test processing ONE job manually
        $report[] = "<hr><strong>Manual Job Processing Test:</strong>";
        try {
            \Illuminate\Support\Facades\Artisan::call('queue:work', [
                '--queue' => 'high,imports,default',
                '--once' => true,
                '--timeout' => 60,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            $report[] = "<pre>" . htmlspecialchars($output) . "</pre>";
            $report[] = "<span style='color:green'>✓ queue:work executed successfully</span>";
        } catch (\Exception $e) {
            $report[] = "<span style='color:red'>queue:work FAILED: " . $e->getMessage() . "</span>";
        }
        
        return implode("<br>", $report);
    });
});

require __DIR__ . '/auth.php';
