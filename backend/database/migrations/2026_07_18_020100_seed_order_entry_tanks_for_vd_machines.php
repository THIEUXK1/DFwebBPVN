<?php
// backend/database/migrations/2026_07_18_020100_seed_order_entry_tanks_for_vd_machines.php
//
// Phát hiện khi rà soát lại VBA màn hình Nhập đơn sản xuất (Workbook "2.C3..."):
// danh sách thùng trộn (Box5 / formselect1) là "1A","2B","3C","4D","FB" — mã CHUNG,
// không gắn tiền tố máy. Nhưng app.tanks hiện tại CHỈ có tank cho các máy L1-L4/T5-T8
// (seed bởi MachinesAndTanksSeeder, phục vụ module Cấu hình nước/Công thức — KHÔNG
// liên quan màn hình này), với mã dạng "L1-1A" (có tiền tố máy). Máy VD006-VD013
// (dùng cho Nhập đơn sản xuất, đúng dải áp quy tắc 250L trong ApproveProductionOrderService)
// CHƯA có bất kỳ tank nào — khiến quy tắc 250L không bao giờ kích hoạt được (không có
// tank.code nào khớp đúng "1A"/"2B" cho các máy này) và màn hình chọn thùng không có
// gì để chọn. Bổ sung tank mã ĐÚNG NHƯ VBA (không tiền tố) cho từng máy VD006-VD013.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const VD_MACHINES = ['VD006', 'VD007', 'VD008', 'VD009', 'VD010', 'VD011', 'VD012', 'VD013'];
    private const TANK_CODES = ['1A', '2B', '3C', '4D', 'FB'];

    public function up(): void
    {
        $machines = DB::table('machines')->whereIn('code', self::VD_MACHINES)->get(['id', 'code']);

        foreach ($machines as $machine) {
            foreach (self::TANK_CODES as $tankCode) {
                $exists = DB::table('tanks')
                    ->where('machine_id', $machine->id)
                    ->where('code', $tankCode)
                    ->exists();

                if (!$exists) {
                    DB::table('tanks')->insert([
                        'machine_id' => $machine->id,
                        'code' => $tankCode,
                        'name' => "Thùng {$tankCode} - {$machine->code}",
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $machineIds = DB::table('machines')->whereIn('code', self::VD_MACHINES)->pluck('id');
        DB::table('tanks')
            ->whereIn('machine_id', $machineIds)
            ->whereIn('code', self::TANK_CODES)
            ->delete();
    }
};
