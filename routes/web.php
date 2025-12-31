<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InternalPropertyController;
use App\Http\Controllers\SavedSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Home Page - Property Listing
    Route::get('/', [InternalPropertyController::class, 'index'])->name('dashboard');
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
});

require __DIR__ . '/auth.php';
