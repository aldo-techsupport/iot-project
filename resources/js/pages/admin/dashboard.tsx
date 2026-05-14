import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Database, FileText, Activity, Trash2, RefreshCw, Calendar, Plus, Edit, List } from 'lucide-react';
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

    const handleRecalculateNoisePeriod = () => {
        if (!selectedDevice || !selectedDate || !selectedPeriod) {
            alert('Please fill all fields');
            return;
        }

        router.post('/admin/recalculate-noise-period', {
            device_id: selectedDevice,
            date: selectedDate,
            period: selectedPeriod,
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
                alert('Failed to load data');
            }
        } catch (error) {
            console.error('Failed to load noise data:', error);
            alert('Failed to load data');
        }
    };

    const handleAddNoiseData = () => {
        if (!selectedDevice || !newNoiseData.measured_at || !newNoiseData.noise_db || !newNoiseData.temperature || !newNoiseData.humidity) {
            alert('Please fill all fields');
            return;
        }

        router.post('/admin/noise-data/add', {
            device_id: selectedDevice,
            ...newNoiseData,
        }, {
            onSuccess: () => {
                setShowAddModal(false);
                setNewNoiseData({ measured_at: '', noise_db: '', temperature: '', humidity: '' });
                loadNoiseDataByPeriod();
            },
        });
    };

    const handleUpdateNoiseData = (data: NoiseData) => {
        router.put('/admin/noise-data/update', {
            id: data.id,
            noise_db: data.noise_db,
            temperature: data.temperature,
            humidity: data.humidity,
        }, {
            onSuccess: () => {
                setEditingNoiseData(null);
                loadNoiseDataByPeriod();
            },
        });
    };

    const handleDeleteNoiseData = (id: number) => {
        if (!confirm('Are you sure you want to delete this data?')) {
            return;
        }

        router.delete('/admin/noise-data/delete', {
            data: { id },
            onSuccess: () => {
                loadNoiseDataByPeriod();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Admin Dashboard</h1>
                    <p className="text-muted-foreground">Manage and recalculate system data</p>
                </div>

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
                            <div className="flex items-end">
                                <button
                                    onClick={handleRecalculateNoisePeriod}
                                    className="w-full rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                >
                                    Recalculate Period
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
                                    Noise Data - {selectedPeriod} ({selectedDate})
                                </h2>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => setShowAddModal(true)}
                                        className="rounded-md bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700"
                                    >
                                        <Plus className="inline h-4 w-4 mr-1" />
                                        Add Data
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
                                            <th className="px-3 py-2 text-right">Noise (dB)</th>
                                            <th className="px-3 py-2 text-right">Temp (°C)</th>
                                            <th className="px-3 py-2 text-right">Humidity (%)</th>
                                            <th className="px-3 py-2 text-right">THI</th>
                                            <th className="px-3 py-2 text-center">Status</th>
                                            <th className="px-3 py-2 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {noiseDataList.map((data, index) => (
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
                                                                className="mr-2 text-green-600 hover:text-green-800"
                                                            >
                                                                Save
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
                                                                className="text-red-600 hover:text-red-800"
                                                                title="Reset slot (filled copy)"
                                                            >
                                                                <Trash2 className="inline h-4 w-4" />
                                                            </button>
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {/* Add Noise Data Modal */}
                {showAddModal && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div className="w-full max-w-md rounded-lg bg-white p-6 dark:bg-gray-800">
                            <h2 className="mb-4 text-xl font-bold">Add Noise Data</h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium">Timestamp</label>
                                    <input
                                        type="datetime-local"
                                        value={newNoiseData.measured_at}
                                        onChange={(e) => setNewNoiseData({ ...newNoiseData, measured_at: e.target.value })}
                                        className="mt-1 w-full rounded-md border p-2"
                                    />
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
                                        className="flex-1 rounded-md bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                                    >
                                        Add Data
                                    </button>
                                    <button
                                        onClick={() => {
                                            setShowAddModal(false);
                                            setNewNoiseData({ measured_at: '', noise_db: '', temperature: '', humidity: '' });
                                        }}
                                        className="flex-1 rounded-md bg-gray-600 px-4 py-2 text-white hover:bg-gray-700"
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
