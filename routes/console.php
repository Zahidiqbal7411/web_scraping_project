<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue worker for cPanel/shared hosting
// This runs every minute via cron, processes jobs, then verify if schedule logic needs to run
Schedule::command('queue:work --queue=high,imports,default --stop-when-empty --tries=3 --timeout=120')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// New Command: Process Schedules logic (for Cron)
// This hits the same logic as the Schedule Page AJAX calls
Artisan::command('schedules:process', function () {
    $controller = app(\App\Http\Controllers\ScheduleController::class);
    $response = $controller->processChunk();
    
    $data = $response->getData(true);
    $this->info(json_encode($data));
    
})->purpose('Process the next chunk of scheduled imports');

// Run the schedule processor every minute
Schedule::command('schedules:process')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
