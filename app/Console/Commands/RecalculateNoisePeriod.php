<?php

namespace App\Console\Commands;

use App\Http\Controllers\IoT\DashboardController;
use Illuminate\Console\Command;

class RecalculateNoisePeriod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noise:recalculate 
                            {device_id : The device ID}
                            {period : The period (L1, L2, L3, L4)}
                            {date? : The date (YYYY-MM-DD), defaults to today}
                            {--force : Force recalculation even if already exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate noise statistics for a specific period using telemetry data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $deviceId = $this->argument('device_id');
        $period = strtoupper($this->argument('period'));
        $date = $this->argument('date') ?? now()->toDateString();
        $force = $this->option('force');

        // Validate period
        if (!in_array($period, ['L1', 'L2', 'L3', 'L4'])) {
            $this->error('Invalid period. Must be L1, L2, L3, or L4');
            return 1;
        }

        $this->info("Recalculating noise data for Device {$deviceId}, Period {$period}, Date {$date}");
        
        try {
            $controller = new DashboardController();
            $response = $controller->triggerCalculation($deviceId, $period, $date, $force);
            
            $data = json_decode($response->getContent(), true);
            
            if ($data['success']) {
                $this->info('✅ Calculation successful!');
                $this->table(
                    ['Metric', 'Value'],
                    [
                        ['Data Count', $data['data']['data_count']],
                        ['Total Collected', $data['data']['total_collected']],
                        ['From Official Period', $data['data']['from_official_period']],
                        ['Leq', $data['data']['leq_value'] . ' dB'],
                        ['Min', $data['data']['min_value'] . ' dB'],
                        ['Max', $data['data']['max_value'] . ' dB'],
                        ['Average', $data['data']['average_value'] . ' dB'],
                    ]
                );
                return 0;
            } else {
                $this->error('❌ Calculation failed: ' . ($data['message'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
