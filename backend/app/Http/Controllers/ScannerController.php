<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CorrelationLink;
use App\Models\Machine;
use App\Models\MachineDispatch;
use App\Models\Material;
use App\Models\MaterialLabel;
use App\Models\MaterialTransport;
use App\Models\MaterialTransportEvent;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Models\RecipeMaterial;
use App\Models\RecipeVersion;
use App\Models\Tank;
use App\Models\WeighingJob;
use App\Models\WeighingJobItem;
use App\Models\Workstation;
use App\Services\FormulaCalculationService;
use App\Services\QrPayloadService;
use App\Services\RealtimeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ScannerController extends Controller
{
    // Dung sai cân ±1% mục tiêu — port đúng VBA Mod_UI_processcolor.CheckRange
    // (ratio = delta/target; 0.99 <= ratio <= 1.01 là ĐẠT). Lưu dạng tuyệt đối
    // (target * 0.01) vào tolerance_minus/tolerance_plus: tương đương toán học với so ratio,
    // và snapshot ngay lúc quét nên nhãn ĐẠT/KHÔNG ĐẠT không trôi khi công thức đổi về sau.
    // Áp dụng đồng nhất cho cả 2 luồng tạo WeighingJobItem (có công thức / ad-hoc).
    private const TOLERANCE_RATIO = 0.01;

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
            'data' => Workstation::where('active', true)->get(),
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
        if (! str_contains($qrToken, ':')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR không đúng định dạng chuẩn hệ thống DF.',
            ], 422);
        }

        $parts = explode(':', $qrToken);
        if (count($parts) < 3 || $parts[0] !== 'DF') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR không thuộc bản quyền hệ thống DF.',
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
            'message' => "Loại mã QR '$qrType' chưa được hỗ trợ tại trạm này.",
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

        $parsed = app(QrPayloadService::class)->parseDyeScan($request->input('raw_qr'));

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

        // Quyết định nghiệp vụ: không chặn thao tác viên khi đơn chưa có trong Web (đang
        // chạy song song với Excel cũ, chưa kịp đồng bộ) — tự tạo 1 Lô sản xuất tối thiểu
        // thẳng từ dữ liệu đọc được trên tem QR rồi cho cân tiếp. Nhánh cân tự do (không
        // tra công thức/dung sai) nằm trong handleOrderScan() khi không tìm thấy Recipe.
        if (! $batch) {
            $machine = Machine::firstOrCreate(
                ['code' => $parsed['machine']],
                ['name' => $parsed['machine']]
            );

            $batch = ProductionBatch::create([
                'legacy_batch_id' => 'ADHOC-'.$parsed['color'].'-'.$parsed['code'].'-'.now()->format('YmdHis'),
                'color' => $parsed['color'],
                'product_code' => $parsed['code'],
                'machine_id' => $machine->id,
                'level_code' => $parsed['level'] !== '' ? $parsed['level'] : null,
                'cloth_weight' => 0,
                'status' => 'NEW',
            ]);
        }

        $dispatch = MachineDispatch::where('batch_id', $batch->id)->first();

        // RACK auto-fill — đúng VBA txt_color_AfterUpdate: điền txt_rack{i} theo đúng bộ ba
        // (rack, dye, weight) thứ i trong chuỗi quét được, không tra cứu/khớp mã gì thêm.
        // Chỉ áp dụng khi rack_lines thật sự đến từ QR (không áp cho luồng mock scan() —
        // xem handleOrderScan()).
        $response = $this->handleOrderScan($batch->id, $workstation, $parsed['rack_lines']);

        // Ghi correlation RECORD_A (dispatch) <-> RECORD_B (weighing job) theo khóa
        // nghiệp vụ color+code+machine — đúng ưu tiên khóa đã thống nhất (không dùng
        // timestamp). Idempotent: không tạo thêm nếu đã có link cho đúng cặp này.
        if ($dispatch) {
            $job = WeighingJob::where('production_batch_id', $batch->id)
                ->orderByDesc('created_at')
                ->first();

            if ($job) {
                $exists = CorrelationLink::where('dispatch_id', $dispatch->id)
                    ->where('weighing_job_id', $job->id)
                    ->exists();

                if (! $exists) {
                    CorrelationLink::create([
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
     *
     * $rackLines: bộ ba (rack, dye, weight) parse được từ QR thật (QrPayloadService::
     * parseDyeScan, chỉ có khi gọi từ scanRawDyeQr() — mock scan() qua DF:ORDER:<uuid>
     * không có chuỗi QR thật nên luôn null). Khớp theo ĐÚNG VỊ TRÍ (không theo mã vật tư)
     * với danh sách recipeMaterials — đúng cách VBA gốc điền thẳng theo thứ tự bộ ba trong
     * chuỗi quét, không tra cứu gì thêm. Chỉ có ý nghĩa cho job_type DYE (rack_lines luôn
     * lấy từ payload qrDye, không áp cho CHEMICAL/A11/DLG).
     */
    private function handleOrderScan(string $batchId, Workstation $workstation, ?array $rackLines = null)
    {
        // Verify workstation matches a weighing station type
        $allowedTypes = [
            'DYE_WEIGHING' => 'DYE',
            'CHEMICAL_WEIGHING' => 'CHEMICAL',
            'A11_WEIGHING' => 'A11',
            'DLG_WEIGHING' => 'DLG',
        ];

        if (! array_key_exists($workstation->type, $allowedTypes)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR Đơn công thức chỉ được phép quét tại các Trạm Cân sản xuất.',
            ], 403);
        }

        $jobType = $allowedTypes[$workstation->type];

        return DB::transaction(function () use ($batchId, $workstation, $jobType, $rackLines) {
            $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($batchId);

            // Ghi nhận các máy KHÁC cũng đang cân đúng đơn này, để báo cho thao tác viên biết —
            // thuần thông tin, không chặn và không ảnh hưởng số liệu (mỗi máy một vòng cân
            // riêng, xem truy vấn tìm job bên dưới). Phải tính TRƯỚC khi tạo/tìm job của máy
            // này, vì nhánh cân tự do phía dưới thoát sớm bằng return riêng.
            // Loại cả COMPLETED lẫn CANCELLED: vòng cân đã hủy (xem WeighingJobController::cancel)
            // không phải "đang được cân", báo lẫn vào đây sẽ là thông tin sai.
            $mayKhac = WeighingJob::where('production_batch_id', $batch->id)
                ->where('job_type', $jobType)
                ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
                ->whereNotNull('assigned_workstation_id')
                ->where('assigned_workstation_id', '!=', $workstation->id)
                ->pluck('assigned_workstation_id')
                ->unique();

            $notice = $mayKhac->isEmpty() ? null : sprintf(
                'Đơn này cũng đang được cân ở %s. Mẻ của bạn ghi riêng, hai bên không ảnh hưởng nhau.',
                Workstation::whereIn('id', $mayKhac)->pluck('name')->implode(', ') ?: 'máy khác'
            );

            // Fetch or create the WeighingJob for this batch and type.
            //
            // BỎ QUA job đã COMPLETED (2026-08-01): quét lại đúng mã đó sau khi đã SAVE nghĩa là
            // thao tác viên muốn CÂN LẠI TỪ ĐẦU — không phải mở lại mẻ cũ để xem. Nếu tái dùng
            // job đã xong thì 9 dòng hiện nguyên số cũ và server chặn ghi đè (weighBatch bỏ qua
            // dòng COMPLETED), thành ra màn hình đứng im không cân được gì.
            //
            // Job cũ GIỮ NGUYÊN, không sửa/không xoá — vòng cân mới là một WeighingJob mới, đúng
            // nguyên tắc không xoá vật lý dữ liệu giao dịch (CLAUDE.md mục 3). Hệ quả có chủ ý:
            // 1 lô có thể có nhiều vòng cân, và trạng thái lô quay về PARTIALLY_WEIGHED trong lúc
            // vòng mới đang chạy rồi trở lại WEIGHED khi xong (WeighingItemRecorder tự cascade).
            // Báo cáo tiêu hao vì vậy phải cộng dồn theo VÒNG, không giả định 1 lô = 1 lần cân.
            // CHỈ tái dùng vòng cân CỦA CHÍNH MÁY NÀY (2026-08-01). Trước đó truy vấn không lọc
            // theo trạm nên hai máy quét cùng một đơn nhận về CÙNG một WeighingJob: cân song
            // song rồi máy bấm SAVE sau bị bỏ qua những dòng máy kia đã ghi (weighBatch bỏ dòng
            // COMPLETED) — mất số mà không ai biết.
            //
            // Quyết định nghiệp vụ: mỗi máy một vòng cân riêng, cả hai đều lưu được đầy đủ, hai
            // bản ghi song song là chấp nhận được. Hệ quả có chủ ý: một lô có thể có nhiều vòng
            // cân cùng lúc, nên báo cáo tiêu hao phải cộng dồn THEO VÒNG chứ không giả định 1 lô
            // = 1 lần cân.
            //
            // Lọc chặt theo đúng trạm, KHÔNG nhận job có assigned_workstation_id rỗng: nhận job
            // rỗng thì hai máy lại cùng vớ phải một job và quay về đúng lỗi trên. Job cũ (nếu có)
            // giữ nguyên, không sửa/không xoá — đúng nguyên tắc không xoá vật lý dữ liệu giao
            // dịch (CLAUDE.md mục 3).
            //
            // Loại cả CANCELLED (2026-08-01, WeighingJobController::cancel): job đã bị thao tác
            // viên hủy trắng (quét nhầm rồi bỏ đi, chưa cân dòng nào) không được tái dùng — coi
            // như chưa từng có, quét lại là mở một vòng cân hoàn toàn mới.
            $job = WeighingJob::where('production_batch_id', $batch->id)
                ->where('job_type', $jobType)
                ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
                ->where('assigned_workstation_id', $workstation->id)
                ->orderByDesc('created_at')
                ->first();

            if (! $job) {
                // Determine Recipe Materials and create Job Items
                $recipe = Recipe::where('color_code', $batch->color)
                    ->where('product_code', $batch->product_code)
                    ->first();

                $version = $recipe
                    ? RecipeVersion::where('recipe_id', $recipe->id)->where('status', 'ACTIVE')->first()
                    : null;

                if ((! $recipe || ! $version) && $jobType === 'DYE' && $rackLines) {
                    // Quyết định nghiệp vụ: đơn chưa có công thức duyệt trong Web (hoặc đơn ad-hoc
                    // tự tạo từ QR khi không khớp Lô sản xuất nào — xem scanRawDyeQr()) — mục tiêu
                    // (planned_weight) vẫn lấy đúng khối lượng in trên tem QR, và dung sai áp đúng
                    // ±1% chuẩn (giống nhánh có Recipe bên dưới, đúng gốc VBA
                    // Mod_UI_processcolor.CheckRange) thay vì mở hết cỡ như trước — nếu không, màu
                    // LED lúc đang cân luôn báo xanh giả bất kể lệch bao nhiêu, và Save không bao
                    // giờ đòi override dù cân sai xa mục tiêu. Báo cáo tiêu hao/dung sai sau này cần
                    // lọc riêng các lô cân kiểu này — nhận diện qua legacy_batch_id tiền tố "ADHOC-"
                    // hoặc không có Recipe khớp color/code.
                    $job = WeighingJob::create([
                        'production_batch_id' => $batch->id,
                        'job_type' => $jobType,
                        'workstation_type' => $workstation->type,
                        'status' => 'RECEIVED',
                        'assigned_workstation_id' => $workstation->id,
                        'received_at' => Carbon::now(),
                        'started_at' => Carbon::now(),
                    ]);

                    // Tra 1 lần cho CẢ 9 mã thay vì firstOrCreate trong vòng lặp (9 lượt đi-về
                    // DB, mỗi lượt ~20ms khi backend không nằm cùng máy với DB → tự nó đã gần
                    // 200ms trước khi làm được việc gì). Mã chưa có thì chèn 1 lần bằng insert
                    // gộp — vẫn giữ đúng ngữ nghĩa firstOrCreate: không đụng vào mã đã tồn tại.
                    $dyeCodes = array_values(array_unique(array_column($rackLines, 'dye')));
                    $existingCodes = Material::whereIn('code', $dyeCodes)->pluck('code')->all();
                    $missingCodes = array_diff($dyeCodes, $existingCodes);

                    if ($missingCodes) {
                        // Điền created_at/updated_at tường minh: insert() gộp đi thẳng query
                        // builder nên KHÔNG tự đóng dấu thời gian như firstOrCreate. Cột là
                        // nullable nên không lỗi, chỉ âm thầm để rỗng — mất dấu vết mã vật tư
                        // này được tự tạo lúc nào.
                        $now = Carbon::now();
                        Material::insert(array_map(
                            fn ($code) => [
                                'code' => $code,
                                'name' => $code,
                                'type' => 'DYE',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ],
                            array_values($missingCodes)
                        ));
                    }

                    $seq = 1;
                    foreach ($rackLines as $line) {
                        $adhocTargetWeight = (float) $line['weight'];
                        // Dung sai ±1% — trước đây để 999999 (gần như vô cực) vì "không có công
                        // thức để so", nhưng planned_weight vẫn lấy đúng khối lượng in trên tem
                        // QR nên VẪN là mục tiêu thật, so đúng ±1% gốc VBA.
                        $adhocTolerance = $adhocTargetWeight * self::TOLERANCE_RATIO;

                        WeighingJobItem::create([
                            'weighing_job_id' => $job->id,
                            'material_code' => $line['dye'],
                            'planned_weight' => $adhocTargetWeight,
                            'tolerance_minus' => $adhocTolerance,
                            'tolerance_plus' => $adhocTolerance,
                            'sequence_no' => $seq++,
                            'rack_code' => $line['rack'],
                            'status' => 'PENDING',
                        ]);
                    }

                    if ($batch->status === 'NEW' || $batch->status === 'READY_TO_WEIGH') {
                        $batch->status = 'WEIGHING';
                        $batch->save();
                    }

                    RealtimeService::publish('weighing_job.received', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);
                    RealtimeService::publish('weighing_job.started', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);

                    return response()->json([
                        'status' => 'SUCCESS',
                        'message' => "Quét đơn thành công (cân tự do — không có công thức). Đã nạp danh sách cân của Trạm {$workstation->code}.",
                        'notice' => $notice,
                        'data' => [
                            'job' => $job->load('items.material'),
                            'batch' => $batch,
                        ],
                    ]);
                }

                if (! $recipe) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "Không tìm thấy công thức nhuộm hợp lệ cho Lô {$batch->legacy_batch_id}.",
                    ], 422);
                }

                if (! $version) {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => "Không tìm thấy phiên bản công thức ACTIVE cho Lô {$batch->legacy_batch_id}.",
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
                            'batch' => $batch,
                        ],
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
                $clothWeight = $batch->cloth_weight > 0 ? (float) $batch->cloth_weight : 100.0;
                $machineLine = $batch->machine ? $batch->machine->code : 'VD-COMMON';
                $processCode = $this->calculationService->getProcessCode($batch->color);

                $waterVolume = $this->calculationService->calculateWater($clothWeight, $machineLine, $processCode);

                $seq = 1;
                foreach ($recipeMaterials as $rm) {
                    $targetWeight = $this->calculationService->getPrecisionRoundedWeight($waterVolume, (float) $rm->concentration);
                    $tolerance = $targetWeight * self::TOLERANCE_RATIO; // ±1% đúng VBA CheckRange

                    // RACK auto-fill theo vị trí (xem docblock handleOrderScan) — chỉ cho DYE,
                    // vì rack_lines chỉ có ý nghĩa khi tới từ payload qrDye thật.
                    $rackCode = ($jobType === 'DYE' && $rackLines) ? ($rackLines[$seq - 1]['rack'] ?? null) : null;

                    WeighingJobItem::create([
                        'weighing_job_id' => $job->id,
                        'material_code' => $rm->material_code,
                        'planned_weight' => $targetWeight,
                        'tolerance_minus' => $tolerance,
                        'tolerance_plus' => $tolerance,
                        'sequence_no' => $seq++,
                        'rack_code' => $rackCode,
                        'status' => 'PENDING',
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
                'notice' => $notice,
                'data' => [
                    'job' => $job->load('items.material'),
                    'batch' => $batch,
                ],
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
            ],
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
                'message' => 'Xác nhận nhận đơn chỉ được thực hiện tại Trạm Quét đơn QR (Order Desk).',
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
                'message' => 'Tem quét vật tư sau cân chỉ được quét nhận tại trạm Vận Chuyển.',
            ], 403);
        }

        return DB::transaction(function () use ($label, $workstation) {
            // Find or create material transport
            $transport = MaterialTransport::where('material_label_id', $label->id)->first();

            if (! $transport) {
                // Create transport in IN_TRANSIT status
                $transport = MaterialTransport::create([
                    'id' => (string) Str::uuid(),
                    'batch_id' => $label->production_batch_id,
                    'weighing_job_id' => $label->weighing_job_id,
                    'material_label_id' => $label->id,
                    'workstation_id' => $workstation->code,
                    'status' => 'IN_TRANSIT',
                    'started_at' => Carbon::now(),
                    'sla_minutes' => 15, // Default SLA time
                ]);

                // Update production batch status
                $batch = $label->batch;
                if ($batch && $batch->status === 'WEIGHED') {
                    $batch->status = 'IN_TRANSIT';
                    $batch->save();
                }

                // Add log event
                MaterialTransportEvent::create([
                    'id' => (string) Str::uuid(),
                    'transport_id' => $transport->id,
                    'status' => 'IN_TRANSIT',
                    'operator_id' => auth()->id(),
                    'notes' => 'Đã tiếp nhận vận chuyển thùng nguyên liệu của trạm cân.',
                ]);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Tiếp nhận vận chuyển thành công. Đích đến: Máy {$label->batch?->machine?->code}.",
                'data' => $transport->load(['batch.machine', 'batch.tank']),
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
                'message' => 'Tính năng quét kép đối chiếu chỉ được thực hiện tại Trạm Nhận Thùng.',
            ], 403);
        }

        // Parse label QR
        if (! str_starts_with($labelQr, 'DF:MATERIAL_LABEL:')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR tem vật tư không đúng định dạng DF:MATERIAL_LABEL.',
            ], 422);
        }
        $labelId = explode(':', $labelQr)[2];
        $label = MaterialLabel::with('batch')->findOrFail($labelId);

        // Parse machine/tank QR
        // DF:MACHINE:<id> or DF:TANK:<id>
        if (! str_contains($tankQr, ':')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã QR bồn/máy không đúng định dạng.',
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
                    'message' => "Sai máy nhuộm! Mẻ này được phân phối tới máy ID {$batch->machine_id}, không phải máy ID $id.",
                ], 422);
            }
        } elseif ($type === 'TANK') {
            if ($batch->tank_id != $id) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => "Sai thùng trộn! Mẻ này được phân phối tới thùng ID {$batch->tank_id}, không phải thùng ID $id.",
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
                    'id' => (string) Str::uuid(),
                    'transport_id' => $transport->id,
                    'status' => 'ARRIVED_AT_TANK',
                    'operator_id' => auth()->id(),
                    'notes' => 'Quét đối soát khớp 100%. Đã nhận thùng tại bồn nhuộm máy.',
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
                'data' => $label,
            ]);
        });
    }
}
