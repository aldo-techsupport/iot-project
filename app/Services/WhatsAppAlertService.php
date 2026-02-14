<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

class WhatsAppAlertService
{
    protected WhatsAppGatewayService $gateway;
    protected string $session;

    public function __construct()
    {
        try {
            $this->gateway = WhatsAppGatewayService::make();
            $this->session = env('WA_SESSION_NAME', 'default');
        } catch (\Exception $e) {
            Log::warning('WhatsApp Gateway not configured: ' . $e->getMessage());
        }
    }

    public function sendMessage(string $message, ?string $phoneNumber = null): bool
    {
        if (!isset($this->gateway)) {
            Log::warning('WhatsApp Gateway not initialized');
            return false;
        }

        if (empty($phoneNumber)) {
            Log::warning('WhatsApp number not provided');
            return false;
        }

        try {
            $this->gateway->sendTextMessage(
                session: $this->session,
                to: $phoneNumber,
                text: $message,
                isGroup: false
            );

            Log::info('WhatsApp alert sent successfully', [
                'phone' => $phoneNumber,
            ]);

            return true;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('WhatsApp Gateway connection failed (DNS/Network issue): ' . $e->getMessage(), [
                'phone' => $phoneNumber,
                'gateway_url' => env('WA_GATEWAY_URL'),
            ]);
            return false;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('WhatsApp Gateway request failed: ' . $e->getMessage(), [
                'phone' => $phoneNumber,
                'status_code' => $e->response?->status(),
                'response' => $e->response?->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp alert: ' . $e->getMessage(), [
                'phone' => $phoneNumber,
                'exception_type' => get_class($e),
            ]);
            return false;
        }
    }

