<?php

namespace App\Services;

class NoiseStatisticsService
{
 
    public function calculateBasicStats(array $noiseLevels): array
    {
        if (empty($noiseLevels)) {
            return ['min' => null, 'max' => null, 'average' => null];
        }

        return [
            'min' => min($noiseLevels),
            'max' => max($noiseLevels),
            'average' => array_sum($noiseLevels) / count($noiseLevels),
        ];
    }

    public function calculateRange(float $max, float $min): float
    {
        return $max - $min;
    }

    public function calculateClassCount(int $dataCount = 720): float
    {
        return 1 + (3.3 * log10($dataCount));
    }


    public function calculateClassInterval(float $range, float $classCount): float
    {
        if ($classCount == 0) {
            return 0;
        }
        return $range / $classCount;
    }

  
    public function buildFrequencyDistribution(
        array $noiseLevels, 
        float $min, 
        float $interval, 
        float $classCount
    ): array {
        $distribution = [];
        $classes = (int) ceil($classCount);

        // Build intervals
        for ($i = 0; $i < $classes; $i++) {
            $intervalMin = $min + ($i * $interval);
            $intervalMax = $intervalMin + $interval;
            $midpoint = ($intervalMin + $intervalMax) / 2;

            $distribution[] = [
                'interval_min' => round($intervalMin, 2),
                'interval_max' => round($intervalMax, 2),
                'midpoint' => round($midpoint, 2),
                'frequency' => 0,
            ];
        }

        // Count frequencies
        foreach ($noiseLevels as $level) {
            foreach ($distribution as $key => $interval) {
                // Check if level falls in this interval
                $isInInterval = $level >= $interval['interval_min'] && 
                               ($key === $classes - 1 
                                   ? $level <= $interval['interval_max'] 
                                   : $level < $interval['interval_max']);
                
                if ($isInInterval) {
                    $distribution[$key]['frequency']++;
                    break;
                }
            }
        }

        return $distribution;
    }


    public function calculateLeq(array $frequencyDistribution, int $totalData): float
    {
        if ($totalData == 0) {
            return 0;
        }

        $sum = 0;

        foreach ($frequencyDistribution as $interval) {
            $ni = $interval['frequency'];
            $Li = $interval['midpoint'];

            // Calculate: ni × 10^(0.1×Li)
            $sum += $ni * pow(10, 0.1 * $Li);
        }

        // Calculate: 10 × log10(1/N × sum)
        $leq = 10 * log10((1 / $totalData) * $sum);

        return round($leq, 2);
    }


    public function calculateTHI(float $temperature, float $humidity): float
    {
        return 0.8 * $temperature + ($humidity * $temperature) / 500;
    }

    /**
     * Process complete calculation for a period
     * 
     * @param array $rawData Array of data with 'noise_level', 'temperature', 'humidity'
     * @return array Complete calculation results
     */
    public function processCompleteCalculation(array $rawData): array
    {
        // Extract noise levels
        $noiseLevels = array_column($rawData, 'noise_level');
        $temperatures = array_filter(array_column($rawData, 'temperature'));
        $humidities = array_filter(array_column($rawData, 'humidity'));

        // Basic statistics
        $basicStats = $this->calculateBasicStats($noiseLevels);

        // Range and class calculations
        $range = $this->calculateRange($basicStats['max'], $basicStats['min']);
        $classCount = $this->calculateClassCount(count($noiseLevels));
        $classInterval = $this->calculateClassInterval($range, $classCount);

        // Frequency distribution
        $frequencyDistribution = $this->buildFrequencyDistribution(
            $noiseLevels,
            $basicStats['min'],
            $classInterval,
            $classCount
        );

        // Leq calculation
        $leq = $this->calculateLeq($frequencyDistribution, count($noiseLevels));

        // THI calculation (average of all THI values)
        $thiValues = [];
        foreach ($rawData as $data) {
            if (isset($data['temperature']) && isset($data['humidity'])) {
                $thiValues[] = $this->calculateTHI($data['temperature'], $data['humidity']);
            }
        }
        $thiAverage = !empty($thiValues) ? array_sum($thiValues) / count($thiValues) : null;

        return [
            'data_count' => count($noiseLevels),
            'min_value' => $basicStats['min'],
            'max_value' => $basicStats['max'],
            'average_value' => $basicStats['average'],
            'range_value' => $range,
            'class_count' => $classCount,
            'class_interval' => $classInterval,
            'frequency_distribution' => $frequencyDistribution,
            'leq_value' => $leq,
            'thi_average' => $thiAverage ? round($thiAverage, 2) : null,
        ];
    }

