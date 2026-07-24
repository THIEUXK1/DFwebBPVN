<?php
// backend/database/migrations/2026_07_18_030000_seed_full_vd_machine_range.php
//
// Phát hiện khi đối chiếu 2 mẫu QR THẬT (phiếu MES "BEST PACIFIC" máy VD04, và ảnh
// chụp trực tiếp MainForm VBA đang chạy quét ra máy VD02): app.machines hiện chỉ có
// VD006-VD013 (seed bởi MachinesAndTanksSeeder, dải áp quy tắc 250L) — THIẾU VD001-
// VD005 và VD014-VD018, dù formselect2.frm (danh sách chọn máy nhanh trong VBA) liệt
// kê đủ VD01..VD18. Máy VD02/VD04 trong dữ liệu thật không resolve được sang machine_id
// nào cả nếu không vá. Bổ sung đủ dải, giữ nguyên convention mã 3 chữ số đã dùng
// (VD006 chứ không phải VD6) cho nhất quán với dữ liệu hiện có.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ALL_VD_MACHINES = [
        'VD001', 'VD002', 'VD003', 'VD004', 'VD005', 'VD006', 'VD007', 'VD008',
        'VD009', 'VD010', 'VD011', 'VD012', 'VD013', 'VD014', 'VD015', 'VD016',
        'VD017', 'VD018',
    ];
    private const TANK_CODES = ['1A', '2B', '3C', '4D', 'FB'];

    public function up(): void
    {
        foreach (self::ALL_VD_MACHINES as $code) {
            $machineId = DB::table('machines')->where('code', $code)->value('id');

            if (!$machineId) {
                $machineId = DB::table('machines')->insertGetId([
                    'code' => $code,
                    'name' => 'Máy nhuộm ' . $code,
                    'is_active' => true,
                ]);
            }

            foreach (self::TANK_CODES as $tankCode) {
                $exists = DB::table('tanks')
                    ->where('machine_id', $machineId)
                    ->where('code', $tankCode)
                    ->exists();

                if (!$exists) {
                    DB::table('tanks')->insert([
                        'machine_id' => $machineId,
                        'code' => $tankCode,
                        'name' => "Thùng {$tankCode} - {$code}",
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $machineIds = DB::table('machines')->whereIn('code', self::ALL_VD_MACHINES)->pluck('id');
        DB::table('tanks')->whereIn('machine_id', $machineIds)->whereIn('code', self::TANK_CODES)->delete();
        // Không xoá app.machines ở down() — có thể đã có production_batches/dispatch tham chiếu.
    }
};
