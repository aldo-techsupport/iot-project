<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ThiCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThiController extends Controller
{
    public function getThiByDate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'date' => 'required|date',
            'group_by' => 'string|in:interval,hour',
        ]);

        $deviceId = $validated['device_id'];
        $date = $validated['date'];
        $groupBy = $validated['group_by'] ?? 'hour';

        try {
            if ($groupBy === 'hour') {
                $data = ThiCalculationService::getThiDataByHour($deviceId, $date);
            } else {
                $data = ThiCalculationService::getThiDataByDate($deviceId, $date);
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch THI data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
