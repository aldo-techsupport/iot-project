<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTelemetryRequest;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * Store telemetry data from IoT device
     * POST /api/v1/telemetry
     */
    public function store(StoreTelemetryRequest $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->get('authenticated_device');

        return $this->storeTelemetry($device, $request);
    }

    /**
     * Store telemetry data with device ID in URL
     * POST /api/v1/devices/{device}/telemetry
     */
    public function storeForDevice(StoreTelemetryRequest $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        // Verify the device ID matches the authenticated device
        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Device mismatch',
                'data' => null,
                'errors' => ['device' => 'The URL device ID does not match your authenticated device'],
            ], 403);
        }

        return $this->storeTelemetry($authenticatedDevice, $request);
    }

    private function storeTelemetry(Device $device, StoreTelemetryRequest $request): JsonResponse
    {
        $telemetry = $device->telemetries()->create([
            'temperature' => $request->input('temperature'),
            'humidity' => $request->input('humidity'),
            'noise_db' => $request->input('noise_db'),
            'measured_at' => $request->getMeasuredAt(),
        ]);

        $device->updateLastSeen();

        return response()->json([
            'success' => true,
            'message' => 'Telemetry data stored successfully',
            'data' => [
                'id' => $telemetry->id,
                'device_id' => $device->id,
                'temperature' => $telemetry->temperature,
                'humidity' => $telemetry->humidity,
                'noise_db' => $telemetry->noise_db,
                'measured_at' => $telemetry->measured_at->toIso8601String(),
            ],
            'errors' => null,
        ], 201);
    }

    /**
     * Get latest telemetry for a device
     * GET /api/v1/devices/{device}/latest
     */
    public function latest(Request $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        // Device can only access its own data
        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to device data',
                'data' => null,
                'errors' => ['device' => 'You can only access your own device data'],
            ], 403);
        }

        $telemetry = $device->latestTelemetry;

        if (!$telemetry) {
            return response()->json([
                'success' => true,
                'message' => 'No telemetry data found',
                'data' => null,
                'errors' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Latest telemetry retrieved',
            'data' => [
                'id' => $telemetry->id,
                'temperature' => $telemetry->temperature,
                'humidity' => $telemetry->humidity,
                'noise_db' => $telemetry->noise_db,
                'measured_at' => $telemetry->measured_at->toIso8601String(),
            ],
            'errors' => null,
        ]);
    }

    /**
     * Get telemetry history for a device
     * GET /api/v1/devices/{device}/history?from=...&to=...
     */
    public function history(Request $request, Device $device): JsonResponse
    {
        /** @var Device $authenticatedDevice */
        $authenticatedDevice = $request->get('authenticated_device');

        if ($authenticatedDevice->id !== $device->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to device data',
                'data' => null,
                'errors' => ['device' => 'You can only access your own device data'],
            ], 403);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $query = $device->telemetries()
            ->inDateRange($request->input('from'), $request->input('to'))
            ->orderBy('measured_at', 'desc');

        $limit = $request->input('limit', 100);
        $telemetries = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'message' => 'Telemetry history retrieved',
            'data' => [
                'device_id' => $device->id,
                'count' => $telemetries->count(),
                'telemetries' => $telemetries->map(fn($t) => [
                    'id' => $t->id,
                    'temperature' => $t->temperature,
                    'humidity' => $t->humidity,
                    'noise_db' => $t->noise_db,
                    'measured_at' => $t->measured_at->toIso8601String(),
                ]),
            ],
            'errors' => null,
        ]);
    }
}
