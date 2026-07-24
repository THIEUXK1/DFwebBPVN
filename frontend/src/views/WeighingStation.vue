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
        <button class="btn btn-secondary btn-sm mr-2" @click="openChecker">🔍 Tra cứu</button>
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

    <!-- Modal Tra cứu bán thành phẩm — port checkform (COLOR+CODE+số ngày → danh sách mẻ đã cân) -->
    <div v-if="showChecker" class="checker-overlay" @click.self="showChecker = false">
      <div class="checker-modal card-sec">
        <div class="checker-header">
          <h3>🔍 Tra cứu bán thành phẩm đã cân</h3>
          <button class="btn btn-secondary btn-sm" @click="showChecker = false">✖ Đóng</button>
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
                    <span v-if="it.override_approved" class="text-danger">⚠️ Override</span>
                    <span v-else class="text-success">✔️ Đạt</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Wait/Ready Scanning Screen -->
    <div v-if="!activeJob" class="scanning-wait-screen card-sec text-center">
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
      <!-- Printer warning + tự cấu hình tại chỗ -->
      <div v-if="!currentWorkstation?.assigned_printer_device_id" class="card warning-card mb-4" style="color:#eab308; border-color:#eab308; background:rgba(234,179,8,0.05); padding:12px; border-radius:8px; border:1px solid rgba(234,179,8,0.2); text-align:left;">
        ⚠️ Trạm chưa gán máy in chính.
        <button @click="showPrinterConfig = !showPrinterConfig" class="btn btn-secondary btn-sm ml-2">⚙️ Cấu hình máy in ngay</button>
        <div v-if="showPrinterConfig" class="mt-3 device-config-form">
          <input v-model="printerConfigForm.deviceId" type="text" class="form-control mb-2" placeholder="Mã máy in, vd: TSC TE200" />
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
            :disabled="!manualQrInput || (isImpersonating && remoteMode === 'VIEW_ONLY')"
          >
            Nạp đơn
          </button>
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
            :disabled="!mockSelectedBatchId || (isImpersonating && remoteMode === 'VIEW_ONLY')"
          >
            Simulate QR Scan
          </button>
        </div>
      </div>
    </div>

    <!-- Active Weighing Layout -->
    <div v-else class="two-col-grid">
      <!-- Left side: Job sequence & materials list -->
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
          <h4>🧪 Danh sách cân chi tiết (Sequence):</h4>
          <div class="sequence-list">
            <div 
              v-for="(item, idx) in activeJob.items" 
              :key="item.id" 
              :class="['seq-card', activeIndex === idx ? 'active' : item.status === 'COMPLETED' ? 'done' : 'pending']"
              @click="selectSeqIndex(idx)"
            >
              <div class="seq-num">#{{ item.sequence_no }}</div>
              <div class="seq-content">
                <div class="item-name">{{ item.material?.name || item.material_code }}</div>
                <div class="item-code">Mã: <code>{{ item.material_code }}</code></div>
              </div>
              <div class="seq-weights">
                <div class="target-w">Mục tiêu: {{ item.planned_weight.toFixed(2) }} g</div>
                <div v-if="item.status === 'COMPLETED'" class="actual-w text-success">
                  Thực tế: <strong>{{ item.actual_weight.toFixed(2) }} g</strong>
                </div>
              </div>
              <div class="seq-status">
                <span v-if="item.status === 'COMPLETED'">✅</span>
                <span v-else-if="activeIndex === idx">⚖️</span>
                <span v-else>⏳</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side: LED Indicator, Live Scale & Confirmation -->
      <div class="section card-sec flex-1">
        <div class="led-display-container">
          <div class="led-label">LIVE SCALE INDICATOR</div>
          <div :class="['led-numbers', toleranceStatus]">
            {{ liveWeight.toFixed(2) }} <span class="unit">g</span>
          </div>
          <div class="led-status-text" :class="toleranceStatus">
            {{ statusMessage }}
          </div>
          <div class="stable-indicator" :class="isStable ? 'is-stable' : 'is-unstable'">
            {{ isStable ? '✅ SỐ CÂN ỔN ĐỊNH' : '⏳ ĐANG CHỜ SỐ CÂN ỔN ĐỊNH...' }}
          </div>
        </div>

        <!-- Scale weight simulator slider (Always available for testing and safety backup) -->
        <div class="scale-simulator-box mt-4">
          <div class="header-sim">
            <span>🎛️ Giả lập số cân điện tử (Simulator)</span>
            <input type="checkbox" v-model="useSimValue" /> Bật giả lập
          </div>
          <div class="sim-controls" v-if="useSimValue">
            <input 
              type="range" 
              v-model.number="simulatedWeight" 
              :min="0" 
              :max="getActiveTargetWeight() * 1.5" 
              step="0.1" 
              class="sim-slider"
            />
            <input 
              type="number" 
              v-model.number="simulatedWeight" 
              step="0.1" 
              class="form-input sim-num-input"
            /> g
          </div>
        </div>

        <!-- Active Target Specs -->
        <div class="tolerance-details-box mt-4" v-if="activeIngredient && activeJob.status !== 'COMPLETED'">
          <h4>Vật tư đang cân:</h4>
          <div class="target-meta">
            <div class="meta-row">
              <span class="label">Tên hóa chất:</span>
              <span class="value">{{ activeIngredient.material?.name || activeIngredient.material_code }}</span>
            </div>
            <div class="meta-row">
              <span class="label">Mục tiêu:</span>
              <span class="value bold-text text-glow">{{ activeIngredient.planned_weight.toFixed(2) }} g</span>
            </div>
            <div class="meta-row">
              <span class="label">Dung sai cho phép:</span>
              <span class="value text-success font-code">
                {{ getMinAllowed().toFixed(2) }} g - {{ getMaxAllowed().toFixed(2) }} g
              </span>
            </div>
            <div class="meta-row" v-if="deviationPercent !== 0">
              <span class="label">Sai số thực tế:</span>
              <span :class="['value', toleranceStatus === 'in-range' ? 'text-success' : 'text-danger']">
                {{ deviationVal.toFixed(2) }} g ({{ deviationPercent.toFixed(2) }}%)
              </span>
            </div>
          </div>

          <!-- Minh bạch trừ bì (tare) — đúng VBA Delta_Begin: lần đọc ổn định đầu tiên sau khi
               đổi vật tư = bì (cốc/khay/thùng), các lần sau tự trừ. Hiển thị rõ để thao tác
               viên biết vì sao net khác với số cân hiển thị trên máy cân vật lý. -->
          <div class="tare-info-box mt-3">
            <span v-if="tareBaseline === null" class="text-muted font-xs">
              ⏳ Chờ đặt cốc/khay rỗng lên cân để lấy bì...
            </span>
            <span v-else class="font-xs text-muted">
              Bì: <strong>{{ tareBaseline.toFixed(2) }} g</strong> ·
              Cân gộp: <strong>{{ grossWeight.toFixed(2) }} g</strong> ·
              Thực (net): <strong class="text-success">{{ liveWeight.toFixed(2) }} g</strong>
            </span>
          </div>

          <!-- Exception override triggers -->
          <div class="override-box mt-3" v-if="toleranceStatus !== 'in-range' && liveWeight > 0">
            <div class="override-checkbox">
              <input type="checkbox" v-model="overrideApproved" id="chk-override" :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'" />
              <label for="chk-override"><strong>⚠️ Yêu cầu Override Dung sai (Cần QA/QC phê duyệt)</strong></label>
            </div>
            <textarea 
              v-if="overrideApproved" 
              v-model="overrideReason" 
              placeholder="Nhập lý do override dung sai..." 
              class="form-input mt-2"
              rows="2"
            ></textarea>
          </div>

          <!-- Confirm button -->
          <div class="action-buttons-group mt-4">
            <button
              @click="confirmWeighing"
              class="weigh-confirm-btn"
              :disabled="(toleranceStatus !== 'in-range' && !overrideApproved) || (isImpersonating && remoteMode === 'VIEW_ONLY') || !isStable"
              :title="!isStable ? 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau (PB-2)' : ''"
            >
              ⚖️ Xác nhận lưu số cân (Save Weight)
            </button>
          </div>
        </div>

        <!-- Printing & Label section -->
        <div class="printed-label-box mt-4" v-if="activeJob.status === 'COMPLETED'">
          <h3>🏷️ TEM QR VẬT TƯ SAU CÂN</h3>
          <p class="font-sm text-muted">Cân đã hoàn tất. Tem TSC đã được spool tự động gửi đến máy in.</p>
          
          <div class="label-preview mt-3">
            <div class="qr-placeholder">🔳</div>
            <div class="label-info">
              <div><strong>Lô: {{ activeBatch.legacy_batch_id }}</strong></div>
              <div>Loại: {{ activeJob.job_type }}</div>
              <div>Trọng lượng: {{ (activeJob.items.reduce((s, i) => s + (i.actual_weight || 0), 0) / 1000).toFixed(2) }} kg</div>
              <div>Đích: Máy {{ activeBatch.machine?.code }} | Thùng {{ activeBatch.tank?.code || 'N/A' }}</div>
            </div>
          </div>

          <div class="mt-4 flex-row gap-3">
            <button @click="resetToScan" class="btn btn-secondary flex-1" :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'">
              ⬅️ Tiếp tục quét mẻ mới
            </button>
            <button @click="reprintLabel" class="btn btn-primary flex-1" :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'">
              🖨️ In lại tem (Reprint)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { currentWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { useAuthStore } from '../stores/auth';
import echo from '../services/echo';

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
const databaseBatches = ref<any[]>([]);
const mockSelectedBatchId = ref<string>('');
const manualQrInput = ref<string>('');

// Tự cấu hình cân/máy in ngay tại trạm — không qua Admin (đơn giản hóa 2026-07-18)
const showScaleConfig = ref(false);
const savingScaleConfig = ref(false);
const scaleConfigForm = reactive({ deviceId: '', comPort: '' });

const showPrinterConfig = ref(false);
const savingPrinterConfig = ref(false);
const printerConfigForm = reactive({ deviceId: '', connectionType: 'USB', address: '' });

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

// Active Job context
const activeJob = ref<any | null>(null);
const activeBatch = ref<any | null>(null);
const activeIndex = ref<number>(0);

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
let batchPollInterval: any = null;

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

  fetchWaitingBatches();

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

  // Realtime qua Reverb — lô mới được tạo/duyệt ở /production-batches phải xuất hiện
  // ngay trong dropdown "Giả lập Quét mã" ở đây, không cần rời màn hình rồi quay lại.
  // Cùng kênh với ProductionBatches.vue/ProductionBatchesList.vue (xem
  // ProductionBatchUpdated::broadcastOn ở backend). Polling giữ làm lưới an toàn.
  echo.channel('production-batches').listen('.updated', fetchWaitingBatches);
  batchPollInterval = setInterval(fetchWaitingBatches, 15000);
});

