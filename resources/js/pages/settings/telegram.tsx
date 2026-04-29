import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Telegram Settings',
        href: '/settings/telegram',
    },
];

interface TelegramSettings {
    bot_token: string | null;
    chat_id: string | null;
    is_configured: boolean;
}

export default function Telegram() {
    const { telegram, flash } = usePage<{
        telegram: TelegramSettings;
        flash: { success?: string; error?: string };
    }>().props;

    const [testing, setTesting] = useState(false);

    const handleTest = () => {
        setTesting(true);
        router.post(
            '/settings/telegram/test',
            {},
            {
                preserveScroll: true,
                onFinish: () => setTesting(false),
            }
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Telegram Settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Telegram Notifications"
                        description="Configure Telegram bot for system notifications"
                    />

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
                            <CardTitle>Configuration Status</CardTitle>
                            <CardDescription>
                                Current Telegram bot configuration
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium">Bot Token</p>
                                    <p className="text-sm text-muted-foreground">
                                        {telegram.bot_token || 'Not configured'}
                                    </p>
                                </div>
                                <div
                                    className={`h-2 w-2 rounded-full ${
                                        telegram.bot_token
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    }`}
                                />
                            </div>

                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium">Chat ID</p>
                                    <p className="text-sm text-muted-foreground">
                                        {telegram.chat_id || 'Not configured'}
                                    </p>
                                </div>
                                <div
                                    className={`h-2 w-2 rounded-full ${
                                        telegram.chat_id
                                            ? 'bg-green-500'
                                            : 'bg-red-500'
                                    }`}
                                />
                            </div>

                            {telegram.is_configured && (
                                <div className="pt-4">
                                    <Button
                                        onClick={handleTest}
                                        disabled={testing}
                                        variant="outline"
                                    >
                                        {testing ? 'Sending...' : 'Send Test Notification'}
                                    </Button>
                                </div>
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

                    {!telegram.is_configured && (
                        <Alert>
                            <AlertDescription>
                                <p className="font-medium mb-2">Setup Required</p>
                                <p className="text-sm">
                                    To enable Telegram notifications, add the following to your .env file:
                                </p>
                                <pre className="mt-2 rounded bg-muted p-2 text-xs">
                                    TELEGRAM_BOT_TOKEN=your_bot_token_here{'\n'}
                                    TELEGRAM_CHAT_ID=your_chat_id_here
                                </pre>
                                <p className="mt-2 text-sm">
                                    See <code className="rounded bg-muted px-1">docs/TELEGRAM_SETUP.md</code> for detailed setup instructions.
                                </p>
                            </AlertDescription>
                        </Alert>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
