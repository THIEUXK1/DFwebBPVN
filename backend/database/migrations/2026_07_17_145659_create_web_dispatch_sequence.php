<?php
// backend/database/migrations/2026_07_17_145659_create_web_dispatch_sequence.php
//
// app.machine_dispatches có UNIQUE(source_table, legacy_row_no) kế thừa từ mô hình
// dữ liệu di trú (mỗi dòng legacy có source_table + số dòng gốc). Dispatch được tạo
// TỪ WEB (qua ApproveProductionOrderService, không phải import Access) không có
// legacy_row_no thật — cần 1 sequence riêng để đảm bảo duy nhất, không tự bịa số
// hoặc dùng random (rủi ro đụng độ khi 2 request duyệt đơn đồng thời).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // SQLite has no CREATE SEQUENCE object; emulated here with a single-row counter
    // table (see ApproveProductionOrderService::nextWebDispatchRowNo for the read side).
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('CREATE TABLE IF NOT EXISTS web_dispatch_seq (value INTEGER NOT NULL DEFAULT 0)');
            DB::statement('INSERT INTO web_dispatch_seq (value) SELECT 0 WHERE NOT EXISTS (SELECT 1 FROM web_dispatch_seq)');
            return;
        }

        DB::statement('CREATE SEQUENCE IF NOT EXISTS web_dispatch_seq START 1');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS web_dispatch_seq');
            return;
        }

        DB::statement('DROP SEQUENCE IF EXISTS web_dispatch_seq');
    }
};
