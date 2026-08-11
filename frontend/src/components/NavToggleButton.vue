<template>
  <!-- Người ĐÃ đăng nhập: nút 3 gạch ẩn/hiện menu điều hướng (mặc định vào trang là đang hiện). -->
  <button
    v-if="isLoggedIn"
    class="nav-toggle-btn"
    :class="variant === 'vba' ? 'nav-vba' : 'nav-app'"
    :style="{ zIndex }"
    :title="isFullscreen ? $t('navToggleButton.showMenuTitle') : $t('navToggleButton.hideMenuTitle')"
    @click="isFullscreen = !isFullscreen"
  >
    <SvgIcon name="menu" size="16" />
  </button>

  <!-- Người xem công khai: không được bọc AppLayout nên không có menu để mở, cũng không có
       nút Đăng xuất/Hồ sơ ở topbar — trước bản này họ không có đường nào quay về trang đăng
       nhập ngoài việc tự gõ URL (báo lỗi 2026-08-04). Đưa kèm ?redirect= để đăng nhập xong
       quay lại đúng màn hình đang xem. -->
  <button
    v-else
    class="nav-toggle-btn"
    :class="variant === 'vba' ? 'nav-vba' : 'nav-app'"
    :style="{ zIndex }"
    :title="$t('navToggleButton.loginTitle')"
    @click="goToLogin"
  >
    <SvgIcon name="user" size="16" />
    <span class="nav-toggle-label">{{ $t('navToggleButton.loginLabel') }}</span>
  </button>
</template>

<script setup lang="ts">
import { isFullscreen } from '../services/layout';
import { useAuthStore } from '../stores/auth';
import { useRoute, useRouter } from 'vue-router';
import SvgIcon from './SvgIcon.vue';

/**
 * Nút "3 gạch" mở lại menu điều hướng trên các trang công khai (requiresAuth: false).
 *
 * Các trang đó tự bọc AppLayout khi người xem đã đăng nhập rồi ẩn sidebar+topbar bằng cờ
 * `isFullscreen` dùng chung (services/layout.ts) — giống hệt cách BpdbMachinesGantt làm từ
 * 2026-07-29. Nút này chỉ hạ cờ đó xuống để menu quen thuộc lộ ra, KHÔNG dựng lại cây
 * component: bật/tắt việc bọc AppLayout sau khi mount sẽ buộc Vue hủy+dựng lại toàn bộ nội
 * dung bên trong, làm mất DOM và state của trang (đúng lỗi đã gặp ở trang Gantt).
 *
 * Đặt NGAY TRÊN nút Toàn màn hình (cùng mép phải, cách đáy 60px) thay vì cạnh nhau: nhãn nút
 * Toàn màn hình đổi độ dài theo trạng thái nên xếp ngang sẽ chồng lên nhau.
 */
withDefaults(defineProps<{
  /** 'vba' cho các màn dựng lại UserForm (hệ màu Windows cổ điển), 'app' cho màn dùng design token. */
  variant?: 'app' | 'vba';
  /** Xem ghi chú cùng tên ở FullscreenButton.vue — mỗi trang một thang z-index riêng. */
  zIndex?: number;
}>(), { variant: 'app', zIndex: 900 });

// Đọc MỘT LẦN: phiên đăng nhập không đổi giữa chừng trong lúc đang xem một trang, mà để
// reactive thì nút xuất hiện/biến mất lệch nhịp với `pageWrapper` vốn đã cố định lúc mount.
const isLoggedIn = useAuthStore().isAuthenticated;

const router = useRouter();
const route = useRoute();

const goToLogin = () => {
  router.push({ path: '/login', query: { redirect: route.fullPath } });
};
</script>

<style scoped>
.nav-toggle-btn {
  position: fixed;
  right: 16px;
  bottom: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  cursor: pointer;
  opacity: 0.9;
}

/* Nút Đăng nhập có kèm chữ (khác nút 3 gạch chỉ có icon) — icon người dùng một mình dễ bị
   hiểu nhầm là "hồ sơ tài khoản" trong khi ở đây chưa hề đăng nhập. */
.nav-toggle-label {
  font-size: 0.85rem;
  font-weight: 600;
  white-space: nowrap;
}

.nav-toggle-btn:hover {
  opacity: 1;
}

/* Giữ đúng hệ màu Windows cổ điển của MSForms cho các màn dựng lại UserForm. */
.nav-vba {
  padding: 6px 10px;
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.45);
}

.nav-vba:active {
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 7px 9px 5px 11px;
}

.nav-app {
  padding: 8px 12px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  box-shadow: var(--shadow-lg);
  transition: opacity 0.2s ease;
}
</style>