    /**
     * Calculate Ls (Leq Siang - Daytime Average Noise Level)
     * Formula: Ls = 10 × log10(1/720 × Σ(Ti × 10^(0.1×Li)))
     * 
     * @param array $periodData Array of ['period' => 'L1', 'leq' => 97.63, 'duration_hours' => 1]
     * @return float Ls value in dB
     */
    public function calculateLs(array $periodData): float
    {
        $sum = 0;
        $totalDataPoints = 720; // Total data points per period (1 hour @ 5s interval)
        
        foreach ($periodData as $data) {
            $Ti = $data['duration_hours'];
            $Li = $data['leq'];
            
            // Calculate: Ti × 10^(0.1×Li)
            $sum += $Ti * pow(10, 0.1 * $Li);
        }
        
        // Calculate: 10 × log10(1/720 × sum)
        $ls = 10 * log10((1 / $totalDataPoints) * $sum);
        
        return round($ls, 2);
    }

    /**
     * Calculate allowable exposure time using NIOSH formula
     * Formula: T = 480 / 2^((L-85)/3)
     * 
     * Note: Formula menggunakan 480 menit sebagai reference time untuk 85 dBA
     *       Hasil dalam menit, kemudian dikonversi ke jam
     * 
     * @param float $noiseLevel Noise level in dB
     * @return float Allowable exposure time in hours
     */
    public function calculateAllowableTime(float $noiseLevel): float
    {
        // T = 480 / 2^((L-85)/3)
        // 480 minutes = reference time for 85 dBA (8 hours)
        // 3 = exchange rate (every 3 dB increase, time is halved)
        $exponent = ($noiseLevel - 85) / 3;
        $allowableTimeMinutes = 480 / pow(2, $exponent);
        
        // Convert to hours
        $allowableTimeHours = $allowableTimeMinutes / 60;
        
        return $allowableTimeHours;
    }

    /**
     * Calculate DND (Daily Noise Dose) using NIOSH method
     * Formula: D(%) = (C/T) × 100%
     * 
     * Following thesis calculation method:
     * - C = 480 minutes (8 hours work day)
     * - T = allowable time in minutes (from formula above)
     * - Result matches reference calculation (755% for Ls=93.78 dB)
     * 
     * @param float $noiseLevel Average noise level (Ls) in dB
     * @param float $exposureTime Actual exposure time in hours (default: 8)
     * @return float DND value in percentage
     */
    public function calculateDND(float $noiseLevel, float $exposureTime = 8): float
    {
        // Calculate allowable time in MINUTES (not hours) for accurate DND calculation
        // T = 480 / 2^((L-85)/3)
        $exponent = ($noiseLevel - 85) / 3;
        $allowableTimeMinutes = 480 / pow(2, $exponent);
        
        // Convert exposure time to minutes
        $exposureTimeMinutes = $exposureTime * 60;
        
        // Calculate DND: D(%) = (C/T) × 100%
        // C = actual exposure time in minutes
        // T = allowable exposure time in minutes
        $dnd = ($exposureTimeMinutes / $allowableTimeMinutes) * 100;
        
        return round($dnd, 2);
    }

    /**
     * Calculate TWA (Time Weighted Average)
     * Formula: TWA = 10 × log(DND/100) + 85
     * 
     * @param float $dnd Dosis harian dalam persen (%)
     * @return float TWA value in dBA
     */
    public function calculateTWA(float $dnd): float
    {
        if ($dnd <= 0) {
            return 0;
        }
        
        $twa = 10 * log10($dnd / 100) + 85;
        
        return round($twa, 2);
    }
}