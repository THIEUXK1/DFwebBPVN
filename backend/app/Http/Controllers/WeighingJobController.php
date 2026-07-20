<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\ScaleMeasurement;
use App\Models\MaterialLabel;
use App\Models\ProductionBatch;
use App\Models\PrintJob;
use App\Models\OperationClient;
use App\Models\AuditLog;
use App\Services\RealtimeService;
use Carbon\Carbon;
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
            'data' => $job
        ]);
    }

    /**
     * Weigh a single item in the job sequence.
     */
    public function weighItem(Request $request, $id)
    {
        $request->validate([
            'weight' => 'required|numeric|min:0',
            'scale_device_id' => 'required|string',
            'stable' => 'required|boolean',
            'override_approved' => 'sometimes|boolean',
            'override_reason' => 'sometimes|nullable|string',
            // Trừ bì (tare/delta) — xác nhận nghiệp vụ 2026-07-18 (CH-BUS-006): cốc/khay/thùng
            // đặt lên cân trước khi cân vật tư coi là bì, phải trừ đi (đúng VBA
            // Mod_delta_raw.Delta_Begin/AutoFlow_OnWeight). Frontend đã tự trừ và gửi 'weight'
            // = NET (giá trị đã trừ bì, dùng để so dung sai — KHÔNG đổi hành vi so sánh hiện
            // có). tare_weight/gross_weight chỉ optional, phục vụ audit minh bạch.
            'tare_weight' => 'sometimes|nullable|numeric|min:0',
            'gross_weight' => 'sometimes|nullable|numeric|min:0',
        ]);

        $item = WeighingJobItem::with('job.batch')->findOrFail($id);
        $job = $item->job;
        $batch = $job->batch;

        $measuredWeight = (float)$request->input('weight');
        $tareWeight = $request->input('tare_weight');
        $grossWeight = $request->input('gross_weight');
        $scaleDeviceId = $request->input('scale_device_id');
        $stable = (bool)$request->input('stable');
        $overrideApproved = (bool)$request->input('override_approved', false);
        $overrideReason = $request->input('override_reason');

        // p0-c-scale-algorithm.md Mục A.4: trước đây 'stable' chỉ được validate là boolean hợp
        // lệ, KHÔNG có bước chặn nào dùng giá trị này — client có thể gửi thẳng stable:false vẫn
        // được lưu bình thường (nút Xác nhận phía frontend chỉ disable UI, không chặn được ai
        // gọi thẳng API). VBA chặn cứng: chỉ đẩy vào CheckRange/lưu khi StableFilter báo ổn định
        // 2 lần đọc liên tiếp giống hệt. Chặn ở đây để không phụ thuộc hoàn toàn vào UI.
        if (!$stable) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau trước khi xác nhận.',
                'error_code' => 'NOT_STABLE',
            ], 422);
        }

        // Tolerance Check
        $target = (float)$item->planned_weight;
        $toleranceMinus = (float)$item->tolerance_minus;
        $tolerancePlus = (float)$item->tolerance_plus;

        $minAllowed = $target - $toleranceMinus;
        $maxAllowed = $target + $tolerancePlus;

        $inRange = ($measuredWeight >= $minAllowed && $measuredWeight <= $maxAllowed);

        if (!$inRange && !$overrideApproved) {
            $item->status = 'OUT_OF_TOLERANCE';
            $item->save();

            return response()->json([
                'status' => 'ERROR',
                'message' => "Khối lượng cân ngoài dung sai cho phép! ({$measuredWeight}g vs mục tiêu {$target}g). Cần sự chấp thuận của Shift Leader để ghi đè.",
                'error_code' => 'OUT_OF_TOLERANCE',
                'data' => [
                    'min_allowed' => $minAllowed,
                    'max_allowed' => $maxAllowed,
                    'measured' => $measuredWeight
                ]
            ], 422);
        }

        $client = $request->attributes->get('operation_client');
        if (!$client) {
            $code = $request->header('X-Workstation-Code') ?? $request->input('workstation_code');
            if ($code) {
                $client = \App\Models\OperationClient::where('code', $code)->first();
            }
        }

        if ($client) {
            $scaleDevice = $client->devices()
                ->where('device_type', 'SCALE')
                ->where('devices.code', $scaleDeviceId)
                ->where('operation_client_devices.enabled', true)
                ->first();
            if (!$scaleDevice) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Thiết bị cân '{$scaleDeviceId}' chưa được gán hoặc chưa được kích hoạt cho máy trạm này."
                ], 400);
            }
        }

        $isOverride = !$inRange && $overrideApproved;
        $overrideUser = null;

        if ($isOverride) {
            $user = Auth::user();

            if (!$user) {
                $pin = $request->input('manager_pin');
                if (!$pin) {
                    return response()->json([
                        'status' => 'FORBIDDEN',
                        'message' => 'Yêu cầu nhập mã PIN của Giám sát (Supervisor) để duyệt override dung sai.'
                    ], 403);
                }
                $user = \App\Models\User::verifyManagerPin($pin);
                if (!$user) {
                    return response()->json([
                        'status' => 'FORBIDDEN',
                        'message' => 'Mã PIN giám sát không đúng hoặc không có quyền.'
                    ], 403);
                }
            }

            if (!$user->hasRole('SUPERVISOR') && !$user->hasRole('ADMIN')) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'message' => 'Chỉ Giám sát viên (Supervisor) hoặc Admin mới có quyền ký duyệt Override dung sai cân.'
                ], 403);
            }

            if (!$overrideReason || strlen(trim($overrideReason)) < 5) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Vui lòng nhập lý do ghi đè dung sai (tối thiểu 5 ký tự).',
                    'error_code' => 'OVERRIDE_REASON_REQUIRED'
                ], 422);
            }
            $overrideUser = $user;
        }

        return DB::transaction(function () use ($item, $job, $batch, $measuredWeight, $tareWeight, $grossWeight, $scaleDeviceId, $stable, $overrideApproved, $isOverride, $overrideReason, $request, $overrideUser) {
            // Save to scale measurements (create new for every weigh attempt, no overwrites)
            $measurement = ScaleMeasurement::create([
                'legacy_source' => 'web_app',
                'legacy_id' => time() + rand(1, 100000), // mock integer
                'legacy_batch_id' => $batch->legacy_batch_id,
                'color' => $batch->color,
                'product_code' => $batch->product_code,
                'machine_code' => $batch->machine ? $batch->machine->code : 'N/A',
                'level_code' => $batch->level_code,
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
            $item->status = 'COMPLETED';
            $item->completed_at = Carbon::now();

            if ($isOverride) {
                $item->override_approved = true;
                $item->override_reason = $overrideReason;
                $item->override_by = $overrideUser->id;
            }

            $item->save();

            if ($isOverride) {
                AuditLog::create([
                    'user_id' => $overrideUser->id,
                    'action' => 'WEIGH_TOLERANCE_OVERRIDE',
                    'entity_type' => 'WeighingJobItem',
                    'entity_id' => $item->id,
                    'before_data' => [
                        'planned_weight' => $item->planned_weight,
                        'tolerance_minus' => $item->tolerance_minus,
                        'tolerance_plus' => $item->tolerance_plus,
                    ],
                    'after_data' => [
                        'actual_weight' => $measuredWeight,
                        'material_code' => $item->material_code,
                        'batch_id' => $batch->id,
                        'reason' => $overrideReason,
                    ],
                    'client_ip' => $request->ip(),
                ]);
            }

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
                ]
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
        if (!$workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        if ($job->status !== 'COMPLETED') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chỉ được phép in tem sau khi nhiệm vụ cân đã hoàn tất.'
            ], 422);
        }

        // Verify printer configuration
        $printerDevice = $workstation->devices()
            ->where('device_type', 'PRINTER')
            ->where('operation_client_devices.enabled', true)
            ->where('operation_client_devices.is_default', true)
            ->first();
        if (!$printerDevice) {
            $printerDevice = $workstation->devices()
                ->where('device_type', 'PRINTER')
                ->where('operation_client_devices.enabled', true)
                ->first();
        }

        if (!$printerDevice) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chưa cấu hình máy in cho máy trạm này.'
            ], 400);
        }

        $printerAddress = $printerDevice->code;

        return DB::transaction(function () use ($job, $batch, $workstation, $printerAddress) {
            $totalWeight = $job->items->sum('actual_weight');

            // 1. Create Material Label
            $label = MaterialLabel::create([
                'production_batch_id' => $batch->id,
                'weighing_job_id' => $job->id,
                'material_type' => $job->job_type,
                'weight' => $totalWeight,
                'reprint_count' => 0
            ]);

            // Save label ID on items
            WeighingJobItem::where('weighing_job_id', $job->id)->update([
                'label_id' => $label->id
            ]);

            // 2. Generate TSPL Command with internal QR Code Spec
            // QR spec format: DF:MATERIAL_LABEL:<uuid>
            $qrToken = "DF:MATERIAL_LABEL:{$label->id}";
            $machineCode = $batch->machine ? $batch->machine->code : 'VD-COMMON';
            
            $tspl = "SIZE 80 mm, 50 mm\r\n" .
                    "GAP 3 mm, 0 mm\r\n" .
                    "CLS\r\n" .
                    "QRCODE 50,50,L,6,A,0,\"$qrToken\"\r\n" .
                    "TEXT 50,220,\"3\",0,1,1,\"LOT: {$batch->legacy_batch_id}\"\r\n" .
                    "TEXT 50,250,\"3\",0,1,1,\"LOAI: {$job->job_type}\"\r\n" .
                    "TEXT 50,280,\"3\",0,1,1,\"KG: " . number_format($totalWeight/1000, 2) . "\"\r\n" .
                    "TEXT 50,310,\"3\",0,1,1,\"MAY: $machineCode\"\r\n" .
                    "PRINT 1\r\n";

            // 3. Spool print job
            $printJob = PrintJob::create([
                'workstation_id' => $workstation->code,
                'label_payload' => $tspl,
                'printer_connection_type' => 'USB',
                'printer_address' => $printerAddress,
                'status' => 'PENDING',
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Lệnh in đã được gửi đến Print Agent.',
                'data' => [
                    'label_id' => $label->id,
                    'qr_token' => $qrToken,
                    'print_job' => $printJob
                ]
            ]);
        });
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
                $q->where('legacy_batch_id', 'like', '%' . $request->input('q') . '%');
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
        if (!$user) {
            $pin = $request->input('manager_pin');
            if (!$pin) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'message' => 'Yêu cầu nhập mã PIN của Giám sát (Supervisor) để in lại tem.'
                ], 403);
            }
            $user = \App\Models\User::verifyManagerPin($pin);
            if (!$user) {
                return response()->json([
                    'status' => 'FORBIDDEN',
                    'message' => 'Mã PIN giám sát không đúng hoặc không có quyền.'
                ], 403);
            }
        }

        $label = MaterialLabel::with('job.batch.machine')->findOrFail($id);
        
        $client = $request->attributes->get('operation_client');
        $workstationCode = $request->input('workstation_code') ?? ($client ? $client->code : null);
        if (!$workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        // Verify printer configuration
        $printerDevice = $workstation->devices()
            ->where('device_type', 'PRINTER')
            ->where('operation_client_devices.enabled', true)
            ->where('operation_client_devices.is_default', true)
            ->first();
        if (!$printerDevice) {
            $printerDevice = $workstation->devices()
                ->where('device_type', 'PRINTER')
                ->where('operation_client_devices.enabled', true)
                ->first();
        }

        if (!$printerDevice) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chưa cấu hình máy in cho máy trạm này.'
            ], 400);
        }

        $printerAddress = $printerDevice->code;

        return DB::transaction(function () use ($label, $workstation, $request, $user, $printerAddress) {
            $label->reprint_count += 1;
            $label->reprint_reason = $request->input('reason');
            $label->save();

            $batch = $label->job->batch;
            $qrToken = "DF:MATERIAL_LABEL:{$label->id}";
            $machineCode = $batch->machine ? $batch->machine->code : 'VD-COMMON';

            $tspl = "SIZE 80 mm, 50 mm\r\n" .
                    "GAP 3 mm, 0 mm\r\n" .
                    "CLS\r\n" .
                    "QRCODE 50,50,L,6,A,0,\"$qrToken\"\r\n" .
                    "TEXT 50,220,\"3\",0,1,1,\"LOT: {$batch->legacy_batch_id} (REPRINT #{$label->reprint_count})\"\r\n" .
                    "TEXT 50,250,\"3\",0,1,1,\"LOAI: {$label->material_type}\"\r\n" .
                    "TEXT 50,280,\"3\",0,1,1,\"KG: " . number_format($label->weight/1000, 2) . "\"\r\n" .
                    "TEXT 50,310,\"3\",0,1,1,\"MAY: $machineCode\"\r\n" .
                    "PRINT 1\r\n";

            $printJob = PrintJob::create([
                'workstation_id' => $workstation->code,
                'label_payload' => $tspl,
                'printer_connection_type' => 'USB',
                'printer_address' => $printerAddress,
                'status' => 'PENDING',
            ]);

            // Save reprint to audit log
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'REPRINT_MATERIAL_LABEL',
                'entity_type' => 'MaterialLabel',
                'entity_id' => $label->id,
                'before_data' => ['reprint_count' => $label->reprint_count - 1],
                'after_data' => ['reprint_count' => $label->reprint_count, 'reason' => $label->reprint_reason],
                'client_ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Yêu cầu in lại tem đã gửi thành công.',
                'data' => $label
            ]);
        });
    }

    /**
     * Push raw weight sample from local scale agent (WS-004/005).
     */
    public function pushSample(Request $request, $id)
    {
        $request->validate([
            'device_id' => 'required|uuid',
            'sequence_no' => 'required|integer',
            'raw_value' => 'required|string',
            'device_timestamp' => 'sometimes|string',
        ]);

        $service = app(\App\Services\WeighingCoreService::class);
        $sample = $service->addSample(
            $id,
            $request->input('device_id'),
            $request->input('sequence_no'),
            $request->input('raw_value'),
            $request->input('device_timestamp')
        );

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $sample
        ], 201);
    }

    /**
     * Get the latest stable reading for the item.
     */
    public function getStableReading($id)
    {
        $stableSample = \App\Models\WeighingSample::where('job_item_id', $id)
            ->where('is_stable', true)
            ->orderBy('sequence_no', 'desc')
            ->first();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $stableSample
        ]);
    }

    /**
     * Post final weight result (Operator accepts stable reading).
     */
    public function acceptWeight(Request $request, $id)
    {
        $request->validate([
            'weight' => 'required|numeric|min:0',
            'stable_sample_id' => 'sometimes|integer',
        ]);

        $service = app(\App\Services\WeighingCoreService::class);
        $result = $service->postResult(
            $id,
            (float) $request->input('weight'),
            $request->input('stable_sample_id')
        );

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $result
        ]);
    }

    /**
     * Supervisor overrides a rejected weighing item.
     */
    public function overrideWeight(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        $user = Auth::user();
        if (!$user->hasRole('SUPERVISOR') && !$user->hasRole('ADMIN')) {
            return response()->json([
                'status' => 'FORBIDDEN',
                'message' => 'Chỉ Giám sát viên (Supervisor) hoặc Admin mới có quyền ký duyệt Override.'
            ], 403);
        }

        $service = app(\App\Services\WeighingCoreService::class);
        try {
            $result = $service->overrideResult(
                $id,
                $request->input('reason'),
                $user->id
            );
            return response()->json([
                'status' => 'SUCCESS',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
