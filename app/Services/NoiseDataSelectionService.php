<?php

namespace App\Services;

use App\Models\NoiseFilteredData;
use App\Models\Telemetry;
use Carbon\Carbon;

class NoiseDataSelectionService
{
    /**
     * Window pencarian data untuk setiap slot (dalam detik).
     * Diperlebar ke 60 detik agar gap kecil tetap tertangkap.
     */
    const SEARCH_WINDOW_SECONDS = 60;

    /**
     * Select up to 60 data points at 1-minute intervals from 1-hour telemetry data.
     *
     * STRATEGY:
     * 1. Untuk setiap slot menit (0–59), cari data real dalam ±60 detik
     * 2. Kalau tidak ada real data, ambil filled data dalam ±60 detik
     * 3. Kalau masih kosong, copy data terdekat (filled) agar slot tidak kosong
     *
     * @return \Illuminate\Support\Collection
     */
    public static function selectOneMinuteIntervalData($deviceId, $period, $startTime, $endTime)
    {
        $officialStart = Carbon::parse($startTime)->second(0);
        $officialEnd   = Carbon::parse($endTime);

        $allData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [
                $officialStart->copy()->subSeconds(self::SEARCH_WINDOW_SECONDS),
                $officialEnd->copy()->addSeconds(self::SEARCH_WINDOW_SECONDS),
            ])
            ->orderBy('measured_at')
            ->get();

        $expectedTimestamps = [];
        $current = $officialStart->copy();
        for ($i = 0; $i < 60; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addMinutes(1);
        }

        $selectedData = collect();
        $usedIds      = [];

        foreach ($expectedTimestamps as $expectedTime) {
            $closest = self::findClosest($allData, $expectedTime, $usedIds, realOnly: true);

            if (!$closest) {
                $closest = self::findClosest($allData, $expectedTime, $usedIds, realOnly: false);
            }

            if ($closest) {
                $selectedData->push(clone $closest);
                $usedIds[] = $closest->id;
            }
        }

