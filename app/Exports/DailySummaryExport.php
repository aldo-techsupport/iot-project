<?php

namespace App\Exports;

use App\Models\NoiseDailySummary;
use App\Models\Device;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class DailySummaryExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $deviceId;
    protected $startDate;
    protected $endDate;
    protected $deviceName;

    public function __construct($deviceId, $startDate, $endDate = null, $deviceName = null)
    {
        $this->deviceId = $deviceId;
        $this->startDate = $startDate;
        $this->endDate = $endDate ?? $startDate;
        $this->deviceName = $deviceName ?? "Device {$deviceId}";
    }

    /**
     * Get the data collection
     */
    public function collection()
    {
        return NoiseDailySummary::where('device_id', $this->deviceId)
            ->whereDate('calculation_date', '>=', $this->startDate)
            ->whereDate('calculation_date', '<=', $this->endDate)
            ->orderBy('calculation_date')
            ->get();
    }

    /**
     * Map data for export
     */
    public function map($summary): array
    {
        static $counter = 0;
        $counter++;

        $status = 'Normal';
        if ($summary->ls_value > 90) {
            $status = 'High Risk';
        } elseif ($summary->ls_value > 85) {
            $status = 'Above Limit';
        }

        return [
            $counter,
            Carbon::parse($summary->calculation_date)->format('Y-m-d'),
            Carbon::parse($summary->calculation_date)->locale('id')->isoFormat('dddd'),
            number_format($summary->ls_value, 2),
            number_format($summary->twa_value, 2),
            number_format($summary->dnd_value, 2),
            number_format($summary->allowable_time ?? 0, 2),
            $summary->thi_avg_daily !== null ? number_format($summary->thi_avg_daily, 2) : 'N/A',
            $summary->temperature_avg_daily !== null ? number_format($summary->temperature_avg_daily, 2) : 'N/A',
            $summary->humidity_avg_daily !== null ? number_format($summary->humidity_avg_daily, 2) : 'N/A',
            number_format($summary->l1_leq, 2),
            number_format($summary->l2_leq, 2),
            number_format($summary->l3_leq, 2),
            number_format($summary->l4_leq, 2),
            number_format($summary->l5_leq, 2),
            number_format($summary->l6_leq, 2),
            number_format($summary->l7_leq, 2),
            number_format($summary->l8_leq, 2),
            $status,
            $summary->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Define headings
     */
    public function headings(): array
    {
        return [
            'No',
            'Date',
            'Day',
            'Ls (dB)',
            'TWA (dBA)',
            'DND (%)',
            'T (hours)',
            'THI Avg Daily',
            'Suhu Avg (°C)',
            'Kelembapan Avg (%)',
            'L1 Leq (dB)',
            'L2 Leq (dB)',
            'L3 Leq (dB)',
            'L4 Leq (dB)',
            'L5 Leq (dB)',
            'L6 Leq (dB)',
            'L7 Leq (dB)',
            'L8 Leq (dB)',
            'Status',
            'Last Updated',
        ];
    }

    /**
     * Apply styles
     */
    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:T1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Add borders to all cells
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:T{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC'],
                ],
            ],
        ]);

        // Center align numeric columns
        $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D2:R{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("S2:S{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        return [];
    }

    /**
     * Sheet title
     */
    public function title(): string
    {
        if ($this->startDate === $this->endDate) {
            return "Daily Report - {$this->startDate}";
        }
        return "Daily Report - {$this->startDate} to {$this->endDate}";
    }
}
