<?php

namespace App\Http\Controllers\IoT;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\WhatsAppAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;


class DeviceWhatsAppController extends Controller
{
    public function update(Request $request, Device $device)
    {
        \Log::info('WhatsApp update request', [
            'device_id' => $device->id,
            'request_data' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'whatsapp_numbers' => 'nullable|array',
            'whatsapp_numbers.*' => 'string|regex:/^628[0-9]{8,12}$/',
            'whatsapp_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        $updateData = [
            'whatsapp_numbers' => $request->input('whatsapp_numbers', []),
            'whatsapp_enabled' => $request->input('whatsapp_enabled', false),
        ];

        \Log::info('Updating device', ['update_data' => $updateData]);

        $device->update($updateData);

        \Log::info('Device updated successfully', [
            'device_id' => $device->id,
            'whatsapp_enabled' => $device->whatsapp_enabled,
        ]);

        return back()->with('success', 'WhatsApp settings updated successfully');
    }

    public function addNumber(Request $request, Device $device)
    {
        \Log::info('WhatsApp addNumber request', [
            'device_id' => $device->id,
            'request_data' => $request->all(),
        ]);

        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|regex:/^628[0-9]{8,12}$/',
        ]);

        if ($validator->fails()) {
            \Log::error('Validation failed', ['errors' => $validator->errors()]);
            return back()->withErrors($validator)->withInput();
        }

        $phoneNumber = $request->input('phone_number');
        $numbers = $device->whatsapp_numbers ?? [];

        \Log::info('Current numbers', ['numbers' => $numbers]);

        // Check if number already exists
        if (in_array($phoneNumber, $numbers)) {
            \Log::warning('Number already exists', ['phone_number' => $phoneNumber]);
            return back()->with('error', 'Phone number already exists');
        }

        $numbers[] = $phoneNumber;
        
        \Log::info('Updating device with new numbers', ['numbers' => $numbers]);
        
        $device->update(['whatsapp_numbers' => $numbers]);

        \Log::info('Device updated successfully', [
            'device_id' => $device->id,
            'whatsapp_numbers' => $device->whatsapp_numbers,
        ]);

        return back()->with('success', 'Phone number added successfully');
    }

    public function deleteNumber(Request $request, Device $device)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $phoneNumber = $request->input('phone_number');
        $numbers = $device->whatsapp_numbers ?? [];

        // Remove the number
        $numbers = array_values(array_filter($numbers, fn($num) => $num !== $phoneNumber));
        $device->update(['whatsapp_numbers' => $numbers]);

        return back()->with('success', 'Phone number deleted successfully');
    }

    public function test(Request $request, Device $device, WhatsAppAlertService $whatsapp)
    {
        Artisan::call('whatsapp:send-alert');
        
        return back()->with('success', 'WhatsApp alert command executed successfully!');
    }

    public function testNumber(Request $request, Device $device, WhatsAppAlertService $whatsapp)
    {
        Artisan::call('whatsapp:send-alert');
        
        return back()->with('success', 'WhatsApp alert command executed successfully!');
    }
}
