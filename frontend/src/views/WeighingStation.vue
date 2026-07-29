<template>
  <div class="weighing-station-container">
    <!-- Remote Access Mode Banner -->
    <div v-if="isImpersonating" class="remote-banner mb-4" :class="remoteModeClass">
      <div class="banner-content">
        <span class="banner-icon">🌐</span>
        <div class="banner-text">
          <strong>CHẾ ĐỘ GIÁM SÁT TỪ XA: </strong>
          <span v-if="remoteMode === 'VIEW_ONLY'">CHỈ XEM (VIEW_ONLY) - Các nút thao tác nghiệp vụ đã bị vô hiệu hóa.</span>
          <span v-else>ĐIỀU KHIỂN TỪ XA (REMOTE_OPERATE) - Cho phép vận hành từ xa. Mọi thao tác sẽ được ghi Audit Log kiểm toán.</span>
        </div>
      </div>
      <div class="banner-actions">
        <select v-model="remoteMode" class="form-select font-xs select-mode">
          <option value="VIEW_ONLY">🔒 Chế độ Chỉ xem</option>
          <option value="REMOTE_OPERATE">⚡ Chế độ Điều khiển</option>
        </select>
      </div>
    </div>

    <!-- Top info bar -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge mr-2" :class="scaleTypeBadgeClass">
          {{ isLargeScale ? '⚡ LARGE SCALE (CÂN LỚN)' : '⚖️ SMALL SCALE (CÂN NHỎ)' }}
        </span>
        <h2>{{ currentWorkstation ? currentWorkstation.name : 'Chưa đăng ký trạm' }}</h2>
        <p class="text-muted font-sm">Mã trạm: <code>{{ currentWorkstation?.code }}</code> | Vị trí: {{ currentWorkstation?.location }}</p>
      </div>
      <div class="banner-right">
        <!-- Tra cứu bán thành phẩm — port scaleform.btnCheck_Click (VBA) → checkform -->
        <button class="btn btn-secondary btn-sm mr-2" @click="showChecker = true">🔍 Tra cứu</button>
        <!-- Scale heartbeat status -->
        <div class="dev-badge">
          <span class="dot-pulse" :class="scaleOnline ? 'dot-green' : 'dot-red'"></span>
          <span>Cân: {{ currentWorkstation?.assigned_scale_device_id || 'Chưa gán' }}</span>
        </div>
        <!-- Printer status -->
        <div class="dev-badge ml-2">
          <span class="dot-pulse" :class="printerOnline ? 'dot-green' : 'dot-red'"></span>
          <span>Máy in: {{ currentWorkstation?.assigned_printer_device_id || 'Chưa gán' }}</span>
        </div>
      </div>
    </div>

    <WeighingCheckerModal :show="showChecker" @close="showChecker = false" />

    <!-- Wait/Ready Scanning Screen — port scaleform (VBA) chờ quét mã -->
    <QrScanPanel v-if="!activeJob" :view-only="isImpersonating && remoteMode === 'VIEW_ONLY'" @manual-qr-submit="handleBarcodeScan" />

    <!-- Active Weighing Layout -->
    <div v-else class="two-col-grid">
      <!-- Left side: bảng RACK/DYE CODE/WEIGHT/PROCESS -->
      <div class="section card-sec flex-1">
        <div class="job-meta-header mb-4">
          <div class="meta-badge-row">
            <span class="badge badge-blue">JOB: {{ activeJob.job_type }}</span>
            <span class="badge badge-yellow" v-if="activeJob.status !== 'COMPLETED'">ĐANG CÂN ({{ completedItemsCount }}/{{ activeJob.items.length }})</span>
            <span class="badge badge-green" v-else>ĐÃ HOÀN TẤT CÂN</span>
            <!-- Phiếu cân tổng hợp — port scaleform.btnPrint_Click, không cần chờ job hoàn tất -->
            <button class="btn btn-secondary btn-sm ml-2" @click="printSlip" :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'">
              🖨️ In phiếu cân
            </button>
          </div>
          <h3>Mẻ nhuộm: <span class="text-glow-blue">{{ activeBatch.legacy_batch_id }}</span></h3>

          <div class="details-grid mt-3">
            <div><span class="label">Mã màu:</span> <span class="val">{{ activeBatch.color }}</span></div>
            <div><span class="label">Mã hàng:</span> <span class="val">{{ activeBatch.product_code }}</span></div>
            <div><span class="label">Máy nhuộm:</span> <span class="machine-tag">{{ activeBatch.machine?.code || 'N/A' }}</span></div>
            <div><span class="label">Mức nước:</span> <span class="val">{{ activeBatch.level_code || 'Mặc định' }}</span></div>
            <div><span class="label">Khối lượng vải:</span> <span class="val bold-text">{{ activeBatch.cloth_weight }} kg</span></div>
          </div>
        </div>

        <div class="job-items-sec">
          <h4>🧪 Bảng cân chi tiết (RACK / DYE CODE / WEIGHT / PROCESS):</h4>
          <WeighingRackTable :items="activeJob.items" :active-index="activeIndex" @select="selectSeqIndex" />
        </div>
      </div>

      <!-- Right side: LED Indicator, Live Scale & Confirmation -->
      <div class="section card-sec flex-1">
        <LiveScaleDisplay
          :live-weight="liveWeight"
          :is-stable="isStable"
          :tolerance-status="toleranceStatus"
          :status-message="statusMessage"
          :target-weight="getActiveTargetWeight()"
          v-model:use-sim-value="useSimValue"
          v-model:simulated-weight="simulatedWeight"
        />

        <WeighingConfirmPanel
          v-if="activeIngredient && activeJob.status !== 'COMPLETED'"
          :active-ingredient="activeIngredient"
          :live-weight="liveWeight"
          :is-stable="isStable"
          :tolerance-status="toleranceStatus"
          :min-allowed="getMinAllowed()"
          :max-allowed="getMaxAllowed()"
          :deviation-val="deviationVal"
          :deviation-percent="deviationPercent"
          :tare-baseline="tareBaseline"
          :gross-weight="grossWeight"
          v-model:override-approved="overrideApproved"
          v-model:override-reason="overrideReason"
          :view-only="isImpersonating && remoteMode === 'VIEW_ONLY'"
          @confirm="confirmWeighing"
        />

        <LabelPrintPanel
          v-if="activeJob.status === 'COMPLETED'"
          :active-job="activeJob"
          :active-batch="activeBatch"
          :label-payload="lastLabelPayload"
          :view-only="isImpersonating && remoteMode === 'VIEW_ONLY'"
          @reset-to-scan="resetToScan"
          @reprint="reprintLabel"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { currentWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { useAuthStore } from '../stores/auth';
import echo from '../services/echo';
import QrScanPanel from '../components/weighing/QrScanPanel.vue';
import WeighingRackTable from '../components/weighing/WeighingRackTable.vue';
import LiveScaleDisplay from '../components/weighing/LiveScaleDisplay.vue';
import WeighingConfirmPanel from '../components/weighing/WeighingConfirmPanel.vue';
import LabelPrintPanel from '../components/weighing/LabelPrintPanel.vue';
import WeighingCheckerModal from '../components/weighing/WeighingCheckerModal.vue';

const route = useRoute();
const authStore = useAuthStore();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);
const remoteMode = ref<'VIEW_ONLY' | 'REMOTE_OPERATE'>('VIEW_ONLY');

