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
        Route::post('/store', [SavedSearchController::class, 'store'])->name('store');
        Route::delete('/{id}', [SavedSearchController::class, 'destroy'])->name('destroy');
        Route::match(['post', 'put'], '/update/{id}', [SavedSearchController::class, 'update'])->name('update');
        Route::get('/areas', [SavedSearchController::class, 'getAreas'])->name('getAreas');
        Route::post('/check-area', [SavedSearchController::class, 'checkArea'])->name('check-area');
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
    });
});

require __DIR__ . '/auth.php';
