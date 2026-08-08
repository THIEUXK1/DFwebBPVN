<?php
// backend/database/migrations/2026_08_07_000001_create_mes_batch_completions_table.php
//
// Bảng cache "giờ kết thúc nhuộm THẬT của mẻ" đồng bộ từ VN-MES (module 车间生产 /
// eBatchLine, status=60 = đã kết thúc). Song sinh với color_swatches: cùng lý do — không
// gọi MES lúc render Gantt, mà đồng bộ định kỳ thành bảng tra nội bộ (lệnh
// mes:sync-batch-completions).
//
// Vì sao cần: Gantt máy VD hiện lấy giờ kết thúc từ BPDB/Sedo (dbo.SUP_Tasks). BPDB chỉ
// biết giờ CẤP/PHA thuốc xong, không biết mẻ NHUỘM kết thúc lúc nào — task chưa có
// FinishTime bị vẽ kéo dài tới now() (giờ "ảo"). MES eBatchLine.endTime là giờ người vận
// hành xác nhận mẻ nhuộm xong (thật), và MES phủ đủ các máy VD. Bảng này giữ endTime đó
// để BpdbMachineMonitoringService ghép vào từng thanh Gantt theo máy + mã màu + mã hàng +
// thời điểm gần nhau.
//
// Khoá chống trùng: (batch_no, line_no). Trường `id` của eBatchLine LUÔN rỗng trên phản
// hồi batchView (xác nhận 2026-08-07) nên không dùng được; khoá tự nhiên thật là số mẻ +
// số dây (line). Đồng bộ lại nhiều lần chỉ upsert, không sinh trùng (idempotency, xem
// database-safety.md mục 4).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mes_batch_completions', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Khoá tự nhiên MES: số mẻ + số dây (line). batchView trả field `id` rỗng nên
            // không dùng được — xem ghi chú đầu file.
            $table->string('batch_no', 64);
            $table->string('line_no', 32)->default('1');

            // Mã máy CHUẨN HOÁ (bỏ số 0 thừa: VD05/VD005 -> VD5) để khớp mã máy BPDB
            // (DyeMachines.MachineNo) vốn có thể pad số khác MES. Xem MachineCodeNormalizer.
            $table->string('machine_code', 32)->nullable();
            // Mã máy nguyên gốc MES (VD05, VD202...) — giữ để đối soát khi nghi ngờ khớp sai.
            $table->string('machine_no_raw', 32)->nullable();

            $table->string('color_code', 100)->nullable();
            $table->string('article_code', 100)->nullable();
            $table->string('order_ucode', 64)->nullable();

            $table->timestampTz('begin_time')->nullable();
            // Giờ kết thúc nhuộm THẬT — lý do tồn tại của cả bảng này.
            $table->timestampTz('end_time')->nullable();

            $table->string('end_by_name', 100)->nullable();
            $table->string('shift', 16)->nullable();
            $table->string('manu_step', 32)->nullable();
            $table->string('status', 16)->nullable();

            $table->string('source', 30)->default('MES_EBATCHLINE');
            $table->timestampTz('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_no', 'line_no'], 'mes_bc_batch_line_uq');
            // Khoá ghép của Gantt: máy + mã màu + mã hàng, lọc/xếp theo begin_time.
            $table->index(['machine_code', 'color_code', 'article_code', 'begin_time'], 'mes_bc_match_idx');
            $table->index('end_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mes_batch_completions');
    }
};
