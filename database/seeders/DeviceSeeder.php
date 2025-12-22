<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\Telemetry;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        // Create 2 dummy devices with known keys for testing
        $devices = [
            [
                'name' => 'Sensor Ruang Server',
                'location' => 'Gedung A - Lantai 2',
                'description' => 'Monitoring suhu dan kelembapan ruang server utama',
                'device_key' => 'dev_server_room_' . str_repeat('a', 48), // 64 chars total
            ],
            [
                'name' => 'Sensor Gudang',
                'location' => 'Gedung B - Basement',
                'description' => 'Monitoring kondisi gudang penyimpanan',
                'device_key' => 'dev_warehouse_' . str_repeat('b', 50), // 64 chars total
            ],
        ];

        foreach ($devices as $deviceData) {
            $deviceKey = $deviceData['device_key'];
            unset($deviceData['device_key']);

            $device = Device::create([
                ...$deviceData,
                'device_key' => $deviceKey,
                'device_key_hash' => Device::hashDeviceKey($deviceKey),
                'is_active' => true,
                'last_seen_at' => now(),
            ]);

            // Generate 48 hours of telemetry data (every 30 minutes)
            $this->generateTelemetryData($device);
        }

        $this->command->info('Created 2 devices with sample telemetry data');
        $this->command->newLine();
        $this->command->info('Device Keys for testing:');
        $this->command->info('1. Sensor Ruang Server: dev_server_room_' . str_repeat('a', 48));
        $this->command->info('2. Sensor Gudang: dev_warehouse_' . str_repeat('b', 50));
    }

    private function generateTelemetryData(Device $device): void
    {
        $startTime = Carbon::now()->subHours(48);
        $telemetries = [];

        // Base values for realistic simulation
        $baseTemp = $device->name === 'Sensor Ruang Server' ? 22 : 28;
        $baseHumidity = $device->name === 'Sensor Ruang Server' ? 45 : 65;
        $baseNoise = $device->name === 'Sensor Ruang Server' ? 55 : 35;

        for ($i = 0; $i < 96; $i++) { // 48 hours * 2 readings per hour
            $time = $startTime->copy()->addMinutes($i * 30);
            
            // Add some variation based on time of day
            $hourOfDay = $time->hour;
            $dayVariation = sin(($hourOfDay - 6) * M_PI / 12) * 3; // Peak at noon
            
            $telemetries[] = [
                'device_id' => $device->id,
                'temperature' => round($baseTemp + $dayVariation + (rand(-20, 20) / 10), 2),
                'humidity' => round($baseHumidity + (rand(-50, 50) / 10), 2),
                'noise_db' => round($baseNoise + ($hourOfDay >= 8 && $hourOfDay <= 18 ? 10 : 0) + (rand(-30, 30) / 10), 2),
                'measured_at' => $time,
                'created_at' => $time,
                'updated_at' => $time,
            ];
        }

        Telemetry::insert($telemetries);
    }
}
