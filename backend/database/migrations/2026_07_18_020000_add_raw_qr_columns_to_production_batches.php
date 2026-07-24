<?php
// backend/database/migrations/2026_07_18_020000_add_raw_qr_columns_to_production_batches.php
//
// VBA mainform.Box1_AfterUpdate (Workbook C3) tách raw_qr_dye/raw_qr_chem ngay từ
// chuỗi quét tại màn hình Nhập đơn sản xuất (đánh dấu "-dye-"/"-chem-" trong chuỗi
// quét gốc) và lưu 2 cột này xuyên suốt tbl_input_all -> tbl_tosend. Bảng
// production_batches hiện chưa có 2 cột này -> mất dữ liệu nếu quét có kèm payload.
// Bổ sung thêm để bảo toàn đúng 100% dữ liệu quét gốc (nguyên tắc cốt lõi CLAUDE.md).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->text('raw_qr_dye')->nullable();
            $table->text('raw_qr_chemical')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_batches', function (Blueprint $table) {
            $table->dropColumn(['raw_qr_dye', 'raw_qr_chemical']);
        });
    }
};
