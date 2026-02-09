<?php

namespace App\Services;

class NoiseStatisticsService
{
    /**
     * Calculate basic statistics (min, max, average) from noise level data
     * 
     * @param array $noiseLevels Array of float noise levels in dB
     * @return array ['min' => float, 'max' => float, 'average' => float]
     */
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

    /**
     * Calculate range (r = Lmax - Lmin)
     * 
     * @param float $max Maximum noise level
     * @param float $min Minimum noise level
     * @return float Range value
     */
    public function calculateRange(float $max, float $min): float
    {
        return $max - $min;
    }

    /**
     * Calculate number of classes using Sturges' Rule
     * Formula: k = 1 + 3.3 × log10(n)
     * 
     * @param int $dataCount Number of data points (default: 120)
     * @return float Number of classes
     */
    public function calculateClassCount(int $dataCount = 120): float
    {
        return 1 + (3.3 * log10($dataCount));
    }

    /**
     * Calculate class interval (i = r / k)
     * 
     * @param float $range Range value
     * @param float $classCount Number of classes
     * @return float Class interval
     */
    public function calculateClassInterval(float $range, float $classCount): float
    {
        if ($classCount == 0) {
            return 0;
        }
        return $range / $classCount;
    }

    /**
     * Build frequency distribution table
     * 
     * @param array $noiseLevels Array of noise levels
     * @param float $min Minimum value
     * @param float $interval Class interval
     * @param float $classCount Number of classes (rounded)
     * @return array Array of frequency distribution
     */
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

    /**
     * Calculate Leq (Equivalent Continuous Sound Level)
     * Formula: Leq = 10 × log10(1/N × Σ(ni × 10^(0.1×Li)))
     * 
     * @param array $frequencyDistribution Array from buildFrequencyDistribution()
     * @param int $totalData Total number of data points (N)
     * @return float Leq value in dB
     */
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

    /**
     * Calculate Temperature Humidity Index (THI)
     * Formula: THI = 0.8 × Ta + (RH × Ta) / 500
     * 
     * @param float $temperature Temperature in Celsius
     * @param float $humidity Humidity percentage
     * @return float THI value
     */
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
     * Formula: Ls = 10 × log10(1/8 × Σ(Ti × 10^(0.1×Li)))
     * 
     * @param array $periodData Array of ['period' => 'L1', 'leq' => 97.63, 'duration_hours' => 2]
     * @return float Ls value in dB
     */
    public function calculateLs(array $periodData): float
    {
        $sum = 0;
        $totalHours = 8; // Total daytime hours (8 jam kerja)
        
        foreach ($periodData as $data) {
            $Ti = $data['duration_hours'];
            $Li = $data['leq'];
            
            // Calculate: Ti × 10^(0.1×Li)
            $sum += $Ti * pow(10, 0.1 * $Li);
        }
        
        // Calculate: 10 × log10(1/8 × sum)
        $ls = 10 * log10((1 / $totalHours) * $sum);
        
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
     * @param float $noiseLevel Average noise level (Ls) in dB
     * @param float $exposureTime Actual exposure time in hours (default: 8)
     * @return float DND value in percentage
     */
    public function calculateDND(float $noiseLevel, float $exposureTime = 8): float
    {
        // Calculate allowable time for this noise level
        $allowableTime = $this->calculateAllowableTime($noiseLevel);
        
        // Calculate DND: D(%) = (C/T) × 100%
        // C = actual exposure time
        // T = allowable exposure time
        $dnd = ($exposureTime / $allowableTime) * 100;
        
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