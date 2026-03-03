<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceTelegramController extends Controller
{
    public function update(Request $request, Device $device)
    {
        \Log::info('Telegram update request', [
            'device_id' => $device->id,
            'request_data' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'telegram_bot_token' => 'nullable|string',
            'telegram_chat_id' => 'nullable|string',
            'telegram_enabled' => 'nullable|boolean',
            'telegram_schedule_type' => 'nullable|string|in:working_hours,24_hours,custom',
            'telegram_schedule_hours' => 'nullable|array',
            'telegram_schedule_hours.*' => 'integer|min:0|max:23',
            'telegram_alert_cooldown' => 'nullable|integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        $updateData = [
            'telegram_bot_token' => $request->input('telegram_bot_token'),
            'telegram_chat_id' => $request->input('telegram_chat_id'),
            'telegram_enabled' => $request->input('telegram_enabled', false),
            'telegram_schedule_type' => $request->input('telegram_schedule_type', 'working_hours'),
            'telegram_schedule_hours' => $request->input('telegram_schedule_hours'),
            'telegram_alert_cooldown' => $request->input('telegram_alert_cooldown', 5),
        ];

        \Log::info('Updating device', ['update_data' => $updateData]);

        $device->update($updateData);

        \Log::info('Device updated successfully', [
            'device_id' => $device->id,
            'telegram_enabled' => $device->telegram_enabled,
        ]);

        return back()->with('success', 'Telegram settings updated successfully');
    }

    public function test(Request $request, Device $device, TelegramNotificationService $telegram)
    {
        if (!$device->telegram_enabled || empty($device->telegram_bot_token) || empty($device->telegram_chat_id)) {
            return back()->with('error', 'Telegram is not configured for this device');
        }

        $message = "🧪 <b>Test Notification</b>\n";
        $message .= "📍 Device: <b>{$device->name}</b>\n";
        $message .= "⏰ " . now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') . " WIB\n\n";
        $message .= "✅ Telegram notification is working correctly!";

        $success = $telegram->sendMessage($message, 'HTML', $device);

        if ($success) {
            return back()->with('success', 'Test notification sent successfully!');
        }

        return back()->with('error', 'Failed to send test notification. Check logs for details.');
    }
}
