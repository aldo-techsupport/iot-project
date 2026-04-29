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
// L1: 08:00-09:00, trigger at 09:01
Schedule::command('iot:getall --period=L1 --force')
    ->dailyAt('09:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L1.log'));

// L2: 09:00-10:00, trigger at 10:01
Schedule::command('iot:getall --period=L2 --force')
    ->dailyAt('10:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L2.log'));

// L3: 10:00-11:00, trigger at 11:01
Schedule::command('iot:getall --period=L3 --force')
    ->dailyAt('11:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L3.log'));

// L4 ends at 12:00, trigger at 12:01
Schedule::command('iot:getall --period=L4 --force')
    ->dailyAt('12:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L4.log'));

// L5 ends at 14:00, trigger at 14:01
Schedule::command('iot:getall --period=L5 --force')
    ->dailyAt('14:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L5.log'));

// L6 ends at 15:00, trigger at 15:01
Schedule::command('iot:getall --period=L6 --force')
    ->dailyAt('15:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L6.log'));

// L7 ends at 16:00, trigger at 16:01
Schedule::command('iot:getall --period=L7 --force')
    ->dailyAt('16:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L7.log'));

// L8 ends at 17:00, trigger at 17:01
Schedule::command('iot:getall --period=L8 --force')
    ->dailyAt('17:01')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-L8.log'));

// Calculate Daily Summary (Ls, TWA, DND) after L8 is done
Schedule::command('iot:calculate-daily')
    ->dailyAt('17:05')
    ->timezone('Asia/Jakarta')
    ->appendOutputTo(storage_path('logs/cronjob-daily-summary.log'));

// Telegram Alert System - Check conditions and send alerts every hour at minute 00
Schedule::command('telegram:send-alert')
    ->hourly()
    ->appendOutputTo(storage_path('logs/telegram-alert.log'));

// WhatsApp Alert System - Check conditions and send alerts every hour at minute 00
Schedule::command('whatsapp:send-alert')
    ->hourly()
    ->appendOutputTo(storage_path('logs/whatsapp-alert.log'));

// Clean up old log files daily at 2 AM
Schedule::command('logs:cleanup --days=7 --size=100')
    ->dailyAt('02:00')
    ->name('cleanup-file-logs');

// Clean up old timeout logs from database daily at 2:30 AM
Schedule::command('iot:cleanup-timeout-logs --days=7 --batch=1000')
    ->dailyAt('02:30')
    ->name('cleanup-timeout-logs');
