<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScraperController;
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

// Auth routes
require __DIR__.'/auth.php';
