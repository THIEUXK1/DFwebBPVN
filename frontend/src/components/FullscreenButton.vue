<template>
  <!-- Nổi cố định ở góc phải dưới và LUÔN hiển thị, kể cả khi đang toàn màn hình: nếu ẩn đi thì
       thao tác viên không còn đường thoát nào ngoài phím ESC. -->
  <button
    class="fs-btn"
    :class="variant === 'vba' ? 'fs-vba' : 'fs-app'"
    :style="{ zIndex }"
    :title="fullscreenOn ? 'Thoát toàn màn hình (hoặc bấm ESC)' : 'Toàn màn hình — ẩn cả menu và thanh trình duyệt'"
    @click="toggleFullscreen"
  >
    {{ fullscreenOn ? '✕ Thoát toàn màn hình' : '⛶ Toàn màn hình' }}
  </button>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { isFullscreen } from '../services/layout';

/**
 * Nút thay phím F11 dùng chung cho các màn hình vận hành nhà xưởng.
 *
 * Bấm một nút ăn cả hai lớp che màn hình: Fullscreen API bỏ thanh trình duyệt (đúng phần F11 làm),
 * còn cờ `isFullscreen` dùng chung của AppLayout bỏ sidebar + thanh trên của app.
 */
withDefaults(defineProps<{
  /** 'vba' cho các màn dựng lại UserForm (bảng màu Windows cổ điển), 'app' cho màn dùng design token. */
  variant?: 'app' | 'vba';
  /**
   * Phải đặt THẤP HƠN mọi lớp phủ của trang chứa, không thì nút nổi đè lên hộp thoại đang mở.
   * Mỗi trang một thang z-index riêng (hàng đợi màn cân dùng 50-60, hộp thoại VBA dùng 1000)
   * nên không có con số mặc định nào đúng cho tất cả.
   */
  zIndex?: number;
}>(), { variant: 'app', zIndex: 900 });

const emit = defineEmits<{ (e: 'change', on: boolean): void }>();

const nativeFull = ref(false);
const fullscreenOn = computed(() => nativeFull.value || isFullscreen.value);

const toggleFullscreen = async () => {
  try {
    if (!document.fullscreenElement) {
      await document.documentElement.requestFullscreen();
      isFullscreen.value = true;
    } else {
      await document.exitFullscreen();
    }
  } catch {
    // Trình duyệt từ chối Fullscreen API (thiếu quyền, kiosk mode...) — vẫn phải ẩn được
    // sidebar/thanh trên, còn hơn không mở rộng được gì.
    isFullscreen.value = !isFullscreen.value;
  }
};

// Thoát bằng ESC hoặc F11 thật thì trình duyệt không báo cho nút biết — phải nghe sự kiện này,
// nếu không nhãn nút đứng nguyên ở "Thoát" và sidebar/thanh trên bị ẩn luôn.
const onFullscreenChange = () => {
  nativeFull.value = !!document.fullscreenElement;
  if (!nativeFull.value) isFullscreen.value = false;
};

// Nút "✕ Thoát toàn màn hình" của AppLayout chỉ hạ cờ dùng chung; đồng bộ ngược lại để không rơi vào
// cảnh menu đã hiện lại mà thanh trình duyệt vẫn mất.
watch(isFullscreen, (on) => {
  if (!on && document.fullscreenElement) document.exitFullscreen().catch(() => {});
});

// Các màn tự thu/phóng theo khung (PrintOrderEntry, WeighingStationLarge...) nghe sự kiện này để đo lại.
watch(fullscreenOn, (on) => emit('change', on));

onMounted(() => document.addEventListener('fullscreenchange', onFullscreenChange));

onUnmounted(() => {
  document.removeEventListener('fullscreenchange', onFullscreenChange);
  // Rời trang mà quên tắt thì màn hình kế tiếp mất cả menu lẫn thanh trình duyệt.
  if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
  isFullscreen.value = false;
});
</script>

<style scoped>
.fs-btn {
  position: fixed;
  right: 16px;
  bottom: 16px;
  white-space: nowrap;
  cursor: pointer;
  opacity: 0.9;
}

.fs-btn:hover {
  opacity: 1;
}

/* Màn dựng lại UserForm: giữ đúng hệ màu Windows cổ điển của MSForms, không dùng design token. */
.fs-vba {
  padding: 6px 12px;
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 2px 2px 6px rgba(0, 0, 0, 0.45);
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
}

.fs-vba:active {
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 7px 11px 5px 13px;
}

/* Màn dùng design token: cùng kiểu với nút thoát toàn màn hình của AppLayout. */
.fs-app {
  padding: 8px 14px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  font-size: 0.85rem;
  font-weight: 600;
  box-shadow: var(--shadow-lg);
  transition: opacity 0.2s ease;
}
</style>
