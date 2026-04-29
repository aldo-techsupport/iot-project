<?php

namespace App\Console\Commands;

use App\Models\NoiseTimeoutLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldTimeoutLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iot:cleanup-timeout-logs 
                            {--days=7 : Number of days to keep}
                            {--batch=1000 : Batch size for deletion}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup old timeout logs to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $batchSize = (int) $this->option('batch');
        $dryRun = $this->option('dry-run');
        
        $cutoffDate = now()->subDays($days);
        
        $this->info("Cleaning up timeout logs older than {$days} days (before {$cutoffDate->toDateTimeString()})");
        
        // Count total records to delete
        $totalCount = NoiseTimeoutLog::where('created_at', '<', $cutoffDate)->count();
        
        if ($totalCount === 0) {
            $this->info('No old timeout logs to clean up.');
            return 0;
        }
        
        $this->info("Found {$totalCount} timeout logs to delete.");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No records will be deleted.');
            
            // Show sample of what would be deleted
            $sample = NoiseTimeoutLog::where('created_at', '<', $cutoffDate)
                ->orderBy('created_at')
                ->limit(5)
                ->get(['id', 'device_id', 'period', 'expected_at', 'created_at']);
            
            $this->table(
                ['ID', 'Device ID', 'Period', 'Expected At', 'Created At'],
                $sample->map(fn($log) => [
                    $log->id,
                    $log->device_id,
                    $log->period,
                    $log->expected_at,
                    $log->created_at,
                ])
            );
            
            return 0;
        }
        
        // Confirm deletion
        if (!$this->confirm("Are you sure you want to delete {$totalCount} timeout logs?")) {
            $this->info('Cleanup cancelled.');
            return 0;
        }
        
        $deletedCount = 0;
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();
        
        // Delete in batches to avoid memory issues
        do {
            $deleted = NoiseTimeoutLog::where('created_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();
            
            $deletedCount += $deleted;
            $bar->advance($deleted);
            
            // Small delay to avoid overwhelming the database
            usleep(10000); // 10ms
            
        } while ($deleted > 0);
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Successfully deleted {$deletedCount} timeout logs.");
        
        // Optimize table after large deletion
        $this->info('Optimizing table...');
        DB::statement('OPTIMIZE TABLE noise_timeout_logs');
        $this->info('Table optimized.');
        
        return 0;
    }
}
