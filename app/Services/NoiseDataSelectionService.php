<?php

namespace App\Services;

use App\Models\Telemetry;
use Carbon\Carbon;

class NoiseDataSelectionService
{
    /**
     * Select up to 60 data points at 1-minute intervals from 1-hour telemetry data
     * 
     * STRATEGY: Flexible data selection - prioritize real data, accept filled if needed
     * 
     * 1. Static period: Always 1 hour (target 60 points @ 1 minute interval)
     * 2. Priority: Real data first (is_filled = false)
     * 3. Fallback: Accept filled data if no real data available
     * 4. Tolerance: ±30 seconds window for matching
     * 
     * Example for L1:
     * - Official: 08:00:00 - 09:00:00 (1 hour)
     * - Result: Up to 60 data points (mix of real and filled if needed)
     * 
     * Benefits:
     * - Flexible: Works even with partial data
     * - Prioritizes real data but doesn't reject filled data
     * - Better data availability
     */
    public static function selectOneMinuteIntervalData($deviceId, $period, $startTime, $endTime)
    {
        // Parse official start and end times
        $officialStart = Carbon::parse($startTime);
        $officialEnd = Carbon::parse($endTime);
        
        // Ensure start time is at 00 seconds
        $officialStart->second(0);
        
        // Get ALL telemetry data from official period (both real and filled)
        $allData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$officialStart, $officialEnd->copy()->addSeconds(10)])
            ->orderBy('measured_at')
            ->get();
        
        // Generate 60 expected timestamps at 1-minute intervals
        $expectedTimestamps = [];
        $current = $officialStart->copy();
        
        for ($i = 0; $i < 60; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addMinutes(1);
        }
        
        // For each expected timestamp, find closest data point
        $selectedData = collect();
        $usedIds = [];
        
        foreach ($expectedTimestamps as $expectedTime) {
            // Priority 1: Find REAL data within ±30 seconds
            $closest = $allData->filter(function($d) use ($expectedTime, $usedIds) {
                return !in_array($d->id, $usedIds) && 
                       !$d->is_filled && // Real data only
                       abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 30;
            })->sortBy(function($d) use ($expectedTime) {
                return abs($d->measured_at->timestamp - $expectedTime->timestamp);
            })->first();
            
            // Priority 2: If no real data, accept filled data
            if (!$closest) {
                $closest = $allData->filter(function($d) use ($expectedTime, $usedIds) {
                    return !in_array($d->id, $usedIds) && 
                           abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 30;
                })->sortBy(function($d) use ($expectedTime) {
                    return abs($d->measured_at->timestamp - $expectedTime->timestamp);
                })->first();
            }
            
            if ($closest) {
                $dataPoint = clone $closest;
                $selectedData->push($dataPoint);
                $usedIds[] = $closest->id;
            }
        }
        
        // Return selected data (may be less than 60 if insufficient data)
        return $selectedData;
    }
}
