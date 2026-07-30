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
    <RestartJobModal
      :show="showRestartModal"
      :batch-label="activeBatch?.legacy_batch_id || ''"
      :submitting="restartSubmitting"
      @close="showRestartModal = false"
      @confirm="confirmRestartJob"
    />

    <!-- Xem trước phiếu cân (DF_WEIGHING_SLIP) — giống layout scaleform.btnPrint_Click VBA
         gốc: MAU/HANG/MAY/MUC + bảng RACK/DYE CODE/WEIGHT/STATUS + giờ in. -->
    <div v-if="showSlipPreview" class="slip-preview-overlay" @click.self="showSlipPreview = false">
      <div class="slip-preview-modal card-sec">
        <div class="slip-preview-header">
          <h3>🖨️ Xem trước phiếu cân (DF_WEIGHING_SLIP)</h3>
          <button class="btn btn-secondary btn-sm" @click="showSlipPreview = false">✖ Đóng</button>
        </div>
        <p class="text-muted font-sm">Đã gửi phiếu cân sang hàng chờ in — bên dưới là xem trước đúng nội dung/bố cục đã gửi.</p>
        <div class="slip-preview-box mt-3">
          <LabelPreview :label-payload="slipPreviewPayload" />
        </div>
      </div>
    </div>

    <!-- Wait/Ready Scanning Screen — port scaleform (VBA) chờ quét mã -->
    <QrScanPanel
      v-if="!activeJob"
      :view-only="isImpersonating && remoteMode === 'VIEW_ONLY'"
      @manual-qr-submit="handleBarcodeScan"
      @resume-job="({ job, batch }) => applyActiveJob(job, batch)"
    />

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
            <!-- Đóng đơn đang xem để quét/nạp đơn khác — KHÔNG xóa hay hủy gì dưới DB, đơn
                 vẫn giữ nguyên trạng thái đang cân dở, chỉ đưa màn hình về lại màn quét QR.
                 Muốn quay lại đơn này thì quét lại QR hoặc bấm thẻ "Đơn đang cân dở" ở màn
                 quét (yêu cầu 2026-07-30: không cần F5 để đổi đơn). -->
            <button
              class="btn btn-secondary btn-sm ml-2"
              @click="clearActiveJob"
              :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
              title="Đơn này vẫn giữ nguyên trạng thái đang cân dở, chỉ đóng màn hình xem để quét đơn khác"
            >
              ✕ Đóng đơn (quét đơn khác)
            </button>
            <!-- Hủy toàn bộ kết quả đã cân của mẻ này, quay lại vật tư đầu tiên — hành động
                 phá hủy dữ liệu đã lưu nên bắt buộc qua modal cảnh báo + tick xác nhận riêng
                 (RestartJobModal), không cho bấm 1 phát là chạy luôn (yêu cầu 2026-07-30). -->
            <button
              class="btn btn-secondary btn-sm ml-2 btn-restart-warn"
              @click="showRestartModal = true"
              :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
              title="Xóa toàn bộ kết quả đã cân của mẻ này, cân lại từ vật tư đầu tiên"
            >
              🔁 Cân lại từ đầu
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
          @start-weighing="startWeighing"
          @retare="resetTareForNewSlot"
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
import RestartJobModal from '../components/weighing/RestartJobModal.vue';
import LabelPreview from '../components/LabelPreview.vue';

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

// Lưu tạm bì (tare) của vật tư ĐANG cân dở vào localStorage — khác với DB (chỉ lưu khi
// bấm Xác nhận), cái này chỉ để sống sót qua F5/mất mạng/tắt máy giữa chừng, tránh phải
// đặt lại cốc/khay lên cân và chờ bì lại từ đầu (yêu cầu 2026-07-30). Key theo item.id
// (UUID ổn định) — mỗi trạm chỉ có 1 vật tư đang cân dở tại 1 thời điểm nên không cần
// lưu nhiều dòng cùng lúc.
const TARE_STORAGE_KEY = 'df_weigh_tare_state';

function saveTareToStorage(itemId: string, tare: number) {
  localStorage.setItem(TARE_STORAGE_KEY, JSON.stringify({ itemId, tare }));
}

function clearTareStorage() {
  localStorage.removeItem(TARE_STORAGE_KEY);
}

