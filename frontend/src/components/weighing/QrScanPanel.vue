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
    <!-- Máy in — luôn cho đổi (không chỉ khi thiếu), đọc đúng danh sách Agent đã phát
         hiện thay vì bắt gõ tay tên máy in (dễ gõ sai, in lỗi âm thầm) — giống hệt
         "⚙️ Đổi máy in" ở /print-station. -->
    <div class="card mb-4" :class="currentWorkstation?.assigned_printer_device_id ? '' : 'warning-card'" style="padding:12px; border-radius:8px; text-align:left;" :style="currentWorkstation?.assigned_printer_device_id ? 'border:1px solid var(--border-divider);' : 'color:#eab308; border-color:#eab308; background:rgba(234,179,8,0.05); border:1px solid rgba(234,179,8,0.2);'">
      <template v-if="currentWorkstation?.assigned_printer_device_id">
        🖨️ Máy in hiện tại: <strong>{{ currentWorkstation.assigned_printer_device_id }}</strong>
      </template>
      <template v-else>
        ⚠️ Trạm chưa gán máy in chính.
      </template>
      <button @click="fetchInstalledPrinters" class="btn btn-secondary btn-sm ml-2" :disabled="loadingInstalledPrinters">🔄 Làm mới</button>
      <button @click="showPrinterConfig = !showPrinterConfig" class="btn btn-secondary btn-sm ml-2">⚙️ Đổi máy in</button>
      <div v-if="showPrinterConfig" class="mt-3 device-config-form">
        <div v-if="loadingInstalledPrinters" class="text-muted font-sm mb-2">Đang tải danh sách máy in đã cài trên máy này...</div>
        <select v-else-if="installedPrinters.length" v-model="printerConfigForm.deviceId" class="form-select mb-2">
          <option value="">-- Chọn máy in --</option>
          <option v-for="p in installedPrinters" :key="p" :value="p">
            {{ p }}{{ p === defaultInstalledPrinter ? ' (mặc định hệ thống)' : '' }}
          </option>
        </select>
        <div v-else class="text-muted font-sm mb-2">
          Không phát hiện máy in nào từ Local Agent. Kiểm tra Agent (DF Agent) có đang chạy trên máy này không, hoặc nhập tay mã máy in bên dưới.
          <input v-model="printerConfigForm.deviceId" type="text" class="form-control mt-2" placeholder="Mã máy in, vd: TSC TE200" />
        </div>
        <select v-model="printerConfigForm.connectionType" class="form-select mb-2">
          <option value="USB">USB</option>
          <option value="LAN">LAN</option>
        </select>
        <input v-model="printerConfigForm.address" type="text" class="form-control mb-2" placeholder="Địa chỉ IP (nếu LAN) hoặc để trống nếu USB" />
        <button @click="savePrinterConfig" class="btn btn-primary btn-sm" :disabled="!printerConfigForm.deviceId || savingPrinterConfig">
          {{ savingPrinterConfig ? 'Đang lưu...' : 'Lưu cấu hình máy in' }}
        </button>
      </div>
    </div>

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

// Tự cấu hình cân/máy in ngay tại trạm — không qua Admin (đơn giản hóa 2026-07-18)
const showScaleConfig = ref(false);
const savingScaleConfig = ref(false);
const scaleConfigForm = reactive({ deviceId: '', comPort: '' });

const showPrinterConfig = ref(false);
const savingPrinterConfig = ref(false);
const printerConfigForm = reactive({ deviceId: '', connectionType: 'USB', address: '' });

// Danh sách máy in thật do Local Agent phát hiện trên máy này (PrinterDiscovery.cs qua
// POST /agents/{id}/printers, ghi vào operation_clients.configuration) — cùng nguồn dữ
// liệu với "⚙️ Đổi máy in" ở /print-station, chỉ đọc lại chứ không gọi API riêng.
const installedPrinters = ref<string[]>([]);
const defaultInstalledPrinter = ref<string | null>(null);
const loadingInstalledPrinters = ref(false);

async function fetchInstalledPrinters() {
  if (!currentWorkstation.value?.code) return;
  loadingInstalledPrinters.value = true;
  try {
    const res = await axios.get('/api/workstations');
    const list = res.data.data || res.data;
    const match = list.find((w: any) => w.code === currentWorkstation.value!.code);
    const config = match?.configuration || {};
    installedPrinters.value = config.available_printers || [];
    defaultInstalledPrinter.value = config.default_printer || null;
    if (!printerConfigForm.deviceId) {
      printerConfigForm.deviceId = currentWorkstation.value.assigned_printer_device_id || defaultInstalledPrinter.value || '';
    }
  } catch (err) {
    console.error('Failed to load installed printers:', err);
  } finally {
    loadingInstalledPrinters.value = false;
  }
}

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

const savePrinterConfig = async () => {
  if (!currentWorkstation.value || !printerConfigForm.deviceId) return;
  savingPrinterConfig.value = true;
  try {
    await axios.put(`/api/workstations/${currentWorkstation.value.id}/local-device-config`, {
      printer_device_id: printerConfigForm.deviceId,
      printer_connection_type: printerConfigForm.connectionType,
      printer_address: printerConfigForm.address || undefined,
    });
    currentWorkstation.value.assigned_printer_device_id = printerConfigForm.deviceId;
    showPrinterConfig.value = false;
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể lưu cấu hình máy in.');
  } finally {
    savingPrinterConfig.value = false;
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
  fetchInstalledPrinters();
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
