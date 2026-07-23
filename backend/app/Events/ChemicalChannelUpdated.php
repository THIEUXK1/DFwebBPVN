<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bắn realtime qua Reverb mỗi khi trạng thái 1 kênh gọi hóa chất đổi (Gọi/Hoàn thành/Hủy/
 * Reset) hoặc danh mục máy/kênh thay đổi (thêm máy, thêm kênh) — /chemical-call và
 * /chemical-call/monitor cùng lắng nghe kênh public "chemical-channels" để tự làm mới
 * NGAY LẬP TỨC thay vì đợi tới lượt polling tiếp theo (yêu cầu 2026-07-20: 2 trang phải
 * "tương thông", đổi ở trang này thấy ngay ở trang kia).
 */
class ChemicalChannelUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new Channel('chemical-channels')];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }
}
