import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type ChartDataPoint, type DeviceDetail } from '@/types/iot';
import { Head, Link, router } from '@inertiajs/react';
import { Droplets, History, RefreshCw, ThermometerSun, Volume2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    device: DeviceDetail;
    chartData: ChartDataPoint[];
}

const REFRESH_INTERVAL = 10000; // 10 detik

function StatusBadge({ status }: { status: DeviceDetail['status'] }) {
    const styles = {
        online: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        idle: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        offline: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        never_connected: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };

    const labels = {
        online: 'Online',
        idle: 'Idle',
        offline: 'Offline',
        never_connected: 'Never Connected',
    };

    return (
        <span className={`inline-flex items-center rounded-full px-3 py-1 text-sm font-medium ${styles[status]}`}>
            {labels[status]}
        </span>
    );
}

function MetricCard({
    title,
    value,
    unit,
    icon: Icon,
    color,
    min,
    max,
    avg,
}: {
    title: string;
    value: number | null;
    unit: string;
    icon: React.ElementType;
    color: string;
    min?: number;
    max?: number;
    avg?: number;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium">{title}</CardTitle>
                <Icon className={`h-5 w-5 ${color}`} />
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-bold">
                    {value !== null ? `${value}${unit}` : '-'}
                </div>
                {min !== undefined && max !== undefined && avg !== undefined && (
                    <div className="text-muted-foreground mt-2 text-xs">
                        <span>Min: {min.toFixed(1)}</span>
                        <span className="mx-2">|</span>
                        <span>Avg: {avg.toFixed(1)}</span>
                        <span className="mx-2">|</span>
                        <span>Max: {max.toFixed(1)}</span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function SimpleLineChart({ data, dataKey, color, label }: { data: ChartDataPoint[]; dataKey: keyof ChartDataPoint; color: string; label: string }) {
    if (data.length === 0) {
        return (
            <div className="flex h-48 items-center justify-center text-muted-foreground">
                No data available
            </div>
        );
    }

    const values = data.map((d) => d[dataKey] as number);
    const minVal = Math.min(...values);
    const maxVal = Math.max(...values);
    const range = maxVal - minVal || 1;

    const width = 100;
    const height = 48;
    const padding = 2;

    const points = data.map((d, i) => {
        const x = padding + (i / (data.length - 1)) * (width - 2 * padding);
        const y = height - padding - ((d[dataKey] as number - minVal) / range) * (height - 2 * padding);
        return `${x},${y}`;
    }).join(' ');

    return (
        <div className="relative">
            <div className="mb-2 flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span>{maxVal.toFixed(1)}</span>
            </div>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-48 w-full" preserveAspectRatio="none">
                <polyline
                    fill="none"
                    stroke={color}
                    strokeWidth="0.5"
                    points={points}
                />
            </svg>
            <div className="flex justify-between text-xs text-muted-foreground">
                <span>24h ago</span>
                <span>{minVal.toFixed(1)}</span>
                <span>Now</span>
            </div>
        </div>
    );
}

export default function DeviceDetailPage({ device, chartData }: Props) {
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [lastUpdate, setLastUpdate] = useState(new Date());

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'IoT Monitoring', href: '/iot' },
        { title: device.name, href: `/iot/devices/${device.id}` },
    ];

    const refresh = () => {
        setIsRefreshing(true);
        router.reload({
            only: ['device', 'chartData'],
            onFinish: () => {
                setIsRefreshing(false);
                setLastUpdate(new Date());
            },
        });
    };

    useEffect(() => {
        if (!autoRefresh) return;

        const interval = setInterval(() => {
            refresh();
        }, REFRESH_INTERVAL);

        return () => clearInterval(interval);
    }, [autoRefresh]);

    const stats = useMemo(() => {
        if (chartData.length === 0) return null;

        const temps = chartData.map((d) => d.temperature);
        const humidities = chartData.map((d) => d.humidity);
        const noises = chartData.map((d) => d.noise_db);

        return {
            temperature: {
                min: Math.min(...temps),
                max: Math.max(...temps),
                avg: temps.reduce((a, b) => a + b, 0) / temps.length,
            },
            humidity: {
                min: Math.min(...humidities),
                max: Math.max(...humidities),
                avg: humidities.reduce((a, b) => a + b, 0) / humidities.length,
            },
            noise: {
                min: Math.min(...noises),
                max: Math.max(...noises),
                avg: noises.reduce((a, b) => a + b, 0) / noises.length,
            },
        };
    }, [chartData]);

    const telemetry = device.latest_telemetry;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${device.name} - IoT Monitoring`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{device.name}</h1>
                            <StatusBadge status={device.status} />
                        </div>
                        <p className="text-muted-foreground">{device.location || 'No location set'}</p>
                        {device.description && <p className="mt-1 text-sm text-muted-foreground">{device.description}</p>}
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <button
                                onClick={refresh}
                                disabled={isRefreshing}
                                className="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-muted disabled:opacity-50"
                            >
                                <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
                            </button>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={autoRefresh}
                                    onChange={(e) => setAutoRefresh(e.target.checked)}
                                    className="rounded"
                                />
                                Auto
                            </label>
                        </div>
                        <Link
                            href={`/iot/devices/${device.id}/log`}
                            className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            <History className="h-4 w-4" />
                            View Log
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <MetricCard
                        title="Temperature"
                        value={telemetry?.temperature ?? null}
                        unit="°C"
                        icon={ThermometerSun}
                        color="text-orange-500"
                        min={stats?.temperature.min}
                        max={stats?.temperature.max}
                        avg={stats?.temperature.avg}
                    />
                    <MetricCard
                        title="Humidity"
                        value={telemetry?.humidity ?? null}
                        unit="%"
                        icon={Droplets}
                        color="text-blue-500"
                        min={stats?.humidity.min}
                        max={stats?.humidity.max}
                        avg={stats?.humidity.avg}
                    />
                    <MetricCard
                        title="Noise Level"
                        value={telemetry?.noise_db ?? null}
                        unit=" dB"
                        icon={Volume2}
                        color="text-purple-500"
                        min={stats?.noise.min}
                        max={stats?.noise.max}
                        avg={stats?.noise.avg}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>24-Hour Trends</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-3">
                            <SimpleLineChart data={chartData} dataKey="temperature" color="#f97316" label="Temperature (°C)" />
                            <SimpleLineChart data={chartData} dataKey="humidity" color="#3b82f6" label="Humidity (%)" />
                            <SimpleLineChart data={chartData} dataKey="noise_db" color="#a855f7" label="Noise (dB)" />
                        </div>
                    </CardContent>
                </Card>

                {telemetry && (
                    <p className="text-center text-sm text-muted-foreground">
                        Last updated: {lastUpdate.toLocaleTimeString()}
                        {autoRefresh && ' • Auto-refresh enabled'}
                    </p>
                )}
            </div>
        </AppLayout>
    );
}
