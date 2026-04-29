<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup {--days=7 : Number of days to keep} {--size=100 : Max file size in MB}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old and large log files';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $maxSizeMB = $this->option('size');
        $logPath = storage_path('logs');
        
        $this->info("Starting log cleanup...");
        $this->info("- Removing files older than {$days} days");
        $this->info("- Removing files larger than {$maxSizeMB}MB");
        $this->newLine();

        $removedCount = 0;
        $removedSize = 0;
        $truncatedCount = 0;

        // Get all log files
        $files = File::glob($logPath . '/*.log');

        foreach ($files as $file) {
            if (!File::isFile($file)) continue;

            $fileName = basename($file);
            $fileSize = File::size($file);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);
            $fileAge = now()->diffInDays(File::lastModified($file));

            // Remove old files
            if ($fileAge > $days) {
                File::delete($file);
                $removedCount++;
                $removedSize += $fileSize;
                $this->line("✓ Removed (old): {$fileName} ({$fileSizeMB}MB, {$fileAge} days old)");
                continue;
            }

            // Remove very large files
            if ($fileSizeMB > $maxSizeMB) {
                File::delete($file);
                $removedCount++;
                $removedSize += $fileSize;
                $this->line("✓ Removed (large): {$fileName} ({$fileSizeMB}MB)");
                continue;
            }

            // Truncate cronjob logs if larger than 10MB
            if (str_starts_with($fileName, 'cronjob-') && $fileSizeMB > 10) {
                $this->truncateLog($file, 1000);
                $truncatedCount++;
                $this->line("✓ Truncated: {$fileName} (was {$fileSizeMB}MB)");
            }
        }

        $this->newLine();
        $this->info("Cleanup completed:");
        $this->line("- Files removed: {$removedCount}");
        $this->line("- Space freed: " . round($removedSize / 1024 / 1024, 2) . "MB");
        $this->line("- Files truncated: {$truncatedCount}");
        
        $this->newLine();
        $this->info("Current log directory size:");
        
        // Calculate total size
        $totalSize = 0;
        $fileCount = 0;
        foreach (File::glob($logPath . '/*.log') as $file) {
            if (File::isFile($file)) {
                $totalSize += File::size($file);
                $fileCount++;
            }
        }
        
        $this->line("- Total files: {$fileCount}");
        $this->line("- Total size: " . round($totalSize / 1024 / 1024, 2) . "MB");

        return Command::SUCCESS;
    }

    /**
     * Truncate log file to keep only last N lines
     */
    private function truncateLog(string $file, int $keepLines = 1000): void
    {
        $lines = file($file);
        if (count($lines) > $keepLines) {
            $lastLines = array_slice($lines, -$keepLines);
            File::put($file, implode('', $lastLines));
        }
    }
}
