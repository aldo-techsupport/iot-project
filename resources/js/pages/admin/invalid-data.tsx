import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle,
    ChevronDown,
    ChevronRight,
    Loader2,
    RefreshCw,
    ShieldAlert,
    Trash2,
    Eye,
} from 'lucide-react';
import { useState } from 'react';

// ─── CSRF helper ─────────────────────────────────────────────────────────────
// Laravel sets XSRF-TOKEN cookie; we read it and pass as X-XSRF-TOKEN header.
function getCsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    return fetch(url, {
        ...options,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
            ...(options.headers ?? {}),
        },
    });
}

// ─── Types ────────────────────────────────────────────────────────────────────

interface PeriodIssue {
    period: string;
    time: string;
    fake_slots: number;
    fake_until: string | null;
    first_real_at: string | null;
    data_count: number;
    leq_value: number | null;
    is_valid: boolean | null;
    has_fake: boolean;
    has_invalid: boolean;
}

interface ReportRow {
    device_id: number;
    device_name: string;
    device_location: string;
    date: string;
    total_fake: number;
    total_invalid: number;
    periods: PeriodIssue[];
}

interface PreviewRow {
    device_id: number;
    device_name: string;
    period: string;
    rows_to_delete: number;
    reason: string;
    first_real_at: string | null;
}

interface Props {
    report: ReportRow[];
    invalidCalcCount: number;
    invalidSummaryCount: number;
    totalAffectedDates: number;
    grandTotalFake: number;
}

// ─── Component ────────────────────────────────────────────────────────────────

