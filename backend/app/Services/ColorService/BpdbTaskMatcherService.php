<?php
// backend/app/Services/ColorService/BpdbTaskMatcherService.php
//
// Chế độ 1 (giữ nguyên vận hành thủ công) — không ghi vào BPDB, chỉ ĐỌC và tìm đúng
// SUP_Tasks tương ứng với 1 machine_dispatch đã in QR + đã được người vận hành quét tay
// tại trạm Color Service hiện hữu.
//
// Khóa đối chiếu: BPDB không có FK/ID liên kết — chỉ có SUP_Tasks.TaskTitle dạng text
// đúng định dạng VBA qrProcess (Mod_printslip.PrintSlip_70x100 dòng 1630):
//   "{color}-{code} {yyyymmddhhmm}"
// Xác nhận thật qua trace 2026-07-20 (xem colorservice-trace-report mục 09).
//
// CHỈ áp dụng cho dispatch có routing_decision.mode = 'PROCESS' (tuyến JIT) — theo VBA,
// chỉ nhánh QR PROCESS mới sinh QR theo định dạng này; EXTRA/FB không bao giờ xuất hiện
// trong BPDB nên không cần (và không nên) tìm.

namespace App\Services\ColorService;

use App\Models\BpdbTaskLink;
use App\Models\MachineDispatch;
use Illuminate\Support\Collection;

class BpdbTaskMatcherService
{
    /** Cửa sổ thời gian tìm kiếm — cân nhắc thời gian in tem + di chuyển + xếp hàng thật. */
    private const SEARCH_WINDOW_BEFORE_HOURS = 1;
    private const SEARCH_WINDOW_AFTER_DAYS = 14;

    public function __construct(private readonly BpdbReadOnlyClient $client)
    {
    }

    /**
     * Thử tìm & liên kết task BPDB cho 1 dispatch. Trả về BpdbTaskLink (đã lưu) với
     * adapter_status phản ánh kết quả: LINKED / AMBIGUOUS / AWAITING_SCAN / NOT_ELIGIBLE.
     */
    public function matchDispatch(MachineDispatch $dispatch): BpdbTaskLink
    {
        $link = BpdbTaskLink::firstOrNew(['dispatch_id' => $dispatch->id]);

        $decision = $dispatch->routingDecision;
        $batch = $dispatch->batch;

        if (!$decision || $decision->mode !== 'PROCESS' || !$batch) {
            $link->adapter_status = 'NOT_ELIGIBLE';
            $link->save();
            return $link;
        }

        $color = trim((string) $batch->color);
        $code = trim((string) $batch->product_code);
        if ($color === '' || $code === '') {
            $link->adapter_status = 'NOT_ELIGIBLE';
            $link->save();
            return $link;
        }

        $expectedPrefix = $color . '-' . $code . ' ';
        $anchor = $decision->decided_at ?? $dispatch->created_at;

        $candidates = $this->findCandidates($expectedPrefix, $anchor);

        $link->jit_routing_rule_id = null; // snapshot rule id không lưu trên dispatch hiện tại — điền ở bước sau nếu cần
        $link->jit_queue_code = $decision->area_label;
        $link->routing_system_id = $decision->input_snapshot['routing_system_id'] ?? null;
        $link->send_attempts = ($link->send_attempts ?? 0) + 1;
        $link->last_synced_at = now();

        if ($candidates->isEmpty()) {
            $link->adapter_status = 'AWAITING_SCAN';
        } elseif ($candidates->count() === 1) {
            $task = $candidates->first();
            $this->applyTaskToLink($link, $task, 'title_exact');
        } else {
            // Nhiều task cùng prefix trong cửa sổ thời gian — CÓ rủi ro nhầm lẫn thật
            // (đúng lo ngại của tài liệu gốc). Không tự chọn, để lại cho người xác nhận.
            $link->adapter_status = 'AMBIGUOUS';
            $link->bpdb_error_message = 'Tìm thấy ' . $candidates->count() . ' task BPDB cùng khớp — cần xác nhận thủ công: '
                . $candidates->pluck('TaskTitle')->implode(' | ');
        }

        $link->save();
        return $link;
    }

    /**
     * Cập nhật lại trạng thái mới nhất từ BPDB cho 1 link ĐÃ khớp (bpdb_task_id có giá
     * trị) — dùng cho polling định kỳ theo dõi tiến trình 10/20/30/40/99.
     */
    public function refreshLinkedTask(BpdbTaskLink $link): BpdbTaskLink
    {
        if (!$link->bpdb_task_id) {
            return $link;
        }

        $rows = $this->client->select(
            'SELECT TOP 1 Id, TaskStatus, WorkStartTime, FinishTime, ErrorMsg, IsDeleted
             FROM dbo.SUP_Tasks WHERE Id = ?',
            [$link->bpdb_task_id]
        );

        if (empty($rows)) {
            return $link;
        }

        $this->applyTaskToLink($link, $rows[0], $link->match_method ?? 'title_exact');
        $link->save();
        return $link;
    }

    private function applyTaskToLink(BpdbTaskLink $link, array $task, string $matchMethod): void
    {
        $link->bpdb_task_title = $task['TaskTitle'] ?? $link->bpdb_task_title;
        $link->bpdb_task_id = $task['Id'];
        $link->bpdb_task_status = (int) $task['TaskStatus'];
        $link->bpdb_is_deleted = (bool) ($task['IsDeleted'] ?? false);
        $link->bpdb_work_start_time = $task['WorkStartTime'] ?: null;
        $link->bpdb_finish_time = $task['FinishTime'] ?: null;
        $link->bpdb_error_message = $task['ErrorMsg'] ?: null;
        $link->match_method = $matchMethod;
        $link->adapter_status = $this->deriveAdapterStatus((int) $task['TaskStatus'], (bool) ($task['IsDeleted'] ?? false));
    }

    /** Mapping TaskStatus thật (xác nhận bằng bằng chứng, xem colorservice-trace-report mục 03) -> adapter_status nội bộ. */
    private function deriveAdapterStatus(int $taskStatus, bool $isDeleted): string
    {
        if ($isDeleted || $taskStatus === 99) {
            return 'CANCELLED';
        }
        return match ($taskStatus) {
            10 => 'BPDB_ACCEPTED',
            20 => 'BPDB_ASSIGNED',
            30 => 'BPDB_PROCESSING',
            40 => 'BPDB_COMPLETED',
            default => 'LINKED',
        };
    }

    private function findCandidates(string $expectedPrefix, $anchor): Collection
    {
        $from = (clone $anchor)->subHours(self::SEARCH_WINDOW_BEFORE_HOURS);
        $to = (clone $anchor)->addDays(self::SEARCH_WINDOW_AFTER_DAYS);

        $likePattern = $this->escapeLike($expectedPrefix) . '%';

        $rows = $this->client->select(
            "SELECT Id, TaskTitle, TaskStatus, CreateTime, WorkStartTime, FinishTime, ErrorMsg, IsDeleted, Machine, DisSystemName
             FROM dbo.SUP_Tasks
             WHERE TaskTitle LIKE ? ESCAPE '\\'
               AND CreateTime BETWEEN ? AND ?
             ORDER BY CreateTime ASC",
            [$likePattern, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]
        );

        // Lọc lại chính xác bằng PHP (case-insensitive) — LIKE của SQL Server có thể
        // khớp rộng hơn ý muốn tùy collation thật của cột.
        return collect($rows)->filter(
            fn ($row) => str_starts_with(mb_strtoupper($row['TaskTitle'] ?? ''), mb_strtoupper($expectedPrefix))
        )->values();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
