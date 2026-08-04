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

use App\Models\FeatureFlag;
use App\Services\ColorService\BpdbMachineMonitoringService;
use Illuminate\Http\Request;

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
