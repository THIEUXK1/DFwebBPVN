<?php
// backend/database/migrations/2026_08_08_000002_fix_mes_batch_completions_natural_key.php
//
// Đổi khoá tự nhiên của mes_batch_completions từ `mes_id` sang (batch_no, line_no).
//
// VÌ SAO PHẢI CÓ FILE NÀY: migration tạo bảng (2026_08_07_000001) đã CHẠY XONG trên
// Production ngày 07/08/2026 với `mes_id NOT NULL UNIQUE`. Sau đó phát hiện field `id` của
// eBatchLine LUÔN rỗng trên phản hồi batchView nên khoá đó vô dụng, và file tạo bảng được
// sửa lại cho các lần cài mới. Laravel KHÔNG chạy lại migration đã ghi trong bảng
// `migrations`, nên DB Production vẫn giữ nguyên hình cũ: `mes_id` NOT NULL, không có
// unique (batch_no, line_no). Hậu quả nếu thiếu bước này:
//   - `upsert(..., ['batch_no','line_no'], ...)` -> Postgres báo "there is no unique or
//     exclusion constraint matching the ON CONFLICT specification";
//   - và kể cả bỏ qua được thì INSERT vẫn vỡ vì `mes_id` NOT NULL mà code không còn ghi.
// Tức là lệnh mes:sync-batch-completions sẽ hỏng 100% trên Production.
//
// GIỮ LẠI cột `mes_id` (chỉ bỏ NOT NULL + bỏ unique) thay vì DROP COLUMN: cột này vô hại khi
// để trống, còn drop là thao tác không lùi lại được — không có lý do gì phải chịu rủi ro đó
// chỉ để dọn một cột chết.
//
// Idempotent: chạy được cả trên DB cũ (có mes_id) lẫn DB mới cài từ đầu (không có mes_id).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'mes_batch_completions';

    private const UNIQUE_NAME = 'mes_bc_batch_line_uq';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        // Các lệnh ALTER dưới đây là cú pháp Postgres. SQLite (dùng cho test) không đổi được
        // ràng buộc cột tại chỗ, nhưng ở đó bảng luôn được tạo mới từ migration đã sửa nên
        // đã đúng hình rồi — không có gì để vá.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Dòng cũ (nếu có) phải có đủ khoá mới trước khi đặt NOT NULL. Không xoá dòng nào:
        // bảng là cache đồng bộ, nhưng nguyên tắc chung của dự án là không xoá vật lý.
        DB::statement("UPDATE ".self::TABLE." SET line_no = '1' WHERE line_no IS NULL");
        DB::statement("UPDATE ".self::TABLE." SET batch_no = 'UNKNOWN-' || id WHERE batch_no IS NULL");

        if (Schema::hasColumn(self::TABLE, 'mes_id')) {
            DB::statement('DROP INDEX IF EXISTS '.self::TABLE.'_mes_id_unique');
            DB::statement('ALTER TABLE '.self::TABLE.' ALTER COLUMN mes_id DROP NOT NULL');
        }

        DB::statement('ALTER TABLE '.self::TABLE.' ALTER COLUMN batch_no SET NOT NULL');
        DB::statement("ALTER TABLE ".self::TABLE." ALTER COLUMN line_no SET DEFAULT '1'");
        DB::statement('ALTER TABLE '.self::TABLE.' ALTER COLUMN line_no SET NOT NULL');

        // Đích thật của cả migration: có unique thì `upsert` mới có ON CONFLICT để bám vào.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS '.self::UNIQUE_NAME
            .' ON '.self::TABLE.' (batch_no, line_no)'
        );
    }

    /**
     * No-op có chủ ý: lùi lại nghĩa là dựng lại `mes_id NOT NULL UNIQUE` trên một bảng mà mọi
     * dòng đều có mes_id rỗng — không thể làm mà không xoá dữ liệu. Cần lùi thật thì viết
     * migration mới, tường minh.
     */
    public function down(): void
    {
    }
};
