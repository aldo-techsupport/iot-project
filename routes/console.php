<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// Check for device timeouts every minute
Schedule::command('iot:check-timeouts')->everyMinute();

// Calculate noise periods every 15 minutes
Schedule::command('noise:calculate-periods')->everyFifteenMinutes();

// Recalculate all devices for each period, 1 minute after period ends
// L1 ends at 09:10, trigger at 09:11
Schedule::command('iot:getall --period=L1 --force')
    ->dailyAt('09:11')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L1.log'));

// L2 ends at 11:10, trigger at 11:11
Schedule::command('iot:getall --period=L2 --force')
    ->dailyAt('11:11')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L2.log'));

// L3 ends at 14:10, trigger at 14:11
Schedule::command('iot:getall --period=L3 --force')
    ->dailyAt('14:11')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L3.log'));

// L4 ends at 16:10, trigger at 16:11
Schedule::command('iot:getall --period=L4 --force')
    ->dailyAt('16:11')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L4.log'));
