import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { DateTimePicker24hForm } from '@/components/ui/date-time-picker-24h';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type PaginatedTelemetry, type TelemetryFilters } from '@/types/iot';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { useState } from 'react';

interface Props {
    device: {
        id: number;
        name: string;
        location: string | null;
    };
    telemetries: PaginatedTelemetry;
    filters: TelemetryFilters;
}

function formatDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('id-ID', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

// Function to calculate THI (Temperature Humidity Index)
// THI = 0.8 × Ta + (RH × Ta) / 500
function calculateTHI(temperature: number, humidity: number): number {
    return 0.8 * temperature + (humidity * temperature) / 500;
}

export default function TelemetryLog({ device, telemetries, filters }: Props) {
    // Parse initial filters into Date objects
    const [fromDate, setFromDate] = useState<Date | undefined>(
        filters.from ? new Date(filters.from) : undefined
    );
    const [toDate, setToDate] = useState<Date | undefined>(
        filters.to ? new Date(filters.to) : undefined
    );

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'IoT Monitoring', href: '/iot' },
        { title: device.name, href: `/iot/devices/${device.id}` },
        { title: 'Log', href: `/iot/devices/${device.id}/log` },
    ];

    const formatDateTimeForAPI = (date: Date | undefined) => {
        if (!date) return undefined;
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:00`;
    };

    const handleFilter = () => {
        router.get(`/iot/devices/${device.id}/log`, {
            from: formatDateTimeForAPI(fromDate),
            to: formatDateTimeForAPI(toDate),
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {
                from: formatDateTimeForAPI(fromDate),
                to: formatDateTimeForAPI(toDate),
            }, { preserveState: true, preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${device.name} - Telemetry Log`} />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href={`/iot/devices/${device.id}`}
                        className="inline-flex items-center gap-2 text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="h-4 w-4" />
                        Back
                    </Link>
                    <div>
                        <h1 className="text-2xl font-bold">Telemetry Log</h1>
                        <p className="text-muted-foreground">{device.name} - {device.location || 'No location'}</p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Filter by Date & Time</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-[1fr_1fr_auto_auto] gap-4 items-end">
                            <div className="grid gap-2">
                                <Label>From</Label>
                                <DateTimePicker24hForm
                                    date={fromDate}
                                    setDate={setFromDate}
                                    placeholder="Select start date & time"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label>To</Label>
                                <DateTimePicker24hForm
                                    date={toDate}
                                    setDate={setToDate}
                                    placeholder="Select end date & time"
                                />
                            </div>
                            <button
                                onClick={handleFilter}
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 h-10"
                            >
                                <Search className="h-4 w-4" />
                                Filter
                            </button>
                            {(fromDate || toDate) && (
                                <button
                                    onClick={() => {
                                        setFromDate(undefined);
                                        setToDate(undefined);
                                        router.get(`/iot/devices/${device.id}/log`);
                                    }}
                                    className="text-sm text-muted-foreground hover:text-foreground h-10"
                                >
                                    Clear
                                </button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left text-sm font-medium">Time</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Temperature</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Humidity</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">Noise</th>
                                        <th className="px-4 py-3 text-right text-sm font-medium">THI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {telemetries.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                                                No telemetry data found
                                            </td>
                                        </tr>
                                    ) : (
                                        telemetries.data.map((t) => {
                                            const thi = calculateTHI(t.temperature, t.humidity);
                                            
                                            return (
                                                <tr key={t.id} className="border-b last:border-0 hover:bg-muted/30">
                                                    <td className="px-4 py-3 text-sm">
                                                        <div className="flex flex-col">
                                                            <span>{formatDateTime(t.measured_at)}</span>
                                                            {t.is_filled && (
                                                                <span className={`mt-1 inline-flex w-fit items-center rounded px-1.5 py-0.5 text-[10px] font-medium uppercase leading-none ${t.fill_method === 'zero'
                                                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                                    : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                                    }`}>
                                                                    TIMEOUT {t.fill_method === 'zero' ? '(OFFLINE)' : '(AUTO-FILLED)'}
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-3 text-right text-sm font-mono">{t.temperature}°C</td>
                                                    <td className="px-4 py-3 text-right text-sm font-mono">{t.humidity}%</td>
                                                    <td className="px-4 py-3 text-right text-sm font-mono">{t.noise_db} dB(A)</td>
                                                    <td className="px-4 py-3 text-right text-sm font-mono">{thi.toFixed(2)}°C</td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {telemetries.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {(telemetries.current_page - 1) * telemetries.per_page + 1} to{' '}
                            {Math.min(telemetries.current_page * telemetries.per_page, telemetries.total)} of{' '}
                            {telemetries.total} entries
                        </p>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={() => handlePageChange(telemetries.links[0]?.url)}
                                disabled={telemetries.current_page === 1}
                                className="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
                            >
                                <ChevronLeft className="h-4 w-4" />
                                Previous
                            </button>
                            <span className="px-3 text-sm">
                                Page {telemetries.current_page} of {telemetries.last_page}
                            </span>
                            <button
                                onClick={() => handlePageChange(telemetries.links[telemetries.links.length - 1]?.url)}
                                disabled={telemetries.current_page === telemetries.last_page}
                                className="inline-flex items-center gap-1 rounded-lg border px-3 py-2 text-sm disabled:opacity-50"
                            >
                                Next
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
