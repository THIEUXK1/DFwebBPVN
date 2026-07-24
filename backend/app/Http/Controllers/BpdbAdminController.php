<?php
// backend/app/Http/Controllers/BpdbAdminController.php
//
// Admin Dashboard cho phần BPDB/JIT (mục 12, "LỆNH TRIỂN KHAI KIẾN TRÚC WEB → BPDB/JIT").
// CHỈ hiển thị số liệu tính được từ dữ liệu THẬT đang có (app.bpdb_task_links,
// app.jit_routing_rules) — không suy đoán/bịa số liệu cho phần chưa có dữ liệu nguồn
// (vd "thời gian từ cân xong tới BPDB tạo task" cần liên kết weighing_jobs<->bpdb_task_links
// CHƯA tồn tại; "Windows Service status" không có cách đọc được từ đây).

namespace App\Http\Controllers;

use App\Models\BpdbTaskLink;
use App\Models\JitRoutingRule;
use App\Services\ColorService\BpdbReadOnlyClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BpdbAdminController extends Controller
{
    public function overview(Request $request, BpdbReadOnlyClient $client)
    {
        $health = $client->healthCheck();

        $statusCounts = BpdbTaskLink::query()
            ->select('adapter_status', DB::raw('count(*) as cnt'))
            ->groupBy('adapter_status')
            ->pluck('cnt', 'adapter_status');

        $total = (int) $statusCounts->sum();
        $completed = (int) ($statusCounts['BPDB_COMPLETED'] ?? 0);
        $cancelled = (int) ($statusCounts['CANCELLED'] ?? 0);

        // Đơn "bị kẹt" — đã gán trạng thái trung gian (10/20/30 tương ứng
        // BPDB_ACCEPTED/BPDB_ASSIGNED/BPDB_PROCESSING) nhưng last_synced_at đã quá lâu,
        // hoặc AWAITING_SCAN/AMBIGUOUS tồn tại lâu không tiến triển.
        $stuckThresholdHours = 6;
        $stuck = BpdbTaskLink::query()
            ->whereIn('adapter_status', ['BPDB_ACCEPTED', 'BPDB_ASSIGNED', 'BPDB_PROCESSING', 'AWAITING_SCAN', 'AMBIGUOUS'])
            ->where('created_at', '<', now()->subHours($stuckThresholdHours))
            ->orderBy('created_at')
            ->with(['dispatch.batch.machine', 'dispatch.batch.tank'])
            ->limit(50)
            ->get()
            ->map(fn ($link) => $this->serializeLink($link));

        $ambiguous = BpdbTaskLink::query()
            ->where('adapter_status', 'AMBIGUOUS')
            ->with(['dispatch.batch.machine', 'dispatch.batch.tank'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($link) => $this->serializeLink($link));

        // Thời gian BPDB xử lý thật (WorkStart -> Finish) — chỉ tính trên các task đã
        // hoàn thành và có đủ 2 mốc thời gian.
        $processingSeconds = BpdbTaskLink::query()
            ->whereNotNull('bpdb_work_start_time')
            ->whereNotNull('bpdb_finish_time')
            ->get()
            // absolute=true — Carbon 3.x trả về giá trị CÓ DẤU theo mặc định (finish sau
            // work_start nên không có "true" sẽ ra số ÂM); xem ghi chú tương tự trong
            // BpdbMachineMonitoringService::detectStuckWarning.
            ->map(fn ($l) => $l->bpdb_finish_time->diffInSeconds($l->bpdb_work_start_time, true));

        $avgProcessingSeconds = $processingSeconds->isNotEmpty()
            ? (int) round($processingSeconds->avg())
            : null;

        $recentErrors = BpdbTaskLink::query()
            ->whereNotNull('bpdb_error_message')
            ->orWhere('adapter_status', 'AMBIGUOUS')
            ->orderByDesc('updated_at')
            ->with(['dispatch.batch.machine', 'dispatch.batch.tank'])
            ->limit(30)
            ->get()
            ->map(fn ($link) => $this->serializeLink($link));

        return response()->json([
            'bpdb_connection' => $health,
            'summary' => [
                'total_links' => $total,
                'completed' => $completed,
                'cancelled' => $cancelled,
                'completion_rate' => $total > 0 ? round($completed / $total * 100, 1) : null,
                'cancellation_rate' => $total > 0 ? round($cancelled / $total * 100, 1) : null,
                'by_adapter_status' => $statusCounts,
                'avg_processing_seconds' => $avgProcessingSeconds,
            ],
            'stuck_threshold_hours' => $stuckThresholdHours,
            'stuck_links' => $stuck,
            'ambiguous_links' => $ambiguous,
            'recent_errors' => $recentErrors,
            'not_available' => [
                // Minh bạch những gì tài liệu yêu cầu nhưng CHƯA có dữ liệu nguồn để tính —
                // không hiển thị số giả.
                'windows_service_status' => 'Không có cách đọc trạng thái Windows Service Color Service từ Adapter (chỉ đọc SQL Server qua ODBC).',
                'weighing_to_bpdb_latency' => 'Chưa có liên kết dữ liệu giữa weighing_jobs và bpdb_task_links — cần thiết kế thêm trước khi tính được.',
            ],
        ]);
    }

    public function taskLinks(Request $request)
    {
        $query = BpdbTaskLink::query()->with(['dispatch.batch.machine', 'dispatch.batch.tank']);

        if ($status = $request->query('adapter_status')) {
            $query->where('adapter_status', $status);
        }

        $links = $query->orderByDesc('created_at')->limit(200)->get();

        return response()->json([
            'data' => $links->map(fn ($link) => $this->serializeLink($link)),
        ]);
    }

    public function jitRoutingRules()
    {
        $rules = JitRoutingRule::query()
            ->orderBy('machine_code')
            ->orderBy('tank_code')
            ->orderBy('priority')
            ->get();

        return response()->json(['data' => $rules]);
    }

    private function serializeLink(BpdbTaskLink $link): array
    {
        $batch = $link->dispatch?->batch;
        return [
            'id' => $link->id,
            'color' => $batch?->color,
            'product_code' => $batch?->product_code,
            'machine_code' => $batch?->machine?->code,
            'tank_code' => $batch?->tank?->code,
            'jit_queue_code' => $link->jit_queue_code,
            'adapter_status' => $link->adapter_status,
            'bpdb_task_title' => $link->bpdb_task_title,
            'bpdb_task_status' => $link->bpdb_task_status,
            'bpdb_error_message' => $link->bpdb_error_message,
            'send_attempts' => $link->send_attempts,
            'created_at' => optional($link->created_at)->toIso8601String(),
            'last_synced_at' => optional($link->last_synced_at)->toIso8601String(),
        ];
    }
}
