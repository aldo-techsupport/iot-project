<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Telemetry;
use App\Models\NoiseRawData;
use App\Models\NoiseFilteredData;
use App\Models\NoiseDailySummary;
use App\Models\Device;
use App\Services\NoiseDataSelectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminDataController extends Controller
{
    /**
     * Show admin dashboard
     */
    public function index(): Response
    {
        $stats = [
            'total_telemetries' => Telemetry::count(),
            'total_noise_data' => NoiseRawData::count(),
            'total_daily_summaries' => NoiseDailySummary::count(),
            'total_devices' => Device::count(),
        ];

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Recalculate noise periods for a specific date
     */
    public function recalculateNoisePeriod(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
            'period' => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

        try {
            $this->triggerRecalculate((int) $validated['device_id'], $validated['period'], $validated['date']);

            return back()->with('success', 'Noise period recalculated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate ALL noise periods (L1–L8) for a device and date in one go
     */
    public function recalculateAllNoisePeriods(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date'      => 'required|date',
        ]);

        $periods = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];
        $success = [];
        $failed  = [];

        foreach ($periods as $period) {
            try {
                $this->triggerRecalculate((int) $validated['device_id'], $period, $validated['date']);
                $success[] = $period;
            } catch (\Exception $e) {
                $failed[] = $period;
                Log::error("Recalculate all — {$period} failed: " . $e->getMessage());
            }
        }

        $msg = 'Recalculated: ' . implode(', ', $success);
        if (!empty($failed)) {
            $msg .= '. Failed: ' . implode(', ', $failed);
        }

        return back()->with('success', $msg);
    }

    /**
     * Recalculate ALL periods (L1–L8) for ALL dates for a device
     * Runs as a queued/synchronous loop over every distinct calculation_date
     */
    public function recalculateAllDates(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
        ]);

        $deviceId = (int) $validated['device_id'];
        $periods  = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];

        // Get all distinct dates that have noise filtered data for this device
        $dates = \App\Models\NoiseFilteredData::where('device_id', $deviceId)
            ->selectRaw('DATE(calculation_date) as date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date')
            ->toArray();

        if (empty($dates)) {
            return back()->with('error', 'No noise data found for this device.');
        }

        $totalSuccess = 0;
        $totalFailed  = 0;

        foreach ($dates as $date) {
            foreach ($periods as $period) {
                try {
                    $this->triggerRecalculate($deviceId, $period, $date);
                    $totalSuccess++;
                } catch (\Exception $e) {
                    $totalFailed++;
                    Log::error("Recalculate all dates — device {$deviceId} {$period} {$date} failed: " . $e->getMessage());
                }
            }
        }

        $msg = "Recalculated {$totalSuccess} period(s) across " . count($dates) . " date(s).";
        if ($totalFailed > 0) {
            $msg .= " {$totalFailed} failed (check logs).";
        }

        return back()->with('success', $msg);
    }

    /**
     * Recalculate daily summary for a specific date
     */
    public function recalculateDailySummary(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
        ]);

        try {
            // Directly recalculate daily summary validity and values
            $this->updateDailySummaryValidity((int) $validated['device_id'], $validated['date']);

            return back()->with('success', 'Daily summary recalculated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate: ' . $e->getMessage());
        }
    }

    /**
     * Delete telemetry data
     */
    public function deleteTelemetry(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:telemetries,id',
        ]);

        try {
            Telemetry::findOrFail($validated['id'])->delete();
            return back()->with('success', 'Telemetry data deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Delete noise raw data
     */
    public function deleteNoiseData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:noise_raw_data,id',
        ]);

        try {
            NoiseRawData::findOrFail($validated['id'])->delete();
            return back()->with('success', 'Noise data deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Delete daily summary
     */
    public function deleteDailySummary(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:noise_daily_summaries,id',
        ]);

        try {
            NoiseDailySummary::findOrFail($validated['id'])->delete();
            return back()->with('success', 'Daily summary deleted successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete telemetry data by date range
     */
    public function bulkDeleteTelemetry(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $count = Telemetry::where('device_id', $validated['device_id'])
                ->whereBetween('measured_at', [$validated['start_date'], $validated['end_date']])
                ->delete();

            return back()->with('success', "Deleted {$count} telemetry records");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete noise data by date range
     */
    public function bulkDeleteNoiseData(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            $count = NoiseRawData::where('device_id', $validated['device_id'])
                ->whereBetween('measured_at', [$validated['start_date'], $validated['end_date']])
                ->delete();

            return back()->with('success', "Deleted {$count} noise data records");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    /**
     * Get filtered noise data for a specific period (dari noise_filtered_data)
     */
    public function getNoiseDataByPeriod(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date'      => 'required|date',
            'period'    => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

        $data = NoiseFilteredData::where('device_id', $validated['device_id'])
            ->where('period', $validated['period'])
            ->whereDate('calculation_date', $validated['date'])
            ->orderBy('slot_index')
            ->get()
            ->map(fn($d) => [
                'id'          => $d->id,
                'slot_index'  => $d->slot_index,
                'measured_at' => $d->measured_at->toIso8601String(),
                'noise_db'    => $d->noise_level,
                'temperature' => $d->temperature,
                'humidity'    => $d->humidity,
                'thi'         => ($d->temperature && $d->humidity)
                                    ? round(0.8 * $d->temperature + ($d->humidity * $d->temperature) / 500, 2)
                                    : null,
                'is_filled'   => $d->is_filled,
                'fill_method' => $d->fill_method,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'count'   => $data->count(),
            'source'  => 'filtered_db',
        ]);
    }

    /**
     * Add a new row to noise_filtered_data for a specific period slot,
     * then trigger recalculate automatically.
     */
    public function addNoiseData(Request $request)
    {
        $validated = $request->validate([
            'device_id'   => 'required|exists:devices,id',
            'period'      => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
            'date'        => 'required|date_format:Y-m-d',
            'measured_at' => 'required|date',
            'noise_db'    => 'required|numeric|min:0|max:200',
            'temperature' => 'required|numeric|min:-50|max:100',
            'humidity'    => 'required|numeric|min:0|max:100',
        ]);

        $periodTimes = [
            'L1' => ['start' => '08:00', 'end' => '09:00'],
            'L2' => ['start' => '09:00', 'end' => '10:00'],
            'L3' => ['start' => '10:00', 'end' => '11:00'],
            'L4' => ['start' => '11:00', 'end' => '12:00'],
            'L5' => ['start' => '13:00', 'end' => '14:00'],
            'L6' => ['start' => '14:00', 'end' => '15:00'],
            'L7' => ['start' => '15:00', 'end' => '16:00'],
            'L8' => ['start' => '16:00', 'end' => '17:00'],
        ];

        $period          = $validated['period'];
        $calculationDate = $validated['date'];
        $measuredAt      = \Carbon\Carbon::parse($validated['measured_at']);
        $periodStart     = \Carbon\Carbon::parse($calculationDate . ' ' . $periodTimes[$period]['start'] . ':00');
        $periodEnd       = \Carbon\Carbon::parse($calculationDate . ' ' . $periodTimes[$period]['end'] . ':00');

        // Validate that measured_at falls within the selected period's time range
        if ($measuredAt->lt($periodStart) || $measuredAt->gte($periodEnd)) {
            return response()->json([
                'success' => false,
                'message' => "Timestamp {$measuredAt->format('H:i')} tidak sesuai dengan periode {$period} ({$periodTimes[$period]['start']}–{$periodTimes[$period]['end']}). Harap masukkan waktu yang benar.",
            ], 422);
        }

        // slot_index = minutes elapsed since period start (0-based)
        $slotIndex = max(0, (int) $periodStart->diffInMinutes($measuredAt));

        try {
            $row = NoiseFilteredData::create([
                'device_id'        => $validated['device_id'],
                'period'           => $period,
                'calculation_date' => $calculationDate,
                'noise_level'      => $validated['noise_db'],
                'temperature'      => $validated['temperature'],
                'humidity'         => $validated['humidity'],
                'measured_at'      => $measuredAt->toDateTimeString(),
                'is_filled'        => false,
                'fill_method'      => 'actual',
                'slot_index'       => $slotIndex,
            ]);

            // Generate 12 telemetry rows (every 5 seconds within the 1-minute slot)
            $this->generateTelemetryForSlot(
                (int) $validated['device_id'],
                $measuredAt,
                (float) $validated['noise_db'],
                (float) $validated['temperature'],
                (float) $validated['humidity']
            );

            $this->triggerRecalculate((int) $validated['device_id'], $period, $calculationDate);

            return response()->json([
                'success' => true,
                'message' => "Data added to period {$period} at slot {$slotIndex}, 12 telemetry rows generated, and recalculated successfully.",
                'data'    => [
                    'id'          => $row->id,
                    'slot_index'  => $row->slot_index,
                    'measured_at' => $row->measured_at->toIso8601String(),
                    'noise_db'    => $row->noise_level,
                    'temperature' => $row->temperature,
                    'humidity'    => $row->humidity,
                    'thi'         => ($row->temperature && $row->humidity)
                                        ? round(0.8 * $row->temperature + ($row->humidity * $row->temperature) / 500, 2)
                                        : null,
                    'is_filled'   => $row->is_filled,
                    'fill_method' => $row->fill_method,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to add data: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Update a single filtered data row, lalu trigger recalculate otomatis
     */
    public function updateNoiseData(Request $request)
    {
        $validated = $request->validate([
            'id'          => 'required|exists:noise_filtered_data,id',
            'noise_db'    => 'required|numeric|min:0|max:200',
            'temperature' => 'required|numeric|min:-50|max:100',
            'humidity'    => 'required|numeric|min:0|max:100',
        ]);

        try {
            $row = NoiseFilteredData::findOrFail($validated['id']);
            $row->update([
                'noise_level' => $validated['noise_db'],
                'temperature' => $validated['temperature'],
                'humidity'    => $validated['humidity'],
                'is_filled'   => false,
                'fill_method' => 'actual',
            ]);

            // Regenerate telemetry rows for this slot
            $this->generateTelemetryForSlot(
                $row->device_id,
                \Carbon\Carbon::parse($row->measured_at),
                (float) $validated['noise_db'],
                (float) $validated['temperature'],
                (float) $validated['humidity']
            );

            // Trigger recalculate otomatis untuk period ini
            $this->triggerRecalculate($row->device_id, $row->period, $row->calculation_date->toDateString());

            return response()->json(['success' => true, 'message' => 'Data updated, telemetry regenerated, and recalculated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Hapus permanen satu row noise_filtered_data beserta telemetry yang di-generate di menit tersebut,
     * lalu trigger recalculate otomatis.
     */
    public function deleteSingleNoiseData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:noise_filtered_data,id',
        ]);

        try {
            $row = NoiseFilteredData::findOrFail($validated['id']);

            $deviceId = $row->device_id;
            $period   = $row->period;
            $date     = $row->calculation_date->toDateString();

            // Hapus telemetry yang di-generate di menit slot ini (window 1 menit)
            $slotMinute    = \Carbon\Carbon::parse($row->measured_at)->second(0);
            $slotMinuteEnd = $slotMinute->copy()->second(59);

            \App\Models\Telemetry::where('device_id', $deviceId)
                ->whereBetween('measured_at', [$slotMinute, $slotMinuteEnd])
                ->where('is_filled', false)
                ->where('fill_method', 'actual')
                ->delete();

            // Hapus row filtered data
            $row->delete();

            // Recalculate periode
            $this->triggerRecalculate($deviceId, $period, $date);

            return response()->json(['success' => true, 'message' => 'Data deleted and recalculated successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete: ' . $e->getMessage()], 422);
        }
    }

    /**
     * Generate 12 realistic telemetry rows (every 5 seconds) for a 1-minute slot.
     * Values are slightly randomized around the given base values to look natural.
     */
    private function generateTelemetryForSlot(
        int $deviceId,
        \Carbon\Carbon $slotTime,
        float $noiseDb,
        float $temperature,
        float $humidity
    ): void {
        // Delete any existing telemetry in this 1-minute window first
        $windowStart = $slotTime->copy()->second(0);
        $windowEnd   = $slotTime->copy()->second(59);

        \App\Models\Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$windowStart, $windowEnd])
            ->delete();

        $rows = [];
        for ($i = 0; $i < 12; $i++) {
            $ts = $slotTime->copy()->second($i * 5);

            // Small random jitter: ±2% for noise, ±0.3°C for temp, ±1% for humidity
            $jitterNoise = $noiseDb    + (mt_rand(-200, 200) / 100);   // ±2 dB
            $jitterTemp  = $temperature + (mt_rand(-30, 30)  / 100);   // ±0.3°C
            $jitterHum   = $humidity    + (mt_rand(-100, 100) / 100);  // ±1%

            // Clamp to sane ranges
            $jitterNoise = max(0,   min(200, round($jitterNoise, 2)));
            $jitterTemp  = max(-50, min(100, round($jitterTemp,  2)));
            $jitterHum   = max(0,   min(100, round($jitterHum,   2)));

            $thi = round(0.8 * $jitterTemp + ($jitterHum * $jitterTemp) / 500, 2);

            $rows[] = [
                'device_id'   => $deviceId,
                'temperature' => $jitterTemp,
                'humidity'    => $jitterHum,
                'thi'         => $thi,
                'noise_db'    => $jitterNoise,
                'measured_at' => $ts->toDateTimeString(),
                'is_filled'   => false,
                'fill_method' => 'actual',
                'created_at'  => now(),
                'updated_at'  => now(),
            ];
        }

        \App\Models\Telemetry::insert($rows);
    }

    /**
     * Helper: trigger recalculate noise period dari filtered data yang sudah ada
     */
    private function triggerRecalculate(int $deviceId, string $period, string $date): void
    {
        try {
            $filteredRows = NoiseFilteredData::where('device_id', $deviceId)
                ->where('period', $period)
                ->whereDate('calculation_date', $date)
                ->orderBy('slot_index')
                ->get();

            if ($filteredRows->isEmpty()) return;

            $rawData = $filteredRows->map(fn($d) => [
                'noise_level' => (float) $d->noise_level,
                'temperature' => (float) $d->temperature,
                'humidity'    => (float) $d->humidity,
            ])->values()->toArray();

            $dataCount = count($rawData);
            $isValid   = $dataCount >= \App\Models\NoiseCalculation::MIN_VALID_DATA_COUNT;

            $statsService = new \App\Services\NoiseStatisticsService();
            $results = $statsService->processCompleteCalculation($rawData);

            $results['data_count']           = $dataCount;
            $results['total_collected']      = $dataCount;
            $results['from_official_period'] = $dataCount;
            $results['is_valid']             = $isValid;
            $results['invalid_reason']       = $isValid
                ? null
                : "INVALID DATA: hanya {$dataCount}/60 data point tersedia untuk periode {$period}.";

            \App\Models\NoiseCalculation::updateOrCreate(
                ['device_id' => $deviceId, 'period' => $period, 'calculation_date' => $date],
                $results
            );

            // Update validity on daily summary too
            $this->updateDailySummaryValidity($deviceId, $date);

        } catch (\Exception $e) {
            Log::error("Admin recalculate failed: " . $e->getMessage());
        }
    }

    /**
     * Update is_valid pada NoiseDailySummary berdasarkan kalkulasi periode terkini.
     * Jika semua periode valid, hitung ulang ls_value, twa_value, dnd_value, dll.
     */
    private function updateDailySummaryValidity(int $deviceId, string $date): void
    {
        $calculations = \App\Models\NoiseCalculation::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->get()
            ->keyBy('period');

        $invalidPeriods = $calculations->filter(fn($c) => !$c->is_valid)->keys()->toArray();

        if (!empty($invalidPeriods)) {
            \App\Models\NoiseDailySummary::updateOrCreate(
                ['device_id' => $deviceId, 'calculation_date' => $date],
                [
                    'is_valid'        => false,
                    'invalid_reason'  => 'INVALID DATA: periode ' . implode(', ', $invalidPeriods)
                        . ' tidak lengkap (data < 60 titik).',
                    'invalid_periods' => $invalidPeriods,
                    // Reset calculated values so stale data is not shown
                    'ls_value'        => null,
                    'twa_value'       => null,
                    'dnd_value'       => null,
                    'allowable_time'  => null,
                ]
            );
            return;
        }

        // All periods are valid — but only recalculate if all 8 periods exist
        if ($calculations->count() < 8) {
            \App\Models\NoiseDailySummary::where('device_id', $deviceId)
                ->whereDate('calculation_date', $date)
                ->update([
                    'is_valid'        => true,
                    'invalid_reason'  => null,
                    'invalid_periods' => null,
                    // Explicitly reset calculated values to NULL so we don't leave
                    // stale data when not all 8 periods are present yet.
                    'ls_value'        => null,
                    'twa_value'       => null,
                    'dnd_value'       => null,
                    'allowable_time'  => null,
                ]);
            return;
        }

        // Full recalculation of daily summary values
        $statsService = new \App\Services\NoiseStatisticsService();

        $periodData = [];
        foreach (['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'] as $p) {
            $calc = $calculations->get($p);
            $periodData[] = [
                'period'         => $p,
                'leq'            => $calc ? (float) $calc->leq_value : 0,
                'duration_hours' => 1,
                'data_count'     => $calc ? (int) $calc->data_count : 0,
            ];
        }

        $ls            = $statsService->calculateLs($periodData);
        $allowableTime = $statsService->calculateAllowableTime($ls);
        $dnd           = $statsService->calculateDND($ls, 8);
        $twa           = $statsService->calculateTWA($dnd);

        // Daily THI average from period calculations
        $thiValues = [];
        foreach (['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'] as $p) {
            $calc = $calculations->get($p);
            if ($calc && $calc->thi_average !== null) {
                $thiValues[] = (float) $calc->thi_average;
            }
        }
        $thiAvgDaily = !empty($thiValues) ? round(array_sum($thiValues) / count($thiValues), 2) : null;

        // Daily temperature & humidity average from telemetry
        $tempAvg = \App\Models\Telemetry::where('device_id', $deviceId)
            ->whereDate('measured_at', $date)
            ->whereNotNull('temperature')
            ->where(function ($q) {
                $q->whereRaw('HOUR(measured_at) BETWEEN 8 AND 11')
                  ->orWhereRaw('HOUR(measured_at) BETWEEN 13 AND 16');
            })
            ->avg('temperature');

        $humAvg = \App\Models\Telemetry::where('device_id', $deviceId)
            ->whereDate('measured_at', $date)
            ->whereNotNull('humidity')
            ->where(function ($q) {
                $q->whereRaw('HOUR(measured_at) BETWEEN 8 AND 11')
                  ->orWhereRaw('HOUR(measured_at) BETWEEN 13 AND 16');
            })
            ->avg('humidity');

        \App\Models\NoiseDailySummary::updateOrCreate(
            ['device_id' => $deviceId, 'calculation_date' => $date],
            [
                'is_valid'              => true,
                'invalid_reason'        => null,
                'invalid_periods'       => null,
                'ls_value'              => $ls,
                'twa_value'             => $twa,
                'dnd_value'             => $dnd,
                'allowable_time'        => $allowableTime,
                'thi_avg_daily'         => $thiAvgDaily,
                'temperature_avg_daily' => $tempAvg !== null ? round((float) $tempAvg, 2) : null,
                'humidity_avg_daily'    => $humAvg !== null ? round((float) $humAvg, 2) : null,
                'l1_leq'                => $calculations->get('L1')?->leq_value,
                'l1_thi_avg'            => $calculations->get('L1')?->thi_average,
                'l2_leq'                => $calculations->get('L2')?->leq_value,
                'l2_thi_avg'            => $calculations->get('L2')?->thi_average,
                'l3_leq'                => $calculations->get('L3')?->leq_value,
                'l3_thi_avg'            => $calculations->get('L3')?->thi_average,
                'l4_leq'                => $calculations->get('L4')?->leq_value,
                'l4_thi_avg'            => $calculations->get('L4')?->thi_average,
                'l5_leq'                => $calculations->get('L5')?->leq_value,
                'l5_thi_avg'            => $calculations->get('L5')?->thi_average,
                'l6_leq'                => $calculations->get('L6')?->leq_value,
                'l6_thi_avg'            => $calculations->get('L6')?->thi_average,
                'l7_leq'                => $calculations->get('L7')?->leq_value,
                'l7_thi_avg'            => $calculations->get('L7')?->thi_average,
                'l8_leq'                => $calculations->get('L8')?->leq_value,
                'l8_thi_avg'            => $calculations->get('L8')?->thi_average,
            ]
        );
    }
}
