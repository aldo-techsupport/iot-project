import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useEffect, useState } from 'react';

interface ThiDataPoint {
    hour: number;
    time: string;
    temperature: number;
    humidity: number;
    thi: number;
    intervals_count: number;
    data_count: number;
}

interface ThiChartProps {
    deviceId: number;
    date: string;
    autoRefresh?: boolean;
    viewMode?: 'data' | 'chart';
}

function getThiCategory(thi: number): { category: string; color: string; bgColor: string; description: string } {
    if (thi > 29) {
        return {
            category: 'Tidak Nyaman',
            color: 'text-red-700 dark:text-red-400',
            bgColor: 'bg-red-100 dark:bg-red-900/30',
            description: 'Kondisi tidak nyaman'
        };
    } else if (thi >= 27) {
        return {
            category: 'Cukup Nyaman',
            color: 'text-yellow-700 dark:text-yellow-400',
            bgColor: 'bg-yellow-100 dark:bg-yellow-900/30',
            description: 'Kondisi cukup nyaman'
        };
    } else {
        return {
            category: 'Nyaman',
            color: 'text-green-700 dark:text-green-400',
            bgColor: 'bg-green-100 dark:bg-green-900/30',
            description: 'Kondisi nyaman'
        };
    }
}

export default function ThiChart({ deviceId, date, autoRefresh = false, viewMode = 'data' }: ThiChartProps) {
    const [data, setData] = useState<ThiDataPoint[]>([]);
    const [loading, setLoading] = useState(true);

    // Check if selected date is today
    const isToday = date === new Date().toISOString().split('T')[0];

    const fetchData = async () => {
        try {
            const params = new URLSearchParams({
                device_id: deviceId.toString(),
                date: date,
                group_by: 'hour',
            });

            const response = await fetch(`/api/v1/iot/thi?${params}`);
            const result = await response.json();

            if (result.success) {
                setData(result.data);
            }
        } catch (error) {
            console.error('Failed to fetch THI data:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [deviceId, date]);

    useEffect(() => {
        if (!autoRefresh) return;
        const interval = setInterval(fetchData, 30000); // Refresh every 30 seconds
        return () => clearInterval(interval);
    }, [autoRefresh, deviceId, date]);

    if (loading) {
        return (
            <Card>
                <CardContent className="py-12">
                    <div className="text-center text-muted-foreground">Loading THI data...</div>
                </CardContent>
            </Card>
        );
    }

    if (data.length === 0) {
        return (
            <Card>
                <CardContent className="py-12">
                    <div className="text-center text-muted-foreground">No THI data available for this date</div>
                </CardContent>
            </Card>
        );
    }

    const maxThi = Math.max(...data.map(d => d.thi));
    const minThi = Math.min(...data.map(d => d.thi));
    const avgThi = data.reduce((sum, d) => sum + d.thi, 0) / data.length;

    // Render Chart View
    if (viewMode === 'chart') {
        const maxTemp = Math.max(...data.map(d => d.temperature));
        const minTemp = Math.min(...data.map(d => d.temperature));
        const maxHumidity = Math.max(...data.map(d => d.humidity));
        const minHumidity = Math.min(...data.map(d => d.humidity));
        const maxThiValue = Math.max(...data.map(d => d.thi));
        const minThiValue = Math.min(...data.map(d => d.thi));

        const chartHeight = 400;
        const chartWidth = 1000;
        const padding = { top: 40, right: 80, bottom: 60, left: 80 };
        const plotWidth = chartWidth - padding.left - padding.right;
        const plotHeight = chartHeight - padding.top - padding.bottom;

        // Scale for left Y-axis (Temperature & Humidity)
        const tempHumidityMax = Math.max(maxTemp, maxHumidity) + 5;
        const tempHumidityMin = Math.min(minTemp, minHumidity) - 5;
        const tempHumidityRange = tempHumidityMax - tempHumidityMin;

        // Scale for right Y-axis (THI)
        const thiMax = maxThiValue + 5;
        const thiMin = minThiValue - 5;
        const thiRange = thiMax - thiMin;

        const getX = (index: number) => padding.left + (index / (data.length - 1)) * plotWidth;
        const getYLeft = (value: number) => padding.top + plotHeight - ((value - tempHumidityMin) / tempHumidityRange) * plotHeight;
        const getYRight = (value: number) => padding.top + plotHeight - ((value - thiMin) / thiRange) * plotHeight;

        // Generate paths
        const tempPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYLeft(d.temperature)}`).join(' ');
        const humidityPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYLeft(d.humidity)}`).join(' ');
        const thiPath = data.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYRight(d.thi)}`).join(' ');

        return (
            <div className="space-y-4">
                {/* Chart */}
                <Card>
                    <CardHeader>
                        <CardTitle>THI Hourly Chart</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Temperature, Humidity, and THI trends throughout the day
                        </p>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <svg viewBox={`0 0 ${chartWidth} ${chartHeight}`} className="w-full" style={{ minWidth: '800px' }}>
                                {/* Grid lines - Horizontal */}
                                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                                    const y = padding.top + plotHeight * (1 - ratio);
                                    return (
                                        <line
                                            key={`grid-h-${ratio}`}
                                            x1={padding.left}
                                            y1={y}
                                            x2={padding.left + plotWidth}
                                            y2={y}
                                            stroke="#e5e7eb"
                                            strokeWidth="1"
                                        />
                                    );
                                })}

                                {/* Grid lines - Vertical */}
                                {data.map((d, i) => {
                                    if (i % 2 === 0) {
                                        const x = getX(i);
                                        return (
                                            <line
                                                key={`grid-v-${i}`}
                                                x1={x}
                                                y1={padding.top}
                                                x2={x}
                                                y2={padding.top + plotHeight}
                                                stroke="#e5e7eb"
                                                strokeWidth="1"
                                            />
                                        );
                                    }
                                    return null;
                                })}

                                {/* Y-axis Left (Temperature & Humidity) */}
                                <line
                                    x1={padding.left}
                                    y1={padding.top}
                                    x2={padding.left}
                                    y2={padding.top + plotHeight}
                                    stroke="#374151"
                                    strokeWidth="2"
                                />
                                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                                    const y = padding.top + plotHeight * (1 - ratio);
                                    const value = tempHumidityMin + tempHumidityRange * ratio;
                                    return (
                                        <g key={`y-left-${ratio}`}>
                                            <line x1={padding.left - 5} y1={y} x2={padding.left} y2={y} stroke="#374151" strokeWidth="2" />
                                            <text x={padding.left - 10} y={y + 4} textAnchor="end" fontSize="12" fill="#374151">
                                                {value.toFixed(0)}
                                            </text>
                                        </g>
                                    );
                                })}
                                <text
                                    x={padding.left - 60}
                                    y={padding.top + plotHeight / 2}
                                    textAnchor="middle"
                                    fontSize="14"
                                    fill="#374151"
                                    transform={`rotate(-90, ${padding.left - 60}, ${padding.top + plotHeight / 2})`}
                                    fontWeight="bold"
                                >
                                    Temperature (°C) / Humidity (%)
                                </text>

                                {/* Y-axis Right (THI) */}
                                <line
                                    x1={padding.left + plotWidth}
                                    y1={padding.top}
                                    x2={padding.left + plotWidth}
                                    y2={padding.top + plotHeight}
                                    stroke="#374151"
                                    strokeWidth="2"
                                />
                                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                                    const y = padding.top + plotHeight * (1 - ratio);
                                    const value = thiMin + thiRange * ratio;
                                    return (
                                        <g key={`y-right-${ratio}`}>
                                            <line x1={padding.left + plotWidth} y1={y} x2={padding.left + plotWidth + 5} y2={y} stroke="#374151" strokeWidth="2" />
                                            <text x={padding.left + plotWidth + 10} y={y + 4} textAnchor="start" fontSize="12" fill="#374151">
                                                {value.toFixed(0)}
                                            </text>
                                        </g>
                                    );
                                })}
                                <text
                                    x={padding.left + plotWidth + 60}
                                    y={padding.top + plotHeight / 2}
                                    textAnchor="middle"
                                    fontSize="14"
                                    fill="#374151"
                                    transform={`rotate(90, ${padding.left + plotWidth + 60}, ${padding.top + plotHeight / 2})`}
                                    fontWeight="bold"
                                >
                                    THI (°C)
                                </text>

                                {/* X-axis */}
                                <line
                                    x1={padding.left}
                                    y1={padding.top + plotHeight}
                                    x2={padding.left + plotWidth}
                                    y2={padding.top + plotHeight}
                                    stroke="#374151"
                                    strokeWidth="2"
                                />
                                {data.map((d, i) => {
                                    if (i % 2 === 0) {
                                        const x = getX(i);
                                        return (
                                            <g key={`x-label-${i}`}>
                                                <line x1={x} y1={padding.top + plotHeight} x2={x} y2={padding.top + plotHeight + 5} stroke="#374151" strokeWidth="2" />
                                                <text x={x} y={padding.top + plotHeight + 20} textAnchor="middle" fontSize="12" fill="#374151">
                                                    {d.time}
                                                </text>
                                            </g>
                                        );
                                    }
                                    return null;
                                })}
                                <text
                                    x={padding.left + plotWidth / 2}
                                    y={chartHeight - 10}
                                    textAnchor="middle"
                                    fontSize="14"
                                    fill="#374151"
                                    fontWeight="bold"
                                >
                                    Time (Hour)
                                </text>

                                {/* Plot lines */}
                                <path d={tempPath} fill="none" stroke="#ef4444" strokeWidth="2.5" />
                                <path d={humidityPath} fill="none" stroke="#3b82f6" strokeWidth="2.5" />
                                <path d={thiPath} fill="none" stroke="#22c55e" strokeWidth="2.5" />

                                {/* Data points */}
                                {data.map((d, i) => (
                                    <g key={`points-${i}`}>
                                        <circle cx={getX(i)} cy={getYLeft(d.temperature)} r="4" fill="#ef4444" />
                                        <circle cx={getX(i)} cy={getYLeft(d.humidity)} r="4" fill="#3b82f6" />
                                        <circle cx={getX(i)} cy={getYRight(d.thi)} r="4" fill="#22c55e" />
                                    </g>
                                ))}

                                {/* Legend */}
                                <g transform={`translate(${padding.left + 20}, ${padding.top - 20})`}>
                                    <line x1="0" y1="0" x2="30" y2="0" stroke="#ef4444" strokeWidth="2.5" />
                                    <circle cx="15" cy="0" r="4" fill="#ef4444" />
                                    <text x="35" y="4" fontSize="12" fill="#374151">Temperature</text>

                                    <line x1="150" y1="0" x2="180" y2="0" stroke="#3b82f6" strokeWidth="2.5" />
                                    <circle cx="165" cy="0" r="4" fill="#3b82f6" />
                                    <text x="185" y="4" fontSize="12" fill="#374151">Humidity</text>

                                    <line x1="280" y1="0" x2="310" y2="0" stroke="#22c55e" strokeWidth="2.5" />
                                    <circle cx="295" cy="0" r="4" fill="#22c55e" />
                                    <text x="315" y="4" fontSize="12" fill="#374151">THI</text>
                                </g>
                            </svg>
                        </div>
                    </CardContent>
                </Card>

                {/* THI Legend */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">THI Categories</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {[
                                { range: '< 27', category: 'Nyaman', thi: 26 },
                                { range: '27 - 29', category: 'Cukup Nyaman', thi: 28 },
                                { range: '> 29', category: 'Tidak Nyaman', thi: 30 },
                            ].map((item) => {
                                const cat = getThiCategory(item.thi);
                                return (
                                    <div key={item.category} className={`p-3 rounded-lg ${cat.bgColor}`}>
                                        <div className={`font-semibold text-sm ${cat.color}`}>{item.category}</div>
                                        <div className="text-xs text-muted-foreground mt-1">THI {item.range}</div>
                                        <div className="text-xs mt-1">{cat.description}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    // Render Data View (Table)
    return (
        <div className="space-y-4">
            {/* Hourly THI Table */}
            <Card>
                <CardHeader>
                    <CardTitle>Hourly THI Data</CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Temperature Humidity Index calculated from 30-minute interval averages
                    </p>
                </CardHeader>
                <CardContent>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-2">Time</th>
                                    <th className="text-right p-2">Temp (°C)</th>
                                    <th className="text-right p-2">Humidity (%)</th>
                                    <th className="text-right p-2">THI (°C)</th>
                                    <th className="text-left p-2">Status</th>
                                    <th className="text-right p-2">Data Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                {data.map((point) => {
                                    const category = getThiCategory(point.thi);
                                    
                                    // Calculate minutes elapsed based on current time
                                    let minutesElapsed: number;
                                    
                                    if (!isToday) {
                                        // Past date - all hours should show 60/60
                                        minutesElapsed = 60;
                                    } else {
                                        // Today - check current time
                                        const now = new Date();
                                        const pointHour = point.hour;
                                        const currentHour = now.getHours();
                                        
                                        if (pointHour < currentHour) {
                                            // Past hour - full 60 minutes
                                            minutesElapsed = 60;
                                        } else if (pointHour === currentHour) {
                                            // Current hour - show current minute
                                            minutesElapsed = now.getMinutes();
                                        } else {
                                            // Future hour - 0 minutes
                                            minutesElapsed = 0;
                                        }
                                    }
                                    
                                    return (
                                        <tr key={point.hour} className="border-b hover:bg-muted/50">
                                            <td className="p-2 font-medium">{point.time}</td>
                                            <td className="text-right p-2">{point.temperature.toFixed(1)}</td>
                                            <td className="text-right p-2">{point.humidity.toFixed(1)}</td>
                                            <td className="text-right p-2 font-bold">{point.thi.toFixed(2)}</td>
                                            <td className="p-2">
                                                <span className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${category.bgColor} ${category.color}`}>
                                                    {category.category}
                                                </span>
                                            </td>
                                            <td className="text-right p-2 text-muted-foreground">
                                                {minutesElapsed}/60 ({point.intervals_count} intervals)
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* THI Legend */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-sm">THI Categories</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-2 sm:grid-cols-3">
                        {[
                            { range: '< 27', category: 'Nyaman', thi: 26 },
                            { range: '27 - 29', category: 'Cukup Nyaman', thi: 28 },
                            { range: '> 29', category: 'Tidak Nyaman', thi: 30 },
                        ].map((item) => {
                            const cat = getThiCategory(item.thi);
                            return (
                                <div key={item.category} className={`p-3 rounded-lg ${cat.bgColor}`}>
                                    <div className={`font-semibold text-sm ${cat.color}`}>{item.category}</div>
                                    <div className="text-xs text-muted-foreground mt-1">THI {item.range}</div>
                                    <div className="text-xs mt-1">{cat.description}</div>
                                </div>
                            );
                        })}
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
