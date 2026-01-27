import React, { useState, useEffect } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { format, parseISO } from 'date-fns';
import { Loader2, AlertCircle, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';

interface NoiseDataPoint {
    noise_level: number;
    temperature: number;
    humidity: number;
    measured_at: string;
}

interface RealTimeNoiseChartProps {
    deviceId: number;
    period: 'L1' | 'L2' | 'L3' | 'L4';
    date?: string;
    autoRefresh?: boolean;
}

export default function RealTimeNoiseChart({
    deviceId,
    period,
    date,
    autoRefresh = true
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
                setData(result.data);
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
            dB: point.noise_level,
            temp: point.temperature,
            humidity: point.humidity,
        };
    });

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
                            {data.length} / 120 data points
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
                            <ActivityIcon className="h-8 w-8 opacity-50" />
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
                                label={{ value: 'Noise (dB)', angle: -90, position: 'insideLeft', fill: "hsl(var(--muted-foreground))" }}
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
                            <Tooltip
                                contentStyle={{
                                    backgroundColor: "hsl(var(--popover))",
                                    borderColor: "hsl(var(--border))",
                                    borderRadius: "var(--radius)",
                                    color: "hsl(var(--popover-foreground))",
                                    boxShadow: "0 4px 6px -1px rgb(0 0 0 / 0.1)",
                                }}
                                itemStyle={{ color: "hsl(var(--foreground))" }}
                                labelStyle={{ color: "hsl(var(--muted-foreground))", marginBottom: "0.25rem" }}
                            />
                            <Legend wrapperStyle={{ paddingTop: "1rem" }} />
                            <Line
                                yAxisId="left"
                                type="monotone"
                                dataKey="dB"
                                stroke="#8b5cf6" // Purple
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 4, strokeWidth: 0 }}
                                name="Noise Level (dB)"
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

function ActivityIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg
            {...props}
            xmlns="http://www.w3.org/2000/svg"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
        </svg>
    )
}
