<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $deviceKey = Device::generateDeviceKey();

        $device = Device::create([
            'name' => $validated['name'],
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'device_key' => $deviceKey,
            'device_key_hash' => Device::hashDeviceKey($deviceKey),
            'is_active' => true,
        ]);

        return redirect()->back()
            ->with('newDevice', [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'device_key' => $deviceKey,
            ]);
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Only update fields that are present in the request
        $updateData = [];
        
        if (isset($validated['name'])) {
            $updateData['name'] = $validated['name'];
        }
        
        if (array_key_exists('location', $validated)) {
            $updateData['location'] = $validated['location'];
        }
        
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        
        if (isset($validated['is_active'])) {
            $updateData['is_active'] = $validated['is_active'];
        }

        $device->update($updateData);

        return redirect()->back()
            ->with('success', 'Device updated successfully');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $deviceName = $device->name;
        $device->telemetries()->delete();
        $device->delete();

        return redirect()->route('iot.dashboard')
            ->with('success', "Device '{$deviceName}' deleted successfully");
    }

    public function regenerateKey(Device $device): RedirectResponse
    {
        $newKey = Device::generateDeviceKey();

        $device->update([
            'device_key' => $newKey,
            'device_key_hash' => Device::hashDeviceKey($newKey),
        ]);

        return redirect()->route('iot.dashboard')
            ->with('newDevice', [
                'id' => $device->id,
                'name' => $device->name,
                'slug' => $device->slug,
                'device_key' => $newKey,
            ]);
    }
}