    /**
     * Send alert to device's configured WhatsApp numbers
     */
    public function sendToDevice(Device $device, string $message): int
    {
        if (!$device->whatsapp_enabled || empty($device->whatsapp_numbers)) {
            return 0;
        }

        $sentCount = 0;
        foreach ($device->whatsapp_numbers as $phoneNumber) {
            if ($this->sendMessage($message, $phoneNumber)) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Check conditions and send appropriate alert to device
     */
    public function checkAndSendAlert(Device $device, float $noiseDb, float $thi): int
    {
        if (!$device->whatsapp_enabled || empty($device->whatsapp_numbers)) {
            return 0;
        }

        // Check if current time is within working hours (08:00 - 17:00 WIB)
        if (!$this->isWorkingHours()) {
            Log::info('Outside working hours, skipping alert', [
                'device' => $device->name,
                'time' => now()->timezone('Asia/Jakarta')->format('H:i:s'),
            ]);
            return 0;
        }

        $alertType = $this->determineAlertType($noiseDb, $thi);
        
        // Always send notification (either alert or status update)
        $message = $this->formatAlertMessage($device->name, $noiseDb, $thi, $alertType);
        return $this->sendToDevice($device, $message);
    }

    /**
     * Check if current time is within working hours (08:00 - 17:00 WIB)
     */
    private function isWorkingHours(): bool
    {
        $now = now()->timezone('Asia/Jakarta');
        $hour = (int) $now->format('H');
        
        // Working hours: 08:00 - 17:00 (8 AM to 5 PM)
        return $hour >= 8 && $hour < 17;
    }

    /**
     * Determine alert type based on conditions
     */
    private function determineAlertType(float $noiseDb, float $thi): ?int
    {
        // Priority order: check most critical conditions first
        
        // Type 5: dB > 100 & THI > 29 (KONDISI DARURAT)
        if ($noiseDb > 100 && $thi > 29) {
            return 5;
        }
        
        // Type 4: dB > 100 (BAHAYA KEBISINGAN TINGGI)
        if ($noiseDb > 100) {
            return 4;
        }
        
        // Type 3: dB > 85 & THI > 29 (PERINGATAN KRITIS)
        if ($noiseDb > 85 && $thi > 29) {
            return 3;
        }
        
        // Type 2: dB > 85 (PERINGATAN KEBISINGAN)
        if ($noiseDb > 85) {
            return 2;
        }
        
        // Type 1: THI > 29 (PERINGATAN SUHU PANAS)
        if ($thi > 29) {
            return 1;
        }
        
        return null; // No alert condition met
    }

    /**
     * Format alert message based on type
     */
    private function formatAlertMessage(string $deviceName, float $noiseDb, float $thi, ?int $alertType): string
    {
        $timestamp = now()->timezone('Asia/Jakarta')->format('d/m/Y H:i:s');
        $message = "";

        // If no alert condition, send status update
        if ($alertType === null) {
            $message = "✅ *STATUS MONITORING*\n\n";
            $message .= "📊 *Kondisi Normal*\n\n";
            $message .= "Semua parameter dalam batas aman.\n";
            $message .= "Tidak ada peringatan yang perlu ditindaklanjuti.\n\n";
        } else {
            switch ($alertType) {
                case 1:
                    $message = "1️⃣ *THI > 29*\n\n";
                    $message .= "⚠️ *PERINGATAN SUHU PANAS!*\n\n";
                    $message .= "Nilai THI terdeteksi lebih dari 29.\n";
                    $message .= "Kondisi lingkungan sudah masuk kategori panas dan berpotensi menyebabkan stres panas.\n\n";
                    $message .= "Segera lakukan pengecekan ventilasi atau pendinginan.\n\n";
                    break;

                case 2:
                    $message = "2️⃣ *dB > 85*\n\n";
                    $message .= "⚠️ *PERINGATAN KEBISINGAN!*\n\n";
                    $message .= "Tingkat kebisingan melebihi 85 dB.\n";
                    $message .= "Suara sudah berada di ambang batas yang dapat mengganggu kenyamanan dan kesehatan.\n\n";
                    $message .= "Segera evaluasi sumber kebisingan.\n\n";
                    break;

                case 3:
                    $message = "3️⃣ *dB > 85 & THI > 29*\n\n";
                    $message .= "🚨 *PERINGATAN KRITIS!*\n\n";
                    $message .= "Kebisingan > 85 dB\n";
                    $message .= "*dan*\n";
                    $message .= "THI > 29 (Kondisi Panas)\n\n";
                    $message .= "Lingkungan dalam kondisi tidak nyaman dan berisiko.\n\n";
                    $message .= "Segera lakukan tindakan pengendalian suhu dan kebisingan.\n\n";
                    break;

                case 4:
                    $message = "4️⃣ *dB > 100*\n\n";
                    $message .= "🚨 *BAHAYA KEBISINGAN TINGGI!*\n\n";
                    $message .= "Tingkat kebisingan melebihi 100 dB.\n";
                    $message .= "Berpotensi merusak pendengaran jika terpapar dalam waktu lama.\n\n";
                    $message .= "Gunakan pelindung telinga dan periksa sumber suara segera!\n\n";
                    break;

                case 5:
                    $message = "5️⃣ *dB > 100 & THI > 29*\n\n";
                    $message .= "🚨🚨 *KONDISI DARURAT!*\n\n";
                    $message .= "Kebisingan > 100 dB\n";
                    $message .= "*dan*\n";
                    $message .= "THI > 29 (Suhu Ekstrem)\n\n";
                    $message .= "Lingkungan sangat berbahaya dan tidak aman.\n\n";
                    $message .= "Segera lakukan evakuasi atau tindakan pengamanan.\n\n";
                    break;
            }
        }

        $message .= "📍 Device: *{$deviceName}*\n";
        $message .= "⏰ {$timestamp} WIB\n\n";
        $message .= "📊 *Data Saat Ini:*\n";
        $message .= "   🔊 Noise: " . number_format($noiseDb, 2) . " dB\n";
        $message .= "   🌡️ THI: " . number_format($thi, 2) . "\n";

        return $message;
    }
}
