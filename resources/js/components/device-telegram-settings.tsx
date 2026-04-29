import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import type { DeviceDetail } from '@/types/iot';

interface Props {
    device: DeviceDetail;
}

export default function DeviceTelegramSettings({ device }: Props) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [testing, setTesting] = useState(false);

    const { data, setData, put, processing, errors, reset } = useForm({
        telegram_bot_token: device.telegram_bot_token || '',
        telegram_chat_id: device.telegram_chat_id || '',
        telegram_enabled: device.telegram_enabled || false,
        telegram_schedule_type: device.telegram_schedule_type || 'working_hours',
        telegram_schedule_hours: device.telegram_schedule_hours || [],
        telegram_alert_cooldown: device.telegram_alert_cooldown || 5,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        console.log('Submitting telegram settings:', data);
        put(`/iot/devices/${device.id}/telegram`, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Settings saved successfully');
            },
            onError: (errors) => {
                console.error('Save failed:', errors);
            },
        });
    };

    const handleTest = () => {
        setTesting(true);
        router.post(
            `/iot/devices/${device.id}/telegram/test`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            }
        );
    };

    const handleToggleChange = (checked: boolean) => {
        console.log('Toggle changed:', checked);
        setData('telegram_enabled', checked);
    };

    return (
        <div className="space-y-6">
            {flash?.success && (
                <Alert className="border-green-200 bg-green-50 text-green-800">
                    <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
            )}

            {flash?.error && (
                <Alert variant="destructive">
                    <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Telegram Configuration</CardTitle>
                    <CardDescription>
                        Configure Telegram bot for this device's notifications
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="telegram_bot_token">Bot Token</Label>
                            <Input
                                id="telegram_bot_token"
                                type="text"
                                placeholder="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
                                value={data.telegram_bot_token}
                                onChange={(e) => setData('telegram_bot_token', e.target.value)}
                            />
                            {errors.telegram_bot_token && (
                                <p className="text-sm text-red-600">{errors.telegram_bot_token}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Get your bot token from @BotFather on Telegram
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="telegram_chat_id">Chat ID</Label>
                            <Input
                                id="telegram_chat_id"
                                type="text"
                                placeholder="987654321"
                                value={data.telegram_chat_id}
                                onChange={(e) => setData('telegram_chat_id', e.target.value)}
                            />
                            {errors.telegram_chat_id && (
                                <p className="text-sm text-red-600">{errors.telegram_chat_id}</p>
                            )}
                            <p className="text-xs text-muted-foreground">
                                Get your chat ID from @getidsbot on Telegram
                            </p>
                        </div>

                        <div className="flex items-center justify-between rounded-lg border p-4">
                            <div className="space-y-0.5">
                                <Label htmlFor="telegram_enabled">Enable Telegram Notifications</Label>
                                <p className="text-sm text-muted-foreground">
                                    Receive notifications for this device via Telegram
                                </p>
                            </div>
                            <Switch
                                id="telegram_enabled"
                                checked={data.telegram_enabled}
                                onCheckedChange={handleToggleChange}
                            />
                        </div>

                        {data.telegram_enabled && (
                            <div className="space-y-4 rounded-lg border p-4 bg-muted/50">
                                <div className="space-y-2">
                                    <Label htmlFor="telegram_schedule_type">Notification Schedule</Label>
                                    <Select
                                        value={data.telegram_schedule_type}
                                        onValueChange={(value) => setData('telegram_schedule_type', value)}
                                    >
                                        <SelectTrigger id="telegram_schedule_type">
                                            <SelectValue placeholder="Select schedule type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="working_hours">Working Hours (08:00 - 17:00)</SelectItem>
                                            <SelectItem value="24_hours">24 Hours (Every Hour)</SelectItem>
                                            <SelectItem value="custom">Custom Hours</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-xs text-muted-foreground">
                                        {data.telegram_schedule_type === 'working_hours' && 'Notifications sent only during working hours (08:00 - 17:00 WIB)'}
                                        {data.telegram_schedule_type === '24_hours' && 'Notifications sent every hour, 24/7'}
                                        {data.telegram_schedule_type === 'custom' && 'Select specific hours below'}
                                    </p>
                                </div>

                                {data.telegram_schedule_type === 'custom' && (
                                    <div className="space-y-2">
                                        <Label>Select Hours (WIB)</Label>
                                        <div className="grid grid-cols-6 gap-2">
                                            {Array.from({ length: 24 }, (_, i) => i).map((hour) => {
                                                const isChecked = data.telegram_schedule_hours.includes(hour);
                                                return (
                                                    <div key={hour} className="flex items-center space-x-2">
                                                        <Checkbox
                                                            id={`hour-${hour}`}
                                                            checked={isChecked}
                                                            onCheckedChange={(checked) => {
                                                                if (checked) {
                                                                    setData('telegram_schedule_hours', [...data.telegram_schedule_hours, hour].sort((a, b) => a - b));
                                                                } else {
                                                                    setData('telegram_schedule_hours', data.telegram_schedule_hours.filter((h: number) => h !== hour));
                                                                }
                                                            }}
                                                        />
                                                        <Label
                                                            htmlFor={`hour-${hour}`}
                                                            className="text-sm font-normal cursor-pointer"
                                                        >
                                                            {hour.toString().padStart(2, '0')}:00
                                                        </Label>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                        {data.telegram_schedule_hours.length > 0 && (
                                            <p className="text-xs text-muted-foreground">
                                                Selected: {data.telegram_schedule_hours.map((h: number) => `${h.toString().padStart(2, '0')}:00`).join(', ')}
                                            </p>
                                        )}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="telegram_alert_cooldown">Real-time Alert Cooldown (Minutes)</Label>
                                    <Input
                                        id="telegram_alert_cooldown"
                                        type="number"
                                        min="1"
                                        max="60"
                                        value={data.telegram_alert_cooldown}
                                        onChange={(e) => setData('telegram_alert_cooldown', parseInt(e.target.value) || 5)}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Minimum interval between alerts (1-60 minutes). Alert will be sent immediately if condition type changes (e.g., Type 2 → Type 4).
                                    </p>
                                </div>
                            </div>
                        )}

                        <div className="flex gap-3">
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : 'Save Settings'}
                            </Button>
                            
                            {data.telegram_enabled && data.telegram_bot_token && data.telegram_chat_id && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleTest}
                                    disabled={testing}
                                >
                                    {testing ? 'Sending...' : 'Send Test'}
                                </Button>
                            )}
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Alert Types</CardTitle>
                    <CardDescription>
                        Automatic alerts based on environmental conditions
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-4">
                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-yellow-100 text-yellow-600">
                                1️⃣
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">THI &gt; 29</p>
                                <p className="text-sm font-semibold text-yellow-600">⚠️ PERINGATAN SUHU PANAS!</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Nilai THI terdeteksi lebih dari 29. Kondisi lingkungan sudah masuk kategori panas dan berpotensi menyebabkan stres panas. Segera lakukan pengecekan ventilasi atau pendinginan.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                                2️⃣
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">dB &gt; 85</p>
                                <p className="text-sm font-semibold text-orange-600">⚠️ PERINGATAN KEBISINGAN!</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Tingkat kebisingan melebihi 85 dB(A). Suara sudah berada di ambang batas yang dapat mengganggu kenyamanan dan kesehatan. Segera evaluasi sumber kebisingan.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-100 text-red-600">
                                3️⃣
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">dB &gt; 85 &amp; THI &gt; 29</p>
                                <p className="text-sm font-semibold text-red-600">🚨 PERINGATAN KRITIS!</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Kebisingan &gt; 85 dB(A) <strong>dan</strong> THI &gt; 29 (Kondisi Panas). Lingkungan dalam kondisi tidak nyaman dan berisiko. Segera lakukan tindakan pengendalian suhu dan kebisingan.
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-200 text-red-700">
                                4️⃣
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">dB &gt; 100</p>
                                <p className="text-sm font-semibold text-red-700">🚨 BAHAYA KEBISINGAN TINGGI!</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Tingkat kebisingan melebihi 100 dB(A). Berpotensi merusak pendengaran jika terpapar dalam waktu lama. Gunakan pelindung telinga dan periksa sumber suara segera!
                                </p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-red-300 text-red-900">
                                5️⃣
                            </div>
                            <div className="flex-1">
                                <p className="font-medium">dB &gt; 100 &amp; THI &gt; 29</p>
                                <p className="text-sm font-semibold text-red-900">🚨🚨 KONDISI DARURAT!</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    Kebisingan &gt; 100 dB(A) <strong>dan</strong> THI &gt; 29 (Suhu Ekstrem). Lingkungan sangat berbahaya dan tidak aman. Segera lakukan evakuasi atau tindakan pengamanan.
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Alert>
                <AlertDescription>
                    <p className="font-medium mb-2">Setup Instructions</p>
                    <ol className="list-decimal list-inside space-y-1 text-sm">
                        <li>Open Telegram and search for @BotFather</li>
                        <li>Send /newbot and follow the instructions</li>
                        <li>Copy the Bot Token provided</li>
                        <li>Search for @getidsbot and send /start</li>
                        <li>Copy your Chat ID</li>
                        <li>Paste both values above and enable notifications</li>
                    </ol>
                </AlertDescription>
            </Alert>
        </div>
    );
}
