<?php
// backend/database/seeders/FoundationSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WorkstationType;
use App\Models\FeatureFlag;
use App\Models\MachineChemicalChannel;
use App\Models\Machine;

class FoundationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Workstation Types
        $types = [
            ['code' => 'CHEMICAL_CALL', 'display_name' => 'Trạm Gọi Hóa Chất'],
            ['code' => 'PRODUCTION_ORDER', 'display_name' => 'Trạm Tạo Đơn Hàng'],
            ['code' => 'QR_LABEL_PRINTING', 'display_name' => 'Trạm In Nhãn QR'],
            ['code' => 'SMALL_SCALE', 'display_name' => 'Trạm Cân Nhỏ (Dưới 6kg)'],
            ['code' => 'LARGE_SCALE', 'display_name' => 'Trạm Cân Lớn (Trên 6kg)'],
        ];

        foreach ($types as $type) {
            WorkstationType::updateOrCreate(['code' => $type['code']], $type);
        }

        // 2. Seed Default Feature Flags
        $flags = [
            [
                'key' => 'b24_routing_enabled',
                'value' => true,
                'description' => 'Bật/tắt tự động tính toán phân vùng kho B24 và nhãn khu vực D1'
            ],
            [
                'key' => 'b24_d1_fix_enabled',
                'value' => false,
                'description' => 'Sửa lỗi rỗng D1 cho tổ hợp VD14-16 + 3C/4D của VBA gốc'
            ],
            [
                'key' => 'manual_routing_review_enabled',
                'value' => true,
                'description' => 'Tự động gửi các dòng lỗi phân vùng kho sang hàng chờ quản lý duyệt thủ công'
            ]
        ];

        foreach ($flags as $flag) {
            FeatureFlag::updateOrCreate(['key' => $flag['key']], $flag);
        }

        // 3. Seed Legacy Chemical Channels for VD006 to VD013 (Kênh 5 & Kênh 6)
        $legacyMachines = ['VD006', 'VD007', 'VD008', 'VD009', 'VD010', 'VD011', 'VD012', 'VD013'];
        foreach ($legacyMachines as $code) {
            $machine = Machine::where('code', $code)->first();
            if ($machine) {
                MachineChemicalChannel::updateOrCreate(
                    ['machine_id' => $machine->id, 'channel_number' => 5],
                    ['chemical_code' => 'VN62+0554', 'is_active' => true]
                );
                MachineChemicalChannel::updateOrCreate(
                    ['machine_id' => $machine->id, 'channel_number' => 6],
                    ['chemical_code' => 'AC77+AC78', 'is_active' => true]
                );
            }
        }
    }
}
