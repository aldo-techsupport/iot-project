<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class SendTelegramAlert extends Command
{
    protected $signature = 'telegram:send-alert';
    protected $description = 'Check conditions and send alert notifications based on noise and THI thresholds';

    public function handle(TelegramNotificationService $telegram): int
    {
        $devices = Device::with(['latestTelemetry'])
            ->where('is_active', true)
            ->where('telegram_enabled', true)
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No devices with Telegram enabled');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($devices as $device) {
            $telemetry = $device->latestTelemetry;
            
            if (!$telemetry) {
                continue;
            }

            $noiseDb = $telemetry->noise_db ?? 0;
            $thi = $telemetry->thi ?? 0;

            // Always send notification (alert or status update)
            if ($telegram->checkAndSendAlert($device->name, $noiseDb, $thi, $device)) {
                $sentCount++;
                $this->info("Notification sent for device: {$device->name}");
            }
        }

        if ($sentCount > 0) {
            $this->info("Sent {$sentCount} notification(s)");
        } else {
            $this->info("No notifications sent (outside working hours or no active devices)");
        }

        return self::SUCCESS;
    }
}