const remoteModeClass = computed(() => {
  return remoteMode.value === 'VIEW_ONLY' ? 'mode-view-only' : 'mode-remote-operate';
});

const isLargeScale = computed(() => {
  return currentWorkstation.value?.workstation_type === 'LARGE_SCALE' || currentWorkstation.value?.type === 'LARGE_SCALE';
});

const scaleTypeBadgeClass = computed(() => {
  return isLargeScale.value ? 'badge-yellow' : 'badge-blue';
});

function getRequestConfig() {
  const config: { headers: Record<string, string> } = { headers: {} };
  if (isImpersonating.value && remoteMode.value === 'REMOTE_OPERATE') {
    config.headers['X-Remote-Operation'] = 'true';
    config.headers['X-Target-Workstation'] = String(targetWsId.value || '');
    config.headers['X-Remote-Reason'] = 'Admin remote weighing control';
  }
  return config;
}

// State variables
const scaleOnline = ref(true);
const printerOnline = ref(true);

// Active Job context
const activeJob = ref<any | null>(null);
const activeBatch = ref<any | null>(null);
const activeIndex = ref<number>(0);
const lastLabelPayload = ref<string | null>(null);

// Scale Telemetry state
// liveWeight = NET (đã trừ bì) — giá trị dùng để hiển thị/so dung sai/gửi khi xác nhận.
const liveWeight = ref<number>(0);
// PB-2 (đã sửa 2026-07-17): trước đây gửi cứng stable:true khi xác nhận cân, không phản ánh
// StableFilter thật. Khi dùng simulator (useSimValue=true), giá trị do người dùng tự đặt cố
// định qua slider nên coi là ổn định (không có "rung" để lọc). Khi dùng cân thật, lấy is_stable
// từ Agent (ScaleReader.StableFilter) qua /api/devices/readings — xem fetchLiveWeight().
const isStable = ref<boolean>(true);
const simulatedWeight = ref<number>(0);
const useSimValue = ref<boolean>(true);
const overrideApproved = ref<boolean>(false);
const overrideReason = ref<string>('');

