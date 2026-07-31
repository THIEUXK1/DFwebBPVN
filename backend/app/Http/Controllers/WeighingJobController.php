<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MaterialLabel;
use App\Models\OperationClient;
use App\Models\PrintJob;
use App\Models\ProductionBatch;
use App\Models\ScaleMeasurement;
use App\Models\User;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Services\RealtimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WeighingJobController extends Controller
{
    /**
     * Get details of a single weighing job.
     */
    public function show($id)
    {
        $job = WeighingJob::with(['batch.machine', 'batch.tank', 'items.material'])->findOrFail($id);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $job,
        ]);
    }

    /**
     * Lô cân đang dở của 1 trạm cân (workstation) — mỗi trạm chỉ dùng cho đúng 1 máy nên
     * KHÔNG bao giờ có 2 job "đang chạy" cùng lúc. Dùng để khôi phục lại đúng job đang cân
     * khi trang /weighing-station được mở lại (F5, mất mạng, tắt máy...) mà KHÔNG bắt thao
     * tác viên quét lại QR — job đã được lưu xuống DB ngay từ lúc quét (handleOrderScan/
     * scanRawDyeQr), chỉ có state phía frontend (Vue ref trong RAM) bị mất khi reload.
     */
    public function activeForWorkstation(Request $request)
    {
        $request->validate([
            'workstation_id' => 'required|integer|exists:operation_clients,id',
        ]);

        $job = WeighingJob::with(['batch.machine', 'batch.tank', 'items.material'])
            ->where('assigned_operation_client_id', $request->input('workstation_id'))
            ->where('status', '!=', 'COMPLETED')
            ->orderByDesc('created_at')
            ->first();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'job' => $job,
                'batch' => $job?->batch,
            ],
        ]);
    }

    /**
     * Weigh a single item in the job sequence.
     */
    public function weighItem(Request $request, $id)
    {
        $request->validate([
            // Không còn 'min:0' — cân cộng dồn trên cùng 1 đĩa, net có thể lệch bất thường so
            // với bì đã chốt; chặn min:0 ở đây sẽ khiến ca đó không bao giờ lưu được.
            'weight' => 'required|numeric',
            'scale_device_id' => 'required|string',
            'stable' => 'required|boolean',
            // Trừ bì (tare/delta) — xác nhận nghiệp vụ 2026-07-18 (CH-BUS-006): cốc/khay/thùng
            // đặt lên cân trước khi cân vật tư coi là bì, phải trừ đi (đúng VBA
            // Mod_delta_raw.Delta_Begin/AutoFlow_OnWeight). Frontend đã tự trừ và gửi 'weight'
            // = NET (giá trị đã trừ bì, dùng để so dung sai — KHÔNG đổi hành vi so sánh hiện
            // có). tare_weight/gross_weight chỉ optional, phục vụ audit minh bạch.
            'tare_weight' => 'sometimes|nullable|numeric|min:0',
            'gross_weight' => 'sometimes|nullable|numeric|min:0',
            // RACK (rebuild bảng 9 dòng RACK/DYE CODE/WEIGHT/PROCESS đúng scaleform.frm VBA
            // gốc) — có thể đã được tự điền từ QR (ScannerController::handleOrderScan) hoặc
            // do thao tác viên tự gõ/sửa tay trên từng dòng trước khi xác nhận cân. Thuần
            // metadata hiển thị/đối soát, không ảnh hưởng logic so dung sai.
            'rack_code' => 'sometimes|nullable|string|max:20',
        ]);

        $item = WeighingJobItem::with('job.batch')->findOrFail($id);
        $job = $item->job;
        $batch = $job->batch;

        // Vật tư đã cân xong = chốt vĩnh viễn, không cho ghi đè lại bì/kết quả qua endpoint
        // này nữa (phản hồi 2026-07-30). Đây là hàng rào phía server — hàng rào phía
        // frontend (WeighingRackTable.vue) chỉ chặn UI, không chặn được ai gọi thẳng API.
        if ($item->status === 'COMPLETED') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Vật tư này đã cân xong — không thể sửa lại bì hoặc cân lại.',
                'error_code' => 'ITEM_ALREADY_COMPLETED',
            ], 409);
        }

        $measuredWeight = (float) $request->input('weight');
        $tareWeight = $request->input('tare_weight');
        $grossWeight = $request->input('gross_weight');
        $rackCode = $request->input('rack_code');
        $scaleDeviceId = $request->input('scale_device_id');
        $stable = (bool) $request->input('stable');

        // p0-c-scale-algorithm.md Mục A.4: trước đây 'stable' chỉ được validate là boolean hợp
        // lệ, KHÔNG có bước chặn nào dùng giá trị này — client có thể gửi thẳng stable:false vẫn
        // được lưu bình thường (nút Xác nhận phía frontend chỉ disable UI, không chặn được ai
        // gọi thẳng API). VBA chặn cứng: chỉ đẩy vào CheckRange/lưu khi StableFilter báo ổn định
        // 2 lần đọc liên tiếp giống hệt. Chặn ở đây để không phụ thuộc hoàn toàn vào UI.
        if (! $stable) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau trước khi xác nhận.',
                'error_code' => 'NOT_STABLE',
            ], 422);
        }

        // KHÔNG chặn khi ngoài dung sai — port đúng VBA btnSave_Click (yêu cầu 2026-07-30):
        // thao tác viên lưu được mọi lần cân, hệ thống chỉ GẮN NHÃN ĐẠT/KHÔNG ĐẠT (xem
        // WeighingJobItem::getProcessStatusAttribute, tương đương cột processColor của Access).
        // Vì vậy cũng không còn luồng override (PIN Giám sát + lý do + AuditLog
        // WEIGH_TOLERANCE_OVERRIDE) — không còn hành vi "phê duyệt" nào để ghi nhận. Nhãn vẫn
        // tái dựng được vĩnh viễn từ planned_weight/tolerance_* đã snapshot trên chính item.

        $client = $request->attributes->get('operation_client');
        if (! $client) {
            $code = $request->header('X-Workstation-Code') ?? $request->input('workstation_code');
            if ($code) {
                $client = OperationClient::where('code', $code)->first();
            }
        }

        if ($client) {
            $scaleDevice = $client->devices()
                ->where('device_type', 'SCALE')
                ->where('devices.code', $scaleDeviceId)
                ->where('operation_client_devices.enabled', true)
                ->first();
            if (! $scaleDevice) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Thiết bị cân '{$scaleDeviceId}' chưa được gán hoặc chưa được kích hoạt cho máy trạm này.",
                ], 400);
            }
        }

        return DB::transaction(function () use ($item, $job, $batch, $measuredWeight, $tareWeight, $grossWeight, $rackCode, $request) {
            // Save to scale measurements (create new for every weigh attempt, no overwrites)
            $measurement = ScaleMeasurement::create([
                'legacy_source' => 'web_app',
                'legacy_id' => time() + rand(1, 100000), // mock integer
                'legacy_batch_id' => $batch->legacy_batch_id,
                'color' => $batch->color,
                'product_code' => $batch->product_code,
                'machine_code' => $batch->machine ? $batch->machine->code : 'N/A',
                'level_code' => $batch->level_code,
                'rack_code' => $rackCode,
                'dye_code' => $item->material_code,
                'weight' => $measuredWeight,
                'tare_weight' => $tareWeight,
                'gross_weight' => $grossWeight,
                'measured_at' => Carbon::now(),
                'material_type' => $job->job_type === 'DYE' ? 'DYE' : 'CHEMICAL',
                'weighing_job_item_id' => $item->id,
            ]);

            // Update item details
            $item->actual_weight = $measuredWeight;
            $item->rack_code = $rackCode;
            $item->status = 'COMPLETED';
            $item->completed_at = Carbon::now();
            $item->save();

            // Check if job is completed
            $unfinishedItems = WeighingJobItem::where('weighing_job_id', $job->id)
                ->where('status', '!=', 'COMPLETED')
                ->count();

            if ($unfinishedItems === 0) {
                $job->status = 'COMPLETED';
                $job->completed_at = Carbon::now();
                $job->save();

                // Trigger alerts rule validation
                RealtimeService::publish('weighing_job.completed', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);

                // Update Overall Production Batch Status based on remaining jobs
                // A production batch is marked as "WEIGHED" only when ALL generated jobs are COMPLETED.
                // If some jobs are completed but others are not, status is "PARTIALLY_WEIGHED".
                $allJobs = WeighingJob::where('production_batch_id', $batch->id)->get();
                $completedJobs = $allJobs->where('status', 'COMPLETED')->count();
                $totalJobsCount = $allJobs->count();

                if ($completedJobs === $totalJobsCount) {
                    $batch->status = 'WEIGHED';
                } else {
                    $batch->status = 'PARTIALLY_WEIGHED';
                }
                $batch->save();
            } else {
                $job->status = 'IN_PROGRESS';
                $job->save();
            }

            // Return next pending item in sequence
            $nextItem = WeighingJobItem::where('weighing_job_id', $job->id)
                ->where('status', '!=', 'COMPLETED')
                ->orderBy('sequence_no', 'asc')
                ->first();

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Lưu số cân thành công.',
                'data' => [
                    'item' => $item,
                    'job_completed' => ($unfinishedItems === 0),
                    'next_item' => $nextItem,
                ],
            ]);
        });
    }

    /**
     * Cân lại từ đầu cả Mẻ nhuộm hiện tại (phản hồi 2026-07-30) — dùng khi thao tác viên
     * phát hiện sai sót nghiêm trọng (nhầm vật tư, tràn/đổ, cân sai từ đầu...) và muốn bỏ
     * hết kết quả đã cân của TOÀN BỘ job, quay lại vật tư đầu tiên. Yêu cầu xác nhận rõ ràng
     * ở frontend (tick checkbox cảnh báo) + bắt buộc nhập lý do, và ghi Audit Log trước/sau
     * đầy đủ vì đây là hành động HỦY kết quả cân đã lưu (không phải sự cố kỹ thuật thông
     * thường). KHÔNG đụng tới bảng scale_measurements — lịch sử mỗi lần cân vẫn giữ nguyên
     * bất biến để đối soát (database-safety.md mục 5), chỉ reset trạng thái/kết quả trên
     * WeighingJobItem/WeighingJob/ProductionBatch.
     */
    public function restart(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $job = WeighingJob::with(['items', 'batch'])->findOrFail($id);
        $batch = $job->batch;

        // Vật tư đã rời khỏi trạm cân (đang vận chuyển/đã nạp máy/đã hoàn tất) thì không thể
        // "cân lại từ đầu" nữa — dữ liệu cân đã được dùng cho các bước sau, sửa ngược lại sẽ
        // gây sai lệch đối soát.
        if ($batch && in_array($batch->status, ['IN_TRANSIT', 'ARRIVED_AT_TANK', 'SENT', 'DONE'])) {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Lô {$batch->legacy_batch_id} đã qua công đoạn cân (trạng thái {$batch->status}) — không thể cân lại từ đầu.",
            ], 409);
        }

        return DB::transaction(function () use ($job, $batch, $request) {
            $beforeItems = $job->items->map(fn ($i) => [
                'id' => $i->id,
                'material_code' => $i->material_code,
                'status' => $i->status,
                'actual_weight' => $i->actual_weight,
                'rack_code' => $i->rack_code,
                'override_approved' => $i->override_approved,
                'override_reason' => $i->override_reason,
            ])->toArray();
            $beforeJobStatus = $job->status;
            $beforeBatchStatus = $batch?->status;

            foreach ($job->items as $item) {
                $item->status = 'PENDING';
                $item->actual_weight = null;
                $item->completed_at = null;
                $item->override_approved = false;
                $item->override_reason = null;
                $item->override_by = null;
                $item->save();
            }

            $job->status = 'RECEIVED';
            $job->completed_at = null;
            $job->save();

            if ($batch && in_array($batch->status, ['WEIGHED', 'PARTIALLY_WEIGHED'])) {
                $batch->status = 'WEIGHING';
                $batch->save();
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'WEIGHING_JOB_RESTART',
                'entity_type' => 'WeighingJob',
                'entity_id' => $job->id,
                'before_data' => [
                    'job_status' => $beforeJobStatus,
                    'batch_status' => $beforeBatchStatus,
                    'items' => $beforeItems,
                ],
                'after_data' => [
                    'job_status' => $job->status,
                    'batch_status' => $batch?->status,
                    'reason' => $request->input('reason'),
                ],
                'client_ip' => $request->ip(),
            ]);

            $job->refresh()->load(['items.material', 'batch.machine', 'batch.tank']);

            RealtimeService::publish('weighing_job.restarted', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch?->machine_id, $batch?->id);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Đã đặt lại toàn bộ Mẻ nhuộm về trạng thái chưa cân.',
                'data' => [
                    'job' => $job,
                    'batch' => $batch,
                ],
            ]);
        });
    }

    /**
     * Generate and Print Material QR Label.
     */
    public function printLabel(Request $request, $id)
    {
        $request->validate([
            'workstation_code' => 'sometimes|string|exists:operation_clients,code',
        ]);

        $job = WeighingJob::with('items')->findOrFail($id);
        $batch = ProductionBatch::with('machine')->where('id', $job->production_batch_id)->firstOrFail();

        $client = $request->attributes->get('operation_client');
        $workstationCode = $request->input('workstation_code') ?? ($client ? $client->code : null);
        if (! $workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        if ($job->status !== 'COMPLETED') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chỉ được phép in tem sau khi nhiệm vụ cân đã hoàn tất.',
            ], 422);
        }

        return DB::transaction(function () use ($job, $batch, $workstation) {
            $totalWeight = $job->items->sum('actual_weight');

            // 1. Create Material Label
            $label = MaterialLabel::create([
                'production_batch_id' => $batch->id,
                'weighing_job_id' => $job->id,
                'material_type' => $job->job_type,
                'weight' => $totalWeight,
                'reprint_count' => 0,
            ]);

            // Save label ID on items
            WeighingJobItem::where('weighing_job_id', $job->id)->update([
                'label_id' => $label->id,
            ]);

            // 2. Generate TSPL Command with internal QR Code Spec
            // QR spec format: DF:MATERIAL_LABEL:<uuid>
            $qrToken = "DF:MATERIAL_LABEL:{$label->id}";
            $machineCode = $batch->machine ? $batch->machine->code : 'VD-COMMON';

            $tspl = "SIZE 80 mm, 50 mm\r\n".
                    "GAP 3 mm, 0 mm\r\n".
                    "CLS\r\n".
                    "QRCODE 50,50,L,6,A,0,\"$qrToken\"\r\n".
                    "TEXT 50,220,\"3\",0,1,1,\"LOT: {$batch->legacy_batch_id}\"\r\n".
                    "TEXT 50,250,\"3\",0,1,1,\"LOAI: {$job->job_type}\"\r\n".
                    'TEXT 50,280,"3",0,1,1,"KG: '.number_format($totalWeight / 1000, 2)."\"\r\n".
                    "TEXT 50,310,\"3\",0,1,1,\"MAY: $machineCode\"\r\n".
                    "PRINT 1\r\n";

            // 3. In qua trình duyệt (window.print(), không qua TSPL/Local Agent, yêu cầu
            // 2026-07-30) — đánh dấu PRINTED ngay, không để status PENDING vì
            // AgentJobsController::getJobs sẽ lấy job PENDING của đúng workstation này và
            // gửi lệnh TSPL thật xuống máy in vật lý, in trùng bản đã in qua trình duyệt.
            $printJob = PrintJob::create([
                'workstation_id' => $workstation->code,
                'label_payload' => $tspl,
                'printer_connection_type' => 'BROWSER',
                'printer_address' => 'BROWSER',
                'status' => 'PRINTED',
                'processed_at' => now(),
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Đã tạo tem — in qua hộp thoại in của trình duyệt.',
                'data' => [
                    'label_id' => $label->id,
                    'qr_token' => $qrToken,
                    'print_job' => $printJob,
                ],
            ]);
        });
    }

    /**
     * In phiếu cân tổng hợp (slip) — port từ VBA `scaleform.btnPrint_Click` (workbook
     * semiauto-small-scale): bảng COLOR/CODE/MACHINE/LEVEL + từng dòng RACK/vật tư/khối
     * lượng/trạng thái. VBA gốc không giới hạn trạng thái job (in được cả khi đang cân dở,
     * dòng chưa cân để trống) — giữ đúng hành vi đó, khác với printLabel() (tem vật tư) vốn
     * bắt buộc job COMPLETED. In qua hộp thoại in của trình duyệt (window.print()), không
     * qua TSPL/Local Agent — xem ghi chú ở printLabel().
     */
    public function printSlip(Request $request, $id)
    {
        $request->validate([
            'workstation_code' => 'sometimes|string|exists:operation_clients,code',
        ]);

        $job = WeighingJob::with('items.material')->findOrFail($id);
        $batch = ProductionBatch::with('machine')->where('id', $job->production_batch_id)->firstOrFail();

        $client = $request->attributes->get('operation_client');
        $workstationCode = $request->input('workstation_code') ?? ($client ? $client->code : null);
        if (! $workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        $machineCode = $batch->machine ? $batch->machine->code : 'N/A';

        $tspl = "SIZE 76 mm, 130 mm\r\n".
                "GAP 2 mm, 0 mm\r\n".
                "DIRECTION 1,0\r\n".
                "REFERENCE 0,0\r\n".
                "CLS\r\n".
                "TEXT 15,15,\"3\",0,1,1,\"DF_WEIGHING_SLIP\"\r\n".
                "TEXT 15,50,\"2\",0,1,1,\"MAU: {$batch->color}\"\r\n".
                "TEXT 15,75,\"2\",0,1,1,\"HANG: {$batch->product_code}\"\r\n".
                "TEXT 15,100,\"2\",0,1,1,\"MAY: {$machineCode}\"\r\n".
                'TEXT 15,125,"2",0,1,1,"MUC: '.($batch->level_code ?? '')."\"\r\n";

        // Bảng RACK/DYE CODE/MT/TT/STATUS — cột thẳng hàng theo tọa độ x cố định thay vì gộp
        // hết vào 1 dòng chữ chạy dài (phản hồi 2026-07-30: "tôi muốn nó là 1 table"), đúng
        // tinh thần bảng gốc VBA (Label11-14: RACK/DYE CODE/WEIGHT/PROCESS trên scaleform).
        $colRack = 15;
        $colDye = 90;
        $colMt = 260;
        $colTt = 380;
        $colStatus = 500;

        $tspl .= "TEXT {$colRack},155,\"1\",0,1,1,\"RACK\"\r\n".
                 "TEXT {$colDye},155,\"1\",0,1,1,\"DYE CODE\"\r\n".
                 "TEXT {$colMt},155,\"1\",0,1,1,\"MT\"\r\n".
                 "TEXT {$colTt},155,\"1\",0,1,1,\"TT\"\r\n".
                 "TEXT {$colStatus},155,\"1\",0,1,1,\"STATUS\"\r\n";

        $y = 178;
        foreach ($job->items as $idx => $item) {
            // ACCEPTED / REJECTED / PENDING — đúng cột processColor của VBA btnSave_Click,
            // suy từ chính dung sai đã snapshot trên item (xem WeighingJobItem::process_status).
            $statusText = $item->process_status;
            // In cả số cân MỤC TIÊU (MT, planned_weight) lẫn số cân THỰC TẾ (TT, actual_weight)
            // — trước đây chỉ in actual, không đối chiếu được ngay trên tem là cân đủ/thiếu/dư
            // bao nhiêu so với định mức (phản hồi 2026-07-30).
            $plannedText = number_format((float) $item->planned_weight, 2).'g';
            $weightText = $item->actual_weight !== null ? number_format((float) $item->actual_weight, 2).'g' : '---';
            $seq = $item->sequence_no ?? ($idx + 1);
            $rackText = $item->rack_code !== null && $item->rack_code !== '' ? $item->rack_code : (string) $seq;
            $dyeText = str_replace('"', '', $item->material_code);

            $tspl .= "TEXT {$colRack},{$y},\"1\",0,1,1,\"".str_replace('"', '', $rackText)."\"\r\n".
                     "TEXT {$colDye},{$y},\"1\",0,1,1,\"{$dyeText}\"\r\n".
                     "TEXT {$colMt},{$y},\"1\",0,1,1,\"{$plannedText}\"\r\n".
                     "TEXT {$colTt},{$y},\"1\",0,1,1,\"{$weightText}\"\r\n".
                     "TEXT {$colStatus},{$y},\"1\",0,1,1,\"{$statusText}\"\r\n";
            $y += 22;
        }

        $tspl .= "TEXT 15,{$y},\"1\",0,1,1,\"In luc: ".Carbon::now()->format('d/m/Y H:i:s')."\"\r\n";
        $tspl .= "PRINT 1,1\r\n";

        // In qua trình duyệt (yêu cầu 2026-07-30) — xem ghi chú ở printLabel().
        $printJob = PrintJob::create([
            'workstation_id' => $workstation->code,
            'label_payload' => $tspl,
            'printer_connection_type' => 'BROWSER',
            'printer_address' => 'BROWSER',
            'status' => 'PRINTED',
            'processed_at' => now(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã tạo phiếu cân — in qua hộp thoại in của trình duyệt.',
            'data' => $printJob,
        ], 201);
    }

    /**
     * WS-007: look up a single material label by id — the scan path for the standalone print
     * station. Read-only; printing/reprinting is a separate explicit action.
     */
    public function showLabel($id)
    {
        $label = MaterialLabel::with(['batch.machine', 'job.items.material', 'material'])->findOrFail($id);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $label,
        ]);
    }

    /**
     * WS-007: manual fallback for the standalone print station — look up printed material labels
     * by the batch's human-readable legacy_batch_id when the physical label QR can't be scanned.
     */
    public function searchLabels(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $labels = MaterialLabel::with(['batch.machine', 'material'])
            ->whereHas('batch', function ($q) use ($request) {
                $q->where('legacy_batch_id', 'like', '%'.$request->input('q').'%');
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $labels,
        ]);
    }

    /**
     * Reprint label with audits and reason logs.
     */
    public function reprintLabel(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
            'workstation_code' => 'sometimes|string|exists:operation_clients,code',
        ]);

        $user = Auth::user();
        if (! $user) {
            $pin = $request->input('manager_pin');
            if (! $pin) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'message' => 'Yêu cầu nhập mã PIN của Giám sát (Supervisor) để in lại tem.',
                ], 403);
            }
            $user = User::verifyManagerPin($pin);
            if (! $user) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'message' => 'Mã PIN giám sát không đúng hoặc không có quyền.',
                ], 403);
            }
        }

        $label = MaterialLabel::with('job.batch.machine')->findOrFail($id);

        $client = $request->attributes->get('operation_client');
        $workstationCode = $request->input('workstation_code') ?? ($client ? $client->code : null);
        if (! $workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        return DB::transaction(function () use ($label, $workstation, $request, $user) {
            $label->reprint_count += 1;
            $label->reprint_reason = $request->input('reason');
            $label->save();

            $batch = $label->job->batch;
            $qrToken = "DF:MATERIAL_LABEL:{$label->id}";
            $machineCode = $batch->machine ? $batch->machine->code : 'VD-COMMON';

            $tspl = "SIZE 80 mm, 50 mm\r\n".
                    "GAP 3 mm, 0 mm\r\n".
                    "CLS\r\n".
                    "QRCODE 50,50,L,6,A,0,\"$qrToken\"\r\n".
                    "TEXT 50,220,\"3\",0,1,1,\"LOT: {$batch->legacy_batch_id} (REPRINT #{$label->reprint_count})\"\r\n".
                    "TEXT 50,250,\"3\",0,1,1,\"LOAI: {$label->material_type}\"\r\n".
                    'TEXT 50,280,"3",0,1,1,"KG: '.number_format($label->weight / 1000, 2)."\"\r\n".
                    "TEXT 50,310,\"3\",0,1,1,\"MAY: $machineCode\"\r\n".
                    "PRINT 1\r\n";

            // In qua trình duyệt (window.print(), không qua TSPL/Local Agent, yêu cầu
            // 2026-07-30) — đánh dấu PRINTED ngay, không để status PENDING vì
            // AgentJobsController::getJobs sẽ lấy job PENDING của đúng workstation này và
            // gửi lệnh TSPL thật xuống máy in vật lý, in trùng bản đã in qua trình duyệt.
            $printJob = PrintJob::create([
                'workstation_id' => $workstation->code,
                'label_payload' => $tspl,
                'printer_connection_type' => 'BROWSER',
                'printer_address' => 'BROWSER',
                'status' => 'PRINTED',
                'processed_at' => now(),
            ]);

            // Save reprint to audit log
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'REPRINT_MATERIAL_LABEL',
                'entity_type' => 'MaterialLabel',
                'entity_id' => $label->id,
                'before_data' => ['reprint_count' => $label->reprint_count - 1],
                'after_data' => ['reprint_count' => $label->reprint_count, 'reason' => $label->reprint_reason],
                'client_ip' => $request->ip(),
            ]);

            // Thuộc tính tạm chỉ để trả về xem trước tem (LabelPreview.vue) — KHÔNG lưu DB
            // (không có cột này trên material_labels, gán sau lần save() cuối cùng ở trên
            // nên không có nguy cơ Eloquent cố UPDATE cột không tồn tại).
            $label->setAttribute('label_payload', $tspl);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Yêu cầu in lại tem đã gửi thành công.',
                'data' => $label,
            ]);
        });
    }
}
