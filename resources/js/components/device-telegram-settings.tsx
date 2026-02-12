import { useState } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
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
                                    Tingkat kebisingan melebihi 85 dB. Suara sudah berada di ambang batas yang dapat mengganggu kenyamanan dan kesehatan. Segera evaluasi sumber kebisingan.
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
                                    Kebisingan &gt; 85 dB <strong>dan</strong> THI &gt; 29 (Kondisi Panas). Lingkungan dalam kondisi tidak nyaman dan berisiko. Segera lakukan tindakan pengendalian suhu dan kebisingan.
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
                                    Tingkat kebisingan melebihi 100 dB. Berpotensi merusak pendengaran jika terpapar dalam waktu lama. Gunakan pelindung telinga dan periksa sumber suara segera!
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
                                    Kebisingan &gt; 100 dB <strong>dan</strong> THI &gt; 29 (Suhu Ekstrem). Lingkungan sangat berbahaya dan tidak aman. Segera lakukan evakuasi atau tindakan pengamanan.
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
