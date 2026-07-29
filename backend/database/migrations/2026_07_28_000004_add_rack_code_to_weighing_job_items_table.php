<?php

// backend/database/migrations/2026_07_28_000004_add_rack_code_to_weighing_job_items_table.php
//
// Rebuild /weighing-station theo đúng mô hình bảng 9 dòng RACK/DYE CODE/WEIGHT/PROCESS
// của VBA gốc (scaleform.frm, txt_RACK1..9) — mỗi weighing_job_item giờ có thêm 1 cột
// rack_code (giống scale_measurements.rack_code đã có sẵn nhưng chưa từng được ghi bởi
// weighItem()). Tự nhận giá trị ban đầu từ rack_lines parse được trong QR quét (đúng VBA
// txt_color_AfterUpdate — điền rack/dye/weight theo đúng bộ ba trong chuỗi quét), nhưng
// vẫn luôn cho thao tác viên sửa tay trước khi xác nhận cân (giống bàn phím số ảo
// NumClick/NumDel gõ vào LastInputBox trong VBA).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weighing_job_items', function (Blueprint $table) {
            $table->string('rack_code', 20)->nullable()->after('sequence_no');
        });
    }

    public function down(): void
    {
        Schema::table('weighing_job_items', function (Blueprint $table) {
            $table->dropColumn('rack_code');
        });
    }
};
