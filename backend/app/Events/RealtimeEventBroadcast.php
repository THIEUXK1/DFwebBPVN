<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thay thế cơ chế SSE cũ (/api/realtime/stream, vòng lặp while(true) trong 1 request HTTP)
 * — trên Windows php artisan serve không có concurrency thật (không có fork()), nên chỉ
 * cần 1 tab mở Dashboard là chiếm vĩnh viễn server, mọi request khác bị treo (phát hiện
 * 2026-07-25). Bắn qua Reverb (đã chạy sẵn, tự xử lý nhiều kết nối đồng thời đúng cách)
 * thay vì giữ 1 kết nối HTTP sống mãi.
 */
class RealtimeEventBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $data)
    {
    }

    public function broadcastOn(): array
    {
        return [new Channel('dashboard-events')];
    }

    public function broadcastAs(): string
    {
        return 'event';
    }

    public function broadcastWith(): array
    {
        return $this->data;
    }
}
