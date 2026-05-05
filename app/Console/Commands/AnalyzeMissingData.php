<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AnalyzeMissingData extends Command
{
    protected $signature = 'noise:analyze-missing 
                            {device? : Device ID or slug}
                            {--date= : Date to analyze (Y-m-d format, default: today)}
                            {--period= : Period to analyze (L1-L8, default: all)}';

    protected $description = 'Analyze which minutes are missing data in noise monitoring periods';

    public function handle()
    {
        $deviceInput = $this->argument('device');
        $date = $this->option('date') ?? now()->toDateString();
        $periodFilter = $this->option('period');

        // Get device
        if ($deviceInput) {
            $device = is_numeric($deviceInput) 
                ? Device::find($deviceInput)
                : Device::where('slug', $deviceInput)->first();

            if (!$device) {
                $this->error("Device not found: {$deviceInput}");
                return 1;
            }

            $devices = collect([$device]);
        } else {
            $devices = Device::all();
        }

        if ($devices->isEmpty()) {
            $this->error('No devices found');
            return 1;
        }

        $this->info("Analyzing missing data for date: {$date}");
        $this->newLine();

        // Period definitions
        $periods = [
            'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
            'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
            'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
            'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
            'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
            'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
            'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
            'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
        ];

        // Filter periods if specified
        if ($periodFilter) {
            if (!isset($periods[$periodFilter])) {
                $this->error("Invalid period: {$periodFilter}");
                return 1;
            }
            $periods = [$periodFilter => $periods[$periodFilter]];
        }

        foreach ($devices as $device) {
            $this->info("═══════════════════════════════════════════════════════");
            $this->info("Device: {$device->name} (ID: {$device->id})");
            $this->info("═══════════════════════════════════════════════════════");
            $this->newLine();

            foreach ($periods as $periodName => $periodTime) {
                $this->analyzePeriod($device, $date, $periodName, $periodTime);
            }
        }

        return 0;
    }

    private function analyzePeriod($device, $date, $periodName, $periodTime)
    {
        $startTime = Carbon::parse("{$date} {$periodTime['start']}");
        $endTime = Carbon::parse("{$date} {$periodTime['end']}");

        $this->line("Period: <fg=cyan>{$periodName}</> ({$periodTime['start']} - {$periodTime['end']})");

        // Get all telemetry data for this period
        $telemetries = Telemetry::where('device_id', $device->id)
            ->whereBetween('measured_at', [$startTime, $endTime->copy()->addSeconds(10)])
            ->orderBy('measured_at')
            ->get();

        if ($telemetries->isEmpty()) {
            $this->error("  ✗ No data found for this period");
            $this->newLine();
            return;
        }

        // Generate expected timestamps (60 minutes)
        $expectedTimestamps = [];
        $current = $startTime->copy();
        for ($i = 0; $i < 60; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addMinute();
        }

        // Find which minutes have data
        $foundMinutes = [];
        $missingMinutes = [];
        $tolerance = 30; // ±30 seconds

        foreach ($expectedTimestamps as $expectedTime) {
            $found = $telemetries->first(function($telemetry) use ($expectedTime, $tolerance) {
                return abs($telemetry->measured_at->timestamp - $expectedTime->timestamp) <= $tolerance;
            });

            $minuteLabel = $expectedTime->format('H:i');
            
            if ($found) {
                $foundMinutes[] = [
                    'expected' => $minuteLabel,
                    'actual' => $found->measured_at->format('H:i:s'),
                    'diff' => $found->measured_at->timestamp - $expectedTime->timestamp,
                    'noise_db' => $found->noise_db,
                    'is_filled' => $found->is_filled,
                ];
            } else {
                $missingMinutes[] = $minuteLabel;
            }
        }

        // Display summary
        $totalFound = count($foundMinutes);
        $totalMissing = count($missingMinutes);
        $percentage = round(($totalFound / 60) * 100, 1);

        if ($totalMissing === 0) {
            $this->info("  ✓ Complete: {$totalFound}/60 points ({$percentage}%)");
        } else {
            $this->warn("  ⚠ Incomplete: {$totalFound}/60 points ({$percentage}%)");
            $this->error("  ✗ Missing {$totalMissing} data points:");
            
            // Group consecutive missing minutes
            $groups = $this->groupConsecutiveMinutes($missingMinutes);
            foreach ($groups as $group) {
                if (count($group) === 1) {
                    $this->line("    • {$group[0]}");
                } else {
                    $this->line("    • {$group[0]} - {$group[count($group) - 1]} (" . count($group) . " minutes)");
                }
            }

            // Check for timeout logs
            $timeoutLogs = \App\Models\NoiseTimeoutLog::where('device_id', $device->id)
                ->whereBetween('expected_at', [$startTime, $endTime])
                ->orderBy('expected_at')
                ->get();

            if ($timeoutLogs->isNotEmpty()) {
                $this->newLine();
                $this->line("  <fg=yellow>Timeout logs found:</>");
                foreach ($timeoutLogs as $log) {
                    $this->line("    • {$log->expected_at->format('H:i:s')} - timeout: {$log->timeout_seconds}s");
                }
            }
        }

        // Show data quality details if verbose
        if ($this->output->isVerbose() && $totalFound > 0) {
            $this->newLine();
            $this->line("  <fg=cyan>Data Quality Details:</>");
            
            $filledCount = collect($foundMinutes)->where('is_filled', true)->count();
            $realCount = $totalFound - $filledCount;
            
            $this->line("    Real data: {$realCount}");
            $this->line("    Filled data: {$filledCount}");
            
            // Show timing accuracy
            $avgDiff = collect($foundMinutes)->avg('diff');
            $maxDiff = collect($foundMinutes)->max('diff');
            $minDiff = collect($foundMinutes)->min('diff');
            
            $this->line("    Timing accuracy:");
            $this->line("      Average offset: " . round($avgDiff, 1) . "s");
            $this->line("      Max offset: {$maxDiff}s");
            $this->line("      Min offset: {$minDiff}s");
        }

        $this->newLine();
    }

    private function groupConsecutiveMinutes(array $minutes): array
    {
        if (empty($minutes)) {
            return [];
        }

        $groups = [];
        $currentGroup = [$minutes[0]];

        for ($i = 1; $i < count($minutes); $i++) {
            $prevTime = Carbon::parse($minutes[$i - 1]);
            $currTime = Carbon::parse($minutes[$i]);

            if ($currTime->diffInMinutes($prevTime) === 1) {
                $currentGroup[] = $minutes[$i];
            } else {
                $groups[] = $currentGroup;
                $currentGroup = [$minutes[$i]];
            }
        }

        $groups[] = $currentGroup;

        return $groups;
    }
}
