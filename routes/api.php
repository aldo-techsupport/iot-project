<?php

use App\Http\Controllers\Api\V1\TelemetryController;
use App\Http\Controllers\Api\V1\ThiController;
use App\Http\Controllers\IoT\DashboardController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware([AuthenticateDevice::class, 'throttle:device-api'])->group(function () {
        // POST telemetry data - generic endpoint (device identified by API key)
        Route::post('/telemetry', [TelemetryController::class, 'store']);
        
        // POST telemetry data - per device endpoint
        Route::post('/devices/{device}/telemetry', [TelemetryController::class, 'storeForDevice']);
        
        // GET latest telemetry for device
        Route::get('/devices/{device}/latest', [TelemetryController::class, 'latest']);
        
        // GET telemetry history
        Route::get('/devices/{device}/history', [TelemetryController::class, 'history']);
    });

    // Device endpoints (Require Device Key)
    Route::prefix('iot')->middleware([AuthenticateDevice::class, 'throttle:device-api'])->group(function () {
        // POST noise data from sensor
        Route::post('/noise-data', [DashboardController::class, 'storeNoiseData']);
        
        // POST trigger calculation
        Route::post('/noise-calculations/trigger', [DashboardController::class, 'triggerCalculation']);
        
        // POST calculate daily summary (Ls and TWA)
        Route::post('/noise-calculations/daily-summary', [DashboardController::class, 'calculateDailySummary']);
    });

    // Dashboard endpoints (Public or Web Auth - for simplicity making them accessible, but in prod should be auth:sanctum)
    Route::prefix('iot')->group(function () {
        // Realtime endpoint with aggressive rate limiting
        Route::middleware('throttle:realtime-api')->group(function () {
            // GET real-time noise data
            Route::get('/noise-data/realtime', [DashboardController::class, 'getRealTimeNoiseData']);
        });

        // Other dashboard endpoints WITHOUT rate limiting
        // GET noise calculations
        Route::get('/noise-calculations', [DashboardController::class, 'getNoiseCalculations']);
        
        // GET daily summary
        Route::get('/daily-summary', [DashboardController::class, 'getDailySummary']);
        
        // GET export daily summary to Excel
        Route::get('/daily-summary/export', [DashboardController::class, 'exportDailySummary']);

        // GET timeout logs
        Route::get('/timeout-logs', [DashboardController::class, 'getTimeoutLogs']);

        // GET export noise data to Excel (NO RATE LIMIT)
        Route::get('/noise-data/export', [DashboardController::class, 'exportNoiseData']);

        // GET THI data
        Route::get('/thi', [ThiController::class, 'getThiByDate']);
    });
});
