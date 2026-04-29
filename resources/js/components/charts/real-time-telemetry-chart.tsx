import React, { useState, useMemo } from 'react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import { format } from 'date-fns';
import { Activity } from 'lucide-react';
import { cn } from '@/lib/utils';
import { type ChartDataPoint } from '@/types/iot';

interface RealTimeTelemetryChartProps {
    data: ChartDataPoint[];
}

type TimeRange = 10 | 30 | 60 | 360 | 720 | 1440;

const TIME_RANGE_OPTIONS: { value: TimeRange; label: string }[] = [
    { value: 10, label: '10 Min' },
    { value: 30, label: '30 Min' },
    { value: 60, label: '1 Hour' },
    { value: 360, label: '6 Hours' },
    { value: 720, label: '12 Hours' },
    { value: 1440, label: '24 Hours' },
];

export default function RealTimeTelemetryChart({ data }: RealTimeTelemetryChartProps) {
    const [selectedRange, setSelectedRange] = useState<TimeRange>(1440);

    const filteredData = useMemo(() => {
        if (data.length === 0) return [];

        // Calculate how many minutes ago from now
        const now = new Date();
        const cutoffTime = new Date(now.getTime() - selectedRange * 60 * 1000);

        // Filter data based on selected time range
        return data.filter((point) => {
            const pointTime = new Date(point.measured_at);
            return pointTime >= cutoffTime;
        });
    }, [data, selectedRange]);

    const chartData = useMemo(() => {
        return filteredData.map((point) => {
            const dateObj = new Date(point.measured_at);
            return {
                time: format(dateObj, 'HH:mm'),
                fullTime: format(dateObj, 'HH:mm:ss'),
                temperature: point.temperature,
                humidity: point.humidity,
                noise: point.noise_db,
            };
        });
    }, [filteredData]);

    const CustomTooltip = ({ active, payload, label }: any) => {
        if (!active || !payload || !payload.length) return null;

        return (
            <div className="rounded-lg border bg-popover p-3 shadow-sm">
                <div className="mb-2 border-b pb-2">
                    <span className="text-sm font-medium text-muted-foreground">
                        {payload[0]?.payload?.fullTime || label}
                    </span>
                </div>
                <div className="grid gap-2">
                    {payload.map((entry: any, index: number) => (
                        <div key={index} className="flex items-center justify-between gap-4">
                            <span className="text-xs text-muted-foreground">
                                {entry.name}
                            </span>
                            <span className="font-bold text-sm" style={{ color: entry.color }}>
                                {entry.value.toFixed(1)}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        );
    };

    return (
        <div className="rounded-xl border bg-card text-card-foreground shadow-sm h-full flex flex-col">
            <div className="flex flex-col space-y-1.5 p-6 pb-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <h3 className="font-semibold leading-none tracking-tight">
                        Realtime Trends
                    </h3>
                    <div className="flex flex-wrap gap-2">
                        {TIME_RANGE_OPTIONS.map((option) => (
                            <button
                                key={option.value}
                                onClick={() => setSelectedRange(option.value)}
                                className={cn(
                                    "px-3 py-1.5 text-xs font-medium rounded-md transition-colors",
                                    selectedRange === option.value
                                        ? "bg-primary text-primary-foreground shadow"
                                        : "bg-muted text-muted-foreground hover:bg-muted/80"
                                )}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span className={cn(
                        "inline-flex items-center rounded-full border px-2.5 py-0.5 font-semibold transition-colors",
                        filteredData.length > 0
                            ? "border-transparent bg-green-500 text-white"
                            : "border-transparent bg-gray-500 text-white"
                    )}>
                        {filteredData.length} data points
                    </span>
                    <span>•</span>
                    <span>Last {selectedRange < 60 ? `${selectedRange} minutes` : selectedRange === 60 ? '1 hour' : `${selectedRange / 60} hours`}</span>
                </div>
            </div>

            <div className="p-6 pt-0 flex-1 min-h-[400px]">
                {chartData.length === 0 ? (
                    <div className="flex h-full flex-col items-center justify-center py-10 text-muted-foreground">
                        <div className="rounded-full bg-muted p-4 mb-3">
                            <Activity className="h-8 w-8 opacity-50" />
                        </div>
                        <p>No data available for selected time range</p>
                        <p className="text-xs mt-1">Try selecting a longer time range</p>
                    </div>
                ) : (
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
                                dataKey="noise"
                                stroke="#a855f7"
                                strokeWidth={2}
                                dot={false}
                                activeDot={{ r: 6, strokeWidth: 0 }}
                                name="Noise Level (dB(A))"
                                animationDuration={300}
                            />
                            <Line
                                yAxisId="right"
                                type="monotone"
                                dataKey="temperature"
                                stroke="#f97316"
                                strokeWidth={1.5}
                                dot={false}
                                name="Temperature (°C)"
                                animationDuration={300}
                            />
                            <Line
                                yAxisId="right"
                                type="monotone"
                                dataKey="humidity"
                                stroke="#3b82f6"
                                strokeWidth={1.5}
                                dot={false}
                                name="Humidity (%)"
                                animationDuration={300}
                            />
                        </LineChart>
                    </ResponsiveContainer>
                )}
            </div>
        </div>
    );
}
