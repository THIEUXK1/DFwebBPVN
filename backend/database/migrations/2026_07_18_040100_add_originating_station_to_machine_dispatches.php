<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Trạm gửi" cho tier A của lịch sử in tem (yêu cầu 2026-07-18) — trước đây
// machine_dispatches không lưu trạm nào đã duyệt đơn (chỉ có user_id trong AuditLog,
// không có mã trạm), nên Trạm in/Admin không thể hiển thị "trạm nào đã gửi lệnh này".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->string('originating_station_code', 50)->nullable()->after('source_table');
        });
    }

    public function down(): void
    {
        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->dropColumn('originating_station_code');
        });
    }
};
