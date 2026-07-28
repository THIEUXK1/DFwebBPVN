<template>
  <img :src="src" class="chem-call-qr-thumb" alt="QR Báo phát AC" />
</template>

<script setup lang="ts">
defineProps<{ src: string }>();
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
