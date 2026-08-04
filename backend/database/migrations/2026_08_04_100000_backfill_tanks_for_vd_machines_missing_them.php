<?php
// backend/database/migrations/2026_08_04_100000_backfill_tanks_for_vd_machines_missing_them.php
//
// Bù 5 thùng chuẩn (1A/2B/3C/4D/FB) cho những máy VD được thêm SAU migration seed
// 2026_07_18_020100_seed_order_entry_tanks_for_vd_machines.
//
// Lỗi thật 2026-08-04: đơn chưa duyệt nằm ở máy VD04 (thêm qua giao diện) không chọn được thùng.
// SubForm màn /production-batches/grid ghi `tank_id` — khóa ngoại theo TỪNG máy — và
// ProductionBatchController::updateTank() chặn "Thùng đã chọn không thuộc đúng máy của lô này".
// Máy thêm qua endpoint storeMachine trước đây không được tạo thùng nào, nên không có thùng nào
// khớp: nút OK khóa cứng, người dùng không có cách nào tự gỡ.
//
// Chỉ đụng tới máy có mã bắt đầu bằng "VD" và CHƯA có thùng nào. Các máy L1-L4/T5-T8 của module
// Cấu hình nước dùng mã thùng có tiền tố ("L1-1A") và đã có sẵn thùng — không được chèn thêm mã
// không tiền tố vào đó.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TANK_CODES = ['1A', '2B', '3C', '4D', 'FB'];

    public function up(): void
    {
        $machines = DB::table('machines')
            ->where('code', 'like', 'VD%')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tanks')
                    ->whereColumn('tanks.machine_id', 'machines.id');
            })
            ->get(['id', 'code']);

        foreach ($machines as $machine) {
            foreach (self::TANK_CODES as $tankCode) {
                DB::table('tanks')->insert([
                    'machine_id' => $machine->id,
                    'code' => $tankCode,
                    'name' => "Thùng {$tankCode} - {$machine->code}",
                ]);
            }
        }
    }

    // Không rollback: thùng bù ở đây có thể đã được đơn sản xuất tham chiếu qua `tank_id`, xóa đi
    // là làm hỏng dữ liệu giao dịch (CLAUDE.md mục 3 — không hard-delete). Muốn gỡ thì chuyển
    // trạng thái máy sang Inactive.
    public function down(): void
    {
        // no-op
    }
};
