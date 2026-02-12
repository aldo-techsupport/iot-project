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

        $alertCount = 0;

        foreach ($devices as $device) {
            $telemetry = $device->latestTelemetry;
            
            if (!$telemetry) {
                continue;
            }

            $noiseDb = $telemetry->noise_db ?? 0;
            $thi = $telemetry->thi ?? 0;

            // Check if alert conditions are met and send
            if ($telegram->checkAndSendAlert($device->name, $noiseDb, $thi, $device)) {
                $alertCount++;
                $this->info("Alert sent for device: {$device->name}");
            }
        }

        if ($alertCount > 0) {
            $this->info("Sent {$alertCount} alert notifications");
        } else {
            $this->info("No alert conditions met");
        }

        return self::SUCCESS;
    }
}
