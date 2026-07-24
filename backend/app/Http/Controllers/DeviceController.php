<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DeviceController extends Controller
{
    /**
     * List all devices.
     */
    public function index()
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => \App\Models\Device::all()
        ]);
    }

    /**
     * Store live scale reading from Local Scale Agent.
     */
    public function storeReading(Request $request)
    {
        $request->validate([
            'workstation_id' => 'required|string|max:50',
            'weight' => 'required|numeric',
            // Không bắt buộc để tương thích ngược với Agent cũ chưa cập nhật (nếu có) —
            // mặc định false (KHÔNG mặc định true, tránh lặp lại bug hard-code true cũ).
            'is_stable' => 'nullable|boolean',
        ]);

        $workstationId = $request->input('workstation_id');
        $weight = $request->input('weight');
        $isStable = $request->boolean('is_stable', false);

        // PB-2 (đã sửa 2026-07-17): cache kèm is_stable thật từ ScaleReader.StableFilter
        // (Agent), thay vì chỉ cache weight rồi để frontend tự hard-code true.
        Cache::put("scale_live_weight_{$workstationId}", $weight, 15);
        Cache::put("scale_live_weight_stable_{$workstationId}", $isStable, 15);
        Cache::put("scale_live_weight_timestamp_{$workstationId}", time(), 3600);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Scale reading cached successfully'
        ]);
    }

    /**
     * Get live scale reading for Web App.
     */
    public function getReading($workstationId)
    {
        $weight = Cache::get("scale_live_weight_{$workstationId}", 0.0);
        $isStable = Cache::get("scale_live_weight_stable_{$workstationId}", false);

        return response()->json([
            'status' => 'SUCCESS',
            'workstation_id' => $workstationId,
            'weight' => (float)$weight,
            'is_stable' => (bool)$isStable,
        ]);
    }
}
