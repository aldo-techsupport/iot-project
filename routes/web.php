<?php

use App\Http\Controllers\IoT\DashboardController;
use App\Http\Controllers\IoT\DeviceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // IoT Dashboard Routes
    Route::prefix('iot')->name('iot.')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/devices', [DeviceController::class, 'store'])->name('device.store');
        Route::get('/devices/{device}', [DashboardController::class, 'show'])->name('device.show');
        Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('device.update');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('device.destroy');
        Route::post('/devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])->name('device.regenerateKey');
        Route::get('/devices/{device}/log', [DashboardController::class, 'telemetryLog'])->name('device.log');
    });
});

require __DIR__.'/settings.php';
