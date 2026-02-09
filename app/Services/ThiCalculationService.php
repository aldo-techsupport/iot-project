<?php

namespace App\Services;

use App\Models\Device;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ThiCalculationService
{
    /**
     * Calculate THI (Temperature Humidity Index)
     * Formula: THI = (0.8 × T) + ((RH × T) ÷ 500)
     * Where T = temperature in Celsius, RH = relative humidity in percentage
     * 
     * This formula is based on the Livestock Weather Safety Index
     */
    public static function calculateThi(float $temperature, float $humidity): float
    {
        return (0.8 * $temperature) + (($humidity * $temperature) / 500);
    }

    /**
     * Get THI data grouped by 30-minute intervals for a specific date
     */
    public static function getThiDataByDate(int $deviceId, string $date): array
    {
        $startDate = Carbon::parse($date)->startOfDay();
        $endDate = Carbon::parse($date)->endOfDay();

        $telemetries = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$startDate, $endDate])
            ->orderBy('measured_at')
            ->get();

        if ($telemetries->isEmpty()) {
            return [];
        }

        // Group by 30-minute intervals
        $intervals = [];
        
        for ($hour = 0; $hour < 24; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $intervalStart = $startDate->copy()->addHours($hour)->addMinutes($minute);
                $intervalEnd = $intervalStart->copy()->addMinutes(30);
                
                $intervalData = $telemetries->filter(function ($telemetry) use ($intervalStart, $intervalEnd) {
                    return $telemetry->measured_at >= $intervalStart && $telemetry->measured_at < $intervalEnd;
                });

                if ($intervalData->isNotEmpty()) {
                    $avgTemp = $intervalData->avg('temperature');
                    $avgHumidity = $intervalData->avg('humidity');
                    $thi = self::calculateThi($avgTemp, $avgHumidity);

                    $intervals[] = [
                        'time' => $intervalStart->format('H:i'),
                        'hour' => $hour,
                        'minute' => $minute,
                        'temperature' => round($avgTemp, 2),
                        'humidity' => round($avgHumidity, 2),
                        'thi' => round($thi, 2),
                        'data_count' => $intervalData->count(),
                        'interval_start' => $intervalStart->toIso8601String(),
                        'interval_end' => $intervalEnd->toIso8601String(),
                    ];
                }
            }
        }

        return $intervals;
    }

    /**
     * Get THI data grouped by hour (mean of two 30-minute intervals)
     */
    public static function getThiDataByHour(int $deviceId, string $date): array
    {
        $intervalData = self::getThiDataByDate($deviceId, $date);
        
        if (empty($intervalData)) {
            return [];
        }

        // Group by hour
        $hourlyData = [];
        
        foreach ($intervalData as $interval) {
            $hour = $interval['hour'];
            
            if (!isset($hourlyData[$hour])) {
                $hourlyData[$hour] = [
                    'hour' => $hour,
                    'time' => sprintf('%02d:00', $hour),
                    'intervals' => [],
                    'temperature_sum' => 0,
                    'humidity_sum' => 0,
                    'thi_sum' => 0,
                    'count' => 0,
                    'data_count' => 0,
                ];
            }
            
            $hourlyData[$hour]['intervals'][] = $interval;
            $hourlyData[$hour]['temperature_sum'] += $interval['temperature'];
            $hourlyData[$hour]['humidity_sum'] += $interval['humidity'];
            $hourlyData[$hour]['thi_sum'] += $interval['thi'];
            $hourlyData[$hour]['count']++;
            $hourlyData[$hour]['data_count'] += $interval['data_count'];
        }

        // Calculate means
        $result = [];
        foreach ($hourlyData as $hour => $data) {
            $count = $data['count'];
            $result[] = [
                'hour' => $hour,
                'time' => $data['time'],
                'temperature' => round($data['temperature_sum'] / $count, 2),
                'humidity' => round($data['humidity_sum'] / $count, 2),
                'thi' => round($data['thi_sum'] / $count, 2),
                'intervals_count' => $count,
                'data_count' => $data['data_count'],
                'intervals' => $data['intervals'],
            ];
        }

        return $result;
    }

    /**
     * Get THI category based on value
     */
    public static function getThiCategory(float $thi): array
    {
        if ($thi < 68) {
            return ['category' => 'Normal', 'color' => 'green', 'description' => 'No heat stress'];
        } elseif ($thi < 72) {
            return ['category' => 'Alert', 'color' => 'yellow', 'description' => 'Mild heat stress'];
        } elseif ($thi < 79) {
            return ['category' => 'Danger', 'color' => 'orange', 'description' => 'Moderate heat stress'];
        } elseif ($thi < 84) {
            return ['category' => 'Emergency', 'color' => 'red', 'description' => 'Severe heat stress'];
        } else {
            return ['category' => 'Extreme', 'color' => 'purple', 'description' => 'Extreme heat stress'];
        }
    }
}
