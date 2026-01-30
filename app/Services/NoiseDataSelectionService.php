<?php

namespace App\Services;

use App\Models\NoiseRawData;
use Carbon\Carbon;

class NoiseDataSelectionService
{
    /**
     * Select data at 5-second intervals with 2-minute safety buffer before start
     * ESP32 sends every 1 second, we select closest to 5-second marks
     * Returns up to 120 points of REAL data only (no filled data)
     * 
     * Safety buffer: Start 2 minutes earlier to ensure we get 120 data points
     * Example: L1 official 09:00-09:10, we collect from 08:58-09:10 (12 minutes)
     * This gives us 144 possible timestamps, ensuring we get at least 120 real data points
     */
    public static function selectFiveSecondIntervalData($deviceId, $period, $startTime, $endTime)
    {
        // Add 2-minute safety buffer BEFORE start time
        $safetyStart = Carbon::parse($startTime)->subMinutes(2);
        $safetyEnd = Carbon::parse($endTime);
        
        // Get all REAL data in the time range
        $allData = NoiseRawData::where('device_id', $deviceId)
            ->where('period', $period)
            ->where('is_filled', false)
            ->whereBetween('measured_at', [$safetyStart, $safetyEnd])
            ->orderBy('measured_at')
            ->get();
        
        // If we have 120 or fewer, return all
        if ($allData->count() <= 120) {
            return $allData;
        }
        
        // If we have more than 120, select evenly distributed data
        // Strategy: Pick every Nth data point to get exactly 120 points
        $totalData = $allData->count();
        $step = $totalData / 120;
        
        $selectedData = collect();
        for ($i = 0; $i < 120; $i++) {
            $index = (int) floor($i * $step);
            if (isset($allData[$index])) {
                $selectedData->push($allData[$index]);
            }
        }
        
        return $selectedData;
    }
}
