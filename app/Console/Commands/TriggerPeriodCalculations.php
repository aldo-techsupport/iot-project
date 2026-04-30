<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Http\Controllers\IoT\DashboardController;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TriggerPeriodCalculations extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'noise:calculate-periods {--force}';

    /**
     * The console command description.
     */
    protected $description = 'Trigger calculations for completed periods with available data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for completed periods...');
        
        $now = now();
        $today = $now->toDateString();
        
        // Define periods with their end times
        $periods = [
            'L1' => '09:10:00',
            'L2' => '11:10:00',
            'L3' => '14:10:00',
            'L4' => '16:10:00',
        ];
        
        $devices = Device::where('is_active', true)->get();
        $controller = new DashboardController();
        
        foreach ($devices as $device) {
            foreach ($periods as $period => $endTime) {
                $periodEnd = Carbon::parse("$today $endTime");
                
                // Only process if period has ended (with 5 minute grace period)
                if ($now->lt($periodEnd->copy()->addMinutes(5))) {
                    continue;
                }
                
                // Check if calculation already exists for today
                $existingCalc = \App\Models\NoiseCalculation::where('device_id', $device->id)
                    ->where('period', $period)
                    ->whereDate('calculation_date', $today)
                    ->first();
                
                if ($existingCalc && !$this->option('force')) {
                    $this->line("  ⏭️  {$device->name} - {$period}: Already calculated");
                    continue;
                }
                
                // Count available real data (only non-filled data)
                $dataCount = \App\Models\NoiseRawData::where('device_id', $device->id)
                    ->where('period', $period)
                    ->whereDate('measured_at', $today)
                    ->where('is_filled', false)
                    ->count();
                
                // Skip if no real data at all
                if ($dataCount === 0) {
                    $this->warn("  ⚠️  {$device->name} - {$period}: No real data available");
                    continue;
                }
                
                // Trigger calculation with force=true
                try {
                    $result = $controller->triggerCalculation($device->id, $period, $today, true);
                    $data = $result->getData();
                    
                    if ($data->success) {
                        $this->info("  ✅ {$device->name} - {$period}: Calculated with {$dataCount} data points");
                    } else {
                        $this->error("  ❌ {$device->name} - {$period}: {$data->message}");
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ {$device->name} - {$period}: {$e->getMessage()}");
                }
            }
        }
        
        $this->info('✅ Period calculations completed!');
        return 0;
    }
}
