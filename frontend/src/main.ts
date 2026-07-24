import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import './style.css'; // Global CSS theme styles
import axios from 'axios';
import { useAuthStore } from './stores/auth';
import { initTheme } from './services/theme';

// Áp dụng theme (sáng/tối) đã lưu ngay từ đầu, trước khi app mount, để tránh
// nháy giao diện tối mặc định rồi mới đổi sang sáng sau khi Vue chạy xong.
initTheme();

// Centralized API backend baseURL — dùng đúng host mà trình duyệt đang dùng để mở
// trang (LAN IP của máy chủ, không phải 'localhost') để các máy trạm khác trong
// mạng nội bộ gọi API đúng máy chủ thay vì gọi vào chính máy trạm đó.
axios.defaults.baseURL = `http://${window.location.hostname}:8500`;

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Phiên (đăng nhập hoặc kiosk) hết hạn/không hợp lệ -> trước đây chỉ console.error im
// lặng, người dùng thấy màn hình trống không rõ lý do (đúng triệu chứng báo lỗi 401
// hàng loạt 2026-07-18). Tự dọn state hỏng + tải lại trang: AppLayout/router guard sẽ
// tự đưa về đúng màn hình (đăng nhập lại nếu tài khoản thường, hoặc "chưa xác định
// trạm" nếu phiên kiosk) thay vì để danh sách trống lặng lẽ.
axios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      const authStore = useAuthStore();
      if (authStore.isAuthenticated) {
        authStore.logout();
        window.location.reload();
      }
    }
    return Promise.reject(error);
  }
);

app.mount('#app');
