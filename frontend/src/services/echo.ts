import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Laravel Reverb (giao thức tương thích Pusher) — kết nối WebSocket để nhận cập nhật
// TỨC THÌ thay vì đợi tới lượt polling tiếp theo. Server Reverb chạy nền bằng
// `php artisan reverb:start` (mặc định cổng 8080), xem backend/.env REVERB_*.
// wsHost lấy theo đúng host mà trình duyệt đang dùng để mở trang (giống main.ts) để
// các máy trạm khác trong mạng nội bộ kết nối đúng máy chủ thay vì 'localhost'.
(window as any).Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'tywrk4gtzmorzuylobjg',
  wsHost: window.location.hostname,
  wsPort: 8080,
  wssPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
});

export default echo;
