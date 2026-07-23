<?php
// backend/app/Console/Commands/BpdbHealthCheckCommand.php
//
// Lệnh kiểm tra thủ công: xác nhận Adapter kết nối được BPDB (chỉ đọc) và hiển thị vài
// task gần nhất — dùng để test nhanh trong lúc phát triển, không phải lệnh vận hành.

namespace App\Console\Commands;

use App\Services\ColorService\BpdbReadOnlyClient;
use Illuminate\Console\Command;

class BpdbHealthCheckCommand extends Command
{
    protected $signature = 'colorservice:bpdb-health';
    protected $description = 'Kiểm tra kết nối BPDB (chỉ đọc) và hiển thị vài task gần nhất';

    public function handle(BpdbReadOnlyClient $client): int
    {
        $health = $client->healthCheck();

        if (!$health['connected']) {
            $this->error('Không kết nối được BPDB: ' . $health['error']);
            return self::FAILURE;
        }

        $this->info("Kết nối BPDB OK ({$health['latency_ms']}ms)");

        $tasks = $client->select(
            'SELECT TOP 5 TaskTitle, TaskStatus, CreateTime, WorkStartTime, FinishTime, DisSystemName
             FROM dbo.SUP_Tasks
             ORDER BY CreateTime DESC'
        );

        $this->table(
            ['TaskTitle', 'Status', 'CreateTime', 'WorkStartTime', 'FinishTime', 'System'],
            array_map(fn ($t) => [
                $t['TaskTitle'],
                $t['TaskStatus'],
                $t['CreateTime'],
                $t['WorkStartTime'],
                $t['FinishTime'],
                $t['DisSystemName'],
            ], $tasks)
        );

        return self::SUCCESS;
    }
}
