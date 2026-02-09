<?php

use App\Http\Controllers\Api\V1\TelemetryController;
use App\Http\Controllers\Api\V1\ThiController;
use App\Http\Controllers\IoT\DashboardController;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// Configure rate limiting per device
RateLimiter::for('device-api', function ($request) {
    $device = $request->get('authenticated_device');
    $key = $device ? 'device:' . $device->id : 'ip:' . $request->ip();
    
    return Limit::perMinute(60)->by($key)->response(function () {
        return response()->json([
            'success' => false,
            'message' => 'Too many requests',
            'data' => null,
            'errors' => ['rate_limit' => 'Rate limit exceeded. Please wait before sending more requests.'],
        ], 429);
    });
});

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
        // GET noise calculations
        Route::get('/noise-calculations', [DashboardController::class, 'getNoiseCalculations']);
        
        // GET daily summary
        Route::get('/daily-summary', [DashboardController::class, 'getDailySummary']);
        
        // GET export daily summary to Excel
        Route::get('/daily-summary/export', [DashboardController::class, 'exportDailySummary']);
        
        // GET real-time noise data
        Route::get('/noise-data/realtime', [DashboardController::class, 'getRealTimeNoiseData']);

        // GET timeout logs
        Route::get('/timeout-logs', [DashboardController::class, 'getTimeoutLogs']);

        // GET export noise data to Excel
        Route::get('/noise-data/export', [DashboardController::class, 'exportNoiseData']);

        // GET THI data
        Route::get('/thi', [ThiController::class, 'getThiByDate']);
    });
});
