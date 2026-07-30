<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bắn realtime qua Reverb mỗi khi 1 lô sản xuất được tạo mới, đổi trạng thái, gán Thùng
 * trộn, hoặc được duyệt — mọi màn hình đang hiển thị danh sách lô (/production-batches,
 * /production-batches/list, dropdown giả lập quét ở /weighing-station) cùng lắng nghe
 * kênh public "production-batches" để tự làm mới NGAY LẬP TỨC thay vì đợi tới lượt
 * polling tiếp theo, theo đúng mẫu ChemicalChannelUpdated (yêu cầu 2026-07-23).
 *
 * ShouldBroadcast (queued), KHÔNG ShouldBroadcastNow — xem lý do chi tiết trong
 * RealtimeEventBroadcast.php (sự cố 2026-07-30: Reverb không tới được làm rollback
 * transaction nghiệp vụ đang lưu).
 */
class ProductionBatchUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new Channel('production-batches')];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }
}
