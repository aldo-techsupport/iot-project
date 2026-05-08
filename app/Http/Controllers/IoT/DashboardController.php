<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Telemetry;
use App\Models\NoiseRawData;
use App\Models\NoiseCalculation;
use App\Models\NoiseDailySummary;
use App\Services\NoiseStatisticsService;
use App\Services\NoiseDataSelectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $devices = Device::with('latestTelemetry')
            ->orderBy('name')
            ->get()
            ->map(fn($device) => [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'location' => $device->location,
                'description' => $device->description,
                'is_active' => $device->is_active,
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'latest_telemetry' => $device->latestTelemetry ? [
                    'temperature' => $device->latestTelemetry->temperature,
                    'humidity' => $device->latestTelemetry->humidity,
                    'noise_db' => $device->latestTelemetry->noise_db,
                    'measured_at' => $device->latestTelemetry->measured_at->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('iot/dashboard', [
            'devices' => $devices,
            'newDevice' => fn () => session('newDevice'),
            'apiBaseUrl' => request()->getSchemeAndHttpHost() . '/api/v1',
        ]);
    }

    public function monitoring(): Response
    {
        try {
            $date = request()->input('date', now()->toDateString());
            
            Log::info('Monitoring page accessed', ['date' => $date]);
            
            // Get all devices with their latest telemetry
            $devices = Device::with('latestTelemetry')
                ->orderBy('name')
                ->get()
                ->map(function($device) use ($date) {
                    try {
                        // Get calculations for all periods
                        $calculations = NoiseCalculation::where('device_id', $device->id)
                            ->whereDate('calculation_date', $date)
                            ->get()
                            ->keyBy('period');
                        
                        // Get timeout logs for today - OPTIMIZED with date range instead of whereDate
                        $startOfDay = \Carbon\Carbon::parse($date)->startOfDay();
                        $endOfDay = \Carbon\Carbon::parse($date)->endOfDay();
                        $timeoutLogs = \App\Models\NoiseTimeoutLog::where('device_id', $device->id)
                            ->whereBetween('expected_at', [$startOfDay, $endOfDay])
                            ->orderBy('expected_at', 'desc')
                            ->limit(10)
                            ->get();
                        
                        // Get telemetry count for today
                        $telemetryCount = Telemetry::where('device_id', $device->id)
                            ->whereDate('measured_at', $date)
                            ->count();
                        
                        // Get daily summary if exists
                        $dailySummary = NoiseDailySummary::where('device_id', $device->id)
                            ->whereDate('calculation_date', $date)
                            ->first();
                        
                        // Determine device status
                        $lastSeenMinutes = $device->last_seen_at ? 
                            now()->diffInMinutes($device->last_seen_at) : null;
                        
                        $status = 'offline';
                        if ($lastSeenMinutes !== null) {
                            if ($lastSeenMinutes < 2) {
                                $status = 'online';
                            } elseif ($lastSeenMinutes < 10) {
                                $status = 'warning';
                            }
                        }
                        
                        return [
                            'id' => $device->id,
                            'name' => $device->name,
                            'slug' => $device->slug,
                            'location' => $device->location,
                            'description' => $device->description,
                            'is_active' => $device->is_active,
                            'status' => $status,
                            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                            'last_seen_minutes' => $lastSeenMinutes,
                            'latest_telemetry' => $device->latestTelemetry ? [
                                'temperature' => $device->latestTelemetry->temperature,
                                'humidity' => $device->latestTelemetry->humidity,
                                'noise_db' => $device->latestTelemetry->noise_db,
                                'measured_at' => $device->latestTelemetry->measured_at->toIso8601String(),
                            ] : null,
                            'telemetry_count_today' => $telemetryCount,
                            'calculations' => [
                                'L1' => $calculations->get('L1') ? [
                                    'leq_value' => $calculations->get('L1')->leq_value,
                                    'data_count' => $calculations->get('L1')->data_count,
                                    'min_value' => $calculations->get('L1')->min_value,
                                    'max_value' => $calculations->get('L1')->max_value,
                                    'created_at' => $calculations->get('L1')->created_at->toIso8601String(),
                                ] : null,
                                'L2' => $calculations->get('L2') ? [
                                    'leq_value' => $calculations->get('L2')->leq_value,
                                    'data_count' => $calculations->get('L2')->data_count,
                                    'min_value' => $calculations->get('L2')->min_value,
                                    'max_value' => $calculations->get('L2')->max_value,
                                    'created_at' => $calculations->get('L2')->created_at->toIso8601String(),
                                ] : null,
                                'L3' => $calculations->get('L3') ? [
                                    'leq_value' => $calculations->get('L3')->leq_value,
                                    'data_count' => $calculations->get('L3')->data_count,
                                    'min_value' => $calculations->get('L3')->min_value,
                                    'max_value' => $calculations->get('L3')->max_value,
                                    'created_at' => $calculations->get('L3')->created_at->toIso8601String(),
                                ] : null,
                                'L4' => $calculations->get('L4') ? [
                                    'leq_value' => $calculations->get('L4')->leq_value,
                                    'data_count' => $calculations->get('L4')->data_count,
                                    'min_value' => $calculations->get('L4')->min_value,
                                    'max_value' => $calculations->get('L4')->max_value,
                                    'created_at' => $calculations->get('L4')->created_at->toIso8601String(),
                                ] : null,
                                'L5' => $calculations->get('L5') ? [
                                    'leq_value' => $calculations->get('L5')->leq_value,
                                    'data_count' => $calculations->get('L5')->data_count,
                                    'min_value' => $calculations->get('L5')->min_value,
                                    'max_value' => $calculations->get('L5')->max_value,
                                    'created_at' => $calculations->get('L5')->created_at->toIso8601String(),
                                ] : null,
                                'L6' => $calculations->get('L6') ? [
                                    'leq_value' => $calculations->get('L6')->leq_value,
                                    'data_count' => $calculations->get('L6')->data_count,
                                    'min_value' => $calculations->get('L6')->min_value,
                                    'max_value' => $calculations->get('L6')->max_value,
                                    'created_at' => $calculations->get('L6')->created_at->toIso8601String(),
                                ] : null,
                                'L7' => $calculations->get('L7') ? [
                                    'leq_value' => $calculations->get('L7')->leq_value,
                                    'data_count' => $calculations->get('L7')->data_count,
                                    'min_value' => $calculations->get('L7')->min_value,
                                    'max_value' => $calculations->get('L7')->max_value,
                                    'created_at' => $calculations->get('L7')->created_at->toIso8601String(),
                                ] : null,
                                'L8' => $calculations->get('L8') ? [
                                    'leq_value' => $calculations->get('L8')->leq_value,
                                    'data_count' => $calculations->get('L8')->data_count,
                                    'min_value' => $calculations->get('L8')->min_value,
                                    'max_value' => $calculations->get('L8')->max_value,
                                    'created_at' => $calculations->get('L8')->created_at->toIso8601String(),
                                ] : null,
                            ],
                            'daily_summary' => $dailySummary ? [
                                'ls_value' => $dailySummary->ls_value,
                                'twa_value' => $dailySummary->twa_value,
                                'dnd_value' => $dailySummary->dnd_value,
                            ] : null,
                            'timeout_logs' => $timeoutLogs->map(fn($log) => [
                                'expected_at' => $log->expected_at->toIso8601String(),
                                'period' => $log->period,
                                'timeout_seconds' => $log->timeout_seconds,
                                'created_at' => $log->created_at->toIso8601String(),
                            ]),
                        ];
                    } catch (\Exception $e) {
                        Log::error('Error processing device in monitoring', [
                            'device_id' => $device->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        
                        // Return minimal device data on error
                        return [
                            'id' => $device->id,
                            'name' => $device->name,
                            'slug' => $device->slug,
                            'location' => $device->location,
                            'description' => $device->description,
                            'is_active' => $device->is_active,
                            'status' => 'offline',
                            'last_seen_at' => null,
                            'last_seen_minutes' => null,
                            'latest_telemetry' => null,
                            'telemetry_count_today' => 0,
                            'calculations' => [
                                'L1' => null,
                                'L2' => null,
                                'L3' => null,
                                'L4' => null,
                            ],
                            'daily_summary' => null,
                            'timeout_logs' => [],
                        ];
                    }
                });

            Log::info('Rendering monitoring page', ['device_count' => $devices->count()]);

            return Inertia::render('iot/monitoring', [
                'devices' => $devices,
                'selectedDate' => $date,
                'currentDate' => now()->toDateString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in monitoring method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to simple error page or redirect
            abort(500, 'Error loading monitoring page: ' . $e->getMessage());
        }
    }

    public function show(Device $device): Response
    {
        $device->load('latestTelemetry');

        // Get 24-hour chart data
        $chartData = $device->telemetries()
            ->last24Hours()
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(fn($t) => [
                'measured_at' => $t->measured_at->toIso8601String(),
                'temperature' => (float) $t->temperature,
                'humidity' => (float) $t->humidity,
                'noise_db' => (float) $t->noise_db,
            ]);

        return Inertia::render('iot/device-detail', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'location' => $device->location,
                'description' => $device->description,
                'is_active' => $device->is_active,
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'telegram_bot_token' => $device->telegram_bot_token,
                'telegram_chat_id' => $device->telegram_chat_id,
                'telegram_enabled' => $device->telegram_enabled,
                'whatsapp_numbers' => $device->whatsapp_numbers,
                'whatsapp_enabled' => $device->whatsapp_enabled,
                'calculation_method' => $device->calculation_method ?? 'copy_timeout',
                'latest_telemetry' => $device->latestTelemetry ? [
                    'temperature' => $device->latestTelemetry->temperature,
                    'humidity' => $device->latestTelemetry->humidity,
                    'noise_db' => $device->latestTelemetry->noise_db,
                    'measured_at' => $device->latestTelemetry->measured_at->toIso8601String(),
                ] : null,
            ],
            'chartData' => $chartData,
            'apiBaseUrl' => request()->getSchemeAndHttpHost() . '/api/v1',
        ]);
    }

    public function telemetryLog(Request $request, Device $device): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $device->telemetries()
            ->inDateRange($request->input('from'), $request->input('to'))
            ->orderBy('measured_at', 'desc');

        $telemetries = $query->paginate(50)
            ->appends($request->only(['from', 'to']))
            ->through(fn($t) => [
                'id' => $t->id,
                'temperature' => $t->temperature,
                'humidity' => $t->humidity,
                'noise_db' => $t->noise_db,
                'measured_at' => $t->measured_at->toIso8601String(),
                'is_filled' => $t->is_filled ?? false,
                'fill_method' => $t->fill_method ?? null,
            ]);

        return Inertia::render('iot/telemetry-log', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ],
            'telemetries' => $telemetries,
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        ]);
    }

    // Note: selectOneMinuteIntervalData moved to NoiseDataSelectionService


    /**
     * Store noise data from sensor
     * POST /api/iot/noise-data
     * 
     * Note: ESP32 sends data every 1 second. We store ALL data for redundancy.
     * When retrieving for calculation, we select data closest to 5-second intervals.
     * This provides backup data in case of timeouts.
     */
    public function storeNoiseData(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'period' => ['required', 'in:L1,L2,L3,L4,L5,L6,L7,L8'],
            'noise_level' => ['required', 'numeric', 'min:0', 'max:200'],
            'temperature' => ['nullable', 'numeric'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'measured_at' => ['nullable', 'date'],
        ]);

        $measuredAt = $validated['measured_at'] ? \Carbon\Carbon::parse($validated['measured_at']) : now();

        // Store all data (no filtering at storage time)
        $data = NoiseRawData::create([
            ...$validated,
            'measured_at' => $measuredAt,
        ]);

        // Check if we have enough data points for this period today
        // Note: Since we store every 1 minute, we'll have 60 points per 1-hour period
        // We select 60 points at 1-minute intervals during retrieval
        $count = NoiseRawData::where('device_id', $validated['device_id'])
            ->where('period', $validated['period'])
            ->whereDate('measured_at', $measuredAt->toDateString())
            ->count();

        // Auto-trigger calculation if we have enough data (>= 60)
        // The calculation will select appropriate 1-minute interval data
        if ($count >= 60) {
            $this->triggerCalculation(
                $validated['device_id'],
                $validated['period'],
                $measuredAt->toDateString()
            );
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'total_count' => $count,
        ], 201);
    }

    /**
     * Get noise calculations
     * GET /api/iot/noise-calculations
     */
    public function getNoiseCalculations(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'period' => ['nullable', 'in:L1,L2,L3,L4,L5,L6,L7,L8'],
            'date' => ['nullable', 'date'],
        ]);

        $query = NoiseCalculation::where('device_id', $validated['device_id']);

        if (!empty($validated['period'])) {
            $query->period($validated['period']);
        }

        if (!empty($validated['date'])) {
            $query->forDate($validated['date']);
        } else {
            $query->forDate(now()->toDateString());
        }

        $calculations = $query->with('device')->get();

        return response()->json([
            'success' => true,
            'data' => $calculations,
        ]);
    }

    /**
     * Get real-time noise data for charts
     * GET /api/iot/noise-data/realtime
     */
    public function getRealTimeNoiseData(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'period' => ['required', 'in:L1,L2,L3,L4,L5,L6,L7,L8'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $period = $validated['period'];
        $deviceId = $validated['device_id'];

        // Cache key based on device, period, and date
        $cacheKey = "realtime_noise:{$deviceId}:{$period}:{$date}";
        
        // Cache for 5 seconds (data updates every 5 seconds anyway)
        return \Cache::remember($cacheKey, 5, function () use ($deviceId, $period, $date) {
            // Get official period times (8 periods, skip 12:00-13:00 lunch break)
            $periodTimes = [
                'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
                'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
                'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
                'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
                'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
                'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
                'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
                'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
            ];

            $officialStart = \Carbon\Carbon::parse("$date {$periodTimes[$period]['start']}");
            $officialEnd = \Carbon\Carbon::parse("$date {$periodTimes[$period]['end']}");

            // Try to get data from Telemetry first
            $selectedData = NoiseDataSelectionService::selectOneMinuteIntervalData(
                $deviceId,
                $period,
                $officialStart,
                $officialEnd
            );

            // If no telemetry data, try NoiseRawData
            if ($selectedData->isEmpty()) {
                \Log::info("No telemetry data found, trying NoiseRawData", [
                    'device_id' => $deviceId,
                    'period' => $period,
                    'date' => $date,
                ]);

                $selectedData = \App\Models\NoiseRawData::where('device_id', $deviceId)
                    ->whereBetween('measured_at', [$officialStart, $officialEnd])
                    ->orderBy('measured_at')
                    ->get();
            }

            // Get total telemetry data collected from official period only
            $totalCollected = \App\Models\Telemetry::where('device_id', $deviceId)
                ->whereDate('measured_at', $date)
                ->whereBetween('measured_at', [$officialStart, $officialEnd->copy()->addSeconds(10)])
                ->count();
            
            // If still no telemetry, count NoiseRawData
            if ($totalCollected === 0) {
                $totalCollected = \App\Models\NoiseRawData::where('device_id', $deviceId)
                    ->whereDate('measured_at', $date)
                    ->whereBetween('measured_at', [$officialStart, $officialEnd])
                    ->count();
            }
            
            $fromOfficial = $selectedData->count();

            // Format response - handle both Telemetry and NoiseRawData
            $formattedData = $selectedData->map(fn($d) => [
                'noise_level' => (float) ($d->noise_db ?? $d->noise_level ?? 0),
                'temperature' => (float) $d->temperature,
                'humidity' => (float) $d->humidity,
                'measured_at' => $d->measured_at->toIso8601String(),
                'is_filled' => (bool) ($d->is_filled ?? false),
                'fill_method' => $d->fill_method ?? null,
            ])->values();

            \Log::info("Noise data fetched", [
                'device_id' => $deviceId,
                'period' => $period,
                'date' => $date,
                'count' => $formattedData->count(),
                'total_collected' => $totalCollected,
            ]);

            return response()->json([
                'success' => true,
                'data' => $formattedData,
                'count' => $formattedData->count(),
                'total_collected' => $totalCollected,
                'from_official_period' => $fromOfficial,
                'cached_at' => now()->toIso8601String(),
            ]);
        });
    }

    /**
     * Trigger manual calculation for a period
     * POST /api/iot/noise-calculations/trigger
     */
    public function triggerCalculation(
        int|string $deviceId = null, 
        string $period = null, 
        string $date = null,
        bool $force = false
    ) {
        // Handle both API call and internal call
        if ($deviceId === null && request()->has('device_id')) {
            $validated = request()->validate([
                'device_id' => ['required', 'exists:devices,id'],
                'period' => ['required', 'in:L1,L2,L3,L4,L5,L6,L7,L8'],
                'date' => ['nullable', 'date'],
                'force' => ['nullable', 'boolean'],
            ]);
            $deviceId = $validated['device_id'];
            $period = $validated['period'];
            $date = $validated['date'] ?? now()->toDateString();
            $force = $validated['force'] ?? false;
        }

        // Get official period times (8 periods, skip 12:00-13:00 lunch break)
        $periodTimes = [
            'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
            'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
            'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
            'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
            'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
            'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
            'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
            'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
        ];

        $officialStart = \Carbon\Carbon::parse("$date {$periodTimes[$period]['start']}");
        $officialEnd = \Carbon\Carbon::parse("$date {$periodTimes[$period]['end']}");

        // Select real data at 1-minute intervals (with 5-minute safety buffer before start)
        $selectedData = NoiseDataSelectionService::selectOneMinuteIntervalData(
            $deviceId,
            $period,
            $officialStart,
            $officialEnd
        );

        // Get total telemetry data collected (including 1-minute extended period)
        // Include both real and filled data
        $extendedStart = $officialStart->copy()->subMinute();
        $totalCollected = \App\Models\Telemetry::where('device_id', $deviceId)
            ->whereDate('measured_at', $date)
            ->whereBetween('measured_at', [$extendedStart, $officialEnd])
            ->count();

        // Convert to array for calculation
        // Note: Telemetry model uses 'noise_db', not 'noise_level'
        $rawData = $selectedData->map(fn($d) => [
            'noise_level' => (float) ($d->noise_db ?? $d->noise_level ?? 0),
            'temperature' => (float) $d->temperature,
            'humidity' => (float) $d->humidity,
        ])->values()->toArray();

        $dataCount = count($rawData);

        // If NOT forced, require at least SOME real data
        if (!$force && $dataCount === 0) {
            return response()->json([
                'success' => false,
                'message' => "No real data available for calculation. Total collected: {$totalCollected}",
            ], 400);
        }

        // If forced but no data at all
        if ($dataCount === 0) {
             return response()->json([
                'success' => false,
                'message' => 'No data available to calculate.',
            ], 400);
        }

        // Process calculation
        $statsService = new NoiseStatisticsService();
        $results = $statsService->processCompleteCalculation($rawData);

        // Add metadata about data collection
        $results['data_count'] = $dataCount;
        $results['total_collected'] = $totalCollected;
        $results['from_official_period'] = $dataCount; // All data is from official period now

        // Store or update calculation
        $calculation = NoiseCalculation::updateOrCreate(
            [
                'device_id' => $deviceId,
                'period' => $period,
                'calculation_date' => $date,
            ],
            $results
        );

        return response()->json([
            'success' => true,
            'data' => $calculation,
            'metadata' => [
                'data_used' => $dataCount,
                'total_collected' => $totalCollected,
                'from_official_period' => $dataCount, // All data is from official period
                'collection_strategy' => $dataCount >= 60 ? 'full_dataset' : 'real_data_only',
            ],
        ]);
    }

    /**
     * Calculate daily summary (Ls and TWA) after all periods complete
     * POST /api/v1/iot/noise-calculations/daily-summary
     */
    public function calculateDailySummary(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'date' => ['nullable', 'date'],
        ]);
        
        $deviceId = $validated['device_id'];
        $date = $validated['date'] ?? now()->toDateString();
        
        // Get all period calculations for the day
        $calculations = NoiseCalculation::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->get()
            ->keyBy('period');
        
        if ($calculations->count() < 8) {
            return response()->json([
                'success' => false,
                'message' => 'Need all 8 periods (L1-L8) to calculate daily summary. Found: ' . $calculations->count(),
                'available_periods' => $calculations->pluck('period')->toArray(),
            ], 400);
        }
        
        // Prepare data for Ls calculation
        // Based on 8 hours work day: L1-L8 = 1h each (total 8h, skip 12-13 lunch)
        $periodData = [
            ['period' => 'L1', 'leq' => $calculations->get('L1')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L1')->data_count],
            ['period' => 'L2', 'leq' => $calculations->get('L2')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L2')->data_count],
            ['period' => 'L3', 'leq' => $calculations->get('L3')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L3')->data_count],
            ['period' => 'L4', 'leq' => $calculations->get('L4')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L4')->data_count],
            ['period' => 'L5', 'leq' => $calculations->get('L5')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L5')->data_count],
            ['period' => 'L6', 'leq' => $calculations->get('L6')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L6')->data_count],
            ['period' => 'L7', 'leq' => $calculations->get('L7')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L7')->data_count],
            ['period' => 'L8', 'leq' => $calculations->get('L8')->leq_value, 'duration_hours' => 1, 'data_count' => $calculations->get('L8')->data_count],
        ];
        
        $statsService = new NoiseStatisticsService();
        
        // Calculate Ls (Leq Siang) using actual data count
        $ls = $statsService->calculateLs($periodData);
        
        // Calculate allowable time (T) using NIOSH formula
        // Formula: T = 8 / 2^((L-85)/3)
        $allowableTime = $statsService->calculateAllowableTime($ls);
        
        // Calculate DND (Daily Noise Dose) using NIOSH method
        // Formula: D(%) = (C/T) × 100%
        // Where T = 8 / 2^((L-85)/3)
        $exposureTime = 8; // 8 hours work day
        $dnd = $statsService->calculateDND($ls, $exposureTime);
        
        // Calculate TWA (Time Weighted Average)
        // Formula: TWA = 10 × log(DND/100) + 85
        $twa = $statsService->calculateTWA($dnd);
        
        // Store daily summary
        $summary = NoiseDailySummary::updateOrCreate(
            [
                'device_id' => $deviceId,
                'calculation_date' => $date,
            ],
            [
                'ls_value' => $ls,
                'twa_value' => $twa,
                'dnd_value' => $dnd,
                'allowable_time' => $allowableTime,
                'l1_leq' => $periodData[0]['leq'],
                'l2_leq' => $periodData[1]['leq'],
                'l3_leq' => $periodData[2]['leq'],
                'l4_leq' => $periodData[3]['leq'],
                'l5_leq' => $periodData[4]['leq'],
                'l6_leq' => $periodData[5]['leq'],
                'l7_leq' => $periodData[6]['leq'],
                'l8_leq' => $periodData[7]['leq'],
            ]
        );
        
        return response()->json([
            'success' => true,
            'data' => $summary,
            'calculation_details' => [
                'periods' => $periodData,
                'ls_formula' => '10 × log(1/8 × Σ(Ti × 10^(0.1×Li)))',
                'dnd_formula' => 'D(%) = (C/T) × 100%, where T = 8 / 2^((L-85)/3)',
                'twa_formula' => '10 × log(DND/100) + 85',
                'exposure_time' => $exposureTime . ' hours',
                'reference_level' => '85 dBA (NIOSH standard)',
            ],
        ]);
    }

    /**
     * Get daily summary for a device and date
     * GET /api/v1/iot/daily-summary
     */
    public function getDailySummary(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'date' => ['nullable', 'date'],
        ]);
        
        $deviceId = $validated['device_id'];
        $date = $validated['date'] ?? now()->toDateString();
        
        // Get daily summary
        $summary = NoiseDailySummary::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->first();
        
        if (!$summary) {
            // Try to calculate if all periods are available
            $calculations = NoiseCalculation::where('device_id', $deviceId)
                ->whereDate('calculation_date', $date)
                ->get();
            
            if ($calculations->count() === 8) {
                // Auto-calculate
                $calcRequest = new Request([
                    'device_id' => $deviceId,
                    'date' => $date,
                ]);
                $response = $this->calculateDailySummary($calcRequest);
                $responseData = $response->getData(true);
                
                if ($responseData['success']) {
                    $summary = $responseData['data'];
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Export daily summary to Excel
     * GET /api/v1/iot/daily-summary/export
     */
    public function exportDailySummary(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $device = Device::find($validated['device_id']);
        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'] ?? $startDate;

        $filename = $startDate === $endDate
            ? "Daily_Report_{$device->slug}_{$startDate}.xlsx"
            : "Daily_Report_{$device->slug}_{$startDate}_to_{$endDate}.xlsx";

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\DailySummaryExport(
                $validated['device_id'],
                $startDate,
                $endDate,
                $device->name
            ),
            $filename
        );
    }

    /**
     * Get timeout logs for a device and date
     * GET /api/iot/timeout-logs
     */
    public function getTimeoutLogs(Request $request)
    {
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        
        // OPTIMIZED: Use date range instead of whereDate for better index usage
        $startOfDay = \Carbon\Carbon::parse($date)->startOfDay();
        $endOfDay = \Carbon\Carbon::parse($date)->endOfDay();

        $logs = \App\Models\NoiseTimeoutLog::where('device_id', $validated['device_id'])
            ->whereBetween('expected_at', [$startOfDay, $endOfDay])
            ->orderBy('expected_at', 'desc')
            ->limit(100) // Add limit to prevent loading too many logs
            ->get();

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * Export noise data to Excel
     * GET /api/v1/iot/noise-data/export
     */
    public function exportNoiseData(Request $request)
    {
        try {
            \Log::info('Export noise data request received', [
                'device_id' => $request->device_id,
                'period' => $request->period,
                'date' => $request->date,
                'ip' => $request->ip(),
            ]);

            $validated = $request->validate([
                'device_id' => ['required', 'exists:devices,id'],
                'period' => ['required', 'in:L1,L2,L3,L4,L5,L6,L7,L8'],
                'date' => ['nullable', 'date'],
            ]);

            $device = \App\Models\Device::find($validated['device_id']);
            $date = $validated['date'] ?? now()->toDateString();
            $period = $validated['period'];

            $filename = sprintf(
                '%s_%s_%s_noise_data.xlsx',
                str_replace(' ', '_', $device->name),
                $period,
                $date
            );

            \Log::info('Starting Excel export', [
                'filename' => $filename,
                'device' => $device->name,
            ]);

            $export = \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\NoiseDataExport(
                    $validated['device_id'],
                    $period,
                    $date,
                    $device->name
                ),
                $filename
            );

            \Log::info('Export completed successfully');

            return $export;
        } catch (\Exception $e) {
            \Log::error('Export noise data failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'device_id' => $request->device_id ?? null,
                'period' => $request->period ?? null,
                'date' => $request->date ?? null,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
