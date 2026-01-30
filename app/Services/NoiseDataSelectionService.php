<?php

namespace App\Services;

use App\Models\Telemetry;
use Carbon\Carbon;

class NoiseDataSelectionService
{
    /**
     * Select exactly 120 data points at 5-second intervals from 24-hour telemetry data
     * 
     * NEW APPROACH: Use telemetry table (24-hour continuous data) instead of noise_raw_data
     * 
     * Strategy:
     * 1. Get all telemetry data from extended period (1 min before official start)
     * 2. Generate 132 expected timestamps at 5-second intervals (11 minutes)
     * 3. For each timestamp, find closest telemetry data within ±2 seconds
     * 4. Return exactly 120 unique data points
     * 
     * Extended period: 1 minute before official start to ensure 120 points
     * Example: L1 official 09:00-09:10, we collect from 08:59-09:10 (11 minutes)
     * 
     * Benefits:
     * - ESP32 sends data 24/7, so we always have backup data
     * - Can calculate retroactively for past periods
     * - More reliable than depending on period detection
     */
    public static function selectFiveSecondIntervalData($deviceId, $period, $startTime, $endTime)
    {
        // Extended period: 1 minute before official start
        $extendedStart = Carbon::parse($startTime)->subMinute();
        $officialEnd = Carbon::parse($endTime);
        
        // Get all telemetry data from extended period (24-hour continuous data)
        $allData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$extendedStart, $officialEnd])
            ->where('is_filled', false) // Only real data
            ->orderBy('measured_at')
            ->get();
        
        // If we have 120 or fewer, return all
        if ($allData->count() <= 120) {
            return $allData;
        }
        
        // Generate 132 expected timestamps at 5-second intervals (11 minutes)
        $expectedTimestamps = [];
        $current = $extendedStart->copy()->second(0);
        
        for ($i = 0; $i < 132; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addSeconds(5);
        }
        
        // For each expected timestamp, find closest data point
        $selectedData = collect();
        $usedIds = [];
        
        foreach ($expectedTimestamps as $expectedTime) {
            // Find closest data within ±2 seconds that hasn't been used
            $closest = $allData->filter(function($d) use ($expectedTime, $usedIds) {
                return !in_array($d->id, $usedIds) && 
                       abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 2;
            })->sortBy(function($d) use ($expectedTime) {
                return abs($d->measured_at->timestamp - $expectedTime->timestamp);
            })->first();
            
            if ($closest) {
                $selectedData->push($closest);
                $usedIds[] = $closest->id;
                
                // Stop when we have 120 points
                if ($selectedData->count() >= 120) {
                    break;
                }
            }
        }
        
        return $selectedData;
    }
}
