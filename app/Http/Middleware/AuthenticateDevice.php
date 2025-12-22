<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $deviceKey = $this->extractDeviceKey($request);

        if (!$deviceKey) {
            return response()->json([
                'success' => false,
                'message' => 'Device key is required',
                'data' => null,
                'errors' => ['device_key' => 'Missing device_key header or Bearer token'],
            ], 401);
        }

        $device = Device::findByDeviceKey($deviceKey);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid device key',
                'data' => null,
                'errors' => ['device_key' => 'Device not found or key is invalid'],
            ], 401);
        }

        if (!$device->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Device is deactivated',
                'data' => null,
                'errors' => ['device' => 'This device has been deactivated'],
            ], 403);
        }

        $request->merge(['authenticated_device' => $device]);

        return $next($request);
    }

    private function extractDeviceKey(Request $request): ?string
    {
        // Check X-Device-Key header first
        if ($key = $request->header('X-Device-Key')) {
            return $key;
        }

        // Check Bearer token
        if ($bearer = $request->bearerToken()) {
            return $bearer;
        }

        return null;
    }
}
