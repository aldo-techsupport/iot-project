import { useState, useEffect } from 'react';
import { router, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import { Trash2, Plus } from 'lucide-react';
import type { DeviceDetail } from '@/types/iot';
import { Toaster } from "@/components/ui/sonner"
import { toast } from 'sonner';

interface Props {
    device: DeviceDetail;
}

export default function DeviceWhatsAppSettings({ device }: Props) {
    const { flash } = usePage<{ flash: { success?: string; error?: string } }>().props;
    const [testing, setTesting] = useState(false);
    const [testingNumber, setTestingNumber] = useState<string | null>(null);
    const [sendingTester, setSendingTester] = useState<string | null>(null);
    const [newNumber, setNewNumber] = useState('');
    const [addingNumber, setAddingNumber] = useState(false);

    const { data, setData, put, processing } = useForm({
        whatsapp_numbers: device.whatsapp_numbers || [],
        whatsapp_enabled: device.whatsapp_enabled || false,
    });

    // Sync form data with device prop when it changes
    useEffect(() => {
        setData({
            whatsapp_numbers: device.whatsapp_numbers || [],
            whatsapp_enabled: device.whatsapp_enabled || false,
        });
    }, [device.whatsapp_enabled, device.whatsapp_numbers]);

    const handleToggleChange = (checked: boolean) => {
        // Update local state
        setData('whatsapp_enabled', checked);
        
        // Send PUT request with updated data
        router.put(
            `/iot/devices/${device.id}/whatsapp`,
            {
                whatsapp_numbers: data.whatsapp_numbers,
                whatsapp_enabled: checked,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Reload device data to sync
                    router.reload({ only: ['device'] });
                },
            }
        );
    };

    const handleAddNumber = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (!newNumber.match(/^628[0-9]{8,12}$/)) {
            alert('Format nomor tidak valid. Gunakan format: 628xxx (tanpa +, spasi, atau tanda hubung)');
            return;
        }

        setAddingNumber(true);
        router.post(
            `/iot/devices/${device.id}/whatsapp/add`,
            { phone_number: newNumber },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewNumber('');
                    router.reload({ only: ['device'] });
                },
                onFinish: () => setAddingNumber(false),
            }
        );
    };

    const handleDeleteNumber = (phoneNumber: string) => {
        if (!confirm(`Delete number ${phoneNumber}?`)) {
            return;
        }

        router.post(
            `/iot/devices/${device.id}/whatsapp/delete`,
            { phone_number: phoneNumber },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['device'] });
                },
            }
        );
    };

    const handleTestNumber = (phoneNumber: string) => {
        if (testingNumber) return; // Prevent multiple simultaneous tests
        
        setTestingNumber(phoneNumber);
        router.post(
            `/iot/devices/${device.id}/whatsapp/test-number`,
            { phone_number: phoneNumber },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Test message sent to +${phoneNumber}!`);
                },
                onError: () => {
                    toast.error('Failed to send test message');
                },
                onFinish: () => setTestingNumber(null),
            }
        );
    };

    const handleSendTester = (phoneNumber: string) => {
        if (sendingTester) return; // Prevent multiple simultaneous sends
        
        setSendingTester(phoneNumber);
        router.post(
            `/iot/devices/${device.id}/whatsapp/send-tester`,
            { phone_number: phoneNumber },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`Tester notification sent to +${phoneNumber}!`);
                },
                onError: () => {
                    toast.error('Failed to send tester notification');
                },
                onFinish: () => setSendingTester(null),
            }
        );
    };

    const handleTestAll = () => {
        setTesting(true);
        router.post(
            `/iot/devices/${device.id}/whatsapp/test`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            }
        );
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
        <Toaster />
            <Card>
                <CardHeader>
                    <CardTitle>WhatsApp Configuration</CardTitle>
                    <CardDescription>
                        Configure WhatsApp numbers for this device's notifications
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Add Number Form */}
                    <div className="space-y-2">
                        <Label htmlFor="phone_number">Add Phone Number</Label>
                        <form onSubmit={handleAddNumber} className="flex gap-2">
                            <Input
                                id="phone_number"
                                type="text"
                                placeholder="628123456789"
                                value={newNumber}
                                onChange={(e) => setNewNumber(e.target.value)}
                                className="flex-1"
                            />
                            <Button type="submit" disabled={addingNumber || !newNumber}>
                                <Plus className="h-4 w-4 mr-2" />
                                {addingNumber ? 'Adding...' : 'Add'}
                            </Button>
                        </form>
                        <p className="text-xs text-muted-foreground">
                            Format: 628xxx (without +, spaces, or dashes)
                        </p>
                    </div>

                    {/* Numbers List */}
                    <div className="space-y-2">
                        <Label>Configured Numbers</Label>
                        {device.whatsapp_numbers && device.whatsapp_numbers.length > 0 ? (
                            <div className="space-y-2">
                                {device.whatsapp_numbers.map((number: string) => (
                                    <div
                                        key={number}
                                        className="flex items-center justify-between gap-2 rounded-lg border p-3"
                                    >
                                        <span className="font-mono text-sm flex-1">+{number}</span>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleTestNumber(number)}
                                                disabled={testingNumber !== null || sendingTester !== null}
                                            >
                                                {testingNumber === number ? 'Sending...' : 'Send Test Message'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                size="sm"
                                                onClick={() => handleSendTester(number)}
                                                disabled={testingNumber !== null || sendingTester !== null}
                                            >
                                                {sendingTester === number ? 'Sending...' : 'Test Notif'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDeleteNumber(number)}
                                                disabled={testingNumber !== null || sendingTester !== null}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No phone numbers configured yet
                            </p>
                        )}
                    </div>

                    {/* Enable Toggle */}
                    <div className="flex items-center justify-between rounded-lg border p-4">
                        <div className="space-y-0.5">
                            <Label htmlFor="whatsapp_enabled">Enable WhatsApp Notifications</Label>
                            <p className="text-sm text-muted-foreground">
                                Receive notifications for this device via WhatsApp
                            </p>
                        </div>
                        <Switch
                            id="whatsapp_enabled"
                            checked={data.whatsapp_enabled}
                            onCheckedChange={handleToggleChange}
                            disabled={processing}
                        />
                    </div>

                    {/* Test All Button */}
                    {data.whatsapp_enabled && device.whatsapp_numbers && device.whatsapp_numbers.length > 0 && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleTestAll}
                            disabled={testing}
                            className="w-full"
                        >
                            {testing ? 'Sending...' : 'Send Test Message to All Numbers'}
                        </Button>
                    )}
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
                                <p className="font-medium">dB(A) &gt; 85</p>
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
                                <p className="font-medium">dB(A) &gt; 85 &amp; THI &gt; 29</p>
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
                                <p className="font-medium">dB(A) &gt; 100</p>
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
                                <p className="font-medium">dB(A) &gt; 100 &amp; THI &gt; 29</p>
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
                        <li>Enter phone number in format 628xxx (Indonesian format without +)</li>
                        <li>Click Add to add the number to the list</li>
                        <li>You can add multiple numbers</li>
                        <li>Enable WhatsApp notifications</li>
                        <li>Click "Send Test Message" to verify</li>
                        <li>All configured numbers will receive alerts when conditions are met</li>
                    </ol>
                </AlertDescription>
            </Alert>
        </div>
    );
}
