<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\NoiseRawData;
use App\Models\NoiseCalculation;
use App\Models\NoiseDailySummary;
use App\Models\NoiseTimeoutLog;
use App\Services\NoiseStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterDataController extends Controller
{
    protected NoiseStatisticsService $statisticsService;

    public function __construct(NoiseStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Get noise raw data (L1-L8) with filters
     * GET /api/v1/master-data/noise-raw
     */
    public function getNoiseRawData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
            'period' => 'nullable|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = NoiseRawData::where('device_id', $request->device_id)
            ->whereDate('measured_at', $request->date)
            ->orderBy('measured_at', 'asc');

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Noise raw data retrieved successfully',
            'data' => $data->map(fn($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'period' => $item->period,
                'noise_level' => $item->noise_level,
                'temperature' => $item->temperature,
                'humidity' => $item->humidity,
                'measured_at' => $item->measured_at->toIso8601String(),
                'is_filled' => $item->is_filled,
                'fill_method' => $item->fill_method,
                'consecutive_timeouts' => $item->consecutive_timeouts,
            ]),
            'meta' => [
                'total' => $data->count(),
                'device_id' => $request->device_id,
                'date' => $request->date,
                'period' => $request->period,
            ],
        ]);
    }

    /**
     * Get noise calculations (L1-L8) with filters
     * GET /api/v1/master-data/noise-calculations
     */
    public function getNoiseCalculations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
            'period' => 'nullable|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = NoiseCalculation::where('device_id', $request->device_id)
            ->whereDate('calculation_date', $request->date);

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Noise calculations retrieved successfully',
            'data' => $data->map(fn($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'period' => $item->period,
                'calculation_date' => $item->calculation_date->format('Y-m-d'),
                'data_count' => $item->data_count,
                'total_collected' => $item->total_collected,
                'from_official_period' => $item->from_official_period,
                'min_value' => $item->min_value,
                'max_value' => $item->max_value,
                'average_value' => $item->average_value,
                'range_value' => $item->range_value,
                'class_count' => $item->class_count,
                'class_interval' => $item->class_interval,
                'leq_value' => $item->leq_value,
                'frequency_distribution' => $item->frequency_distribution,
                'thi_average' => $item->thi_average,
                'is_complete' => $item->isComplete(),
            ]),
            'meta' => [
                'total' => $data->count(),
                'device_id' => $request->device_id,
                'date' => $request->date,
                'period' => $request->period,
            ],
        ]);
    }

    /**
     * Get daily summaries (Ls, TWA, DND) with filters
     * GET /api/v1/master-data/daily-summaries
     */
    public function getDailySummaries(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $dateTo = $request->date_to ?? $request->date_from;

        $data = NoiseDailySummary::where('device_id', $request->device_id)
            ->whereBetween('calculation_date', [$request->date_from, $dateTo])
            ->orderBy('calculation_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daily summaries retrieved successfully',
            'data' => $data->map(fn($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'calculation_date' => $item->calculation_date->format('Y-m-d'),
                'ls_value' => $item->ls_value,
                'twa_value' => $item->twa_value,
                'dnd_value' => $item->dnd_value,
                'allowable_time' => $item->allowable_time,
                'l1_leq' => $item->l1_leq,
                'l2_leq' => $item->l2_leq,
                'l3_leq' => $item->l3_leq,
                'l4_leq' => $item->l4_leq,
            ]),
            'meta' => [
                'total' => $data->count(),
                'device_id' => $request->device_id,
                'date_from' => $request->date_from,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Get timeout logs with filters
     * GET /api/v1/master-data/timeout-logs
     */
    public function getTimeoutLogs(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
            'period' => 'nullable|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = NoiseTimeoutLog::where('device_id', $request->device_id)
            ->whereDate('expected_at', $request->date)
            ->orderBy('expected_at', 'desc');

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        $data = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Timeout logs retrieved successfully',
            'data' => $data->map(fn($item) => [
                'id' => $item->id,
                'device_id' => $item->device_id,
                'period' => $item->period,
                'expected_at' => $item->expected_at->toIso8601String(),
                'action_taken' => $item->action_taken,
                'consecutive_count' => $item->consecutive_count,
                'details' => $item->details,
            ]),
            'meta' => [
                'total' => $data->count(),
                'device_id' => $request->device_id,
                'date' => $request->date,
                'period' => $request->period,
            ],
        ]);
    }

    /**
     * Add missing noise raw data
     * POST /api/v1/master-data/noise-raw
     */
    public function addNoiseRawData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'period' => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
            'noise_level' => 'required|numeric|min:0|max:200',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric|min:0|max:100',
            'measured_at' => 'required|date',
            'fill_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if data already exists
        $exists = NoiseRawData::where('device_id', $request->device_id)
            ->where('period', $request->period)
            ->where('measured_at', $request->measured_at)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Data already exists for this timestamp',
                'errors' => ['measured_at' => 'Duplicate entry'],
            ], 422);
        }

        $data = NoiseRawData::create([
            'device_id' => $request->device_id,
            'period' => $request->period,
            'noise_level' => $request->noise_level,
            'temperature' => $request->temperature,
            'humidity' => $request->humidity,
            'measured_at' => $request->measured_at,
            'is_filled' => true,
            'fill_method' => $request->fill_method ?? 'manual',
            'consecutive_timeouts' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Noise raw data added successfully',
            'data' => [
                'id' => $data->id,
                'device_id' => $data->device_id,
                'period' => $data->period,
                'noise_level' => $data->noise_level,
                'temperature' => $data->temperature,
                'humidity' => $data->humidity,
                'measured_at' => $data->measured_at->toIso8601String(),
                'is_filled' => $data->is_filled,
                'fill_method' => $data->fill_method,
            ],
        ], 201);
    }

    /**
     * Update noise raw data
     * PUT /api/v1/master-data/noise-raw/{id}
     */
    public function updateNoiseRawData(Request $request, int $id): JsonResponse
    {
        $data = NoiseRawData::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'noise_level' => 'nullable|numeric|min:0|max:200',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric|min:0|max:100',
            'measured_at' => 'nullable|date',
            'fill_method' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data->update($request->only([
            'noise_level',
            'temperature',
            'humidity',
            'measured_at',
            'fill_method',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Noise raw data updated successfully',
            'data' => [
                'id' => $data->id,
                'device_id' => $data->device_id,
                'period' => $data->period,
                'noise_level' => $data->noise_level,
                'temperature' => $data->temperature,
                'humidity' => $data->humidity,
                'measured_at' => $data->measured_at->toIso8601String(),
                'is_filled' => $data->is_filled,
                'fill_method' => $data->fill_method,
            ],
        ]);
    }

    /**
     * Delete noise raw data
     * DELETE /api/v1/master-data/noise-raw/{id}
     */
    public function deleteNoiseRawData(int $id): JsonResponse
    {
        $data = NoiseRawData::find($id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Noise raw data deleted successfully',
        ]);
    }

    /**
     * Recalculate noise period (L1-L8)
     * POST /api/v1/master-data/recalculate-period
     */
    public function recalculatePeriod(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'period' => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $deviceId = $request->device_id;
            $period = $request->period;
            $date = $request->date;

            // Get raw data for the period
            $rawData = NoiseRawData::where('device_id', $deviceId)
                ->where('period', $period)
                ->whereDate('measured_at', $date)
                ->orderBy('measured_at', 'asc')
                ->get();

            if ($rawData->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No raw data found for this period',
                ], 404);
            }

            // Prepare data for calculation
            $dataForCalculation = $rawData->map(fn($item) => [
                'noise_level' => $item->noise_level,
                'temperature' => $item->temperature,
                'humidity' => $item->humidity,
            ])->toArray();

            // Calculate statistics
            $calculation = $this->statisticsService->processCompleteCalculation($dataForCalculation);

            // Count data from official period (not filled)
            $fromOfficialPeriod = $rawData->where('is_filled', false)->count();

            // Update or create calculation
            $noiseCalculation = NoiseCalculation::updateOrCreate(
                [
                    'device_id' => $deviceId,
                    'period' => $period,
                    'calculation_date' => $date,
                ],
                [
                    'data_count' => $calculation['data_count'],
                    'total_collected' => $rawData->count(),
                    'from_official_period' => $fromOfficialPeriod,
                    'min_value' => $calculation['min_value'],
                    'max_value' => $calculation['max_value'],
                    'average_value' => $calculation['average_value'],
                    'range_value' => $calculation['range_value'],
                    'class_count' => $calculation['class_count'],
                    'class_interval' => $calculation['class_interval'],
                    'leq_value' => $calculation['leq_value'],
                    'frequency_distribution' => $calculation['frequency_distribution'],
                    'thi_average' => $calculation['thi_average'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Period recalculated successfully',
                'data' => [
                    'id' => $noiseCalculation->id,
                    'device_id' => $noiseCalculation->device_id,
                    'period' => $noiseCalculation->period,
                    'calculation_date' => $noiseCalculation->calculation_date->format('Y-m-d'),
                    'data_count' => $noiseCalculation->data_count,
                    'total_collected' => $noiseCalculation->total_collected,
                    'from_official_period' => $noiseCalculation->from_official_period,
                    'leq_value' => $noiseCalculation->leq_value,
                    'min_value' => $noiseCalculation->min_value,
                    'max_value' => $noiseCalculation->max_value,
                    'average_value' => $noiseCalculation->average_value,
                    'thi_average' => $noiseCalculation->thi_average,
                    'is_complete' => $noiseCalculation->isComplete(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Recalculation failed',
                'errors' => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Recalculate daily summary (Ls, TWA, DND)
     * POST /api/v1/master-data/recalculate-daily
     */
    public function recalculateDaily(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $deviceId = $request->device_id;
            $date = $request->date;

            // Get all period calculations for the date
            $calculations = NoiseCalculation::where('device_id', $deviceId)
                ->whereDate('calculation_date', $date)
                ->whereIn('period', ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'])
                ->get();

            if ($calculations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No calculations found for this date',
                ], 404);
            }

            // Prepare period data for Ls calculation
            $periodData = $calculations->map(fn($calc) => [
                'period' => $calc->period,
                'leq' => $calc->leq_value,
                'duration_hours' => 1, // Each period is 1 hour
                'data_count' => $calc->data_count,
            ])->toArray();

            // Calculate Ls
            $ls = $this->statisticsService->calculateLs($periodData);

            // Calculate allowable time
            $allowableTime = $this->statisticsService->calculateAllowableTime($ls);

            // Calculate DND (8 hours exposure)
            $dnd = $this->statisticsService->calculateDND($ls, 8);

            // Calculate TWA
            $twa = $this->statisticsService->calculateTWA($dnd);

            // Get individual Leq values
            $leqValues = [];
            foreach (['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'] as $period) {
                $calc = $calculations->firstWhere('period', $period);
                $leqValues[strtolower($period) . '_leq'] = $calc ? $calc->leq_value : null;
            }

            // Update or create daily summary
            $summary = NoiseDailySummary::updateOrCreate(
                [
                    'device_id' => $deviceId,
                    'calculation_date' => $date,
                ],
                array_merge([
                    'ls_value' => $ls,
                    'twa_value' => $twa,
                    'dnd_value' => $dnd,
                    'allowable_time' => $allowableTime,
                ], $leqValues)
            );

            return response()->json([
                'success' => true,
                'message' => 'Daily summary recalculated successfully',
                'data' => [
                    'id' => $summary->id,
                    'device_id' => $summary->device_id,
                    'calculation_date' => $summary->calculation_date->format('Y-m-d'),
                    'ls_value' => $summary->ls_value,
                    'twa_value' => $summary->twa_value,
                    'dnd_value' => $summary->dnd_value,
                    'allowable_time' => $summary->allowable_time,
                    'l1_leq' => $summary->l1_leq,
                    'l2_leq' => $summary->l2_leq,
                    'l3_leq' => $summary->l3_leq,
                    'l4_leq' => $summary->l4_leq,
                    'l5_leq' => $summary->l5_leq,
                    'l6_leq' => $summary->l6_leq,
                    'l7_leq' => $summary->l7_leq,
                    'l8_leq' => $summary->l8_leq,
                    'periods_calculated' => $calculations->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Recalculation failed',
                'errors' => ['exception' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get data summary for a specific date and device
     * GET /api/v1/master-data/summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deviceId = $request->device_id;
        $date = $request->date;

        // Get counts for each period
        $periodSummary = [];
        foreach (['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'] as $period) {
            $rawCount = NoiseRawData::where('device_id', $deviceId)
                ->where('period', $period)
                ->whereDate('measured_at', $date)
                ->count();

            $calculation = NoiseCalculation::where('device_id', $deviceId)
                ->where('period', $period)
                ->whereDate('calculation_date', $date)
                ->first();

            $periodSummary[] = [
                'period' => $period,
                'raw_data_count' => $rawCount,
                'required_count' => 60,
                'is_complete' => $rawCount >= 60,
                'has_calculation' => $calculation !== null,
                'leq_value' => $calculation?->leq_value,
            ];
        }

        // Get daily summary
        $dailySummary = NoiseDailySummary::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Summary retrieved successfully',
            'data' => [
                'device_id' => $deviceId,
                'date' => $date,
                'periods' => $periodSummary,
                'daily_summary' => $dailySummary ? [
                    'ls_value' => $dailySummary->ls_value,
                    'twa_value' => $dailySummary->twa_value,
                    'dnd_value' => $dailySummary->dnd_value,
                    'allowable_time' => $dailySummary->allowable_time,
                ] : null,
            ],
        ]);
    }
}
