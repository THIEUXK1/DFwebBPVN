<?php
// backend/database/migrations/2026_08_03_000001_create_rack_dispatch_commands_table.php
//
// Hàng đợi lệnh gửi mã rack sang hệ pha màu — phần "SEND OVER 6" của workbook
// "5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm".
//
// Bản gốc không có bảng nào: VBA đặt clipboard rồi bắn chuột thẳng vào ứng dụng pha màu
// (Mod_sendRackauto.FireRackBatch). Kiến trúc web cấm trình duyệt chạm ứng dụng cục bộ
// (ADR-002), nên lệnh phải đi vòng: Web -> bảng này -> Local Agent poll -> Agent mô phỏng
// chuột. Bảng này chính là chỗ "vòng" đó.
//
// Chỉ TẠO MỚI, không đụng bảng nào đang có.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_dispatch_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('workstation_code', 50)->index();

            // OUT = bắn 6 mã rack vào ứng dụng đích; IN = bấm nút xác nhận bên đó rồi dồn
            // lô 2 lên lô 1 (btn_Out_Click / btn_In_Click).
            $table->string('action', 10);

            // Giữ NGUYÊN độ dài 6 và thứ tự, ô trống là chuỗi rỗng: mỗi vị trí ứng với một
            // ô nhập cố định bên ứng dụng đích (bản gốc bắn theo toạ độ, không theo tên
            // trường) — dồn mảng lại là lệch ô.
            $table->jsonb('racks')->nullable();

            // Bắt buộc theo rules/database-safety mục 4: Agent đồng bộ lại sau khi mất mạng
            // không được bắn trùng lệnh vào ứng dụng pha màu (bắn trùng = cấp thừa vật tư).
            $table->string('idempotency_key', 191)->unique();

            $table->string('status', 20)->default('PENDING')->index();
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('created_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_dispatch_commands');
    }
};
