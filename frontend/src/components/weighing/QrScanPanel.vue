<template>
  <div class="scanning-wait-screen card-sec text-center">
    <!-- Scale warning + tự cấu hình tại chỗ -->
    <div v-if="!currentWorkstation?.assigned_scale_device_id" class="card error-card mb-4" style="color:var(--status-red); border-color:var(--status-red-border); background:var(--status-red-bg); padding:12px; border-radius:8px; text-align:left;">
      ⚠️ Trạm chưa gán thiết bị Cân.
      <button @click="showScaleConfig = !showScaleConfig" class="btn btn-secondary btn-sm ml-2">⚙️ Cấu hình cân ngay</button>
      <div v-if="showScaleConfig" class="mt-3 device-config-form">
        <input v-model="scaleConfigForm.deviceId" type="text" class="form-control mb-2" placeholder="Mã cân, vd: SCALE_SMALL_01" />
        <input v-model="scaleConfigForm.comPort" type="text" class="form-control mb-2" placeholder="Cổng COM, vd: COM3 (tùy chọn)" />
        <button @click="saveScaleConfig" class="btn btn-primary btn-sm" :disabled="!scaleConfigForm.deviceId || savingScaleConfig">
          {{ savingScaleConfig ? 'Đang lưu...' : 'Lưu cấu hình cân' }}
        </button>
      </div>
    </div>
    <!-- Đã BỎ khối chọn/gán máy in (2026-07-31). Trạm cân in tem và phiếu cân hoàn toàn qua
         hộp thoại in của trình duyệt (printTsplViaBrowser) — không lệnh in nào gửi
         printer_address/printer_type xuống Local Agent nữa, nên máy in đã gán chỉ còn là
         thông tin thừa gây hiểu nhầm là phải cấu hình mới in được. Cùng hướng với
         /print-station (đã bỏ in qua Agent từ 2026-07-30). -->

    <div class="scanner-anim-icon">🔳</div>
    <h3>VUI LÒNG QUÉT MÃ QR ĐƠN CÔNG THỨC ĐỂ BẮT ĐẦU</h3>
    <p class="text-muted">Hệ thống sẽ tự động đối chiếu, nạp danh sách nguyên liệu và thiết lập dung sai cho trạm.</p>

    <!-- Manual QR entry — fallback khi máy quét vật lý lỗi/không kết nối. Nhập/dán
         đúng chuỗi QR thật do QR_LABEL_PRINTING in ra (bắt đầu bằng "#"). -->
    <div class="mock-scanner-widget mt-5">
      <h4>⌨️ Nhập tay mã QR (khi máy quét lỗi)</h4>
      <div class="mock-input-row">
        <input
          v-model="manualQrInput"
          type="text"
          class="form-control"
          placeholder="Dán hoặc gõ chuỗi QR thật, vd: #RED-P123-VD10-220-..."
          @keyup.enter="submitManualQr"
        />
        <button
          @click="submitManualQr"
          class="btn btn-primary"
          :disabled="!manualQrInput || viewOnly"
        >
          Nạp đơn
        </button>
      </div>
    </div>

    <!-- Đơn đang cân dở của đúng trạm này — dự phòng thủ công cho restoreActiveJob() tự
         động ở WeighingStation.vue (onMounted): nếu vì lý do gì đó job không tự nạp lại
         được, thao tác viên vẫn bấm tay vào đây để tiếp tục, không cần quét lại QR và
         không mất các vật tư đã cân xong. Mỗi trạm chỉ dùng cho đúng 1 máy nên tối đa có
         1 đơn đang dở tại 1 thời điểm (yêu cầu 2026-07-30). -->
    <div v-if="pendingJob" class="mock-scanner-widget mt-5">
      <h4>📌 Đơn đang cân dở tại trạm này</h4>
      <div class="pending-job-card" @click="resumePendingJob">
        <div class="pending-job-info">
          <strong>{{ pendingBatch?.legacy_batch_id }}</strong>
          <span class="text-muted font-sm">
            {{ pendingBatch?.color }} · {{ pendingBatch?.product_code }} — đã cân {{ pendingCompletedCount }}/{{ pendingJob.items?.length || 0 }} vật tư
          </span>
        </div>
        <button class="btn btn-primary btn-sm">▶️ Tiếp tục cân</button>
      </div>
    </div>

    <!-- Mock Scanner Tool for Testing -->
    <div class="mock-scanner-widget mt-3">
      <h4>📋 Công cụ Giả lập Quét mã (Dành cho kiểm thử)</h4>
      <div class="mock-input-row">
        <select v-model="mockSelectedBatchId" class="form-select mock-select">
          <option value="">-- Chọn mẻ từ CSDL để quét giả lập --</option>
          <option v-for="b in databaseBatches" :key="b.id" :value="b.id">
            Mẻ: {{ b.legacy_batch_id }} | Màu: {{ b.color }} | Vải: {{ b.cloth_weight }} kg
          </option>
        </select>
        <button
          @click="triggerMockScan"
          class="btn btn-secondary"
          :disabled="!mockSelectedBatchId || viewOnly"
        >
          Simulate QR Scan
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { currentWorkstation } from '../../services/workstation';
import { scannerService } from '../../services/scanner';
import echo from '../../services/echo';

