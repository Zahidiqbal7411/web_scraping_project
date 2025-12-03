<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScraperController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\InternalPropertyController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard'); // Removed auth & verified middleware

// Profile routes (still require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Scraper routes (public access)
Route::get('/scrape-services', [ScraperController::class, 'scrapeServices']);
Route::get('/scrape-testimonials', [ScraperController::class, 'scrapeTestimonials']);
Route::get('/testimonials', [ScraperController::class, 'showTestimonials']);
Route::get('/api/testimonials', [ScraperController::class, 'getTestimonials']);
Route::post('/sync-testimonials', [ScraperController::class, 'syncTestimonials']);

// Property routes (public access)
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/api/properties', [PropertyController::class, 'getProperties'])->name('properties.get');
Route::post('/api/properties/sync', [PropertyController::class, 'sync'])->name('properties.sync');
Route::get('/api/properties/test', [PropertyController::class, 'test'])->name('properties.test');

// Internal Property routes (public access)
Route::get('/internal-properties', [InternalPropertyController::class, 'index'])->name('internal-property.index');
Route::get('/api/internal-property/fetch-urls', [InternalPropertyController::class, 'fetchUrls'])->name('internal-property.fetch-urls');
Route::get('/api/internal-property/fetch-urls-paginated', [InternalPropertyController::class, 'fetchUrlsPaginated'])->name('internal-property.fetch-urls-paginated');
Route::post('/api/internal-property/fetch-all', [InternalPropertyController::class, 'fetchAllProperties'])->name('internal-property.fetch-all');
Route::post('/api/internal-property/sync', [InternalPropertyController::class, 'sync'])->name('internal-property.sync');

// Auth routes
require __DIR__.'/auth.php';
