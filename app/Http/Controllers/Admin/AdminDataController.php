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
            Artisan::call('noise:recalculate-period', [
                'device_id' => $validated['device_id'],
                'date' => $validated['date'],
                'period' => $validated['period'],
            ]);

            return back()->with('success', 'Noise period recalculated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to recalculate: ' . $e->getMessage());
        }
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
            Artisan::call('noise:recalculate-daily', [
                'device_id' => $validated['device_id'],
                'date' => $validated['date'],
            ]);

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
            'measured_at' => 'required|date',
            'noise_db'    => 'required|numeric|min:0|max:200',
            'temperature' => 'required|numeric|min:-50|max:100',
            'humidity'    => 'required|numeric|min:0|max:100',
        ]);

        // Map time → period
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

        $measuredAt = \Carbon\Carbon::parse($validated['measured_at']);
        $timeStr    = $measuredAt->format('H:i');
        $period     = null;

        foreach ($periodTimes as $p => $range) {
            if ($timeStr >= $range['start'] && $timeStr < $range['end']) {
                $period = $p;
                break;
            }
        }

        if (!$period) {
            return back()->with('error', 'Timestamp is outside any valid measurement period (L1–L8: 08:00–12:00, 13:00–17:00).');
        }

        $calculationDate = $measuredAt->toDateString();
        $periodStart     = \Carbon\Carbon::parse($calculationDate . ' ' . $periodTimes[$period]['start'] . ':00');

        // slot_index = minutes elapsed since period start (0-based)
        $slotIndex = (int) $periodStart->diffInMinutes($measuredAt);

        try {
            NoiseFilteredData::create([
                'device_id'        => $validated['device_id'],
                'period'           => $period,
                'calculation_date' => $calculationDate,
                'noise_level'      => $validated['noise_db'],
                'temperature'      => $validated['temperature'],
                'humidity'         => $validated['humidity'],
                'measured_at'      => $measuredAt->toDateTimeString(),
                'is_filled'        => true,
                'fill_method'      => 'manual',
                'slot_index'       => $slotIndex,
            ]);

            $this->triggerRecalculate((int) $validated['device_id'], $period, $calculationDate);

            return back()->with('success', "Data added to period {$period} at slot {$slotIndex} and recalculated successfully.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add data: ' . $e->getMessage());
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
                'is_filled'   => true,
                'fill_method' => 'manual',
            ]);

            // Trigger recalculate otomatis untuk period ini
            $this->triggerRecalculate($row->device_id, $row->period, $row->calculation_date->toDateString());

            return back()->with('success', 'Data updated and recalculated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Reset slot ke data terdekat (filled copy) — tidak benar-benar hapus agar tetap 60 slot
     */
    public function deleteSingleNoiseData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:noise_filtered_data,id',
        ]);

        try {
            $row = NoiseFilteredData::findOrFail($validated['id']);

            // Cari data terdekat dari telemetry untuk mengisi ulang slot ini
            $expectedTime = \Carbon\Carbon::parse(
                $row->calculation_date->toDateString() . ' 08:00:00'
            )->addMinutes($row->slot_index);

            // Ambil data terdekat dari telemetry (selain slot ini sendiri)
            $nearest = \App\Models\Telemetry::where('device_id', $row->device_id)
                ->whereDate('measured_at', $row->calculation_date->toDateString())
                ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, measured_at, ?))', [$expectedTime])
                ->first();

            if ($nearest) {
                $row->update([
                    'noise_level' => (float) $nearest->noise_db,
                    'temperature' => (float) $nearest->temperature,
                    'humidity'    => (float) $nearest->humidity,
                    'measured_at' => $expectedTime->toDateTimeString(),
                    'is_filled'   => true,
                    'fill_method' => 'copied',
                ]);
            } else {
                // Tidak ada data sama sekali — set ke 0
                $row->update([
                    'noise_level' => 0,
                    'is_filled'   => true,
                    'fill_method' => 'zero',
                ]);
            }

            // Trigger recalculate
            $this->triggerRecalculate($row->device_id, $row->period, $row->calculation_date->toDateString());

            return back()->with('success', 'Slot reset and recalculated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to reset: ' . $e->getMessage());
        }
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

            $statsService = new \App\Services\NoiseStatisticsService();
            $results = $statsService->processCompleteCalculation($rawData);
            $results['data_count']          = count($rawData);
            $results['total_collected']     = count($rawData);
            $results['from_official_period'] = count($rawData);

            \App\Models\NoiseCalculation::updateOrCreate(
                ['device_id' => $deviceId, 'period' => $period, 'calculation_date' => $date],
                $results
            );
        } catch (\Exception $e) {
            Log::error("Admin recalculate failed: " . $e->getMessage());
        }
    }
}
