<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bắn realtime qua Reverb khi trang test `/bpdb-machines/gantt-test` tự phát hiện 1 mẻ VỪA
 * chuyển sang "đang chạy" trên BPDB (client-side diff `newlyAppearedRunningIds` đã có sẵn
 * trong BpdbMachinesGantt.vue) — để `/machine-id-board` nhấp nháy đỏ đúng mã máy đó.
 *
 * Gantt BPDB (SQL Server ngoài, đọc qua BpdbMachineMonitoringService) và machine-id-board
 * (bảng nội bộ machine_dispatches/production_batches) là 2 nguồn dữ liệu tách biệt hoàn
 * toàn, không share DB được và không có job polling BPDB tự động — nên tín hiệu này do
 * chính trình duyệt đang mở Gantt kích hoạt qua endpoint public, không phải do backend tự
 * phát hiện thay đổi dữ liệu nghiệp vụ nội bộ (khác ProductionBatchUpdated).
 *
 * ShouldBroadcast (queued), KHÔNG ShouldBroadcastNow — cùng lý do đã ghi trong
 * RealtimeEventBroadcast.php (sự cố 2026-07-30: Reverb không tới được không được phép làm
 * hỏng luồng nghiệp vụ đang chạy trên trình duyệt gọi endpoint).
 */
class BpdbMachineRunningAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('bpdb-machine-alerts')];
    }

    public function broadcastAs(): string
    {
        return 'running';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
