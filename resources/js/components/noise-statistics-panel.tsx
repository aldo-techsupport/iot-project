import React from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowDown, ArrowUp, ThermometerSun, Thermometer, Droplets, Info, Clock, Database, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils'; // Assuming utils exists, if not I will standard use className

interface NoiseStatisticsPanelProps {
    calculation: {
        min_value: number;
        max_value: number;
        average_value: number;
        leq_value: number;
        thi_average: number;
        avg_temperature?: number | null;
        avg_humidity?: number | null;
        data_count: number;
        is_valid?: boolean;
        invalid_reason?: string | null;
        updated_at?: string;
    } | null;
    loading?: boolean;
}

export default function NoiseStatisticsPanel({ calculation, loading }: NoiseStatisticsPanelProps) {
    if (loading) {
        return (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                {[1, 2, 3, 4, 5].map((i) => (
                    <Card key={i} className="animate-pulse">
                        <CardContent className="flex flex-col items-center justify-center p-6 h-32">
                            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    }

    if (!calculation) {
        return (
            <div className="rounded-lg border bg-blue-50 dark:bg-blue-950/30 p-4 text-blue-900 dark:text-blue-200">
                <div className="flex items-center gap-2">
                    <Info className="h-5 w-5" />
                    <span className="font-medium">No calculation results yet.</span>
                </div>
                <p className="mt-1 text-sm opacity-90 pl-7">
                    Calculations are performed automatically after 60 data points are collected for a period (1 data per minute).
                </p>
            </div>
        );
    }

    const stats = [
        {
            label: 'Min',
            value: calculation.min_value,
            unit: 'dB(A)',
            icon: ArrowDown,
            color: 'text-green-500',
            bg: 'bg-green-100 dark:bg-green-900/20',
        },
        {
            label: 'Max',
            value: calculation.max_value,
            unit: 'dB(A)',
            icon: ArrowUp,
            color: 'text-red-500',
            bg: 'bg-red-100 dark:bg-red-900/20',
        },
        {
            label: 'THI',
            value: calculation.thi_average,
            unit: '°C',
            icon: ThermometerSun,
            color: 'text-orange-500',
            bg: 'bg-orange-100 dark:bg-orange-900/20',
        },
        {
            label: 'Rata² Suhu',
            value: calculation.avg_temperature ?? null,
            unit: '°C',
            icon: Thermometer,
            color: 'text-blue-500',
            bg: 'bg-blue-100 dark:bg-blue-900/20',
        },
        {
            label: 'Rata² Kelembaban',
            value: calculation.avg_humidity ?? null,
            unit: '%',
            icon: Droplets,
            color: 'text-cyan-500',
            bg: 'bg-cyan-100 dark:bg-cyan-900/20',
        },
    ];

    const isInvalid = calculation.is_valid === false;

    return (
        <div className="space-y-4">
            {/* INVALID DATA banner */}
            {isInvalid && (
                <div className="flex items-start gap-3 rounded-lg border border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950 px-4 py-3">
                    <span className="text-red-600 dark:text-red-400 text-lg font-bold mt-0.5">⚠</span>
                    <div>
                        <p className="text-sm font-bold text-red-700 dark:text-red-300">INVALID DATA</p>
                        <p className="text-xs text-red-600 dark:text-red-400 mt-0.5">
                            {calculation.invalid_reason ?? `Hanya ${calculation.data_count}/60 data point tersedia. Hasil kalkulasi tidak valid.`}
                        </p>
                    </div>
                </div>
            )}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                {stats.map((stat) => (
                    <Card key={stat.label}>
                        <CardContent className="flex flex-col items-center justify-center p-4 text-center">
                            <div className={cn("mb-2 rounded-full p-2.5", stat.bg)}>
                                <stat.icon className={cn("h-5 w-5", stat.color)} />
                            </div>
                            <span className="text-xs font-semibold uppercase text-muted-foreground mb-1">
                                {stat.label}
                            </span>
                            <div className="flex items-baseline gap-1">
                                <span className="text-2xl font-bold tracking-tight">
                                    {stat.value ? stat.value.toFixed(2) : 'N/A'}
                                </span>
                                {stat.unit && <span className="text-xs text-muted-foreground">{stat.unit}</span>}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            {calculation.updated_at && (
                <div className="flex justify-center items-center gap-4 text-xs text-muted-foreground">
                    <div className="flex items-center gap-1">
                        <Clock className="h-3 w-3" />
                        <span>Last calculated: {new Date(calculation.updated_at).toLocaleString()}</span>
                    </div>
                    <div className="flex items-center gap-1">
                        <Database className="h-3 w-3" />
                        <span>{calculation.data_count} data points</span>
                    </div>
                </div>
            )}
        </div>
    );
}