export default function InvalidData({
    report: initialReport,
    invalidCalcCount,
    invalidSummaryCount,
    totalAffectedDates,
    grandTotalFake,
}: Props) {
    const [report, setReport] = useState<ReportRow[]>(initialReport);
    const [counts, setCounts] = useState({
        invalidCalcCount,
        invalidSummaryCount,
        totalAffectedDates,
        grandTotalFake,
    });

    // Fix All state
    const [fixingAll, setFixingAll] = useState(false);
    const [fixResult, setFixResult] = useState<string | null>(null);

    // Cleanup state
    const [cleanupDate, setCleanupDate] = useState(new Date().toISOString().split('T')[0]);
    const [previewing, setPreviewing] = useState(false);
    const [previewData, setPreviewData] = useState<PreviewRow[] | null>(null);
    const [previewTotal, setPreviewTotal] = useState(0);
    const [cleaning, setCleaning] = useState(false);
    const [cleanupResult, setCleanupResult] = useState<string | null>(null);

    // Expanded rows
    const [expandedRows, setExpandedRows] = useState<Set<string>>(new Set());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Admin', href: '/admin' },
        { title: 'Invalid Data', href: '/admin/invalid-data' },
    ];

    // ── Fix All ──────────────────────────────────────────────────────────────

    const handleFixAll = async () => {
        if (
            !confirm(
                `Hapus semua ${counts.grandTotalFake} slot fake dari noise_filtered_data dan hitung ulang?\n\nProses ini tidak dapat dibatalkan.`,
            )
        )
            return;

        setFixingAll(true);
        setFixResult(null);

        try {
            const res = await apiFetch('/admin/invalid-data/fix-all', {
                method: 'POST',
            });
            const data = await res.json();

            if (data.success) {
                setReport(data.report);
                setCounts(data.counts);
                setFixResult(`✓ ${data.message}`);
            } else {
                setFixResult('✗ Gagal: ' + (data.message ?? 'Unknown error'));
            }
        } catch (e) {
            setFixResult('✗ Request gagal. Cek console.');
            console.error(e);
        } finally {
            setFixingAll(false);
        }
    };

    // ── Preview Cleanup ──────────────────────────────────────────────────────

    const handlePreviewCleanup = async () => {
        if (!cleanupDate) {
            alert('Pilih tanggal terlebih dahulu.');
            return;
        }

        setPreviewing(true);
        setPreviewData(null);
        setCleanupResult(null);

        try {
            const res = await apiFetch(`/admin/invalid-data/preview-cleanup?date=${cleanupDate}`, {
                method: 'GET',
            });
            const data = await res.json();

            if (data.success) {
                setPreviewData(data.preview);
                setPreviewTotal(data.total_to_delete);
            } else {
                alert('Gagal memuat preview.');
            }
        } catch (e) {
            alert('Request gagal. Cek console.');
            console.error(e);
        } finally {
            setPreviewing(false);
        }
    };

    // ── Execute Cleanup ──────────────────────────────────────────────────────

    const handleCleanup = async () => {
        if (!cleanupDate) {
            alert('Pilih tanggal terlebih dahulu.');
            return;
        }

        if (
            !confirm(
                `Hapus ${previewTotal} baris noise_raw_data (filled sebelum alat nyala) untuk tanggal ${cleanupDate}?\n\nKalkulasi & filtered data terkait juga akan dihapus agar bisa dihitung ulang.\n\nProses ini tidak dapat dibatalkan.`,
            )
        )
            return;

        setCleaning(true);
        setCleanupResult(null);

        try {
            const res = await apiFetch('/admin/invalid-data/cleanup', {
                method: 'POST',
                body: JSON.stringify({ date: cleanupDate }),
            });
            const data = await res.json();

            if (data.success) {
                setReport(data.report);
                setCounts(data.counts);
                setPreviewData(null);
                setCleanupResult(`✓ ${data.message}`);
            } else {
                setCleanupResult('✗ Gagal: ' + (data.message ?? 'Unknown error'));
            }
        } catch (e) {
            setCleanupResult('✗ Request gagal. Cek console.');
            console.error(e);
        } finally {
            setCleaning(false);
        }
    };

    // ── Toggle expand ────────────────────────────────────────────────────────

    const toggleRow = (key: string) => {
        setExpandedRows((prev) => {
            const next = new Set(prev);
            next.has(key) ? next.delete(key) : next.add(key);
            return next;
        });
    };

    // ─────────────────────────────────────────────────────────────────────────

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invalid Data Manager" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">

                {/* Header */}
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => router.visit('/admin')}
                        className="rounded-md p-1.5 hover:bg-muted"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </button>
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <ShieldAlert className="h-6 w-6 text-red-500" />
                            Invalid Data Manager
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Deteksi & bersihkan data fake akibat alat baru dinyalakan di tengah periode
                        </p>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Tanggal Bermasalah</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-orange-500">{counts.totalAffectedDates}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Total Slot Fake</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-red-500">{counts.grandTotalFake}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Kalkulasi Invalid</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-yellow-500">{counts.invalidCalcCount}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium text-muted-foreground">Summary Invalid</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-3xl font-bold text-yellow-500">{counts.invalidSummaryCount}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* ── Fix All (noise_filtered_data) ── */}
                <Card className="border-orange-200 dark:border-orange-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-orange-700 dark:text-orange-300">
                            <RefreshCw className="h-5 w-5" />
                            Fix All — Bersihkan noise_filtered_data
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <p className="text-sm text-muted-foreground">
                            Hapus semua slot fake di <code className="rounded bg-muted px-1">noise_filtered_data</code> (slot sebelum alat nyala atau tanpa data real),
                            lalu hitung ulang kalkulasi periode yang terpengaruh.
                        </p>

                        {counts.grandTotalFake === 0 ? (
                            <div className="flex items-center gap-2 rounded-md bg-green-50 dark:bg-green-950/30 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                                <CheckCircle className="h-4 w-4" />
                                Tidak ada slot fake terdeteksi di noise_filtered_data.
                            </div>
                        ) : (
                            <div className="flex items-center gap-3">
                                <button
                                    onClick={handleFixAll}
                                    disabled={fixingAll}
                                    className="flex items-center gap-2 rounded-md bg-orange-600 px-4 py-2 text-sm text-white hover:bg-orange-700 disabled:opacity-60"
                                >
                                    {fixingAll ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <RefreshCw className="h-4 w-4" />
                                    )}
                                    {fixingAll ? 'Memproses...' : `Fix All (${counts.grandTotalFake} slot fake)`}
                                </button>
                            </div>
                        )}

                        {fixResult && (
                            <div
                                className={`rounded-md px-4 py-3 text-sm ${
                                    fixResult.startsWith('✓')
                                        ? 'bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-300'
                                        : 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300'
                                }`}
                            >
                                {fixResult}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ── Cleanup Pre-Device Fills (noise_raw_data) ── */}
                <Card className="border-red-200 dark:border-red-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-red-700 dark:text-red-300">
                            <Trash2 className="h-5 w-5" />
                            Cleanup Pre-Device Fills — Bersihkan noise_raw_data
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm text-muted-foreground">
                            Hapus baris <code className="rounded bg-muted px-1">is_filled=true</code> di{' '}
                            <code className="rounded bg-muted px-1">noise_raw_data</code> yang timestampnya{' '}
                            <strong>sebelum data real pertama</strong> masuk pada tanggal tertentu.
                            Kalkulasi & filtered data terkait juga dihapus agar bisa dihitung ulang.
                        </p>

                        {/* Date picker + actions */}
                        <div className="flex flex-wrap items-end gap-3">
                            <div>
                                <label className="mb-1 block text-sm font-medium">Tanggal</label>
                                <input
                                    type="date"
                                    value={cleanupDate}
                                    onChange={(e) => {
                                        setCleanupDate(e.target.value);
                                        setPreviewData(null);
                                        setCleanupResult(null);
                                    }}
                                    className="rounded-md border px-3 py-2 text-sm dark:bg-gray-800 dark:border-gray-600"
                                />
                            </div>

                            <button
                                onClick={handlePreviewCleanup}
                                disabled={previewing || cleaning}
                                className="flex items-center gap-2 rounded-md border border-blue-500 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30 disabled:opacity-60"
                            >
                                {previewing ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <Eye className="h-4 w-4" />
                                )}
                                {previewing ? 'Memuat preview...' : 'Preview'}
                            </button>

                            {previewData !== null && previewTotal > 0 && (
                                <button
                                    onClick={handleCleanup}
                                    disabled={cleaning}
                                    className="flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 disabled:opacity-60"
                                >
                                    {cleaning ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Trash2 className="h-4 w-4" />
                                    )}
                                    {cleaning ? 'Menghapus...' : `Hapus ${previewTotal} baris`}
                                </button>
                            )}
                        </div>

                        {/* Preview table */}
                        {previewData !== null && (
                            <div>
                                {previewData.length === 0 ? (
                                    <div className="flex items-center gap-2 rounded-md bg-green-50 dark:bg-green-950/30 px-4 py-3 text-sm text-green-700 dark:text-green-300">
                                        <CheckCircle className="h-4 w-4" />
                                        Tidak ada data yang perlu dibersihkan untuk tanggal {cleanupDate}.
                                    </div>
                                ) : (
                                    <div className="overflow-x-auto rounded-md border">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted text-xs uppercase">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Device</th>
                                                    <th className="px-3 py-2 text-center">Periode</th>
                                                    <th className="px-3 py-2 text-right">Baris Dihapus</th>
                                                    <th className="px-3 py-2 text-left">Keterangan</th>
                                                    <th className="px-3 py-2 text-center">Data Real Pertama</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {previewData.map((row, i) => (
                                                    <tr key={i} className="border-t hover:bg-muted/30">
                                                        <td className="px-3 py-2 font-medium">{row.device_name}</td>
                                                        <td className="px-3 py-2 text-center">
                                                            <span className="rounded bg-blue-100 dark:bg-blue-900 px-2 py-0.5 text-xs font-mono">
                                                                {row.period}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-bold text-red-600">
                                                            {row.rows_to_delete}
                                                        </td>
                                                        <td className="px-3 py-2 text-muted-foreground text-xs">{row.reason}</td>
                                                        <td className="px-3 py-2 text-center font-mono text-xs">
                                                            {row.first_real_at ?? '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="bg-muted font-semibold">
                                                <tr>
                                                    <td colSpan={2} className="px-3 py-2">Total</td>
                                                    <td className="px-3 py-2 text-right text-red-600">{previewTotal}</td>
                                                    <td colSpan={2} />
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Cleanup result */}
                        {cleanupResult && (
                            <div
                                className={`rounded-md px-4 py-3 text-sm ${
                                    cleanupResult.startsWith('✓')
                                        ? 'bg-green-50 dark:bg-green-950/30 text-green-700 dark:text-green-300'
                                        : 'bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300'
                                }`}
                            >
                                {cleanupResult}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ── Report Table ── */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-yellow-500" />
                            Laporan Masalah ({report.length} entri)
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {report.length === 0 ? (
                            <div className="flex items-center gap-2 rounded-md bg-green-50 dark:bg-green-950/30 px-4 py-4 text-sm text-green-700 dark:text-green-300">
                                <CheckCircle className="h-5 w-5" />
                                Semua data bersih — tidak ada masalah terdeteksi.
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted text-xs uppercase">
                                        <tr>
                                            <th className="w-8 px-2 py-2" />
                                            <th className="px-3 py-2 text-left">Device</th>
                                            <th className="px-3 py-2 text-left">Tanggal</th>
                                            <th className="px-3 py-2 text-right">Slot Fake</th>
                                            <th className="px-3 py-2 text-right">Periode Invalid</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.map((row) => {
                                            const key = `${row.device_id}-${row.date}`;
                                            const expanded = expandedRows.has(key);
                                            return (
                                                <>
                                                    <tr
                                                        key={key}
                                                        className="border-t cursor-pointer hover:bg-muted/40"
                                                        onClick={() => toggleRow(key)}
                                                    >
                                                        <td className="px-2 py-2 text-center text-muted-foreground">
                                                            {expanded ? (
                                                                <ChevronDown className="h-4 w-4 inline" />
                                                            ) : (
                                                                <ChevronRight className="h-4 w-4 inline" />
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 font-medium">
                                                            {row.device_name}
                                                            {row.device_location && (
                                                                <span className="ml-1 text-xs text-muted-foreground">
                                                                    ({row.device_location})
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs">{row.date}</td>
                                                        <td className="px-3 py-2 text-right">
                                                            {row.total_fake > 0 ? (
                                                                <span className="rounded bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-bold text-red-700 dark:text-red-300">
                                                                    {row.total_fake}
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground">—</span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            {row.total_invalid > 0 ? (
                                                                <span className="rounded bg-yellow-100 dark:bg-yellow-900 px-2 py-0.5 text-xs font-bold text-yellow-700 dark:text-yellow-300">
                                                                    {row.total_invalid}
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground">—</span>
                                                            )}
                                                        </td>
                                                    </tr>

                                                    {/* Expanded period detail */}
                                                    {expanded && (
                                                        <tr key={`${key}-detail`} className="bg-muted/20">
                                                            <td />
                                                            <td colSpan={4} className="px-3 py-3">
                                                                <div className="overflow-x-auto rounded border bg-background">
                                                                    <table className="w-full text-xs">
                                                                        <thead className="bg-muted">
                                                                            <tr>
                                                                                <th className="px-2 py-1.5 text-left">Periode</th>
                                                                                <th className="px-2 py-1.5 text-left">Waktu</th>
                                                                                <th className="px-2 py-1.5 text-right">Slot Fake</th>
                                                                                <th className="px-2 py-1.5 text-center">Fake s/d</th>
                                                                                <th className="px-2 py-1.5 text-center">Real Pertama</th>
                                                                                <th className="px-2 py-1.5 text-right">Data Count</th>
                                                                                <th className="px-2 py-1.5 text-right">Leq (dB)</th>
                                                                                <th className="px-2 py-1.5 text-center">Valid</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            {row.periods.map((p) => (
                                                                                <tr key={p.period} className="border-t">
                                                                                    <td className="px-2 py-1.5 font-mono font-bold">{p.period}</td>
                                                                                    <td className="px-2 py-1.5 text-muted-foreground">{p.time}</td>
                                                                                    <td className="px-2 py-1.5 text-right">
                                                                                        {p.fake_slots > 0 ? (
                                                                                            <span className="text-red-600 font-bold">{p.fake_slots}</span>
                                                                                        ) : '—'}
                                                                                    </td>
                                                                                    <td className="px-2 py-1.5 text-center font-mono">
                                                                                        {p.fake_until ?? '—'}
                                                                                    </td>
                                                                                    <td className="px-2 py-1.5 text-center font-mono">
                                                                                        {p.first_real_at ?? '—'}
                                                                                    </td>
                                                                                    <td className="px-2 py-1.5 text-right">{p.data_count}</td>
                                                                                    <td className="px-2 py-1.5 text-right">
                                                                                        {p.leq_value != null ? p.leq_value.toFixed(2) : '—'}
                                                                                    </td>
                                                                                    <td className="px-2 py-1.5 text-center">
                                                                                        {p.is_valid === null ? (
                                                                                            <span className="text-muted-foreground">—</span>
                                                                                        ) : p.is_valid ? (
                                                                                            <CheckCircle className="inline h-3.5 w-3.5 text-green-500" />
                                                                                        ) : (
                                                                                            <AlertTriangle className="inline h-3.5 w-3.5 text-yellow-500" />
                                                                                        )}
                                                                                    </td>
                                                                                </tr>
                                                                            ))}
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    )}
                                                </>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