// Trừ bì (tare/delta) — xác nhận nghiệp vụ 2026-07-18 (CH-BUS-006): cốc/khay/thùng đặt lên
// cân TRƯỚC khi thêm vật tư coi là bì, phải trừ đi. Đúng VBA Mod_delta_raw:
// Delta_Begin (reset baseline = null khi bắt đầu 1 vật tư/slot mới) + AutoFlow_OnWeight
// (lần đọc ỔN ĐỊNH ĐẦU TIÊN sau reset = bì, không tính là kết quả; từ lần đọc ổn định
// THỨ HAI trở đi, net = |raw - bì|). Baseline sống trong phiên làm việc (không lưu DB) —
// đúng bản chất VBA (biến module trong form đang mở, mất khi đóng form/chuyển vật tư khác).
const tareBaseline = ref<number | null>(null);
const grossWeight = ref<number>(0);

function resetTareForNewSlot() {
  tareBaseline.value = null;
  liveWeight.value = 0;
  grossWeight.value = 0;
  isStable.value = false;
}

/**
 * Cổng vào DUY NHẤT cho mọi nguồn số cân thô (cân thật qua Agent, hoặc simulator) — áp
 * đúng logic Delta_Begin/AutoFlow_OnWeight. Chỉ nhận số ổn định (đã qua StableFilter ở
 * tầng Agent, hoặc simulator luôn coi ổn định) mới được xét làm bì/tính net — số chưa ổn
 * định chỉ cập nhật "cân gộp" hiển thị, không đụng vào bì/net.
 */
function ingestRawWeight(raw: number, stable: boolean) {
  grossWeight.value = raw;
  isStable.value = stable;
  if (!stable) return;

  if (tareBaseline.value === null) {
    // Lần đọc ổn định ĐẦU TIÊN sau khi bắt đầu vật tư mới = bì — không tính là kết quả,
    // giữ hiển thị net = 0 (giống VBA: Exit Sub, không cập nhật gì thêm).
    tareBaseline.value = raw;
    liveWeight.value = 0;
    return;
  }

  liveWeight.value = Math.abs(raw - tareBaseline.value);
}

let livePoller: any = null;

// Lifecycle
onMounted(async () => {
  // Resolve Workstation if impersonating
  if (isImpersonating.value && targetWsId.value) {
    try {
      const res = await axios.get('/api/workstations');
      const wsList = res.data.data || res.data;
      const target = wsList.find((w: any) => String(w.id) === String(targetWsId.value));
      if (target) {
        currentWorkstation.value = target;
      }
    } catch (e) {
      console.error('Failed to load impersonated workstation', e);
    }
  }

  // Đến từ nút "Sang Trạm cân" ở /order-scan — mở thẳng lô đó, khỏi phải quét lại
  // (đi qua đúng luồng xử lý DF:ORDER: như quét thật, không phải đường tắt riêng).
  const batchIdParam = route.query.batch_id;
  if (batchIdParam) {
    handleBarcodeScan(`DF:ORDER:${batchIdParam}`);
  }

  // Register scanner callback
  scannerService.onScan(handleBarcodeScan);

  // Poll live scale readings
  // 1000ms thay vì 500ms — php artisan serve (Windows, single-thread) không chịu được
  // tần suất 2 lần/giây khi có nhiều trạm cân mở cùng lúc, làm nghẽn các request khác.
  livePoller = setInterval(fetchLiveWeight, 1000);
});

onUnmounted(() => {
  scannerService.offScan(handleBarcodeScan);
  if (livePoller) clearInterval(livePoller);
});

// Watch simulator slider — đi qua CÙNG cổng ingestRawWeight() như cân thật, để kiểm thử
// được đúng hành vi trừ bì mà không cần phần cứng: kéo lần đầu = đặt cốc rỗng (bì), kéo
// tiếp = cốc + vật tư (net tự trừ bì). Simulator luôn coi số kéo ra là ổn định ngay (không
// có "rung" để lọc, không qua StableFilter thật).
watch(simulatedWeight, (newVal) => {
  if (useSimValue.value) {
    ingestRawWeight(newVal, true);
  }
});

