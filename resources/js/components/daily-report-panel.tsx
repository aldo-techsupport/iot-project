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
    ls_value: number;
    twa_value: number;
    dnd_value: number;
    allowable_time: number;
    l1_leq: number;
    l2_leq: number;
    l3_leq: number;
    l4_leq: number;
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
                        <p className="text-sm">Complete all 4 periods (L1-L4) to generate daily report</p>
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
                                    {summary.ls_value.toFixed(2)} dB
                                </p>
                                <p className="text-xs text-muted-foreground mt-2">
                                    Formula: 10 × log(1/8 × Σ(Tᵢ × 10^(0.1×Lᵢ)))
                                </p>
                            </div>
                            <Badge variant={summary.ls_value > 85 ? 'destructive' : 'default'} className="text-lg px-4 py-2">
                                {summary.ls_value > 85 ? 'Above Limit' : 'Normal'}
                            </Badge>
                        </div>
                    </div>

                    {/* TWA and DND */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="rounded-lg border p-4">
                            <p className="text-sm font-medium text-muted-foreground mb-1">
                                TWA (Time Weighted Average)
                            </p>
                            <p className="text-3xl font-bold">
                                {summary.twa_value.toFixed(2)} dBA
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
                                {summary.dnd_value.toFixed(2)}%
                            </p>
                            <p className="text-xs text-muted-foreground mt-2">
                                Formula: D(%) = (C/T) × 100%, where T = 8 / 2^((L-85)/3)
                            </p>
                            {summary.dnd_value > 100 && (
                                <p className="text-xs text-red-600 dark:text-red-400 mt-2 font-medium">
                                    ⚠️ Exceeds safe limit (&gt;100%)
                                </p>
                            )}
                            {summary.dnd_value <= 100 && (
                                <p className="text-xs text-green-600 dark:text-green-400 mt-2 font-medium">
                                    ✓ Within safe limit (≤100%)
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
                                    <Badge variant="outline">2 hours</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l1_leq.toFixed(2)} dB</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L2</span>
                                    <Badge variant="outline">2 hours</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l2_leq.toFixed(2)} dB</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L3</span>
                                    <Badge variant="outline">2 hours</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l3_leq.toFixed(2)} dB</p>
                            </div>
                            <div className="rounded-lg border bg-muted/50 p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">L4</span>
                                    <Badge variant="outline">2 hours</Badge>
                                </div>
                                <p className="text-2xl font-bold">{summary.l4_leq.toFixed(2)} dB</p>
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
