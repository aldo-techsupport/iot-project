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
                        
                        // Get timeout logs for today
                        $timeoutLogs = \App\Models\NoiseTimeoutLog::where('device_id', $device->id)
                            ->whereDate('expected_at', $date)
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

    // Note: selectFiveSecondIntervalData moved to NoiseDataSelectionService


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
            'period' => ['required', 'in:L1,L2,L3,L4'],
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
        // Note: Since we store every second, we'll have ~600 points per 10-min period
        // We select 120 points at 5-second intervals during retrieval
        $count = NoiseRawData::where('device_id', $validated['device_id'])
            ->where('period', $validated['period'])
            ->whereDate('measured_at', $measuredAt->toDateString())
            ->count();

        // Auto-trigger calculation if we have enough data (>= 120)
        // The calculation will select appropriate 5-second interval data
        if ($count >= 120) {
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
            'period' => ['nullable', 'in:L1,L2,L3,L4'],
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
            'period' => ['required', 'in:L1,L2,L3,L4'],
            'date' => ['nullable', 'date'],
        ]);

        $date = $validated['date'] ?? now()->toDateString();
        $period = $validated['period'];

        // Get official period times
        $periodTimes = [
            'L1' => ['start' => '09:00:00', 'end' => '09:10:00'],
            'L2' => ['start' => '11:00:00', 'end' => '11:10:00'],
            'L3' => ['start' => '14:00:00', 'end' => '14:10:00'],
            'L4' => ['start' => '16:00:00', 'end' => '16:10:00'],
        ];

        $officialStart = \Carbon\Carbon::parse("$date {$periodTimes[$period]['start']}");
        $officialEnd = \Carbon\Carbon::parse("$date {$periodTimes[$period]['end']}");

        // Select real data at 5-second intervals from 24-hour telemetry data
        $selectedData = NoiseDataSelectionService::selectFiveSecondIntervalData(
            $validated['device_id'],
            $period,
            $officialStart,
            $officialEnd
        );

        // Get total telemetry data collected from official period only
        // (no buffer, consistent with new timing strategy)
        $totalCollected = \App\Models\Telemetry::where('device_id', $validated['device_id'])
            ->whereDate('measured_at', $date)
            ->whereBetween('measured_at', [$officialStart, $officialEnd->copy()->addSeconds(10)])
            ->where('is_filled', false)
            ->count();
        
        // All selected data are from official period (with tolerance for closest match)
        $fromOfficial = $selectedData->count();

        // Format response - map telemetry fields to noise data format
        $formattedData = $selectedData->map(fn($d) => [
            'noise_level' => (float) ($d->noise_db ?? 0), // telemetry uses 'noise_db'
            'temperature' => (float) $d->temperature,
            'humidity' => (float) $d->humidity,
            'measured_at' => $d->measured_at->toIso8601String(),
            'is_filled' => (bool) $d->is_filled,
            'fill_method' => $d->fill_method,
        ])->values();

        return response()->json([
            'success' => true,
            'data' => $formattedData,
            'count' => $formattedData->count(),
            'total_collected' => $totalCollected,
            'from_official_period' => $fromOfficial,
        ]);
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
                'period' => ['required', 'in:L1,L2,L3,L4'],
                'date' => ['nullable', 'date'],
                'force' => ['nullable', 'boolean'],
            ]);
            $deviceId = $validated['device_id'];
            $period = $validated['period'];
            $date = $validated['date'] ?? now()->toDateString();
            $force = $validated['force'] ?? false;
        }

        // Get official period times
        $periodTimes = [
            'L1' => ['start' => '09:00:00', 'end' => '09:10:00'],
            'L2' => ['start' => '11:00:00', 'end' => '11:10:00'],
            'L3' => ['start' => '14:00:00', 'end' => '14:10:00'],
            'L4' => ['start' => '16:00:00', 'end' => '16:10:00'],
        ];

        $officialStart = \Carbon\Carbon::parse("$date {$periodTimes[$period]['start']}");
        $officialEnd = \Carbon\Carbon::parse("$date {$periodTimes[$period]['end']}");

        // Select real data at 5-second intervals (with 1-minute safety buffer before start)
        $selectedData = NoiseDataSelectionService::selectFiveSecondIntervalData(
            $deviceId,
            $period,
            $officialStart,
            $officialEnd
        );

        // Get total telemetry data collected (including 1-minute extended period)
        $extendedStart = $officialStart->copy()->subMinute();
        $totalCollected = \App\Models\Telemetry::where('device_id', $deviceId)
            ->whereDate('measured_at', $date)
            ->whereBetween('measured_at', [$extendedStart, $officialEnd])
            ->where('is_filled', false)
            ->count();

        // Convert to array for calculation
        // Note: Telemetry model uses 'noise_db', not 'noise_level'
        $rawData = $selectedData->map(fn($d) => [
            'noise_level' => (float) ($d->noise_db ?? $d->noise_level ?? 0),
            'temperature' => (float) $d->temperature,
            'humidity' => (float) $d->humidity,
        ])->values()->toArray();

        $dataCount = count($rawData);

        // If NOT forced, require at least SOME data (lowered from 60 to 10)
        if (!$force && $dataCount < 10) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient data. Need at least 10 data points for calculation, got {$dataCount}. Total collected: {$totalCollected}",
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
                'collection_strategy' => $dataCount >= 120 ? 'full_dataset' : 'real_data_only',
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
        
        if ($calculations->count() < 4) {
            return response()->json([
                'success' => false,
                'message' => 'Need all 4 periods (L1-L4) to calculate daily summary. Found: ' . $calculations->count(),
                'available_periods' => $calculations->pluck('period')->toArray(),
            ], 400);
        }
        
        // Prepare data for Ls calculation
        // Based on 8 hours work day: L1=2h, L2=2h, L3=2h, L4=2h (total 8h)
        $periodData = [
            ['period' => 'L1', 'leq' => $calculations->get('L1')->leq_value, 'duration_hours' => 2],
            ['period' => 'L2', 'leq' => $calculations->get('L2')->leq_value, 'duration_hours' => 2],
            ['period' => 'L3', 'leq' => $calculations->get('L3')->leq_value, 'duration_hours' => 2],
            ['period' => 'L4', 'leq' => $calculations->get('L4')->leq_value, 'duration_hours' => 2],
        ];
        
        $statsService = new NoiseStatisticsService();
        
        // Calculate Ls (Leq Siang)
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
            
            if ($calculations->count() === 4) {
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

        $logs = \App\Models\NoiseTimeoutLog::where('device_id', $validated['device_id'])
            ->whereDate('expected_at', $date)
            ->orderBy('expected_at', 'desc')
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
        $validated = $request->validate([
            'device_id' => ['required', 'exists:devices,id'],
            'period' => ['required', 'in:L1,L2,L3,L4'],
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

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\NoiseDataExport(
                $validated['device_id'],
                $period,
                $date,
                $device->name
            ),
            $filename
        );
    }
}
