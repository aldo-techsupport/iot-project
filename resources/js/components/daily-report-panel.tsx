import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useEffect, useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Download } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';

interface DailySummary {
    id: number;
    device_id: number;
    calculation_date: string;
    ls_value: number | null;
    twa_value: number | null;
    dnd_value: number | null;
    allowable_time: number | null;
    thi_avg_daily: number | null;
    temperature_avg_daily: number | null;
    humidity_avg_daily: number | null;
    is_valid: boolean;
    invalid_reason: string | null;
    invalid_periods: string[] | null;
    l1_leq: number | null;
    l1_thi_avg: number | null;
    l2_leq: number | null;
    l2_thi_avg: number | null;
    l3_leq: number | null;
    l3_thi_avg: number | null;
    l4_leq: number | null;
    l4_thi_avg: number | null;
    l5_leq: number | null;
    l5_thi_avg: number | null;
    l6_leq: number | null;
    l6_thi_avg: number | null;
    l7_leq: number | null;
    l7_thi_avg: number | null;
    l8_leq: number | null;
    l8_thi_avg: number | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    deviceId: number;
    date: string;
    loading?: boolean;
}

export default function DailyReportPanel({ deviceId, date, loading: externalLoading }: Props) {
    const [summary, setSummary] = useState<DailySummary | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [showExportDialog, setShowExportDialog] = useState(false);
    const [exportType, setExportType] = useState<'single' | 'range'>('single');
    const [exportStartDate, setExportStartDate] = useState(date);
    const [exportEndDate, setExportEndDate] = useState(date);

    const fetchDailySummary = async () => {
        try {
            setLoading(true);
            setError(null);

            const params = new URLSearchParams({
                device_id: deviceId.toString(),
                date: date,
            });

            const response = await fetch(`/api/v1/iot/daily-summary?${params}`);
            const result = await response.json();

            if (result.success) {
                setSummary(result.data);
            } else {
                setError('Failed to load daily summary');
            }
        } catch (err) {
            console.error('Error fetching daily summary:', err);
            setError('Error loading data');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchDailySummary();
        setExportStartDate(date);
        setExportEndDate(date);
    }, [deviceId, date]);

    const handleExport = () => {
        const params = new URLSearchParams({
            device_id: deviceId.toString(),
            start_date: exportType === 'single' ? date : exportStartDate,
        });

        if (exportType === 'range') {
            params.append('end_date', exportEndDate);
        }

        window.location.href = `/api/v1/iot/daily-summary/export?${params}`;
        setShowExportDialog(false);
    };

    const isLoading = loading || externalLoading;

    if (isLoading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Daily Report</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <Skeleton className="h-24 w-full" />
                    <Skeleton className="h-24 w-full" />
                    <Skeleton className="h-32 w-full" />
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Daily Report</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-center text-red-500 py-12">
                        {error}
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!summary) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Daily Report</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="text-center text-muted-foreground py-12">
                        <p className="mb-2">No daily summary available for this date</p>
                        <p className="text-sm">Complete all 8 periods (L1-L8) to generate daily report</p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    // Show INVALID DATA banner if daily summary is marked invalid
    if (!summary.is_valid) {
        return (
            <Card className="border-red-300 dark:border-red-700">
                <CardHeader className="bg-red-50 dark:bg-red-950 rounded-t-lg">
                    <CardTitle className="flex items-center gap-2 text-red-700 dark:text-red-300">
                        <span className="text-2xl">⚠</span>
                        INVALID DATA — Daily Report Tidak Dapat Dihitung
                    </CardTitle>
                </CardHeader>
                <CardContent className="pt-6 space-y-4">
                    <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 p-4">
                        <p className="text-sm font-semibold text-red-800 dark:text-red-200 mb-2">
                            Alasan:
                        </p>
                        <p className="text-sm text-red-700 dark:text-red-300">
                            {summary.invalid_reason}
                        </p>
                    </div>

                    {summary.invalid_periods && summary.invalid_periods.length > 0 && (
                        <div>
                            <p className="text-sm font-semibold mb-2">Periode bermasalah:</p>
                            <div className="flex flex-wrap gap-2">
                                {summary.invalid_periods.map((p) => (
                                    <span
                                        key={p}
                                        className="px-3 py-1 rounded-full bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 text-sm font-bold border border-red-300 dark:border-red-700"
                                    >
                                        {p} — INVALID
                                    </span>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="rounded-lg border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950 p-4">
                        <p className="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-1">
                            Apa yang harus dilakukan?
                        </p>
                        <ul className="text-sm text-yellow-700 dark:text-yellow-300 space-y-1 list-disc list-inside">
                            <li>Pastikan alat dinyalakan sebelum jam 08:00 (awal periode L1)</li>
                            <li>Setiap periode membutuhkan minimal 60 data point (1 per menit)</li>
                            <li>Periksa koneksi sensor jika terjadi gangguan di tengah periode</li>
                            <li>Data dari periode yang tidak lengkap tidak dimasukkan ke laporan harian</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Main Summary Card */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle>Daily Report - {new Date(date).toLocaleDateString('id-ID', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}</CardTitle>

                        <Dialog open={showExportDialog} onOpenChange={setShowExportDialog}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm" className="gap-2">
                                    <Download className="h-4 w-4" />
                                    Export Excel
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Export Daily Report</DialogTitle>
                                </DialogHeader>
                                <div className="space-y-4 py-4">
                                    <div className="space-y-2">
                                        <Label>Export Type</Label>
                                        <div className="flex gap-4">
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="exportType"
                                                    value="single"
                                                    checked={exportType === 'single'}
                                                    onChange={(e) => setExportType('single')}
                                                    className="rounded"
                                                />
                                                <span className="text-sm">Single Date</span>
                                            </label>
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <input
                                                    type="radio"
                                                    name="exportType"
                                                    value="range"
                                                    checked={exportType === 'range'}
                                                    onChange={(e) => setExportType('range')}
                                                    className="rounded"
                                                />
                                                <span className="text-sm">Date Range</span>
                                            </label>
                                        </div>
                                    </div>

                                    {exportType === 'single' ? (
                                        <div className="space-y-2">
                                            <Label htmlFor="export-date">Date</Label>
                                            <Input
                                                id="export-date"
                                                type="date"
                                                value={date}
                                                disabled
                                                max={new Date().toISOString().split('T')[0]}
                                            />
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="export-start-date">Start Date</Label>
                                                <Input
                                                    id="export-start-date"
                                                    type="date"
                                                    value={exportStartDate}
                                                    onChange={(e) => setExportStartDate(e.target.value)}
                                                    max={new Date().toISOString().split('T')[0]}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="export-end-date">End Date</Label>
                                                <Input
                                                    id="export-end-date"
                                                    type="date"
                                                    value={exportEndDate}
                                                    onChange={(e) => setExportEndDate(e.target.value)}
                                                    min={exportStartDate}
                                                    max={new Date().toISOString().split('T')[0]}
                                                />
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex justify-end gap-2 pt-4">
                                        <Button
                                            variant="outline"
                                            onClick={() => setShowExportDialog(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            onClick={handleExport}
                                            className="gap-2"
                                        >
                                            <Download className="h-4 w-4" />
                                            Export
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    </div>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Ls Value */}
                    <div className="rounded-lg border bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-950 dark:to-blue-900 p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground mb-1">
                                    Laeq 8h
                                </p>
                                <p className="text-4xl font-bold text-blue-700 dark:text-blue-300">
                                    {Number(summary.ls_value).toFixed(2)} dB
                                </p>
                                <p className="text-xs text-muted-foreground mt-2">
                                    Formula: 10 × log(1/8 × Σ(Tᵢ × 10^(0.1×Lᵢ)))
                                </p>
                            </div>
                            <Badge variant={Number(summary.ls_value) > 85 ? 'destructive' : 'default'} className="text-lg px-4 py-2">
                                {Number(summary.ls_value) > 85 ? 'Above Limit' : 'Normal'}
                            </Badge>
                        </div>
                    </div>

                    {/* TWA, DND and THI Daily Average */}
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                TWA (Time Weighted Average)
                            </p>
                            <p className="text-3xl font-bold">
                                {Number(summary.twa_value).toFixed(2)} dBA
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Formula: 10 × log(DND/100) + 85
                            </p>
                        </div>
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                DND (Dosis Harian)
                            </p>
                            <p className="text-3xl font-bold">
                                {Number(summary.dnd_value).toFixed(2)}%
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Formula: D(%) = (C/T) × 100%, where T = 8 / 2^((L-85)/3)
                            </p>
                            {Number(summary.dnd_value) > 100 && (
                                <p className="text-xs text-red-600 dark:text-red-400 mt-2 font-medium">
                                    ⚠️ Exceeds safe limit (&gt;100%)
                                </p>
                            )}
                            {Number(summary.dnd_value) <= 100 && (
                                <p className="text-xs text-green-600 dark:text-green-400 mt-2 font-medium">
                                    ✓ Within safe limit (≤100%)
                                </p>
                            )}
                        </div>
                        <div className="rounded-lg border bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-950 dark:to-orange-900 p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                THI Average (Daily)
                            </p>
                            <p className="text-3xl font-bold text-orange-700 dark:text-orange-300">
                                {summary.thi_avg_daily != null ? Number(summary.thi_avg_daily).toFixed(2) : 'N/A'}
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Rata-rata THI dari periode L1-L8
                            </p>
                            {summary.thi_avg_daily != null && (
                                <p className="text-xs text-orange-700 dark:text-orange-300 mt-2 font-medium">
                                    {Number(summary.thi_avg_daily) < 24 ? '❄️ Sejuk' :
                                     Number(summary.thi_avg_daily) < 27 ? '✓ Nyaman' :
                                     Number(summary.thi_avg_daily) < 29 ? '⚠️ Agak Panas' :
                                     '🔥 Panas'}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Temperature and Humidity Daily Average */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-lg border bg-gradient-to-br from-red-50 to-red-100 dark:from-red-950 dark:to-red-900 p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                🌡️ Suhu Rata-rata (Daily)
                            </p>
                            <p className="text-3xl font-bold text-red-700 dark:text-red-300">
                                {summary.temperature_avg_daily != null ? Number(summary.temperature_avg_daily).toFixed(2) : 'N/A'} °C
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Rata-rata suhu selama jam kerja (08:00-17:00)
                            </p>
                            {summary.temperature_avg_daily != null && (
                                <p className="text-xs text-red-700 dark:text-red-300 mt-2 font-medium">
                                    {Number(summary.temperature_avg_daily) < 24 ? '❄️ Dingin' :
                                     Number(summary.temperature_avg_daily) < 28 ? '✓ Nyaman' :
                                     Number(summary.temperature_avg_daily) < 32 ? '⚠️ Hangat' :
                                     '🔥 Panas'}
                                </p>
                            )}
                        </div>
                        <div className="rounded-lg border bg-gradient-to-br from-cyan-50 to-cyan-100 dark:from-cyan-950 dark:to-cyan-900 p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                💧 Kelembapan Rata-rata (Daily)
                            </p>
                            <p className="text-3xl font-bold text-cyan-700 dark:text-cyan-300">
                                {summary.humidity_avg_daily != null ? Number(summary.humidity_avg_daily).toFixed(2) : 'N/A'} %
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Rata-rata kelembapan selama jam kerja (08:00-17:00)
                            </p>
                            {summary.humidity_avg_daily != null && (
                                <p className="text-xs text-cyan-700 dark:text-cyan-300 mt-2 font-medium">
                                    {Number(summary.humidity_avg_daily) < 40 ? '🏜️ Kering' :
                                     Number(summary.humidity_avg_daily) < 60 ? '✓ Ideal' :
                                     Number(summary.humidity_avg_daily) < 70 ? '⚠️ Agak Lembab' :
                                     '💦 Lembab'}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Allowable Time (T) */}
                    <div className="rounded-lg border bg-gradient-to-br from-amber-50 to-amber-100 dark:from-amber-950 dark:to-amber-900 p-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground mb-1">
                                    T (Waktu Maksimal yang Diizinkan)
                                </p>
                                <p className="text-4xl font-bold text-amber-700 dark:text-amber-300">
                                    {summary.allowable_time.toLocaleString('id-ID', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })} jam
                                </p>
                                <p className="text-xs text-muted-foreground mt-2">
                                    Formula: T = 8 / 2^((L-85)/3)
                                </p>
                            </div>
                            <Badge
                                variant={summary.allowable_time < 8 ? 'destructive' : 'default'}
                                className="text-lg px-4 py-2"
                            >
                                {summary.allowable_time < 8 ? 'Reduced Time' : summary.allowable_time > 24 ? 'Very Safe' : 'Full Time'}
                            </Badge>
                        </div>
                        <div className="mt-4 pt-4 border-t border-amber-200 dark:border-amber-800">
                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                Waktu paparan aktual: <span className="font-bold">8 jam</span>
                            </p>
                            <p className="text-sm text-amber-700 dark:text-amber-300">
                                Waktu maksimal yang diizinkan: <span className="font-bold">
                                    {summary.allowable_time.toLocaleString('id-ID', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })} jam
                                </span>
                            </p>
                            {summary.allowable_time < 8 && (
                                <p className="text-sm text-red-600 dark:text-red-400 mt-2 font-medium">
                                    ⚠️ Waktu paparan melebihi batas yang diizinkan
                                </p>
                            )}
                            {summary.allowable_time >= 8 && summary.allowable_time <= 24 && (
                                <p className="text-sm text-green-600 dark:text-green-400 mt-2 font-medium">
                                    ✓ Dalam batas aman
                                </p>
                            )}
                            {summary.allowable_time > 24 && (
                                <p className="text-sm text-green-600 dark:text-green-400 mt-2 font-medium">
                                    ✓ Tingkat kebisingan sangat rendah, sangat aman
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Period Breakdown */}
                    <div>
                        <h3 className="text-lg font-semibold mb-4">Period Breakdown (Leq Values)</h3>
                        <div className="grid gap-3 md:grid-cols-4">
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L1</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l1_leq != null ? Number(summary.l1_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L2</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l2_leq != null ? Number(summary.l2_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L3</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l3_leq != null ? Number(summary.l3_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L4</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l4_leq != null ? Number(summary.l4_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L5</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l5_leq != null ? Number(summary.l5_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L6</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l6_leq != null ? Number(summary.l6_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L7</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l7_leq != null ? Number(summary.l7_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L8</span>
                                    <Badge variant="outline">1 hour</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l8_leq != null ? Number(summary.l8_leq).toFixed(2) : 'N/A'} dB(A)</p>
                            </div>
                        </div>
                    </div>

                    {/* Calculation Details */}
                    <div className="rounded-lg border bg-muted/30 p-4">
                        <h4 className="text-sm font-semibold mb-3">Calculation Details</h4>
                        <div className="space-y-2 text-sm text-muted-foreground">
                            <div className="flex justify-between">
                                <span>Total Work Hours:</span>
                                <span className="font-medium">8 hours</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Calculation Date:</span>
                                <span className="font-medium">
                                    {new Date(summary.calculation_date).toLocaleDateString('id-ID')}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span>Last Updated:</span>
                                <span className="font-medium">
                                    {new Date(summary.updated_at).toLocaleString('id-ID')}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Reference Information */}
                    <div className="rounded-lg border border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950 p-4">
                        <h4 className="text-sm font-semibold mb-2 text-yellow-800 dark:text-yellow-200">
                            Reference Standards
                        </h4>
                        <ul className="space-y-1 text-sm text-yellow-700 dark:text-yellow-300">
                            <li>• Maximum permissible exposure: 85 dBA for 8 hours</li>
                            <li>• Ls calculation based on 8-hour work day</li>
                            <li>• Each period (L1-L4) represents 2 hours of measurement</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