function restoreTareFromStorage(itemId: string): number | null {
  try {
    const raw = localStorage.getItem(TARE_STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed.itemId === itemId ? parsed.tare : null;
  } catch {
    return null;
  }
}

function resetTareForNewSlot() {
  tareBaseline.value = null;
  liveWeight.value = 0;
  grossWeight.value = 0;
  isStable.value = false;
  clearTareStorage();
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
    // Chờ thao tác viên chủ động bấm "▶️ Bắt đầu cân" (startWeighing) để xác nhận đây là
    // bì — KHÔNG tự khóa bì vào lần đọc ổn định đầu tiên nữa (yêu cầu 2026-07-30: đặt
    // cốc/thau lên cân xong, tự đọc ổn định ngay, nhưng operator cần bấm nút mới chốt bì,
    // tránh khóa nhầm bì lúc cân chưa kịp đứng đúng ý). Chỉ cập nhật cân gộp để hiển thị.
    return;
  }

  // KHÔNG dùng Math.abs() — cân cộng dồn trên cùng 1 đĩa (bì = cân gộp của item trước đó),
  // nếu vật tư bị lấy bớt ra/đổ tràn/rung lệch làm cân gộp tụt xuống dưới bì, net PHẢI ra
  // số âm để báo thật sự có bất thường (phản hồi 2026-07-30) — lấy trị tuyệt đối sẽ biến
  // 1 sai số âm thành nhìn giống "chưa đủ", che mất dấu hiệu mất vật tư.
  liveWeight.value = raw - tareBaseline.value;
}

