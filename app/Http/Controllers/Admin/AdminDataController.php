<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Telemetry;
use App\Models\NoiseRawData;
use App\Models\NoiseDailySummary;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
     * Get noise data for a specific period
     */
    public function getNoiseDataByPeriod(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'date' => 'required|date',
            'period' => 'required|in:L1,L2,L3,L4,L5,L6,L7,L8',
        ]);

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

        $startTime = $validated['date'] . ' ' . $periodTimes[$validated['period']]['start'];
        $endTime = $validated['date'] . ' ' . $periodTimes[$validated['period']]['end'];

        $data = NoiseRawData::where('device_id', $validated['device_id'])
            ->whereBetween('measured_at', [$startTime, $endTime])
            ->orderBy('measured_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'count' => $data->count(),
        ]);
    }

    /**
     * Add new noise data
     */
    public function addNoiseData(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:devices,id',
            'measured_at' => 'required|date',
            'noise_db' => 'required|numeric|min:0|max:200',
            'temperature' => 'required|numeric|min:-50|max:100',
            'humidity' => 'required|numeric|min:0|max:100',
        ]);

        try {
            // Check if data already exists at this timestamp
            $exists = NoiseRawData::where('device_id', $validated['device_id'])
                ->where('measured_at', $validated['measured_at'])
                ->exists();

            if ($exists) {
                return back()->with('error', 'Data already exists at this timestamp');
            }

            $noiseData = NoiseRawData::create([
                'device_id' => $validated['device_id'],
                'measured_at' => $validated['measured_at'],
                'noise_db' => $validated['noise_db'],
                'temperature' => $validated['temperature'],
                'humidity' => $validated['humidity'],
                'is_filled' => true,
                'fill_method' => 'manual',
            ]);

            return back()->with('success', 'Noise data added successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to add data: ' . $e->getMessage());
        }
    }

    /**
     * Update noise data
     */
    public function updateNoiseData(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:noise_raw_data,id',
            'noise_db' => 'required|numeric|min:0|max:200',
            'temperature' => 'required|numeric|min:-50|max:100',
            'humidity' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $noiseData = NoiseRawData::findOrFail($validated['id']);
            $noiseData->update([
                'noise_db' => $validated['noise_db'],
                'temperature' => $validated['temperature'],
                'humidity' => $validated['humidity'],
            ]);

            return back()->with('success', 'Noise data updated successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    /**
     * Delete single noise data
     */
    public function deleteSingleNoiseData(Request $request)
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
}
