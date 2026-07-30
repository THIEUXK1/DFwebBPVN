<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\OperationClient;
use App\Models\Device;
use App\Models\OperationClientDevice;

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

        // Tu dong gan thiet bi Can cho tram neu chua co (2026-07-30, "cai la dung thoi"):
        // truoc day nguoi dung phai tu bam "Cau hinh can ngay" tren UI (QrScanPanel.vue)
        // truoc khi xem duoc so can, du Agent da bao cao so that roi. Agent gui duoc so
        // can that qua day la du bang chung de tu tao/gan Device — giu nguyen co che
        // Device/OperationClientDevice hien co (giong WorkstationLocalConfigController)
        // thay vi bo qua han buoc gan thiet bi.
        // Chi tra theo code (Agent luon gui Workstation:Id dang chuoi ma tram, vd
        // "WS-WEIGH-SCALE") — KHONG orWhere('id', ...) vi id la cot bigint, Postgres loi
        // ngay "invalid input syntax for type bigint" khi so sanh voi chuoi khong phai so.
        $client = OperationClient::where('code', $workstationId)->first();
        if ($client) {
            $hasScale = OperationClientDevice::where('operation_client_id', $client->id)
                ->where('device_role', 'PRIMARY_SCALE')
                ->exists();

            if (!$hasScale) {
                $device = Device::firstOrCreate(
                    ['code' => "SCALE_{$workstationId}"],
                    ['device_type' => 'SCALE', 'status' => 'ACTIVE']
                );

                OperationClientDevice::create([
                    'operation_client_id' => $client->id,
                    'device_id' => $device->id,
                    'device_role' => 'PRIMARY_SCALE',
                    'is_default' => true,
                    'priority' => 1,
                    'enabled' => true,
                ]);
            }
        }

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
