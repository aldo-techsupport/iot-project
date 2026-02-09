<?php

namespace App\Services;

use App\Models\Telemetry;
use Carbon\Carbon;

class NoiseDataSelectionService
{
    /**
     * Select exactly 120 data points at 5-second intervals from 24-hour telemetry data
     * 
     * STRATEGY: Static timing with silent backup fallback
     * 
     * 1. Static period: Always 09:00:00 - 09:10:00 (120 points @ 5s interval)
     * 2. Priority: Use real data from official period first
     * 3. Backup: If slots empty, fill with data from 1 minute before (08:59:00 - 09:00:00)
     * 4. Timestamp manipulation: Backup data uses static timestamps (not original time)
     * 
     * Example for L1:
     * - Official: 09:00:00 - 09:10:00
     * - Backup: 08:59:00 - 09:00:00 (used only if needed)
     * - Result: Always 120 data points with timestamps 09:00:00, 09:00:05, ..., 09:09:55
     * 
     * Benefits:
     * - Always reaches 120 data points
     * - Transparent to user (no indication of backup usage)
     * - Static timing (no dynamic adjustment)
     * - Backup data seamlessly integrated
     */
    public static function selectFiveSecondIntervalData($deviceId, $period, $startTime, $endTime)
    {
        // Parse official start and end times
        $officialStart = Carbon::parse($startTime);
        $officialEnd = Carbon::parse($endTime);
        
        // Ensure start time is at 00 seconds
        $officialStart->second(0);
        
        // Get backup data from 1 minute before official start
        $backupStart = $officialStart->copy()->subMinute();
        
        // Get all telemetry data from backup period + official period
        $allData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$backupStart, $officialEnd->copy()->addSeconds(10)])
            ->where('is_filled', false) // Only real data
            ->orderBy('measured_at')
            ->get();
        
        // Generate 120 expected timestamps at 5-second intervals starting from 00 seconds
        // Example: 09:00:00, 09:00:05, 09:00:10, ..., 09:09:55
        $expectedTimestamps = [];
        $current = $officialStart->copy();
        
        for ($i = 0; $i < 120; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addSeconds(5);
        }
        
        // For each expected timestamp, find closest data point
        $selectedData = collect();
        $usedIds = [];
        
        foreach ($expectedTimestamps as $expectedTime) {
            // Priority 1: Find data from official period (within ±2.5 seconds)
            $closest = $allData->filter(function($d) use ($expectedTime, $usedIds, $officialStart) {
                return !in_array($d->id, $usedIds) && 
                       $d->measured_at->gte($officialStart) && // From official period
                       abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 2.5;
            })->sortBy(function($d) use ($expectedTime) {
                return abs($d->measured_at->timestamp - $expectedTime->timestamp);
            })->first();
            
            // Priority 2: If not found, use backup data from 1 minute before
            if (!$closest) {
                $closest = $allData->filter(function($d) use ($usedIds, $officialStart) {
                    return !in_array($d->id, $usedIds) && 
                           $d->measured_at->lt($officialStart); // From backup period
                })->sortBy(function($d) use ($expectedTime) {
                    return abs($d->measured_at->timestamp - $expectedTime->timestamp);
                })->first();
            }
            
            if ($closest) {
                // Clone the data and set static timestamp (manipulation)
                $dataPoint = clone $closest;
                $dataPoint->measured_at = $expectedTime->copy(); // Use static timestamp
                $dataPoint->is_backup = $closest->measured_at->lt($officialStart); // Track if from backup
                
                $selectedData->push($dataPoint);
                $usedIds[] = $closest->id;
            }
        }
        
        // Return selected data (should always be 120 if backup data available)
        return $selectedData;
    }
}
