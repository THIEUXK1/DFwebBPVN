<template>
  <div v-if="thoai.hien" class="thoai-overlay" @keydown.esc="emit('dong', false)">
    <div class="thoai-box" role="alertdialog" aria-modal="true">
      <p v-for="(d, i) in thoai.dong" :key="i" class="thoai-dong" :class="{ trong: d === '' }">{{ d }}</p>
      <div class="thoai-nut">
        <!-- Nút AN TOÀN đứng trước và là nút được lấy tiêu điểm: hộp thoại này chỉ bật lên ở
             thao tác không hoàn tác được, lỡ gõ Enter phải rơi vào vế không làm gì cả. -->
        <button v-if="thoai.kieu === 'hoi'" ref="nutRef" class="vba-btn big" @click="emit('dong', false)">
          {{ $t('hopThoaiVba.btnNo') }}
        </button>
        <button v-else ref="nutRef" class="vba-btn big primary" @click="emit('dong', true)">{{ $t('hopThoaiVba.btnOk') }}</button>
        <button v-if="thoai.kieu === 'hoi'" class="vba-btn big danger" @click="emit('dong', true)">{{ $t('hopThoaiVba.btnYes') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import type { TrangThaiThoai } from '../composables/useHopThoai';

/**
 * Phần NHÌN THẤY của `useHopThoai` — hộp thoại thay `window.confirm`/`window.alert` để hai trạm
 * cân không bị Chrome đá khỏi chế độ toàn màn hình. Lý do đầy đủ nằm ở `composables/useHopThoai.ts`.
 *
 * Dùng chung `/weighing-station-v2` và `/weighing-station-large`. Style KHÔNG `scoped` cho phần
 * nút: hai màn đã có sẵn bộ nút `.vba-btn` riêng (một màn dựng theo toạ độ form VBA, một màn bố
 * cục thường) và hộp thoại phải hoà vào bộ nút của màn đang mở, không mang bảng nút thứ ba vào.
 */
defineProps<{ thoai: TrangThaiThoai }>();
const emit = defineEmits<{ (e: 'dong', ok: boolean): void }>();

const nutRef = ref<HTMLButtonElement | null>(null);

/**
 * Kéo tiêu điểm vào nút an toàn khi hộp thoại mở.
 *
 * Bắt buộc: ô COLOR trên cả hai màn tự giành lại tiêu điểm để đón máy quét — không kéo về đây thì
 * phím Enter (và cả một cú quét nhầm) rơi thẳng vào ô quét trong lúc hộp thoại đang chắn màn hình.
 */
watch(
  () => nutRef.value,
  (el) => { if (el) nextTick(() => el.focus()); }
);
</script>

<style scoped>
.thoai-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 20, 40, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  /* Cao hơn mọi lớp phủ của hai màn (hàng đợi dùng 50, hộp thoại VBA dùng 1000): nút BỎ MẺ nằm
     TRONG bảng hàng đợi và chính nó gọi hộp thoại này. */
  z-index: 1100;
}

.thoai-box {
  background: #fff;
  border-radius: 14px;
  padding: 26px 28px 22px;
  /* vw chia lại cho mức phóng của màn chứa (V2 có nút A−/A+); màn nào không phóng thì mặc định 1. */
  width: min(620px, calc(92vw / var(--df-zoom, 1)));
  box-shadow: 0 16px 50px rgba(10, 20, 40, 0.35);
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* 19px: đọc được từ chỗ đứng cân, không phải cúi sát màn hình. */
.thoai-dong {
  margin: 0;
  font-size: 19px;
  line-height: 1.45;
  font-weight: 600;
  color: #0d1520;
}

/* Dòng trống của chuỗi gốc (\n\n) giữ lại thành khoảng thở, thay vì bị gộp mất như thẻ <p> rỗng. */
.thoai-dong.trong { height: 12px; }

.thoai-nut {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 22px;
}

/*
 * Bộ nút DỰ PHÒNG. Hai màn cân đều có `.vba-btn` riêng nhưng style của chúng `scoped` nên không
 * với tới component này được; không có phần dưới thì hộp thoại ra hai nút trần của trình duyệt.
 */
.thoai-nut .vba-btn {
  border: 1px solid #c2cad8;
  border-radius: 8px;
  background: #f2f4f8;
  color: #0d1520;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
}

.thoai-nut .vba-btn.big {
  min-width: 132px;
  height: 52px;
  font-size: 17px;
  letter-spacing: 0.5px;
}

.thoai-nut .vba-btn:focus-visible {
  outline: none;
  border-color: #1f6bff;
  box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.25);
}

.thoai-nut .vba-btn.primary { background: #1f6bff; border-color: #1a5ae0; color: #fff; }
.thoai-nut .vba-btn.danger { background: #d43a2f; border-color: #b32b21; color: #fff; }
</style>
