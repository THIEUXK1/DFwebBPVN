<?php
// backend/database/migrations/2026_08_13_000001_add_process_to_mes_formulas_table.php
//
// Thêm cột `process` (jsonb) vào mes_formulas — lưu quy trình nhuộm (工艺) trích từ head
// công thức mesPfM/infoByHeadId: các bước công đoạn kèm nhiệt độ (pfMScgyList) + cờ kỹ
// thuật bật/tắt (pfMTechList, vd 固色/防黄). Xem MesSedoClient::extractProcess().
//
// Tách cột riêng thay vì nhét vào `raw` hiện có: `raw` đã mang nghĩa cố định là nguyên
// pfMPfList (danh sách liệu) ở nơi khác trong code — đổi ngữ nghĩa ngầm dễ gây nhầm khi
// đọc lại sau này.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mes_formulas', function (Blueprint $table) {
            $table->jsonb('process')->nullable()->after('raw');
        });
    }

    public function down(): void
    {
        // no-op an toàn: xóa cột có thể mất dữ liệu quy trình đã đồng bộ, không rollback tự động
        // (đúng quy tắc coding-standards.md mục 1 — migration down() an toàn, no-op nếu mất dữ liệu).
    }
};
