<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramNotificationService
{
    protected string $botToken;
    protected string $chatId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->chatId = config('services.telegram.chat_id');
        $this->baseUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    public function sendMessage(string $message, ?string $parseMode = 'HTML', ?Device $device = null): bool
    {
        // If device is provided, use device-specific credentials
        if ($device && $device->telegram_enabled) {
            $botToken = $device->telegram_bot_token;
            $chatId = $device->telegram_chat_id;
        } else {
            // Fallback to global credentials
            $botToken = $this->botToken;
            $chatId = $this->chatId;
        }

        if (empty($botToken) || empty($chatId)) {
            Log::warning('Telegram credentials not configured', [
                'device_id' => $device?->id,
                'device_name' => $device?->name,
            ]);
            return false;
        }

        try {
            $baseUrl = "https://api.telegram.org/bot{$botToken}";
            $response = Http::post("{$baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->successful()) {
                Log::info('Telegram notification sent successfully', [
                    'device_id' => $device?->id,
                    'device_name' => $device?->name,
                ]);
                return true;
            }

            Log::error('Failed to send Telegram notification', [
                'device_id' => $device?->id,
                'device_name' => $device?->name,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Telegram notification error: ' . $e->getMessage(), [
                'device_id' => $device?->id,
                'device_name' => $device?->name,
            ]);
            return false;
        }
    }

    /**
     * Check conditions and send appropriate alert
     */
    public function checkAndSendAlert(string $deviceName, float $noiseDb, float $thi, Device $device): bool
    {
        // Check if current time is within device's configured schedule
        if (!$this->isWithinSchedule($device)) {
            Log::info('Outside configured schedule, skipping alert', [
                'device' => $deviceName,
                'schedule_type' => $device->telegram_schedule_type,
                'current_hour' => now()->timezone('Asia/Jakarta')->format('H'),
            ]);
            return false;
        }

        $alertType = $this->determineAlertType($noiseDb, $thi);
        
        // Always send notification (either alert or status update)
        $message = $this->formatAlertMessage($deviceName, $noiseDb, $thi, $alertType);
        return $this->sendMessage($message, 'HTML', $device);
    }

    /**
     * Check if current time is within device's configured schedule
     */
    private function isWithinSchedule(Device $device): bool
    {
        $now = now()->timezone('Asia/Jakarta');
        $currentHour = (int) $now->format('H');
        
        $scheduleType = $device->telegram_schedule_type ?? 'working_hours';
        
        switch ($scheduleType) {
            case '24_hours':
                // Always send, 24/7
                return true;
                
            case 'custom':
                // Check if current hour is in custom hours array
                $customHours = $device->telegram_schedule_hours ?? [];
                return in_array($currentHour, $customHours);
                
            case 'working_hours':
            default:
                // Working hours: 08:00 - 17:00 (8 AM to 5 PM)
                return $currentHour >= 8 && $currentHour < 17;
        }
    }

    /**
     * Check if current time is within working hours (08:00 - 17:00 WIB)
     * @deprecated Use isWithinSchedule() instead
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
     * Public method to get alert type (for external use)
     */
    public function getAlertType(float $noiseDb, float $thi): ?int
    {
        return $this->determineAlertType($noiseDb, $thi);
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
            $message = "✅ <b>STATUS MONITORING</b>\n\n";
            $message .= "📊 <b>Kondisi Normal</b>\n\n";
            $message .= "Semua parameter dalam batas aman.\n";
            $message .= "Tidak ada peringatan yang perlu ditindaklanjuti.\n\n";
        } else {
            switch ($alertType) {
                case 1:
                    $message = "1️⃣ <b>THI &gt; 29</b>\n\n";
                    $message .= "⚠️ <b>PERINGATAN SUHU PANAS!</b>\n\n";
                    $message .= "Nilai THI terdeteksi lebih dari 29.\n";
                    $message .= "Kondisi lingkungan sudah masuk kategori panas dan berpotensi menyebabkan stres panas.\n\n";
                    $message .= "Segera lakukan pengecekan ventilasi atau pendinginan.\n\n";
                    break;

                case 2:
                    $message = "2️⃣ <b>dB &gt; 85</b>\n\n";
                    $message .= "⚠️ <b>PERINGATAN KEBISINGAN!</b>\n\n";
                    $message .= "Tingkat kebisingan melebihi 85 dB.\n";
                    $message .= "Suara sudah berada di ambang batas yang dapat mengganggu kenyamanan dan kesehatan.\n\n";
                    $message .= "Segera evaluasi sumber kebisingan.\n\n";
                    break;

                case 3:
                    $message = "3️⃣ <b>dB &gt; 85 &amp; THI &gt; 29</b>\n\n";
                    $message .= "🚨 <b>PERINGATAN KRITIS!</b>\n\n";
                    $message .= "Kebisingan &gt; 85 dB\n";
                    $message .= "<b>dan</b>\n";
                    $message .= "THI &gt; 29 (Kondisi Panas)\n\n";
                    $message .= "Lingkungan dalam kondisi tidak nyaman dan berisiko.\n\n";
                    $message .= "Segera lakukan tindakan pengendalian suhu dan kebisingan.\n\n";
                    break;

                case 4:
                    $message = "4️⃣ <b>dB &gt; 100</b>\n\n";
                    $message .= "🚨 <b>BAHAYA KEBISINGAN TINGGI!</b>\n\n";
                    $message .= "Tingkat kebisingan melebihi 100 dB.\n";
                    $message .= "Berpotensi merusak pendengaran jika terpapar dalam waktu lama.\n\n";
                    $message .= "Gunakan pelindung telinga dan periksa sumber suara segera!\n\n";
                    break;

                case 5:
                    $message = "5️⃣ <b>dB &gt; 100 &amp; THI &gt; 29</b>\n\n";
                    $message .= "🚨🚨 <b>KONDISI DARURAT!</b>\n\n";
                    $message .= "Kebisingan &gt; 100 dB\n";
                    $message .= "<b>dan</b>\n";
                    $message .= "THI &gt; 29 (Suhu Ekstrem)\n\n";
                    $message .= "Lingkungan sangat berbahaya dan tidak aman.\n\n";
                    $message .= "Segera lakukan evakuasi atau tindakan pengamanan.\n\n";
                    break;
            }
        }

        $message .= "📍 Device: <b>{$deviceName}</b>\n";
        $message .= "⏰ {$timestamp} WIB\n\n";
        $message .= "📊 <b>Data Saat Ini:</b>\n";
        $message .= "   🔊 Noise: " . number_format($noiseDb, 2) . " dB\n";
        $message .= "   🌡️ THI: " . number_format($thi, 2) . "\n";

        return $message;
    }
}
