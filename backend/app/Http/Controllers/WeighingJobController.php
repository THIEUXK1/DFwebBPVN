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
use App\Services\WeighingItemRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WeighingJobController extends Controller
{
    /**
     * Trần số vòng cân trả về một lượt cho màn Lịch sử cân. Bảng `weighing_jobs` chỉ có tăng nên
     * không bao giờ được trả hết; 500 dòng (~9 dòng vật tư mỗi vòng) là cỡ vài trăm KB JSON, vẫn
     * nhẹ hơn nhiều so với việc mỗi lần tìm/đổi trang lại đi một vòng HTTP tới DB ở máy khác.
     */
    private const HISTORY_MAX_ROWS = 500;

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
     * Lịch sử cân — mỗi bản ghi là MỘT VÒNG CÂN (một WeighingJob đã COMPLETED), không phải
     * một lô. Từ 2026-08-01 một lô có thể được cân lại nhiều vòng (quét lại mã sau khi đã SAVE
     * sẽ mở vòng mới, xem ScannerController::handleOrderScan), nên gom theo lô sẽ giấu mất các
     * lần cân lại — đúng thứ cần nhìn thấy nhất khi đối soát.
     *
     * KHÔNG phân trang phía server nữa (2026-08-02). Trước đây mỗi lần lọc/đổi trang là một vòng
     * HTTP mới, mà với DB nằm ở máy khác thì mỗi truy vấn tốn ~35ms và mỗi vòng mất vài trăm ms.
     * Nay trả về TRỌN một cửa sổ dữ liệu (mặc định khoảng ngày người dùng chọn, trần
     * HISTORY_MAX_ROWS dòng) để trình duyệt tự tìm kiếm/phân trang tức thì, không chạm mạng.
     *
     * Bảng này chỉ có tăng nên vẫn phải có trần: lấy dư ĐÚNG 1 dòng để biết có bị cắt hay không
     * mà không tốn thêm câu `count(*)` — cái count đó chính là 1 vòng đi-về DB bỏ đi được.
     */
    public function history(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|max:100',
            'workstation_id' => 'nullable|integer|exists:operation_clients,id',
            'limit' => 'nullable|integer|min:1|max:'.self::HISTORY_MAX_ROWS,
        ]);

        $query = WeighingJob::with(['batch.machine', 'items'])
            ->where('status', 'COMPLETED');

        if ($from = $request->input('from')) {
            $query->where('completed_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $request->input('to')) {
            $query->where('completed_at', '<=', Carbon::parse($to)->endOfDay());
        }

        if ($wsId = $request->input('workstation_id')) {
            $query->where('assigned_operation_client_id', $wsId);
        }

        // Tìm theo màu / mã hàng / mã lô / máy — gõ gì cũng ra, thao tác viên không phải nhớ
        // trường nào là trường nào. Dùng `like` (không phải `ILIKE`) cho đồng nhất với toàn bộ
        // controller khác trong dự án và để không khoá cứng vào Postgres — mã màu/mã hàng/mã máy
        // đều viết hoa sẵn nên phân biệt hoa thường không ảnh hưởng thực tế.
        //
        // Màn Lịch sử cân KHÔNG dùng tham số này cho ô tìm kiếm thường ngày (nó lọc ngay tại
        // trình duyệt trong cửa sổ đã tải, không tốn vòng mạng nào) — chỉ dùng khi người dùng bấm
        // "tìm trên toàn bộ lịch sử", tức là cần với ra ngoài cửa sổ đó.
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.$q.'%';
            $query->whereHas('batch', function ($b) use ($like) {
                $b->where('color', 'like', $like)
                    ->orWhere('product_code', 'like', $like)
                    ->orWhere('legacy_batch_id', 'like', $like)
                    ->orWhereHas('machine', fn ($m) => $m->where('code', 'like', $like));
            });
        }

        $limit = (int) $request->input('limit', 200);

        // Lấy dư ĐÚNG 1 dòng: nếu về đủ $limit+1 thì biết ngay là còn nữa, khỏi tốn một câu
        // `count(*)` — với DB ở máy khác, câu count đó là ~35ms thuần đi-về mạng.
        $jobs = $query->orderByDesc('completed_at')
            ->limit($limit + 1)
            ->get();

        $truncated = $jobs->count() > $limit;
        if ($truncated) {
            $jobs = $jobs->take($limit);
        }

        // Đếm ĐẠT/KHÔNG ĐẠT ngay tại đây thay vì để frontend tự suy: process_status là thuộc
        // tính suy diễn của model (getProcessStatusAttribute), tính ở server mới đảm bảo web và
        // báo cáo luôn dùng chung một định nghĩa.
        $jobs->each(function ($job) {
            $items = $job->items;
            $job->setAttribute('total_items', $items->count());
            $job->setAttribute('accepted_count', $items->where('process_status', 'ACCEPTED')->count());
            $job->setAttribute('rejected_count', $items->where('process_status', 'REJECTED')->count());
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'rounds' => $jobs->values(),
                // `truncated` phải được frontend nói rõ ra: im lặng cắt bớt là kiểu bảng "tìm
                // không thấy" mà người dùng tưởng là không có dữ liệu.
                'truncated' => $truncated,
                'limit' => $limit,
            ],
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

        // Loại cả CANCELLED (2026-08-01, WeighingJobController::cancel): job đã bị thao tác viên
        // hủy trắng không được coi là "đang dở" nữa — không job nào để khôi phục lại là đúng.
        $job = WeighingJob::with(['batch.machine', 'batch.tank', 'items.material'])
            ->where('assigned_operation_client_id', $request->input('workstation_id'))
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
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
            //
            // KHÔNG 'min:0' — cùng lý do với 'weight' ở trên, và đã thành lỗi thật ngoài trạm
            // (06/08/2026): "The rows.0.tare_weight field must be at least 0". Hai trường này là
            // SỐ ĐỌC THÔ của mặt cân, mà mặt cân âm là chuyện bình thường — nhấc vật ra khỏi đĩa
            // là nó tụt xuống dưới mốc 0, và bì được chốt tự động ở lần đọc ổn định đầu tiên sau
            // NEXT nên rơi đúng vào lúc đó. Chặn ở đây làm hỏng CẢ MẺ (mất hết 9 ô đã cân) chỉ
            // để bảo vệ một trường thuần audit — đắt hơn nhiều so với việc lưu một số âm đúng
            // như cân đã hiển thị.
            'tare_weight' => 'sometimes|nullable|numeric',
            'gross_weight' => 'sometimes|nullable|numeric',
            // RACK (rebuild bảng 9 dòng RACK/DYE CODE/WEIGHT/PROCESS đúng scaleform.frm VBA
            // gốc) — có thể đã được tự điền từ QR (ScannerController::handleOrderScan) hoặc
            // do thao tác viên tự gõ/sửa tay trên từng dòng trước khi xác nhận cân. Thuần
            // metadata hiển thị/đối soát, không ảnh hưởng logic so dung sai.
            'rack_code' => 'sometimes|nullable|string|max:20',
        ]);

        // Vẫn eager-load job.batch: WeighingItemRecorder đọc tới cả 2 quan hệ này.
        $item = WeighingJobItem::with('job.batch')->findOrFail($id);

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

        $deviceError = $this->assertScaleDeviceBound($request, $scaleDeviceId);
        if ($deviceError) {
            return $deviceError;
        }

        return DB::transaction(function () use ($item, $measuredWeight, $tareWeight, $grossWeight, $rackCode) {
            // Đường ghi dùng chung với weighBatch (/weighing-station-v2) — xem WeighingItemRecorder.
            $result = app(WeighingItemRecorder::class)->record($item, [
                'weight' => $measuredWeight,
                'tare_weight' => $tareWeight,
                'gross_weight' => $grossWeight,
                'rack_code' => $rackCode,
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Lưu số cân thành công.',
                'data' => $result,
            ]);
        });
    }

    /**
     * Lưu CẢ MẺ trong 1 lần — port đúng VBA scaleform.btnSave_Click (workbook
     * "4.semiauto-small scale ... DF026-027.xlsm"): thao tác viên bấm NEXT chạy hết 9 ô, giá
     * trị từng ô giữ trên màn hình, tới lúc bấm SAVE mới ghi 1 header + N dòng xuống DB.
     * Dùng cho /weighing-station-v2. Màn hình cũ /weighing-station vẫn dùng weighItem (lưu
     * ngay từng dòng) — 2 luồng chạy song song, chung 1 đường ghi WeighingItemRecorder.
     *
     * Toàn bộ nằm trong 1 transaction: mẻ cân là một đơn vị nghiệp vụ, không được lưu nửa vời.
     */
    public function weighBatch(Request $request, $id)
    {
        $request->validate([
            'rows' => 'required|array|min:1',
            'rows.*.item_id' => 'required|string',
            // 'present|nullable' chứ không 'required': VBA btnSave_Click ghi MỌI dòng có
            // WEIGHT mục tiêu khác rỗng, kể cả ô PROCESS chưa hề cân (weight = null) —
            // những dòng đó bị gắn REJECTED. Không 'min:0' vì cân cộng dồn trên cùng 1 đĩa,
            // net có thể lệch âm so với bì đã chốt.
            'rows.*.weight' => 'present|nullable|numeric',
            'rows.*.rack_code' => 'sometimes|nullable|string|max:20',
            // Xem ghi chú dài ở weighItem(): bì/gộp là SỐ ĐỌC THÔ của mặt cân, âm được, và chặn
            // min:0 ở đây từng làm cả mẻ 9 ô không lưu được (lỗi thật 06/08/2026).
            'rows.*.tare_weight' => 'sometimes|nullable|numeric',
            'rows.*.gross_weight' => 'sometimes|nullable|numeric',
            'scale_device_id' => 'required|string',
            'stable' => 'required|boolean',
            // Có gửi thì dựng luôn phiếu cân trả kèm response (bỏ được request /print-slip
            // riêng); không gửi thì hành vi giữ nguyên như cũ.
            //
            // CỐ Ý KHÔNG đặt tên là 'workstation_code': assertScaleDeviceBound() bên dưới cũng
            // đọc chính khoá đó để quyết định có bắt buộc kiểm tra thiết bị cân hay không. Đặt
            // trùng tên sẽ vô tình bật hàng rào đó lên cho màn hình V2 (vốn gửi scale_device_id
            // 'MOCK_SCALE' khi trạm chưa gán cân) và làm SAVE trả 400 — đổi tên là để giữ
            // nguyên hành vi hiện có, không phải né kiểm tra.
            'slip_workstation_code' => 'sometimes|string|exists:operation_clients,code',
        ]);

        // Nạp sẵn batch.machine: recordMany dùng cả hai để dựng scale_measurements, và
        // buildAndStoreSlip dùng lại batch — trước đây mỗi dòng cân tự lazy-load lại từ đầu.
        $job = WeighingJob::with('batch.machine')->findOrFail($id);

        // Cùng hàng rào với weighItem: client có thể gọi thẳng API, không phụ thuộc UI.
        if (! $request->boolean('stable')) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau trước khi xác nhận.',
                'error_code' => 'NOT_STABLE',
            ], 422);
        }

        $deviceError = $this->assertScaleDeviceBound($request, $request->input('scale_device_id'));
        if ($deviceError) {
            return $deviceError;
        }

        $rows = collect($request->input('rows'))->keyBy('item_id');

        // Chỉ nhận vật tư THUỘC ĐÚNG job này — chặn ghi nhầm sang mẻ khác nếu client gửi sai id.
        // Nạp sẵn material để dựng phiếu in ngay trong response này (xem cuối hàm) mà không phải
        // truy vấn lại cả job/items/material ở một request /print-slip riêng.
        $items = WeighingJobItem::with('material')
            ->where('weighing_job_id', $job->id)
            ->whereIn('id', $rows->keys()->all())
            ->orderBy('sequence_no', 'asc')
            ->get();

        if ($items->count() !== $rows->count()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Có dòng không thuộc mẻ cân này — tải lại màn hình và cân lại.',
                'error_code' => 'ITEM_NOT_IN_JOB',
            ], 422);
        }

        $workstationCode = $request->input('slip_workstation_code');

        return DB::transaction(function () use ($items, $rows, $job, $workstationCode) {
            $result = app(WeighingItemRecorder::class)->recordMany($job, $items, $rows);

            $saved = $result['saved'];
            $skipped = $result['skipped'];

            // Dựng LUÔN phiếu cân và trả kèm response (2026-08-02). Trước đây màn hình V2 phải
            // gọi tiếp POST /print-slip sau khi SAVE xong — thêm nguyên một vòng HTTP + nạp lại
            // job/items/material/batch/machine chỉ để in đúng những dòng vừa ghi. Với DB ở máy
            // khác (~36ms/query đo thật) đó là chỗ "bấm SAVE xong chờ mãi tem mới hiện".
            $slip = null;
            if ($workstationCode) {
                $slip = $this->buildAndStoreSlip($job, $items, $workstationCode);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'message' => count($skipped) > 0
                    ? 'Đã lưu '.count($saved).' dòng, bỏ qua '.count($skipped).' dòng đã cân xong trước đó.'
                    : 'Đã lưu '.count($saved).' dòng cân.',
                'data' => [
                    'saved_item_ids' => $saved,
                    'skipped_item_ids' => $skipped,
                    'job_completed' => $result['job_completed'],
                    // null khi client không gửi workstation_code — client tự gọi /print-slip như cũ.
                    'slip' => $slip,
                ],
            ]);
        });
    }

    /**
     * Bỏ một vòng cân CHƯA HỀ GHI GÌ (2026-08-01) — dọn dấu vết khi thao tác viên quét nhầm đơn
     * rồi rời đi mà không SAVE, hoặc bấm CLEAR trước khi cân dòng nào. Từ khi mỗi máy có vòng
     * cân riêng (xem ScannerController::handleOrderScan), mỗi lần quét bỏ dở như vậy để lại một
     * WeighingJob mồ côi — nếu không dọn, lô đó KHÔNG BAO GIỜ về được trạng thái WEIGHED (cascade
     * trong WeighingItemRecorder đòi TẤT CẢ job của lô phải COMPLETED), và trạm Vận Chuyển không
     * bao giờ nhận được thùng.
     *
     * Hàng rào bắt buộc: CHỈ hủy khi CHƯA có dòng nào COMPLETED. Còn dòng đã cân thật (kể cả
     * job dở dang hợp lệ ở /weighing-station, nơi weighItem lưu ngay từng dòng) thì từ chối —
     * đây là dữ liệu giao dịch thật, không được xoá/hủy tuỳ tiện (CLAUDE.md mục 3). Muốn bỏ một
     * job đã có dữ liệu thật thì dùng restart() (có audit log, có lý do, có xác nhận rõ ràng).
     *
     * Không ghi AuditLog: khác restart(), hành động này không làm mất bất kỳ số cân thật nào —
     * job vẫn còn nguyên trong DB với status CANCELLED để đối soát, chỉ đổi ý nghĩa "không tính
     * vào vòng cân nào của lô nữa".
     */
    public function cancel(Request $request, $id)
    {
        $job = WeighingJob::with('items')->findOrFail($id);

        if ($job->status === 'CANCELLED') {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Vòng cân này đã được hủy trước đó.',
                'data' => $job,
            ]);
        }

        if ($job->status === 'COMPLETED') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Vòng cân đã hoàn tất — không thể hủy.',
                'error_code' => 'JOB_ALREADY_COMPLETED',
            ], 409);
        }

        $daCan = $job->items->where('status', 'COMPLETED')->count();
        if ($daCan > 0) {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Vòng cân này đã cân {$daCan} dòng — không thể hủy trắng. Dùng chức năng CÂN LẠI TỪ ĐẦU nếu muốn bỏ toàn bộ kết quả.",
                'error_code' => 'JOB_HAS_COMPLETED_ITEMS',
            ], 409);
        }

        $job->status = 'CANCELLED';
        $job->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã hủy vòng cân trống.',
            'data' => $job,
        ]);
    }

    /**
     * Thiết bị cân phải được gán + kích hoạt cho đúng máy trạm đang gọi. Tách ra để weighItem
     * và weighBatch dùng chung đúng một quy tắc. Trả về response lỗi, hoặc null nếu hợp lệ.
     */
    private function assertScaleDeviceBound(Request $request, string $scaleDeviceId)
    {
        $client = $request->attributes->get('operation_client');
        if (! $client) {
            $code = $request->header('X-Workstation-Code') ?? $request->input('workstation_code');
            if ($code) {
                $client = OperationClient::where('code', $code)->first();
            }
        }

        if (! $client) {
            return null;
        }

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

        return null;
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
     *
     * MỌI lượt gọi endpoint này đều là IN LẠI: luồng SAVE của /weighing-station-v2 dựng phiếu
     * thẳng trong response (buildSlipForJob), không đi qua đây. Nên đây là chỗ đúng để ghi Audit
     * Log theo CLAUDE.md mục 5 ("In lại tem (Reprint) ... phải ghi Audit Log bất biến") — trước
     * 2026-08-02 chỉ có bản ghi PrintJob, không truy được AI bấm in lại.
     */
    public function printSlip(Request $request, $id)
    {
        $request->validate([
            'workstation_code' => 'sometimes|string|exists:operation_clients,code',
        ]);

        $job = WeighingJob::with(['items.material', 'batch.machine'])->findOrFail($id);

        // Màn Lịch sử cân chạy trên máy văn phòng, không gắn trạm nào — nhưng phiếu vẫn phải mang
        // đúng mã trạm ĐÃ CÂN ra nó, nếu không phiếu in lại lại ghi tên một trạm không liên quan.
        // Trạm gốc của vòng cân vì thế là nguồn ưu tiên hơn "máy đang đứng" ở nhánh cuối.
        $client = $request->attributes->get('operation_client');
        $workstationCode = $request->input('workstation_code')
            ?? $job->workstation?->code
            ?? ($client ? $client->code : null);
        if (! $workstationCode) {
            return response()->json(['status' => 'ERROR', 'message' => 'Không xác định được mã trạm.'], 400);
        }

        $printJob = $this->buildAndStoreSlip($job, $job->items, $workstationCode);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'WEIGH_SLIP_REPRINT',
            'entity_type' => 'WeighingJob',
            'entity_id' => $job->id,
            'after_data' => [
                'weighing_job_id' => $job->id,
                'production_batch_id' => $job->production_batch_id,
                'color' => $job->batch?->color,
                'product_code' => $job->batch?->product_code,
                'machine_code' => $job->batch?->machine?->code,
                'workstation_code' => $workstationCode,
                'print_job_id' => $printJob->id,
                'item_count' => $job->items->count(),
            ],
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã tạo phiếu cân — in qua hộp thoại in của trình duyệt.',
            'data' => $printJob,
        ], 201);
    }

    /**
     * Dựng nội dung phiếu cân (TSPL) + lưu PrintJob. Tách riêng để luồng SAVE của
     * /weighing-station-v2 dựng phiếu NGAY trong response weigh-batch, không phải gọi thêm một
     * request /print-slip nữa (2026-08-02) — với DB ở máy khác, vòng HTTP thừa đó chính là chỗ
     * "bấm SAVE xong chờ mãi tem mới hiện".
     *
     * `$items` truyền vào từ ngoài để tái dùng đúng collection vừa ghi xong, khỏi query lại.
     *
     * `buildSlipForJob()` là cửa công khai cho ScannerController::weighFromQr — luồng "một lệnh
     * duy nhất" của /weighing-station-v2 cũng cần đúng phiếu này.
     */
    public function buildSlipForJob(WeighingJob $job, $items, string $workstationCode, ?string $printedAt = null): PrintJob
    {
        return $this->buildAndStoreSlip($job, $items, $workstationCode, $printedAt);
    }

    /**
     * `$printedAt` do trình duyệt gửi lên: từ 2026-08-02 màn hình cân IN NGAY từ dữ liệu trên
     * màn, trước khi chạm mạng. Nếu để server tự lấy giờ của mình thì bản ghi print_jobs sẽ mang
     * một mốc giờ khác với tờ phiếu đã ra giấy — đúng thứ mà bản ghi này phải đối chiếu được.
     */
    private function buildAndStoreSlip(WeighingJob $job, $items, string $workstationCode, ?string $printedAt = null): PrintJob
    {
        // Tái dùng batch đã nạp kèm job nếu có (luồng weigh-batch nạp sẵn 'batch.machine'),
        // chỉ truy vấn lại khi thật sự chưa có (luồng /print-slip gọi độc lập).
        $batch = $job->relationLoaded('batch') && $job->batch
            ? $job->batch->loadMissing('machine')
            : ProductionBatch::with('machine')->where('id', $job->production_batch_id)->firstOrFail();
        $workstation = OperationClient::where('code', $workstationCode)->firstOrFail();

        $noiDung = self::buildSlipHtml([
            'color' => $batch->color,
            'product_code' => $batch->product_code,
            'machine_code' => $batch->machine ? $batch->machine->code : 'N/A',
            'level_code' => $batch->level_code,
            'printed_at' => $printedAt,
        ], $items);

        // In qua trình duyệt (yêu cầu 2026-07-30) — xem ghi chú ở printLabel().
        return PrintJob::create([
            'workstation_id' => $workstation->code,
            'label_payload' => $noiDung,
            'printer_connection_type' => 'BROWSER',
            'printer_address' => 'BROWSER',
            'status' => 'PRINTED',
            'processed_at' => now(),
        ]);
    }

    /**
     * Khổ giấy phiếu (mm) và lề, lấy từ chính DEVMODE lưu trong workbook
     * `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm`
     * (`xl/printerSettings/printerSettings1.bin`: TSC TTP-244 Pro, dmPaperWidth 533,
     * dmPaperLength 1016 → 53.3 x 101.6mm, dọc). Lề 0.2cm = đúng PageSetup của btnPrint_Click.
     *
     * Để dạng chuỗi chứ không phải float: chuỗi phiếu phải khớp TỪNG KÝ TỰ với bản
     * `frontend/src/utils/weighSlip.ts`, mà PHP và JS in số thực ra chuỗi không phải lúc nào cũng
     * giống nhau. Đổi cuộn tem thì sửa cả hai nơi.
     */
    private const SLIP_PAGE_W_MM = '53.3';

    private const SLIP_PAGE_H_MM = '101.6';

    private const SLIP_PAGE_MARGIN_MM = '2';

    /** Số dòng dữ liệu luôn in ra — `For i = 1 To 9` của VBA. */
    private const SLIP_DATA_ROWS = 9;

    /**
     * Dựng NỘI DUNG phiếu cân (mảnh HTML) — hàm THUẦN: không chạm DB, không phụ thuộc request.
     *
     * Tách khỏi buildAndStoreSlip() vì hai lý do:
     *   1. Trình duyệt có một bản port của đúng hàm này (`frontend/src/utils/weighSlip.ts`) để in
     *      được phiếu khi MẤT MẠNG (mẻ nằm hàng đợi chờ đẩy lên sau). Hai bản phải ra chuỗi y hệt
     *      nhau, nếu không phiếu in lúc mất mạng sẽ khác phiếu in lúc bình thường.
     *   2. Có tách ra thì mới đối chiếu được hai bản bằng script mà KHÔNG ghi PrintJob nào xuống
     *      DB (xem `frontend/scripts/check-weigh-slip.mjs`).
     *
     * `$header` là mảng phẳng (color/product_code/machine_code/level_code) chứ không phải model,
     * để script đối chiếu dựng đầu vào mà không cần bản ghi thật.
     *
     * ===================== NGUỒN BỐ CỤC (06/08/2026) =====================
     * Yêu cầu người dùng: "tem in ra giống form VBA 100%". Đây là bản dựng lại nguyên văn
     * `scaleform.btnPrint_Click` của workbook đang chạy ngoài xưởng — VBA KHÔNG in TSPL mà
     * `Sheets.Add` một sheet mới, đổ dữ liệu vào ô, kẻ viền toàn vùng A1:E19, đặt Calibri 12,
     * AutoFit cột, fit 1 trang, rồi `PrintOut ActivePrinter:="TSC TTP-224 Pro"`.
     *
     * Ghi chú đầy đủ (kể cả 3 chi tiết trông như lỗi nhưng chính là bản gốc — dòng in đậm là dòng
     * 1 và dòng 7 TRỐNG chứ không phải dòng tiêu đề bảng; cột WEIGHT là MỤC TIÊU còn PROCESS mới
     * là số cân THỰC TẾ; luôn in đủ 9 dòng) nằm ở đầu `frontend/src/utils/weighSlip.ts`. Sửa bố
     * cục thì đọc ghi chú đó trước.
     */
    public static function buildSlipHtml(array $header, $items): string
    {
        $color = (string) ($header['color'] ?? '');
        $productCode = (string) ($header['product_code'] ?? '');
        $machineCode = (string) ($header['machine_code'] ?? 'N/A');
        $levelCode = (string) ($header['level_code'] ?? '');
        // `printed_at` cho phép script đối chiếu ghim cứng một mốc giờ; bỏ trống thì lấy giờ hiện tại.
        $printedAt = (string) ($header['printed_at'] ?? Carbon::now()->format('d/m/Y H:i:s'));

        $rows = [];

        // r1..r8 — phần đầu phiếu, đúng thứ tự điền ô của btnPrint_Click.
        $rows[] = self::slipRow(['DF_WEIGHING_SLIP', '', '', '', ''], true);
        $rows[] = self::slipRow(['', '', '', '', '']);
        $rows[] = self::slipRow(['COLOR:', $color, '', '', '']);
        $rows[] = self::slipRow(['CODE:', $productCode, '', '', '']);
        $rows[] = self::slipRow(['MACHINE:', $machineCode, '', '', '']);
        $rows[] = self::slipRow(['LEVEL:', $levelCode, '', '', '']);
        // Dòng 7 trống nhưng IN ĐẬM — đúng `ws.Range("A7:E7").Font.Bold = True`; chính vì dòng
        // này mà dòng tiêu đề bảng bên dưới lại KHÔNG đậm.
        $rows[] = self::slipRow(['', '', '', '', ''], true);
        $rows[] = self::slipRow(['RACK', 'DYE CODE', 'WEIGHT', 'PROCESS', 'STATUS']);

        // r9..r17 — luôn đủ 9 dòng. `values()` để đánh chỉ số lại từ 0: collection lấy từ quan hệ
        // Eloquent không đảm bảo khoá liên tục, mà ở đây phải duyệt theo VỊ TRÍ dòng trên phiếu.
        $danhSach = collect($items)->values();
        $soDong = max(self::SLIP_DATA_ROWS, $danhSach->count());
        for ($i = 0; $i < $soDong; $i++) {
            $item = $danhSach->get($i);
            if (! $item) {
                // Dòng trống VẪN có STATUS = REJECTED: ô txt_process của nó nền trắng, mà
                // GetProcessStatus trả REJECTED cho mọi nền không phải xanh — xem slipStatus().
                $rows[] = self::slipRow(['', '', '', '', 'REJECTED']);

                continue;
            }

            $planned = $item->planned_weight;
            $actual = $item->actual_weight;

            $rows[] = self::slipRow([
                (string) ($item->rack_code ?? ''),
                (string) ($item->material_code ?? ''),
                $planned === null || $planned === '' ? '' : number_format((float) $planned, 2, '.', ''),
                $actual === null || $actual === '' ? '' : number_format((float) $actual, 2, '.', ''),
                self::slipStatus((string) ($item->process_status ?? '')),
            ]);
        }

        // r18 trống, r19 mốc giờ in — VBA để "Print time:" ở CUỐI phiếu.
        $rows[] = self::slipRow(['', '', '', '', '']);
        $rows[] = self::slipRow(['Print time:', $printedAt, '', '', '']);

        return '<div class="df-slip-page" data-w="'.self::SLIP_PAGE_W_MM.'" data-h="'.self::SLIP_PAGE_H_MM.'"'.
               ' data-m="'.self::SLIP_PAGE_MARGIN_MM."\">\n".
               "<style>\n".self::slipCss()."</style>\n".
               "<table class=\"df-slip\">\n".
               implode("\n", $rows).
               "\n</table>\n</div>";
    }

    /**
     * CSS của phiếu — bản dựng lại định dạng mà btnPrint_Click áp cho vùng A1:E19 (Calibri 12,
     * viền mọi ô, AutoFit cột). Phải trùng TỪNG KÝ TỰ với `SLIP_CSS` trong
     * `frontend/src/utils/weighSlip.ts`.
     */
    private static function slipCss(): string
    {
        return '@page { size: '.self::SLIP_PAGE_W_MM.'mm '.self::SLIP_PAGE_H_MM.'mm; margin: '.self::SLIP_PAGE_MARGIN_MM."mm; }\n".
               "html, body { margin: 0; padding: 0; background: #fff; }\n".
               ".df-slip-page { font-family: Calibri, Carlito, \"Segoe UI\", sans-serif; color: #000; }\n".
               ".df-slip { border-collapse: collapse; font-size: 12pt; }\n".
               '.df-slip td { border: 0.2mm solid #000; padding: 0 0.8mm; white-space: nowrap;'.
               " height: 5.3mm; line-height: 5.3mm; }\n".
               ".df-slip tr.b td { font-weight: 700; }\n";
    }

    /**
     * Port của `Mod_print_tsc224.GetProcessStatus` — hàm dựng cột STATUS trên phiếu:
     *
     *     Select Case tb.BackColor
     *         Case RGB(120, 250, 20): "ACCEPTED"
     *         Case Else:              "REJECTED"
     *
     * Chỉ ô nền XANH mới ACCEPTED; vàng (thiếu), đỏ (vượt) và cả ô TRẮNG của dòng chưa đụng tới
     * đều ra REJECTED. Bản port đầy đủ kèm lý do nằm ở `frontend/src/utils/weighSlip.ts`
     * (`statusInRaPhieu`) — hai bản phải ra chuỗi y hệt nhau.
     */
    private static function slipStatus(string $status): string
    {
        // MANUAL (cân tay) là luồng chỉ có ở web, VBA không có — giữ nguyên nhãn riêng.
        if ($status === 'MANUAL') {
            return 'MANUAL';
        }

        return $status === 'ACCEPTED' ? 'ACCEPTED' : 'REJECTED';
    }

    /**
     * Một hàng 5 ô của sheet. `$bold` = VBA có `.Font.Bold = True` cho cả hàng đó.
     *
     * `htmlspecialchars` với ENT_QUOTES để khớp đúng bản escape bên JS (`&`, `<`, `>`, `"`, `'`).
     */
    private static function slipRow(array $cells, bool $bold = false): string
    {
        $tds = '';
        foreach ($cells as $cell) {
            $tds .= '<td>'.htmlspecialchars((string) ($cell ?? ''), ENT_QUOTES, 'UTF-8').'</td>';
        }

        return '<tr'.($bold ? ' class="b"' : '').'>'.$tds.'</tr>';
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
