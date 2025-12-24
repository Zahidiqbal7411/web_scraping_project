<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InternalPropertyController;
use Illuminate\Support\Facades\Route;

// Public routes - redirect to dashboard (which requires login)
Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth'])->name('dashboard');

// Profile routes (still require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Internal Property routes (require authentication)
Route::middleware(['auth'])->group(function () {
    Route::get('/internal-properties', [InternalPropertyController::class, 'index'])->name('internal-property.index');
    Route::get('/internal-properties/search/{id}', [InternalPropertyController::class, 'show'])->name('internal-property.show');
    Route::get('/api/internal-property/load-from-db', [InternalPropertyController::class, 'loadPropertiesFromDatabase'])->name('internal-property.load-from-db');
    Route::get('/api/internal-property/fetch-urls', [InternalPropertyController::class, 'fetchUrls'])->name('internal-property.fetch-urls');
    Route::get('/api/internal-property/fetch-urls-paginated', [InternalPropertyController::class, 'fetchUrlsPaginated'])->name('internal-property.fetch-urls-paginated');
    Route::post('/api/internal-property/fetch-all', [InternalPropertyController::class, 'fetchAllProperties'])->name('internal-property.fetch-all');
    Route::post('/api/internal-property/sync', [InternalPropertyController::class, 'sync'])->name('internal-property.sync');
    Route::post('/api/internal-property/process-sold-links', [InternalPropertyController::class, 'processSoldLinks'])->name('internal-property.process-sold-links');
    Route::post('/api/internal-property/fetch-missing-sold-images', [InternalPropertyController::class, 'fetchMissingSoldImages'])->name('internal-property.fetch-missing-sold-images');
});

// Saved Search routes (require authentication)
Route::middleware(['auth'])->group(function () {
    Route::get('/searchproperties', [App\Http\Controllers\SavedSearchController::class, 'showPage'])->name('searchproperties.index');
    Route::get('/api/saved-searches', [App\Http\Controllers\SavedSearchController::class, 'index']);
    Route::post('/api/saved-searches', [App\Http\Controllers\SavedSearchController::class, 'store']);
    Route::put('/api/saved-searches/{id}', [App\Http\Controllers\SavedSearchController::class, 'update']);
    Route::delete('/api/saved-searches/{id}', [App\Http\Controllers\SavedSearchController::class, 'destroy']);

    // Area routes
    Route::get('/api/areas', [App\Http\Controllers\SavedSearchController::class, 'getAreas']);
    Route::post('/api/areas/check', [App\Http\Controllers\SavedSearchController::class, 'checkArea']);
    Route::post('/api/areas/refresh', [App\Http\Controllers\SavedSearchController::class, 'refreshAreas']);
});

// Auth routes
require __DIR__.'/auth.php';
