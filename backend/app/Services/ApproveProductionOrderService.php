<?php
// backend/app/Services/ApproveProductionOrderService.php
//
// Thay thế procedure VBA Mod_movetosend.MoveToSend (Workbook C3) — trước đây
// COPY dòng từ tbl_input_all sang tbl_tosend rồi DELETE dòng gốc, không có
// transaction, có rủi ro mất/trùng dữ liệu nếu lỗi giữa 2 bước (xem
// production-order-to-dispatch-flow.md Mục 1.2).
//
// Hệ thống mới KHÔNG di chuyển bảng vật lý: ProductionBatch giữ nguyên, chỉ đổi
// status -> APPROVED và tạo 1 MachineDispatch (tương đương "đã nằm trong hàng chờ
// tbl_tosend"). Toàn bộ nằm trong 1 transaction, có row lock + idempotency để
// chống tạo trùng dispatch khi duyệt gọi lại (mạng chập chờn, double-click...).
//
// Trước bản vá này, KHÔNG có bất kỳ API/service nào tạo MachineDispatch từ
// ProductionBatch — MachineDispatchController không có store(), và
// ProductionBatchController::updateStatus() chỉ đổi cột status tự do, không có
// quy tắc 250L, không tạo dispatch, không audit. Đây là mắt xích PRODUCTION_ORDER
// -> QR_LABEL_PRINTING còn thiếu hoàn toàn trong code (dù đã có trong ma trận
// audit trước đó).

namespace App\Services;

