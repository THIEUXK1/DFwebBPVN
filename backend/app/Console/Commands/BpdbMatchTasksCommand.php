<?php
// backend/app/Console/Commands/BpdbMatchTasksCommand.php
//
// Chạy thủ công (chưa phải scheduler tự động) để thử khớp các dispatch mode=PROCESS
// với SUP_Tasks thật bên BPDB — Chế độ 1 (giữ nguyên vận hành thủ công) theo yêu cầu.

namespace App\Console\Commands;

use App\Models\MachineDispatch;
use App\Services\ColorService\BpdbTaskMatcherService;
use Illuminate\Console\Command;

class BpdbMatchTasksCommand extends Command
{
    protected $signature = 'colorservice:bpdb-match-tasks {--dispatch= : Chỉ chạy cho 1 dispatch_id cụ thể}';
    protected $description = 'Tìm và liên kết SUP_Tasks thật bên BPDB cho các dispatch mode=PROCESS (chỉ đọc)';

    public function handle(BpdbTaskMatcherService $matcher): int
    {
        $query = MachineDispatch::query()
            ->whereHas('routingDecision', fn ($q) => $q->where('mode', 'PROCESS'))
            ->with(['routingDecision', 'batch']);

        if ($dispatchId = $this->option('dispatch')) {
            $query->where('id', $dispatchId);
        }

        $dispatches = $query->get();

        if ($dispatches->isEmpty()) {
            $this->warn('Không có dispatch nào ở mode=PROCESS để thử khớp.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($dispatches as $dispatch) {
            $link = $matcher->matchDispatch($dispatch);
            $rows[] = [
                $dispatch->batch->color ?? '-',
                $dispatch->batch->product_code ?? '-',
                $link->jit_queue_code,
                $link->adapter_status,
                $link->bpdb_task_title ?? '-',
                $link->bpdb_task_status ?? '-',
            ];
        }

        $this->table(
            ['Color', 'Product Code', 'JIT Queue', 'Adapter Status', 'BPDB TaskTitle khớp', 'BPDB Status'],
            $rows
        );

        return self::SUCCESS;
    }
}
