<?php
// backend/app/Console/Commands/BpdbSyncCommand.php
//
// Lệnh đồng bộ đầy đủ 1 vòng Adapter (Chế độ 1 — giữ nguyên vận hành thủ công):
//   1. Với dispatch mode=PROCESS CHƯA khớp (hoặc đang AMBIGUOUS/AWAITING_SCAN) -> thử
//      tìm lại (có thể vừa được người vận hành quét xong ở trạm Color Service).
//   2. Với link ĐÃ khớp nhưng CHƯA ở trạng thái cuối (BPDB_COMPLETED/CANCELLED) -> đọc
//      lại BPDB để cập nhật tiến trình 10->20->30->40/99.
//
// CHỈ đọc BPDB (BpdbReadOnlyClient chặn cứng mọi câu không phải SELECT). Chạy thủ công
// qua lệnh này trong giai đoạn pilot; CHƯA đăng ký vào scheduler tự động — việc bật
// polling định kỳ thật (bao lâu 1 lần, có tránh giờ cao điểm sản xuất không) cần người
// phụ trách nhà máy xác nhận trước, theo đúng CLAUDE.md mục 3 (tránh tải lớn giờ sản
// xuất) — không tự ý bật.

namespace App\Console\Commands;

use App\Models\BpdbTaskLink;
use App\Models\MachineDispatch;
use App\Services\ColorService\BpdbTaskMatcherService;
use Illuminate\Console\Command;

class BpdbSyncCommand extends Command
{
    protected $signature = 'colorservice:bpdb-sync';
    protected $description = 'Đồng bộ 1 vòng: thử khớp task mới + cập nhật trạng thái task đã khớp (chỉ đọc BPDB)';

    private const TERMINAL_STATUSES = ['BPDB_COMPLETED', 'CANCELLED'];

    public function handle(BpdbTaskMatcherService $matcher): int
    {
        $matched = 0;
        $refreshed = 0;
        $changed = 0;

        // Bước 1: thử khớp lại các dispatch PROCESS chưa có kết quả cuối.
        $pending = MachineDispatch::query()
            ->whereHas('routingDecision', fn ($q) => $q->where('mode', 'PROCESS'))
            ->whereDoesntHave('bpdbTaskLink', fn ($q) => $q->whereIn('adapter_status', self::TERMINAL_STATUSES))
            ->with(['routingDecision', 'batch'])
            ->get();

        foreach ($pending as $dispatch) {
            $before = $dispatch->bpdbTaskLink?->adapter_status;
            $link = $matcher->matchDispatch($dispatch);
            $matched++;
            if ($before !== $link->adapter_status) {
                $changed++;
                $this->line("[{$dispatch->batch?->color}-{$dispatch->batch?->product_code}] "
                    . ($before ?? 'NEW') . ' -> ' . $link->adapter_status);
            }
        }

        // Bước 2: cập nhật lại trạng thái cho link ĐÃ có bpdb_task_id nhưng chưa terminal.
        $linked = BpdbTaskLink::query()
            ->whereNotNull('bpdb_task_id')
            ->whereNotIn('adapter_status', self::TERMINAL_STATUSES)
            ->get();

        foreach ($linked as $link) {
            $before = $link->adapter_status;
            $matcher->refreshLinkedTask($link);
            $refreshed++;
            if ($before !== $link->adapter_status) {
                $changed++;
                $this->line("[{$link->bpdb_task_title}] {$before} -> {$link->adapter_status}");
            }
        }

        $this->info("Xong: thử khớp {$matched} dispatch, cập nhật lại {$refreshed} link đã khớp, {$changed} thay đổi trạng thái.");

        return self::SUCCESS;
    }
}