use App\Models\ProductionBatch;
use App\Models\MachineDispatch;
use App\Models\AuditLog;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApproveProductionOrderService
{
    /**
     * Dải máy áp dụng quy tắc 250L (production-order-to-dispatch-flow.md Mục 1.1.3,
     * đối chiếu VBA btnSAVE_Click). Mã máy thật trong app.machines dùng định dạng
     * 3 chữ số (VD006..VD013) — đã xác nhận trực tiếp qua DB, không phải VD06 2 chữ số.
     */
    private const MIN_LEVEL_MACHINE_MIN = 'VD006';
    private const MIN_LEVEL_MACHINE_MAX = 'VD013';
    private const MIN_LEVEL_TANKS = ['1A', '2B'];
    private const MIN_LEVEL_THRESHOLD = 250.0;

    /**
     * @return array{batch: ProductionBatch, dispatch: MachineDispatch, reused: bool}
     * @throws BusinessRuleException khi vi phạm quy tắc 250L hoặc batch đã bị hủy
     */
    public function execute(string $batchId, ?string $userId, ?string $correlationId = null, ?string $originatingStationCode = null): array
    {
        return DB::transaction(function () use ($batchId, $userId, $correlationId, $originatingStationCode) {
            $batch = ProductionBatch::where('id', $batchId)->lockForUpdate()->first();

            if (!$batch) {
                throw (new ModelNotFoundException())->setModel(ProductionBatch::class, [$batchId]);
            }

            // Idempotency: nếu batch này đã có dispatch rồi (duyệt lần 2, double-click,
            // request lặp lại do timeout mạng), trả lại dispatch đã có — KHÔNG tạo mới.
            // Khóa dòng ProductionBatch ở trên đã đủ ngăn 2 request đồng thời cùng vượt
            // qua điểm kiểm tra này (request thứ 2 phải chờ request 1 COMMIT/ROLLBACK).
            $existingDispatch = MachineDispatch::where('batch_id', $batch->id)->first();
            if ($existingDispatch) {
                return ['batch' => $batch, 'dispatch' => $existingDispatch, 'reused' => true];
            }

            if ($batch->status === 'CANCELLED') {
                throw new BusinessRuleException('Không thể duyệt đơn đã bị hủy.');
            }

            $this->assertMinLevelRule($batch);

            $beforeStatus = $batch->status;
            $batch->status = 'APPROVED';
            $batch->save();

            $legacyRowNo = DB::connection()->getDriverName() === 'sqlite'
                ? $this->nextWebDispatchRowNoSqlite()
                : DB::selectOne("SELECT nextval('web_dispatch_seq') AS val")->val;
            $correlationId = $correlationId ?: (string) Str::uuid();

            $dispatch = MachineDispatch::create([
                'batch_id' => $batch->id,
                'queue_state' => 'WAITING',
                'source_table' => 'WEB_APPROVAL',
                'originating_station_code' => $originatingStationCode,
                'legacy_row_no' => $legacyRowNo,
                'idempotency_key' => "dispatch-{$batch->id}",
                'is_sent' => false,
                'scale_checked' => false,
                // BUG NGHIÊM TRỌNG phát hiện 2026-07-19 khi build tem 70x100: batch đã lưu
                // đúng raw_qr_dye/raw_qr_chemical lúc quét, nhưng KHÔNG BAO GIỜ được copy
                // sang machine_dispatches — QrPayloadService::buildDyePayload/buildChemPayload
                // đọc từ $dispatch (không phải $batch), nên QR THẬT gửi cho Color Service
                // (máy pha màu tự động) bị THIẾU TOÀN BỘ dữ liệu thuốc nhuộm/hóa chất kể từ
                // khi ApproveProductionOrderService tồn tại — không phải chỉ ảnh hưởng bảng
                // hiển thị trên tem, mà ảnh hưởng đúng nghiệp vụ pha màu tự động thật.
                'raw_qr_dye' => $batch->raw_qr_dye,
                'raw_qr_chemical' => $batch->raw_qr_chemical,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'ORDER_APPROVED_DISPATCH_CREATED',
                'entity_type' => 'ProductionBatch',
                'entity_id' => $batch->id,
                'before_data' => ['status' => $beforeStatus],
                'after_data' => [
                    'status' => $batch->status,
                    'dispatch_id' => $dispatch->id,
                    'queue_state' => $dispatch->queue_state,
                ],
                'correlation_id' => $correlationId,
            ]);

            return ['batch' => $batch, 'dispatch' => $dispatch, 'reused' => false];
        });
    }

    /**
     * SQLite has no CREATE SEQUENCE; emulated via a single-row counter table
     * (see 2026_07_17_145659_create_web_dispatch_sequence.php). Not perfectly
     * atomic across concurrent writers, but SQLite serializes writes anyway and
     * this path is dev/local-test only — the real deployment uses pgsql nextval().
     */
    private function nextWebDispatchRowNoSqlite(): int
    {
        return DB::transaction(function () {
            DB::statement('UPDATE web_dispatch_seq SET value = value + 1');
            return (int) DB::selectOne('SELECT value FROM web_dispatch_seq')->value;
        });
    }

    /**
     * VBA btnSAVE_Click: nếu machine trong [VD006,VD013] VÀ tank trong {1A,2B} VÀ
     * level < 250 -> chặn lưu, thông báo "MINIMUM LEVEL 250L". Chỉ áp dụng khi có
     * đủ machine+tank+level_code hợp lệ; thiếu dữ liệu thì KHÔNG áp quy tắc (không
     * suy diễn ngưỡng cho trường hợp chưa xác nhận).
     */
    private function assertMinLevelRule(ProductionBatch $batch): void
    {
        $machine = $batch->machine;
        $tank = $batch->tank;

        if (!$machine || !$tank || $batch->level_code === null || $batch->level_code === '') {
            return;
        }

        $machineCode = strtoupper(trim($machine->code));
        $tankCode = strtoupper(trim($tank->code));

        $inRange = $machineCode >= self::MIN_LEVEL_MACHINE_MIN && $machineCode <= self::MIN_LEVEL_MACHINE_MAX;
        $tankMatches = in_array($tankCode, self::MIN_LEVEL_TANKS, true);

        if (!$inRange || !$tankMatches) {
            return;
        }

        if (!is_numeric($batch->level_code)) {
            return;
        }

        if ((float) $batch->level_code < self::MIN_LEVEL_THRESHOLD) {
            throw new BusinessRuleException('MINIMUM LEVEL 250L');
        }
    }
}
