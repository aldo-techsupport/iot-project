<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NoiseDataExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $deviceId;

    protected $period;

    protected $date;

    protected $deviceName;

    public function __construct($deviceId, $period, $date, $deviceName = null)
    {
        $this->deviceId = $deviceId;
        $this->period = $period;
        $this->date = $date;
        $this->deviceName = $deviceName ?? "Device {$deviceId}";
    }

    /**
     * Get the data collection.
     *
     * Uses the exact same priority as the on-screen modal (getRealTimeNoiseData):
     * read the persisted noise_filtered_data snapshot first so manual admin edits
     * are reflected, and only fall back to live telemetry selection when no
     * snapshot exists yet.
     */
    public function collection()
    {
        // Prioritas 1: snapshot noise_filtered_data (mencerminkan edit manual admin)
        $filteredRows = \App\Services\NoiseDataSelectionService::getFromDb(
            (int) $this->deviceId,
            $this->period,
            $this->date
        );

        if ($filteredRows->isNotEmpty()) {
            return $filteredRows;
        }

        // Prioritas 2: fallback seleksi live dari telemetry saat snapshot belum ada
        $periodTimes = [
            'L1' => ['start' => '08:00:00', 'end' => '09:00:00'],
            'L2' => ['start' => '09:00:00', 'end' => '10:00:00'],
            'L3' => ['start' => '10:00:00', 'end' => '11:00:00'],
            'L4' => ['start' => '11:00:00', 'end' => '12:00:00'],
            'L5' => ['start' => '13:00:00', 'end' => '14:00:00'],
            'L6' => ['start' => '14:00:00', 'end' => '15:00:00'],
            'L7' => ['start' => '15:00:00', 'end' => '16:00:00'],
            'L8' => ['start' => '16:00:00', 'end' => '17:00:00'],
        ];

        $officialStart = Carbon::parse("{$this->date} {$periodTimes[$this->period]['start']}");
        $officialEnd = Carbon::parse("{$this->date} {$periodTimes[$this->period]['end']}");

        return \App\Services\NoiseDataSelectionService::selectOneMinuteIntervalData(
            $this->deviceId,
            $this->period,
            $officialStart,
            $officialEnd
        );
    }

    /**
     * Map data for export
     */
    public function map($data): array
    {
        static $counter = 0;
        $counter++;

        // Filtered-data snapshot uses noise_level; live telemetry uses noise_db.
        $noise = $data->noise_db ?? $data->noise_level ?? 0;
        $temperature = $data->temperature ?? 0;
        $humidity = $data->humidity ?? 0;

        // THI (Temperature Humidity Index) — same formula as the modal display
        // and NoiseStatisticsService: THI = 0.8 × Ta + (RH × Ta) / 500
        $thi = 0.8 * $temperature + ($humidity * $temperature) / 500;

        return [
            $counter,
            $data->measured_at->format('Y-m-d H:i:s'),
            number_format($noise, 2),
            number_format($temperature, 2),
            number_format($humidity, 2),
            number_format($thi, 2),
        ];
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'No',
            'Timestamp',
            'Noise Level (dB)',
            'Temperature (°C)',
            'Humidity (%)',
            'THI (°C)',
        ];
    }

    /**
     * Apply styles
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4F46E5'],
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
            ],
        ];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        return "{$this->period} - {$this->deviceName}";
    }
}
