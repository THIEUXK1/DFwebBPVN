import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Laravel Reverb (giao thức tương thích Pusher) — kết nối WebSocket để nhận cập nhật
// TỨC THÌ thay vì đợi tới lượt polling tiếp theo. Server Reverb chạy nền bằng
// `php artisan reverb:start` (mặc định cổng 8080), xem backend/.env REVERB_*.
// Giá trị hardcode theo đúng cách main.ts đang hardcode axios.defaults.baseURL cho
// môi trường dev/local này (xem ghi chú ở đó).
(window as any).Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'tywrk4gtzmorzuylobjg',
  wsHost: 'localhost',
  wsPort: 8080,
  wssPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
});

export default echo;
