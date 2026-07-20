<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Trừ bì (tare/delta) — xác nhận nghiệp vụ 2026-07-18 (CH-BUS-006): "chắc chắn phải
// trừ bì, cốc/khay/thùng đặt lên trước coi như là bì", đúng VBA Mod_delta_raw
// (Delta_Begin reset baseline -> AutoFlow_OnWeight lấy lần đọc đầu làm bì, deltaVal =
// |rawW - base| từ lần đọc thứ 2 trở đi). Cột 'weight' hiện có TIẾP TỤC là NET (giá trị
// đã trừ bì — không đổi ý nghĩa, không phá dữ liệu lịch sử/so dung sai hiện có). 2 cột
// mới chỉ phục vụ audit "vì sao actual_weight lại là net chứ không phải gross" — đúng
// khuyến nghị p0-c-scale-algorithm.md Mục A.6/Phần D.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->decimal('tare_weight', 18, 6)->nullable()->after('weight');
            $table->decimal('gross_weight', 18, 6)->nullable()->after('tare_weight');
        });
    }

    public function down(): void
    {
        Schema::table('scale_measurements', function (Blueprint $table) {
            $table->dropColumn(['tare_weight', 'gross_weight']);
        });
    }
};
