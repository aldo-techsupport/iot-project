<?php

namespace App\Console\Commands;

use App\Models\NoiseDailySummary;
use App\Services\NoiseStatisticsService;
use Illuminate\Console\Command;

class RecalculateDailySummaries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'noise:recalculate-summaries 
                            {--device= : Specific device ID to recalculate}
                            {--date= : Specific date to recalculate (YYYY-MM-DD)}
                            {--all : Recalculate all summaries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate DND and TWA for daily summaries using new formula';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recalculating Daily Summaries with New DND Formula');
        $this->info(str_repeat('=', 80));

        $query = NoiseDailySummary::query();

        // Filter by device if specified
        if ($deviceId = $this->option('device')) {
            $query->where('device_id', $deviceId);
            $this->info("Filtering by Device ID: {$deviceId}");
        }

        // Filter by date if specified
        if ($date = $this->option('date')) {
            $query->whereDate('calculation_date', $date);
            $this->info("Filtering by Date: {$date}");
        }

        $summaries = $query->get();

        if ($summaries->isEmpty()) {
            $this->warn('No daily summaries found to recalculate.');
            return 0;
        }

        $this->info("Found {$summaries->count()} daily summaries to recalculate.");
        $this->newLine();

        if (!$this->option('all') && !$this->confirm('Do you want to proceed?', true)) {
            $this->info('Operation cancelled.');
            return 0;
        }

        $service = new NoiseStatisticsService();
        $updated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($summaries->count());
        $progressBar->start();

        foreach ($summaries as $summary) {
            try {
                // Recalculate DND and TWA based on existing Ls
                $ls = $summary->ls_value;
                $dnd = $service->calculateDND($ls, 8);
                $twa = $service->calculateTWA($dnd);

                // Update
                $summary->dnd_value = $dnd;
                $summary->twa_value = $twa;
                $summary->save();

                $updated++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error updating Device {$summary->device_id} on {$summary->calculation_date}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Recalculation Complete!');
        $this->info(str_repeat('=', 80));
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Records', $summaries->count()],
                ['Successfully Updated', $updated],
                ['Errors', $errors],
            ]
        );

        if ($updated > 0) {
            $this->newLine();
            $this->info('Sample Results:');
            $samples = $query->limit(5)->get();
            $this->table(
                ['Device ID', 'Date', 'Ls (dB)', 'DND (%)', 'TWA (dBA)'],
                $samples->map(fn($s) => [
                    $s->device_id,
                    $s->calculation_date->format('Y-m-d'),
                    $s->ls_value,
                    $s->dnd_value,
                    $s->twa_value,
                ])
            );
        }

        return 0;
    }
}
