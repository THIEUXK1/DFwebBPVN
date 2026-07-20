<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductionBatch;
use App\Models\Workstation;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\MaterialLabel;
use App\Models\MaterialTransport;
use App\Models\MaterialTransportEvent;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use App\Models\Machine;
use App\Models\Tank;
use App\Models\AuditLog;
use App\Services\FormulaCalculationService;
use App\Services\RealtimeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScannerController extends Controller
{
    private FormulaCalculationService $calculationService;

    public function __construct(FormulaCalculationService $calculationService)
    {
        $this->calculationService = $calculationService;
    }

    /**
     * List active workstations.
     */
    public function listWorkstations()
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => Workstation::where('active', true)->get()
        ]);
    }

    /**
     * Main Entry Scan QR endpoint.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'qr_token' => 'required|string',
            'workstation_code' => 'required|string|exists:operation_clients,code',
        ]);

        $qrToken = trim($request->input('qr_token'));
        $workstationCode = $request->input('workstation_code');
        $workstation = Workstation::where('code', $workstationCode)->firstOrFail();

        // Parse QR Code structure
        // DF:ORDER:<uuid>
        // DF:MATERIAL_LABEL:<uuid>
        if (!str_contains($qrToken, ':')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR không đúng định dạng chuẩn hệ thống DF.'
            ], 422);
        }

        $parts = explode(':', $qrToken);
        if (count($parts) < 3 || $parts[0] !== 'DF') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR không thuộc bản quyền hệ thống DF.'
            ], 422);
        }

        $qrType = $parts[1];
        $entityId = $parts[2];

        // Route scan based on QR type
        if ($qrType === 'ORDER') {
            if ($workstation->type === 'ORDER_DESK') {
                return $this->handleOrderDeskPreview($entityId);
            }
            return $this->handleOrderScan($entityId, $workstation);
        } elseif ($qrType === 'MATERIAL_LABEL') {
            return $this->handleMaterialLabelScan($entityId, $workstation);
        }

        return response()->json([
            'status' => 'ERROR',
            'message' => "Loại mã QR '$qrType' chưa được hỗ trợ tại trạm này."
        ], 422);
    }

    /**
     * Quét QR THẬT do QR_LABEL_PRINTING sinh (định dạng VBA gốc qua QrPayloadService,
     * vd "#RED-P123-VD10-220-RACK1-DYE001-1.500...") tại trạm cân — KHÔNG phải định dạng
     * giả "DF:ORDER:<uuid>" mà scan() ở trên xử lý.
     *
     * Phát hiện khi audit 2026-07-17: QR do QR_LABEL_PRINTING in ra (đúng VBA, đã sửa theo
     * CLAUDE.md C-04) và endpoint scan() ở trên dùng 2 định dạng hoàn toàn khác nhau — trạm
     * cân không đọc được QR thật. Đã trích lại nguyên văn `txt_color_AfterUpdate` (olevba,
     * "4.semiauto-small scale...xlsm" dòng 973-1045) xác nhận VBA gốc không tra UUID nào —
     * chỉ parse trực tiếp color/code/machine/level từ chuỗi quét được rồi khớp theo
     * color+code (xem QrPayloadService::parseDyeScan). Đây là endpoint port lại đúng luồng
     * đó, có ghi correlation_links (RECORD_A <-> RECORD_B) khi khớp được dispatch gốc.
     */
    public function scanRawDyeQr(Request $request)
    {
        $request->validate([
            'raw_qr' => 'required|string',
            'workstation_code' => 'required|string|exists:operation_clients,code',
        ]);

        $workstation = Workstation::where('code', $request->input('workstation_code'))->firstOrFail();

        $parsed = app(\App\Services\QrPayloadService::class)->parseDyeScan($request->input('raw_qr'));

        if ($parsed['color'] === '' || $parsed['code'] === '') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Không đọc được color/code từ mã QR đã quét — kiểm tra lại đầu đọc hoặc mã tem.',
            ], 422);
        }

        // Khóa nghiệp vụ đúng như VBA: color + code. Lấy bản ghi MỚI NHẤT nếu trùng
        // (cùng color+code có thể lặp lại ở các lô khác nhau theo thời gian) — không suy
        // diễn thêm điều kiện khớp nào khác vì VBA gốc cũng không có.
        $batch = ProductionBatch::where('color', $parsed['color'])
            ->where('product_code', $parsed['code'])
            ->orderByDesc('created_at')
            ->first();

        if (!$batch) {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Không tìm thấy đơn sản xuất khớp color={$parsed['color']}, code={$parsed['code']}.",
                'parsed' => $parsed,
            ], 404);
        }

        $dispatch = \App\Models\MachineDispatch::where('batch_id', $batch->id)->first();

        $response = $this->handleOrderScan($batch->id, $workstation);

        // Ghi correlation RECORD_A (dispatch) <-> RECORD_B (weighing job) theo khóa
        // nghiệp vụ color+code+machine — đúng ưu tiên khóa đã thống nhất (không dùng
        // timestamp). Idempotent: không tạo thêm nếu đã có link cho đúng cặp này.
        if ($dispatch) {
            $job = \App\Models\WeighingJob::where('production_batch_id', $batch->id)
                ->orderByDesc('created_at')
                ->first();

            if ($job) {
                $exists = \App\Models\CorrelationLink::where('dispatch_id', $dispatch->id)
                    ->where('weighing_job_id', $job->id)
                    ->exists();

                if (!$exists) {
                    \App\Models\CorrelationLink::create([
                        'dispatch_id' => $dispatch->id,
                        'weighing_job_id' => $job->id,
                        'match_method' => 'DETERMINISTIC_COMPOSITE',
                        'confidence' => 1.00,
                        'matched_on' => [
                            'color' => $parsed['color'],
                            'code' => $parsed['code'],
                            'machine' => $parsed['machine'],
                        ],
                        'status' => 'LINKED',
                    ]);
                }
            }
        }

        return $response;
    }

    /**
     * Handle Order QR Scan in the weighing stations.
     */
    private function handleOrderScan(string $batchId, Workstation $workstation)
    {
        // Verify workstation matches a weighing station type
        $allowedTypes = [
            'DYE_WEIGHING' => 'DYE',
            'CHEMICAL_WEIGHING' => 'CHEMICAL',
            'A11_WEIGHING' => 'A11',
            'DLG_WEIGHING' => 'DLG',
        ];

        if (!array_key_exists($workstation->type, $allowedTypes)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Mã QR Đơn công thức chỉ được phép quét tại các Trạm Cân sản xuất."
            ], 403);
        }

        $jobType = $allowedTypes[$workstation->type];

        return DB::transaction(function () use ($batchId, $workstation, $jobType) {
            $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($batchId);

            // Fetch or create the WeighingJob for this batch and type
            $job = WeighingJob::where('production_batch_id', $batch->id)
                ->where('job_type', $jobType)
                ->first();

            if (!$job) {
                // Determine Recipe Materials and create Job Items
                $recipe = Recipe::where('color_code', $batch->color)
                    ->where('product_code', $batch->product_code)
                    ->first();

                if (!$recipe) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "Không tìm thấy công thức nhuộm hợp lệ cho Lô {$batch->legacy_batch_id}."
                    ], 422);
                }

                $version = RecipeVersion::where('recipe_id', $recipe->id)
                    ->where('status', 'ACTIVE')
                    ->first();

                if (!$version) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "Không tìm thấy phiên bản công thức ACTIVE cho Lô {$batch->legacy_batch_id}."
                    ], 422);
                }

                // Get materials matching the job type
                // Dyeing stations only get DYE, Chemical gets CHEMICAL, A11/DLG get their specific materials
                $materialsQuery = RecipeMaterial::where('recipe_version_id', $version->id);
                
                if ($jobType === 'DYE') {
                    $materialsQuery->whereHas('material', function ($q) {
                        $q->where('type', 'DYE');
                    });
                } elseif ($jobType === 'CHEMICAL') {
                    $materialsQuery->whereHas('material', function ($q) {
                        $q->where('type', 'CHEMICAL');
                    });
                } elseif ($jobType === 'A11') {
                    $materialsQuery->where('material_code', 'A11');
                } elseif ($jobType === 'DLG') {
                    $materialsQuery->where('material_code', 'DLG');
                }

                $recipeMaterials = $materialsQuery->get();

                if ($recipeMaterials->isEmpty()) {
                    return response()->json([
                        'status' => 'SUCCESS',
                        'message' => "Trạm này không có nhiệm vụ cân nào cho Lô {$batch->legacy_batch_id} (Công thức không chứa vật tư này).",
                        'data' => [
                            'empty' => true,
                            'batch' => $batch
                        ]
                    ]);
                }

                // Create the Weighing Job
                $job = WeighingJob::create([
                    'production_batch_id' => $batch->id,
                    'job_type' => $jobType,
                    'workstation_type' => $workstation->type,
                    'status' => 'RECEIVED',
                    'assigned_workstation_id' => $workstation->id,
                    'received_at' => Carbon::now(),
                    'started_at' => Carbon::now(),
                ]);

                // Calculate plans and populate Items
                $clothWeight = $batch->cloth_weight > 0 ? (float)$batch->cloth_weight : 100.0;
                $machineLine = $batch->machine ? $batch->machine->code : 'VD-COMMON';
                $processCode = $this->calculationService->getProcessCode($batch->color);
                
                $waterVolume = $this->calculationService->calculateWater($clothWeight, $machineLine, $processCode);

                $seq = 1;
                foreach ($recipeMaterials as $rm) {
                    $targetWeight = $this->calculationService->getPrecisionRoundedWeight($waterVolume, (float)$rm->concentration);
                    $tolerance = $targetWeight * 0.01; // ±1% standard tolerance limit

                    WeighingJobItem::create([
                        'weighing_job_id' => $job->id,
                        'material_code' => $rm->material_code,
                        'planned_weight' => $targetWeight,
                        'tolerance_minus' => $tolerance,
                        'tolerance_plus' => $tolerance,
                        'sequence_no' => $seq++,
                        'status' => 'PENDING'
                    ]);
                }

                // Update Batch status to WEIGHING
                if ($batch->status === 'NEW' || $batch->status === 'READY_TO_WEIGH') {
                    $batch->status = 'WEIGHING';
                    $batch->save();
                }

                // Publish realtime events
                RealtimeService::publish('weighing_job.received', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);
                RealtimeService::publish('weighing_job.started', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);

            } else {
                // If job exists but was in locked or pending state, mark received/active
                if ($job->status === 'PENDING') {
                    $job->status = 'RECEIVED';
                    $job->assigned_workstation_id = $workstation->id;
                    $job->received_at = Carbon::now();
                    $job->started_at = Carbon::now();
                    $job->save();
                    
                    RealtimeService::publish('weighing_job.received', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);
                }
            }

            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Quét đơn thành công. Đã nạp danh sách cân của Trạm {$workstation->code}.",
                'data' => [
                    'job' => $job->load('items.material'),
                    'batch' => $batch
                ]
            ]);
        });
    }

    /**
     * WS-005: Read-only preview at the Order Desk (WS-01 "quét đơn QR"). Unlike handleOrderScan
     * (which creates a weighing job at a weighing station), this only looks up and displays the
     * order — no weighing job, no status change. The operator confirms separately via
     * acknowledgeOrder(), so a re-scan for review is always safe.
     */
    private function handleOrderDeskPreview(string $batchId)
    {
        $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($batchId);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Đã tải thông tin Lô {$batch->legacy_batch_id}.",
            'data' => [
                'batch' => $batch,
                'already_acknowledged' => $batch->status !== 'NEW',
            ]
        ]);
    }

    /**
     * WS-005: Confirm receipt of an order at the Order Desk — transitions NEW -> READY_TO_WEIGH.
     * Idempotent: re-confirming an already-acknowledged batch is a no-op success, not an error,
     * so a nervous double-tap on the kiosk button never produces a confusing failure.
     */
    public function acknowledgeOrder(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|string|exists:production_batches,id',
            'workstation_code' => 'required|string|exists:operation_clients,code',
        ]);

        $workstation = Workstation::where('code', $request->input('workstation_code'))->firstOrFail();
        if ($workstation->type !== 'ORDER_DESK') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Xác nhận nhận đơn chỉ được thực hiện tại Trạm Quét đơn QR (Order Desk).'
            ], 403);
        }

        $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($request->input('batch_id'));

        if ($batch->status !== 'NEW') {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Lô {$batch->legacy_batch_id} đã được xác nhận nhận đơn trước đó.",
                'data' => $batch,
            ]);
        }

        $previousStatus = $batch->status;
        $batch->status = 'READY_TO_WEIGH';
        $batch->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ORDER_RECEIVED_ACK',
            'entity_type' => 'ProductionBatch',
            'entity_id' => $batch->id,
            'before_data' => ['status' => $previousStatus],
            'after_data' => ['status' => $batch->status, 'workstation_code' => $workstation->code],
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Đã xác nhận nhận đơn Lô {$batch->legacy_batch_id}. Sẵn sàng chuyển sang cân.",
            'data' => $batch,
        ]);
    }

    /**
     * Handle Material Label Scan.
     */
    private function handleMaterialLabelScan(string $labelId, Workstation $workstation)
    {
        $label = MaterialLabel::with('batch')->findOrFail($labelId);

        if ($workstation->type !== 'MATERIAL_TRANSFER') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Tem quét vật tư sau cân chỉ được quét nhận tại trạm Vận Chuyển.'
            ], 403);
        }

        return DB::transaction(function () use ($label, $workstation) {
            // Find or create material transport
            $transport = MaterialTransport::where('material_label_id', $label->id)->first();

            if (!$transport) {
                // Create transport in IN_TRANSIT status
                $transport = MaterialTransport::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'batch_id' => $label->production_batch_id,
                    'weighing_job_id' => $label->weighing_job_id,
                    'material_label_id' => $label->id,
                    'workstation_id' => $workstation->code,
                    'status' => 'IN_TRANSIT',
                    'started_at' => Carbon::now(),
                    'sla_minutes' => 15 // Default SLA time
                ]);

                // Update production batch status
                $batch = $label->batch;
                if ($batch && $batch->status === 'WEIGHED') {
                    $batch->status = 'IN_TRANSIT';
                    $batch->save();
                }

                // Add log event
                MaterialTransportEvent::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'transport_id' => $transport->id,
                    'status' => 'IN_TRANSIT',
                    'operator_id' => auth()->id(),
                    'notes' => "Đã tiếp nhận vận chuyển thùng nguyên liệu của trạm cân."
                ]);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Tiếp nhận vận chuyển thành công. Đích đến: Máy {$label->batch?->machine?->code}.",
                'data' => $transport->load(['batch.machine', 'batch.tank'])
            ]);
        });
    }

    /**
     * Dual scanning verification endpoint at the Tank Receiving station.
     */
    public function verifyTank(Request $request)
    {
        $request->validate([
            'machine_or_tank_qr' => 'required|string',
            'material_label_qr' => 'required|string',
            'workstation_code' => 'required|string|exists:operation_clients,code',
        ]);

        $tankQr = trim($request->input('machine_or_tank_qr'));
        $labelQr = trim($request->input('material_label_qr'));
        $workstationCode = $request->input('workstation_code');
        
        $workstation = Workstation::where('code', $workstationCode)->firstOrFail();
        if ($workstation->type !== 'TANK_RECEIVING') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Tính năng quét kép đối chiếu chỉ được thực hiện tại Trạm Nhận Thùng.'
            ], 403);
        }

        // Parse label QR
        if (!str_starts_with($labelQr, 'DF:MATERIAL_LABEL:')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR tem vật tư không đúng định dạng DF:MATERIAL_LABEL.'
            ], 422);
        }
        $labelId = explode(':', $labelQr)[2];
        $label = MaterialLabel::with('batch')->findOrFail($labelId);

        // Parse machine/tank QR
        // DF:MACHINE:<id> or DF:TANK:<id>
        if (!str_contains($tankQr, ':')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR bồn/máy không đúng định dạng.'
            ], 422);
        }

        $tankParts = explode(':', $tankQr);
        $type = $tankParts[1];
        $id = $tankParts[2];

        $batch = $label->batch;

        if ($type === 'MACHINE') {
            if ($batch->machine_id != $id) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Sai máy nhuộm! Mẻ này được phân phối tới máy ID {$batch->machine_id}, không phải máy ID $id."
                ], 422);
            }
        } elseif ($type === 'TANK') {
            if ($batch->tank_id != $id) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Sai thùng trộn! Mẻ này được phân phối tới thùng ID {$batch->tank_id}, không phải thùng ID $id."
                ], 422);
            }
        }

        return DB::transaction(function () use ($label, $batch) {
            // Find material transport
            $transport = MaterialTransport::where('material_label_id', $label->id)->first();
            if ($transport) {
                $transport->status = 'ARRIVED_AT_TANK';
                $transport->arrived_at = Carbon::now();
                $transport->save();

                MaterialTransportEvent::create([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'transport_id' => $transport->id,
                    'status' => 'ARRIVED_AT_TANK',
                    'operator_id' => auth()->id(),
                    'notes' => "Quét đối soát khớp 100%. Đã nhận thùng tại bồn nhuộm máy."
                ]);
            }

            // Update batch status to ARRIVED_AT_TANK
            if ($batch->status === 'IN_TRANSIT' || $batch->status === 'WEIGHED') {
                $batch->status = 'ARRIVED_AT_TANK';
                $batch->save();
            }

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Xác nhận đối soát thành công. Vật tư đã nạp vào thùng máy nhuộm.',
                'data' => $label
            ]);
        });
    }
}
