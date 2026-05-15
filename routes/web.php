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
        return redirect('/iot');
    })->name('dashboard');

    // Admin Routes
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\AdminDataController::class, 'index'])->name('dashboard');
        Route::post('/recalculate-noise-period', [\App\Http\Controllers\Admin\AdminDataController::class, 'recalculateNoisePeriod'])->name('recalculate.noise.period');
        Route::post('/recalculate-daily-summary', [\App\Http\Controllers\Admin\AdminDataController::class, 'recalculateDailySummary'])->name('recalculate.daily');
        Route::delete('/telemetry/{id}', [\App\Http\Controllers\Admin\AdminDataController::class, 'deleteTelemetry'])->name('delete.telemetry');
        Route::delete('/noise-data/{id}', [\App\Http\Controllers\Admin\AdminDataController::class, 'deleteNoiseData'])->name('delete.noise');
        Route::delete('/daily-summary/{id}', [\App\Http\Controllers\Admin\AdminDataController::class, 'deleteDailySummary'])->name('delete.daily');
        Route::post('/bulk-delete-telemetry', [\App\Http\Controllers\Admin\AdminDataController::class, 'bulkDeleteTelemetry'])->name('bulk.delete.telemetry');
        Route::post('/bulk-delete-noise', [\App\Http\Controllers\Admin\AdminDataController::class, 'bulkDeleteNoiseData'])->name('bulk.delete.noise');
        
        // Noise Data Management
        Route::get('/noise-data/period', [\App\Http\Controllers\Admin\AdminDataController::class, 'getNoiseDataByPeriod'])->name('noise.data.period');
        Route::post('/noise-data/add', [\App\Http\Controllers\Admin\AdminDataController::class, 'addNoiseData'])->name('noise.data.add');
        Route::put('/noise-data/update', [\App\Http\Controllers\Admin\AdminDataController::class, 'updateNoiseData'])->name('noise.data.update');
        Route::delete('/noise-data/delete', [\App\Http\Controllers\Admin\AdminDataController::class, 'deleteSingleNoiseData'])->name('noise.data.delete');

        Route::get('/invalid-data', [\App\Http\Controllers\Admin\InvalidDataController::class, 'index'])->name('invalid.data');
        Route::post('/invalid-data/fix-all', [\App\Http\Controllers\Admin\InvalidDataController::class, 'fixAll'])->name('invalid.data.fix.all');
        Route::get('/invalid-data/preview-cleanup', [\App\Http\Controllers\Admin\InvalidDataController::class, 'previewCleanup'])->name('invalid.data.preview.cleanup');
        Route::post('/invalid-data/cleanup', [\App\Http\Controllers\Admin\InvalidDataController::class, 'cleanupPreDeviceFills'])->name('invalid.data.cleanup');
    });

    // IoT Dashboard Routes
    Route::prefix('iot')->name('iot.')->group(function () {
        Route::get('/', [DashboardController::class, 'monitoring'])->name('dashboard');
        Route::get('/devices', [DashboardController::class, 'index'])->name('devices');
        Route::post('/devices', [DeviceController::class, 'store'])->name('device.store');
        Route::get('/devices/{device}', [DashboardController::class, 'show'])->name('device.show');
        Route::put('/devices/{device}', [DeviceController::class, 'update'])->name('device.update');
        Route::delete('/devices/{device}', [DeviceController::class, 'destroy'])->name('device.destroy');
        Route::post('/devices/{device}/regenerate-key', [DeviceController::class, 'regenerateKey'])->name('device.regenerateKey');
        Route::get('/devices/{device}/log', [DashboardController::class, 'telemetryLog'])->name('device.log');
        
        // Device Telegram Settings
        Route::put('/devices/{device}/telegram', [\App\Http\Controllers\IoT\DeviceTelegramController::class, 'update'])->name('device.telegram.update');
        Route::post('/devices/{device}/telegram/test', [\App\Http\Controllers\IoT\DeviceTelegramController::class, 'test'])->name('device.telegram.test');
        
        // Device WhatsApp Settings
        Route::put('/devices/{device}/whatsapp', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'update'])->name('device.whatsapp.update');
        Route::post('/devices/{device}/whatsapp/add', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'addNumber'])->name('device.whatsapp.add');
        Route::post('/devices/{device}/whatsapp/delete', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'deleteNumber'])->name('device.whatsapp.delete');
        Route::post('/devices/{device}/whatsapp/test', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'test'])->name('device.whatsapp.test');
        Route::post('/devices/{device}/whatsapp/test-number', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'testNumber'])->name('device.whatsapp.test-number');
        Route::post('/devices/{device}/whatsapp/send-tester', [\App\Http\Controllers\IoT\DeviceWhatsAppController::class, 'sendTesterNotification'])->name('device.whatsapp.send-tester');
    });
});

require __DIR__.'/settings.php';
