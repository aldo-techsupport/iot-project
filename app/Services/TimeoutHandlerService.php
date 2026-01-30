<?php

namespace App\Services;

use App\Models\Device;
use App\Models\NoiseRawData;
use App\Models\NoiseTimeoutLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TimeoutHandlerService
{
    /**
     * Check for missing data points and fill them if necessary
     */
    public function checkAndFillGaps(Device $device, string $period)
    {
        // Define period start and end times
        $dates = $this->getPeriodDates($period);
        if (!$dates) return;

        $startTime = $dates['start'];
        $endTime = $dates['end'];
        $now = now();

        // Don't process future periods
        if ($now->lt($startTime)) return;

        // Limit end time to now if we are currently in the period
        $checkUntil = $now->lt($endTime) ? $now : $endTime;

        // Get the latest data point for this period
        $lastData = NoiseRawData::where('device_id', $device->id)
            ->where('period', $period)
            ->whereDate('measured_at', $startTime->toDateString())
            ->orderBy('measured_at', 'desc')
            ->first();

        // If no data at all, assume start time
        $lastTime = $lastData ? $lastData->measured_at : $startTime;

        // Calculate expected next time (1 second after last data - ESP32 sends every 1s)
        $expectedTime = $lastTime->copy()->addSeconds(1);

        // While expected time is strictly before current time check line (allow 5s buffer)
        // We use 10s buffer to allow slight network delays before declaring timeout
        while ($expectedTime->lte($checkUntil->copy()->subSeconds(10))) {
            
            // Avoid infinite loops - logic check
            if ($expectedTime->lt($startTime)) {
                $expectedTime = $startTime;
                continue;
            }
            
            // Check if data already exists at this exact second (or close to it)
            // We give +/- 1 second tolerance
            $exists = NoiseRawData::where('device_id', $device->id)
                ->where('period', $period)
                ->whereBetween('measured_at', [
                    $expectedTime->copy()->subSeconds(1), 
                    $expectedTime->copy()->addSeconds(1)
                ])
                ->exists();

            if (!$exists) {
                // Only log the timeout, don't fill the data
                $this->logMissingPoint($device, $period, $expectedTime);
            }

            // Move to next expected point (every 1 second)
            $expectedTime->addSeconds(1);
        }
    }

    /**
     * Log a missing data point (timeout) without filling it
     */
    private function logMissingPoint(Device $device, string $period, Carbon $timestamp)
    {
        // Only create log entry, don't fill the data
        NoiseTimeoutLog::create([
            'device_id' => $device->id,
            'period' => $period,
            'expected_at' => $timestamp,
            'detected_at' => now(),
            'status' => 'logged_only', // New status: just logged, not filled
        ]);

        Log::info("Timeout detected for device {$device->id}, period {$period} at {$timestamp->toDateTimeString()}");
    }

    /**
     * Get start and end times for a period today
     * Note: Actual collection starts 3 minutes early and ends 3 minutes late
     * to ensure we can collect enough data points (buffer strategy)
     */
    private function getPeriodDates(string $period): ?array
    {
        $date = now()->toDateString();
        
        // Official period times (for display)
        $officialTimes = [
            'L1' => ['start' => '09:00:00', 'end' => '09:10:00'],
            'L2' => ['start' => '11:00:00', 'end' => '11:10:00'],
            'L3' => ['start' => '14:00:00', 'end' => '14:10:00'],
            'L4' => ['start' => '16:00:00', 'end' => '16:10:00'],
        ];

        if (!isset($officialTimes[$period])) return null;

        // Add 3-minute buffer before and after
        // This allows collection from 08:57-09:13 for L1, etc.
        // Total: 16 minutes = up to 192 data points (5s interval)
        $startTime = Carbon::parse("$date {$officialTimes[$period]['start']}")->subMinutes(3);
        $endTime = Carbon::parse("$date {$officialTimes[$period]['end']}")->addMinutes(3);

        return [
            'start' => $startTime,
            'end' => $endTime,
            'official_start' => Carbon::parse("$date {$officialTimes[$period]['start']}"),
            'official_end' => Carbon::parse("$date {$officialTimes[$period]['end']}"),
        ];
    }
}
