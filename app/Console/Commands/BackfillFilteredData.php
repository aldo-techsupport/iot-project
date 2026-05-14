<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\NoiseCalculation;
use App\Services\NoiseDataSelectionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillFilteredData extends Command
{
    protected $signature = 'noise:backfill-filtered
                            {--date= : Tanggal spesifik (Y-m-d), default: semua tanggal yang ada kalkulasinya}
                            {--device= : Device ID spesifik (opsional)}
                            {--force : Timpa data filtered yang sudah ada}';

    protected $description = 'Backfill tabel noise_filtered_data dari kalkulasi yang sudah ada di noise_calculations';

    protected array $periodTimes = [
        'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
        'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
        'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
        'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
        'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
        'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
        'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
        'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
    ];

    public function handle(): int
    {
        $specificDate   = $this->option('date');
        $specificDevice = $this->option('device');
        $force          = $this->option('force');

        // Ambil semua kalkulasi yang perlu di-backfill
        $query = NoiseCalculation::query();

        if ($specificDate) {
            $query->whereDate('calculation_date', $specificDate);
        }
        if ($specificDevice) {
            $query->where('device_id', $specificDevice);
        }

        $calculations = $query->get();

        if ($calculations->isEmpty()) {
            $this->warn('Tidak ada kalkulasi yang ditemukan.');
            return 0;
        }

        $this->info("Ditemukan {$calculations->count()} kalkulasi untuk di-backfill.");

        $bar = $this->output->createProgressBar($calculations->count());
        $bar->start();

        $success = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($calculations as $calc) {
            try {
                $date   = $calc->calculation_date->toDateString();
                $period = $calc->period;

                // Skip kalau sudah ada dan tidak force
                if (!$force) {
                    $exists = \App\Models\NoiseFilteredData::where('device_id', $calc->device_id)
                        ->where('period', $period)
                        ->whereDate('calculation_date', $date)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        $bar->advance();
                        continue;
                    }
                }

                $officialStart = Carbon::parse("$date {$this->periodTimes[$period]['start']}");
                $officialEnd   = Carbon::parse("$date {$this->periodTimes[$period]['end']}");

                NoiseDataSelectionService::selectAndPersist(
                    $calc->device_id,
                    $period,
                    $officialStart,
                    $officialEnd,
                    $date
                );

                $success++;
            } catch (\Exception $e) {
                $failed++;
                \Log::error("BackfillFilteredData error: " . $e->getMessage(), [
                    'device_id' => $calc->device_id,
                    'period'    => $calc->period,
                    'date'      => $calc->calculation_date,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Status', 'Jumlah'],
            [
                ['✅ Berhasil', $success],
                ['⏭️  Dilewati (sudah ada)', $skipped],
                ['❌ Gagal', $failed],
            ]
        );

        return 0;
    }
}
