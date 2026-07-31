<?php
// backend/app/Services/ConfirmDispatchService.php

namespace App\Services;

use App\Models\MachineDispatch;
use App\Models\DispatchEvent;
use App\Models\QrPayload;
use App\Models\PrintJob;
use App\Models\PrintAttempt;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ConfirmDispatchService
{
    protected $routingService;
    protected $qrPayloadService;
    protected $eventService;

    public function __construct(
        WarehouseRoutingService $routingService,
        QrPayloadService $qrPayloadService,
        \App\Services\PrintJobEventService $eventService
    ) {
        $this->routingService = $routingService;
        $this->qrPayloadService = $qrPayloadService;
        $this->eventService = $eventService;
    }

    /**
     * Confirms a dispatch job (equivalent to ConfirmRow in Excel VBA).
     * Wrapped in a transaction with database row locking.
     */
    public function confirm(string $dispatchId, string $idempotencyKey, array $options = []): array
    {
        return DB::transaction(function () use ($dispatchId, $idempotencyKey, $options) {
            // 1. Check if record exists and is valid (not soft deleted)
            $exists = MachineDispatch::find($dispatchId);
            if (!$exists) {
                throw new Exception("DISPATCH_NOT_FOUND");
            }

            // 3. Lock row for updates (Pessimistic Locking) — PHẢI khóa TRƯỚC khi kiểm tra
            // trạng thái "đã confirm chưa", không phải sau. Nếu kiểm tra trước khi khóa, 2
            // request đồng thời (state-machines.md Mục 3, test 2.2 "Hai người xác nhận cùng
            // một dòng") đều có thể đọc thấy queue_state chưa CONFIRMED cùng lúc, request B
            // sẽ block ở lockForUpdate() cho tới khi A commit, rồi vẫn chạy tiếp logic confirm
            // lần 2 vì chưa từng re-check sau khi có khóa — tạo trùng QrPayload/PrintJob/
            // DispatchEvent. Phát hiện + sửa khi review code Phase E, 2026-07-17.
            $dispatch = MachineDispatch::where('id', $dispatchId)->lockForUpdate()->first();

            // 2. Check if already confirmed (idempotency check) — chạy SAU khi đã có khóa,
            // đọc lại trạng thái mới nhất (không dùng biến $exists đọc trước khóa).
            if ($dispatch->queue_state === 'CONFIRMED' || ($dispatch->idempotency_key !== null && $dispatch->idempotency_key === $idempotencyKey)) {
                $payloads = QrPayload::where('dispatch_id', $dispatch->id)->get();
                $printJob = PrintJob::where('dispatch_id', $dispatch->id)->first();
                $decision = $dispatch->routingDecision;

                return [
                    'dispatch_id' => $dispatch->id,
                    'status' => 'CONFIRMED',
                    'qr_payloads' => $payloads,
                    'print_job_id' => $printJob ? $printJob->id : null,
                    'routing_decision' => $decision,
                    'reused' => true,
                ];
            }

            $dispatch->idempotency_key = $idempotencyKey;

            // 4. Compute Warehouse Routing
            $decision = $this->routingService->calculateRouting($dispatch);

            // 5. Generate QR Payloads
            $qrPayloads = $this->generateQrPayloads($dispatch, $decision);

            // 6. Create Print Job
            $printJob = $this->createPrintJob($dispatch, $qrPayloads, $options, decision: $decision);

            // 7. Update dispatch queue state
            $dispatch->queue_state = 'CONFIRMED';
            $dispatch->routing_decision_id = $decision->id;
            
            // 10. Update scale check according to ScaleCheckPolicy (wait_printform variant)
            $scaleCheckPolicy = $options['scale_check_policy'] ?? 'DEFAULT';
            if ($scaleCheckPolicy === 'WAIT_PRINTFORM') {
                $dispatch->scale_checked = true;
            }
            $dispatch->save();

            // 8 & 9. Save raw QR dye & chemical to dispatch events
            $event = DispatchEvent::create([
                'dispatch_id' => $dispatch->id,
                'event_type' => 'CONFIRMED',
                'color' => $dispatch->batch->color ?? null,
                'code' => $dispatch->batch->product_code ?? null,
                'machine_id' => $dispatch->batch->machine_id ?? null,
                'tank' => $dispatch->batch && $dispatch->batch->tank ? $dispatch->batch->tank->code : null,
                'level' => $dispatch->batch->level_code ?? null,
                'confirm_1' => $dispatch->confirm_1,
                'confirm_2' => $dispatch->confirm_2,
                'is_sent' => $dispatch->is_sent,
                'scale_checked' => $dispatch->scale_checked,
                'raw_qr_dye' => collect($qrPayloads)->where('payload_type', 'DYE')->first()->raw_payload ?? null,
                'raw_qr_chemical' => collect($qrPayloads)->where('payload_type', 'CHEM')->first()->raw_payload ?? null,
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'legacy_source' => $dispatch->source_table,
                'legacy_id' => $dispatch->legacy_id,
            ]);

            // 11. Log AuditLog
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'DISPATCH_CONFIRMED',
                'entity_type' => 'machine_dispatches',
                'entity_id' => $dispatch->id,
                'after_data' => [
                    'dispatch_id' => $dispatch->id,
                    'routing_decision_id' => $decision->id,
                    'print_job_id' => $printJob->id,
                    'event_id' => $event->id,
                ]
            ]);

            // 12. Dispatch integration event (mocked here, can be Laravel Event)
            // event(new \App\Events\DispatchConfirmed($dispatch));

            return [
                'dispatch_id' => $dispatch->id,
                'status' => 'CONFIRMED',
                'qr_payloads' => $qrPayloads,
                'print_job_id' => $printJob->id,
                'routing_decision' => $decision,
                'reused' => false,
            ];
        });
    }

    /**
     * Sinh QR payload theo đúng định dạng chuỗi thô VBA gốc (QrPayloadService,
     * xem b24-warehouse-routing.md Mục 4) — bắt buộc theo CLAUDE.md C-04, KHÔNG
     * dùng định dạng tự chế. Luôn có DYE+CHEM; thêm 1 payload theo mode (PROCESS/
     * EXTRA/FB) do WarehouseRoutingService quyết định.
     */
    protected function generateQrPayloads(MachineDispatch $dispatch, $decision): array
    {
        $batch = $dispatch->batch;
        $payloads = [];

        $dyeData = $this->qrPayloadService->buildDyePayload($dispatch, $batch);
        $payloads[] = QrPayload::create([
            'dispatch_id' => $dispatch->id,
            'payload_version' => '1.0',
            'payload_type' => 'DYE',
            'raw_payload' => $dyeData,
            'payload_hash' => hash('sha256', $dyeData),
            'routing_decision_id' => $decision->id,
            'template_version' => 'v1',
            'created_at' => now(),
        ]);

        $chemData = $this->qrPayloadService->buildChemPayload($dispatch, $batch);
        $payloads[] = QrPayload::create([
            'dispatch_id' => $dispatch->id,
            'payload_version' => '1.0',
            'payload_type' => 'CHEM',
            'raw_payload' => $chemData,
            'payload_hash' => hash('sha256', $chemData),
            'routing_decision_id' => $decision->id,
            'template_version' => 'v1',
            'created_at' => now(),
        ]);

        $mode = $decision->mode ?? 'FB';
        $modeData = $this->qrPayloadService->buildProcessPayload($dispatch, $batch, $mode);
        $payloads[] = QrPayload::create([
            'dispatch_id' => $dispatch->id,
            'payload_version' => '1.0',
            'payload_type' => strtoupper($mode),
            'raw_payload' => $modeData,
            'payload_hash' => hash('sha256', $modeData),
            'routing_decision_id' => $decision->id,
            'template_version' => 'v1',
            'created_at' => now(),
        ]);

        return $payloads;
    }

    /**
     * In lại tem cho 1 dispatch ĐÃ in (CONFIRMED) — tái dùng QrPayload đã sinh lần đầu
     * (không tính lại routing/QR, đúng nội dung tem gốc), tạo PrintJob MỚI và ghi
     * REPRINT_REQUESTED thay vì PRINT_REQUESTED. Yêu cầu 2026-07-18: "Có phải in lại
     * hay không, lý do in lại" phải phân biệt được với lần in đầu.
     */
    public function reprint(string $dispatchId, array $options = []): PrintJob
    {
        return DB::transaction(function () use ($dispatchId, $options) {
            $dispatch = MachineDispatch::where('id', $dispatchId)->lockForUpdate()->first();
            if (!$dispatch) {
                throw new Exception('DISPATCH_NOT_FOUND');
            }
            if ($dispatch->queue_state !== 'CONFIRMED') {
                throw new Exception('DISPATCH_NOT_YET_PRINTED — chỉ in lại được đơn đã in lần đầu.');
            }

            $qrPayloads = QrPayload::where('dispatch_id', $dispatch->id)->get()->all();
            if (empty($qrPayloads)) {
                throw new Exception('NO_QR_PAYLOAD_FOUND');
            }

            $job = $this->createPrintJob($dispatch, $qrPayloads, $options, isReprint: true, decision: $dispatch->routingDecision);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'PRINT_JOB_REPRINTED',
                'entity_type' => 'print_jobs',
                'entity_id' => $job->id,
                'after_data' => [
                    'dispatch_id' => $dispatch->id,
                    'print_job_id' => $job->id,
                    'reason' => $options['reason'] ?? null,
                ],
            ]);

            return $job;
        });
    }

    /**
     * Spools a PrintJob.
     */
    protected function createPrintJob(MachineDispatch $dispatch, array $qrPayloads, array $options, bool $isReprint = false, ?\App\Models\RoutingDecision $decision = null): PrintJob
    {
        // time() (giây) có thể trùng giữa lần in đầu và lần in lại ngay sau đó trong
        // cùng 1 request test/thao tác nhanh tay — print_jobs.idempotency_key có ràng
        // buộc UNIQUE, thêm hậu tố ngẫu nhiên để không bao giờ đụng nhau giữa các
        // PrintJob khác nhau (phát hiện khi viết test reprint, 2026-07-18).
        $idempotencyKey = 'print_' . $dispatch->id . '_' . time() . '_' . \Illuminate\Support\Str::random(8);

        $job = new PrintJob();
        $job->id = \Illuminate\Support\Str::uuid();
        $job->dispatch_id = $dispatch->id;
        $job->workstation_id = $options['workstation_id'] ?? 'QR_LABEL_PRINTING_1';

        // Tem 70x100mm — lệnh TSPL THẬT (SIZE/TEXT/QRCODE), không phải JSON thô như trước
        // (khiến máy in KHÔNG hiểu được gì, xem QrPayloadService::buildTsplLabel70x100 cho
        // đối chiếu chi tiết từng trường với VBA PrintSlip_70x100 gốc). Xác nhận kích thước
        // 70x100mm + yêu cầu rà soát nội dung: 2026-07-19.
        $dyePayload = collect($qrPayloads)->firstWhere('payload_type', 'DYE');
        $chemPayload = collect($qrPayloads)->firstWhere('payload_type', 'CHEM');
        $modePayload = collect($qrPayloads)->first(fn ($p) => !in_array($p->payload_type, ['DYE', 'CHEM'], true));

        $job->label_payload = $this->qrPayloadService->buildTsplLabel70x100(
            $dispatch,
            $dispatch->batch,
            $decision,
            $dyePayload->raw_payload ?? '',
            $chemPayload->raw_payload ?? '',
            $modePayload->raw_payload ?? '',
            $modePayload->payload_type ?? 'FB'
        );
        // Local Print Agent là nguồn xác định máy in (PrinterDiscovery.cs) — frontend gửi
        // đúng tên máy in Windows đã resolve (ưu tiên: đã gán > mặc định hệ thống > máy in
        // đầu tiên). Trước đây mặc định cứng 'LAN'/'10.0.19.79' (địa chỉ giả lập) khi
        // frontend không gửi options, sai với thực tế USB-spooler-theo-tên của
        // LabelPrinter.cs::PrintViaUsb — chỉ set khi frontend thực sự gửi, để cột DB tự
        // dùng default ('USB'/'TSC TE200') khi thiếu, không còn IP giả.
        if (!empty($options['printer_type'])) {
            $job->printer_connection_type = $options['printer_type'];
        }
        if (!empty($options['printer_address'])) {
            $job->printer_address = $options['printer_address'];
        }
        // printed_via_browser: đã in thật qua hộp thoại Windows/trình duyệt ngay tại thời
        // điểm confirm (xem MachineDispatchController::confirm) — đánh dấu PRINTED thẳng để
        // AgentJobsController::getJobs (chỉ lấy status=PENDING) không bao giờ thấy job này,
        // tránh Agent gửi in lại lần 2 xuống máy in vật lý.
        $printedViaBrowser = !empty($options['printed_via_browser']);
        $job->status = $printedViaBrowser ? 'PRINTED' : 'PENDING';
        $job->idempotency_key = $idempotencyKey;
        $job->correlation_id = \Illuminate\Support\Str::uuid();
        $job->expiry = now()->addMinutes(30);
        $job->retry_policy = [
            'max_attempts' => 3,
            'backoff_seconds' => 5,
        ];
        $job->created_at = now();
        $job->save();

        $eventContext = [
            'dispatch_id' => $dispatch->id,
            'production_job_id' => $dispatch->batch_id,
            'station_id' => $options['workstation_id'] ?? null,
            'printer_name' => $job->printer_address,
            'correlation_id' => $job->correlation_id,
        ];
        $this->eventService->log($job->id, 'JOB_CREATED', $eventContext);
        if ($job->printer_address) {
            $this->eventService->log($job->id, 'PRINTER_SELECTED', $eventContext);
        }
        if ($isReprint) {
            $this->eventService->log($job->id, 'REPRINT_REQUESTED', $eventContext + [
                'error_message' => $options['reason'] ?? null,
            ]);
        } else {
            // confirm() là hành động atomic duy nhất tạo PrintJob (lần in đầu) — không có
            // pha riêng "đã hiện ở trạm" hay "yêu cầu in" tách biệt trong luồng hiện tại,
            // nên 2 event này log liền nhau tại cùng 1 thời điểm (xem ghi chú đầu
            // PrintJobEventService).
            $this->eventService->log($job->id, 'JOB_VISIBLE_AT_STATION', $eventContext);
            $this->eventService->log($job->id, 'PRINT_REQUESTED', $eventContext);
        }

        if ($printedViaBrowser) {
            // Ghi nhận kết quả in giống hệt AgentJobsController::acknowledgeJob (cùng tạo
            // PrintAttempt cho tier C lịch sử in, cùng log SENT_TO_PRINTER/PRINT_SUCCEEDED)
            // — chỉ khác nguồn xác nhận là trình duyệt tự báo ngay lúc confirm, không phải
            // Agent báo cáo sau khi in TSPL thật.
            PrintAttempt::create([
                'print_job_id' => $job->id,
                'attempt_no' => 1,
                'status' => 'PRINTED',
                'started_at' => now(),
                'finished_at' => now(),
                'error_detail' => null,
            ]);
            $job->processed_at = now();
            $job->save();
            $this->eventService->log($job->id, 'SENT_TO_PRINTER', $eventContext);
            $this->eventService->log($job->id, 'PRINT_SUCCEEDED', $eventContext);
        }

        return $job;
    }
}
