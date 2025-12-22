import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

export default function TelemetryLog({ device, telemetries, filters }: Props) {
    const [fromDate, setFromDate] = useState(filters.from || '');
    const [toDate, setToDate] = useState(filters.to || '');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'IoT Monitoring', href: '/iot' },
        { title: device.name, href: `/iot/devices/${device.id}` },
        { title: 'Log', href: `/iot/devices/${device.id}/log` },
    ];

    const handleFilter = () => {
        router.get(`/iot/devices/${device.id}/log`, {
            from: fromDate || undefined,
            to: toDate || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
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
                        <CardTitle className="text-base">Filter by Date</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="datetime-local"
                                    value={fromDate}
                                    onChange={(e) => setFromDate(e.target.value)}
                                    className="w-auto"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="datetime-local"
                                    value={toDate}
                                    onChange={(e) => setToDate(e.target.value)}
                                    className="w-auto"
                                />
                            </div>
                            <button
                                onClick={handleFilter}
                                className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            >
                                <Search className="h-4 w-4" />
                                Filter
                            </button>
                            {(fromDate || toDate) && (
                                <button
                                    onClick={() => {
                                        setFromDate('');
                                        setToDate('');
                                        router.get(`/iot/devices/${device.id}/log`);
                                    }}
                                    className="text-sm text-muted-foreground hover:text-foreground"
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
                                    </tr>
                                </thead>
                                <tbody>
                                    {telemetries.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={4} className="px-4 py-8 text-center text-muted-foreground">
                                                No telemetry data found
                                            </td>
                                        </tr>
                                    ) : (
                                        telemetries.data.map((t) => (
                                            <tr key={t.id} className="border-b last:border-0 hover:bg-muted/30">
                                                <td className="px-4 py-3 text-sm">{formatDateTime(t.measured_at)}</td>
                                                <td className="px-4 py-3 text-right text-sm font-mono">{t.temperature}°C</td>
                                                <td className="px-4 py-3 text-right text-sm font-mono">{t.humidity}%</td>
                                                <td className="px-4 py-3 text-right text-sm font-mono">{t.noise_db} dB</td>
                                            </tr>
                                        ))
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
