<?php

namespace App\Console\Commands;

use App\Models\NoiseTimeoutLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTimeoutLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:cleanup-timeout-logs {--days=7 : Keep logs from last N days} {--batch=1000 : Delete in batches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old timeout logs from database to reduce table size';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $batchSize = (int) $this->option('batch');
        $cutoffDate = now()->subDays($days);
        
        $this->info("🧹 Starting timeout logs cleanup...");
        $this->info("📅 Removing logs older than: {$cutoffDate->toDateString()}");
        $this->newLine();

        // Count total records to delete
        $totalCount = NoiseTimeoutLog::where('expected_at', '<', $cutoffDate)->count();
        
        if ($totalCount === 0) {
            $this->info("✓ No old logs to clean up!");
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$totalCount} old log entries to delete");
        
        if (!$this->confirm("Do you want to proceed with deletion?", true)) {
            $this->warn("Cleanup cancelled.");
            return Command::SUCCESS;
        }

        $this->newLine();
        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->start();

        $deletedCount = 0;
        
        // Delete in batches to avoid locking the table
        do {
            $deleted = NoiseTimeoutLog::where('expected_at', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();
            
            $deletedCount += $deleted;
            $progressBar->advance($deleted);
            
            // Small delay to prevent overwhelming the database
            if ($deleted > 0) {
                usleep(100000); // 100ms delay
            }
        } while ($deleted > 0);

        $progressBar->finish();
        $this->newLine(2);

        // Optimize table after deletion
        $this->info("🔧 Optimizing table...");
        DB::statement('OPTIMIZE TABLE noise_timeout_logs');

        // Show final statistics
        $remainingCount = NoiseTimeoutLog::count();
        
        $this->info("✅ Cleanup completed!");
        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Records deleted', number_format($deletedCount)],
                ['Records remaining', number_format($remainingCount)],
                ['Cutoff date', $cutoffDate->toDateString()],
            ]
        );

        return Command::SUCCESS;
    }
}
