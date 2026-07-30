<template>
  <div v-if="show" class="restart-overlay" @click.self="onCancel">
    <div class="restart-modal card-sec">
      <div class="restart-header">
        <h3>⚠️ Cân lại từ đầu Mẻ nhuộm</h3>
        <button class="btn btn-secondary btn-sm" @click="onCancel">✖ Đóng</button>
      </div>

      <div class="restart-warning">
        <strong>Cảnh báo:</strong> Toàn bộ kết quả cân đã lưu của TẤT CẢ vật tư trong Mẻ nhuộm
        <strong>{{ batchLabel }}</strong> sẽ bị xóa và đặt lại về trạng thái CHƯA CÂN — quay lại
        đúng vật tư đầu tiên. Lịch sử từng lần cân vẫn được lưu vết để đối soát, nhưng kết quả
        đang hiển thị sẽ mất hết. Hành động này không thể hoàn tác qua giao diện.
      </div>

      <label class="restart-field-label" for="restart-reason">Lý do cân lại từ đầu (bắt buộc):</label>
      <textarea
        id="restart-reason"
        v-model="reason"
        class="form-input mt-2"
        rows="3"
        placeholder="Ví dụ: nhầm vật tư từ đầu, tràn/đổ vật tư ra ngoài, cân sai bì..."
      ></textarea>

      <div class="restart-confirm-checkbox mt-3">
        <input type="checkbox" id="restart-ack" v-model="acknowledged" />
        <label for="restart-ack">Tôi đã hiểu rõ hậu quả và muốn cân lại từ đầu Mẻ nhuộm này.</label>
      </div>

      <div class="restart-actions mt-4">
        <button class="btn btn-secondary" @click="onCancel" :disabled="submitting">Hủy</button>
        <button
          class="btn btn-danger"
          @click="onConfirm"
          :disabled="!canConfirm || submitting"
        >
          {{ submitting ? 'Đang xử lý...' : '🔁 Xác nhận cân lại từ đầu' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';

const props = defineProps<{
  show: boolean;
  batchLabel: string;
  submitting: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'confirm', reason: string): void;
}>();

const reason = ref('');
const acknowledged = ref(false);

// Reset form mỗi lần mở lại modal — tránh giữ lý do/tick cũ của lần trước.
watch(() => props.show, (val) => {
  if (val) {
    reason.value = '';
    acknowledged.value = false;
  }
});

const canConfirm = computed(() => acknowledged.value && reason.value.trim().length >= 5);

function onCancel() {
  if (props.submitting) return;
  emit('close');
}

function onConfirm() {
  if (!canConfirm.value || props.submitting) return;
  emit('confirm', reason.value.trim());
}
</script>

<style scoped>
.restart-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 60px 16px;
  z-index: 1100;
}

.restart-modal {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--status-red);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  width: 100%;
  max-width: 560px;
}

.restart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.restart-warning {
  background-color: rgba(231, 76, 60, 0.08);
  border: 1px dashed var(--status-red);
  border-radius: var(--radius-md);
  padding: 12px;
  font-size: 13px;
  line-height: 1.5;
}

.restart-field-label {
  display: block;
  margin-top: 14px;
  font-size: 12px;
  font-weight: 700;
  color: var(--text-muted);
}

.restart-confirm-checkbox {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
}

.restart-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn-danger {
  background-color: var(--status-red);
  border: 1px solid var(--status-red);
  color: #fff;
}

.btn-danger:disabled {
  background-color: var(--border-divider);
  border-color: var(--border-divider);
  color: var(--text-muted);
  cursor: not-allowed;
}
</style>
