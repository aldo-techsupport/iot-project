<?php

namespace App\Console\Commands;

use App\Http\Controllers\IoT\DashboardController;
use App\Models\Device;
use App\Models\NoiseCalculation;
use App\Models\NoiseRawData;
use App\Services\TimeoutHandlerService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class CheckDataTimeouts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iot:check-timeouts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missing data points in monitoring periods (active and past) and fill them';

    /**
     * Execute the console command.
     */
    public function handle(TimeoutHandlerService $timeoutHandler, DashboardController $dashboardController)
    {
        $now = now();
        
        // Official period times - exact timing starting at 00 seconds
        // Data collection uses these exact times with tolerance for finding closest data
        // Skip 12:00-13:00 (lunch break)
        $periods = [
            'L1' => ['start' => '08:00', 'end' => '09:00'],
            'L2' => ['start' => '09:00', 'end' => '10:00'],
            'L3' => ['start' => '10:00', 'end' => '11:00'],
            'L4' => ['start' => '11:00', 'end' => '12:00'],
            // SKIP: 12:00-13:00 (lunch break)
            'L5' => ['start' => '13:00', 'end' => '14:00'],
            'L6' => ['start' => '14:00', 'end' => '15:00'],
            'L7' => ['start' => '15:00', 'end' => '16:00'],
            'L8' => ['start' => '16:00', 'end' => '17:00'],
        ];

        $devices = Device::where('is_active', true)->get();

        foreach ($periods as $periodName => $times) {
            $periodEnd = Carbon::parse($times['end']);
            $periodStart = Carbon::parse($times['start']);
            
            // Skip periods that haven't started yet
            if ($now->lt($periodStart)) continue;

            $isPeriodActive = $now->between($periodStart, $periodEnd->copy()->addMinutes(2)); // Buffer 2 mins
            $isPastPeriod = $now->gt($periodEnd->copy()->addMinutes(2));

            if (!$isPeriodActive && !$isPastPeriod) continue;

            $this->info("Checking period {$periodName}...");

            foreach ($devices as $device) {
                // Check if calculation already exists
                $exists = NoiseCalculation::where('device_id', $device->id)
                    ->where('period', $periodName)
                    ->whereDate('calculation_date', $now->toDateString())
                    ->exists();

                if ($exists) {
                    $this->info("  - Device {$device->name}: Calculation already exists. Skipping.");
                    continue;
                }

                $this->info("  - Processing device: {$device->name} ({$device->id})");

                if ($isPeriodActive) {
                    // Normal processing for active period
                    $timeoutHandler->checkAndFillGaps($device, $periodName);
                } elseif ($isPastPeriod) {
                    // Past period cleanup: Fill gaps first, then force calculation
                    $this->info("    - Past period detected. Filling gaps and forcing calculation.");
                    
                    // Fill gaps for past period
                    $timeoutHandler->checkAndFillGaps($device, $periodName);
                    
                    // Check count after filling
                    $count = NoiseRawData::where('device_id', $device->id)
                        ->where('period', $periodName)
                        ->whereDate('measured_at', $now->toDateString())
                        ->count();
                        
                    // If we have some data, trigger with force=true
                    if ($count > 0) {
                         $dashboardController->triggerCalculation(
                            $device->id,
                            $periodName,
                            $now->toDateString(),
                            true // Force calculation
                        );
                        $this->info("    - Forced calculation triggered with {$count} points.");

                        // Check if all periods complete, trigger daily summary
                        $periodsComplete = NoiseCalculation::where('device_id', $device->id)
                            ->whereDate('calculation_date', $now->toDateString())
                            ->count();
                        
                        if ($periodsComplete >= 4) {
                            try {
                                $dashboardController->calculateDailySummary(
                                    new \Illuminate\Http\Request(['device_id' => $device->id])
                                );
                                $this->info("    - Daily Summary Calculation triggered.");
                            } catch (\Exception $e) {
                                $this->error('Daily summary calculation failed: ' . $e->getMessage());
                            }
                        }
                    } else {
                        $this->info("    - No data found for this period. Skipping.");
                    }
                    continue; 
                }

                // Check count again for active period
                $count = NoiseRawData::where('device_id', $device->id)
                    ->where('period', $periodName)
                    ->whereDate('measured_at', $now->toDateString())
                    ->count();

                if ($count >= 720) {
                    $dashboardController->triggerCalculation(
                        $device->id,
                        $periodName,
                        $now->toDateString()
                    );
                    $this->info("  - 720 points reached. Calculation triggered.");
                    
                    // Check if all periods complete, trigger daily summary
                    $periodsComplete = NoiseCalculation::where('device_id', $device->id)
                        ->whereDate('calculation_date', $now->toDateString())
                        ->count();
                    
                    if ($periodsComplete >= 8) {
                        try {
                            $dashboardController->calculateDailySummary(
                                new \Illuminate\Http\Request(['device_id' => $device->id])
                            );
                            $this->info("  - Daily Summary Calculation triggered.");
                        } catch (\Exception $e) {
                            $this->error('Daily summary calculation failed: ' . $e->getMessage());
                        }
                    }
                } else {
                    $this->info("  - Current count: {$count}/120");
                }
            }
        }
    }
}
