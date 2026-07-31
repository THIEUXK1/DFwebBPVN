<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Đã từng in" (yêu cầu 2026-07-30) — tách riêng khỏi queue_state=CONFIRMED (xác nhận
// xong, chuyển xuống lịch sử): "⚡ In nhanh"/"🖥️ In qua trình duyệt" ở /print-station
// giờ chỉ mở hộp thoại in trình duyệt, KHÔNG gọi confirm (người vận hành có thể in thử/
// in lại thoải mái trước khi bấm "✅ OK"). Cần 1 cờ nhẹ, không qua state machine đầy đủ,
// chỉ để đổi màu nền hàng chờ từ đỏ (chưa từng in) sang bình thường (đã từng in) — không
// phải hành động nhạy cảm cần Audit Log (không đổi routing/QR/PrintJob thật).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->boolean('ever_printed')->default(false)->after('queue_state');
        });
    }

    public function down(): void
    {
        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->dropColumn('ever_printed');
        });
    }
};
