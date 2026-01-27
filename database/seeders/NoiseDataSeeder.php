<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NoiseDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first device (or create if none exists)
        $device = \App\Models\Device::first();
        
        if (!$device) {
            echo "No devices found. Please create a device first.\n";
            return;
        }

        echo "Seeding noise data for device: {$device->name}\n";

        // Generate data for L1 period (today)
        // Based on example data: range 66-103 dB, focused around 90-100 dB
        $periods = ['L1', 'L2', 'L3', 'L4'];
        $today = now();

        foreach ($periods as $period) {
            echo "Generating data for period {$period}...\n";
            
            // Define time for each period
            $baseTime = match($period) {
                'L1' => $today->copy()->setHour(8)->setMinute(0),
                'L2' => $today->copy()->setHour(10)->setMinute(0),
                'L3' => $today->copy()->setHour(13)->setMinute(0),
                'L4' => $today->copy()->setHour(15)->setMinute(0),
            };

            // Generate 120 data points (every 5 seconds)
            for ($i = 0; $i < 120; $i++) {
                $measuredAt = $baseTime->copy()->addSeconds($i * 5);
                
                // Generate noise level based on realistic distribution
                // Simulate the frequency distribution from the example
                $rand = rand(0, 119);
                
                if ($rand < 2) {
                    // 2 data points in 66-70.7 range
                    $noiseLevel = rand(6600, 7070) / 100;
                } elseif ($rand < 19) {
                    // 17 data points in 90.0-94.7 range
                    $noiseLevel = rand(9000, 9470) / 100;
                } elseif ($rand < 107) {
                    // 88 data points in 94.8-99.5 range (peak)
                    $noiseLevel = rand(9480, 9950) / 100;
                } else {
                    // 13 data points in 99.6-104.4 range
                    $noiseLevel = rand(9960, 10440) / 100;
                }

                // Generate realistic temperature and humidity
                $temperature = rand(2700, 3200) / 100; // 27-32°C
                $humidity = rand(6000, 8500) / 100; // 60-85%

                \App\Models\NoiseRawData::create([
                    'device_id' => $device->id,
                    'period' => $period,
                    'noise_level' => $noiseLevel,
                    'temperature' => $temperature,
                    'humidity' => $humidity,
                    'measured_at' => $measuredAt,
                ]);
            }

            echo "  ✓ Created 120 data points for {$period}\n";
        }

        echo "\n✅ Seed complete! Total 480 data points created.\n";
        echo "Now triggering calculations...\n\n";

        // Trigger calculations for all periods
        $statsService = new \App\Services\NoiseStatisticsService();
        
        foreach ($periods as $period) {
            $rawData = \App\Models\NoiseRawData::where('device_id', $device->id)
                ->where('period', $period)
                ->whereDate('measured_at', $today->toDateString())
                ->get()
                ->map(fn($d) => [
                    'noise_level' => (float) $d->noise_level,
                    'temperature' => (float) $d->temperature,
                    'humidity' => (float) $d->humidity,
                ])
                ->toArray();

            if (count($rawData) >= 120) {
                $results = $statsService->processCompleteCalculation($rawData);
                
                $calculation = \App\Models\NoiseCalculation::create([
                    'device_id' => $device->id,
                    'period' => $period,
                    'calculation_date' => $today->toDateString(),
                    ...$results,
                ]);

                echo "  ✓ {$period} - Leq: {$calculation->leq_value} dB (Min: {$calculation->min_value}, Max: {$calculation->max_value})\n";
            }
        }

        echo "\n🎉 All done! Visit /iot/noise-monitoring to view the dashboard.\n";
    }
}
