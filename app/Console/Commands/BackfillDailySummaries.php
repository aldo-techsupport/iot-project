<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Device;
use App\Models\NoiseCalculation;
use App\Models\NoiseDailySummary;
use App\Services\NoiseStatisticsService;
use Carbon\Carbon;

class BackfillDailySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noise:backfill-daily-summaries 
                            {--device= : Specific device ID to backfill}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill daily summaries (Ls, TWA, DND) for dates with complete period calculations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting daily summary backfill...');

        // Get devices
        $deviceId = $this->option('device');
        $devices = $deviceId 
            ? Device::where('id', $deviceId)->get()
            : Device::all();

        if ($devices->isEmpty()) {
            $this->error('No devices found!');
            return 1;
        }

        // Get date range
        $from = $this->option('from') 
            ? Carbon::parse($this->option('from'))
            : Carbon::now()->subDays(30);
        
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : Carbon::now();

        $this->info("Date range: {$from->toDateString()} to {$to->toDateString()}");
        $this->info("Devices: " . $devices->count());
        $this->newLine();

        $statsService = new NoiseStatisticsService();
        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($devices as $device) {
            $this->info("Processing Device: {$device->name} (ID: {$device->id})");

            $currentDate = $from->copy();
            while ($currentDate->lte($to)) {
                $dateStr = $currentDate->toDateString();

                // Check if daily summary already exists
                $existingSummary = NoiseDailySummary::where('device_id', $device->id)
                    ->whereDate('calculation_date', $dateStr)
                    ->first();

                if ($existingSummary) {
                    $this->line("  ⏭️  {$dateStr} - Already exists (Ls: {$existingSummary->ls_value} dB)");
                    $totalSkipped++;
                    $currentDate->addDay();
                    continue;
                }

                // Get all period calculations for this date
                $calculations = NoiseCalculation::where('device_id', $device->id)
                    ->whereDate('calculation_date', $dateStr)
                    ->get()
                    ->keyBy('period');

                // Check if all 4 periods are complete
                if ($calculations->count() < 4) {
                    $this->line("  ⚠️  {$dateStr} - Incomplete ({$calculations->count()}/4 periods)");
                    $totalSkipped++;
                    $currentDate->addDay();
                    continue;
                }

                // Prepare data for Ls calculation (8 hours work day)
                $periodData = [
                    ['period' => 'L1', 'leq' => $calculations->get('L1')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L2', 'leq' => $calculations->get('L2')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L3', 'leq' => $calculations->get('L3')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L4', 'leq' => $calculations->get('L4')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L5', 'leq' => $calculations->get('L5')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L6', 'leq' => $calculations->get('L6')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L7', 'leq' => $calculations->get('L7')->leq_value, 'duration_hours' => 1],
                    ['period' => 'L8', 'leq' => $calculations->get('L8')->leq_value, 'duration_hours' => 1],
                ];

                // Calculate Ls
                $ls = $statsService->calculateLs($periodData);

                // Calculate DND using NIOSH method
                $exposureTime = 8; // 8 hours work day
                $dnd = $statsService->calculateDND($ls, $exposureTime);
                
                // Calculate TWA
                $twa = $statsService->calculateTWA($dnd);

                // Create daily summary
                $summary = NoiseDailySummary::create([
                    'device_id' => $device->id,
                    'calculation_date' => $dateStr,
                    'ls_value' => $ls,
                    'twa_value' => $twa,
                    'dnd_value' => $dnd,
                    'l1_leq' => $periodData[0]['leq'],
                    'l2_leq' => $periodData[1]['leq'],
                    'l3_leq' => $periodData[2]['leq'],
                    'l4_leq' => $periodData[3]['leq'],
                    'l5_leq' => $periodData[4]['leq'],
                    'l6_leq' => $periodData[5]['leq'],
                    'l7_leq' => $periodData[6]['leq'],
                    'l8_leq' => $periodData[7]['leq'],
                ]);

                $this->line("  ✅ {$dateStr} - Created (Ls: {$ls} dB)");
                $totalCreated++;

                $currentDate->addDay();
            }

            $this->newLine();
        }

        $this->info("Backfill completed!");
        $this->info("Created: {$totalCreated}");
        $this->info("Skipped: {$totalSkipped}");

        return 0;
    }
}
