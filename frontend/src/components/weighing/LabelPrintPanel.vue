<template>
  <div class="printed-label-box mt-4">
    <h3>🏷️ TEM QR VẬT TƯ SAU CÂN</h3>
    <p class="font-sm text-muted">Cân đã hoàn tất. Tem TSC đã được spool tự động gửi đến máy in.</p>

    <div class="label-preview-box mt-3">
      <LabelPreview v-if="labelPayload" :label-payload="labelPayload" />
      <div v-else class="label-preview-fallback">
        <div class="qr-placeholder">🔳</div>
        <div class="label-info">
          <div><strong>Lô: {{ activeBatch?.legacy_batch_id }}</strong></div>
          <div>Loại: {{ activeJob?.job_type }}</div>
          <div>Trọng lượng: {{ totalWeightKg.toFixed(2) }} kg</div>
          <div>Đích: Máy {{ activeBatch?.machine?.code }} | Thùng {{ activeBatch?.tank?.code || 'N/A' }}</div>
        </div>
      </div>
    </div>

    <div class="mt-4 flex-row gap-3">
      <button @click="$emit('reset-to-scan')" class="btn btn-secondary flex-1" :disabled="viewOnly">
        ⬅️ Tiếp tục quét mẻ mới
      </button>
      <button @click="$emit('reprint')" class="btn btn-primary flex-1" :disabled="viewOnly">
        🖨️ In lại tem (Reprint)
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import LabelPreview from '../LabelPreview.vue';

const props = defineProps<{
  activeJob: any | null;
  activeBatch: any | null;
  labelPayload: string | null;
  viewOnly: boolean;
}>();

defineEmits<{
  (e: 'reset-to-scan'): void;
  (e: 'reprint'): void;
}>();

const totalWeightKg = computed(() => {
  const items = props.activeJob?.items || [];
  const total = items.reduce((s: number, i: any) => s + (i.actual_weight || 0), 0);
  return total / 1000;
});
</script>

<style scoped>
.printed-label-box {
  background-color: var(--bg-sidebar);
  border: 2px dashed var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-xl);
  text-align: center;
}

.label-preview-box {
  background-color: #fff;
  color: #000;
  border-radius: var(--radius-md);
  overflow: hidden;
  text-align: left;
  border: 1px solid #ddd;
}

.label-preview-fallback {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
}

.qr-placeholder {
  font-size: 3rem;
}

.label-info {
  font-size: 12px;
  line-height: 1.4;
}
</style>
