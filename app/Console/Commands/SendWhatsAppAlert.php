<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\WhatsAppAlertService;
use Illuminate\Console\Command;

class SendWhatsAppAlert extends Command
{
    protected $signature = 'whatsapp:send-alert';
    protected $description = 'Check conditions and send WhatsApp alert notifications based on noise and THI thresholds';

    public function handle(WhatsAppAlertService $whatsapp): int
    {
        $devices = Device::with(['latestTelemetry'])
            ->where('is_active', true)
            ->where('whatsapp_enabled', true)
            ->get();

        if ($devices->isEmpty()) {
            $this->info('No active devices with WhatsApp enabled found');
            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($devices as $device) {
            // Skip if device is offline (last_seen_at > 60 minutes ago)
            if ($device->status === 'offline' || $device->status === 'never_connected') {
                $this->info("Skipping device {$device->name}: status is {$device->status}");
                continue;
            }

            $telemetry = $device->latestTelemetry;
            
            if (!$telemetry) {
                continue;
            }

            $noiseDb = $telemetry->noise_db ?? 0;
            $thi = $telemetry->thi ?? 0;

            // Always send notification (alert or status update) to device's configured numbers
            $sentCount = $whatsapp->checkAndSendAlert($device, $noiseDb, $thi);
            
            if ($sentCount > 0) {
                $totalSent += $sentCount;
                $this->info("Notification sent for device: {$device->name} to {$sentCount} number(s)");
            }
        }

        if ($totalSent > 0) {
            $this->info("Total {$totalSent} WhatsApp notification(s) sent");
        } else {
            $this->info("No notifications sent (outside working hours or no active devices)");
        }

        return self::SUCCESS;
    }
}
