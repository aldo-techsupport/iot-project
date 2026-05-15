import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    XCircle,
    Clock,
    Database,
    TrendingUp,
    Calendar,
    RefreshCw
} from 'lucide-react';
import { useState } from 'react';
import { formatDistanceToNow } from 'date-fns';

interface Calculation {
    leq_value: number;
    data_count: number;
    min_value: number;
    max_value: number;
    is_valid: boolean;
    invalid_reason?: string | null;
    created_at: string;
}

interface TimeoutLog {
    expected_at: string;
    period: string;
    timeout_seconds: number;
    created_at: string;
}

interface DeviceMonitoring {
    id: number;
    name: string;
    slug: string;
    location: string;
    description: string;
    is_active: boolean;
    status: 'online' | 'warning' | 'offline';
    last_seen_at: string | null;
    last_seen_minutes: number | null;
    latest_telemetry: {
        temperature: number;
        humidity: number;
        noise_db: number;
        measured_at: string;
    } | null;
    telemetry_count_today: number;
    calculations: {
        L1: Calculation | null;
        L2: Calculation | null;
        L3: Calculation | null;
        L4: Calculation | null;
    };
    daily_summary: {
        ls_value: number | null;
        twa_value: number | null;
        dnd_value: number | null;
        is_valid: boolean;
        invalid_reason: string | null;
        invalid_periods: string[] | null;
    } | null;
    timeout_logs: TimeoutLog[];
}

interface MonitoringProps {
    devices: DeviceMonitoring[];
    selectedDate: string;
    currentDate: string;
}

