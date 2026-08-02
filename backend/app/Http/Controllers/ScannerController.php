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
            // KHÔNG dùng rule `exists:operation_clients,code` ở đây: nó chạy hẳn một truy vấn
            // xuống DB chỉ để kiểm tra tồn tại, rồi ngay dòng dưới lại truy vấn CHÍNH bảng đó
            // lần nữa để lấy bản ghi. Trên đường mạng này mỗi query ~33ms (đo 2026-08-02), nên
            // đó là 33ms mất trắng ngay ở bước đầu mỗi lần quét. firstOrFail() bên dưới đã bắt
            // đúng trường hợp mã trạm không tồn tại.
            'workstation_code' => 'required|string',
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

            // Gắn sẵn 2 quan hệ mà handleOrderScan cần, thay vì để nó truy vấn lại: máy vừa
            // firstOrCreate ngay trên nên đã cầm object rồi, còn lô vừa tạo thì chắc chắn chưa
            // có bồn nào. Bỏ được 2 query (~66ms) ở mỗi lần quét đơn chưa có trong Web.
            $batch->setRelation('machine', $machine);
            $batch->setRelation('tank', null);
        }

        $dispatch = MachineDispatch::where('batch_id', $batch->id)->first();

        // RACK auto-fill — đúng VBA txt_color_AfterUpdate: điền txt_rack{i} theo đúng bộ ba
        // (rack, dye, weight) thứ i trong chuỗi quét được, không tra cứu/khớp mã gì thêm.
        // Chỉ áp dụng khi rack_lines thật sự đến từ QR (không áp cho luồng mock scan() —
        // xem handleOrderScan()).
        $response = $this->handleOrderScan($batch, $workstation, $parsed['rack_lines']);

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
     * MỘT LỆNH DUY NHẤT cho cả mẻ cân của /weighing-station-v2 (2026-08-02, yêu cầu "dùng 100%
     * là bằng JS"): mở lệnh sản xuất + tạo vòng cân + ghi số cân + dựng phiếu in.
     *
     * Vì sao gộp: chuỗi QR đã chứa đủ rack/dye/weight nên trình duyệt tự vẽ được bảng 9 dòng,
     * KHÔNG cần hỏi server lúc quét. Cả mẻ chỉ còn đúng một lần chạm mạng, ngay lúc bấm SAVE —
     * cũng chính là cách VBA gốc làm (`scaleform.btnSave_Click` mới INSERT xuống Access, trước
     * đó form chỉ là dữ liệu trong RAM).
     *
     * Hệ quả có chủ ý, đã nêu rõ với người dùng trước khi làm: quét xong mà chưa SAVE thì dưới
     * DB KHÔNG có gì cả — trạm khác không thấy mẻ đang cân dở, và không còn cảnh báo "đơn này
     * cũng đang được cân ở máy khác". Đổi lại: quét không tốn một vòng mạng nào, và không bao
     * giờ còn sinh ra vòng cân mồ côi khi thợ quét nhầm rồi bỏ đi.
     *
     * `rows` khớp theo `sequence_no` (thứ tự bộ ba trong chuỗi QR) chứ không theo item_id —
     * client chưa từng biết id nào vì chưa hề gọi server.
     */
    public function weighFromQr(Request $request)
    {
        $request->validate([
            // CÂN TAY (`manual=true`): bấm NEXT cân luôn, không quét đơn nào — không có chuỗi QR
            // để gửi. Mọi thứ còn lại (hàng đợi, chống ghi trùng, dựng phiếu) đi chung đường với
            // mẻ quét QR, cố ý không tách endpoint riêng để khỏi có hai bộ ngữ nghĩa hàng đợi.
            'manual' => 'nullable|boolean',
            'raw_qr' => 'required_without:manual|string',
            'workstation_code' => 'required|string',
            'scale_device_id' => 'required|string',
            'stable' => 'required|boolean',
            // Trình duyệt sinh khoá này MỘT LẦN lúc bấm SAVE và giữ nguyên qua mọi lần gửi lại
            // (hàng đợi offline, xem frontend/src/services/saveQueue.ts). Không bắt buộc để các
            // client cũ và luồng gọi tay vẫn chạy được.
            'idempotency_key' => 'nullable|string|max:100',
            // Mốc giờ đã IN RA GIẤY. Trình duyệt in ngay từ dữ liệu trên màn (không chờ server),
            // nên phải gửi mốc đó lên để print_jobs lưu đúng tờ phiếu thật, không phải một chuỗi
            // khác chỉ vì server dựng lại vào lúc khác.
            'printed_at' => 'nullable|string|max:40',
            'rows' => 'required|array|min:1',
            'rows.*.sequence_no' => 'required|integer|min:1',
            // 'present|nullable' chứ không 'required': đúng VBA btnSave_Click, ô chưa cân vẫn
            // được ghi và bị gắn KHÔNG ĐẠT.
            'rows.*.weight' => 'present|nullable|numeric',
            'rows.*.tare_weight' => 'sometimes|nullable|numeric|min:0',
            'rows.*.gross_weight' => 'sometimes|nullable|numeric|min:0',
            // Chỉ cân tay mới gửi lên: dòng của mẻ QR lấy rack từ chính tem, không nhận từ client.
            'rows.*.rack_code' => 'sometimes|nullable|string|max:50',
        ]);

        // Cùng hàng rào với weighItem/weighBatch: client có thể gọi thẳng API, không phụ thuộc UI.
        if (! $request->boolean('stable')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau trước khi xác nhận.',
                'error_code' => 'NOT_STABLE',
            ], 422);
        }

        $workstation = Workstation::where('code', $request->input('workstation_code'))->firstOrFail();

        // ===== CHỐNG GHI TRÙNG =====
        // Trường hợp phải chặn: request ĐÃ tới đây và ghi xong, nhưng phản hồi không về được tới
        // trình duyệt (rớt mạng giữa chừng). Hàng đợi phía trình duyệt coi như thất bại và gửi
        // lại — không có khoá này thì mẻ bị ghi hai lần.
        //
        // Kiểm TRƯỚC khi rẽ nhánh cân tay: cân tay cũng đi qua hàng đợi nên cũng gửi lại được, mà
        // ở đó hậu quả còn nặng hơn — mỗi lần gửi lại tạo MỘT lô mới toanh, không có gì trùng để
        // mà đụng nhau, nên không có khoá này thì ghi trùng bao nhiêu lần cũng lọt.
        $idemKey = $request->input('idempotency_key');
        if ($idemKey) {
            $daGhi = WeighingJob::with(['batch.machine', 'items.material'])
                ->where('idempotency_key', $idemKey)
                ->first();

            if ($daGhi) {
                return $this->traLaiKetQuaDaGhi($daGhi, $workstation, $request->input('printed_at'));
            }
        }

        if ($request->boolean('manual')) {
            return $this->luuCanTay($request, $workstation, $idemKey);
        }

        $parsed = app(QrPayloadService::class)->parseDyeScan($request->input('raw_qr'));

        if ($parsed['color'] === '' || $parsed['code'] === '') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Không đọc được color/code từ mã QR đã quét — kiểm tra lại đầu đọc hoặc mã tem.',
            ], 422);
        }

        return DB::transaction(function () use ($request, $workstation, $parsed, $idemKey) {
            $batch = ProductionBatch::where('color', $parsed['color'])
                ->where('product_code', $parsed['code'])
                ->orderByDesc('created_at')
                ->first();

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

                $batch->setRelation('machine', $machine);
                $batch->setRelation('tank', null);
            }

            // Tái dùng ĐÚNG logic mở vòng cân của luồng quét cũ (nhánh có công thức lẫn nhánh
            // cân tự do, khóa chống 2 máy cân chung 1 job, cascade trạng thái lô...) thay vì
            // chép lại — chép lại là chắc chắn sẽ trôi dạt khỏi nhau theo thời gian.
            $scan = $this->handleOrderScan($batch, $workstation, $parsed['rack_lines'], true);

            // Lỗi nghiệp vụ (không có công thức ACTIVE, quét sai loại trạm...) trả nguyên response.
            if (! is_array($scan)) {
                return $scan;
            }

            /** @var WeighingJob $job */
            $job = $scan['job'];
            // Gắn sẵn lô đã có trong tay: recordMany() và buildSlipForJob() đều cần nó, không
            // gắn thì mỗi hàm tự lazy-load lại một lần.
            $job->setRelation('batch', $scan['batch']);

            $items = WeighingJobItem::with('material')
                ->where('weighing_job_id', $job->id)
                ->orderBy('sequence_no', 'asc')
                ->get();

            // Ghép số cân vào item theo sequence_no. Dòng client gửi mà job không có (QR nhiều
            // dòng hơn công thức) thì bỏ qua — không tự chế thêm vật tư ngoài công thức.
            $bySeq = collect($request->input('rows'))->keyBy('sequence_no');
            $rows = collect();
            foreach ($items as $item) {
                $row = $bySeq->get($item->sequence_no);
                if ($row === null) {
                    continue;
                }
                $rows->put($item->id, [
                    'weight' => $row['weight'],
                    'tare_weight' => $row['tare_weight'] ?? null,
                    'gross_weight' => $row['gross_weight'] ?? null,
                    'rack_code' => $item->rack_code,
                ]);
            }

            $ghi = app(\App\Services\WeighingItemRecorder::class)
                ->recordMany($job, $items->whereIn('id', $rows->keys()), $rows);

            // Đóng dấu khoá TRONG CÙNG transaction với việc ghi số cân: hoặc cả hai cùng có,
            // hoặc cả hai cùng không. Nếu ghi khoá ngoài transaction thì có kẽ hở để lần gửi lại
            // thấy khoá đã tồn tại trong khi số cân đã bị rollback mất.
            if ($idemKey) {
                $job->idempotency_key = $idemKey;
                $job->save();
            }

            $slip = app(WeighingJobController::class)
                ->buildSlipForJob($job, $items, $workstation->code, $request->input('printed_at'));

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Đã lưu '.count($ghi['saved']).' dòng cân.',
                'data' => [
                    'job_id' => $job->id,
                    'batch_id' => $batch->id,
                    'saved_item_ids' => $ghi['saved'],
                    'skipped_item_ids' => $ghi['skipped'],
                    'job_completed' => $ghi['job_completed'],
                    'reused' => false,
                    'slip' => $slip,
                ],
            ]);
        });
    }

    /**
     * CÂN TAY — lưu một vòng cân không gắn với đơn nào (thợ bấm thẳng NEXT rồi cân, không quét QR).
     *
     * Quyết định của người dùng 2026-08-02: "vẫn in phiếu bình thường, cái gì trống thì trống,
     * vẫn lưu DB bình thường". Nên lô sinh ra để TRỐNG màu/mã hàng/máy thay vì bịa giá trị —
     * bịa là làm bẩn master data và mọi báo cáo lọc theo màu.
     *
     * Hai chỗ buộc phải có giá trị vì lược đồ không cho null:
     *   · `weighing_job_items.material_code` (NOT NULL + khoá ngoại) -> mã mồi `CANTAY`.
     *   · `weighing_job_items.planned_weight` (NOT NULL) -> 0, và `process_status` nhận ra dòng
     *     cân tay qua mã mồi nên KHÔNG gắn nhãn KHÔNG ĐẠT cho số không có gì để so.
     *
     * Báo cáo tiêu hao/dung sai/sản lượng phải LOẠI các lô này — nhận diện qua tiền tố
     * `legacy_batch_id` = "CANTAY-", cùng cách đang dùng cho tiền tố "ADHOC-".
     */
    private function luuCanTay(Request $request, Workstation $workstation, ?string $idemKey)
    {
        $rowsInput = collect($request->input('rows'))
            // Dòng chưa cân thì KHÔNG tạo item: mẻ theo đơn phải ghi cả dòng bỏ trống vì đơn quy
            // định sẵn phải cân những gì, còn cân tay không có danh sách nào để mà thiếu.
            ->filter(fn ($r) => ($r['weight'] ?? null) !== null)
            ->values();

        if ($rowsInput->isEmpty()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chưa có dòng nào được cân — bấm NEXT và cân ít nhất một ô trước khi lưu.',
            ], 422);
        }

        return DB::transaction(function () use ($rowsInput, $request, $workstation, $idemKey) {
            $batch = ProductionBatch::create([
                'legacy_batch_id' => ProductionBatch::MANUAL_BATCH_PREFIX.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'color' => null,
                'product_code' => null,
                'machine_id' => null,
                'level_code' => null,
                'cloth_weight' => 0,
                'status' => 'NEW',
            ]);
            $batch->setRelation('machine', null);
            $batch->setRelation('tank', null);

            Material::firstOrCreate(
                ['code' => WeighingJobItem::MANUAL_MATERIAL_CODE],
                ['name' => 'Cân tay (không theo đơn)', 'type' => 'DYE']
            );

            $job = WeighingJob::create([
                'production_batch_id' => $batch->id,
                'job_type' => 'DYE',
                'workstation_type' => $workstation->type,
                'status' => 'RECEIVED',
                'assigned_workstation_id' => $workstation->id,
                'received_at' => Carbon::now(),
                'started_at' => Carbon::now(),
            ]);

            $itemRows = $rowsInput->map(fn ($r) => [
                'id' => (string) Str::uuid(),
                'weighing_job_id' => $job->id,
                'material_code' => WeighingJobItem::MANUAL_MATERIAL_CODE,
                'planned_weight' => 0,
                'tolerance_minus' => 0,
                'tolerance_plus' => 0,
                'sequence_no' => $r['sequence_no'],
                'rack_code' => $r['rack_code'] ?? null,
                'status' => 'PENDING',
            ])->all();
            WeighingJobItem::insert($itemRows);

            $items = WeighingJobItem::with('material')
                ->where('weighing_job_id', $job->id)
                ->orderBy('sequence_no', 'asc')
                ->get();

            $bySeq = $rowsInput->keyBy('sequence_no');
            $rows = collect();
            foreach ($items as $item) {
                $row = $bySeq->get($item->sequence_no);
                if ($row === null) {
                    continue;
                }
                $rows->put($item->id, [
                    'weight' => $row['weight'],
                    'tare_weight' => $row['tare_weight'] ?? null,
                    'gross_weight' => $row['gross_weight'] ?? null,
                    'rack_code' => $item->rack_code,
                ]);
            }

            // Gắn sẵn lô đang cầm trong tay: recordMany() và buildSlipForJob() đều cần nó, không
            // gắn thì mỗi hàm tự nạp lại một lần.
            $job->setRelation('batch', $batch);

            $ghi = app(\App\Services\WeighingItemRecorder::class)->recordMany($job, $items, $rows);

            if ($idemKey) {
                $job->idempotency_key = $idemKey;
                $job->save();
            }

            $slip = app(WeighingJobController::class)
                ->buildSlipForJob($job, $items, $workstation->code, $request->input('printed_at'));

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Đã lưu '.count($ghi['saved']).' dòng cân tay.',
                'data' => [
                    'job_id' => $job->id,
                    'batch_id' => $batch->id,
                    'saved_item_ids' => $ghi['saved'],
                    'skipped_item_ids' => $ghi['skipped'],
                    'job_completed' => $ghi['job_completed'],
                    'manual' => true,
                    'reused' => false,
                    'slip' => $slip,
                ],
            ]);
        });
    }

    /**
     * Mẻ này đã được ghi ở một lần gửi trước (trùng idempotency_key) — dựng lại phiếu từ chính
     * dữ liệu đã lưu và trả về như một lần lưu thành công, KHÔNG ghi thêm gì.
     *
     * Trả 200 chứ không phải lỗi: với hàng đợi phía trình duyệt thì đây là kết quả ĐÚNG (mẻ đã
     * nằm dưới DB), trả lỗi sẽ khiến nó thử lại mãi không thôi.
     */
    private function traLaiKetQuaDaGhi(WeighingJob $job, Workstation $workstation, ?string $printedAt = null)
    {
        $items = $job->items->sortBy('sequence_no')->values();

        $slip = app(WeighingJobController::class)
            ->buildSlipForJob($job, $items, $workstation->code, $printedAt);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Mẻ này đã được lưu trước đó — không ghi trùng.',
            'data' => [
                'job_id' => $job->id,
                'batch_id' => $job->production_batch_id,
                'saved_item_ids' => [],
                'skipped_item_ids' => [],
                'job_completed' => $job->status === 'COMPLETED',
                'reused' => true,
                'slip' => $slip,
            ],
        ]);
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
    /**
     * `$batchOrId` nhận cả UUID (luồng scan() qua "DF:ORDER:<uuid>") lẫn ProductionBatch ĐÃ NẠP
     * SẴN (luồng scanRawDyeQr — nó vừa tra/tạo chính bản ghi này xong). Truyền thẳng object để
     * bỏ được findOrFail + 2 quan hệ eager-load lặp lại: DB nằm ở máy khác, đo thật 2026-08-02
     * ra ~36ms/query nên mỗi vòng đi-về tiết kiệm được đều thấy rõ ở thời gian quét.
     */
    private function handleOrderScan($batchOrId, Workstation $workstation, ?array $rackLines = null, bool $returnJob = false)
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

        return DB::transaction(function () use ($batchOrId, $workstation, $jobType, $rackLines, $returnJob) {
            $batch = $batchOrId instanceof ProductionBatch
                ? $batchOrId->loadMissing(['machine', 'tank'])
                : ProductionBatch::with(['machine', 'tank'])->findOrFail($batchOrId);

            // Ghi nhận các máy KHÁC cũng đang cân đúng đơn này, để báo cho thao tác viên biết —
            // thuần thông tin, không chặn và không ảnh hưởng số liệu (mỗi máy một vòng cân
            // riêng, xem truy vấn tìm job bên dưới). Phải tính TRƯỚC khi tạo/tìm job của máy
            // này, vì nhánh cân tự do phía dưới thoát sớm bằng return riêng.
            // Loại cả COMPLETED lẫn CANCELLED: vòng cân đã hủy (xem WeighingJobController::cancel)
            // không phải "đang được cân", báo lẫn vào đây sẽ là thông tin sai.
            // Dùng đúng tên CỘT THẬT trong DB (assigned_operation_client_id) — WeighingJob có
            // accessor/mutator ánh xạ assigned_workstation_id -> assigned_operation_client_id
            // (đổi tên cột ở migration 2026_07_17_131458, "kiến trúc OperationClient") nhưng
            // ánh xạ đó CHỈ áp dụng khi đọc/ghi qua object model, KHÔNG áp dụng cho query
            // builder — where()/whereNotNull()/pluck() truyền thẳng chuỗi tên cột vào SQL, sinh
            // lỗi thật "column assigned_workstation_id does not exist" khi quét đơn (2026-08-02).
            $mayKhac = WeighingJob::where('production_batch_id', $batch->id)
                ->where('job_type', $jobType)
                ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
                ->whereNotNull('assigned_operation_client_id')
                ->where('assigned_operation_client_id', '!=', $workstation->id)
                ->pluck('assigned_operation_client_id')
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
                ->where('assigned_operation_client_id', $workstation->id)
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

                    // CHÈN GỘP 1 lần thay vì 9 lần create() (2026-08-02): DB nằm ở máy khác
                    // (10.0.60.209), đo được ~9ms cho mỗi vòng đi-về, nên 9 lần chèn tuần tự tự
                    // nó đã ~80ms trước khi làm được việc gì. Tự sinh UUID vì insert() gộp đi
                    // thẳng query builder nên KHÔNG chạy hook `creating` của model; an toàn với
                    // bảng này vì `$timestamps = false` (không có created_at/updated_at để điền).
                    $seq = 1;
                    $itemRows = [];
                    foreach ($rackLines as $line) {
                        $adhocTargetWeight = (float) $line['weight'];
                        // Dung sai ±1% — trước đây để 999999 (gần như vô cực) vì "không có công
                        // thức để so", nhưng planned_weight vẫn lấy đúng khối lượng in trên tem
                        // QR nên VẪN là mục tiêu thật, so đúng ±1% gốc VBA.
                        $adhocTolerance = $adhocTargetWeight * self::TOLERANCE_RATIO;

                        $itemRows[] = [
                            'id' => (string) Str::uuid(),
                            'weighing_job_id' => $job->id,
                            'material_code' => $line['dye'],
                            'planned_weight' => $adhocTargetWeight,
                            'tolerance_minus' => $adhocTolerance,
                            'tolerance_plus' => $adhocTolerance,
                            'sequence_no' => $seq++,
                            'rack_code' => $line['rack'],
                            'status' => 'PENDING',
                        ];
                    }
                    WeighingJobItem::insert($itemRows);

                    if ($batch->status === 'NEW' || $batch->status === 'READY_TO_WEIGH') {
                        $batch->status = 'WEIGHING';
                        $batch->save();
                    }

                    RealtimeService::publish('weighing_job.received', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);
                    RealtimeService::publish('weighing_job.started', 'WeighingJob', $job->id, $job->toArray(), auth()->id(), $batch->machine_id, $batch->id);

                    if ($returnJob) {
                        return ['job' => $job, 'batch' => $batch, 'notice' => $notice];
                    }

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

                // Chèn gộp 1 lần — cùng lý do với nhánh ad-hoc phía trên (mỗi vòng đi-về DB
                // ~9ms, chèn tuần tự từng dòng là tự cộng thêm hàng chục ms vô ích).
                $seq = 1;
                $itemRows = [];
                foreach ($recipeMaterials as $rm) {
                    $targetWeight = $this->calculationService->getPrecisionRoundedWeight($waterVolume, (float) $rm->concentration);
                    $tolerance = $targetWeight * self::TOLERANCE_RATIO; // ±1% đúng VBA CheckRange

                    // RACK auto-fill theo vị trí (xem docblock handleOrderScan) — chỉ cho DYE,
                    // vì rack_lines chỉ có ý nghĩa khi tới từ payload qrDye thật.
                    $rackCode = ($jobType === 'DYE' && $rackLines) ? ($rackLines[$seq - 1]['rack'] ?? null) : null;

                    $itemRows[] = [
                        'id' => (string) Str::uuid(),
                        'weighing_job_id' => $job->id,
                        'material_code' => $rm->material_code,
                        'planned_weight' => $targetWeight,
                        'tolerance_minus' => $tolerance,
                        'tolerance_plus' => $tolerance,
                        'sequence_no' => $seq++,
                        'rack_code' => $rackCode,
                        'status' => 'PENDING',
                    ];
                }
                WeighingJobItem::insert($itemRows);

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

            if ($returnJob) {
                return ['job' => $job, 'batch' => $batch, 'notice' => $notice];
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
