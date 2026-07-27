<template>
  <canvas ref="canvasEl" class="chem-call-qr-thumb" :title="text"></canvas>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import QRCode from 'qrcode';

const props = defineProps<{ text: string }>();
const canvasEl = ref<HTMLCanvasElement | null>(null);

function render() {
  if (canvasEl.value) {
    QRCode.toCanvas(canvasEl.value, props.text, { width: 128, margin: 1 }).catch((err) => {
      console.error('Chemical call QR render failed', err);
    });
  }
}

onMounted(render);
watch(() => props.text, render);
</script>

<style scoped>
.chem-call-qr-thumb {
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: 4px;
  background: #fff;
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
