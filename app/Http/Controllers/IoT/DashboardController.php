<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Telemetry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $devices = Device::with('latestTelemetry')
            ->orderBy('name')
            ->get()
            ->map(fn($device) => [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'location' => $device->location,
                'description' => $device->description,
                'is_active' => $device->is_active,
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'latest_telemetry' => $device->latestTelemetry ? [
                    'temperature' => $device->latestTelemetry->temperature,
                    'humidity' => $device->latestTelemetry->humidity,
                    'noise_db' => $device->latestTelemetry->noise_db,
                    'measured_at' => $device->latestTelemetry->measured_at->toIso8601String(),
                ] : null,
            ]);

        return Inertia::render('iot/dashboard', [
            'devices' => $devices,
            'newDevice' => fn () => session('newDevice'),
            'apiBaseUrl' => config('app.url') . '/api/v1',
        ]);
    }

    public function show(Device $device): Response
    {
        $device->load('latestTelemetry');

        // Get 24-hour chart data
        $chartData = $device->telemetries()
            ->last24Hours()
            ->orderBy('measured_at', 'asc')
            ->get()
            ->map(fn($t) => [
                'measured_at' => $t->measured_at->toIso8601String(),
                'temperature' => (float) $t->temperature,
                'humidity' => (float) $t->humidity,
                'noise_db' => (float) $t->noise_db,
            ]);

        return Inertia::render('iot/device-detail', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'location' => $device->location,
                'description' => $device->description,
                'is_active' => $device->is_active,
                'status' => $device->status,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'latest_telemetry' => $device->latestTelemetry ? [
                    'temperature' => $device->latestTelemetry->temperature,
                    'humidity' => $device->latestTelemetry->humidity,
                    'noise_db' => $device->latestTelemetry->noise_db,
                    'measured_at' => $device->latestTelemetry->measured_at->toIso8601String(),
                ] : null,
            ],
            'chartData' => $chartData,
            'apiBaseUrl' => config('app.url') . '/api/v1',
        ]);
    }

    public function telemetryLog(Request $request, Device $device): Response
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $device->telemetries()
            ->inDateRange($request->input('from'), $request->input('to'))
            ->orderBy('measured_at', 'desc');

        $telemetries = $query->paginate(50)->through(fn($t) => [
            'id' => $t->id,
            'temperature' => $t->temperature,
            'humidity' => $t->humidity,
            'noise_db' => $t->noise_db,
            'measured_at' => $t->measured_at->toIso8601String(),
        ]);

        return Inertia::render('iot/telemetry-log', [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ],
            'telemetries' => $telemetries,
            'filters' => [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        ]);
    }
}
