<template>
  <div v-if="show" class="checker-overlay" @click.self="$emit('close')">
    <div class="checker-modal card-sec">
      <div class="checker-header">
        <h3>🔍 Tra cứu bán thành phẩm đã cân</h3>
        <button class="btn btn-secondary btn-sm" @click="$emit('close')">✖ Đóng</button>
      </div>
      <div class="checker-form">
        <input v-model="checkerColor" type="text" class="form-control" placeholder="COLOR (mã màu)" @keyup.enter="runChecker" />
        <input v-model="checkerCode" type="text" class="form-control" placeholder="CODE (mã hàng)" @keyup.enter="runChecker" />
        <input v-model.number="checkerDaysBack" type="number" min="0" class="form-control checker-days-input" placeholder="Số ngày (để trống = tất cả)" @keyup.enter="runChecker" />
        <button class="btn btn-primary" @click="runChecker" :disabled="!checkerColor || !checkerCode || checkerLoading">
          {{ checkerLoading ? 'Đang tra...' : 'Kiểm tra' }}
        </button>
        <button class="btn btn-secondary" @click="clearChecker">Xóa</button>
      </div>

      <div class="checker-result mt-3">
        <p v-if="checkerMessage" class="text-muted font-sm">{{ checkerMessage }}</p>
        <div v-for="batch in checkerResults" :key="batch.batch_id" class="checker-batch-card">
          <div class="checker-batch-header">
            <span class="badge badge-blue">Mẻ: {{ batch.batch_id || 'N/A' }}</span>
            <span>{{ batch.color }} / {{ batch.product_code }}</span>
            <span class="text-muted font-xs">
              Máy: {{ batch.machine_code || 'N/A' }} · Mức: {{ batch.level_code || 'N/A' }} ·
              {{ batch.measured_at ? new Date(batch.measured_at).toLocaleString('vi-VN') : '' }}
            </span>
          </div>
          <table class="checker-table">
            <thead>
              <tr><th>Rack</th><th>Mã vật tư</th><th>Khối lượng</th><th>Process</th><th>Trạng thái</th></tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in batch.items" :key="idx">
                <td>{{ it.rack_code || '-' }}</td>
                <td>{{ it.dye_code || '-' }}</td>
                <td>{{ it.weight !== null ? it.weight.toFixed(2) + ' g' : '-' }}</td>
                <td>{{ it.process_code || '-' }}</td>
                <td>
                  <span v-if="it.process_status === 'REJECTED'" class="text-danger">❌ Không đạt</span>
                  <span v-else class="text-success">✔️ Đạt</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import axios from 'axios';

defineProps<{ show: boolean }>();
defineEmits<{ (e: 'close'): void }>();

// ===== Tra cứu bán thành phẩm — port VBA scaleform.btnCheck_Click → checkform =====
const checkerColor = ref('');
const checkerCode = ref('');
const checkerDaysBack = ref<number | null>(null);
const checkerLoading = ref(false);
const checkerResults = ref<any[]>([]);
const checkerMessage = ref('');

const runChecker = async () => {
  if (!checkerColor.value || !checkerCode.value) return;
  checkerLoading.value = true;
  checkerMessage.value = '';
  try {
    const params: Record<string, any> = {
      color: checkerColor.value.trim(),
      code: checkerCode.value.trim(),
    };
    if (checkerDaysBack.value !== null && checkerDaysBack.value !== undefined && `${checkerDaysBack.value}` !== '') {
      params.days_back = checkerDaysBack.value;
    }
    const res = await axios.get('/api/scale-measurements/checker', { params });
    checkerResults.value = res.data?.data || [];
    if (checkerResults.value.length === 0) {
      checkerMessage.value = res.data?.message || 'KHONG CO DU LIEU';
    }
  } catch (err: any) {
    checkerResults.value = [];
    checkerMessage.value = err.response?.data?.message || 'Không thể tra cứu dữ liệu.';
  } finally {
    checkerLoading.value = false;
  }
};

const clearChecker = () => {
  checkerColor.value = '';
  checkerCode.value = '';
  checkerDaysBack.value = null;
  checkerResults.value = [];
  checkerMessage.value = '';
};
</script>

<style scoped>
.checker-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 40px 16px;
  z-index: 1000;
}

.checker-modal {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  width: 100%;
  max-width: 860px;
  max-height: 85vh;
  overflow-y: auto;
}

.checker-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.checker-form {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.checker-form .form-control {
  flex: 1;
  min-width: 160px;
}

.checker-days-input {
  max-width: 220px;
}

.checker-batch-card {
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: 12px;
  margin-bottom: 12px;
  background-color: var(--bg-card);
}

.checker-batch-header {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 8px;
}

.checker-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 12px;
}

.checker-table th,
.checker-table td {
  border: 1px solid var(--border-divider);
  padding: 6px 8px;
  text-align: left;
}

.checker-table th {
  background-color: var(--bg-main);
  color: var(--text-muted);
  font-weight: 700;
}
</style>
