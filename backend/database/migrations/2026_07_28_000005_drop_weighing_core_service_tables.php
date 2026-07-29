<?php

// backend/database/migrations/2026_07_28_000005_drop_weighing_core_service_tables.php
//
// Gỡ pipeline WeighingCoreService (pushSample/getStableReading/acceptWeight/overrideWeight)
// — song song với WeighingJobController::weighItem() nhưng KHÔNG được frontend gọi tới bao
// giờ (đã rà soát toàn bộ backend/ + frontend/, chỉ WeighingJobController tự gọi nó qua 4
// method vừa xóa). WeighingJobController::weighItem() là pipeline thật đang chạy ở Phase 12
// pilot, giữ lại làm chuẩn duy nhất. Xem session-log.md mục rebuild /weighing-station theo
// mô hình RACK 9 dòng (scaleform.frm VBA gốc).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('weighing_events');
        Schema::dropIfExists('weighing_results');
        Schema::dropIfExists('weighing_samples');
    }

    public function down(): void
    {
        // Không tái tạo — WeighingCoreService chưa từng được gọi từ frontend (không có dữ
        // liệu nghiệp vụ thật trong 3 bảng này), rollback migration này không có ý nghĩa
        // khôi phục gì. Nếu cần lại pipeline này, khôi phục qua git log thay vì down().
    }
};
