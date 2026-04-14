<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Telemetry;
use App\Models\NoiseRawData;
use App\Models\NoiseCalculation;
use Illuminate\Console\Command;
use Carbon\Carbon;

class FillL1Gaps extends Command
{
    protected $signature = 'iot:fill-l1-gaps {device_id} {date?}';
    protected $description = 'Fill gaps in L1 data to reach 120 data points';

    public function handle()
    {
        $deviceId = $this->argument('device_id');
        $date = $this->argument('date') ?? now()->toDateString();
        
        $device = Device::find($deviceId);
        if (!$device) {
            $this->error("Device not found");
            return 1;
        }
        
        $this->info("Filling L1 gaps for {$device->name} on {$date}");
        
        $start = Carbon::parse("{$date} 09:00:00");
        $end = Carbon::parse("{$date} 09:10:00");
        
        // Get existing data
        $existingData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$start, $end])
            ->orderBy('measured_at')
            ->get();
        
        $this->info("Existing data: {$existingData->count()} records");
        
        // Generate expected timestamps (120 points at 5-second intervals)
        $expectedTimes = [];
        $current = $start->copy();
        for ($i = 0; $i < 120; $i++) {
            $expectedTimes[] = $current->copy();
            $current->addSeconds(5);
        }
        
        // Find gaps and fill them
        $lastData = null;
        $filledCount = 0;
        
        foreach ($expectedTimes as $expectedTime) {
            // Find closest data within ±2.5 seconds
            $found = $existingData->first(function($d) use ($expectedTime) {
                return abs($d->measured_at->timestamp - $expectedTime->timestamp) <= 2.5;
            });
            
            if (!$found && $lastData) {
                // Gap found - clone from last data
                $this->line("  Filling gap at {$expectedTime->format('H:i:s')} (clone from {$lastData->measured_at->format('H:i:s')})");
                
                // Create in Telemetry
                $newTelemetry = Telemetry::create([
                    'device_id' => $deviceId,
                    'temperature' => $lastData->temperature,
                    'humidity' => $lastData->humidity,
                    'noise_db' => $lastData->noise_db,
                    'measured_at' => $expectedTime,
                    'is_filled' => true,
                    'fill_method' => 'copied', // Use valid enum value
                ]);
                
                // Create in NoiseRawData
                NoiseRawData::create([
                    'device_id' => $deviceId,
                    'period' => 'L1',
                    'noise_level' => $lastData->noise_db,
                    'temperature' => $lastData->temperature,
                    'humidity' => $lastData->humidity,
                    'measured_at' => $expectedTime,
                    'is_filled' => true,
                    'fill_method' => 'copied', // Use valid enum value
                ]);
                
                $filledCount++;
                $lastData = $newTelemetry;
            } elseif ($found) {
                $lastData = $found;
            }
        }
        
        $this->info("\nFilled {$filledCount} gaps");
        
        // Verify final count
        $finalCount = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [$start, $end])
            ->count();
        
        $this->info("Final data count: {$finalCount}");
        
        // Delete existing calculation to allow recalculation
        $deleted = NoiseCalculation::where('device_id', $deviceId)
            ->where('period', 'L1')
            ->whereDate('calculation_date', $date)
            ->delete();
        
        if ($deleted) {
            $this->info("Deleted existing L1 calculation - ready for recalculation");
        }
        
        return 0;
    }
}
