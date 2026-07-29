<template>
  <img
    :src="resolvedSrc"
    class="chem-call-qr-thumb"
    :class="{ 'chem-call-qr-thumb--large': size >= 100, 'is-loaded': loaded }"
    :style="{ '--qr-size': size + 'px' }"
    alt="QR Báo phát AC"
    decoding="async"
    @load="loaded = true"
  />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ src: string; size?: number }>(), { size: 40 });
const loaded = ref(false);

// Đổi ảnh (vd đổi công thức trên thùng) thì fade lại từ đầu thay vì giữ ảnh cũ hiện lộ liễu.
watch(() => props.src, () => { loaded.value = false; });

// `src` từ backend (MachineChemicalChannel::qrImageUrl) là đường dẫn tương đối
// (vd "/chemical-qr/QR_VD006_AC77+AC78.jpg") phục vụ từ public/ của Laravel — backend
// chạy khác cổng với frontend (xem main.ts: axios.defaults.baseURL dùng cổng 8500),
// nên phải tự ghép domain backend thật, không để trình duyệt tự resolve theo origin
// của frontend (sẽ ra 404, ảnh trắng không hiện gì).
const resolvedSrc = computed(() => {
  if (!props.src) return props.src;
  if (/^https?:\/\//i.test(props.src)) return props.src;
  return `http://${window.location.hostname}:8500${props.src}`;
});
</script>

<style scoped>
.chem-call-qr-thumb {
  /* Kích thước theo --qr-size nhưng luôn co lại vừa khung chứa (max-width: 100%) khi
     khung bị thu hẹp (thu nhỏ trình duyệt/màn hình hẹp) — object-fit: contain đảm bảo
     ảnh không bao giờ bị cắt/vỡ, chỉ co giãn giữ nguyên tỉ lệ. */
  width: var(--qr-size);
  max-width: 100%;
  height: auto;
  aspect-ratio: 1 / 1;
  flex-shrink: 1;
  border-radius: 4px;
  background: #fff;
  object-fit: contain;
  cursor: zoom-in;
  opacity: 0;
  transition: transform 0.15s ease, opacity 0.2s ease;
}

/* Ảnh đã tải xong mới hiện — tránh "nháy" khung trắng/vỡ ảnh trong lúc tải. */
.chem-call-qr-thumb.is-loaded {
  opacity: 1;
}

.chem-call-qr-thumb:hover {
  position: relative;
  z-index: 20;
  transform: scale(3);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
}

/* QR đã hiện đủ to sẵn (vd trang /chemical-call/pending) — phóng thêm 3x sẽ tràn màn
   hình, chỉ cần phóng nhẹ để soi chi tiết. */
.chem-call-qr-thumb--large:hover {
  transform: scale(1.4);
}
</style>
