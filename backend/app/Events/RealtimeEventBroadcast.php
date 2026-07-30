<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Thay thế cơ chế SSE cũ (/api/realtime/stream, vòng lặp while(true) trong 1 request HTTP)
 * — trên Windows php artisan serve không có concurrency thật (không có fork()), nên chỉ
 * cần 1 tab mở Dashboard là chiếm vĩnh viễn server, mọi request khác bị treo (phát hiện
 * 2026-07-25). Bắn qua Reverb (đã chạy sẵn, tự xử lý nhiều kết nối đồng thời đúng cách)
 * thay vì giữ 1 kết nối HTTP sống mãi.
 *
 * ShouldBroadcast (queued qua QUEUE_CONNECTION=database), KHÔNG phải ShouldBroadcastNow —
 * bắn realtime là phụ trợ (Dashboard tự làm mới nhanh hơn), nghiệp vụ chính vẫn chạy đúng
 * qua polling dự phòng (ADR-010) nếu Reverb không chạy/không tới được. ShouldBroadcastNow
 * từng làm cURL lỗi kết nối Reverb (vd Reverb chưa bật ở máy dev) ném exception NGAY TRONG
 * transaction DB đang lưu dữ liệu nghiệp vụ (vd xác nhận cân), khiến cả thao tác lưu bị
 * rollback theo dù bản thân việc lưu không hề có lỗi (sự cố 2026-07-30).
 */
class RealtimeEventBroadcast implements ShouldBroadcast
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
