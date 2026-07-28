<template>
  <canvas ref="canvasEl" class="chem-call-qr-thumb" :class="{ 'chem-call-qr-thumb--large': size >= 100, 'is-loaded': ready }" :title="text"></canvas>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

const props = withDefaults(defineProps<{ text: string; size?: number }>(), { size: 40 });
const canvasEl = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);

function render() {
  if (canvasEl.value) {
    QRCode.toCanvas(canvasEl.value, props.text, { width: props.size, margin: 1 })
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
  flex-shrink: 0;
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
