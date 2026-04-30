<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('device-api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Aggressive rate limiting for realtime endpoints
        \Illuminate\Support\Facades\RateLimiter::for('realtime-api', function (\Illuminate\Http\Request $request) {
            $key = 'realtime:' . $request->ip() . ':' . $request->input('device_id', 'unknown');
            
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(1000) // Increased from 10 to 1000
                ->by($key)
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.',
                        'data' => null,
                        'errors' => ['rate_limit' => 'Rate limit exceeded.'],
                    ], 429);
                });
        });
    }
}
