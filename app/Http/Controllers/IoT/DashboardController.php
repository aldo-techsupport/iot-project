<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Telemetry;
use App\Models\NoiseRawData;
use App\Models\NoiseCalculation;
use App\Models\NoiseDailySummary;
use App\Services\NoiseStatisticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        $telemetries = $query->paginate(50)->through(fn($t) => [
            'id' => $t->id,
            'temperature' => $t->temperature,
            'humidity' => $t->humidity,
            'noise_db' => $t->noise_db,
            'measured_at' => $t->measured_at->toIso8601String(),
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

    /**
     * Store noise data from sensor
     * POST /api/iot/noise-data
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

        $data = NoiseRawData::create([
            ...$validated,
            'measured_at' => $validated['measured_at'] ?? now(),
        ]);

        // Check if we have 120 data points for this period today
        $count = NoiseRawData::where('device_id', $validated['device_id'])
            ->where('period', $validated['period'])
            ->whereDate('measured_at', now()->toDateString())
            ->count();

        // Auto-trigger calculation if we reached 120 data points
        if ($count >= 120) {
            $this->triggerCalculation(
                $validated['device_id'],
                $validated['period'],
                now()->toDateString()
            );
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $count,
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

        $data = NoiseRawData::where('device_id', $validated['device_id'])
            ->where('period', $validated['period'])
            ->whereDate('measured_at', $date)
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(fn($d) => [
                'noise_level' => (float) $d->noise_level,
                'temperature' => (float) $d->temperature,
                'humidity' => (float) $d->humidity,
                'measured_at' => $d->measured_at->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /**
     * Trigger manual calculation for a period
     * POST /api/iot/noise-calculations/trigger
     */
    public function triggerCalculation(
        int|string $deviceId = null, 
        string $period = null, 
        string $date = null
    ) {
        // Handle both API call and internal call
        if ($deviceId === null && request()->has('device_id')) {
            $validated = request()->validate([
                'device_id' => ['required', 'exists:devices,id'],
                'period' => ['required', 'in:L1,L2,L3,L4'],
                'date' => ['nullable', 'date'],
            ]);
            $deviceId = $validated['device_id'];
            $period = $validated['period'];
            $date = $validated['date'] ?? now()->toDateString();
        }

        // Get raw data
        $rawData = NoiseRawData::where('device_id', $deviceId)
            ->where('period', $period)
            ->whereDate('measured_at', $date)
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(fn($d) => [
                'noise_level' => (float) $d->noise_level,
                'temperature' => (float) $d->temperature,
                'humidity' => (float) $d->humidity,
            ])
            ->toArray();

        if (count($rawData) < 120) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient data. Need 120 data points, got ' . count($rawData),
            ], 400);
        }

        // Process calculation
        $statsService = new NoiseStatisticsService();
        $results = $statsService->processCompleteCalculation($rawData);

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
        // Based on research: L1=2h, L2=2h, L3=4h, L4=5h (total 13h, but using 16h standard)
        $periodData = [
            ['period' => 'L1', 'leq' => $calculations->get('L1')->leq_value, 'duration_hours' => 2],
            ['period' => 'L2', 'leq' => $calculations->get('L2')->leq_value, 'duration_hours' => 2],
            ['period' => 'L3', 'leq' => $calculations->get('L3')->leq_value, 'duration_hours' => 4],
            ['period' => 'L4', 'leq' => $calculations->get('L4')->leq_value, 'duration_hours' => 5],
        ];
        
        $statsService = new NoiseStatisticsService();
        
        // Calculate Ls (Leq Siang)
        $ls = $statsService->calculateLs($periodData);
        
        // Calculate DND (Dosis Harian)
        // Note: This is a simplified calculation. 
        // Proper DND calculation requires exposure time and reference level (85 dBA for 8 hours)
        // For now, we'll use a simplified approach based on Ls
        $dnd = 100; // Placeholder - needs proper implementation based on exposure standards
        
        // Calculate TWA
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
                'ls_formula' => '10 × log10(1/16 × Σ(Ti × 10^(0.1×Li)))',
                'twa_formula' => '10 × log(DND/100) + 85',
            ],
        ]);
    }
}
