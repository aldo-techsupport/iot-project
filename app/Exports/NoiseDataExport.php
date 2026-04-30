<?php

namespace App\Exports;

use App\Models\NoiseRawData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

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
     * Get the data collection
     */
    public function collection()
    {
        // Use the same smart selection logic as in DashboardController
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

        // Select real data at 1-minute intervals from official period only
        $selectedData = \App\Services\NoiseDataSelectionService::selectOneMinuteIntervalData(
            $this->deviceId,
            $this->period,
            $officialStart,
            $officialEnd
        );

        return $selectedData;
    }

    /**
     * Map data for export
     */
    public function map($data): array
    {
        static $counter = 0;
        $counter++;

        return [
            $counter,
            $data->measured_at->format('Y-m-d H:i:s'),
            number_format($data->noise_db ?? 0, 2),
            number_format($data->temperature ?? 0, 2),
            number_format($data->humidity ?? 0, 2),
            ($data->is_filled ?? false) ? 'Filled' : 'OK',
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
            'Status',
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
                    'startColor' => ['rgb' => '4F46E5']
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
