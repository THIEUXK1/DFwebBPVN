<?php
// backend/app/Http/Controllers/WorkstationLocalConfigController.php
//
// Cấu hình thiết bị (cân/máy in) TẠI CHỖ, ngay trên màn hình vận hành — theo yêu cầu
// đơn giản hóa 2026-07-18: "máy nào cần in thì thiết lập máy in, máy nào cần cân thì
// thiết lập kết nối cân" ngay trên giao diện, không qua đăng ký/Admin trước. Khác
// OperationClientAdminController::updateConfig (role:ADMIN, sửa được MỌI thứ của
// TRẠM BẤT KỲ: tên, capability, quyền, route...) — controller này CHỈ cho phép người
// dùng đã đăng nhập (vai trò bất kỳ) tự gán/sửa cân+máy in cho ĐÚNG trạm họ đang đứng,
// không đụng tới capability/quyền/route — phạm vi hẹp nên không cần chặn theo role,
// giống cách vận hành VBA gốc (mỗi máy tự có 1 dòng cấu hình COM port/tên máy in cục
// bộ trong workbook, ai ngồi máy đó chỉnh được, không qua phê duyệt).

namespace App\Http\Controllers;

use App\Models\OperationClient;
use App\Models\Device;
use App\Models\OperationClientDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkstationLocalConfigController extends Controller
{
    public function updateDeviceConfig(Request $request, $id)
    {
        $request->validate([
            'scale_device_id' => 'sometimes|nullable|string|max:100',
            'scale_com_port' => 'sometimes|nullable|string|max:50',
            'printer_device_id' => 'sometimes|nullable|string|max:100',
            'printer_connection_type' => 'sometimes|nullable|string|in:USB,LAN',
            'printer_address' => 'sometimes|nullable|string|max:200',
        ]);

        $client = OperationClient::findOrFail($id);

        DB::transaction(function () use ($request, $client) {
            if ($request->filled('scale_device_id')) {
                $device = Device::firstOrCreate(
                    ['code' => $request->input('scale_device_id')],
                    ['device_type' => 'SCALE', 'status' => 'ACTIVE']
                );

                if ($request->filled('scale_com_port')) {
                    $config = $device->configuration ?? [];
                    $config['com_port'] = $request->input('scale_com_port');
                    $device->configuration = $config;
                    $device->save();
                }

                $this->assignPrimaryDevice($client->id, $device->id, 'PRIMARY_SCALE');
            }

            if ($request->filled('printer_device_id')) {
                $device = Device::firstOrCreate(
                    ['code' => $request->input('printer_device_id')],
                    ['device_type' => 'PRINTER', 'status' => 'ACTIVE']
                );

                $config = $device->configuration ?? [];
                if ($request->filled('printer_connection_type')) {
                    $config['connection_type'] = $request->input('printer_connection_type');
                }
                if ($request->filled('printer_address')) {
                    $config['address'] = $request->input('printer_address');
                }
                if (!empty($config)) {
                    $device->configuration = $config;
                    $device->save();
                }

                $this->assignPrimaryDevice($client->id, $device->id, 'PRIMARY_PRINTER');
            }
        });

        $client = OperationClient::with('devices')->findOrFail($id);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã lưu cấu hình thiết bị cho trạm này.',
            'data' => [
                'assigned_scale_device_id' => $client->devices->firstWhere('pivot.device_role', 'PRIMARY_SCALE')?->code,
                'assigned_printer_device_id' => $client->devices->firstWhere('pivot.device_role', 'PRIMARY_PRINTER')?->code,
            ],
        ]);
    }

    private function assignPrimaryDevice(int $operationClientId, string $deviceId, string $role): void
    {
        OperationClientDevice::where('operation_client_id', $operationClientId)
            ->where('device_role', $role)
            ->delete();

        OperationClientDevice::create([
            'operation_client_id' => $operationClientId,
            'device_id' => $deviceId,
            'device_role' => $role,
            'is_default' => true,
            'priority' => 1,
            'enabled' => true,
        ]);
    }
}
