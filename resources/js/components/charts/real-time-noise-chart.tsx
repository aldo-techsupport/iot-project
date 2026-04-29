import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { format, parseISO } from 'date-fns';
import { Loader2, AlertCircle, RefreshCw, Activity } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Period } from '@/types/period';

interface NoiseDataPoint {
    noise_level: number;
    temperature: number;
    humidity: number;
    measured_at: string;
    is_filled: boolean;
    fill_method: 'actual' | 'copied' | 'zero';
}

interface RealTimeNoiseChartProps {
    deviceId: number;
    period: Period;
    date?: string;
    autoRefresh?: boolean;
    onStatusChange?: (isOffline: boolean) => void;
}

export default function RealTimeNoiseChart({
    deviceId,
    period,
    date,
    autoRefresh = true,
    onStatusChange
}: RealTimeNoiseChartProps) {
    const [data, setData] = useState<NoiseDataPoint[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchData = async () => {
        try {
            // Don't set loading on auto-refresh to avoid flickering
            if (data.length === 0) setLoading(true);
            setError(null);

            const params = new URLSearchParams({
                device_id: deviceId.toString(),
                period,
                ...(date && { date }),
            });

            const response = await fetch(`/api/v1/iot/noise-data/realtime?${params}`);
            const result = await response.json();

            if (result.success) {
                const newData = result.data;
                setData(newData);

                // Detection logic for offline status
                // If last 12 points (1 minute) are all zero-filled, consider offline
                if (newData.length > 0 && onStatusChange) {
                    const recentPoints = newData.slice(-12); // Last 12 points
                    const isOffline = recentPoints.length >= 12 && recentPoints.every((p: any) => p.fill_method === 'zero');
                    onStatusChange(isOffline);
                }
            } else {
                setError('Failed to load data');
            }
        } catch (err) {
            setError('Error fetching data');
            console.error(err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();

        if (autoRefresh) {
            const interval = setInterval(fetchData, 5000); // Refresh every 5 seconds
            return () => clearInterval(interval);
        }
    }, [deviceId, period, date, autoRefresh]);

    const chartData = data.map((point) => {
        // Handle measured_at format safely
        let dateObj;
        try {
            dateObj = new Date(point.measured_at);
            if (isNaN(dateObj.getTime())) {
                // Try parseISO if standard constructor fails (e.g., for some string formats)
                dateObj = parseISO(point.measured_at);
            }
        } catch (e) {
            dateObj = new Date();
        }

        return {
            time: format(dateObj, 'HH:mm:ss'),
            'dB(A)': point.noise_level,
            temp: point.temperature,
            humidity: point.humidity,
            isFilled: point.is_filled,
            fillMethod: point.fill_method,
        };
    });

    const CustomDot = (props: any) => {
        const { cx, cy, payload } = props;
        if (!payload.isFilled) return null; // Don't show dots for actual data to keep chart clean

        const color = payload.fillMethod === 'zero' ? '#ef4444' : '#eab308'; // Red for zero, Yellow for copied

        return (
            <circle cx={cx} cy={cy} r={4} fill={color} stroke="white" strokeWidth={1} />
        );
    };

    const CustomTooltip = ({ active, payload, label }: any) => {
        if (!active || !payload || !payload.length) return null;

        const data = payload[0].payload;

        return (
            <div className="rounded-lg border bg-popover p-2 shadow-sm">
                <div className="grid grid-cols-2 gap-2">
                    <div className="flex flex-col">
                        <span className="text-[0.70rem] uppercase text-muted-foreground">
                            Time
                        </span>
                        <span className="font-bold text-muted-foreground">
                            {label}
                        </span>
                    </div>
                    {data.isFilled && (
                        <div className="flex flex-col">
                            <span className="text-[0.70rem] uppercase text-muted-foreground">
                                Status
                            </span>
                            <span className={cn(
                                "font-bold",
                                data.fillMethod === 'zero' ? "text-red-500" : "text-yellow-500"
                            )}>
                                {data.fillMethod === 'zero' ? 'Filled (Zero)' : 'Failed To Fetch Data'}
                            </span>
                        </div>
                    )}
                </div>
                <div className="mt-2 grid grid-cols-3 gap-2 border-t pt-2">
                    {payload.map((entry: any, index: number) => (
                        <div key={index} className="flex flex-col">
                            <span className="text-[0.70rem] uppercase text-muted-foreground">
                                {entry.name}
                            </span>
                            <span className="font-bold" style={{ color: entry.color }}>
                                {entry.value}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <div className="rounded-xl border bg-card text-card-foreground shadow-sm h-full flex flex-col">
            <div className="flex flex-col space-y-1.5 p-6 pb-2">
                <div className="flex flex-row items-center justify-between">
                    <h3 className="font-semibold leading-none tracking-tight">
                        Real-Time Noise Level - {period}
                    </h3>
                    <div className="flex items-center gap-2">
                        <span className={cn(
                            "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
                            data.length >= 120
                                ? "border-transparent bg-green-500 text-white shadow hover:bg-green-600"
                                : "border-transparent bg-yellow-500 text-white shadow hover:bg-yellow-600"
                        )}>
                            {data.length} / 720 data points
                        </span>
                        {autoRefresh && (
                            <span className="flex items-center text-xs text-muted-foreground animate-pulse">
                                <RefreshCw className="mr-1 h-3 w-3" />
                                Live
                            </span>
                        )}
                    </div>
                </div>
            </div>

            <div className="p-6 pt-0 flex-1 min-h-[400px]">
                {loading && data.length === 0 && (
                    <div className="flex h-full items-center justify-center py-10">
                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                    </div>
                )}

                {error && (
                    <div className="flex h-full items-center justify-center py-10 text-destructive">
                        <AlertCircle className="mr-2 h-5 w-5" />
                        <span>{error}</span>
                    </div>
                )}

                {!loading && data.length === 0 && !error && (
                    <div className="flex h-full flex-col items-center justify-center py-10 text-muted-foreground">
                        <div className="rounded-full bg-muted p-4 mb-3">
                            <Activity className="h-8 w-8 opacity-50" />
                        </div>
                        <p>No data available for {period}</p>
                        <p className="text-xs mt-1">Measurements appear here automatically</p>
                    </div>
                )}

                {data.length > 0 && (
                    <ResponsiveContainer width="100%" height={400}>
                        <LineChart data={chartData} margin={{ top: 20, right: 30, left: 10, bottom: 20 }}>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                            <XAxis
                                dataKey="time"
                                tick={{ fontSize: 12, fill: "hsl(var(--muted-foreground))" }}
                                tickLine={false}
                                axisLine={false}
                                interval="preserveStartEnd"
                                minTickGap={30}
                            />
                            <YAxis
                                yAxisId="left"
                                label={{ value: 'Noise (dB(A))', angle: -90, position: 'insideLeft', fill: "hsl(var(--muted-foreground))" }}
                                tick={{ fontSize: 12, fill: "hsl(var(--muted-foreground))" }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <YAxis
                                yAxisId="right"
                                orientation="right"
                                label={{ value: 'Temp (°C) / Hum (%)', angle: 90, position: 'insideRight', fill: "hsl(var(--muted-foreground))" }}
                                tick={{ fontSize: 12, fill: "hsl(var(--muted-foreground))" }}
                                tickLine={false}
                                axisLine={false}
                            />
                            <Tooltip content={<CustomTooltip />} />
                            <Legend wrapperStyle={{ paddingTop: "1rem" }} />
                            <Line
                                yAxisId="left"
                                type="monotone"
                                dataKey="dB(A)"
                                stroke="#8b5cf6" // Purple
                                strokeWidth={2}
                                dot={<CustomDot />}
                                activeDot={{ r: 6, strokeWidth: 0 }}
                                name="Noise Level (dB(A))"
                                animationDuration={1000}
                            />
                            <Line
                                yAxisId="right"
                                type="monotone"
                                dataKey="temp"
                                stroke="#f97316" // Orange
                                strokeWidth={1.5}
                                dot={false}
                                name="Temperature (°C)"
                                animationDuration={1000}
                            />
                            <Line
                                yAxisId="right"
                                type="monotone"
                                dataKey="humidity"
                                stroke="#3b82f6" // Blue
                                strokeWidth={1.5}
                                dot={false}
                                name="Humidity (%)"
                                animationDuration={1000}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                )}
            </div>
        </div>
    );
}
