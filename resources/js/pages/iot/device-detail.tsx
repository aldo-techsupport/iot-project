import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type ChartDataPoint, type DeviceDetail } from '@/types/iot';
import { Head, Link, router } from '@inertiajs/react';
import { Droplets, History, RefreshCw, ThermometerSun, Volume2, Activity, BarChart3, Gauge, FileText, MessageSquare, LayoutGrid, LayoutList } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import RealTimeNoiseChart from '@/components/charts/real-time-noise-chart';
import RealTimeTelemetryChart from '@/components/charts/real-time-telemetry-chart';
import TelemetrySvgChart from '@/components/charts/telemetry-svg-chart';
import NoiseSvgChart from '@/components/charts/noise-svg-chart';
import ThiChart from '@/components/charts/thi-chart';
import NoiseStatisticsPanel from '@/components/noise-statistics-panel';
import PeriodSelector from '@/components/period-selector';
import PeriodBarView from '@/components/period-bar-view';
import NoiseDataModal from '@/components/noise-data-modal';
import DailyReportPanel from '@/components/daily-report-panel';
import DeviceTelegramSettings from '@/components/device-telegram-settings';
import { Period } from '@/types/period';

interface Props {
    device: DeviceDetail;
    chartData: ChartDataPoint[];
}

interface NoiseCalculation {
    id: number;
    period: Period;
    min_value: number;
    max_value: number;
    average_value: number;
    leq_value: number;
    thi_average: number;
    data_count: number;
    calculation_date: string;
    updated_at: string;
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
                        <span>{avg.toFixed(1)}</span>
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
    const [activeTab, setActiveTab] = useState<'overview' | 'noise' | 'thi' | 'daily' | 'telegram'>('overview');
    const [overviewViewMode, setOverviewViewMode] = useState<'recharts' | 'svg'>('recharts');
    const [noiseViewMode, setNoiseViewMode] = useState<'recharts' | 'svg'>('recharts');
    const [periodViewMode, setPeriodViewMode] = useState<'grid' | 'bar'>('bar'); // NEW: bar view by default
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [autoRefresh, setAutoRefresh] = useState(true);
    const [lastUpdate, setLastUpdate] = useState(new Date());
    const [pingTime, setPingTime] = useState<number | null>(null);

    // Noise Dashboard State
    const [selectedPeriod, setSelectedPeriod] = useState<Period>('L1');
    const [selectedDate, setSelectedDate] = useState<string>(new Date().toISOString().split('T')[0]);
    const [selectedThiDate, setSelectedThiDate] = useState<string>(new Date().toISOString().split('T')[0]);
    const [thiViewMode, setThiViewMode] = useState<'data' | 'chart'>('data');
    const [calculations, setCalculations] = useState<NoiseCalculation[]>([]);
    const [dataCount, setDataCount] = useState<Partial<Record<Period, number>>>({
        L1: 0, L2: 0, L3: 0, L4: 0, L5: 0, L6: 0, L7: 0, L8: 0,
    });
    const [loadingNoise, setLoadingNoise] = useState(false);

