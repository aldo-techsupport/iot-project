import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Database, FileText, Activity, Trash2, RefreshCw, Calendar, Plus, Edit, List, ShieldAlert, CheckCircle, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Props {
    stats: {
        total_telemetries: number;
        total_noise_data: number;
        total_daily_summaries: number;
        total_devices: number;
    };
}

interface NoiseData {
    id: number;
    slot_index: number;
    measured_at: string;
    noise_db: number;
    temperature: number;
    humidity: number;
    thi: number | null;
    is_filled: boolean;
    fill_method: string | null;
}

interface Toast {
    type: 'success' | 'error';
    message: string;
}

function getCsrfToken(): string {
    // Laravel sets XSRF-TOKEN cookie automatically; decode it for use as X-XSRF-TOKEN header
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export default function AdminDashboard({ stats }: Props) {
    const [selectedDevice, setSelectedDevice] = useState('');
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().split('T')[0]);
    const [selectedPeriod, setSelectedPeriod] = useState('L1');
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');

    // Noise Data Management
    const [noiseDataList, setNoiseDataList] = useState<NoiseData[]>([]);
    const [showNoiseDataModal, setShowNoiseDataModal] = useState(false);
    const [editingNoiseData, setEditingNoiseData] = useState<NoiseData | null>(null);
    const [showAddModal, setShowAddModal] = useState(false);
    const [loadingAction, setLoadingAction] = useState(false);

    // Toast
    const [toast, setToast] = useState<Toast | null>(null);

    // Form states
    const [newNoiseData, setNewNoiseData] = useState({
        measured_at: '',
        noise_db: '',
        temperature: '',
        humidity: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Admin', href: '/admin' },
    ];

    const showToast = (type: 'success' | 'error', message: string) => {
        setToast({ type, message });
        setTimeout(() => setToast(null), 4000);
    };

    const handleRecalculateNoisePeriod = () => {
        if (!selectedDevice || !selectedDate || !selectedPeriod) {
            alert('Please fill all fields');
            return;
        }

        router.post('/admin/recalculate-noise-period', {
            device_id: selectedDevice,
            date: selectedDate,
            period: selectedPeriod,
        }, {
            onSuccess: () => showToast('success', 'Noise period recalculated successfully'),
            onError: () => showToast('error', 'Failed to recalculate noise period'),
        });
    };

    const handleRecalculateAllDates = () => {
        if (!selectedDevice) {
            alert('Please fill Device ID');
            return;
        }

        if (!confirm(`Recalculate ALL periods (L1–L8) for ALL dates on device ${selectedDevice}? This may take a while.`)) return;

        router.post('/admin/recalculate-all-dates', {
            device_id: selectedDevice,
        }, {
            onSuccess: () => showToast('success', 'All dates recalculated successfully'),
            onError: () => showToast('error', 'Failed to recalculate all dates'),
        });
    };

    const handleRecalculateAllNoisePeriods = () => {
        if (!selectedDevice || !selectedDate) {
            alert('Please fill Device ID and Date');
            return;
        }

        if (!confirm(`Recalculate ALL 8 periods (L1–L8) for device ${selectedDevice} on ${selectedDate}?`)) return;

        router.post('/admin/recalculate-all-noise', {
            device_id: selectedDevice,
            date: selectedDate,
        }, {
            onSuccess: () => showToast('success', 'All noise periods recalculated successfully'),
            onError: () => showToast('error', 'Failed to recalculate all noise periods'),
        });
    };

    const handleRecalculateDailySummary = () => {
        if (!selectedDevice || !selectedDate) {
            alert('Please fill all fields');
            return;
        }

        router.post('/admin/recalculate-daily-summary', {
            device_id: selectedDevice,
            date: selectedDate,
        }, {
            onSuccess: () => showToast('success', 'Daily summary recalculated successfully'),
            onError: () => showToast('error', 'Failed to recalculate daily summary'),
        });
    };

    const handleBulkDeleteTelemetry = () => {
        if (!selectedDevice || !startDate || !endDate) {
            alert('Please fill all fields');
            return;
        }

        if (!confirm('Are you sure you want to delete telemetry data in this date range?')) {
            return;
        }

        router.post('/admin/bulk-delete-telemetry', {
            device_id: selectedDevice,
            start_date: startDate,
            end_date: endDate,
        }, {
            onSuccess: () => showToast('success', 'Telemetry data deleted successfully'),
            onError: () => showToast('error', 'Failed to delete telemetry data'),
        });
    };

    const handleBulkDeleteNoiseData = () => {
        if (!selectedDevice || !startDate || !endDate) {
            alert('Please fill all fields');
            return;
        }

        if (!confirm('Are you sure you want to delete noise data in this date range?')) {
            return;
        }

        router.post('/admin/bulk-delete-noise', {
            device_id: selectedDevice,
            start_date: startDate,
            end_date: endDate,
        }, {
            onSuccess: () => showToast('success', 'Noise data deleted successfully'),
            onError: () => showToast('error', 'Failed to delete noise data'),
        });
    };

    const loadNoiseDataByPeriod = async () => {
        if (!selectedDevice || !selectedDate || !selectedPeriod) {
            alert('Please fill all fields');
            return;
        }

        try {
            const params = new URLSearchParams({
                device_id: selectedDevice,
                date: selectedDate,
                period: selectedPeriod,
            });

            const response = await fetch(`/admin/noise-data/period?${params}`);
            const result = await response.json();

            if (result.success) {
                setNoiseDataList(result.data);
                setShowNoiseDataModal(true);
            } else {
                showToast('error', 'Failed to load data');
            }
        } catch (error) {
            console.error('Failed to load noise data:', error);
            showToast('error', 'Failed to load data');
        }
    };

    const refreshNoiseData = async () => {
        try {
            const params = new URLSearchParams({
                device_id: selectedDevice,
                date: selectedDate,
                period: selectedPeriod,
            });

            const response = await fetch(`/admin/noise-data/period?${params}`);
            const result = await response.json();

            if (result.success) {
                setNoiseDataList(result.data);
            }
        } catch (error) {
            console.error('Failed to refresh noise data:', error);
        }
    };

    const handleAddNoiseData = async () => {
        if (!selectedDevice || !newNoiseData.measured_at || !newNoiseData.noise_db || !newNoiseData.temperature || !newNoiseData.humidity) {
            alert('Please fill all fields');
            return;
        }

        setLoadingAction(true);
        try {
            const response = await fetch('/admin/noise-data/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    device_id: selectedDevice,
                    period: selectedPeriod,
                    date: selectedDate,
                    measured_at: newNoiseData.measured_at,
                    noise_db: parseFloat(newNoiseData.noise_db),
                    temperature: parseFloat(newNoiseData.temperature),
                    humidity: parseFloat(newNoiseData.humidity),
                }),
            });

            const result = await response.json();

            if (result.success) {
                setShowAddModal(false);
                setNewNoiseData({ measured_at: '', noise_db: '', temperature: '', humidity: '' });
                showToast('success', result.message ?? 'Data added successfully');
                await refreshNoiseData();
            } else {
                showToast('error', result.message ?? 'Failed to add data');
            }
        } catch (error) {
            console.error('Failed to add noise data:', error);
            showToast('error', 'Failed to add data');
        } finally {
            setLoadingAction(false);
        }
    };

    const handleUpdateNoiseData = async (data: NoiseData) => {
        setLoadingAction(true);
        try {
            const response = await fetch('/admin/noise-data/update', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    id: data.id,
                    noise_db: data.noise_db,
                    temperature: data.temperature,
                    humidity: data.humidity,
                }),
            });

            const result = await response.json();

            if (result.success) {
                setEditingNoiseData(null);
                showToast('success', result.message ?? 'Data updated successfully');
                await refreshNoiseData();
            } else {
                showToast('error', result.message ?? 'Failed to update data');
            }
        } catch (error) {
            console.error('Failed to update noise data:', error);
            showToast('error', 'Failed to update data');
        } finally {
            setLoadingAction(false);
        }
    };

    const handleDeleteNoiseData = async (id: number) => {
        if (!confirm('Hapus data ini secara permanen? Telemetry di menit yang sama juga akan dihapus.')) {
            return;
        }

        setLoadingAction(true);
        try {
            const response = await fetch('/admin/noise-data/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ id }),
            });

            const result = await response.json();

            if (result.success) {
                showToast('success', result.message ?? 'Slot reset successfully');
                await refreshNoiseData();
            } else {
                showToast('error', result.message ?? 'Failed to reset slot');
            }
        } catch (error) {
            console.error('Failed to delete noise data:', error);
            showToast('error', 'Failed to reset slot');
        } finally {
            setLoadingAction(false);
        }
    };

    // Build default measured_at for add modal based on selected date + period start
    const periodStartTimes: Record<string, string> = {
        L1: '08:00', L2: '09:00', L3: '10:00', L4: '11:00',
        L5: '13:00', L6: '14:00', L7: '15:00', L8: '16:00',
    };

    const periodEndTimes: Record<string, string> = {
        L1: '08:59', L2: '09:59', L3: '10:59', L4: '11:59',
        L5: '13:59', L6: '14:59', L7: '15:59', L8: '16:59',
    };

    const periodLabels: Record<string, string> = {
        L1: '08:00–09:00', L2: '09:00–10:00', L3: '10:00–11:00', L4: '11:00–12:00',
        L5: '13:00–14:00', L6: '14:00–15:00', L7: '15:00–16:00', L8: '16:00–17:00',
    };

    // Compute empty (missing) minute slots for the currently loaded period.
    // A period has 60 slots (slot_index 0..59) corresponding to minutes after the period start.
    const availableSlots = (() => {
        const startHHMM = periodStartTimes[selectedPeriod] ?? '08:00';
        const [startH, startM] = startHHMM.split(':').map(Number);

        const usedSlots = new Set(noiseDataList.map((d) => d.slot_index));

        const slots: { slot_index: number; time: string; measured_at: string }[] = [];
        for (let i = 0; i < 60; i++) {
            if (usedSlots.has(i)) continue;

            const totalMinutes = startH * 60 + startM + i;
            const hh = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
            const mm = String(totalMinutes % 60).padStart(2, '0');

            slots.push({
                slot_index: i,
                time: `${hh}:${mm}`,
                measured_at: `${selectedDate}T${hh}:${mm}`,
            });
        }
        return slots;
    })();

    const openAddModal = () => {
        // Default to the first empty slot, fallback to period start if none free
        const firstEmpty = availableSlots[0];
        const defaultMeasuredAt = firstEmpty
            ? firstEmpty.measured_at
            : `${selectedDate}T${periodStartTimes[selectedPeriod] ?? '08:00'}`;

        setNewNoiseData({
            measured_at: defaultMeasuredAt,
            noise_db: '',
            temperature: '',
            humidity: '',
        });
        setShowAddModal(true);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Admin Dashboard</h1>
                    <p className="text-muted-foreground">Manage and recalculate system data</p>
                </div>

                {/* Toast Notification */}
                {toast && (
                    <div className={`fixed top-4 right-4 z-[100] flex items-center gap-2 rounded-lg px-4 py-3 text-sm text-white shadow-lg transition-all ${
                        toast.type === 'success' ? 'bg-green-600' : 'bg-red-600'
                    }`}>
                        {toast.type === 'success'
                            ? <CheckCircle className="h-4 w-4 shrink-0" />
                            : <XCircle className="h-4 w-4 shrink-0" />
                        }
                        <span>{toast.message}</span>
                    </div>
                )}

                {/* Quick Links */}
                <Card className="border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20">
                    <CardHeader className="pb-3">
                        <CardTitle className="flex items-center gap-2 text-base text-red-700 dark:text-red-300">
                            <ShieldAlert className="h-5 w-5" />
                            Invalid Data Management
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p className="text-sm text-muted-foreground mb-3">
                            Deteksi data fake (slot sebelum alat dinyalakan), bersihkan, dan hitung ulang periode yang terpengaruh.
                        </p>
                        <button
                            onClick={() => router.visit('/admin/invalid-data')}
                            className="rounded-md bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 flex items-center gap-2"
                        >
                            <ShieldAlert className="h-4 w-4" />
                            Buka Invalid Data Manager
                        </button>
                    </CardContent>
                </Card>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total Telemetries</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_telemetries.toLocaleString()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total Noise Data</CardTitle>
                            <Database className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_noise_data.toLocaleString()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Daily Summaries</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_daily_summaries.toLocaleString()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total Devices</CardTitle>
                            <Database className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_devices.toLocaleString()}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recalculate Noise Period */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <RefreshCw className="h-5 w-5" />
                            Recalculate Noise Period
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div>
                                <label className="text-sm font-medium">Device ID</label>
                                <input
                                    type="number"
                                    value={selectedDevice}
                                    onChange={(e) => setSelectedDevice(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                    placeholder="Enter device ID"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Date</label>
                                <input
                                    type="date"
                                    value={selectedDate}
                                    onChange={(e) => setSelectedDate(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Period</label>
                                <select
                                    value={selectedPeriod}
                                    onChange={(e) => setSelectedPeriod(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                >
                                    {['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'].map((period) => (
                                        <option key={period} value={period}>{period}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <button
                                    onClick={handleRecalculateNoisePeriod}
                                    className="flex-1 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                >
                                    Recalculate Period
                                </button>
                                <button
                                    onClick={handleRecalculateAllNoisePeriods}
                                    className="flex-1 rounded-md bg-purple-600 px-4 py-2 text-white hover:bg-purple-700 flex items-center justify-center gap-1"
                                    title="Recalculate all 8 periods (L1–L8) at once"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    All Periods
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Recalculate All Dates */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <RefreshCw className="h-5 w-5" />
                            Recalculate All Dates
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">Recalculate ALL periods (L1–L8) for ALL dates on a device. Use this to backfill new fields.</p>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Device ID</label>
                                <input
                                    type="number"
                                    value={selectedDevice}
                                    onChange={(e) => setSelectedDevice(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                    placeholder="Enter device ID"
                                />
                            </div>
                            <div className="flex items-end col-span-2">
                                <button
                                    onClick={handleRecalculateAllDates}
                                    className="w-full rounded-md bg-rose-600 px-4 py-2 text-white hover:bg-rose-700 flex items-center justify-center gap-2"
                                >
                                    <RefreshCw className="h-4 w-4" />
                                    Recalculate All Dates
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Recalculate Daily Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Calendar className="h-5 w-5" />
                            Recalculate Daily Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <label className="text-sm font-medium">Device ID</label>
                                <input
                                    type="number"
                                    value={selectedDevice}
                                    onChange={(e) => setSelectedDevice(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                    placeholder="Enter device ID"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium">Date</label>
                                <input
                                    type="date"
                                    value={selectedDate}
                                    onChange={(e) => setSelectedDate(e.target.value)}
                                    className="mt-1 w-full rounded-md border p-2"
                                />
                            </div>
                            <div className="flex items-end">
                                <button
                                    onClick={handleRecalculateDailySummary}
                                    className="w-full rounded-md bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                                >
                                    Recalculate Daily
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Delete */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Trash2 className="h-5 w-5" />
                            Bulk Delete Data
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label className="text-sm font-medium">Device ID</label>
                                    <input
                                        type="number"
                                        value={selectedDevice}
                                        onChange={(e) => setSelectedDevice(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                        placeholder="Enter device ID"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Start Date</label>
                                    <input
                                        type="date"
                                        value={startDate}
                                        onChange={(e) => setStartDate(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">End Date</label>
                                    <input
                                        type="date"
                                        value={endDate}
                                        onChange={(e) => setEndDate(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={handleBulkDeleteTelemetry}
                                    className="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700"
                                >
                                    Delete Telemetry Data
                                </button>
                                <button
                                    onClick={handleBulkDeleteNoiseData}
                                    className="rounded-md bg-red-600 px-4 py-2 text-white hover:bg-red-700"
                                >
                                    Delete Noise Data
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Noise Data Management */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <List className="h-5 w-5" />
                            Manage Noise Data by Period
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label className="text-sm font-medium">Device ID</label>
                                    <input
                                        type="number"
                                        value={selectedDevice}
                                        onChange={(e) => setSelectedDevice(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                        placeholder="Enter device ID"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Date</label>
                                    <input
                                        type="date"
                                        value={selectedDate}
                                        onChange={(e) => setSelectedDate(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Period</label>
                                    <select
                                        value={selectedPeriod}
                                        onChange={(e) => setSelectedPeriod(e.target.value)}
                                        className="mt-1 w-full rounded-md border p-2"
                                    >
                                        {['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'].map((period) => (
                                            <option key={period} value={period}>{period}</option>
                                        ))}
                                    </select>
                                </div>
                                <div className="flex items-end">
                                    <button
                                        onClick={loadNoiseDataByPeriod}
                                        className="w-full rounded-md bg-purple-600 px-4 py-2 text-white hover:bg-purple-700"
                                    >
                                        Load Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Noise Data Modal */}
                {showNoiseDataModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div className="max-h-[90vh] w-full max-w-6xl overflow-auto rounded-lg bg-white p-6 dark:bg-gray-800">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="text-xl font-bold">
                                    Noise Data — {selectedPeriod} ({selectedDate}) · Device #{selectedDevice}
                                </h2>
                                <div className="flex gap-2">
                                    <button
                                        onClick={openAddModal}
                                        className="rounded-md bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 flex items-center gap-1"
                                    >
                                        <Plus className="h-4 w-4" />
                                        Add Data
                                    </button>
                                    <button
                                        onClick={refreshNoiseData}
                                        className="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 flex items-center gap-1"
                                    >
                                        <RefreshCw className="h-4 w-4" />
                                        Refresh
                                    </button>
                                    <button
                                        onClick={() => setShowNoiseDataModal(false)}
                                        className="rounded-md bg-gray-600 px-4 py-2 text-sm text-white hover:bg-gray-700"
                                    >
                                        Close
                                    </button>
                                </div>
                            </div>

                            <div className="mb-4 text-sm text-muted-foreground">
                                Total: {noiseDataList.length} / 60 data points
                                <span className="ml-3 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                    Source: Filtered DB
                                </span>
                                <span className="ml-2 text-xs">
                                    Filled: {noiseDataList.filter(d => d.is_filled).length} slot
                                </span>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Slot</th>
                                            <th className="px-3 py-2 text-left">Timestamp</th>
                                            <th className="px-3 py-2 text-right">Noise (dB(A))</th>
                                            <th className="px-3 py-2 text-right">Temp (°C)</th>
                                            <th className="px-3 py-2 text-right">Humidity (%)</th>
                                            <th className="px-3 py-2 text-right">THI</th>
                                            <th className="px-3 py-2 text-center">Status</th>
                                            <th className="px-3 py-2 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {noiseDataList.map((data) => (
                                            <tr key={data.id} className="border-b hover:bg-muted/30">
                                                {editingNoiseData?.id === data.id ? (
                                                    <>
                                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">{data.slot_index}</td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {new Date(data.measured_at).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                value={editingNoiseData.noise_db}
                                                                onChange={(e) => setEditingNoiseData({
                                                                    ...editingNoiseData,
                                                                    noise_db: parseFloat(e.target.value),
                                                                })}
                                                                className="w-full rounded border px-2 py-1 text-right"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                value={editingNoiseData.temperature}
                                                                onChange={(e) => setEditingNoiseData({
                                                                    ...editingNoiseData,
                                                                    temperature: parseFloat(e.target.value),
                                                                })}
                                                                className="w-full rounded border px-2 py-1 text-right"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                value={editingNoiseData.humidity}
                                                                onChange={(e) => setEditingNoiseData({
                                                                    ...editingNoiseData,
                                                                    humidity: parseFloat(e.target.value),
                                                                })}
                                                                className="w-full rounded border px-2 py-1 text-right"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 text-right text-xs text-muted-foreground">—</td>
                                                        <td className="px-3 py-2 text-center">
                                                            <span className="text-xs text-yellow-600">Manual</span>
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            <button
                                                                onClick={() => handleUpdateNoiseData(editingNoiseData)}
                                                                disabled={loadingAction}
                                                                className="mr-2 text-green-600 hover:text-green-800 disabled:opacity-50"
                                                            >
                                                                {loadingAction ? '...' : 'Save'}
                                                            </button>
                                                            <button
                                                                onClick={() => setEditingNoiseData(null)}
                                                                className="text-gray-600 hover:text-gray-800"
                                                            >
                                                                Cancel
                                                            </button>
                                                        </td>
                                                    </>
                                                ) : (
                                                    <>
                                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">{data.slot_index}</td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {new Date(data.measured_at).toLocaleString('id-ID')}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">{data.noise_db != null ? data.noise_db.toFixed(2) : '-'}</td>
                                                        <td className="px-3 py-2 text-right">{data.temperature != null ? data.temperature.toFixed(2) : '-'}</td>
                                                        <td className="px-3 py-2 text-right">{data.humidity != null ? data.humidity.toFixed(2) : '-'}</td>
                                                        <td className="px-3 py-2 text-right">
                                                            {data.thi != null ? (
                                                                <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                                    data.thi >= 29 ? 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
                                                                    : data.thi >= 27 ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'
                                                                    : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                                                }`}>
                                                                    {data.thi.toFixed(2)}
                                                                </span>
                                                            ) : '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            {!data.is_filled ? (
                                                                <span className="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900 dark:text-green-300">Real</span>
                                                            ) : data.fill_method === 'manual' ? (
                                                                <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">Manual</span>
                                                            ) : data.fill_method === 'zero' ? (
                                                                <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-300">Zero</span>
                                                            ) : (
                                                                <span className="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300">Copied</span>
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            <button
                                                                onClick={() => setEditingNoiseData(data)}
                                                                className="mr-2 text-blue-600 hover:text-blue-800"
                                                                title="Edit"
                                                            >
                                                                <Edit className="inline h-4 w-4" />
                                                            </button>
                                                            <button
                                                                onClick={() => handleDeleteNoiseData(data.id)}
                                                                disabled={loadingAction}
                                                                className="text-red-600 hover:text-red-800 disabled:opacity-50"
                                                                title="Reset slot (filled copy)"
                                                            >
                                                                <Trash2 className="inline h-4 w-4" />
                                                            </button>
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                        {noiseDataList.length === 0 && (
                                            <tr>
                                                <td colSpan={8} className="px-3 py-8 text-center text-muted-foreground">
                                                    No data found for this period
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Add Noise Data Modal */}
                {showAddModal && (
                    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/50">
                        <div className="w-full max-w-md rounded-lg bg-white p-6 dark:bg-gray-800">
                            <h2 className="mb-1 text-xl font-bold">Add Noise Data</h2>
                            <p className="mb-4 text-sm text-muted-foreground">
                                Period: <strong>{selectedPeriod}</strong> · Date: <strong>{selectedDate}</strong> · Device: <strong>#{selectedDevice}</strong>
                            </p>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium">Timestamp</label>
                                    <input
                                        type="datetime-local"
                                        value={newNoiseData.measured_at}
                                        min={`${selectedDate}T${periodStartTimes[selectedPeriod]}`}
                                        max={`${selectedDate}T${periodEndTimes[selectedPeriod]}`}
                                        onChange={(e) => setNewNoiseData({ ...newNoiseData, measured_at: e.target.value })}
                                        className="mt-1 w-full rounded-md border p-2"
                                    />
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Harus dalam rentang periode {selectedPeriod}: <strong>{periodLabels[selectedPeriod]}</strong>
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Noise (dB)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={newNoiseData.noise_db}
                                        onChange={(e) => setNewNoiseData({ ...newNoiseData, noise_db: e.target.value })}
                                        className="mt-1 w-full rounded-md border p-2"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Temperature (°C)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={newNoiseData.temperature}
                                        onChange={(e) => setNewNoiseData({ ...newNoiseData, temperature: e.target.value })}
                                        className="mt-1 w-full rounded-md border p-2"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium">Humidity (%)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={newNoiseData.humidity}
                                        onChange={(e) => setNewNoiseData({ ...newNoiseData, humidity: e.target.value })}
                                        className="mt-1 w-full rounded-md border p-2"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        onClick={handleAddNoiseData}
                                        disabled={loadingAction}
                                        className="flex-1 rounded-md bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {loadingAction ? 'Saving...' : 'Add Data'}
                                    </button>
                                    <button
                                        onClick={() => {
                                            setShowAddModal(false);
                                            setNewNoiseData({ measured_at: '', noise_db: '', temperature: '', humidity: '' });
                                        }}
                                        disabled={loadingAction}
                                        className="flex-1 rounded-md bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 disabled:opacity-50"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}