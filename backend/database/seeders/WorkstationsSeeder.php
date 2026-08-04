<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OperationClient;
use App\Models\Capability;
use App\Models\Device;
use Illuminate\Support\Str;

class WorkstationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stations = [
            // 1. CHEMICAL_CALL: 1 máy
            [
                'code' => 'WS-CHEMICAL-01',
                'name' => 'Trạm Gọi Hóa Chất 1',
                'type' => 'CHEMICAL_CALL',
                'workstation_type' => 'CHEMICAL_CALL',
                'location' => 'Khu gọi hóa chất',
                'active' => true,
                'default_screen' => '/chemical-call',
                'default_route' => '/chemical-call',
                'allowed_actions' => ['CALL_CHEMICAL'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-CHEMICAL-01'),
            ],

            // 2. PRODUCTION_ORDER: 1 máy
            [
                'code' => 'WS-ORDER-01',
                'name' => 'Trạm Điều Phối Đơn Hàng 1',
                'type' => 'PRODUCTION_ORDER',
                'workstation_type' => 'PRODUCTION_ORDER',
                'location' => 'Phòng điều phối',
                'active' => true,
                // '/order-scan' là trạm "ORDER DESK" khác (quét QR xem/xác nhận đã nhận
                // đơn) — route đúng khớp VBA C3 (tạo+duyệt đơn) là '/production-batches'.
                // Sửa 2026-07-18.
                'default_screen' => '/production-batches',
                'default_route' => '/production-batches',
                'allowed_actions' => ['SCAN_ORDER'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-ORDER-01'),
            ],

            // 3. QR_LABEL_PRINTING: 1 máy
            [
                'code' => 'WS-PRINT-01',
                'name' => 'Trạm In Nhãn QR 1',
                'type' => 'QR_LABEL_PRINTING',
                'workstation_type' => 'QR_LABEL_PRINTING',
                'location' => 'Khu in tem',
                'active' => true,
                'default_screen' => '/print-station',
                'default_route' => '/print-station',
                'allowed_actions' => ['SCAN_LABEL', 'REPRINT_LABEL'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-PRINT-01'),
            ],

            // 4. SMALL_SCALE: 2 máy
            [
                'code' => 'WS-SMALL-01',
                'name' => 'Trạm Cân Nhỏ 1 (Dưới 6kg)',
                'type' => 'SMALL_SCALE',
                'workstation_type' => 'SMALL_SCALE',
                'location' => 'Khu cân nhỏ',
                'active' => true,
                // Bản dựng lại workbook "4.semiauto-small scale.xlsm" — KHÔNG phải
                // '/weighing-station' (màn quản lý trạm cân bản web, không port từ workbook nào).
                'default_screen' => '/weighing-station-v2',
                'default_route' => '/weighing-station-v2',
                'allowed_actions' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-SMALL-01'),
            ],
            [
                'code' => 'WS-SMALL-02',
                'name' => 'Trạm Cân Nhỏ 2 (Dưới 6kg)',
                'type' => 'SMALL_SCALE',
                'workstation_type' => 'SMALL_SCALE',
                'location' => 'Khu cân nhỏ',
                'active' => true,
                'default_screen' => '/weighing-station-v2',
                'default_route' => '/weighing-station-v2',
                'allowed_actions' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-SMALL-02'),
            ],

            // 5. LARGE_SCALE: 1 máy
            [
                'code' => 'WS-LARGE-01',
                'name' => 'Trạm Cân Lớn 1 (Trên 6kg)',
                'type' => 'LARGE_SCALE',
                'workstation_type' => 'LARGE_SCALE',
                'location' => 'Khu cân lớn',
                'active' => true,
                // Bản dựng lại workbook "5.Semiauto- lockmove SEND OVER6.xlsm" (có khối SEND OVER 6).
                'default_screen' => '/weighing-station-large',
                'default_route' => '/weighing-station-large',
                'allowed_actions' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
                'locked_to_type' => true,
                'registration_token_hash' => hash('sha256', 'WS-TOKEN-LARGE-01'),
            ],
        ];

        if (app()->environment('testing')) {
            $stations = array_merge($stations, [
                [
                    'code' => 'WS-DYE',
                    'name' => 'Dye Weighing Station',
                    'type' => 'DYE_WEIGHING',
                    'workstation_type' => 'DYE_WEIGHING',
                    'active' => true,
                    'allowed_actions' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
                    'default_screen' => '/weighing-station',
                ],
                [
                    'code' => 'TANK-01',
                    'name' => 'Tank Receiving Station',
                    'type' => 'TANK_RECEIVING',
                    'workstation_type' => 'TANK_RECEIVING',
                    'active' => true,
                ],
                [
                    'code' => 'TRANS-01',
                    'name' => 'Material Transfer Station',
                    'type' => 'MATERIAL_TRANSFER',
                    'workstation_type' => 'MATERIAL_TRANSFER',
                    'active' => true,
                ],
                [
                    'code' => 'ORD-01',
                    'name' => 'Order Desk Station',
                    'type' => 'ORDER_DESK',
                    'workstation_type' => 'ORDER_DESK',
                    'active' => true,
                ],
                [
                    'code' => 'PRINT-01',
                    'name' => 'Print Station 01',
                    'type' => 'LABEL_PRINTING',
                    'workstation_type' => 'LABEL_PRINTING',
                    'active' => true,
                ],
                [
                    'code' => 'WS-CHEM',
                    'name' => 'Chemical Weighing Station',
                    'type' => 'CHEMICAL_WEIGHING',
                    'workstation_type' => 'CHEMICAL_WEIGHING',
                    'active' => true,
                ],
            ]);
        }

        // Clean and update workstations and devices in the database
        OperationClient::query()->delete();
        Device::query()->delete();

        foreach ($stations as $ws) {
            $capCode = $ws['type'] ?? $ws['workstation_type'] ?? null;
            $mappedCap = null;
            $deviceCaps = ['SCAN_QR', 'LOCAL_AGENT'];

            if (in_array($capCode, ['DYE_WEIGHING', 'CHEMICAL_WEIGHING', 'A11_WEIGHING', 'DLG_WEIGHING', 'SMALL_SCALE'])) {
                $mappedCap = 'SMALL_SCALE';
                $deviceCaps[] = 'WEIGH';
                $deviceCaps[] = 'PRINT';
            } elseif ($capCode === 'LABEL_PRINTING' || $capCode === 'PRINT_STATION' || $capCode === 'QR_LABEL_PRINTING') {
                $mappedCap = 'QR_LABEL_PRINTING';
                $deviceCaps[] = 'PRINT';
            } elseif ($capCode === 'ORDER_SCAN' || $capCode === 'ORDER_DESK' || $capCode === 'PRODUCTION_ORDER') {
                $mappedCap = 'PRODUCTION_ORDER';
            } elseif ($capCode === 'TANK_RECEIVING' || $capCode === 'MACHINE_FEEDING' || $capCode === 'CHEMICAL_CALL') {
                $mappedCap = 'CHEMICAL_CALL';
            } elseif ($capCode === 'LARGE_SCALE') {
                $mappedCap = 'LARGE_SCALE';
                $deviceCaps[] = 'WEIGH';
                $deviceCaps[] = 'PRINT';
            }

            $defaultRoute = $ws['default_route'] ?? $ws['default_screen'] ?? null;
            if (!$defaultRoute) {
                if ($mappedCap === 'SMALL_SCALE' || $mappedCap === 'LARGE_SCALE') {
                    $defaultRoute = '/weighing-station';
                } elseif ($mappedCap === 'QR_LABEL_PRINTING') {
                    $defaultRoute = '/print-station';
                } elseif ($mappedCap === 'PRODUCTION_ORDER') {
                    $defaultRoute = '/order-scan';
                } elseif ($mappedCap === 'CHEMICAL_CALL') {
                    $defaultRoute = '/feeding-monitor';
                }
            }

            $client = OperationClient::updateOrCreate(
                ['code' => $ws['code']],
                [
                    'name' => $ws['name'],
                    'type' => $ws['type'] ?? $mappedCap ?? 'SMALL_SCALE',
                    'workstation_type' => $ws['workstation_type'] ?? $mappedCap ?? 'SMALL_SCALE',
                    'location' => $ws['location'] ?? null,
                    'status' => (isset($ws['active']) && !$ws['active']) ? 'INACTIVE' : 'ACTIVE',
                    'default_capability' => $mappedCap,
                    'default_route' => $defaultRoute,
                    'kiosk_mode' => true,
                    'kiosk_token_hash' => $ws['registration_token_hash'] ?? null,
                    'kiosk_token_active' => true,
                ]
            );

            // Sync capabilities
            if ($mappedCap) {
                $cap = Capability::where('code', $mappedCap)->first();
                if ($cap) {
                    $client->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);
                }
            }

            foreach ($deviceCaps as $dc) {
                $cap = Capability::where('code', $dc)->first();
                if ($cap) {
                    $client->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);
                }
            }

            // Provision scale/printer association
            if ($mappedCap === 'SMALL_SCALE' || $mappedCap === 'LARGE_SCALE') {
                $scale = Device::where('code', 'SCALE_' . $client->code)->first();
                if (!$scale) {
                    $scale = Device::create([
                        'id' => (string) Str::uuid(),
                        'code' => 'SCALE_' . $client->code,
                        'device_type' => 'SCALE',
                        'status' => 'ONLINE'
                    ]);
                }
                $client->devices()->syncWithoutDetaching([$scale->id => [
                    'device_role' => 'PRIMARY_SCALE',
                    'is_default' => true,
                    'priority' => 1,
                    'enabled' => true
                ]]);
            }

            if ($mappedCap === 'QR_LABEL_PRINTING' || $mappedCap === 'SMALL_SCALE' || $mappedCap === 'LARGE_SCALE') {
                $printer = Device::where('code', 'PRINTER_' . $client->code)->first();
                if (!$printer) {
                    $printer = Device::create([
                        'id' => (string) Str::uuid(),
                        'code' => 'PRINTER_' . $client->code,
                        'device_type' => 'PRINTER',
                        'status' => 'ONLINE'
                    ]);
                }
                $client->devices()->syncWithoutDetaching([$printer->id => [
                    'device_role' => 'PRIMARY_PRINTER',
                    'is_default' => true,
                    'priority' => 1,
                    'enabled' => true
                ]]);
            }
        }
    }
}
