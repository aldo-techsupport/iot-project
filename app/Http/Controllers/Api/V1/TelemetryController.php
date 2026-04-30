<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\IoT\DashboardController;
use App\Http\Requests\Api\StoreTelemetryRequest;
use App\Models\Device;
use App\Models\NoiseRawData;
use App\Models\NoiseCalculation;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * Store telemetry data from IoT device
     * POST /api/v1/telemetry
     */
    public function store(StoreTelemetryRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->get('authenticated_device');

        return $this->storeTelemetry($device, $request);
    }

    /**
     * Store telemetry data with device ID in URL
     * POST /api/v1/devices/{device}/telemetry
     */
    public function storeForDevice(StoreTelemetryRequest $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        // Verify the device ID matches the authenticated device
        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Device mismatch',
                'data' => null,
                'errors' => ['device' => 'The URL device ID does not match your authenticated device'],
            ], 403);
        }

        return $this->storeTelemetry($authenticatedDevice, $request);
    }
    
    private function storeTelemetry(Device $device, StoreTelemetryRequest $request): JsonResponse
    {
        // Rate Limit Check: 5 Seconds
        $lastTelemetry = $device->telemetries()->latest('measured_at')->first();
        $incomingTime = $request->getMeasuredAt();

        if ($lastTelemetry) {
            // Ensure absolute difference to handle any timezone quirks or parameter ordering
            $diff = abs($incomingTime->diffInSeconds($lastTelemetry->measured_at));
            
            if ($diff < 5) {
                return response()->json([
                    'success' => true,
                    'message' => 'Rate limit exceeded (5s rule). Data ignored.',
                    'data' => null,
                ], 200);
            }
        }

        $telemetry = $device->telemetries()->create([
            'temperature' => $request->input('temperature'),
            'humidity' => $request->input('humidity'),
            'noise_db' => $request->input('noise_db'),
            'measured_at' => $incomingTime,
            'is_filled' => false,
            'fill_method' => 'actual',
        ]);

        $device->updateLastSeen();

        // Real-time alert: Check if conditions meet alert thresholds
        $this->checkAndSendRealtimeAlert($device, $telemetry);

        // Auto-save to noise_raw_data if in monitoring period
        $period = $this->detectPeriod();
        $noiseMonitoring = null;
        
        if ($period !== null && $request->has('noise_db')) {
            // Check for gaps before saving current data
            try {
                $timeoutHandler = app(\App\Services\TimeoutHandlerService::class);
                $timeoutHandler->checkAndFillGaps($device, $period);
            } catch (\Exception $e) {
                \Log::error('Timeout handler failed: ' . $e->getMessage());
            }

            NoiseRawData::create([
                'device_id' => $device->id,
                'period' => $period,
                'noise_level' => $request->input('noise_db'),
                'temperature' => $request->input('temperature'),
                'humidity' => $request->input('humidity'),
                'measured_at' => $telemetry->measured_at,
                // Maps to same fill status as telemetry
                'is_filled' => false,
                'fill_method' => 'actual',
            ]);
            
            // Check if we have 60 data points (1 per minute for 1 hour)
            $count = NoiseRawData::where('device_id', $device->id)
                ->where('period', $period)
                ->whereDate('measured_at', now()->toDateString())
                ->count();
            
            $noiseMonitoring = [
                'period' => $period,
                'count' => $count,
                'target' => 60,
            ];
            
            // Auto-trigger calculation if 60 data points reached
            if ($count >= 60) {
                try {
                    $dashboardController = app(DashboardController::class);
                    $response = $dashboardController->triggerCalculation(
                        $device->id,
                        $period,
                        now()->toDateString()
                    );
                    
                    if ($response->getStatusCode() == 200) {
                        $noiseMonitoring['calculation_triggered'] = true;
                        
                        // Check if all periods complete, trigger daily summary
                        $periodsComplete = NoiseCalculation::where('device_id', $device->id)
                            ->whereDate('calculation_date', now()->toDateString())
                            ->count();
                        
                        if ($periodsComplete >= 8) {
                            try {
                                $dashboardController->calculateDailySummary(
                                    new Request(['device_id' => $device->id])
                                );
                                $noiseMonitoring['daily_summary_triggered'] = true;
                            } catch (\Exception $e) {
                                \Log::error('Daily summary calculation failed: ' . $e->getMessage());
                            }
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Noise calculation trigger failed: ' . $e->getMessage());
                    $noiseMonitoring['calculation_error'] = $e->getMessage();
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Telemetry data stored successfully',
            'data' => [
                'id' => $telemetry->id,
                'device_id' => $device->id,
                'temperature' => $telemetry->temperature,
                'humidity' => $telemetry->humidity,
                'noise_db' => $telemetry->noise_db,
                'measured_at' => $telemetry->measured_at->toIso8601String(),
            ],
            'noise_monitoring' => $noiseMonitoring,
            'errors' => null,
        ], 201);
    }

    /**
     * Detect current monitoring period based on time
     * Returns L1-L8, or null if not in monitoring period
     * Skip 12:00-13:00 (lunch break)
     */
    private function detectPeriod(): ?string
    {
        $hour = now()->hour;
        
        // L1: 08:00-09:00
        if ($hour == 8) return 'L1';
        
        // L2: 09:00-10:00
        if ($hour == 9) return 'L2';
        
        // L3: 10:00-11:00
        if ($hour == 10) return 'L3';
        
        // L4: 11:00-12:00
        if ($hour == 11) return 'L4';
        
        // SKIP: 12:00-13:00 (lunch break)
        
        // L5: 13:00-14:00
        if ($hour == 13) return 'L5';
        
        // L6: 14:00-15:00
        if ($hour == 14) return 'L6';
        
        // L7: 15:00-16:00
        if ($hour == 15) return 'L7';
        
        // L8: 16:00-17:00
        if ($hour == 16) return 'L8';
        
        return null;
    }

    /**
     * Get latest telemetry for a device
     * GET /api/v1/devices/{device}/latest
     */
    public function latest(Request $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        // Device can only access its own data
        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to device data',
                'data' => null,
                'errors' => ['device' => 'You can only access your own device data'],
            ], 403);
        }

        $telemetry = $device->latestTelemetry;

        if (!$telemetry) {
            return response()->json([
                'success' => true,
                'message' => 'No telemetry data found',
                'data' => null,
                'errors' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest telemetry retrieved',
            'data' => [
                'id' => $telemetry->id,
                'temperature' => $telemetry->temperature,
                'humidity' => $telemetry->humidity,
                'noise_db' => $telemetry->noise_db,
                'measured_at' => $telemetry->measured_at->toIso8601String(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * Get telemetry history for a device
     * GET /api/v1/devices/{device}/history?from=...&to=...
     */
    public function history(Request $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to device data',
                'data' => null,
                'errors' => ['device' => 'You can only access your own device data'],
            ], 403);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = $device->telemetries()
            ->inDateRange($request->input('from'), $request->input('to'))
            ->orderBy('measured_at', 'desc');

        $limit = $request->input('limit', 100);
        $telemetries = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'message' => 'Telemetry history retrieved',
            'data' => [
                'device_id' => $device->id,
                'count' => $telemetries->count(),
                'telemetries' => $telemetries->map(fn($t) => [
                    'id' => $t->id,
                    'temperature' => $t->temperature,
                    'humidity' => $t->humidity,
                    'noise_db' => $t->noise_db,
                    'measured_at' => $t->measured_at->toIso8601String(),
                    'is_filled' => (bool)$t->is_filled,
                    'fill_method' => $t->fill_method,
                ]),
            ],
            'errors' => null,
        ]);
    }

    /**
     * Check and send real-time alert if conditions meet thresholds
     * This triggers immediately when device sends data with dangerous levels
     */
    private function checkAndSendRealtimeAlert(Device $device, $telemetry): void
    {
        // Only send real-time alerts if device has Telegram enabled
        if (!$device->telegram_enabled) {
            return;
        }

        $noiseDb = $telemetry->noise_db ?? 0;
        $thi = $telemetry->thi ?? 0;

        // Get current alert type
        $telegram = app(TelegramNotificationService::class);
        $currentAlertType = $telegram->getAlertType($noiseDb, $thi);
        
        // No alert condition met
        if ($currentAlertType === null) {
            return;
        }

        // Check cooldown and alert type change
        $shouldSendAlert = $this->shouldSendAlert($device, $currentAlertType);
        
        if (!$shouldSendAlert) {
            \Log::info('Alert skipped due to cooldown or same alert type', [
                'device' => $device->name,
                'current_type' => $currentAlertType,
                'last_type' => $device->telegram_last_alert_type,
                'last_alert_at' => $device->telegram_last_alert_at?->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        // Send real-time alert
        try {
            $success = $telegram->checkAndSendAlert($device->name, $noiseDb, $thi, $device);
            
            if ($success) {
                // Update tracking
                $device->update([
                    'telegram_last_alert_at' => now(),
                    'telegram_last_alert_type' => $currentAlertType,
                ]);
                
                \Log::info('Real-time alert sent', [
                    'device' => $device->name,
                    'noise_db' => $noiseDb,
                    'thi' => $thi,
                    'alert_type' => $currentAlertType,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Real-time alert failed: ' . $e->getMessage(), [
                'device' => $device->name,
                'noise_db' => $noiseDb,
                'thi' => $thi,
            ]);
        }
    }

    /**
     * Determine if alert should be sent based on cooldown and alert type change
     */
    private function shouldSendAlert(Device $device, int $currentAlertType): bool
    {
        $lastAlertAt = $device->telegram_last_alert_at;
        $lastAlertType = $device->telegram_last_alert_type;
        $cooldownMinutes = $device->telegram_alert_cooldown ?? 5;

        // First time alert - always send
        if ($lastAlertAt === null) {
            return true;
        }

        // Alert type changed - always send (condition changed)
        if ($lastAlertType !== $currentAlertType) {
            return true;
        }

        // Same alert type - check cooldown
        $minutesSinceLastAlert = $lastAlertAt->diffInMinutes(now());
        
        return $minutesSinceLastAlert >= $cooldownMinutes;
    }
}