// Bật/tắt simulator — không trộn baseline giữa cân thật và giả lập.
watch(useSimValue, () => {
  resetTareForNewSlot();
});

// Computed properties
const activeIngredient = computed(() => {
  if (!activeJob.value || !activeJob.value.items) return null;
  return activeJob.value.items[activeIndex.value] || null;
});

const completedItemsCount = computed(() => {
  if (!activeJob.value) return 0;
  return activeJob.value.items.filter((i: any) => i.status === 'COMPLETED').length;
});

// Fetch live scale weight from Cache
const fetchLiveWeight = async () => {
  if (useSimValue.value) return; // ignore API when using simulator

  if (!currentWorkstation.value) return;
  try {
    // Sửa 2026-07-17: response API là object PHẲNG ({status, workstation_id, weight,
    // is_stable}), không lồng thêm 1 lớp "data" — code cũ đọc res.data.data?.weight nên
    // luôn nhận undefined→0 khi thật sự dùng cân thật (bug bị che khuất vì useSimValue mặc
    // định true nên nhánh này ít khi chạy tới trong demo/test thủ công).
    const res = await axios.get(`/api/devices/readings/${currentWorkstation.value.id}`);
    if (res.data?.status === 'SUCCESS') {
      const rawWeight = parseFloat(res.data.weight ?? 0);
      const rawStable = Boolean(res.data.is_stable);
      ingestRawWeight(rawWeight, rawStable);
      scaleOnline.value = true;
    }
  } catch (err) {
    scaleOnline.value = false;
  }
};

// Handle QR Scanner keyboard wedge event
const handleBarcodeScan = async (token: string) => {
  if (!currentWorkstation.value) return;

  // QR thật do QR_LABEL_PRINTING in ra (đúng định dạng VBA gốc qua QrPayloadService)
  // luôn bắt đầu bằng "#" (vd "#RED-P123-VD10-220-..."), khác với token giả lập
  // "DF:ORDER:<uuid>"/"DF:MATERIAL_LABEL:<uuid>" dùng cho công cụ mock ở QrScanPanel. Định
  // tuyến đúng endpoint theo định dạng thật của chuỗi quét được — không đổi hành vi
  // của luồng DF:ORDER: hiện có.
  const isRealVbaQr = token.startsWith('#');
  const scanUrl = isRealVbaQr ? '/api/scanner/scan-dye-qr' : '/api/scanner/scan';
  const scanPayload = isRealVbaQr
    ? { raw_qr: token, workstation_code: currentWorkstation.value.code }
    : { qr_token: token, workstation_code: currentWorkstation.value.code };

  try {
    const res = await axios.post(scanUrl, scanPayload, getRequestConfig());

    if (res.data?.status === 'SUCCESS') {
      const data = res.data.data;
      if (data.empty) {
        scannerService.playBeep(800, 300); // warning beep
        alert(res.data.message);
        return;
      }
      activeJob.value = data.job;
      activeBatch.value = data.batch;
      lastLabelPayload.value = null;

      // Auto focus on the first pending item
      const pIdx = activeJob.value.items.findIndex((i: any) => i.status !== 'COMPLETED');
      activeIndex.value = pIdx >= 0 ? pIdx : 0;
      overrideApproved.value = false;
      overrideReason.value = '';
      resetTareForNewSlot(); // vật tư đầu tiên của đơn mới — bắt buộc cân bì lại từ đầu
    }
  } catch (err: any) {
    scannerService.playBeep(600, 400); // Error sound
    alert(err.response?.data?.message || 'Không thể mở lệnh sản xuất này.');
  }
};

// Target values helper
const getActiveTargetWeight = () => {
  return activeIngredient.value ? activeIngredient.value.planned_weight : 100;
};

const getMinAllowed = () => {
  if (!activeIngredient.value) return 0;
  return activeIngredient.value.planned_weight - activeIngredient.value.tolerance_minus;
};

const getMaxAllowed = () => {
  if (!activeIngredient.value) return 0;
  return activeIngredient.value.planned_weight + activeIngredient.value.tolerance_plus;
};

const deviationVal = computed(() => {
  if (!activeIngredient.value) return 0;
  return liveWeight.value - activeIngredient.value.planned_weight;
});

