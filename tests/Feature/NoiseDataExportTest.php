<?php

use App\Exports\NoiseDataExport;
use App\Models\Device;
use App\Models\NoiseFilteredData;
use App\Models\Telemetry;

function createExportDevice(): Device
{
    return Device::create([
        'name' => 'MONITORING NOISE',
        'device_key' => 'key-'.uniqid(),
        'device_key_hash' => hash('sha256', uniqid()),
        'location' => 'Lab',
        'is_active' => true,
    ]);
}

it('exports the persisted snapshot so admin edits match the modal', function () {
    $device = createExportDevice();
    $date = '2026-05-04';

    // Jittered telemetry as regenerated after an admin edit (noise ~99, not the edited value)
    for ($i = 0; $i < 12; $i++) {
        Telemetry::create([
            'device_id' => $device->id,
            'temperature' => 25.00,
            'humidity' => 50.00,
            'noise_db' => 99.00 + $i,
            'measured_at' => "{$date} 08:00:".str_pad((string) ($i * 5), 2, '0', STR_PAD_LEFT),
        ]);
    }

    // The exact admin-edited snapshot value shown in the modal
    NoiseFilteredData::create([
        'device_id' => $device->id,
        'period' => 'L1',
        'calculation_date' => $date,
        'noise_level' => 63.00,
        'temperature' => 29.60,
        'humidity' => 70.90,
        'measured_at' => "{$date} 08:00:01",
        'is_filled' => false,
        'fill_method' => 'actual',
        'slot_index' => 0,
    ]);

    $export = new NoiseDataExport($device->id, 'L1', $date, $device->name);

    $collection = $export->collection();
    expect($collection)->toHaveCount(1);

    $row = $export->map($collection->first());

    // Noise comes from the snapshot (63.00), NOT the jittered telemetry (99+)
    expect($row[2])->toBe('63.00');
    expect($row[3])->toBe('29.60');
    expect($row[4])->toBe('70.90');

    // THI matches the modal formula: 0.8*Ta + (RH*Ta)/500 = 27.88 (not the old 27.18)
    expect($row[5])->toBe('27.88');
});

it('falls back to live telemetry selection when no snapshot exists', function () {
    $device = createExportDevice();
    $date = '2026-05-04';

    Telemetry::create([
        'device_id' => $device->id,
        'temperature' => 30.00,
        'humidity' => 60.00,
        'noise_db' => 70.00,
        'measured_at' => "{$date} 08:00:00",
    ]);

    $export = new NoiseDataExport($device->id, 'L1', $date, $device->name);

    $collection = $export->collection();

    expect($collection)->not->toBeEmpty();
    expect($export->map($collection->first())[2])->toBe('70.00');
});
