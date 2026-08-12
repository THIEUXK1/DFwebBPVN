<?php
// backend/app/Http/Controllers/BpdbMachineController.php
//
// API giám sát máy VD (mục 7, "YÊU CẦU RÀ SOÁT VÀ BỔ SUNG GIÁM SÁT MÁY VD TRÊN BPDB
// ADMIN"). Đặt dưới prefix /admin/bpdb (nhất quán với BpdbAdminController hiện có,
// thay vì /api/bpdb/machines như tài liệu gợi ý) — cùng middleware role:ADMIN.
//
// Mọi response đều có envelope lastSyncedAt/dataAgeSeconds/source/readOnly/stale (mục 9,
// "YÊU CẦU KHÓA MODULE GIÁM SÁT BPDB") để frontend không hiển thị như đang realtime mà
// không cảnh báo khi dữ liệu đã cũ.

namespace App\Http\Controllers;

use App\Events\BpdbMachineRunningAlert;
use App\Models\FeatureFlag;
use App\Services\ColorService\BpdbMachineMonitoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BpdbMachineController extends Controller
{
    public function status(Request $request, BpdbMachineMonitoringService $service)
    {
        $data = $service->getAllMachineStatuses();
        $machines = $data['machines'];

        if ($machine = $request->query('machine')) {
            $machines = array_values(array_filter($machines, fn ($m) => $m['machineCode'] === $machine));
        }
        if ($status = $request->query('status')) {
            $machines = array_values(array_filter($machines, fn ($m) => $m['operationalStatus'] === $status));
        }
        if ($request->query('hasError') === '1') {
            $machines = array_values(array_filter($machines, fn ($m) => $m['operationalStatus'] === 'ERROR' || !empty($m['stuckWarning'])));
        }
        if ($request->query('activeOnly') === '1') {
            $machines = array_values(array_filter($machines, fn ($m) => $m['activeTaskCount'] > 0));
        }
        if ($jitQueue = $request->query('jitQueue')) {
            $machines = array_values(array_filter($machines, fn ($m) => ($m['currentTask']['jitQueue'] ?? null) === $jitQueue));
        }

        return response()->json(array_merge(
            ['data' => $machines, 'bpdbConnected' => $data['bpdb_connected']],
            $this->envelope($data['fetched_at'])
        ));
    }

    public function show(string $machineCode, BpdbMachineMonitoringService $service)
    {
        $detail = $service->getMachineDetail($machineCode);
        if (!$detail) {
            // Không trả lỗi SQL/connection string về frontend (mục 3) — chỉ mã lỗi nghiệp vụ.
            return response()->json(['error' => 'MACHINE_NOT_FOUND'], 404);
        }
        return response()->json(array_merge(['data' => $detail], $this->envelope(now()->toIso8601String())));
    }

    public function timeline(string $machineCode, Request $request, BpdbMachineMonitoringService $service)
    {
        $timeline = $service->getMachineTimeline(
            $machineCode,
            $request->query('from'),
            $request->query('to'),
            (int) $request->query('limit', 50)
        );

        return response()->json(array_merge(['data' => $timeline], $this->envelope(now()->toIso8601String())));
    }

    public function statusSummary(BpdbMachineMonitoringService $service)
    {
        $summary = $service->getStatusSummary();
        return response()->json(array_merge($summary, $this->envelope($summary['bpdbLastSyncedAt'] ?? now()->toIso8601String())));
    }

    /** Dữ liệu cho biểu đồ Gantt "Máy VD" — groups (máy) + items (mẻ/task). */
    public function gantt(Request $request, BpdbMachineMonitoringService $service)
    {
        $data = $service->getGanttTimeline($request->query('fromDate'), $request->query('toDate'));

        return response()->json(array_merge(
            [
                'groups' => $data['groups'],
                'items' => $data['items'],
                'totalRecords' => $data['totalRecords'],
                'bpdbConnected' => $data['bpdb_connected'],
            ],
            $this->envelope($data['fetched_at'])
        ));
    }

    /**
     * Tổng số mẻ của 1 mã màu - mã hàng đã từng chạy từ đầu tới hiện tại (bảng chi tiết
     * khi bấm vào thanh Gantt). Tách khỏi /gantt vì quét toàn bộ lịch sử — chỉ gọi khi
     * người dùng thật sự mở 1 mẻ ra xem.
     */
    public function lotTotal(Request $request, BpdbMachineMonitoringService $service)
    {
        $color = trim((string) $request->query('color', ''));
        $productCode = trim((string) $request->query('productCode', ''));

        if ($color === '' || $productCode === '') {
            return response()->json(['error' => 'LOT_REQUIRED'], 422);
        }

        $data = $service->getLotRunTotal($color, $productCode);

        return response()->json(array_merge($data, $this->envelope(now()->toIso8601String())));
    }

    /**
     * Toàn bộ thông tin mẻ trong MES (khách, đơn/MO, công thức, SL/khối lượng, người+giờ,
     * ngày giao, ghi chú SX...) — gọi khi bấm vào 1 thanh Gantt đã ghép được với MES.
     * Đọc từ cache mes_batch_completions (đồng bộ định kỳ), không gọi thẳng MES lúc render.
     */
    public function mesBatch(Request $request, BpdbMachineMonitoringService $service)
    {
        $batchNo = trim((string) $request->query('batchNo', ''));
        $lineNo = trim((string) $request->query('lineNo', '1'));

        if ($batchNo === '') {
            return response()->json(['error' => 'BATCH_REQUIRED'], 422);
        }

        $detail = $service->getMesBatchDetail($batchNo, $lineNo === '' ? '1' : $lineNo);
        if ($detail === null) {
            return response()->json(['error' => 'MES_BATCH_NOT_FOUND'], 404);
        }

        return response()->json([
            'data' => $detail,
            'source' => 'MES',
            'readOnly' => true,
            'syncedAt' => $detail['syncedAt'],
        ]);
    }

    /**
     * Bảng liệu của 1 mẻ (khi bấm vào thanh Gantt): SONG SONG công thức MES (thuốc nhuộm +
     * hóa chất, G/L) và cân thật BPDB (rack + gram). Nhận định danh mà frontend đã có sẵn
     * trên item Gantt: mesBatchNo/mesLineNo (để tra công thức MES) + machineCode/taskTitle/
     * start/end (để tra SUP_Storico). Chỉ đọc.
     */
    public function batchRecipe(Request $request, BpdbMachineMonitoringService $service)
    {
        $recipe = $service->getBatchRecipe(
            $request->query('mesBatchNo') ?: null,
            $request->query('mesLineNo') ?: null,
            $request->query('machineCode') ?: null,
            $request->query('taskTitle') ?: null,
            $request->query('start') ?: null,
            $request->query('end') ?: null,
        );

        return response()->json([
            'data' => $recipe,
            'readOnly' => true,
        ]);
    }

    /**
     * Khoá cache lưu danh sách alert "đang chạy" còn hiệu lực — mảng JSON gộp chung 1 key
     * (yêu cầu 2026-08-12). Không dùng bảng DB riêng: đây vẫn chỉ là tín hiệu UI ngắn hạn,
     * không phải dữ liệu nghiệp vụ cần audit/lịch sử (xem ghi chú notifyRunning() bên dưới).
     * Gộp 1 key thay vì 1 key/máy vì cache driver mặc định của app là 'file' (CACHE_STORE
     * trong .env) — không liệt kê được key theo prefix như Redis SCAN, nên phải tự đọc/lọc
     * hết hạn thủ công mỗi lần thay vì để cache TTL từng phần tử tự dọn.
     */
    private const RUNNING_ALERTS_CACHE_KEY = 'bpdb_machine_running_alerts';
    private const RUNNING_ALERT_TTL_SECONDS = 180; // khớp BLINK_DURATION_MS bên MachineIdBoard.vue.

    /**
     * Nhận tín hiệu "1 mẻ VỪA chuyển sang đang chạy" từ trang test `/bpdb-machines/gantt-test`
     * (client tự phát hiện qua diff `newlyAppearedRunningIds`, xem BpdbMachinesGanttTest.vue)
     * và bắn broadcast để `/machine-id-board` nhấp nháy đỏ đúng mã máy đó.
     *
     * Không ghi DB (bảng nghiệp vụ) — đây là tín hiệu UI ngắn hạn, không phải hành động
     * nghiệp vụ cần Audit Log (CLAUDE.md mục 5 chỉ bắt buộc với duyệt công thức/override dung
     * sai/reprint/force unlock/troubleshooting KB). Endpoint public (không auth) nên validate
     * chặt định dạng machineCode để chặn payload rác.
     *
     * Đồng thời LƯU TẠM vào cache (yêu cầu 2026-08-12, báo cáo thực tế: "máy đang nhấp nháy
     * bên machine-id-board nhưng mở gantt-test lên không thấy gì") — trước đây tín hiệu chỉ
     * tồn tại trên kênh Reverb tại đúng khoảnh khắc bắn: tab nào mở SAU thời điểm đó (dù cùng
     * đang trong "cửa sổ 3 phút" của 1 tab khác đã mở từ trước) sẽ không bao giờ nhận được
     * sự kiện đó nữa, hoàn toàn không biết máy nào đang "trong cửa sổ nhấp nháy". Cache đóng
     * vai trò "phát lại" cho các tab mở muộn — xem activeAlerts() bên dưới.
     */
    public function notifyRunning(Request $request)
    {
        $validated = $request->validate([
            'machineCode' => ['required', 'string', 'regex:/^VD\d+$/i'],
            'tankLabel' => ['nullable', 'string', 'max:8'],
            'color' => ['nullable', 'string', 'max:64'],
            'productCode' => ['nullable', 'string', 'max:64'],
            'taskId' => ['nullable', 'string', 'max:64'],
        ]);

        $validated['occurredAt'] = now()->toIso8601String();
        $expiresAt = now()->addSeconds(self::RUNNING_ALERT_TTL_SECONDS);

        // Lọc hết hạn + bỏ bản ghi cũ trùng taskId (mẻ báo lại) trước khi thêm bản mới, rồi
        // ghi đè nguyên mảng — TTL của chính key cache canh dư (không phải nguồn xác định hết
        // hạn thật, field `expiresAt` trong từng phần tử mới là nguồn thật, lọc ở đây và ở
        // activeAlerts() đều dựa vào field đó).
        $alerts = collect(Cache::get(self::RUNNING_ALERTS_CACHE_KEY, []))
            ->reject(fn ($a) => !isset($a['expiresAt']) || Carbon::parse($a['expiresAt'])->isPast())
            ->reject(fn ($a) => $validated['taskId'] !== null && ($a['taskId'] ?? null) === $validated['taskId'])
            ->push(array_merge($validated, ['expiresAt' => $expiresAt->toIso8601String()]))
            ->values()
            ->all();
        Cache::put(self::RUNNING_ALERTS_CACHE_KEY, $alerts, self::RUNNING_ALERT_TTL_SECONDS + 30);

        event(new BpdbMachineRunningAlert($validated));

        return response()->noContent();
    }

    /**
     * Danh sách alert "đang chạy" còn hiệu lực (yêu cầu 2026-08-12) — /machine-id-board và
     * /bpdb-machines/gantt-test gọi lúc mount để "bắt kịp" trạng thái nhấp nháy hiện tại,
     * bổ sung cho kênh Reverb (kênh chỉ báo được sự kiện xảy ra SAU khi đã kết nối, xem ghi
     * chú notifyRunning()). Public, không auth — cùng nhóm với notifyRunning()/gantt().
     */
    public function activeAlerts(Request $request)
    {
        $alerts = collect(Cache::get(self::RUNNING_ALERTS_CACHE_KEY, []))
            ->reject(fn ($a) => !isset($a['expiresAt']) || Carbon::parse($a['expiresAt'])->isPast())
            ->values();

        return response()->json(['data' => $alerts]);
    }

    /**
     * Envelope chung (mục 9): lastSyncedAt/dataAgeSeconds/source/readOnly/stale.
     * Ngưỡng "cũ" cấu hình qua feature_flags (Admin sửa được), mặc định 60 giây.
     */
    private function envelope(string $lastSyncedAtIso): array
    {
        $lastSyncedAt = \Illuminate\Support\Carbon::parse($lastSyncedAtIso);
        $ageSeconds = now()->diffInSeconds($lastSyncedAt, true);

        $staleFlag = FeatureFlag::where('key', 'bpdb_stale_threshold_seconds')->first();
        $staleThreshold = $staleFlag && is_numeric($staleFlag->value) ? (int) $staleFlag->value : 60;

        return [
            'lastSyncedAt' => $lastSyncedAt->toIso8601String(),
            'dataAgeSeconds' => $ageSeconds,
            'source' => 'BPDB',
            'readOnly' => true,
            'stale' => $ageSeconds > $staleThreshold,
            'staleThresholdSeconds' => $staleThreshold,
        ];
    }
}
