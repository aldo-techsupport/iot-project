<?php

use App\Http\Controllers\Api\V1\TelemetryController;
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
});