defineProps<{ viewOnly: boolean }>();
const emit = defineEmits<{
  (e: 'manual-qr-submit', token: string): void;
  (e: 'resume-job', payload: { job: any; batch: any }): void;
}>();

// Đơn đang cân dở của đúng trạm này — xem comment ở template. Dùng lại đúng API
// WeighingJobController::activeForWorkstation (đã có sẵn cho restoreActiveJob() tự động
// bên WeighingStation.vue), chỉ khác là hiển thị thành thẻ bấm tay thay vì tự nạp ngầm.
const pendingJob = ref<any | null>(null);
const pendingBatch = ref<any | null>(null);
const pendingCompletedCount = computed(() => {
  if (!pendingJob.value?.items) return 0;
  return pendingJob.value.items.filter((i: any) => i.status === 'COMPLETED').length;
});

const fetchPendingJob = async () => {
  if (!currentWorkstation.value?.id) {
    pendingJob.value = null;
    pendingBatch.value = null;
    return;
  }
  try {
    const res = await axios.get('/api/weighing-jobs/active', {
      params: { workstation_id: currentWorkstation.value.id },
    });
    pendingJob.value = res.data?.data?.job || null;
    pendingBatch.value = res.data?.data?.batch || null;
  } catch (err) {
    console.error('Failed to load pending weighing job:', err);
  }
};

const resumePendingJob = () => {
  if (!pendingJob.value) return;
  emit('resume-job', { job: pendingJob.value, batch: pendingBatch.value });
};

// Tự cấu hình cân ngay tại trạm — không qua Admin (đơn giản hóa 2026-07-18).
// Phần cấu hình MÁY IN đã bỏ (2026-07-31): trạm cân in qua trình duyệt, không còn gửi lệnh
// in xuống Local Agent nên không cần chọn/gán máy in nữa.
const showScaleConfig = ref(false);
const savingScaleConfig = ref(false);
const scaleConfigForm = reactive({ deviceId: '', comPort: '' });

const saveScaleConfig = async () => {
  if (!currentWorkstation.value || !scaleConfigForm.deviceId) return;
  savingScaleConfig.value = true;
  try {
    await axios.put(`/api/workstations/${currentWorkstation.value.id}/local-device-config`, {
      scale_device_id: scaleConfigForm.deviceId,
      scale_com_port: scaleConfigForm.comPort || undefined,
    });
    currentWorkstation.value.assigned_scale_device_id = scaleConfigForm.deviceId;
    showScaleConfig.value = false;
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể lưu cấu hình cân.');
  } finally {
    savingScaleConfig.value = false;
  }
};

const manualQrInput = ref<string>('');
const submitManualQr = () => {
  const token = manualQrInput.value.trim();
  if (!token) return;
  emit('manual-qr-submit', token);
  manualQrInput.value = '';
};

const databaseBatches = ref<any[]>([]);
const mockSelectedBatchId = ref<string>('');

const fetchWaitingBatches = async () => {
  try {
    const res = await axios.get('/api/production-batches');
    databaseBatches.value = res.data.data || res.data || [];
  } catch (err) {
    console.error('Failed to load batches:', err);
  }
};

const triggerMockScan = () => {
  if (!mockSelectedBatchId.value) return;
  const token = `DF:ORDER:${mockSelectedBatchId.value}`;
  // Đi qua scannerService (không emit trực tiếp) vì WeighingStation.vue đã đăng ký
  // handleBarcodeScan làm callback của scannerService — giữ đúng 1 đường xử lý duy
  // nhất cho quét thật/quét giả lập, chỉ nhập tay (manual-qr-submit) mới đi tắt.
  scannerService.simulateScan(token);
};

let batchPollInterval: any = null;

defineExpose({ fetchWaitingBatches });

onMounted(() => {
  fetchWaitingBatches();
  fetchPendingJob();
  // Realtime qua Reverb — lô mới được tạo/duyệt ở /production-batches phải xuất hiện
  // ngay trong dropdown "Giả lập Quét mã" ở đây, không cần rời màn hình rồi quay lại.
  echo.channel('production-batches').listen('.updated', fetchWaitingBatches);
  batchPollInterval = setInterval(() => {
    fetchWaitingBatches();
    fetchPendingJob();
  }, 15000);
});

onUnmounted(() => {
  if (batchPollInterval) clearInterval(batchPollInterval);
  echo.leaveChannel('production-batches');
});
</script>

<style scoped>
.scanning-wait-screen {
  padding: 60px 40px;
}

.scanner-anim-icon {
  font-size: 4rem;
  margin-bottom: 20px;
  animation: pulseScan 2s infinite ease-in-out;
}

@keyframes pulseScan {
  0% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); opacity: 0.7; }
}

.mock-scanner-widget {
  max-width: 500px;
  margin: 0 auto;
  border-top: 1px solid var(--border-divider);
  padding-top: 24px;
}

.mock-input-row {
  display: flex;
  gap: 12px;
  margin-top: 12px;
}

.mock-select {
  flex: 2;
}

.pending-job-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-top: 12px;
  padding: 14px 18px;
  border: 1px solid var(--primary);
  border-radius: var(--radius-md, 8px);
  background: var(--bg-card);
  cursor: pointer;
  transition: background 0.2s ease;
  text-align: left;
}

.pending-job-card:hover {
  background: var(--bg-card-hover, var(--bg-tag));
}

.pending-job-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
</style>
