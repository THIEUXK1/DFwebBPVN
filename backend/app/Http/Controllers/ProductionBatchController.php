<?php

namespace App\Http\Controllers;

use App\Events\ProductionBatchUpdated;
use App\Models\ProductionBatch;
use App\Models\Machine;
use App\Models\Tank;
use App\Services\ApproveProductionOrderService;
use App\Services\QrPayloadService;
use App\Exceptions\BusinessRuleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProductionBatchController extends Controller
{
    /**
     * Danh mục máy nhuộm thật (thay danh sách hardcode trước đây trong frontend —
     * bị thiếu máy khi máy mới được thêm vào DB mà không sửa code).
     */
    public function machines()
    {
        return response()->json([
            'status' => 'SUCCESS',
            'data' => Machine::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    /** Thùng trộn chuẩn theo VBA (Box5 / formselect1) — mã CHUNG, không tiền tố máy. */
    public const TANK_CODES_MAC_DINH = ['1A', '2B', '3C', '4D', 'FB'];

    /**
     * Thêm máy nhuộm mới vào danh mục dùng chung (production batches + kênh gọi hóa
     * chất đều tham chiếu cùng bảng machines qua machine_id).
     *
     * Tạo LUÔN 5 thùng chuẩn cho máy mới (2026-08-04). Trước đây endpoint này chỉ tạo bản ghi
     * máy, còn thùng thì chỉ được seed một lần bằng migration cho dải máy có sẵn lúc đó — nên
     * mọi máy thêm qua giao diện sau đó đều KHÔNG duyệt đơn được: SubForm ghi `tank_id` theo
     * từng máy và updateTank() chặn thùng không thuộc máy của lô, mà máy mới không có thùng nào
     * để khớp. Người dùng chỉ thấy nút OK khóa cứng, không có cách nào tự gỡ (lỗi thật với máy
     * VD04/VD10).
     */
    public function storeMachine(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:machines,code',
            'name' => 'required|string|max:255',
        ]);

        $machine = DB::transaction(function () use ($request) {
            $machine = Machine::create([
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'is_active' => true,
            ]);

            foreach (self::TANK_CODES_MAC_DINH as $tankCode) {
                Tank::create([
                    'machine_id' => $machine->id,
                    'code' => $tankCode,
                    'name' => "Thùng {$tankCode} - {$machine->code}",
                ]);
            }

            return $machine;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $machine,
        ], 201);
    }

    /**
     * Danh mục thùng trộn thật, lọc theo máy (Box5 / formselect1 trong VBA — "1A",
     * "2B","3C","4D","FB" — riêng theo từng máy, xem migration
     * 2026_07_18_020100_seed_order_entry_tanks_for_vd_machines).
     */
    public function tanks(Request $request)
    {
        $query = Tank::orderBy('code');
        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }
        return response()->json([
            'status' => 'SUCCESS',
            'data' => $query->get(['id', 'machine_id', 'code', 'name']),
        ]);
    }

    /**
     * Tách chuỗi quét màn hình Nhập đơn sản xuất — port đúng VBA Box1_AfterUpdate
     * (xem QrPayloadService::parseOrderEntryScan). Trả về field thô (chuỗi), KHÔNG
     * resolve machine/tank ra ID ở đây — để frontend đối chiếu với danh mục thật
     * (GET /machines, /tanks) và cho phép người vận hành sửa tay nếu quét sai/thiếu,
     * đúng hành vi VBA (textbox không khóa sau khi auto-fill từ scan).
     */
    public function scanParse(Request $request)
    {
        $request->validate(['raw_scan' => 'required|string']);

        $raw = $request->input('raw_scan');
        $parsed = app(QrPayloadService::class)->parseOrderEntryScan($raw);
        // Debug tạm thời (2026-07-19): trả kèm raw_scan để soi lỗi máy quét thật đọc
        // sai/lệch nội dung so với QR gốc — xóa khi đã xác định xong nguyên nhân.
        $parsed['debug_raw_scan'] = $raw;

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $parsed,
        ]);
    }

    /**
     * List production batches with pagination and filters.
     */
    public function index(Request $request)
    {
        // 'dispatches' (queue_state) can thiet de frontend biet lo nao da CONFIRMED
        // (da bam OK o /print-station) ma an di khoi bang "lo gan nhat" (yeu cau 2026-07-31).
        // Loại lô CÂN TAY: chúng không phải lệnh sản xuất, không màu/mã hàng/máy, lọt vào đây là
        // danh sách lô đầy những dòng trống. Xem ProductionBatch::MANUAL_BATCH_PREFIX.
        $query = ProductionBatch::query()->khongPhaiCanTay()->with(['machine', 'tank', 'dispatches']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        } else {
            // Lô đã hủy (soft delete qua status=CANCELLED) không hiện ở danh sách mặc định —
            // vẫn xem lại được bằng cách lọc tường minh status=CANCELLED.
            $query->where('status', '!=', 'CANCELLED');
        }

        if ($request->has('machine_id')) {
            $query->where('machine_id', $request->input('machine_id'));
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('legacy_batch_id', 'like', "%{$search}%")
                  ->orWhere('color', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }

        // per_page tùy chọn — mặc định giữ nguyên 15 để không đổi hành vi phân trang của
        // /production-batches/list và các màn hình khác đang dùng chung endpoint này.
        // Chỉ trang quét đơn (/production-batches) truyền per_page=30 cho bảng "30 lô gần
        // nhất" (yêu cầu 2026-07-31). Chặn trần 100 để không ai vô tình kéo cả bảng.
        $perPage = min((int) $request->input('per_page', 15) ?: 15, 100);

        $batches = $query->orderBy('created_at', 'desc')->paginate($perPage);
        return response()->json($batches);
    }

    /**
     * Create a new production batch — dùng chung cho cả 2 nguồn: đẩy đơn giả lập MES
     * và màn hình quét thật WS-ORDER-01 (mainform.frm/btnSAVE_Click).
     *
     * Chặn trùng color+code+machine (VBA `Exists_ColorCode` chống chèn trùng vào
     * tbl_input_all, thông báo "Da ton tai mau nay") — chỉ tính trùng với đơn CHƯA
     * duyệt (status=NEW, tương đương còn nằm trong tbl_input_all; đơn đã duyệt/dispatch
     * coi như đã "rời" khỏi hàng chờ nhập, y hệt MoveToSend xóa khỏi tbl_input_all).
     * Yêu cầu 2026-07-22: tính cả machine_id vào điều kiện trùng — 2 lô cùng màu+mã
     * hàng nhưng chạy ở 2 máy khác nhau là hợp lệ, không nên cảnh báo nhầm.
     */
    public function store(Request $request)
    {
        $request->validate([
            'legacy_batch_id' => 'required|string|max:100',
            'color' => 'required|string|max:100',
            'product_code' => 'required|string|max:100',
            'machine_id' => 'required|exists:machines,id',
            'tank_id' => 'nullable|exists:tanks,id',
            'level_code' => 'nullable|string|max:50',
            'status' => 'sometimes|string|max:30',
            'raw_qr_dye' => 'nullable|string',
            'raw_qr_chemical' => 'nullable|string',
            'confirm_duplicate' => 'sometimes|boolean',
        ]);

        $duplicate = ProductionBatch::where('color', $request->input('color'))
            ->where('product_code', $request->input('product_code'))
            ->where('machine_id', $request->input('machine_id'))
            ->where('status', 'NEW')
            ->exists();

        // Không còn chặn cứng — chỉ CẢNH BÁO nghi ngờ trùng. Người vận hành tự xác nhận
        // qua tick "vẫn lưu" (confirm_duplicate=true) rồi gọi lại API mới thật sự lưu,
        // vì có trường hợp hợp lệ trùng màu+mã hàng+máy (vd nhập lại đơn bị lỗi trước
        // đó) mà chặn cứng sẽ chặn nhầm nghiệp vụ thật.
        if ($duplicate && !$request->boolean('confirm_duplicate')) {
            return response()->json([
                'status' => 'DUPLICATE_WARNING',
                'message' => 'Nghi ngờ trùng: đã có đơn cùng mã màu + mã hàng + máy đang chờ duyệt (chưa gửi máy). Tick xác nhận rồi lưu lại nếu vẫn muốn tạo mới.',
            ], 409);
        }

        $batch = ProductionBatch::create([
            'legacy_batch_id' => $request->input('legacy_batch_id'),
            'color' => $request->input('color'),
            'product_code' => $request->input('product_code'),
            'machine_id' => $request->input('machine_id'),
            'tank_id' => $request->input('tank_id'),
            'level_code' => $request->input('level_code'),
            'status' => $request->input('status', 'NEW'),
            'raw_qr_dye' => $request->input('raw_qr_dye'),
            'raw_qr_chemical' => $request->input('raw_qr_chemical'),
        ]);

        event(new ProductionBatchUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Production batch created successfully',
            'data' => $batch->load(['machine', 'tank'])
        ], 201);
    }

    /**
     * Update status of a production batch.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|max:30',
        ]);

        $batch = ProductionBatch::findOrFail($id);
        $batch->status = $request->input('status');
        $batch->save();

        event(new ProductionBatchUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Production batch status updated',
            'data' => $batch
        ]);
    }

    /**
     * Gán nhanh Thùng trộn cho lô — dùng ở ô "Thùng Trộn" trên bảng danh sách
     * (yêu cầu 2026-07-22: bấm chọn nhanh ngay tại bảng thay vì phải mở form riêng),
     * hỗ trợ bỏ chọn (tank_id=null) và bắt buộc thùng phải thuộc đúng máy của lô.
     */
    public function updateTank(Request $request, $id)
    {
        $request->validate([
            'tank_id' => 'nullable|exists:tanks,id',
        ]);

        $batch = ProductionBatch::findOrFail($id);
        $tankId = $request->input('tank_id');

        // Yêu cầu 2026-07-26: khóa Thùng trộn sau khi đơn đã duyệt — đơn NEW mới cho đổi
        // thùng tự do, từ APPROVED trở đi coi như đã "chốt" để tránh QR/tem đã phát hành
        // (dùng batch.tank tại thời điểm in) bị lệch so với thùng thực tế đã ghi ở tem.
        if ($batch->status !== 'NEW') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Đơn đã duyệt — không thể đổi Thùng trộn nữa.',
            ], 422);
        }

        if ($tankId !== null) {
            $tank = \App\Models\Tank::find($tankId);
            if (!$tank || $tank->machine_id !== $batch->machine_id) {
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Thùng đã chọn không thuộc đúng máy của lô này.',
                ], 422);
            }
        }

        $batch->tank_id = $tankId;
        $batch->save();

        event(new ProductionBatchUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã cập nhật Thùng trộn.',
            'data' => $batch->load(['machine', 'tank']),
        ]);
    }

    /**
     * Duyệt đơn sản xuất -> tạo Dispatch Job (hàng chờ cho QR_LABEL_PRINTING).
     * Thay thế procedure VBA MoveToSend (Workbook C3). Xem ApproveProductionOrderService.
     */
    public function approve(Request $request, $id)
    {
        $request->validate([
            'correlation_id' => 'sometimes|string|max:100',
            'workstation_id' => 'sometimes|string|max:50',
        ]);

        try {
            $result = app(ApproveProductionOrderService::class)->execute(
                $id,
                optional(auth()->user())->id,
                $request->input('correlation_id'),
                $request->input('workstation_id')
            );
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Không tìm thấy đơn sản xuất.',
            ], 404);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }

        event(new ProductionBatchUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'message' => $result['reused']
                ? 'Đơn đã được duyệt trước đó — trả về hàng chờ đã tạo (idempotent).'
                : 'Đã duyệt đơn và tạo hàng chờ điều phối thành công.',
            'data' => [
                'batch' => $result['batch'],
                'dispatch' => $result['dispatch'],
                'reused' => $result['reused'],
            ],
        ], $result['reused'] ? 200 : 201);
    }
}
