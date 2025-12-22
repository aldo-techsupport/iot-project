import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type DeviceSummary } from '@/types/iot';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Activity, Check, Copy, Droplets, MoreVertical, Plus, RefreshCw, ThermometerSun, Volume2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface NewDeviceInfo {
    id: number;
    name: string;
    slug: string;
    device_key: string;
}

interface Props {
    devices: DeviceSummary[];
    newDevice: NewDeviceInfo | null;
    apiBaseUrl: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'IoT Monitoring', href: '/iot' },
];

const REFRESH_INTERVAL = 10000;

function StatusBadge({ status }: { status: DeviceSummary['status'] }) {
    const styles = {
        online: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        idle: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        offline: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        never_connected: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300',
    };
    const labels = { online: 'Online', idle: 'Idle', offline: 'Offline', never_connected: 'Never Connected' };
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status]}`}>
            <span className={`mr-1.5 h-2 w-2 rounded-full ${status === 'online' ? 'animate-pulse bg-green-500' : ''}`} />
            {labels[status]}
        </span>
    );
}

function formatLastSeen(dateString: string | null): string {
    if (!dateString) return 'Never';
    const diffMs = new Date().getTime() - new Date(dateString).getTime();
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    const diffHours = Math.floor(diffMins / 60);
    if (diffHours < 24) return `${diffHours}h ago`;
    return `${Math.floor(diffHours / 24)}d ago`;
}


function DeviceCard({ device, onEdit, onDelete }: { device: DeviceSummary; onEdit: () => void; onDelete: () => void }) {
    const telemetry = device.latest_telemetry;
    return (
        <Card className="transition-shadow hover:shadow-lg">
            <CardHeader className="pb-2">
                <div className="flex items-start justify-between">
                    <button onClick={() => router.visit(`/iot/devices/${device.id}`)} className="text-left flex-1">
                        <CardTitle className="text-lg hover:text-primary">{device.name}</CardTitle>
                        <p className="text-muted-foreground text-sm">{device.location || 'No location'}</p>
                    </button>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={device.status} />
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="p-1 text-muted-foreground hover:text-foreground rounded hover:bg-muted">
                                    <MoreVertical className="h-4 w-4" />
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={onEdit}>Edit Device</DropdownMenuItem>
                                <DropdownMenuItem onClick={onDelete} className="text-red-600">Delete Device</DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                {telemetry ? (
                    <div className="grid grid-cols-3 gap-4">
                        <div className="flex items-center gap-2">
                            <ThermometerSun className="h-4 w-4 text-orange-500" />
                            <div>
                                <p className="text-muted-foreground text-xs">Temp</p>
                                <p className="font-semibold">{telemetry.temperature}°C</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Droplets className="h-4 w-4 text-blue-500" />
                            <div>
                                <p className="text-muted-foreground text-xs">Humidity</p>
                                <p className="font-semibold">{telemetry.humidity}%</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <Volume2 className="h-4 w-4 text-purple-500" />
                            <div>
                                <p className="text-muted-foreground text-xs">Noise</p>
                                <p className="font-semibold">{telemetry.noise_db} dB</p>
                            </div>
                        </div>
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">No telemetry data</p>
                )}
                <p className="text-muted-foreground mt-3 text-xs">Last seen: {formatLastSeen(device.last_seen_at)}</p>
            </CardContent>
        </Card>
    );
}

function AddDeviceModal({ isOpen, onClose, onSuccess }: { isOpen: boolean; onClose: () => void; onSuccess: (device: NewDeviceInfo) => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', location: '', description: '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/iot/devices', {
            preserveScroll: true,
            onSuccess: (page) => {
                const props = page.props as unknown as Props;
                reset();
                onClose();
                if (props.newDevice) onSuccess(props.newDevice);
            },
        });
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg bg-background p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-xl font-bold">Add New Device</h2>
                    <button onClick={onClose} className="text-muted-foreground hover:text-foreground"><X className="h-5 w-5" /></button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="name">Device Name *</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g., Sensor Ruang Server" required />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label htmlFor="location">Location</Label>
                        <Input id="location" value={data.location} onChange={(e) => setData('location', e.target.value)} placeholder="e.g., Gedung A - Lantai 2" />
                    </div>
                    <div>
                        <Label htmlFor="description">Description</Label>
                        <textarea id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Optional description..." className="w-full rounded-md border bg-background px-3 py-2 text-sm" rows={3} />
                    </div>
                    <div className="flex justify-end gap-3 pt-4">
                        <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm hover:bg-muted">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                            {processing ? 'Creating...' : 'Create Device'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}


function EditDeviceModal({ device, onClose }: { device: DeviceSummary; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm({ name: device.name, location: device.location || '', description: device.description || '' });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/iot/devices/${device.id}`, { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg bg-background p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-xl font-bold">Edit Device</h2>
                    <button onClick={onClose} className="text-muted-foreground hover:text-foreground"><X className="h-5 w-5" /></button>
                </div>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="edit-name">Device Name *</Label>
                        <Input id="edit-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                        {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>
                    <div>
                        <Label htmlFor="edit-location">Location</Label>
                        <Input id="edit-location" value={data.location} onChange={(e) => setData('location', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="edit-description">Description</Label>
                        <textarea id="edit-description" value={data.description} onChange={(e) => setData('description', e.target.value)} className="w-full rounded-md border bg-background px-3 py-2 text-sm" rows={3} />
                    </div>
                    <div className="flex justify-end gap-3 pt-4">
                        <button type="button" onClick={onClose} className="rounded-lg border px-4 py-2 text-sm hover:bg-muted">Cancel</button>
                        <button type="submit" disabled={processing} className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50">
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function DeleteConfirmModal({ device, onClose }: { device: DeviceSummary; onClose: () => void }) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        setProcessing(true);
        router.delete(`/iot/devices/${device.id}`, { onFinish: () => { setProcessing(false); onClose(); } });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-md rounded-lg bg-background p-6 shadow-xl">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-xl font-bold text-red-600">Delete Device</h2>
                    <button onClick={onClose} className="text-muted-foreground hover:text-foreground"><X className="h-5 w-5" /></button>
                </div>
                <p className="text-muted-foreground mb-4">Are you sure you want to delete <strong>{device.name}</strong>? This will also delete all telemetry data. This action cannot be undone.</p>
                <div className="flex justify-end gap-3">
                    <button onClick={onClose} className="rounded-lg border px-4 py-2 text-sm hover:bg-muted">Cancel</button>
                    <button onClick={handleDelete} disabled={processing} className="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                        {processing ? 'Deleting...' : 'Delete'}
                    </button>
                </div>
            </div>
        </div>
    );
}


function ApiKeyModal({ device, apiBaseUrl, onClose }: { device: NewDeviceInfo; apiBaseUrl: string; onClose: () => void }) {
    const [copiedKey, setCopiedKey] = useState(false);
    const [copiedCurl, setCopiedCurl] = useState(false);

    const deviceEndpoint = `${apiBaseUrl}/devices/${device.id}/telemetry`;
    const curlExample = `curl -X POST ${deviceEndpoint} \\
  -H "Content-Type: application/json" \\
  -H "X-Device-Key: ${device.device_key}" \\
  -d '{"temperature": 25.5, "humidity": 60.0, "noise_db": 45.0}'`;

    const copyToClipboard = async (text: string, type: 'key' | 'curl') => {
        await navigator.clipboard.writeText(text);
        if (type === 'key') { setCopiedKey(true); setTimeout(() => setCopiedKey(false), 2000); }
        else { setCopiedCurl(true); setTimeout(() => setCopiedCurl(false), 2000); }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div className="w-full max-w-2xl rounded-lg bg-background p-6 shadow-xl max-h-[90vh] overflow-y-auto">
                <div className="mb-4 flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-green-600">✓ Device Created!</h2>
                        <p className="text-muted-foreground">{device.name}</p>
                    </div>
                    <button onClick={onClose} className="text-muted-foreground hover:text-foreground"><X className="h-5 w-5" /></button>
                </div>
                <div className="space-y-4">
                    <div className="rounded-lg border border-yellow-500/50 bg-yellow-500/10 p-4">
                        <p className="text-sm font-medium text-yellow-600 dark:text-yellow-400">⚠️ Save this API Key! It won't be shown again.</p>
                    </div>
                    <div>
                        <Label>Device API Key</Label>
                        <div className="mt-1 flex gap-2">
                            <code className="flex-1 rounded-md border bg-muted px-3 py-2 text-sm font-mono break-all">{device.device_key}</code>
                            <button onClick={() => copyToClipboard(device.device_key, 'key')} className="rounded-lg border px-3 py-2 hover:bg-muted">
                                {copiedKey ? <Check className="h-4 w-4 text-green-500" /> : <Copy className="h-4 w-4" />}
                            </button>
                        </div>
                    </div>
                    <div>
                        <Label>API Endpoint</Label>
                        <code className="mt-1 block rounded-md border bg-muted px-3 py-2 text-sm break-all">POST {deviceEndpoint}</code>
                    </div>
                    <div>
                        <div className="flex items-center justify-between">
                            <Label>Example cURL</Label>
                            <button onClick={() => copyToClipboard(curlExample, 'curl')} className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
                                {copiedCurl ? <Check className="h-3 w-3 text-green-500" /> : <Copy className="h-3 w-3" />} Copy
                            </button>
                        </div>
                        <pre className="mt-1 overflow-x-auto rounded-md border bg-muted p-3 text-xs">{curlExample}</pre>
                    </div>
                    <div className="flex justify-end pt-4">
                        <button onClick={onClose} className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Done</button>
                    </div>
                </div>
            </div>
        </div>
    );
}


export default function Dashboard({ devices, newDevice, apiBaseUrl }: Props) {
    const [isAddModalOpen, setIsAddModalOpen] = useState(false);
    const [apiKeyDevice, setApiKeyDevice] = useState<NewDeviceInfo | null>(newDevice);
    const [editingDevice, setEditingDevice] = useState<DeviceSummary | null>(null);
    const [deletingDevice, setDeletingDevice] = useState<DeviceSummary | null>(null);
    const [isRefreshing, setIsRefreshing] = useState(false);

    // Show API key modal immediately if newDevice exists from session
    useEffect(() => {
        if (newDevice) setApiKeyDevice(newDevice);
    }, [newDevice]);

    useEffect(() => {
        const interval = setInterval(() => { router.reload({ only: ['devices'] }); }, REFRESH_INTERVAL);
        return () => clearInterval(interval);
    }, []);

    const handleRefresh = () => {
        setIsRefreshing(true);
        router.reload({ only: ['devices'], onFinish: () => setIsRefreshing(false) });
    };

    const stats = {
        total: devices.length,
        online: devices.filter((d) => d.status === 'online').length,
        idle: devices.filter((d) => d.status === 'idle').length,
        offline: devices.filter((d) => d.status === 'offline').length,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="IoT Monitoring" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">IoT Monitoring</h1>
                        <p className="text-muted-foreground">Monitor your IoT devices in real-time</p>
                    </div>
                    <div className="flex gap-2">
                        <button onClick={handleRefresh} disabled={isRefreshing} className="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm hover:bg-muted disabled:opacity-50">
                            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} /> Refresh
                        </button>
                        <button onClick={() => setIsAddModalOpen(true)} className="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                            <Plus className="h-4 w-4" /> Add Device
                        </button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Devices</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent><div className="text-2xl font-bold">{stats.total}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Online</CardTitle>
                            <div className="h-3 w-3 rounded-full bg-green-500" />
                        </CardHeader>
                        <CardContent><div className="text-2xl font-bold text-green-600">{stats.online}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Idle</CardTitle>
                            <div className="h-3 w-3 rounded-full bg-yellow-500" />
                        </CardHeader>
                        <CardContent><div className="text-2xl font-bold text-yellow-600">{stats.idle}</div></CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Offline</CardTitle>
                            <div className="h-3 w-3 rounded-full bg-red-500" />
                        </CardHeader>
                        <CardContent><div className="text-2xl font-bold text-red-600">{stats.offline}</div></CardContent>
                    </Card>
                </div>

                {devices.length > 0 ? (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        {devices.map((device) => (
                            <DeviceCard key={device.id} device={device} onEdit={() => setEditingDevice(device)} onDelete={() => setDeletingDevice(device)} />
                        ))}
                    </div>
                ) : (
                    <Card className="flex flex-col items-center justify-center py-12">
                        <Activity className="h-12 w-12 text-muted-foreground" />
                        <h3 className="mt-4 text-lg font-semibold">No devices yet</h3>
                        <p className="text-muted-foreground">Add your first IoT device to start monitoring</p>
                        <button onClick={() => setIsAddModalOpen(true)} className="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                            <Plus className="h-4 w-4" /> Add Device
                        </button>
                    </Card>
                )}
            </div>

            <AddDeviceModal isOpen={isAddModalOpen} onClose={() => setIsAddModalOpen(false)} onSuccess={setApiKeyDevice} />
            {editingDevice && <EditDeviceModal device={editingDevice} onClose={() => setEditingDevice(null)} />}
            {deletingDevice && <DeleteConfirmModal device={deletingDevice} onClose={() => setDeletingDevice(null)} />}
            {apiKeyDevice && <ApiKeyModal device={apiKeyDevice} apiBaseUrl={apiBaseUrl} onClose={() => setApiKeyDevice(null)} />}
        </AppLayout>
    );
}