        return $selectedData;
    }

    /**
     * Select 60 data points AND simpan hasilnya ke tabel noise_filtered_data.
     *
     * Jaminan 60 data:
     * - Window ±60 detik untuk setiap slot
     * - Kalau masih kosong → copy data terdekat (filled) dari seluruh period
     *
     * @param  int|string  $deviceId
     * @param  string      $period
     * @param  Carbon      $startTime
     * @param  Carbon      $endTime
     * @param  string      $date       Format Y-m-d
     * @return \Illuminate\Support\Collection
     */
    public static function selectAndPersist($deviceId, string $period, Carbon $startTime, Carbon $endTime, string $date)
    {
        $officialStart = $startTime->copy()->second(0);
        $officialEnd   = $endTime->copy();

        // Ambil semua telemetry dalam rentang period + buffer
        $allData = Telemetry::where('device_id', $deviceId)
            ->whereBetween('measured_at', [
                $officialStart->copy()->subSeconds(self::SEARCH_WINDOW_SECONDS),
                $officialEnd->copy()->addSeconds(self::SEARCH_WINDOW_SECONDS),
            ])
            ->orderBy('measured_at')
            ->get();

        // Generate 60 expected timestamps
        $expectedTimestamps = [];
        $current = $officialStart->copy();
        for ($i = 0; $i < 60; $i++) {
            $expectedTimestamps[] = $current->copy();
            $current->addMinutes(1);
        }

        $selectedData = collect();
        $usedIds      = [];
        $slots        = []; // slot_index => Telemetry|null

        // Pass 1: cari data real/filled dalam window ±60 detik
        foreach ($expectedTimestamps as $slotIndex => $expectedTime) {
            $closest = self::findClosest($allData, $expectedTime, $usedIds, realOnly: true);

            if (!$closest) {
                $closest = self::findClosest($allData, $expectedTime, $usedIds, realOnly: false);
            }

            if ($closest) {
                $selectedData->push(clone $closest);
                $usedIds[]         = $closest->id;
                $slots[$slotIndex] = clone $closest;
            } else {
                $slots[$slotIndex] = null; // tandai kosong
            }
        }

        // Pass 2: isi slot kosong dengan copy data terdekat (filled)
        $emptySlots = array_keys(array_filter($slots, fn($v) => $v === null));

        if (!empty($emptySlots) && $allData->isNotEmpty()) {
            foreach ($emptySlots as $slotIndex) {
                $expectedTime = $expectedTimestamps[$slotIndex];

                // Cari data terdekat dari seluruh period (tanpa batasan window)
                $nearest = $allData->sortBy(
                    fn($d) => abs($d->measured_at->timestamp - $expectedTime->timestamp)
                )->first();

                if ($nearest) {
                    // Buat objek Telemetry palsu (filled copy)
                    $filled                = clone $nearest;
                    $filled->is_filled     = true;
                    $filled->fill_method   = 'copied';
                    $filled->measured_at   = $expectedTime->copy(); // set ke expected time
                    $filled->id            = null; // jangan pakai ID asli

                    $selectedData->push($filled);
                    $slots[$slotIndex] = $filled;
                }
            }
        }

        // Urutkan berdasarkan slot_index
        ksort($slots);

        // Bangun array untuk insert ke DB
        $toInsert = [];
        foreach ($slots as $slotIndex => $d) {
            if (!$d) continue;

            $toInsert[] = [
                'device_id'        => $deviceId,
                'period'           => $period,
                'calculation_date' => $date,
                'noise_level'      => (float) ($d->noise_db ?? $d->noise_level ?? 0),
                'temperature'      => $d->temperature !== null ? (float) $d->temperature : null,
                'humidity'         => $d->humidity !== null ? (float) $d->humidity : null,
                'measured_at'      => $d->measured_at->toDateTimeString(),
                'is_filled'        => (bool) $d->is_filled,
                'fill_method'      => $d->fill_method ?? 'actual',
                'slot_index'       => $slotIndex,
                'created_at'       => now()->toDateTimeString(),
                'updated_at'       => now()->toDateTimeString(),
            ];
        }

        // Hapus data lama lalu insert ulang
        if (!empty($toInsert)) {
            NoiseFilteredData::where('device_id', $deviceId)
                ->where('period', $period)
                ->whereDate('calculation_date', $date)
                ->delete();

            foreach (array_chunk($toInsert, 60) as $chunk) {
                NoiseFilteredData::insert($chunk);
            }
        }

        // Return collection yang sudah diurutkan (untuk kalkulasi)
        return collect(array_values($slots))->filter()->values();
    }

    /**
     * Ambil data filtered dari DB (sudah tersimpan sebelumnya).
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getFromDb(int $deviceId, string $period, string $date)
    {
        return NoiseFilteredData::where('device_id', $deviceId)
            ->where('period', $period)
            ->whereDate('calculation_date', $date)
            ->orderBy('slot_index')
            ->get();
    }

    /**
     * Cari data terdekat dari collection dalam window ±SEARCH_WINDOW_SECONDS.
     *
     * @param  \Illuminate\Support\Collection  $allData
     * @param  Carbon  $expectedTime
     * @param  array   $usedIds
     * @param  bool    $realOnly  true = hanya is_filled=false
     * @return Telemetry|null
     */
    private static function findClosest($allData, Carbon $expectedTime, array $usedIds, bool $realOnly): ?Telemetry
    {
        return $allData->filter(function ($d) use ($expectedTime, $usedIds, $realOnly) {
            if (in_array($d->id, $usedIds)) return false;
            if ($realOnly && $d->is_filled) return false;
            return abs($d->measured_at->timestamp - $expectedTime->timestamp) <= self::SEARCH_WINDOW_SECONDS;
        })->sortBy(fn($d) => abs($d->measured_at->timestamp - $expectedTime->timestamp))->first();
    }
}
