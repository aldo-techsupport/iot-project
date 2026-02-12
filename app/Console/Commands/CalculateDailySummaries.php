<?php

namespace App\Console\Commands;

use App\Http\Controllers\IoT\DashboardController;
use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class CalculateDailySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iot:calculate-daily 
                            {date? : The date (YYYY-MM-DD), defaults to today}
                            {--device= : Specific device ID (optional)}
                            {--force : Force recalculation (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily summary (Ls, TWA, DND) for all devices that have 4 periods completed';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? now()->toDateString();
        $specificDevice = $this->option('device');
        
        $this->info("🔄 Starting daily summary calculation for date: {$date}");
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

        $controller = new DashboardController();
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $progressBar = $this->output->createProgressBar($devices->count());
        $progressBar->start();

        foreach ($devices as $device) {
            try {
                // Check if all 4 periods exist first to avoid unnecessary errors
                $count = \App\Models\NoiseCalculation::where('device_id', $device->id)
                    ->whereDate('calculation_date', $date)
                    ->whereIn('period', ['L1', 'L2', 'L3', 'L4'])
                    ->count();
                
                if ($count < 4) {
                    $results['skipped']++;
                    // $this->line("  Skipping {$device->name}: Only {$count}/4 periods found.");
                    $progressBar->advance();
                    continue;
                }

                // Verify if summary already exists unless force
                if (!$this->option('force')) {
                    $exists = \App\Models\NoiseDailySummary::where('device_id', $device->id)
                        ->whereDate('calculation_date', $date)
                        ->exists();
                    
                    if ($exists) {
                        $results['skipped']++;
                        $progressBar->advance();
                        continue;
                    }
                }

                // Call controller method
                $request = new Request([
                    'device_id' => $device->id,
                    'date' => $date,
                ]);
                
                $response = $controller->calculateDailySummary($request);
                $data = $response->getData(true); // Get array data
                
                if ($data['success']) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                    \Log::warning("Failed to calculate daily summary for {$device->name}: " . ($data['message'] ?? 'Unknown error'));
                }

            } catch (\Exception $e) {
                $results['failed']++;
                \Log::error("Exception calculating daily summary for {$device->name}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info('✅ Daily Summary Calculation completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Success', $results['success']],
                ['⏭️ Skipped', $results['skipped']],
                ['❌ Failed', $results['failed']],
                ['Total', $devices->count()],
            ]
        );

        return 0;
    }
}
