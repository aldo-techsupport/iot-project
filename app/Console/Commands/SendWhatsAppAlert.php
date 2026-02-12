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

        $totalAlertsSent = 0;

        foreach ($devices as $device) {
            $telemetry = $device->latestTelemetry;
            
            if (!$telemetry) {
                continue;
            }

            $noiseDb = $telemetry->noise_db ?? 0;
            $thi = $telemetry->thi ?? 0;

            // Check if alert conditions are met and send to device's configured numbers
            $sentCount = $whatsapp->checkAndSendAlert($device, $noiseDb, $thi);
            
            if ($sentCount > 0) {
                $totalAlertsSent += $sentCount;
                $this->info("Alert sent for device: {$device->name} to {$sentCount} number(s)");
            }
        }

        if ($totalAlertsSent > 0) {
            $this->info("Total {$totalAlertsSent} WhatsApp alert(s) sent");
        } else {
            $this->info("No alert conditions met");
        }

        return self::SUCCESS;
    }
}