    // Modal state
    const [showDataModal, setShowDataModal] = useState(false);
    const [modalPeriod, setModalPeriod] = useState<Period>('L1');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'IoT Monitoring', href: '/iot' },
        { title: device.name, href: `/iot/devices/${device.id}` },
    ];

    const refresh = () => {
        setIsRefreshing(true);
        const startTime = performance.now();
        
        router.reload({
            only: ['device', 'chartData'],
            onFinish: () => {
                const endTime = performance.now();
                const responseTime = Math.round(endTime - startTime);
                setPingTime(responseTime);
                setIsRefreshing(false);
                setLastUpdate(new Date());
            },
        });
    };

    const fetchNoiseData = async () => {
        try {
            setLoadingNoise(true);

            // Fetch calculations
            const calcParams = new URLSearchParams({
                device_id: device.id.toString(),
                date: selectedDate,
            });
            const calcResponse = await fetch(`/api/v1/iot/noise-calculations?${calcParams}`);
            const calcResult = await calcResponse.json();
            if (calcResult.success) {
                setCalculations(calcResult.data);
            }

            // Fetch counts for all 8 periods
            const periods: Period[] = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];
            const counts: Partial<Record<Period, number>> = {};
            await Promise.all(
                periods.map(async (period) => {
                    try {
                        const params = new URLSearchParams({
                            device_id: device.id.toString(),
                            period,
                            date: selectedDate,
                        });
                        const response = await fetch(`/api/v1/iot/noise-data/realtime?${params}`);
                        const result = await response.json();
                        if (result.success) {
                            counts[period] = result.count;
                        } else {
                            counts[period] = 0;
                        }
                    } catch (e) {
                        counts[period] = 0;
                    }
                })
            );
            setDataCount(counts);

        } catch (error) {
            console.error('Failed to fetch noise data:', error);
        } finally {
            setLoadingNoise(false);
        }
    };

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(() => {
            refresh();
            if (activeTab === 'noise') {
                fetchNoiseData();
            }
        }, REFRESH_INTERVAL);
        return () => clearInterval(interval);
    }, [autoRefresh, activeTab, selectedDate]);

    useEffect(() => {
        if (activeTab === 'noise') {
            fetchNoiseData();
        }
    }, [activeTab, selectedDate]);

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
    const currentCalculation = calculations.find((c) => c.period === selectedPeriod);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${device.name} - IoT Monitoring`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{device.name}</h1>
                            <StatusBadge status={device.status} />
                            {pingTime !== null && (
                                <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ${
                                    pingTime < 200 
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                        : pingTime < 500 
                                        ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                }`}>
                                    {pingTime}ms
                                </span>
                            )}
                        </div>
                        <p className="text-muted-foreground">{device.location || 'No location set'}</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => {
                                    refresh();
                                    if (activeTab === 'noise') fetchNoiseData();
                                }}
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

                {/* Tabs Navigation */}
                <div className="border-b overflow-x-auto">
                    <div className="flex gap-2 md:gap-4 min-w-max">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`flex items-center gap-1.5 md:gap-2 border-b-2 px-2 md:px-4 py-2 text-xs md:text-sm font-medium transition-colors whitespace-nowrap ${activeTab === 'overview'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            <Activity className="h-3.5 w-3.5 md:h-4 md:w-4" />
                            <span className="hidden sm:inline">Overview</span>
                        </button>
                        <button
                            onClick={() => setActiveTab('noise')}
                            className={`flex items-center gap-1.5 md:gap-2 border-b-2 px-2 md:px-4 py-2 text-xs md:text-sm font-medium transition-colors whitespace-nowrap ${activeTab === 'noise'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            <BarChart3 className="h-3.5 w-3.5 md:h-4 md:w-4" />
                            <span className="hidden sm:inline">Noise</span>
                        </button>
                        {/* THI Tab - Hidden temporarily */}
                        {/* <button
                            onClick={() => setActiveTab('thi')}
                            className={`flex items-center gap-1.5 md:gap-2 border-b-2 px-2 md:px-4 py-2 text-xs md:text-sm font-medium transition-colors whitespace-nowrap ${activeTab === 'thi'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            <Gauge className="h-3.5 w-3.5 md:h-4 md:w-4" />
                            <span className="hidden sm:inline">THI</span>
                        </button> */}
                        <button
                            onClick={() => setActiveTab('daily')}
                            className={`flex items-center gap-1.5 md:gap-2 border-b-2 px-2 md:px-4 py-2 text-xs md:text-sm font-medium transition-colors whitespace-nowrap ${activeTab === 'daily'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            <FileText className="h-3.5 w-3.5 md:h-4 md:w-4" />
                            <span className="hidden sm:inline">Daily</span>
                        </button>
                        <button
                            onClick={() => setActiveTab('telegram')}
                            className={`flex items-center gap-1.5 md:gap-2 border-b-2 px-2 md:px-4 py-2 text-xs md:text-sm font-medium transition-colors whitespace-nowrap ${activeTab === 'telegram'
                                ? 'border-primary text-primary'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                        >
                            <MessageSquare className="h-3.5 w-3.5 md:h-4 md:w-4" />
                            <span className="hidden sm:inline">Telegram</span>
                        </button>
                    </div>
                </div>

                {/* Tab Content: Overview */}
                {activeTab === 'overview' && (
                    <div className="flex flex-col gap-6 animate-in fade-in duration-300">
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
                                unit=" dB(A)"
                                icon={Volume2}
                                color="text-purple-500"
                                min={stats?.noise.min}
                                max={stats?.noise.max}
                                avg={stats?.noise.avg}
                            />
                        </div>

                        {/* Sub-tabs for Chart Type */}
                        <div className="flex justify-end">
                            <div className="flex gap-1 md:gap-2 border rounded-lg p-1">
                                <button
                                    onClick={() => setOverviewViewMode('recharts')}
                                    className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                        overviewViewMode === 'recharts'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Interactive
                                </button>
                                <button
                                    onClick={() => setOverviewViewMode('svg')}
                                    className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                        overviewViewMode === 'svg'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Detailed
                                </button>
                            </div>
                        </div>

                        {overviewViewMode === 'recharts' ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle>24-Hour Trends</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <RealTimeTelemetryChart data={chartData} />
                                </CardContent>
                            </Card>
                        ) : (
                            <TelemetrySvgChart data={chartData} />
                        )}
                    </div>
                )}

                {/* Tab Content: Noise Analysis */}
                {activeTab === 'noise' && (
                    <div className="flex flex-col gap-6 animate-in fade-in duration-300">
                        <div className="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium">Date:</label>
                                <input
                                    type="date"
                                    className="rounded-md border p-1 text-sm"
                                    value={selectedDate}
                                    onChange={(e) => setSelectedDate(e.target.value)}
                                    max={new Date().toISOString().split('T')[0]}
                                />
                            </div>

                            <div className="flex gap-2">
                                {/* Period View Toggle */}
                                <div className="flex gap-1 border rounded-lg p-1">
                                    <button
                                        onClick={() => setPeriodViewMode('bar')}
                                        className={`px-3 py-1.5 text-xs font-medium rounded transition-colors flex items-center gap-1 ${
                                            periodViewMode === 'bar'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                        title="Bar View"
                                    >
                                        <LayoutList className="h-3 w-3" />
                                        Bar
                                    </button>
                                    <button
                                        onClick={() => setPeriodViewMode('grid')}
                                        className={`px-3 py-1.5 text-xs font-medium rounded transition-colors flex items-center gap-1 ${
                                            periodViewMode === 'grid'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                        title="Grid View"
                                    >
                                        <LayoutGrid className="h-3 w-3" />
                                        Grid
                                    </button>
                                </div>

                                {/* Chart Type Toggle */}
                                <div className="flex gap-1 border rounded-lg p-1">
                                    <button
                                        onClick={() => setNoiseViewMode('recharts')}
                                        className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                            noiseViewMode === 'recharts'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        Interactive
                                    </button>
                                    <button
                                        onClick={() => setNoiseViewMode('svg')}
                                        className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                            noiseViewMode === 'svg'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        Detailed
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-6">
                            {/* Conditional Period View */}
                            {periodViewMode === 'bar' ? (
                                <PeriodBarView
                                    calculations={(() => {
                                        const allPeriods: Period[] = ['L1', 'L2', 'L3', 'L4', 'L5', 'L6', 'L7', 'L8'];
                                        const result: Partial<Record<Period, any>> = {};
                                        
                                        // First, populate from calculations
                                        calculations.forEach((calc) => {
                                            result[calc.period as Period] = {
                                                leq_value: calc.leq_value,
                                                average_value: calc.average_value,
                                                data_count: dataCount[calc.period as Period] || calc.data_count || 0,
                                                min_value: calc.min_value,
                                                max_value: calc.max_value,
                                            };
                                        });
                                        
                                        // Then, add periods that have dataCount but no calculations yet
                                        allPeriods.forEach((period) => {
                                            if (!result[period] && dataCount[period] && dataCount[period]! > 0) {
                                                result[period] = {
                                                    leq_value: 0,
                                                    average_value: 0,
                                                    data_count: dataCount[period]!,
                                                    min_value: 0,
                                                    max_value: 0,
                                                };
                                            }
                                        });
                                        
                                        return result;
                                    })()}
                                    selectedPeriod={selectedPeriod as Period}
                                    onPeriodClick={(period) => setSelectedPeriod(period as any)}
                                    onPeriodDoubleClick={(period) => {
                                        setModalPeriod(period);
                                        setShowDataModal(true);
                                    }}
                                />
                            ) : (
                                <PeriodSelector
                                    selectedPeriod={selectedPeriod}
                                    onChange={setSelectedPeriod}
                                    dataCount={dataCount}
                                    onPeriodDoubleClick={(period) => {
                                        setModalPeriod(period);
                                        setShowDataModal(true);
                                    }}
                                />
                            )}

                            <NoiseStatisticsPanel calculation={currentCalculation || null} loading={loadingNoise} />

                            {/* THI Categories */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm">THI Categories</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {[
                                            { range: '< 27', category: 'Nyaman', color: 'text-green-700 dark:text-green-400', bgColor: 'bg-green-100 dark:bg-green-900/30', description: 'Kondisi nyaman' },
                                            { range: '27 - 29', category: 'Cukup Nyaman', color: 'text-yellow-700 dark:text-yellow-400', bgColor: 'bg-yellow-100 dark:bg-yellow-900/30', description: 'Kondisi cukup nyaman' },
                                            { range: '> 29', category: 'Tidak Nyaman', color: 'text-red-700 dark:text-red-400', bgColor: 'bg-red-100 dark:bg-red-900/30', description: 'Kondisi tidak nyaman' },
                                        ].map((item) => (
                                            <div key={item.category} className={`p-3 rounded-lg ${item.bgColor}`}>
                                                <div className={`font-semibold text-sm ${item.color}`}>{item.category}</div>
                                                <div className="text-xs text-muted-foreground mt-1">THI {item.range}</div>
                                                <div className="text-xs mt-1">{item.description}</div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {noiseViewMode === 'recharts' ? (
                                <RealTimeNoiseChart
                                    deviceId={device.id}
                                    period={selectedPeriod}
                                    date={selectedDate}
                                    autoRefresh={selectedDate === new Date().toISOString().split('T')[0] && autoRefresh}
                                />
                            ) : (
                                <NoiseSvgChart
                                    deviceId={device.id}
                                    period={selectedPeriod}
                                    date={selectedDate}
                                    autoRefresh={selectedDate === new Date().toISOString().split('T')[0] && autoRefresh}
                                />
                            )}
                        </div>
                    </div>
                )}

                {/* Tab Content: THI */}
                {activeTab === 'thi' && (
                    <div className="flex flex-col gap-6 animate-in fade-in duration-300">
                        <div className="flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium">Date:</label>
                                <input
                                    type="date"
                                    className="rounded-md border p-1 text-sm"
                                    value={selectedThiDate}
                                    onChange={(e) => setSelectedThiDate(e.target.value)}
                                    max={new Date().toISOString().split('T')[0]}
                                />
                            </div>

                            {/* Sub-tabs for Data/Chart */}
                            <div className="flex gap-1 md:gap-2 border rounded-lg p-1">
                                <button
                                    onClick={() => setThiViewMode('data')}
                                    className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                        thiViewMode === 'data'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Data
                                </button>
                                <button
                                    onClick={() => setThiViewMode('chart')}
                                    className={`px-2 md:px-4 py-1.5 text-xs md:text-sm font-medium rounded transition-colors whitespace-nowrap ${
                                        thiViewMode === 'chart'
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground hover:text-foreground'
                                    }`}
                                >
                                    Grafik
                                </button>
                            </div>
                        </div>

                        <ThiChart 
                            deviceId={device.id} 
                            date={selectedThiDate}
                            autoRefresh={selectedThiDate === new Date().toISOString().split('T')[0] && autoRefresh}
                            viewMode={thiViewMode}
                        />
                    </div>
                )}

                {/* Tab Content: Daily Report */}
                {activeTab === 'daily' && (
                    <div className="flex flex-col gap-6 animate-in fade-in duration-300">
                        <div className="flex items-center gap-2">
                            <label className="text-sm font-medium">Date:</label>
                            <input
                                type="date"
                                className="rounded-md border p-1 text-sm"
                                value={selectedDate}
                                onChange={(e) => setSelectedDate(e.target.value)}
                                max={new Date().toISOString().split('T')[0]}
                            />
                        </div>

                        <DailyReportPanel 
                            deviceId={device.id} 
                            date={selectedDate}
                            loading={loadingNoise}
                        />
                    </div>
                )}

                {/* Tab Content: Telegram */}
                {activeTab === 'telegram' && (
                    <div className="flex flex-col gap-6 animate-in fade-in duration-300">
                        <DeviceTelegramSettings device={device} />
                    </div>
                )}

                <p className="text-center text-sm text-muted-foreground">
                    Last updated: {lastUpdate.toLocaleTimeString()}
                    {autoRefresh && ' • Auto-refresh enabled'}
                </p>
            </div>

            <NoiseDataModal
                open={showDataModal}
                onClose={() => setShowDataModal(false)}
                deviceId={device.id}
                deviceName={device.name}
                period={modalPeriod}
                date={selectedDate}
            />
        </AppLayout>
    );
}
