<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\NoiseCalculation;
use App\Models\NoiseFilteredData;
use App\Models\NoiseDailySummary;
use App\Models\NoiseRawData;
use App\Models\Telemetry;
use App\Services\NoiseStatisticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InvalidDataController extends Controller
{
    private const PERIOD_TIMES = [
        'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
        'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
        'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
        'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
        'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
        'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
        'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
        'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Page
    // ─────────────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        [$report, $counts] = $this->buildReport();

        return Inertia::render('admin/invalid-data', array_merge(
            ['report' => $report],
            $counts
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fix All
    // ─────────────────────────────────────────────────────────────────────────

    public function fixAll(Request $request)
    {
        $totalDeletedFake  = 0;
        $totalRecalculated = 0;
        $errors            = [];

        // Ambil semua kombinasi device+tanggal yang ada di noise_filtered_data
        $combinations = NoiseFilteredData::selectRaw('DISTINCT device_id, DATE(calculation_date) as date')
            ->orderBy('date')
            ->get();

        foreach ($combinations as $combo) {
            $deviceId = (int) $combo->device_id;
            $date     = $combo->date;

            foreach (self::PERIOD_TIMES as $period => $times) {
                try {
                    $start = Carbon::parse("$date {$times['start']}");
                    $end   = Carbon::parse("$date {$times['end']}");

                    // Cari data real pertama di telemetry untuk periode ini
                    $firstReal = Telemetry::where('device_id', $deviceId)
                        ->where('is_filled', false)
                        ->whereBetween('measured_at', [$start, $end])
                        ->orderBy('measured_at')
                        ->first();

                    $deleted = 0;

                    DB::transaction(function () use ($deviceId, $date, $period, $firstReal, &$deleted) {
                        if ($firstReal) {
                            // Hapus slot fake sebelum alat nyala
                            $deleted = NoiseFilteredData::where('device_id', $deviceId)
                                ->where('period', $period)
                                ->whereDate('calculation_date', $date)
                                ->where('measured_at', '<', $firstReal->measured_at)
                                ->delete();
                        } else {
                            // Tidak ada data real — hapus semua filled
                            $deleted = NoiseFilteredData::where('device_id', $deviceId)
                                ->where('period', $period)
                                ->whereDate('calculation_date', $date)
                                ->where('is_filled', true)
                                ->delete();
                        }

                        if ($deleted > 0) {
                            // Hapus kalkulasi lama agar dibangun ulang
                            NoiseCalculation::where('device_id', $deviceId)
                                ->where('period', $period)
                                ->whereDate('calculation_date', $date)
                                ->delete();
                        }
                    });

                    $totalDeletedFake += $deleted;

                    // Recalculate dari filtered data yang tersisa
                    $recalcOk = $this->recalculateFromFiltered($deviceId, $period, $date);
                    if ($recalcOk) $totalRecalculated++;

                } catch (\Exception $e) {
                    $errors[] = "device=$deviceId date=$date period=$period: " . $e->getMessage();
                    Log::error("InvalidDataController::fixAll error: " . $e->getMessage());
                }
            }

            // Update validity daily summary untuk tanggal ini
            $this->updateDailySummaryValidity($deviceId, $date);
        }

        Log::info("Admin fixAll: deleted=$totalDeletedFake recalculated=$totalRecalculated errors=" . count($errors));

        // Reload report setelah fix
        [$report, $counts] = $this->buildReport();

        return response()->json([
            'success'            => true,
            'total_deleted_fake' => $totalDeletedFake,
            'total_recalculated' => $totalRecalculated,
            'errors'             => $errors,
            'report'             => $report,
            'counts'             => $counts,
            'message'            => "$totalDeletedFake slot fake dihapus, $totalRecalculated periode dihitung ulang."
                . (count($errors) ? ' ' . count($errors) . ' error.' : ''),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cleanup Pre-Device Filled Data (noise_raw_data)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Preview: tampilkan data yang akan dihapus tanpa benar-benar menghapus.
     */
    public function previewCleanup(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date    = $validated['date'];
        $periods = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];
        $devices = Device::where('is_active', true)->orderBy('name')->get();

        $preview = [];

        foreach ($devices as $device) {
            foreach ($periods as $period) {
                $firstReal = NoiseRawData::where('device_id', $device->id)
                    ->where('period', $period)
                    ->whereDate('measured_at', $date)
                    ->where('is_filled', false)
                    ->orderBy('measured_at', 'asc')
                    ->first();

                if (!$firstReal) {
                    $filledCount = NoiseRawData::where('device_id', $device->id)
                        ->where('period', $period)
                        ->whereDate('measured_at', $date)
                        ->where('is_filled', true)
                        ->count();

                    if ($filledCount > 0) {
                        $preview[] = [
                            'device_id'      => $device->id,
                            'device_name'    => $device->name,
                            'period'         => $period,
                            'rows_to_delete' => $filledCount,
                            'reason'         => 'Tidak ada data real — semua filled akan dihapus',
                            'first_real_at'  => null,
                        ];
                    }
                    continue;
                }

                $preFilledCount = NoiseRawData::where('device_id', $device->id)
                    ->where('period', $period)
                    ->whereDate('measured_at', $date)
                    ->where('is_filled', true)
                    ->where('measured_at', '<', $firstReal->measured_at)
                    ->count();

                if ($preFilledCount > 0) {
                    $preview[] = [
                        'device_id'      => $device->id,
                        'device_name'    => $device->name,
                        'period'         => $period,
                        'rows_to_delete' => $preFilledCount,
                        'reason'         => "Alat Offline / Jaringan Tidak Bagus",
                        'first_real_at'  => $firstReal->measured_at->format('H:i:s'),
                    ];
                }
            }
        }

        return response()->json([
            'success'       => true,
            'date'          => $date,
            'preview'       => $preview,
            'total_to_delete' => array_sum(array_column($preview, 'rows_to_delete')),
        ]);
    }

    /**
     * Eksekusi cleanup: hapus filled rows di noise_raw_data sebelum data real pertama.
     */
    public function cleanupPreDeviceFills(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);

        $date         = $validated['date'];
        $periods      = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];
        $devices      = Device::where('is_active', true)->orderBy('name')->get();
        $totalDeleted = 0;
        $details      = [];
        $errors       = [];

        foreach ($devices as $device) {
            foreach ($periods as $period) {
                try {
                    $firstReal = NoiseRawData::where('device_id', $device->id)
                        ->where('period', $period)
                        ->whereDate('measured_at', $date)
                        ->where('is_filled', false)
                        ->orderBy('measured_at', 'asc')
                        ->first();

                    $deleted = 0;

                    DB::transaction(function () use ($device, $period, $date, $firstReal, &$deleted) {
                        if (!$firstReal) {
                            // Tidak ada data real — hapus semua filled
                            $deleted = NoiseRawData::where('device_id', $device->id)
                                ->where('period', $period)
                                ->whereDate('measured_at', $date)
                                ->where('is_filled', true)
                                ->delete();
                        } else {
                            // Hapus filled sebelum data real pertama
                            $deleted = NoiseRawData::where('device_id', $device->id)
                                ->where('period', $period)
                                ->whereDate('measured_at', $date)
                                ->where('is_filled', true)
                                ->where('measured_at', '<', $firstReal->measured_at)
                                ->delete();
                        }

                        if ($deleted > 0) {
                            // Hapus kalkulasi & filtered agar bisa dihitung ulang
                            NoiseFilteredData::where('device_id', $device->id)
                                ->where('period', $period)
                                ->whereDate('calculation_date', $date)
                                ->delete();

                            NoiseCalculation::where('device_id', $device->id)
                                ->where('period', $period)
                                ->whereDate('calculation_date', $date)
                                ->delete();
                        }
                    });

                    if ($deleted > 0) {
                        $totalDeleted += $deleted;
                        $details[] = [
                            'device_name'   => $device->name,
                            'period'        => $period,
                            'deleted'       => $deleted,
                            'first_real_at' => $firstReal?->measured_at->format('H:i:s'),
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = "device={$device->name} period={$period}: " . $e->getMessage();
                    Log::error("InvalidDataController::cleanupPreDeviceFills error: " . $e->getMessage());
                }
            }
        }

        Log::info("Admin cleanupPreDeviceFills date={$date}: deleted={$totalDeleted} errors=" . count($errors));

        // Reload report setelah cleanup
        [$report, $counts] = $this->buildReport();

        return response()->json([
            'success'       => true,
            'date'          => $date,
            'total_deleted' => $totalDeleted,
            'details'       => $details,
            'errors'        => $errors,
            'report'        => $report,
            'counts'        => $counts,
            'message'       => "{$totalDeleted} baris noise_raw_data dihapus untuk tanggal {$date}."
                . ($totalDeleted > 0 ? ' Jalankan recalculate untuk memperbarui kalkulasi.' : '')
                . (count($errors) ? ' ' . count($errors) . ' error.' : ''),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scan semua kombinasi device+tanggal dan kembalikan laporan masalah.
     */
    private function buildReport(): array
    {
        $devices = Device::orderBy('name')->get(['id', 'name', 'location']);

        $combinations = NoiseFilteredData::selectRaw('DISTINCT device_id, DATE(calculation_date) as date')
            ->orderBy('date', 'desc')
            ->get();

        $report = [];

        foreach ($combinations as $combo) {
            $deviceId = (int) $combo->device_id;
            $date     = $combo->date;
            $device   = $devices->firstWhere('id', $deviceId);

            $periodIssues  = [];
            $totalFake     = 0;
            $totalInvalid  = 0;

            foreach (self::PERIOD_TIMES as $period => $times) {
                $start = Carbon::parse("$date {$times['start']}");
                $end   = Carbon::parse("$date {$times['end']}");

                $filteredTotal = NoiseFilteredData::where('device_id', $deviceId)
                    ->where('period', $period)
                    ->whereDate('calculation_date', $date)
                    ->count();

                if ($filteredTotal === 0) continue;

                $firstReal = Telemetry::where('device_id', $deviceId)
                    ->where('is_filled', false)
                    ->whereBetween('measured_at', [$start, $end])
                    ->orderBy('measured_at')
                    ->first();

                // Hitung slot fake
                $fakeSlots = 0;
                $fakeUntil = null;
                if ($firstReal) {
                    $fakeSlots = NoiseFilteredData::where('device_id', $deviceId)
                        ->where('period', $period)
                        ->whereDate('calculation_date', $date)
                        ->where('measured_at', '<', $firstReal->measured_at)
                        ->count();
                    if ($fakeSlots > 0) {
                        $fakeUntil = $firstReal->measured_at->format('H:i:s');
                    }
                } else {
                    // Tidak ada data real — semua filled = fake
                    $fakeSlots = NoiseFilteredData::where('device_id', $deviceId)
                        ->where('period', $period)
                        ->whereDate('calculation_date', $date)
                        ->where('is_filled', true)
                        ->count();
                }

                $calc      = NoiseCalculation::where('device_id', $deviceId)
                    ->where('period', $period)
                    ->whereDate('calculation_date', $date)
                    ->first();

                $isInvalid = $calc && !$calc->is_valid;

                if ($fakeSlots > 0) $totalFake += $fakeSlots;
                if ($isInvalid)     $totalInvalid++;

                if ($fakeSlots > 0 || $isInvalid) {
                    $periodIssues[] = [
                        'period'        => $period,
                        'time'          => "{$times['start']}–{$times['end']}",
                        'fake_slots'    => $fakeSlots,
                        'fake_until'    => $fakeUntil,
                        'first_real_at' => $firstReal?->measured_at->format('H:i:s'),
                        'data_count'    => $calc?->data_count ?? $filteredTotal,
                        'leq_value'     => $calc?->leq_value,
                        'is_valid'      => $calc?->is_valid,
                        'has_fake'      => $fakeSlots > 0,
                        'has_invalid'   => $isInvalid,
                    ];
                }
            }

            if (!empty($periodIssues)) {
                $report[] = [
                    'device_id'       => $deviceId,
                    'device_name'     => $device?->name ?? "Device #$deviceId",
                    'device_location' => $device?->location ?? '',
                    'date'            => $date,
                    'total_fake'      => $totalFake,
                    'total_invalid'   => $totalInvalid,
                    'periods'         => $periodIssues,
                ];
            }
        }

        $counts = [
            'invalidCalcCount'    => NoiseCalculation::where('is_valid', false)->count(),
            'invalidSummaryCount' => NoiseDailySummary::where('is_valid', false)->count(),
            'totalAffectedDates'  => count($report),
            'grandTotalFake'      => array_sum(array_column($report, 'total_fake')),
        ];

        return [$report, $counts];
    }

    /**
     * Recalculate periode dari noise_filtered_data yang tersisa.
     */
    private function recalculateFromFiltered(int $deviceId, string $period, string $date): bool
    {
        $rows = NoiseFilteredData::where('device_id', $deviceId)
            ->where('period', $period)
            ->whereDate('calculation_date', $date)
            ->orderBy('slot_index')
            ->get();

        if ($rows->isEmpty()) return false;

        $rawData   = $rows->map(fn($d) => [
            'noise_level' => (float) $d->noise_level,
            'temperature' => (float) ($d->temperature ?? 0),
            'humidity'    => (float) ($d->humidity ?? 0),
        ])->values()->toArray();

        $dataCount = count($rawData);
        $isValid   = $dataCount >= NoiseCalculation::MIN_VALID_DATA_COUNT;

        $stats   = new NoiseStatisticsService();
        $results = $stats->processCompleteCalculation($rawData);

        $results['data_count']           = $dataCount;
        $results['total_collected']      = $dataCount;
        $results['from_official_period'] = $dataCount;
        $results['is_valid']             = $isValid;
        $results['invalid_reason']       = $isValid
            ? null
            : "INVALID DATA: hanya {$dataCount}/60 data point tersedia untuk periode {$period}.";

        NoiseCalculation::updateOrCreate(
            ['device_id' => $deviceId, 'period' => $period, 'calculation_date' => $date],
            $results
        );

        return true;
    }

    /**
     * Update is_valid pada NoiseDailySummary berdasarkan kalkulasi periode terkini.
     */
    private function updateDailySummaryValidity(int $deviceId, string $date): void
    {
        $invalidPeriods = NoiseCalculation::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->where('is_valid', false)
            ->pluck('period')
            ->toArray();

        $summary = NoiseDailySummary::where('device_id', $deviceId)
            ->whereDate('calculation_date', $date)
            ->first();

        if (!$summary) return;

        if (!empty($invalidPeriods)) {
            $summary->update([
                'is_valid'        => false,
                'invalid_reason'  => 'INVALID DATA: periode ' . implode(', ', $invalidPeriods)
                    . ' tidak lengkap (data < 60 titik).',
                'invalid_periods' => $invalidPeriods,
                'ls_value'        => null,
                'twa_value'       => null,
                'dnd_value'       => null,
                'allowable_time'  => null,
            ]);
        } else {
            $summary->update([
                'is_valid'        => true,
                'invalid_reason'  => null,
                'invalid_periods' => null,
            ]);
        }
    }
}