const deviationPercent = computed(() => {
  if (!activeIngredient.value || activeIngredient.value.planned_weight === 0) return 0;
  return (deviationVal.value / activeIngredient.value.planned_weight) * 100;
});

// Live Tolerance checks — 3 mức đúng VBA Mod_UI_processcolor.CheckRange (vàng "chưa
// đủ" / xanh "đạt" / đỏ "vượt"), thay vì gộp chung 1 mức "out-of-range" như trước
// (p0-c-scale-algorithm.md Mục A.7, TV5). Ngưỡng vẫn dùng tolerance_minus/plus tuyệt
// đối theo từng item (cải tiến hợp lý so với %, đã có sẵn) — chỉ khôi phục lại việc
// PHÂN BIỆT "thiếu, cứ thêm tiếp" khỏi "vượt/lệch, cần xử lý khác", không đổi ngưỡng.
// Cổng lưu (backend) vẫn nhị phân trong/ngoài dung sai — đúng bản chất VBA (operator
// chỉ bấm Save khi đã thấy xanh, không có luồng nghiệp vụ riêng nào cho "vàng" ở bước
// lưu, chỉ khác nhau ở màu hiển thị trong lúc đang cân).
const toleranceStatus = computed(() => {
  if (!activeIngredient.value) return 'out';
  const w = liveWeight.value;
  const min = getMinAllowed();
  const max = getMaxAllowed();

  if (w === 0) return 'zero';
  if (w < min) return 'insufficient';
  if (w > max) return 'over-range';
  return 'in-range';
});

const statusMessage = computed(() => {
  const status = toleranceStatus.value;
  if (status === 'zero') return 'CÂN RỖNG - ĐỢI ĐẶT VẬT TƯ';
  if (status === 'insufficient') return 'CHƯA ĐỦ - TIẾP TỤC THÊM VẬT TƯ';
  if (status === 'in-range') return 'ĐẠT DUNG SAI CHO PHÉP';
  return 'VƯỢT DUNG SAI - KIỂM TRA LẠI KHỐI LƯỢNG';
});

const selectSeqIndex = (idx: number) => {
  activeIndex.value = idx;
  overrideApproved.value = false;
  overrideReason.value = '';
  resetTareForNewSlot(); // đổi vật tư = đặt cốc/khay mới lên cân — phải cân bì lại (Delta_Begin)
  if (useSimValue.value) {
    // Trước đây tự set thẳng slider = mục tiêu (tiện test nhưng bỏ qua bì). Nay set về 0
    // (mô phỏng "chưa đặt gì lên cân") — tester tự kéo 2 bước: bì trước, rồi bì+vật tư.
    simulatedWeight.value = 0;
  }
};

