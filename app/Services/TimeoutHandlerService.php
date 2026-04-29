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
        // For past periods, check until the end time (no buffer needed)
        if ($now->lt($endTime)) {
            // Active period: check until now with buffer
            $checkUntil = $now;
            $bufferSeconds = 10; // Allow 10s buffer for active periods
        } else {
            // Past period: check until end time without buffer
            $checkUntil = $endTime;
            $bufferSeconds = 0; // No buffer for past periods
        }

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

        // While expected time is strictly before current time check line
        // Use buffer for active periods, no buffer for past periods
        while ($expectedTime->lte($checkUntil->copy()->subSeconds($bufferSeconds))) {
            
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
                // Fill missing data by cloning the last available data
                $filledData = $this->fillMissingPoint($device, $period, $expectedTime, $lastData);
                
                // Update lastData reference to the filled data for next iteration
                if ($filledData) {
                    $lastData = $filledData;
                }
            }

            // Move to next expected point (every 1 second)
            $expectedTime->addSeconds(1);
        }
    }

    /**
     * Fill a missing data point by cloning the last available data
     * Returns the filled data for chaining
     */
    private function fillMissingPoint(Device $device, string $period, Carbon $timestamp, $lastData)
    {
        // Log the timeout
        NoiseTimeoutLog::create([
            'device_id' => $device->id,
            'period' => $period,
            'expected_at' => $timestamp,
            'consecutive_count' => ($lastData->consecutive_timeouts ?? 0) + 1,
            'action_taken' => 'copied_previous',
            'details' => 'Data filled by copying last available data',
        ]);

        // If we have previous data, clone it with new timestamp
        if ($lastData) {
            // Fill NoiseRawData
            $filledData = NoiseRawData::create([
                'device_id' => $device->id,
                'period' => $period,
                'noise_level' => $lastData->noise_level,
                'temperature' => $lastData->temperature,
                'humidity' => $lastData->humidity,
                'measured_at' => $timestamp,
                'is_filled' => true,
                'fill_method' => 'copied', // Use valid enum value
                'consecutive_timeouts' => ($lastData->consecutive_timeouts ?? 0) + 1,
            ]);

            // Also fill Telemetry table for consistency
            \App\Models\Telemetry::create([
                'device_id' => $device->id,
                'temperature' => $lastData->temperature,
                'humidity' => $lastData->humidity,
                'noise_db' => $lastData->noise_level,
                'measured_at' => $timestamp,
                'is_filled' => true,
                'fill_method' => 'copied', // Use valid enum value
            ]);

            Log::info("Filled missing data for device {$device->id}, period {$period} at {$timestamp->toDateTimeString()} by cloning previous data");
            
            return $filledData;
        } else {
            // No previous data to clone, just log
            Log::warning("Cannot fill missing data for device {$device->id}, period {$period} at {$timestamp->toDateTimeString()} - no previous data available");
            
            return null;
        }
    }

    /**
     * Get start and end times for a period today
     * Note: Uses exact official times starting at 00 seconds
     * Example: L1 starts at 09:00:00, data collected at 09:00:00, 09:00:05, 09:00:10, etc.
     */
    private function getPeriodDates(string $period): ?array
    {
        $date = now()->toDateString();
        
        // Official period times - exact timing at 00 seconds (8 periods, skip 12-13 lunch)
        $officialTimes = [
            'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
            'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
            'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
            'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
            'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
            'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
            'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
            'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
        ];

        if (!isset($officialTimes[$period])) return null;

        // Use exact official times without buffer
        // Data selection will handle timeouts by finding closest available data
        $startTime = Carbon::parse("$date {$officialTimes[$period]['start']}");
        $endTime = Carbon::parse("$date {$officialTimes[$period]['end']}");

        return [
            'start' => $startTime,
            'end' => $endTime,
            'official_start' => $startTime->copy(),
            'official_end' => $endTime->copy(),
        ];
    }
}
