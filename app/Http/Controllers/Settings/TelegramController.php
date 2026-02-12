<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelegramController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/telegram', [
            'telegram' => [
                'bot_token' => config('services.telegram.bot_token') ? '***' . substr(config('services.telegram.bot_token'), -8) : null,
                'chat_id' => config('services.telegram.chat_id'),
                'is_configured' => !empty(config('services.telegram.bot_token')) && !empty(config('services.telegram.chat_id')),
            ],
        ]);
    }

    public function test(Request $request, TelegramNotificationService $telegram)
    {
        if (empty(config('services.telegram.bot_token')) || empty(config('services.telegram.chat_id'))) {
            return back()->with('error', 'Telegram credentials not configured in .env file');
        }

        $message = "🧪 <b>Test Notification</b>\n";
        $message .= "⏰ " . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . " WIB\n\n";
        $message .= "✅ Telegram notification is working correctly!";

        $success = $telegram->sendMessage($message);

        if ($success) {
            return back()->with('success', 'Test notification sent successfully!');
        }

        return back()->with('error', 'Failed to send test notification. Check logs for details.');
    }
}
