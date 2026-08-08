<?php
// backend/database/migrations/2026_08_08_000001_add_raw_to_mes_batch_completions.php
//
// Lưu TOÀN BỘ dòng eBatchLine gốc của MES dưới dạng JSONB, phục vụ popup "xem toàn bộ thông
// tin mẻ trong MES" khi bấm vào thanh Gantt. Giữ nguyên trạng để không phải thêm cột mỗi khi
// muốn hiển thị thêm 1 trường; các cột phẳng sẵn có (color_code, end_time...) vẫn dùng cho
// việc ghép/lọc nhanh.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mes_batch_completions', function (Blueprint $table) {
            $table->jsonb('raw')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mes_batch_completions', function (Blueprint $table) {
            $table->dropColumn('raw');
        });
    }
};
