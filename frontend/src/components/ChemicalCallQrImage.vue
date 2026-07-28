<template>
  <img :src="resolvedSrc" class="chem-call-qr-thumb" alt="QR Báo phát AC" />
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ src: string }>();

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
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 4px;
  background: #fff;
  object-fit: contain;
  cursor: zoom-in;
  transition: transform 0.15s ease;
}

.chem-call-qr-thumb:hover {
  position: relative;
  z-index: 20;
  transform: scale(3);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
}
</style>
