<?php

namespace App\Console\Commands;

use App\Http\Controllers\IoT\DashboardController;
use App\Models\Device;
use Illuminate\Console\Command;

class RecalculateAllNoisePeriods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iot:recalculate-all 
                            {date? : The date (YYYY-MM-DD), defaults to today}
                            {--device= : Specific device ID (optional)}
                            {--period= : Specific period L1/L2/L3/L4 (optional)}
                            {--force : Force recalculation even if already exists}';

    /**
     * Command aliases
     */
    protected $aliases = ['iot:getall'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate noise statistics for all devices and all periods (L1, L2, L3, L4)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? now()->toDateString();
        $specificDevice = $this->option('device');
        $specificPeriod = $this->option('period');
        $force = $this->option('force');

        $this->info("🔄 Starting recalculation for date: {$date}");
        $this->newLine();

        // Get devices
        if ($specificDevice) {
            $devices = Device::where('id', $specificDevice)->get();
            if ($devices->isEmpty()) {
                $this->error("Device ID {$specificDevice} not found");
                return 1;
            }
        } else {
            $devices = Device::all();
        }

        // Get periods
        if ($specificPeriod) {
            $periods = [strtoupper($specificPeriod)];
            if (!in_array($periods[0], ['L1', 'L2', 'L3', 'L4'])) {
                $this->error('Invalid period. Must be L1, L2, L3, or L4');
                return 1;
            }
        } else {
            $periods = ['L1', 'L2', 'L3', 'L4'];
        }

        $this->info("📊 Devices: " . $devices->count());
        $this->info("📊 Periods: " . implode(', ', $periods));
        $this->newLine();

        $controller = new DashboardController();
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $progressBar = $this->output->createProgressBar($devices->count() * count($periods));
        $progressBar->start();

        foreach ($devices as $device) {
            foreach ($periods as $period) {
                try {
                    $response = $controller->triggerCalculation(
                        $device->id,
                        $period,
                        $date,
                        $force
                    );
                    
                    $data = json_decode($response->getContent(), true);
                    
                    if ($data['success']) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    // Log error but continue
                    \Log::error("Failed to calculate {$device->name} - {$period}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('✅ Recalculation completed!');
        $this->newLine();
        
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $results['success']],
                ['❌ Failed', $results['failed']],
                ['Total', $results['success'] + $results['failed']],
            ]
        );

        // Show detailed results (always show, not just verbose)
        $this->newLine();
        $this->info('📋 Detailed Results:');
        $this->newLine();

        foreach ($devices as $device) {
            $this->line("<fg=cyan>Device: {$device->name}</> <fg=gray>(ID: {$device->id})</>");
            
            $deviceHasData = false;
            foreach ($periods as $period) {
                $calc = \App\Models\NoiseCalculation::where('device_id', $device->id)
                    ->where('period', $period)
                    ->whereDate('calculation_date', $date)
                    ->first();
                
                if ($calc) {
                    $deviceHasData = true;
                    $this->line("  <fg=green>{$period}: ✅</> Leq=<fg=yellow>{$calc->leq_value} dB</>, Data=<fg=blue>{$calc->data_count}</>, Min=<fg=magenta>{$calc->min_value}</>, Max=<fg=magenta>{$calc->max_value}</>");
                } else {
                    $this->line("  <fg=red>{$period}: ❌</> No calculation");
                }
            }
            
            if (!$deviceHasData) {
                $this->line("  <fg=gray>No data available for this device</>");
            }
            
            $this->newLine();
        }

        return 0;
    }
}
