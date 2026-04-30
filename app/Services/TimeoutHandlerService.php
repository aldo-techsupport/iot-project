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
     * OPTIMIZED: Uses batch queries instead of checking each second individually
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

        // OPTIMIZATION: Get ALL existing data timestamps at once
        $existingTimestamps = NoiseRawData::where('device_id', $device->id)
            ->where('period', $period)
            ->whereDate('measured_at', $startTime->toDateString())
            ->whereBetween('measured_at', [$startTime, $checkUntil->copy()->subSeconds($bufferSeconds)])
            ->pluck('measured_at')
            ->map(fn($t) => $t->timestamp)
            ->toArray();

        // Convert to set for O(1) lookup
        $existingSet = array_flip($existingTimestamps);

        // Get the latest data point for this period (for cloning)
        $lastData = NoiseRawData::where('device_id', $device->id)
            ->where('period', $period)
            ->whereDate('measured_at', $startTime->toDateString())
            ->orderBy('measured_at', 'desc')
            ->first();

        // If no data at all, skip filling (nothing to clone)
        if (!$lastData) {
            return;
        }

        // Track filled data for summary logging
        $filledCount = 0;
        $firstGapTime = null;
        $lastGapTime = null;
        $batchInserts = [];
        // REMOVED: $batchTelemetry - no longer fill telemetry table

        // Check each second in the period
        $expectedTime = $startTime->copy();
        $endCheck = $checkUntil->copy()->subSeconds($bufferSeconds);

        while ($expectedTime->lte($endCheck)) {
            // Safety check: prevent infinite loops
            if ($filledCount > 3600) {
                Log::error("Gap filling stopped for device {$device->id}, period {$period} - exceeded 3600 fills (1 hour of data)");
                break;
            }

            // Check if data exists at this timestamp (with 1 second tolerance)
            $timestamp = $expectedTime->timestamp;
            $exists = isset($existingSet[$timestamp]) || 
                     isset($existingSet[$timestamp - 1]) || 
                     isset($existingSet[$timestamp + 1]);

            if (!$exists) {
                // Track gap range
                if ($filledCount === 0) {
                    $firstGapTime = $expectedTime->copy();
                }
                $lastGapTime = $expectedTime->copy();

                // Prepare batch insert data (ONLY for NoiseRawData, NOT Telemetry)
                $batchInserts[] = [
                    'device_id' => $device->id,
                    'period' => $period,
                    'noise_level' => $lastData->noise_level,
                    'temperature' => $lastData->temperature,
                    'humidity' => $lastData->humidity,
                    'measured_at' => $expectedTime->copy(),
                    'is_filled' => true,
                    'fill_method' => 'copied',
                    'consecutive_timeouts' => ($lastData->consecutive_timeouts ?? 0) + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // REMOVED: No longer add to $batchTelemetry

                $filledCount++;

                // Batch insert every 100 records to avoid memory issues
                if (count($batchInserts) >= 100) {
                    NoiseRawData::insert($batchInserts);
                    // REMOVED: Telemetry::insert($batchTelemetry);
                    $batchInserts = [];
                }
            }

            // Move to next expected point (every 1 second)
            $expectedTime->addSeconds(1);
        }

        // Insert remaining batch
        if (count($batchInserts) > 0) {
            NoiseRawData::insert($batchInserts);
            // REMOVED: Telemetry::insert($batchTelemetry);
        }

        // Log timeout entries if gaps were filled
        // OPTIMIZATION: Only log if this gap hasn't been logged in the last 5 minutes
        if ($filledCount > 0) {
            $recentLog = NoiseTimeoutLog::where('device_id', $device->id)
                ->where('period', $period)
                ->where('expected_at', '>=', $firstGapTime->copy()->subMinutes(5))
                ->where('expected_at', '<=', $firstGapTime->copy()->addMinutes(5))
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();
            
            if (!$recentLog) {
                NoiseTimeoutLog::create([
                    'device_id' => $device->id,
                    'period' => $period,
                    'expected_at' => $firstGapTime,
                    'consecutive_count' => $filledCount,
                    'action_taken' => 'copied_previous',
                    'details' => "Filled {$filledCount} missing data points (from {$firstGapTime->toDateTimeString()} to {$lastGapTime->toDateTimeString()})",
                ]);

                Log::info("Filled {$filledCount} missing data points for device {$device->id}, period {$period} (from {$firstGapTime->toDateTimeString()} to {$lastGapTime->toDateTimeString()})");
            }
        }
    }

    /**
     * Fill a missing data point by cloning the last available data
     * Returns the filled data for chaining
     * 
     * NOTE: Only fills NoiseRawData, NOT Telemetry (to keep telemetry log clean)
     * 
     * @param bool $logIndividual Whether to log each individual fill (default: false to prevent spam)
     */
    private function fillMissingPoint(Device $device, string $period, Carbon $timestamp, $lastData, bool $logIndividual = false)
    {
        // Log the timeout
        NoiseTimeoutLog::create([
            'device_id' => $device->id,
            'period' => $period,
            'expected_at' => $timestamp,
            'consecutive_count' => ($lastData->consecutive_timeouts ?? 0) + 1,
            'action_taken' => 'copied_previous',
            'details' => 'Data filled by copying last available data (NoiseRawData only)',
        ]);

        // If we have previous data, clone it with new timestamp
        if ($lastData) {
            // Fill NoiseRawData ONLY (not Telemetry)
            $filledData = NoiseRawData::create([
                'device_id' => $device->id,
                'period' => $period,
                'noise_level' => $lastData->noise_level,
                'temperature' => $lastData->temperature,
                'humidity' => $lastData->humidity,
                'measured_at' => $timestamp,
                'is_filled' => true,
                'fill_method' => 'copied',
                'consecutive_timeouts' => ($lastData->consecutive_timeouts ?? 0) + 1,
            ]);

            // REMOVED: No longer fill Telemetry table
            // This keeps the telemetry log clean (only real data)

            // Only log individual fills if explicitly requested (to prevent log spam)
            if ($logIndividual) {
                Log::debug("Filled missing data for device {$device->id}, period {$period} at {$timestamp->toDateTimeString()} (NoiseRawData only)");
            }
            
            return $filledData;
        } else {
            // No previous data to clone, only log warning if individual logging is enabled
            if ($logIndividual) {
                Log::warning("Cannot fill missing data for device {$device->id}, period {$period} at {$timestamp->toDateTimeString()} - no previous data available");
            }
            
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
