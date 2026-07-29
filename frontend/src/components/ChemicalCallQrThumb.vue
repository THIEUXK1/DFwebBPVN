<template>
  <canvas
    ref="canvasEl"
    class="chem-call-qr-thumb"
    :class="{ 'chem-call-qr-thumb--large': size >= 100, 'is-loaded': ready }"
    :style="{ '--qr-size': size + 'px' }"
    :title="text"
  ></canvas>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

const props = withDefaults(defineProps<{ text: string; size?: number }>(), { size: 40 });
const canvasEl = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);

function render() {
  if (canvasEl.value) {
    // Vẽ ảnh gốc ở độ phân giải cao hơn kích thước hiển thị (x2, theo devicePixelRatio)
    // rồi để CSS co giãn khung theo --qr-size — tránh vỡ nét/mờ khi ảnh bị phóng to lúc
    // hover hoặc khi khung chứa co giãn theo kích thước màn hình/trình duyệt.
    const bufferSize = Math.round(props.size * Math.max(window.devicePixelRatio || 1, 2));
    QRCode.toCanvas(canvasEl.value, props.text, { width: bufferSize, margin: 1 })
      .then(() => { ready.value = true; })
      .catch((err) => {
        console.error('Chemical call QR render failed', err);
      });
  }
}

onMounted(render);
watch(() => props.text, render);
watch(() => props.size, render);
</script>

<style scoped>
.chem-call-qr-thumb {
  /* Canvas được vẽ ở độ phân giải cao hơn (xem render()); hiển thị theo --qr-size nhưng
     luôn co lại vừa khung chứa (max-width: 100%) để không tràn/vỡ khi thu nhỏ trình
     duyệt hoặc màn hình hẹp. */
  width: var(--qr-size);
  max-width: 100%;
  height: auto;
  aspect-ratio: 1 / 1;
  flex-shrink: 1;
  border-radius: 4px;
  background: #fff;
  cursor: zoom-in;
  opacity: 0;
  transition: transform 0.15s ease, opacity 0.2s ease;
}

.chem-call-qr-thumb.is-loaded {
  opacity: 1;
}

.chem-call-qr-thumb:hover {
  position: relative;
  z-index: 20;
  transform: scale(3);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.35);
}

.chem-call-qr-thumb--large:hover {
  transform: scale(1.4);
}
</style>