onUnmounted(() => {
  scannerService.offScan(handleBarcodeScan);
  if (livePoller) clearInterval(livePoller);
  if (batchPollInterval) clearInterval(batchPollInterval);
  echo.leaveChannel('production-batches');
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

// Fetch current waiting batches for the simulator dropdown
const fetchWaitingBatches = async () => {
  try {
    const res = await axios.get('/api/production-batches');
    databaseBatches.value = res.data.data || res.data || [];
  } catch (err) {
    console.error('Failed to load batches:', err);
  }
};

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
  // "DF:ORDER:<uuid>"/"DF:MATERIAL_LABEL:<uuid>" dùng cho công cụ mock ở trên. Định
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

// Simulated scan handler
const triggerMockScan = () => {
  if (!mockSelectedBatchId.value) return;
  const token = `DF:ORDER:${mockSelectedBatchId.value}`;
  scannerService.simulateScan(token);
};

// Nhập tay QR thật (fallback máy quét lỗi) — đi qua đúng luồng xử lý như quét vật
// lý thật (handleBarcodeScan tự định tuyến theo định dạng chuỗi).
const submitManualQr = () => {
  const token = manualQrInput.value.trim();
  if (!token) return;
  handleBarcodeScan(token);
  manualQrInput.value = '';
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
    alert("Lỗi: Máy trạm chưa được cấu hình Thiết bị Cân. Không thể thực hiện cân.");
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
    await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print`, {
      workstation_code: currentWorkstation.value.code
    }, getRequestConfig());
    scannerService.playBeep(2200, 200);
  } catch (err: any) {
    console.error('Failed to trigger print:', err);
  }
};

// Reprint label trigger
const reprintLabel = async () => {
  if (!activeJob.value) return;

  if (!currentWorkstation.value?.assigned_printer_device_id) {
    alert("Lỗi: Máy trạm chưa được cấu hình Thiết bị Máy in. Không thể in lại tem.");
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

    await axios.post(`/api/material-labels/${labelId}/reprint`, {
      reason: reason,
      workstation_code: currentWorkstation.value?.code,
      manager_pin: managerPin
    }, getRequestConfig());
    alert('Đã gửi yêu cầu in lại tem.');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể in lại tem.');
  }
};

const resetToScan = () => {
  activeJob.value = null;
  activeBatch.value = null;
  mockSelectedBatchId.value = '';
  fetchWaitingBatches();
};

// ===== Tra cứu bán thành phẩm — port VBA scaleform.btnCheck_Click → checkform =====
const showChecker = ref(false);
const checkerColor = ref('');
const checkerCode = ref('');
const checkerDaysBack = ref<number | null>(null);
const checkerLoading = ref(false);
const checkerResults = ref<any[]>([]);
const checkerMessage = ref('');

const openChecker = () => {
  showChecker.value = true;
};

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

.sequence-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-top: 12px;
}

.seq-card {
  display: flex;
  align-items: center;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s;
}

.seq-card:hover {
  background-color: var(--bg-card-hover);
}

.seq-card.active {
  border-color: var(--status-blue);
  box-shadow: 0 0 8px rgba(0, 168, 204, 0.25);
  background-color: var(--bg-card-hover);
}

.seq-card.done {
  opacity: 0.75;
  background-color: rgba(46, 204, 113, 0.03);
  border-color: rgba(46, 204, 113, 0.2);
}

.seq-num {
  font-size: 11px;
  font-weight: 700;
  background-color: var(--bg-main);
  color: var(--text-muted);
  width: 24px;
  height: 24px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
}

.seq-content {
  flex: 1;
}

.item-name {
  font-weight: 600;
  color: var(--text-title);
  font-size: 13px;
}

.item-code {
  font-size: 11px;
}

.seq-weights {
  text-align: right;
  margin-right: 16px;
}

.target-w {
  font-size: 11px;
  color: var(--text-muted);
}

.actual-w {
  font-size: 13px;
}

/* LED Indicator Display */
.led-display-container {
  background-color: #0b0f19;
  border: 2px solid #1a2236;
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  text-align: center;
  box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
}

.led-label {
  font-family: 'Outfit', sans-serif;
  font-size: 10px;
  color: #3e527a;
  letter-spacing: 2px;
  font-weight: 700;
  margin-bottom: 8px;
}

.led-numbers {
  font-family: 'Courier New', monospace;
  font-size: 3.5rem;
  font-weight: 700;
  color: var(--status-yellow);
  text-shadow: 0 0 10px rgba(241, 196, 15, 0.5);
  margin-bottom: 8px;
}

.led-numbers.in-range {
  color: var(--status-green);
  text-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
}

/* 3 mức đúng VBA CheckRange: vàng "chưa đủ" (insufficient) / xanh "đạt" (in-range) /
   đỏ "vượt" (over-range) — trước đây gộp mọi trường hợp ngoài dung sai vào 1 màu đỏ
   duy nhất "out-of-range", không phân biệt được "cứ thêm tiếp" khỏi "vượt, cần xử lý". */
.led-numbers.insufficient {
  color: var(--status-yellow);
  text-shadow: 0 0 10px rgba(241, 196, 15, 0.5);
}

.led-numbers.over-range {
  color: var(--status-red);
  text-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
}

.led-status-text {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-muted);
}

.led-status-text.in-range { color: var(--status-green); }
.led-status-text.insufficient { color: var(--status-yellow); }
.led-status-text.over-range { color: var(--status-red); }

.stable-indicator {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.stable-indicator.is-stable { color: var(--status-green); }
.stable-indicator.is-unstable { color: var(--status-yellow); }

/* Simulator Control */
.scale-simulator-box {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: 12px;
}

.header-sim {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  align-items: center;
}

.sim-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.sim-slider {
  flex: 1;
}

.sim-num-input {
  width: 90px;
  padding: 4px 8px;
  font-size: 13px;
}

/* Tolerance / Target specifications */
.tolerance-details-box {
  background-color: var(--bg-main);
  padding: 16px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.target-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.meta-row {
  display: flex;
  justify-content: space-between;
}

.tare-info-box {
  padding: 8px 10px;
  background-color: var(--bg-sidebar);
  border: 1px dashed var(--border-divider);
  border-radius: var(--radius-md);
  text-align: center;
}

.weigh-confirm-btn {
  width: 100%;
  padding: 14px;
  font-size: 1.1rem;
  background-color: var(--status-green);
  border: 1px solid var(--status-green);
  color: #fff;
  border-radius: var(--radius-md);
  font-weight: 700;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  transition: all 0.2s;
}

.weigh-confirm-btn:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.weigh-confirm-btn:disabled {
  background-color: var(--border-divider);
  border-color: var(--border-divider);
  color: var(--text-muted);
  cursor: not-allowed;
}

.override-box {
  background-color: rgba(231, 76, 60, 0.05);
  border: 1px dashed var(--status-red);
  padding: 12px;
  border-radius: var(--radius-md);
}

.override-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}

/* Material QR code Label block */
.printed-label-box {
  background-color: var(--bg-sidebar);
  border: 2px dashed var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-xl);
  text-align: center;
}

.label-preview {
  display: flex;
  background-color: #fff;
  color: #000;
  border-radius: var(--radius-md);
  padding: 16px;
  align-items: center;
  gap: 16px;
  text-align: left;
  border: 1px solid #ddd;
}

.qr-placeholder {
  font-size: 3rem;
}

.label-info {
  font-size: 12px;
  line-height: 1.4;
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

/* Checker (tra cứu bán thành phẩm) modal */
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
