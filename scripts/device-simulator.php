#!/usr/bin/env php
<?php

/**
 * IoT Device Simulator
 * 
 * Simulasi pengiriman data sensor ke API secara realtime.
 * Jalankan: php scripts/device-simulator.php
 */

$baseUrl = getenv('API_URL') ?: 'http://localhost:8000';
$deviceKeys = [
    'dev_server_room_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    'dev_warehouse_bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
];

$intervalSeconds = (int)(getenv('INTERVAL') ?: 5);

echo "🚀 IoT Device Simulator Started\n";
echo "   API URL: {$baseUrl}\n";
echo "   Interval: {$intervalSeconds} seconds\n";
echo "   Devices: " . count($deviceKeys) . "\n";
echo str_repeat("-", 50) . "\n\n";

// Base values untuk setiap device
$deviceStates = [
    0 => ['temp' => 22, 'humidity' => 45, 'noise' => 55], // Server Room
    1 => ['temp' => 28, 'humidity' => 65, 'noise' => 35], // Warehouse
];

function generateTelemetry(array $baseState): array
{
    $hour = (int)date('H');
    $dayVariation = sin(($hour - 6) * M_PI / 12) * 3;
    
    return [
        'temperature' => round($baseState['temp'] + $dayVariation + (rand(-20, 20) / 10), 2),
        'humidity' => round($baseState['humidity'] + (rand(-50, 50) / 10), 2),
        'noise_db' => round($baseState['noise'] + ($hour >= 8 && $hour <= 18 ? 10 : 0) + (rand(-30, 30) / 10), 2),
    ];
}

function sendTelemetry(string $baseUrl, string $deviceKey, array $data): array
{
    $ch = curl_init("{$baseUrl}/api/v1/telemetry");
    
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Device-Key: ' . $deviceKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $httpCode === 201,
        'code' => $httpCode,
        'response' => $response,
        'error' => $error,
    ];
}

$iteration = 0;

while (true) {
    $iteration++;
    $timestamp = date('Y-m-d H:i:s');
    
    echo "[{$timestamp}] Iteration #{$iteration}\n";
    
    foreach ($deviceKeys as $index => $deviceKey) {
        $deviceName = $index === 0 ? 'Server Room' : 'Warehouse';
        $telemetry = generateTelemetry($deviceStates[$index]);
        
        $result = sendTelemetry($baseUrl, $deviceKey, $telemetry);
        
        if ($result['success']) {
            echo "  ✅ {$deviceName}: {$telemetry['temperature']}°C, {$telemetry['humidity']}%, {$telemetry['noise_db']}dB\n";
        } else {
            echo "  ❌ {$deviceName}: HTTP {$result['code']} - {$result['error']}\n";
        }
    }
    
    echo "\n";
    sleep($intervalSeconds);
}
