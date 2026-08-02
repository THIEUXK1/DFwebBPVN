<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Khoá chống ghi trùng cho luồng lưu mẻ cân của /weighing-station-v2.
 *
 * Vì sao cần: từ 2026-08-02 màn hình cân có HÀNG ĐỢI phía trình duyệt — mất mạng lúc bấm SAVE thì
 * mẻ nằm lại localStorage và tự đẩy lên khi có mạng. Tình huống nguy hiểm là request ĐÃ tới server
 * và ghi xong nhưng phản hồi không về được tới trình duyệt: hàng đợi coi như thất bại và gửi lại,
 * và nếu không có khoá này thì mẻ bị ghi hai lần.
 *
 * Cùng khuôn mẫu đã dùng cho Local Agent (ADR + `.claude/rules/database-safety.md` mục 4):
 * `machine_dispatches.idempotency_key`, `print_jobs.idempotency_key`, `chemical_call_requests`.
 *
 * `nullable` vì mọi vòng cân đã có từ trước (và mọi luồng khác: cân từng dòng, quét mock) không
 * mang khoá này — chỉ luồng lưu cả mẻ mới sinh khoá.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('weighing_jobs', 'idempotency_key')) {
            return; // chạy lại migration nhiều lần vẫn an toàn
        }

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->string('idempotency_key', 100)->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Gỡ cột này KHÔNG mất dữ liệu nghiệp vụ nào (chỉ mất khả năng chống ghi trùng của các
        // request đang treo), nên rollback được. Vẫn kiểm tra tồn tại để down() chạy lại vô hại.
        if (! Schema::hasColumn('weighing_jobs', 'idempotency_key')) {
            return;
        }

        Schema::table('weighing_jobs', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
