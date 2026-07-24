import { ref } from 'vue';

// Trạng thái Toàn màn hình (ẩn sidebar+topbar) dùng chung toàn app — cùng pattern với
// services/theme.ts. Các trang con (vd ChemicalCall.vue) đọc giá trị này để tự co giãn
// layout riêng (vd lưới máy dùng thêm cột khi có nhiều chỗ trống hơn lúc Toàn màn hình).
export const isFullscreen = ref(false);
