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
     * OPTIMIZED: Only process active periods and recently ended periods
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
        
        // OPTIMIZATION: Batch check which calculations already exist
        $existingCalculations = NoiseCalculation::whereIn('device_id', $devices->pluck('id'))
            ->whereDate('calculation_date', $now->toDateString())
            ->get()
            ->groupBy('device_id')
            ->map(fn($calcs) => $calcs->pluck('period')->toArray());

        foreach ($periods as $periodName => $times) {
            $periodEnd = Carbon::parse($times['end']);
            $periodStart = Carbon::parse($times['start']);
            
            // Skip periods that haven't started yet
            if ($now->lt($periodStart)) continue;

            $isPeriodActive = $now->between($periodStart, $periodEnd->copy()->addMinutes(2)); // Buffer 2 mins
            // OPTIMIZATION: Only process past periods within 10 minutes after end
            // After that, they should be handled by scheduled cronjobs
            $isPastPeriod = $now->between($periodEnd->copy()->addMinutes(2), $periodEnd->copy()->addMinutes(10));

            if (!$isPeriodActive && !$isPastPeriod) continue;

            $this->info("Checking period {$periodName}...");

            foreach ($devices as $device) {
                // OPTIMIZATION: Use cached calculation check
                $exists = isset($existingCalculations[$device->id]) && 
                         in_array($periodName, $existingCalculations[$device->id]);

                if ($exists) {
                    continue; // Skip silently if calculation exists
                }

                if ($isPeriodActive) {
                    // OPTIMIZATION: Only fill gaps for active periods
                    // Don't log individual device processing to reduce noise
                    $timeoutHandler->checkAndFillGaps($device, $periodName);
                    
                    // Check count for active period
                    $count = NoiseRawData::where('device_id', $device->id)
                        ->where('period', $periodName)
                        ->whereDate('measured_at', $now->toDateString())
                        ->count();

                    if ($count >= 60) {
                        $dashboardController->triggerCalculation(
                            $device->id,
                            $periodName,
                            $now->toDateString()
                        );
                        $this->info("  ✓ {$device->name} - {$periodName}: Calculation triggered ({$count} points)");
                    }
                } elseif ($isPastPeriod) {
                    // Past period cleanup: Fill gaps first, then force calculation
                    $this->info("  → {$device->name} - {$periodName}: Processing past period");
                    
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
                        $this->info("    ✓ Forced calculation triggered with {$count} points");

                        // Check if all periods complete, trigger daily summary
                        $periodsComplete = NoiseCalculation::where('device_id', $device->id)
                            ->whereDate('calculation_date', $now->toDateString())
                            ->count();
                        
                        if ($periodsComplete >= 8) {
                            try {
                                $dashboardController->calculateDailySummary(
                                    new \Illuminate\Http\Request(['device_id' => $device->id])
                                );
                                $this->info("    ✓ Daily Summary calculated");
                            } catch (\Exception $e) {
                                $this->error('Daily summary calculation failed: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
    }
}