// Xác nhận cân gộp hiện tại (đang đứng ổn định) chính là BÌ — bấm sau khi đặt cốc/khay/thau
// rỗng lên cân và thấy số đã ổn định, thay vì để hệ thống tự khóa bì vào lần đọc đầu tiên.
function startWeighing() {
  if (!isStable.value || tareBaseline.value !== null) return;
  tareBaseline.value = grossWeight.value;
  liveWeight.value = 0;
  if (activeIngredient.value) {
    saveTareToStorage(activeIngredient.value.id, grossWeight.value);
  }
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
    await handleBarcodeScan(`DF:ORDER:${batchIdParam}`);
  } else {
    // Trạm cân này gắn cố định cho đúng 1 máy (1 workstation = 1 nhiệm vụ) — nên tại
    // mọi thời điểm chỉ có tối đa 1 WeighingJob "đang chạy" cho trạm này. Job đã được
    // lưu xuống DB ngay lúc quét QR (handleOrderScan/scanRawDyeQr) — chỉ có state phía
    // Vue (RAM) là mất khi F5/mất mạng/tắt máy. Tự khôi phục lại đây để không bắt thao
    // tác viên quét lại QR mỗi lần mở trang, cân tiếp được ngay (yêu cầu 2026-07-30).
    await restoreActiveJob();
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

// Nạp 1 job đang chạy vào state hiển thị — dùng chung cho cả 2 nguồn: quét QR mới
// (handleBarcodeScan) và khôi phục job đang dở khi mở lại trang (restoreActiveJob).
function applyActiveJob(job: any, batch: any) {
  activeJob.value = job;
  activeBatch.value = batch;
  lastLabelPayload.value = null;

  // Auto focus on the first pending item
  const pIdx = job.items.findIndex((i: any) => i.status !== 'COMPLETED');
  activeIndex.value = pIdx >= 0 ? pIdx : 0;
  overrideApproved.value = false;
  overrideReason.value = '';

  // Nếu vật tư đang đứng (sau khi restore) đúng là vật tư đã lỡ lấy bì trước đó (còn lưu
  // trong localStorage từ trước khi F5/mất mạng) — khôi phục lại bì đó thay vì bắt đặt lại
  // cốc/khay lên cân từ đầu. Lần đọc cân kế tiếp sẽ tự tính đúng net = |raw - bì cũ|.
  const currentItem = job.items[activeIndex.value];
  const savedTare = currentItem ? restoreTareFromStorage(currentItem.id) : null;
  if (savedTare !== null) {
    tareBaseline.value = savedTare;
    grossWeight.value = savedTare;
    liveWeight.value = 0;
    isStable.value = false;
  } else {
    resetTareForNewSlot(); // vật tư chưa từng lấy bì (hoặc đã chuyển sang vật tư khác) — cân bì lại từ đầu
  }
}

// Trạm cân này gắn cố định cho đúng 1 máy — tự khôi phục job đang dở (nếu có) khi mở
// trang, không bắt quét lại QR. Job vẫn nằm nguyên trong DB từ lúc quét, chỉ mất state
// Vue khi F5/mất mạng. Xem WeighingJobController::activeForWorkstation.
const restoreActiveJob = async () => {
  if (!currentWorkstation.value) return;
  try {
    const res = await axios.get('/api/weighing-jobs/active', {
      params: { workstation_id: currentWorkstation.value.id },
    });
    const job = res.data?.data?.job;
    const batch = res.data?.data?.batch;
    if (job) {
      applyActiveJob(job, batch);
    }
  } catch (err) {
    console.error('Failed to restore active weighing job', err);
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
      applyActiveJob(data.job, data.batch);
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
  // Net âm = bất thường (mất vật tư/tràn/lệch bì cộng dồn), khác hẳn "insufficient" (chưa
  // đủ, cứ thêm tiếp) — tách riêng để không hiểu lầm là còn thiếu (phản hồi 2026-07-30).
  if (w < 0) return 'negative';
  if (w < min) return 'insufficient';
  if (w > max) return 'over-range';
  return 'in-range';
});

const statusMessage = computed(() => {
  const status = toleranceStatus.value;
  if (status === 'negative') {
    return `SỐ ÂM (${liveWeight.value.toFixed(2)} g) - VẬT TƯ BỊ HAO HỤT SO VỚI BÌ CỘNG DỒN, KIỂM TRA LẠI`;
  }
  if (status === 'zero') {
    // Cân cộng dồn (cumulative dosing) trên CÙNG 1 đĩa cân — vật tư của item trước vẫn
    // nằm nguyên trên đĩa khi chuyển sang item kế tiếp (không lấy ra), nên "cân gộp"
    // (grossWeight) > 0 nghĩa là đĩa đang có sẵn khối lượng cộng dồn, KHÔNG phải đĩa
    // trống — net=0 chỉ có nghĩa "chưa thêm gì mới cho item này", không phải "chưa có gì
    // trên cân". Dùng đúng chữ để tránh hiểu lầm (phản hồi 2026-07-30).
    return grossWeight.value > 0.05
      ? 'SẴN SÀNG (ĐÃ CÓ NỀN CÂN CỘNG DỒN) - CHƯA THÊM VẬT TƯ MỚI'
      : 'CÂN RỖNG - ĐỢI ĐẶT VẬT TƯ';
  }
  if (status === 'insufficient') return 'CHƯA ĐỦ - TIẾP TỤC THÊM VẬT TƯ';
  if (status === 'in-range') return 'ĐẠT DUNG SAI CHO PHÉP';
  return 'VƯỢT DUNG SAI - KIỂM TRA LẠI KHỐI LƯỢNG';
});

const selectSeqIndex = (idx: number) => {
  if (idx === activeIndex.value) return; // đang chọn đúng dòng hiện tại — khỏi reset bì vô cớ
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

// Cả 1 Mẻ nhuộm chỉ cân bì DUY NHẤT 1 lần — ngay từ vật tư đầu tiên (yêu cầu 2026-07-30:
// "1 Mẻ nhuộm chỉ cân bì 1 lần đầu thôi"). Từ vật tư thứ 2 trở đi KHÔNG bắt bấm lại
// "▶️ Bắt đầu cân": vì không lấy gì ra khỏi đĩa, cân gộp hiện tại (đúng lúc vừa xác nhận
// xong vật tư trước) CHÍNH LÀ bì mới cho vật tư kế tiếp — tự chốt luôn, net về 0 ngay.
function advanceToNextItem(idx: number) {
  activeIndex.value = idx;
  overrideApproved.value = false;
  overrideReason.value = '';
  tareBaseline.value = grossWeight.value;
  liveWeight.value = 0;
  const nextItem = activeJob.value?.items?.[idx];
  if (nextItem) {
    saveTareToStorage(nextItem.id, grossWeight.value);
  }
}

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
      clearTareStorage(); // vật tư này đã lưu xong xuống DB — bì tạm không còn ý nghĩa nữa

      const next = res.data.data?.next_item;
      const jobCompleted = res.data.data?.job_completed;

      if (jobCompleted) {
        // Mark job as completed
        activeJob.value.status = 'COMPLETED';
        // Auto trigger label printing
        await printMaterialLabel();
      } else if (next) {
        // Move to the next item automatically — cả mẻ chỉ cân bì 1 lần, tự chốt bì mới
        // luôn (advanceToNextItem), KHÔNG reset về "chờ cân bì lại" như chọn tay 1 dòng.
        const nextIdx = activeJob.value.items.findIndex((i: any) => i.id === next.id);
        if (nextIdx >= 0) {
          advanceToNextItem(nextIdx);
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

// Đóng đơn ĐANG CÂN DỞ để quét/nạp đơn khác — thay cho việc phải F5 (yêu cầu 2026-07-30).
// Chỉ đưa màn hình về lại QrScanPanel, KHÔNG đụng gì tới DB: đơn vẫn nguyên trạng thái cũ,
// và bì (nếu có) vẫn còn trong localStorage — nếu operator quay lại đúng đơn này (quét lại
// QR hoặc bấm thẻ "Đơn đang cân dở"), tiến độ + bì vẫn khôi phục đúng như restoreActiveJob.
const clearActiveJob = () => {
  if (activeJob.value?.status !== 'COMPLETED') {
    const ok = confirm('Đơn này vẫn đang cân dở, chưa hoàn tất. Đóng lại để quét đơn khác — đơn vẫn giữ nguyên tiến độ, có thể quay lại sau. Tiếp tục?');
    if (!ok) return;
  }
  resetToScan();
};

// ===== Cân lại từ đầu cả Mẻ nhuộm (yêu cầu 2026-07-30) — hủy kết quả đã cân của TẤT CẢ vật
// tư, quay lại vật tư đầu tiên. Modal cảnh báo + tick xác nhận bắt buộc (RestartJobModal),
// backend cũng yêu cầu lý do và tự ghi Audit Log (WeighingJobController::restart). =====
const showRestartModal = ref(false);
const restartSubmitting = ref(false);

const confirmRestartJob = async (reason: string) => {
  if (!activeJob.value) return;
  restartSubmitting.value = true;
  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/restart`, {
      reason,
      workstation_code: currentWorkstation.value?.code,
    }, getRequestConfig());

    if (res.data?.status === 'SUCCESS') {
      clearTareStorage(); // đơn đã reset về vật tư đầu — bì cũ (nếu còn sót) không còn hợp lệ nữa
      applyActiveJob(res.data.data.job, res.data.data.batch);
      showRestartModal.value = false;
      scannerService.playBeep(600, 200);
    }
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể cân lại từ đầu mẻ này.');
  } finally {
    restartSubmitting.value = false;
  }
};

// ===== Tra cứu bán thành phẩm — port VBA scaleform.btnCheck_Click → checkform =====
const showChecker = ref(false);

// ===== Phiếu cân tổng hợp — port VBA scaleform.btnPrint_Click (DF_WEIGHING_SLIP: header
// MAU/HANG/MAY/MUC + bảng RACK/DYE CODE/WEIGHT/STATUS + giờ in) — nội dung TSPL trả về từ
// backend giống hệt layout VBA, hiển thị lại bằng LabelPreview (đã có sẵn khả năng vẽ TSPL)
// để thao tác viên xem trước tem giống thật trước khi cầm tem giấy (yêu cầu 2026-07-30).
const showSlipPreview = ref(false);
const slipPreviewPayload = ref<string | null>(null);

const printSlip = async () => {
  if (!activeJob.value || !currentWorkstation.value) return;
  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print-slip`, {
      workstation_code: currentWorkstation.value.code
    }, getRequestConfig());
    scannerService.playBeep(1800, 150);
    slipPreviewPayload.value = res.data?.data?.label_payload || null;
    showSlipPreview.value = true;
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

.btn-restart-warn {
  color: var(--status-red);
  border-color: var(--status-red);
}

.slip-preview-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.55);
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 40px 16px;
  z-index: 1050;
}

.slip-preview-modal {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  width: 100%;
  max-width: 460px;
  max-height: 85vh;
  overflow-y: auto;
}

.slip-preview-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.slip-preview-box {
  display: flex;
  justify-content: center;
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