export default function Monitoring({ devices, selectedDate, currentDate }: MonitoringProps) {
    const [date, setDate] = useState(selectedDate);
    const [selectedPeriods, setSelectedPeriods] = useState<Record<number, string | null>>({});

    const handleDateChange = (newDate: string) => {
        setDate(newDate);
        router.get('/iot', { date: newDate }, { preserveState: true });
    };

    const handleRefresh = () => {
        router.reload();
    };

    const handlePeriodClick = (deviceId: number, period: string | null) => {
        setSelectedPeriods(prev => ({
            ...prev,
            [deviceId]: prev[deviceId] === period ? null : period
        }));
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'online':
                return 'bg-green-500';
            case 'warning':
                return 'bg-yellow-500';
            case 'offline':
                return 'bg-red-500';
            default:
                return 'bg-gray-500';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'online':
                return <CheckCircle2 className="h-4 w-4" />;
            case 'warning':
                return <AlertTriangle className="h-4 w-4" />;
            case 'offline':
                return <XCircle className="h-4 w-4" />;
            default:
                return <Activity className="h-4 w-4" />;
        }
    };

    const totalDevices = devices.length;
    const onlineDevices = devices.filter(d => d.status === 'online').length;
    const warningDevices = devices.filter(d => d.status === 'warning').length;
    const offlineDevices = devices.filter(d => d.status === 'offline').length;
    const totalCalculations = devices.reduce((sum, d) => {
        return sum + Object.values(d.calculations).filter(c => c !== null).length;
    }, 0);
    const totalTimeouts = devices.reduce((sum, d) => sum + d.timeout_logs.length, 0);

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard' },
            ]}
        >
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">IoT Dashboard</h1>
                        <p className="text-muted-foreground">
                            Monitor all devices, calculations, and system health
                        </p>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <Label htmlFor="date" className="whitespace-nowrap">
                                <Calendar className="inline h-4 w-4 mr-1" />
                                Date:
                            </Label>
                            <Input
                                id="date"
                                type="date"
                                value={date}
                                max={currentDate}
                                onChange={(e) => handleDateChange(e.target.value)}
                                className="w-40"
                            />
                        </div>
                        <Button onClick={handleRefresh} variant="outline" size="sm">
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                    </div>
                </div>

                {/* Summary Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Devices</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalDevices}</div>
                            <div className="flex gap-2 mt-2 text-xs text-muted-foreground">
                                <span className="text-green-600">● {onlineDevices} online</span>
                                <span className="text-yellow-600">● {warningDevices} warning</span>
                                <span className="text-red-600">● {offlineDevices} offline</span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Calculations</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalCalculations}</div>
                            <p className="text-xs text-muted-foreground">
                                Out of {totalDevices * 4} possible (L1-L4)
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Data Points</CardTitle>
                            <Database className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {devices.reduce((sum, d) => sum + d.telemetry_count_today, 0).toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Telemetry records today
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Timeouts</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalTimeouts}</div>
                            <p className="text-xs text-muted-foreground">
                                Failed data transmissions
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Device Cards */}
                <div className="grid gap-6 md:grid-cols-1 lg:grid-cols-2">
                    {devices.map((device) => (
                        <Card key={device.id} className="overflow-hidden">
                            <CardHeader className="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950 dark:to-indigo-950">
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <CardTitle className="flex items-center gap-2">
                                            <div className={`h-3 w-3 rounded-full ${getStatusColor(device.status)} animate-pulse`} />
                                            {device.name}
                                        </CardTitle>
                                        <CardDescription>
                                            {device.location}
                                        </CardDescription>
                                    </div>
                                    <Badge variant={device.status === 'online' ? 'default' : 'secondary'}>
                                        {getStatusIcon(device.status)}
                                        <span className="ml-1 capitalize">{device.status}</span>
                                    </Badge>
                                </div>

                                {device.last_seen_at && (
                                    <p className="text-xs text-muted-foreground">
                                        Last seen: {formatDistanceToNow(new Date(device.last_seen_at), { addSuffix: true })}
                                    </p>
                                )}
                            </CardHeader>


                            <CardContent className="pt-6 space-y-4">
                                {/* Data Display - Telemetry or Selected Period Calculation */}
                                {(() => {
                                    const selectedPeriod = selectedPeriods[device.id];
                                    
                                    // If a period is selected, show its calculation details
                                    if (selectedPeriod) {
                                        const calc = device.calculations[selectedPeriod as 'L1' | 'L2' | 'L3' | 'L4'];
                                        if (calc) {
                                            const getSafetyStatus = (leq: number) => {
                                                if (leq < 80) return { status: 'SAFE', color: 'text-green-600', bgColor: 'bg-green-50 dark:bg-green-950', borderColor: 'border-green-200 dark:border-green-800' };
                                                if (leq < 85) return { status: 'WARNING', color: 'text-yellow-600', bgColor: 'bg-yellow-50 dark:bg-yellow-950', borderColor: 'border-yellow-200 dark:border-yellow-800' };
                                                return { status: 'DANGER', color: 'text-red-600', bgColor: 'bg-red-50 dark:bg-red-950', borderColor: 'border-red-200 dark:border-red-800' };
                                            };
                                            const safety = getSafetyStatus(calc.leq_value);
                                            
                                            return (
                                                <div className={`p-4 rounded-lg border ${safety.bgColor} ${safety.borderColor}`}>
                                                    <div className="grid grid-cols-3 gap-4">
                                                        <div className="text-center">
                                                            <p className="text-xs text-muted-foreground mb-1">Avg Leq</p>
                                                            <p className={`text-2xl font-semibold ${safety.color}`}>
                                                                {calc.leq_value.toFixed(2)}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">dB(A)</p>
                                                            <p className="text-xs mt-1">{calc.data_count} periods</p>
                                                        </div>
                                                        <div className="text-center">
                                                            <p className="text-xs text-muted-foreground mb-1">Min</p>
                                                            <p className="text-2xl font-semibold text-green-600">
                                                                {calc.min_value.toFixed(1)}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">dB(A)</p>
                                                            <p className="text-xs mt-1">Lowest recorded</p>
                                                        </div>
                                                        <div className="text-center">
                                                            <p className="text-xs text-muted-foreground mb-1">Max</p>
                                                            <p className="text-2xl font-semibold text-red-600">
                                                                {calc.max_value.toFixed(1)}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">dB(A)</p>
                                                            <p className="text-xs mt-1">Highest recorded</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        }
                                    }
                                    
                                    // Default: Show latest telemetry
                                    if (device.latest_telemetry) {
                                        return (
                                            <div className="grid grid-cols-3 gap-4 p-4 bg-muted/50 rounded-lg">
                                                <div>
                                                    <p className="text-xs text-muted-foreground">Noise</p>
                                                    <p className="text-lg font-semibold">{device.latest_telemetry.noise_db} dB(A)</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted-foreground">Temp</p>
                                                    <p className="text-lg font-semibold">{device.latest_telemetry.temperature}°C</p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted-foreground">Humidity</p>
                                                    <p className="text-lg font-semibold">{device.latest_telemetry.humidity}%</p>
                                                </div>
                                            </div>
                                        );
                                    }
                                    
                                    return null;
                                })()}

                                {/* Calculations Grid */}
                                <div>
                                    <h4 className="text-sm font-semibold mb-3">Period Calculations</h4>
                                    <div className="grid grid-cols-2 gap-3">
                                        {(['L1', 'L2', 'L3', 'L4'] as const).map((period) => {
                                            const calc = device.calculations[period];

                                            // Determine safety status based on Leq value
                                            const getSafetyStatus = (leq: number) => {
                                                if (leq < 80) return { status: 'SAFE', color: 'bg-green-600', textColor: 'text-green-700 dark:text-green-300' };
                                                if (leq < 85) return { status: 'WARNING', color: 'bg-yellow-600', textColor: 'text-yellow-700 dark:text-yellow-300' };
                                                return { status: 'DANGER', color: 'bg-red-600', textColor: 'text-red-700 dark:text-red-300' };
                                            };

                                            const safety = calc ? getSafetyStatus(calc.leq_value) : null;

                                            return (
                                                <div
                                                    key={period}
                                                    className={`p-3 border rounded-lg space-y-1 transition-all ${calc && calc.is_valid ? 'hover:shadow-md cursor-pointer hover:border-primary' : ''
                                                        } ${selectedPeriods[device.id] === period ? 'border-primary bg-primary/5' : ''
                                                        } ${calc && !calc.is_valid ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950/30' : ''
                                                        }`}
                                                    onClick={() => {
                                                        if (calc && calc.is_valid) {
                                                            handlePeriodClick(device.id, period);
                                                        }
                                                    }}
                                                >
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm font-medium">{period}</span>
                                                        {calc ? (
                                                            calc.is_valid ? (
                                                                <Badge variant="default" className={safety?.color}>
                                                                    {calc.leq_value.toFixed(2)} dB(A)
                                                                </Badge>
                                                            ) : (
                                                                <Badge variant="destructive" className="text-xs">
                                                                    ⚠ INVALID
                                                                </Badge>
                                                            )
                                                        ) : (
                                                            <Badge variant="outline" className="text-gray-500">No Data</Badge>
                                                        )}
                                                    </div>
                                                    {calc && calc.is_valid && (
                                                        <div className="text-xs space-y-0.5">
                                                            <div className={`font-semibold ${safety?.textColor}`}>
                                                                {safety?.status}
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                Data: {calc.data_count} points
                                                            </div>
                                                            <div className="text-muted-foreground">
                                                                Range: {calc.min_value} - {calc.max_value} dB(A)
                                                            </div>
                                                        </div>
                                                    )}
                                                    {calc && !calc.is_valid && (
                                                        <div className="text-xs space-y-0.5">
                                                            <div className="font-semibold text-red-600 dark:text-red-400">
                                                                Data tidak lengkap
                                                            </div>
                                                            <div className="text-red-500 dark:text-red-400">
                                                                {calc.data_count}/60 points
                                                            </div>
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {/* Daily Summary */}
                                {device.daily_summary && (
                                    device.daily_summary.is_valid ? (
                                        <div className="p-4 bg-green-50 dark:bg-green-950 rounded-lg border border-green-200 dark:border-green-800">
                                            <h4 className="text-sm font-semibold mb-2 text-green-900 dark:text-green-100">
                                                Daily Summary
                                            </h4>
                                            <div className="grid grid-cols-3 gap-4 text-sm">
                                                <div>
                                                    <p className="text-xs text-green-700 dark:text-green-300">Ls</p>
                                                    <p className="font-semibold text-green-900 dark:text-green-100">
                                                        {device.daily_summary.ls_value?.toFixed(2)} dB(A)
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-green-700 dark:text-green-300">TWA</p>
                                                    <p className="font-semibold text-green-900 dark:text-green-100">
                                                        {device.daily_summary.twa_value?.toFixed(2)} dB(A)
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-green-700 dark:text-green-300">DND</p>
                                                    <p className="font-semibold text-green-900 dark:text-green-100">
                                                        {device.daily_summary.dnd_value?.toFixed(0)}%
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="p-4 bg-red-50 dark:bg-red-950 rounded-lg border border-red-300 dark:border-red-700">
                                            <h4 className="text-sm font-semibold mb-1 text-red-800 dark:text-red-200 flex items-center gap-2">
                                                <span>⚠</span> INVALID DATA — Daily Report
                                            </h4>
                                            <p className="text-xs text-red-600 dark:text-red-400">
                                                Periode tidak lengkap:{' '}
                                                <span className="font-bold">
                                                    {device.daily_summary.invalid_periods?.join(', ')}
                                                </span>
                                            </p>
                                            <p className="text-xs text-red-500 dark:text-red-400 mt-1">
                                                Daily report tidak dapat dihitung
                                            </p>
                                        </div>
                                    )
                                )}

                                {/* Timeout Logs */}
                                {device.timeout_logs.length > 0 && (
                                    <div className="space-y-2">
                                        <h4 className="text-sm font-semibold flex items-center gap-2">
                                            <AlertTriangle className="h-4 w-4 text-yellow-600" />
                                            Timeout Logs ({device.timeout_logs.length})
                                        </h4>
                                        <div className="space-y-1 max-h-32 overflow-y-auto">
                                            {device.timeout_logs.slice(0, 5).map((log, idx) => (
                                                <div key={idx} className="text-xs p-2 bg-yellow-50 dark:bg-yellow-950 rounded border border-yellow-200 dark:border-yellow-800">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">{log.period}</span>
                                                        <span className="text-muted-foreground">
                                                            {new Date(log.expected_at).toLocaleTimeString()}
                                                        </span>
                                                    </div>
                                                    <div className="text-muted-foreground">
                                                        Timeout: {log.timeout_seconds}s
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Stats */}
                                <div className="pt-4 border-t flex items-center justify-between text-sm text-muted-foreground">
                                    <span>Telemetry today: {device.telemetry_count_today.toLocaleString()}</span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => router.visit(`/iot/devices/${device.id}`)}
                                    >
                                        View Details →
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {devices.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Activity className="h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No Devices Found</h3>
                            <p className="text-sm text-muted-foreground">
                                Add devices to start monitoring your IoT system.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