// Confirm scale reading action
const confirmWeighing = async () => {
  if (!activeIngredient.value) return;

  if (!currentWorkstation.value?.assigned_scale_device_id) {
    alert('Lỗi: Máy trạm chưa được cấu hình Thiết bị Cân. Không thể thực hiện cân.');
    return;
  }

  let managerPin: string | null = null;
  if (overrideApproved.value && !authStore.user) {
    managerPin = prompt('Nhập mã PIN của Giám sát (Supervisor) để duyệt override dung sai:');
    if (!managerPin) {
      alert('Cần có mã PIN Giám sát để duyệt override dung sai.');
      return;
    }
  }

  try {
    const res = await axios.post(`/api/weighing-jobs/items/${activeIngredient.value.id}/weigh`, {
      weight: liveWeight.value, // NET — đã trừ bì
      tare_weight: tareBaseline.value,
      gross_weight: grossWeight.value,
      // RACK — bảng 9 dòng RACK/DYE CODE/WEIGHT/PROCESS (scaleform.frm VBA gốc), tự điền
      // từ QR hoặc do thao tác viên gõ/sửa tay trên WeighingRackTable trước khi xác nhận.
      rack_code: activeIngredient.value.rack_code,
      scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
      stable: isStable.value,
      override_approved: overrideApproved.value,
      override_reason: overrideApproved.value ? overrideReason.value : null,
      manager_pin: managerPin
    }, getRequestConfig());

    if (res.data?.status === 'SUCCESS') {
      // Update local state
      activeIngredient.value.actual_weight = liveWeight.value;
      activeIngredient.value.status = 'COMPLETED';

      const next = res.data.data?.next_item;
      const jobCompleted = res.data.data?.job_completed;

      if (jobCompleted) {
        // Mark job as completed
        activeJob.value.status = 'COMPLETED';
        // Auto trigger label printing
        await printMaterialLabel();
      } else if (next) {
        // Move to the next item automatically
        const nextIdx = activeJob.value.items.findIndex((i: any) => i.id === next.id);
        if (nextIdx >= 0) {
          selectSeqIndex(nextIdx);
        }
      }

      // Reset override flags
      overrideApproved.value = false;
      overrideReason.value = '';
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể xác nhận khối lượng cân.');
  }
};

// Print label trigger
const printMaterialLabel = async () => {
  if (!activeJob.value || !currentWorkstation.value) return;
  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print`, {
      workstation_code: currentWorkstation.value.code
    }, getRequestConfig());
    lastLabelPayload.value = res.data?.data?.print_job?.label_payload || null;
    scannerService.playBeep(2200, 200);
  } catch (err: any) {
    console.error('Failed to trigger print:', err);
  }
};

// Reprint label trigger
const reprintLabel = async () => {
  if (!activeJob.value) return;

  if (!currentWorkstation.value?.assigned_printer_device_id) {
    alert('Lỗi: Máy trạm chưa được cấu hình Thiết bị Máy in. Không thể in lại tem.');
    return;
  }

  let managerPin: string | null = null;
  if (!authStore.user) {
    managerPin = prompt('Nhập mã PIN của Giám sát (Supervisor) để in lại tem:');
    if (!managerPin) {
      alert('Cần có mã PIN Giám sát để in lại tem.');
      return;
    }
  }

  const reason = prompt('Vui lòng nhập lý do in lại tem (Audit Log bắt buộc):');
  if (!reason || reason.trim().length < 5) {
    alert('Lý do in lại tem phải có ít nhất 5 ký tự.');
    return;
  }

  try {
    // Get label ID from first item
    const labelId = activeJob.value.items[0]?.label_id;
    if (!labelId) return;

    const res = await axios.post(`/api/material-labels/${labelId}/reprint`, {
      reason: reason,
      workstation_code: currentWorkstation.value?.code,
      manager_pin: managerPin
    }, getRequestConfig());
    lastLabelPayload.value = res.data?.data?.label_payload || lastLabelPayload.value;
    alert('Đã gửi yêu cầu in lại tem.');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể in lại tem.');
  }
};

const resetToScan = () => {
  activeJob.value = null;
  activeBatch.value = null;
  lastLabelPayload.value = null;
};

// ===== Tra cứu bán thành phẩm — port VBA scaleform.btnCheck_Click → checkform =====
const showChecker = ref(false);

// ===== Phiếu cân tổng hợp — port VBA scaleform.btnPrint_Click =====
const printSlip = async () => {
  if (!activeJob.value || !currentWorkstation.value) return;
  try {
    await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print-slip`, {
      workstation_code: currentWorkstation.value.code
    }, getRequestConfig());
    scannerService.playBeep(1800, 150);
    alert('Đã gửi phiếu cân sang hàng chờ in.');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể in phiếu cân.');
  }
};
</script>

<style scoped>
.weighing-station-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.station-banner {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl) var(--space-2xl);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.banner-left h2 {
  font-size: 1.4rem;
  color: var(--text-title);
  margin: 4px 0;
}

.station-badge {
  font-size: 10px;
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  color: var(--status-blue);
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--radius-sm);
  width: fit-content;
}

.banner-right {
  display: flex;
}

.dev-badge {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  padding: 6px 12px;
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* Weighing Layout */
.job-meta-header h3 {
  font-size: 1.3rem;
  margin: 8px 0;
}

.meta-badge-row {
  display: flex;
  gap: 8px;
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
  background-color: var(--bg-main);
  padding: 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.details-grid .label {
  font-size: 11px;
  color: var(--text-muted);
}

.details-grid .val {
  font-weight: 600;
  color: var(--text-title);
}

/* Remote monitoring banner styles */
.remote-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md) var(--space-lg);
  border-radius: var(--radius-md);
  border-width: 1px;
  border-style: solid;
  font-size: var(--font-sm);
  transition: all 0.3s ease;
}

.mode-view-only {
  background-color: var(--status-blue-bg);
  border-color: var(--status-blue-border);
  color: var(--status-blue);
}

.mode-remote-operate {
  background-color: var(--status-yellow-bg);
  border-color: var(--status-yellow-border);
  color: var(--status-yellow);
}

.banner-content {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.banner-icon {
  font-size: var(--font-lg);
}

.select-mode {
  width: 180px;
  background-color: var(--bg-card);
  border-color: var(--border-card);
  color: var(--text-body);
}
</style>
