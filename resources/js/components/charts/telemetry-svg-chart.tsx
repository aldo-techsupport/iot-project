import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type ChartDataPoint } from '@/types/iot';
import { useState, useMemo } from 'react';
import { cn } from '@/lib/utils';

interface TelemetrySvgChartProps {
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

export default function TelemetrySvgChart({ data }: TelemetrySvgChartProps) {
    const [selectedRange, setSelectedRange] = useState<TimeRange>(1440);
    const [hoveredPoint, setHoveredPoint] = useState<number | null>(null);

    const filteredData = useMemo(() => {
        if (data.length === 0) return [];

        const now = new Date();
        const cutoffTime = new Date(now.getTime() - selectedRange * 60 * 1000);

        return data.filter((point) => {
            const pointTime = new Date(point.measured_at);
            return pointTime >= cutoffTime;
        });
    }, [data, selectedRange]);

    if (filteredData.length === 0) {
        return (
            <Card>
                <CardContent className="py-12">
                    <div className="text-center text-muted-foreground">No data available for selected time range</div>
                </CardContent>
            </Card>
        );
    }

    const chartHeight = 400;
    const chartWidth = 1000;
    const padding = { top: 40, right: 80, bottom: 60, left: 80 };
    const plotWidth = chartWidth - padding.left - padding.right;
    const plotHeight = chartHeight - padding.top - padding.bottom;

    // Get min/max values
    const temps = filteredData.map(d => d.temperature);
    const humidities = filteredData.map(d => d.humidity);
    const noises = filteredData.map(d => d.noise_db);

    const maxTemp = Math.max(...temps);
    const minTemp = Math.min(...temps);
    const maxHumidity = Math.max(...humidities);
    const minHumidity = Math.min(...humidities);
    const maxNoise = Math.max(...noises);
    const minNoise = Math.min(...noises);

    // Scale for left Y-axis (Temperature & Humidity)
    const tempHumidityMax = Math.max(maxTemp, maxHumidity) + 5;
    const tempHumidityMin = Math.min(minTemp, minHumidity) - 5;
    const tempHumidityRange = tempHumidityMax - tempHumidityMin;

    // Scale for right Y-axis (Noise)
    const noiseMax = maxNoise + 5;
    const noiseMin = minNoise - 5;
    const noiseRange = noiseMax - noiseMin;

    const getX = (index: number) => padding.left + (index / (filteredData.length - 1)) * plotWidth;
    const getYLeft = (value: number) => padding.top + plotHeight - ((value - tempHumidityMin) / tempHumidityRange) * plotHeight;
    const getYRight = (value: number) => padding.top + plotHeight - ((value - noiseMin) / noiseRange) * plotHeight;

    // Generate paths
    const tempPath = filteredData.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYLeft(d.temperature)}`).join(' ');
    const humidityPath = filteredData.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYLeft(d.humidity)}`).join(' ');
    const noisePath = filteredData.map((d, i) => `${i === 0 ? 'M' : 'L'} ${getX(i)} ${getYRight(d.noise_db)}`).join(' ');

    // Format time labels - show every nth point
    const labelInterval = Math.ceil(filteredData.length / 12);

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <CardTitle>24-Hour Telemetry Trends</CardTitle>
                        <p className="text-sm text-muted-foreground">
                            Temperature, Humidity, and Noise Level over time ({filteredData.length} data points)
                        </p>
                    </div>
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
                        {filteredData.map((d, i) => {
                            if (i % labelInterval === 0) {
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
                            Temp (°C) / Humidity (%)
                        </text>

                        {/* Y-axis Right (Noise) */}
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
                            const value = noiseMin + noiseRange * ratio;
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
                            Noise (dB)
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
                        {filteredData.map((d, i) => {
                            if (i % labelInterval === 0) {
                                const x = getX(i);
                                const time = new Date(d.measured_at);
                                const timeStr = time.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false });
                                return (
                                    <g key={`x-label-${i}`}>
                                        <line x1={x} y1={padding.top + plotHeight} x2={x} y2={padding.top + plotHeight + 5} stroke="#374151" strokeWidth="2" />
                                        <text x={x} y={padding.top + plotHeight + 20} textAnchor="middle" fontSize="12" fill="#374151">
                                            {timeStr}
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
                            Time
                        </text>

                        {/* Plot lines */}
                        <path d={tempPath} fill="none" stroke="#ef4444" strokeWidth="2.5" />
                        <path d={humidityPath} fill="none" stroke="#3b82f6" strokeWidth="2.5" />
                        <path d={noisePath} fill="none" stroke="#a855f7" strokeWidth="2.5" />

                        {/* Data points with hover */}
                        {filteredData.map((d, i) => {
                            const showPoint = i % Math.ceil(filteredData.length / 50) === 0 || hoveredPoint === i;
                            if (!showPoint) return null;
                            
                            return (
                                <g key={`points-${i}`}>
                                    {/* Invisible larger circles for easier hovering */}
                                    <circle 
                                        cx={getX(i)} 
                                        cy={getYLeft(d.temperature)} 
                                        r="8" 
                                        fill="transparent"
                                        onMouseEnter={() => setHoveredPoint(i)}
                                        onMouseLeave={() => setHoveredPoint(null)}
                                        style={{ cursor: 'pointer' }}
                                    />
                                    <circle 
                                        cx={getX(i)} 
                                        cy={getYLeft(d.humidity)} 
                                        r="8" 
                                        fill="transparent"
                                        onMouseEnter={() => setHoveredPoint(i)}
                                        onMouseLeave={() => setHoveredPoint(null)}
                                        style={{ cursor: 'pointer' }}
                                    />
                                    <circle 
                                        cx={getX(i)} 
                                        cy={getYRight(d.noise_db)} 
                                        r="8" 
                                        fill="transparent"
                                        onMouseEnter={() => setHoveredPoint(i)}
                                        onMouseLeave={() => setHoveredPoint(null)}
                                        style={{ cursor: 'pointer' }}
                                    />
                                    
                                    {/* Visible points */}
                                    <circle cx={getX(i)} cy={getYLeft(d.temperature)} r={hoveredPoint === i ? "5" : "3"} fill="#ef4444" />
                                    <circle cx={getX(i)} cy={getYLeft(d.humidity)} r={hoveredPoint === i ? "5" : "3"} fill="#3b82f6" />
                                    <circle cx={getX(i)} cy={getYRight(d.noise_db)} r={hoveredPoint === i ? "5" : "3"} fill="#a855f7" />
                                    
                                    {/* Show values on hover */}
                                    {hoveredPoint === i && (
                                        <g>
                                            {/* Tooltip background */}
                                            <rect
                                                x={getX(i) + 10}
                                                y={padding.top + 10}
                                                width="140"
                                                height="80"
                                                fill="white"
                                                stroke="#374151"
                                                strokeWidth="1"
                                                rx="4"
                                                opacity="0.95"
                                            />
                                            {/* Time */}
                                            <text
                                                x={getX(i) + 15}
                                                y={padding.top + 25}
                                                fontSize="11"
                                                fill="#374151"
                                                fontWeight="bold"
                                            >
                                                {new Date(d.measured_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false })}
                                            </text>
                                            {/* Temperature */}
                                            <text x={getX(i) + 15} y={padding.top + 42} fontSize="10" fill="#ef4444">
                                                Temp: {d.temperature.toFixed(1)}°C
                                            </text>
                                            {/* Humidity */}
                                            <text x={getX(i) + 15} y={padding.top + 58} fontSize="10" fill="#3b82f6">
                                                Humidity: {d.humidity.toFixed(1)}%
                                            </text>
                                            {/* Noise */}
                                            <text x={getX(i) + 15} y={padding.top + 74} fontSize="10" fill="#a855f7">
                                                Noise: {d.noise_db.toFixed(1)} dB
                                            </text>
                                        </g>
                                    )}
                                </g>
                            );
                        })}

                        {/* Legend */}
                        <g transform={`translate(${padding.left + 20}, ${padding.top - 20})`}>
                            <line x1="0" y1="0" x2="30" y2="0" stroke="#ef4444" strokeWidth="2.5" />
                            <circle cx="15" cy="0" r="3" fill="#ef4444" />
                            <text x="35" y="4" fontSize="12" fill="#374151">Temperature</text>

                            <line x1="150" y1="0" x2="180" y2="0" stroke="#3b82f6" strokeWidth="2.5" />
                            <circle cx="165" cy="0" r="3" fill="#3b82f6" />
                            <text x="185" y="4" fontSize="12" fill="#374151">Humidity</text>

                            <line x1="280" y1="0" x2="310" y2="0" stroke="#a855f7" strokeWidth="2.5" />
                            <circle cx="295" cy="0" r="3" fill="#a855f7" />
                            <text x="315" y="4" fontSize="12" fill="#374151">Noise Level</text>
                        </g>
                    </svg>
                </div>
            </CardContent>
        </Card>
    );
}
