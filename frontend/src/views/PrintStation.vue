<template>
  <div class="print-station-container">
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

    <!-- Station Banner — máy in hiện tại LUÔN suy ra từ danh sách Agent vừa báo cáo
         (nguồn xác định thật), không còn dựa vào "đã gán trong DB hay chưa" để quyết
         định hiện đèn xanh/đỏ (bug 2026-07-18: Windows đã có máy in mặc định nhưng
         banner vẫn hiện "Chưa gán" vì chưa ai bấm Lưu). -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">PRINT STATION</span>
        <template v-if="currentWorkstation">
          <h2>{{ currentWorkstation.name }}</h2>
          <p class="text-muted font-sm">Mã trạm: <code>{{ currentWorkstation.code }}</code> | Vị trí: {{ currentWorkstation.location }}</p>
        </template>
        <template v-else>
          <h2>Chưa đăng ký trạm (tài khoản Admin)</h2>
          <div class="text-muted font-sm admin-station-picker">
            Xem/gán máy in cho trạm:
            <select
              class="form-select select-sm"
              :value="printerStationOverride?.code || ''"
              @change="onStationOverrideChange(($event.target as HTMLSelectElement).value)"
            >
              <option v-for="s in stationOptions" :key="s.code" :value="s.code">{{ s.name }} ({{ s.code }})</option>
            </select>
          </div>
        </template>
      </div>
      <div class="banner-right">
        <div class="dev-badge">
          <span class="dot-pulse" :class="resolvedPrinter ? 'dot-green' : 'dot-red'"></span>
          <span>Máy in hiện tại: {{ resolvedPrinter || 'Không phát hiện máy in đã cài' }}</span>
        </div>
        <button @click="fetchInstalledPrinters" class="btn btn-secondary btn-sm ml-2" :disabled="loadingInstalledPrinters">🔄 Làm mới</button>
        <button @click="showPrinterConfig = !showPrinterConfig" class="btn btn-secondary btn-sm ml-2">
          ⚙️ Đổi máy in
        </button>
      </div>
    </div>

    <!-- Cảnh báo — CHỈ xuất hiện trong các trường hợp thật sự cần chú ý (Agent chưa báo
         cáo, không có máy in nào, hoặc máy in đã lưu không còn tồn tại). Không bao giờ
         khóa màn hình vận hành chỉ vì chưa gán máy in trong DB. -->
    <div v-if="printerWarning" class="card error-card mb-4" style="color:var(--status-yellow); border-color:var(--status-yellow-border); background:var(--status-yellow-bg); padding:12px 16px; border-radius:8px;">
      ⚠️ {{ printerWarning }}
    </div>

    <div v-if="isVirtualPrinter(resolvedPrinter)" class="card error-card mb-4" style="color:var(--status-blue); border-color:var(--status-blue-border); background:var(--status-blue-bg); padding:12px 16px; border-radius:8px;">
      ℹ️ "<strong>{{ resolvedPrinter }}</strong>" là máy in ảo của Windows (PDF/OneNote...), không hiểu được lệnh in tem gốc (TSPL) —
      tem in ra sẽ lỗi/không đọc được (vd "Failed to load PDF document"). Chỉ dùng máy in này để kiểm tra hệ thống có gửi đúng lệnh
      đến đúng trạm hay không; cần máy in tem thật (TSC/Zebra) để xem đúng nội dung/layout tem.
    </div>

    <!-- Chọn máy in — CHỈ chọn từ danh sách Agent thật sự phát hiện trên Windows, không
         nhập tay IP/driver/cổng máy in (yêu cầu 2026-07-18: Local Agent là nguồn xác
         định duy nhất). -->
    <div v-if="showPrinterConfig" class="section card-sec printer-config-panel mb-4">
      <h4>⚙️ Đổi máy in cho trạm này</h4>

      <div v-if="loadingInstalledPrinters" class="text-muted font-sm">Đang tải danh sách máy in đã cài trên máy này...</div>

      <div v-else-if="installedPrinters.length" class="printer-config-form">
        <div class="form-group flex-2">
          <label>Máy in đã cài trên máy này</label>
          <select v-model="selectedPrinterName" class="form-select">
            <option v-for="p in installedPrinters" :key="p" :value="p">
              {{ p }}{{ p === defaultInstalledPrinter ? ' (mặc định hệ thống)' : '' }}{{ isVirtualPrinter(p) ? ' — ⚠️ máy in ảo, không đọc được tem thật' : '' }}
            </option>
          </select>
        </div>
        <button @click="savePreferredPrinter" class="btn btn-primary" :disabled="!selectedPrinterName || savingPrinterConfig">
          {{ savingPrinterConfig ? 'Đang lưu...' : 'Dùng máy in này' }}
        </button>
      </div>

      <div v-else class="text-muted font-sm">
        <p>Không phát hiện máy in nào đã cài trên máy tính này.</p>
        <p>Kiểm tra: Local Agent (DF Agent) có đang chạy trên máy này không, và Windows đã cài ít nhất 1 máy in (Cài đặt → Thiết bị → Máy in &amp; máy quét) chưa.</p>
        <button @click="fetchInstalledPrinters" class="btn btn-secondary btn-sm mt-2">🔄 Làm mới danh sách</button>
      </div>

      <p class="text-muted font-sm mt-2">Lưu ý: tem in ra dùng đúng mẫu (layout) đã cấu hình sẵn trên chính máy in vật lý này — web chỉ gửi dữ liệu QR, không tự vẽ mẫu tem.</p>
    </div>

    <!-- Hàng chờ in tem mới — port đúng TO_SEND.frm/LoadGrid (VBA "3.DF028... jit qr
         sending"): đơn vừa được Duyệt ở máy Nhập đơn xuất hiện ở đây, đúng dữ liệu
         tbl_tosend (color/code/machine/tank). VBA tự làm mới mỗi 15s bằng
         Application.OnTime; web dùng interval ngắn hơn vì không cần tiết kiệm tài
         nguyên như Excel. Ghi chú theo yêu cầu: tem in ra dùng ĐÚNG mẫu đã cấu hình
         sẵn trên máy in vật lý (driver/TSPL template cục bộ) — web chỉ gửi dữ liệu
         QR (DYE/CHEM/PROCESS-EXTRA-FB tuỳ B24), không tự vẽ layout tem. -->
    <section class="section card-sec print-queue-panel mb-4" v-if="!label">
      <div class="queue-header">
        <h3>🖨️ Hàng chờ in tem mới ({{ pendingDispatches.length }})</h3>
        <span class="text-muted font-sm">Tự làm mới mỗi 8 giây — đơn xuất hiện ngay sau khi được Duyệt ở máy Nhập đơn.</span>
      </div>

      <p v-if="confirmError" class="text-error mt-2">❌ {{ confirmError }}</p>

      <div class="table-container-fixed mt-3" v-if="pendingDispatches.length">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mã Lô</th>
              <th>Màu</th>
              <th>Mã hàng</th>
              <th>Máy</th>
              <th>Thùng</th>
              <th>Trạng thái</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in pendingDispatches" :key="d.id" class="row-not-printed">
              <td class="highlight-code">{{ d.batch?.legacy_batch_id }}</td>
              <td>{{ d.batch?.color }}</td>
              <td>{{ d.batch?.product_code }}</td>
              <td><span class="machine-tag">{{ d.batch?.machine?.code || 'N/A' }}</span></td>
              <td>{{ d.batch?.tank?.code || '-' }}</td>
              <td>
                <span class="badge badge-red">Chưa in</span>
              </td>
              <td class="actions-cell">
                <button
                  @click="confirmAndPrint(d)"
                  class="btn btn-primary btn-sm"
                  :disabled="confirmingId === d.id"
                >
                  {{ confirmingId === d.id ? 'Đang xử lý...' : '⚡ In nhanh' }}
                </button>
                <button
                  @click="openPrintPreview(d)"
                  class="btn btn-secondary btn-sm"
                  :disabled="confirmingId === d.id"
                >
                  👁️ Xem trước
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-muted text-center mt-3">Không có đơn nào đang chờ in.</p>
    </section>

    <!-- Xem trước tem trước khi in — layout giống scaleform.frm (VBA gốc): thông tin đầu
         mẻ + QR + 2 bảng RACK/MÃ/KHỐI LƯỢNG (dye/chem). QR ở đây render RIÊNG ở trình
         duyệt để xem trực quan — không phải nội dung TSPL thật sẽ gửi xuống máy in (máy
         in dùng đúng mẫu cấu hình sẵn trên phần cứng, xem ghi chú đầu trang). Cho phép
         đổi máy in CHỈ cho lần in này, không ghi đè máy in mặc định đã gán cho trạm. -->
    <div v-if="previewDispatch" class="modal-overlay" @click.self="closePrintPreview">
      <div class="preview-modal-card">
        <div class="preview-modal-header">
          <h4>👁️ Xem trước tem — {{ previewDispatch.batch?.legacy_batch_id }}</h4>
          <button class="close-btn" @click="closePrintPreview">&times;</button>
        </div>

        <div class="preview-body">
          <div class="preview-header-grid">
            <div><span class="label">Mã màu:</span> <span class="val">{{ previewDispatch.batch?.color }}</span></div>
            <div><span class="label">Mã hàng:</span> <span class="val">{{ previewDispatch.batch?.product_code }}</span></div>
            <div><span class="label">Máy nhuộm:</span> <span class="machine-tag">{{ previewDispatch.batch?.machine?.code || 'N/A' }}</span></div>
            <div><span class="label">Thùng:</span> <span class="val">{{ previewDispatch.batch?.tank?.code || 'Chưa gán' }}</span></div>
            <div><span class="label">Mức nước:</span> <span class="val">{{ previewDispatch.batch?.level_code || 'Mặc định' }}</span></div>
          </div>

          <div class="preview-qr-row">
            <canvas ref="previewQrCanvas" class="preview-qr-canvas"></canvas>
            <p class="text-muted font-xs">
              QR minh họa nội dung — máy in thật dùng đúng mẫu TSPL cấu hình sẵn trên máy in vật lý, có thể khác cách trình bày.
            </p>
          </div>

          <div class="rack-tables-row">
            <div class="rack-table-col">
              <label class="rack-table-title">🧵 Thuốc nhuộm (DYE)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ THUỐC NHUỘM</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in previewDyeLines" :key="'pdye-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="rack-table-col">
              <label class="rack-table-title">🧪 Hóa chất (CHEM)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ HÓA CHẤT</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in previewChemLines" :key="'pchem-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="form-group mt-3">
            <label>Máy in (chỉ cho lần in này)</label>
            <select v-model="previewSelectedPrinter" class="form-select">
              <option v-for="p in installedPrinters" :key="p" :value="p">
                {{ p }}{{ p === resolvedPrinter ? ' (mặc định trạm)' : '' }}
              </option>
            </select>
          </div>
        </div>

        <div class="preview-modal-actions">
          <button class="btn btn-secondary" @click="closePrintPreview">Hủy</button>
          <button class="btn btn-secondary" @click="printPreviewViaBrowser">
            🖥️ In qua trình duyệt
          </button>
          <button
            class="btn btn-primary"
            :disabled="confirmingId === previewDispatch.id"
            @click="confirmPrintFromPreview"
          >
            {{ confirmingId === previewDispatch.id ? 'Đang gửi lệnh in...' : '🖨️ In tem' }}
          </button>
        </div>
        <p class="text-muted font-xs mt-2">
          "In qua trình duyệt" mở trang in riêng, dùng hộp thoại in của Windows/trình duyệt — chọn được bất kỳ máy in nào đã cài, không cần qua Local Agent.
        </p>
        <p v-if="!previewSelectedPrinter" class="text-muted font-xs mt-2 preview-no-printer-hint">
          ⚠️ Chưa phát hiện máy in nào từ Local Agent — lệnh in vẫn được tạo và xếp hàng chờ, máy in sẽ dùng mặc định của trạm khi Agent kết nối lại.
        </p>
      </div>
    </div>

    <!-- Lịch sử in tem — tương đương tbl_sentlog VBA. Cho phép người vận hành nhìn
         lại mã hàng nào đã in rồi (khớp yêu cầu 2026-07-18: giữ lịch sử bên dưới). -->
    <section class="section card-sec print-history-panel mb-4" v-if="!label">
      <div class="queue-header">
        <h3>📜 Lịch sử in tem gần đây</h3>
        <button @click="fetchPrintHistory" class="btn btn-secondary btn-sm">🔄 Làm mới</button>
      </div>

      <PrintJobHistoryTable :rows="printHistory" :default-printer="resolvedPrinter" :station-code="currentWorkstation?.code" @refresh="fetchPrintHistory" class="mt-3" />
    </section>

    <!-- Wait / Scan screen -->
    <div v-if="!label" class="scanning-wait-screen card-sec text-center">
      <div class="scanner-anim-icon">🏷️</div>
      <h3>QUÉT MÃ QR TRÊN TEM ĐỂ IN LẠI</h3>
      <p class="text-muted">Dùng khi cần in lại tem bị rách/mất/mờ, không phụ thuộc vào trạm cân đã tạo tem.</p>

      <div class="manual-entry-widget mt-5">
        <h4>⌨️ Tìm theo mã Lô (khi máy quét lỗi)</h4>
        <div class="manual-input-row">
          <input
            v-model="manualQuery"
            @keyup.enter="searchManual"
            type="text"
            class="form-input manual-input"
            placeholder="Nhập mã Lô, ví dụ: B260716"
          />
          <button 
            @click="searchManual" 
            class="btn btn-secondary" 
            :disabled="!manualQuery.trim() || searching || (isImpersonating && remoteMode === 'VIEW_ONLY')"
          >
            {{ searching ? 'Đang tìm...' : 'Tìm' }}
          </button>
        </div>

        <div v-if="manualError" class="manual-error mt-2">{{ manualError }}</div>

        <div v-if="manualResults.length" class="manual-results mt-3">
          <p class="text-muted font-sm">Tìm thấy {{ manualResults.length }} tem khớp — chọn đúng tem:</p>
          <button
            v-for="l in manualResults"
            :key="l.id"
            class="manual-result-item"
            @click="!isImpersonating || remoteMode !== 'VIEW_ONLY' ? selectManualResult(l) : null"
            :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
          >
            <strong>{{ l.batch?.legacy_batch_id }}</strong> — {{ l.material_type }} — {{ (l.weight / 1000).toFixed(2) }} kg
            <span class="text-muted"> ({{ formatTime(l.created_at) }})</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Label info + reprint screen -->
    <div v-else class="section card-sec label-detail">
      <div class="meta-badge-row mb-3">
        <span class="badge badge-blue">{{ label.material_type }}</span>
        <span v-if="label.reprint_count > 0" class="badge badge-yellow">Đã in lại {{ label.reprint_count }} lần</span>
      </div>

      <h3>Lô nhuộm: <span class="text-glow-blue">{{ label.batch?.legacy_batch_id }}</span></h3>

      <div class="details-grid mt-4">
        <div><span class="label">Mã màu:</span> <span class="val">{{ label.batch?.color }}</span></div>
        <div><span class="label">Máy nhuộm:</span> <span class="machine-tag">{{ label.batch?.machine?.code || 'N/A' }}</span></div>
        <div><span class="label">Khối lượng:</span> <span class="val bold-text">{{ (label.weight / 1000).toFixed(3) }} kg</span></div>
        <div><span class="label">Thời gian tạo tem:</span> <span class="val">{{ formatTime(label.created_at) }}</span></div>
      </div>

      <div class="job-items-sec mt-4" v-if="label.job?.items?.length">
        <h4>🧪 Thành phần đã cân:</h4>
        <div class="sequence-list">
          <div v-for="item in label.job.items" :key="item.id" class="seq-card done">
            <div class="seq-content">
              <div class="item-name">{{ item.material?.name || item.material_code }}</div>
            </div>
            <div class="seq-weights">
              <div class="actual-w text-success">{{ item.actual_weight?.toFixed(2) }} g</div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-group mt-4">
        <label>Lý do in lại (bắt buộc)</label>
        <input 
          v-model="reprintReason" 
          type="text" 
          class="form-input" 
          placeholder="Ví dụ: Tem bị rách, mất tem gốc..." 
          :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
        />
      </div>

      <div v-if="reprintError" class="manual-error mt-2">{{ reprintError }}</div>
      <div v-if="reprintSuccess" class="confirm-success mt-2">{{ reprintSuccess }}</div>

      <div class="flex-row gap-3 mt-4">
        <button class="btn btn-secondary flex-1" @click="resetScan">Quét tem khác</button>
        <button
          class="btn btn-primary flex-2"
          @click="submitReprint"
          :disabled="reprinting || reprintReason.trim().length < 5 || (isImpersonating && remoteMode === 'VIEW_ONLY')"
        >
          {{ reprinting ? 'Đang gửi lệnh in...' : '🖨️ In lại tem' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import QRCode from 'qrcode';
import scannerService from '../services/scanner';
import { currentWorkstation } from '../services/workstation';
import { useAuthStore } from '../stores/auth';
import PrintJobHistoryTable from '../components/PrintJobHistoryTable.vue';
import { parseRackLines } from '../utils/rackParser';

const route = useRoute();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);

// Yêu cầu 2026-07-22: tài khoản ADMIN không gắn cứng 1 trạm nào (currentWorkstation =
// null, mở được mọi màn hình) — nếu chỉ dựa vào currentWorkstation để biết Local Agent
// nào vừa báo cáo máy in thì admin sẽ KHÔNG BAO GIỜ thấy được máy in dù Agent đã báo cáo
// thành công lên đúng trạm thật (bug phát hiện khi test MSI: DB có dữ liệu nhưng trang
// luôn báo "chưa nhận được" vì fetchInstalledPrinters() return sớm). Thêm lựa chọn trạm
// thủ công CHỈ dùng khi không có currentWorkstation, để admin tự chỉ định đang xem/quản
// lý máy in của trạm nào.
const stationOptions = ref<{ id: number; code: string; name: string }[]>([]);
const printerStationOverride = ref<{ id: number; code: string; name: string } | null>(null);
const effectiveStationCode = computed(() => currentWorkstation.value?.code || printerStationOverride.value?.code || null);
const effectiveStationId = computed(() => currentWorkstation.value?.id || printerStationOverride.value?.id || null);

async function fetchStationOptions() {
  try {
    const res = await axios.get('/api/workstations');
    const list = res.data.data || res.data || [];
    stationOptions.value = list.filter((w: any) => w.type === 'QR_LABEL_PRINTING' || w.workstation_type === 'QR_LABEL_PRINTING');
    if (!printerStationOverride.value && stationOptions.value.length) {
      printerStationOverride.value = stationOptions.value[0];
    }
  } catch (err) {
    console.error('Error fetching station options:', err);
  }
}

function onStationOverrideChange(code: string) {
  printerStationOverride.value = stationOptions.value.find(s => s.code === code) || null;
  fetchInstalledPrinters();
}

// Chọn máy in — Local Agent (PrinterDiscovery.cs) là nguồn xác định duy nhất. Không còn
// cấu hình thủ công IP/driver/cổng (yêu cầu 2026-07-18). Thứ tự ưu tiên khi tự động
// chọn: (1) máy in đã gán riêng cho trạm này VÀ còn tồn tại trong danh sách Agent vừa
// báo cáo, (2) máy in mặc định của Windows, (3) máy in đầu tiên trong danh sách.
const showPrinterConfig = ref(false);
const savingPrinterConfig = ref(false);
const selectedPrinterName = ref('');

const installedPrinters = ref<string[]>([]);
const defaultInstalledPrinter = ref<string | null>(null);
const assignedPrinterName = ref<string | null>(null);
const loadingInstalledPrinters = ref(false);
const agentEverReported = ref(false);

const resolvedPrinter = computed(() => {
  if (assignedPrinterName.value && installedPrinters.value.includes(assignedPrinterName.value)) {
    return assignedPrinterName.value;
  }
  if (defaultInstalledPrinter.value && installedPrinters.value.includes(defaultInstalledPrinter.value)) {
    return defaultInstalledPrinter.value;
  }
  return installedPrinters.value[0] || null;
});

const printerWarning = computed(() => {
  if (!agentEverReported.value) {
    return 'Chưa nhận được dữ liệu máy in từ Local Agent trên máy này — kiểm tra Agent (DF Agent) có đang chạy không.';
  }
  if (installedPrinters.value.length === 0) {
    return 'Không phát hiện máy in nào đã cài trên máy tính này. Cài máy in trên Windows rồi bấm Làm mới.';
  }
  if (assignedPrinterName.value && !installedPrinters.value.includes(assignedPrinterName.value)) {
    return `Máy in đã lưu trước đó ("${assignedPrinterName.value}") không còn tồn tại trên máy này — đã tự chuyển sang máy in mặc định.`;
  }
  return '';
});

// Máy in ảo của Windows (PDF/OneNote/Fax/XPS...) KHÔNG hiểu lệnh in tem gốc (TSPL) —
// Agent gửi thẳng byte thô (RAW datatype qua winspool.Drv, xem LabelPrinter.cs) cho máy
// in tem thật (TSC/Zebra) hiểu được, nhưng driver PDF/OneNote lại mong nhận dữ liệu đã
// được Windows GDI vẽ sẵn (EMF), không phải văn bản lệnh thô -> file xuất ra hỏng, mở
// lên báo "Failed to load PDF document." (đúng lỗi 2026-07-18). Đây không phải bug có
// thể vá bằng code — chỉ có thể dùng máy in ảo để kiểm tra HỆ THỐNG có gửi đúng lệnh
// đến đúng máy hay không, không dùng để xem đúng nội dung/layout tem thật.
const VIRTUAL_PRINTER_PATTERNS = /pdf|onenote|xps|fax|opendocument/i;
function isVirtualPrinter(name: string | null): boolean {
  return !!name && VIRTUAL_PRINTER_PATTERNS.test(name);
}

async function fetchInstalledPrinters() {
  if (!effectiveStationCode.value) return;
  loadingInstalledPrinters.value = true;
  try {
    const res = await axios.get('/api/workstations');
    const list = res.data.data || res.data;
    const match = list.find((w: any) => w.code === effectiveStationCode.value);
    const config = match?.configuration || {};
    agentEverReported.value = Array.isArray(config.available_printers);
    installedPrinters.value = config.available_printers || [];
    defaultInstalledPrinter.value = config.default_printer || null;
    assignedPrinterName.value = match?.assigned_printer_device_id || null;
    selectedPrinterName.value = resolvedPrinter.value || '';
  } catch (err) {
    console.error('Error fetching installed printers:', err);
  } finally {
    loadingInstalledPrinters.value = false;
  }
}

const savePreferredPrinter = async () => {
  if (!effectiveStationId.value || !selectedPrinterName.value) return;
  savingPrinterConfig.value = true;
  try {
    await axios.put(`/api/workstations/${effectiveStationId.value}/local-device-config`, {
      printer_device_id: selectedPrinterName.value,
    });
    assignedPrinterName.value = selectedPrinterName.value;
    showPrinterConfig.value = false;
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không thể lưu lựa chọn máy in.');
  } finally {
    savingPrinterConfig.value = false;
  }
};
const remoteMode = ref<'VIEW_ONLY' | 'REMOTE_OPERATE'>('VIEW_ONLY');

const remoteModeClass = computed(() => {
  return remoteMode.value === 'VIEW_ONLY' ? 'mode-view-only' : 'mode-remote-operate';
});

function getRequestConfig() {
  const config: { headers: Record<string, string> } = { headers: {} };
  if (isImpersonating.value && remoteMode.value === 'REMOTE_OPERATE') {
    config.headers['X-Remote-Operation'] = 'true';
    config.headers['X-Target-Workstation'] = String(targetWsId.value || '');
    config.headers['X-Remote-Reason'] = 'Admin remote reprint action';
  }
  return config;
}

const label = ref<any>(null);

// Hàng chờ in tem mới (đúng TO_SEND.frm) — tách biệt hoàn toàn với luồng "in lại tem
// cân xong" (material_labels) phía dưới, đây là tem QR sinh ra NGAY sau khi Duyệt đơn,
// dùng để quét ở trạm cân + Color Service (xem QrPayloadService).
const pendingDispatches = ref<any[]>([]);
const confirmingId = ref<string | null>(null);
const confirmError = ref('');
let pollTimer: ReturnType<typeof setInterval> | null = null;

async function fetchPendingDispatches() {
  try {
    const res = await axios.get('/api/machine-dispatches');
    pendingDispatches.value = Array.isArray(res.data) ? res.data : (res.data.data || []);
  } catch (err) {
    console.error('Error fetching print queue:', err);
  }
}

// Lịch sử in tem (tương đương tbl_sentlog VBA) — đơn đã CONFIRMED, kèm trạng thái
// PrintJob thật (PENDING/PRINTED/FAILED qua Local Agent ack).
const printHistory = ref<any[]>([]);

async function fetchPrintHistory() {
  try {
    const res = await axios.get('/api/machine-dispatches/history');
    printHistory.value = res.data.data || [];
  } catch (err) {
    console.error('Error fetching print history:', err);
  }
}

async function confirmAndPrint(dispatch: any, printerOverride?: string) {
  confirmError.value = '';
  confirmingId.value = dispatch.id;
  try {
    await axios.post(`/api/machine-dispatches/${dispatch.id}/confirm`, {
      idempotency_key: `print_${dispatch.id}_${Date.now()}`,
      workstation_id: effectiveStationCode.value || undefined,
      // Gửi đúng tên máy in Windows đã resolve (ưu tiên: chọn tay lúc xem trước > đã gán
      // > mặc định hệ thống > máy đầu tiên) — Agent dùng tên này in qua Win32 Spooler
      // theo tên (LabelPrinter.cs::PrintViaUsb), không phải địa chỉ mạng.
      printer_address: printerOverride || resolvedPrinter.value || undefined,
      printer_type: 'USB',
    }, getRequestConfig());
    scannerService.playBeep(1800, 150);
    await Promise.all([fetchPendingDispatches(), fetchPrintHistory()]);
  } catch (err: any) {
    confirmError.value = err.response?.data?.message || 'Không thể tạo lệnh in cho đơn này.';
  } finally {
    confirmingId.value = null;
  }
}

// Xem trước tem — dựng lại QR minh họa + bảng RACK/MÃ/KHỐI LƯỢNG từ chính dữ liệu
// raw_qr_dye/raw_qr_chemical đã lưu trên batch (KHÔNG gọi endpoint confirm thật, tránh
// tạo PrintJob/RoutingDecision chỉ để xem trước). Cho phép chọn máy in riêng cho lần in
// này trước khi thật sự gửi lệnh.
const previewDispatch = ref<any>(null);
const previewSelectedPrinter = ref('');
const previewQrCanvas = ref<HTMLCanvasElement | null>(null);

const previewDyeLines = computed(() => parseRackLines(previewDispatch.value?.batch?.raw_qr_dye));
const previewChemLines = computed(() => parseRackLines(previewDispatch.value?.batch?.raw_qr_chemical));

function openPrintPreview(dispatch: any) {
  previewDispatch.value = dispatch;
  previewSelectedPrinter.value = resolvedPrinter.value || installedPrinters.value[0] || '';
}

function closePrintPreview() {
  previewDispatch.value = null;
}

async function confirmPrintFromPreview() {
  if (!previewDispatch.value) return;
  await confirmAndPrint(previewDispatch.value, previewSelectedPrinter.value);
  if (!confirmError.value) closePrintPreview();
}

async function renderPreviewQr() {
  await nextTick();
  const canvas = previewQrCanvas.value;
  const d = previewDispatch.value;
  if (!canvas || !d) return;
  const b = d.batch || {};
  const qrText = `#${b.color || ''}-${b.product_code || ''}-${b.machine?.code || ''}-${b.level_code || ''}-dye-${b.raw_qr_dye || ''}-chem-${b.raw_qr_chemical || ''}`;
  try {
    await QRCode.toCanvas(canvas, qrText, { width: 160, margin: 1 });
  } catch (err) {
    console.error('Preview QR render failed:', err);
  }
}

watch(previewDispatch, (val) => {
  if (val) renderPreviewQr();
});

// Port ĐÚNG y hệt WarehouseRoutingService::calculateRouting (backend) — đã đối chiếu
// xác nhận khớp 100% với code thật Mod_printslip.bas (D1 zone) ngày 2026-07-22. Chỉ
// dùng để XEM TRƯỚC ở màn hình "In qua trình duyệt" (dispatch chưa confirm nên chưa
// có RoutingDecision thật) — kết quả này KHÔNG được lưu, không thay thế tính toán thật
// lúc bấm "In tem" (ConfirmDispatchService/WarehouseRoutingService).
function numBetween(v: number, min: number, max: number): boolean {
  return v >= min && v <= max;
}

/** Port "VD" & Format(Val(Mid(s,3)),"000") — chuẩn hóa mã máy về 3 chữ số (VD4 -> VD004). */
function normalizeVdCode(code: string): string {
  const c = (code || '').toUpperCase().trim();
  if (c.startsWith('VD')) {
    const num = parseInt(c.slice(2), 10) || 0;
    return 'VD' + String(num).padStart(3, '0');
  }
  return c;
}

/** Port Format(Now,"yyyymmddhhmm") / Format(Now,"hhmm") — chỉ 2 kiểu Mod_printslip.bas dùng. */
function nowStamp(pattern: 'yyyymmddhhmm' | 'hhmm'): string {
  const n = new Date();
  const p2 = (v: number) => String(v).padStart(2, '0');
  const hhmm = p2(n.getHours()) + p2(n.getMinutes());
  if (pattern === 'hhmm') return hhmm;
  return `${n.getFullYear()}${p2(n.getMonth() + 1)}${p2(n.getDate())}${hhmm}`;
}

function calculateRoutingPreview(machineCode: string, tankCode: string, levelCode: string) {
  const machine = (machineCode || '').toUpperCase().trim();
  const tank = (tankCode || '').toUpperCase().trim();
  const m = /^VD(\d+)$/.exec(machine);
  const machineNum = m ? parseInt(m[1], 10) : 0;

  let b24: string | null = null;
  if (numBetween(machineNum, 6, 13) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT CAO, MAY E13, MAY A11';
  } else if ((machineNum === 17 || machineNum === 18) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT CAO, MAY E12, MAY A11';
  } else if ((machineNum === 17 || machineNum === 18) && (tank === '3C' || tank === '4D')) {
    b24 = levelCode == '50' ? 'PHA TAY, HOA CHAT DLG' : 'THUNG SAT CAO, MAY E12, MAY DLG';
  } else if ((numBetween(machineNum, 1, 5) || numBetween(machineNum, 14, 16)) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY A11';
  } else if (numBetween(machineNum, 1, 16) && (tank === '3C' || tank === '4D')) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY DLG';
  }

  let d1 = '';
  if (numBetween(machineNum, 6, 13) && (tank === '1A' || tank === '2B')) {
    d1 = 'E13';
  } else if ((machineNum === 17 || machineNum === 18) && ['1A', '2B', '3C', '4D'].includes(tank)) {
    d1 = 'E12';
  } else if (numBetween(machineNum, 1, 5) && (tank === '1A' || tank === '2B')) {
    d1 = 'JIT3';
  } else if (numBetween(machineNum, 1, 9) && (tank === '3C' || tank === '4D')) {
    d1 = 'JIT2';
  } else if (numBetween(machineNum, 14, 16) && (tank === '1A' || tank === '2B')) {
    d1 = 'JIT4';
  } else if (numBetween(machineNum, 10, 16) && (tank === '3C' || tank === '4D')) {
    d1 = 'JIT1';
  }

  let mode = 'FB';
  if (b24 !== null) {
    if (b24.includes('MAY JIT')) mode = 'PROCESS';
    else if (b24.includes('THUNG SAT CAO')) mode = 'EXTRA';
  }
  if (mode === 'FB') {
    b24 = 'PHU BAN-LAY LIEU COPOWER';
  }

  return { d1Zone: d1, b24Route: b24 || '', mode };
}

// In qua trình duyệt — mở tab mới dựng lại tem dạng HTML (không qua TSPL/Local Agent),
// gọi window.print() để dùng hộp thoại in gốc của Windows, chọn được bất kỳ máy in nào
// đã cài. Bố cục dưới đây LẤY ĐÚNG toạ độ đo thật từ sheet "DF_WEIGHING_SLIP" gốc
// (file "Copy of Copy of DF002 no formulas..." không khóa VBA, 2026-07-21) — quy đổi
// dot TSPL (backend QrPayloadService::buildTsplLabel70x100) sang mm (chia 8, vì 203dpi
// = 8 dot/mm) để khớp 1:1 với tem in thật, không phải bố cục tự vẽ riêng nữa. Dispatch
// CHƯA confirm nên chưa có RoutingDecision LƯU THẬT trong DB — nhưng khu vực D1/chuỗi
// B24 tự tính lại ngay tại đây bằng calculateRoutingPreview() (đã đối chiếu khớp 100%
// với backend + code VBA gốc), nên tem xem trước vẫn hiển thị ĐÚNG giá trị thật.
async function printPreviewViaBrowser() {
  const d = previewDispatch.value;
  if (!d) return;
  const b = d.batch || {};

  const routing = calculateRoutingPreview(b.machine?.code || '', b.tank?.code || '', b.level_code || '');

  const dyeQrText = `#${b.color || ''}-${b.product_code || ''}-${b.machine?.code || ''}-${b.level_code || ''}-${b.raw_qr_dye || ''}`;

  // qrChem — port đúng backend QrPayloadService::buildChemPayload: KHÔNG nhét thẳng
  // raw_qr_chemical thô, mã thùng chỉ lấy ký tự đầu, và mỗi trường cách nhau 1 dòng
  // trống (yêu cầu 2026-07-22, lệch có chủ đích so với VBA gốc — khớp tem thật đang
  // dùng). "#" cuối nối TRỰC TIẾP vào dòng khối lượng cuối, không có dòng trống trước nó.
  const chemRndPreview = 1 + Math.floor(Math.random() * 9);
  const chemParts = [
    normalizeVdCode(b.machine?.code || ''),
    (b.tank?.code || '').toUpperCase().trim().charAt(0),
    `#${b.color || ''}-${b.product_code || ''}`,
    String(chemRndPreview),
    b.level_code || '',
  ];
  previewChemLines.value.forEach(r => {
    chemParts.push(r.code);
    chemParts.push(String(r.weight).replace(',', '.'));
  });
  const chemQrText = chemParts.join('\n\n') + '#';

  // QR chế độ (PROCESS/EXTRA/FB) — port đúng định dạng qrProcess/qrExtra/qrFB trong
  // Mod_printslip.bas gốc, đặt ở G1:H1. Không có totalD (tổng khối lượng dye) sẵn ở màn
  // hình xem trước nên tính lại từ chính bảng dye đang hiển thị.
  let modeQrText = '';
  if (routing.mode === 'PROCESS') {
    // Yêu cầu 2026-07-22: dòng trống xen giữa 3 phần (khác VBA gốc chỉ 1 vbCrLf) — đối
    // chiếu tem thật đang dùng, chỉ áp dụng cho mode PROCESS (xem QrPayloadService::
    // buildProcessPayload backend, cùng lý do).
    const newLevel = (b.tank?.code || '').toUpperCase() === '1A' ? '450' : (b.tank?.code || '').toUpperCase() === '2B' ? '250' : (b.level_code || '');
    modeQrText = `${b.color || ''}-${b.product_code || ''} ${nowStamp('yyyymmddhhmm')}\n\n${b.machine?.code || ''}-${b.tank?.code || ''}-${newLevel}\n\nNylon Dyes`;
  } else if (routing.mode === 'EXTRA') {
    const totalD = previewDyeLines.value.reduce((sum, r) => sum + (parseFloat(r.weight) || 0), 0);
    const rnd = 1 + Math.floor(Math.random() * 9);
    modeQrText = `${b.machine?.code || ''}\n${(b.tank?.code || '').charAt(0)}\n${b.color || ''} ${b.product_code || ''}\n${rnd}\n${b.level_code || ''}\n1\n${totalD}`;
  } else {
    modeQrText = `${b.color || ''}-${b.product_code || ''} ${nowStamp('hhmm')}`;
  }

  let dyeQrDataUrl = '';
  let chemQrDataUrl = '';
  let modeQrDataUrl = '';
  try {
    dyeQrDataUrl = await QRCode.toDataURL(dyeQrText, { width: 240, margin: 0 });
    chemQrDataUrl = await QRCode.toDataURL(chemQrText, { width: 240, margin: 0 });
    modeQrDataUrl = await QRCode.toDataURL(modeQrText, { width: 200, margin: 0 });
  } catch (err) {
    console.error('Failed to render QR for browser print:', err);
  }

  // Toạ độ — LẤY NGUYÊN các mốc dot (203dpi, 8dot/mm) dùng trong
  // QrPayloadService::buildTsplLabel70x100 (backend, đã đối chiếu ảnh tem in thật
  // 2026-07-21), quy đổi sang mm chỉ tại lúc vẽ (chia 8) qua boxDot()/mmD() — KHÔNG
  // còn tự tính lại mốc riêng, tránh lệch giữa preview và tem in thật. Đổi 2026-07-22:
  // dùng ĐỦ chiều rộng 0-560 dot (trước đó có lề dư 5.25mm/5.375mm trái/phải không có
  // trên tem thật), ô Màu+Mã hàng gộp 1 khung không đường kẻ giữa, QR to hơn.
  const DOT = 8;
  const mmD = (dot: number) => dot / DOT;
  function boxDot(x1: number, y1: number, x2: number, y2: number, innerHtml: string, noBorder = false): string {
    return box(mmD(x1), mmD(y1), mmD(x2 - x1), mmD(y2 - y1), innerHtml, noBorder);
  }

  const tableTop = 200, rowHDot = 41, tableBottom = tableTop + rowHDot * 9; // 569
  const rowH = mmD(rowHDot);
  const titleTop = tableBottom, qrTop = 605, qrBottom = 763, routeY = 772;
  const dyeColsDot: [number, number][] = [[0, 110], [110, 206], [206, 278]];
  const chemColsDot: [number, number][] = [[293, 391], [391, 498], [498, 560]];

  const dyeRows = previewDyeLines.value;
  const chemRows = previewChemLines.value;
  let tableCellsHtml = '';
  for (let i = 0; i < 9; i++) {
    const y = tableTop + i * rowHDot;
    const dr = dyeRows[i];
    const cr = chemRows[i];
    if (dr) {
      tableCellsHtml += boxDot(dyeColsDot[0][0], y, dyeColsDot[0][1], y + rowHDot, `<span class="cellval">${dr.rack}</span>`, true);
      tableCellsHtml += boxDot(dyeColsDot[1][0], y, dyeColsDot[1][1], y + rowHDot, `<span class="cellval">${dr.code}</span>`, true);
      tableCellsHtml += boxDot(dyeColsDot[2][0], y, dyeColsDot[2][1], y + rowHDot, `<span class="cellval cellval-right">${dr.weight}</span>`, true);
    }
    if (cr) {
      tableCellsHtml += boxDot(chemColsDot[0][0], y, chemColsDot[0][1], y + rowHDot, `<span class="cellval">${cr.rack}</span>`, true);
      tableCellsHtml += boxDot(chemColsDot[1][0], y, chemColsDot[1][1], y + rowHDot, `<span class="cellval">${cr.code}</span>`, true);
      tableCellsHtml += boxDot(chemColsDot[2][0], y, chemColsDot[2][1], y + rowHDot, `<span class="cellval cellval-right">${cr.weight}</span>`, true);
    }
  }
  // Khung kẻ toàn bảng (kể cả ô rỗng) — vẽ riêng lưới 6 cột x 9 dòng để luôn thấy đủ khung.
  let tableGridHtml = '';
  for (let i = 0; i < 9; i++) {
    const y = mmD(tableTop + i * rowHDot);
    [...dyeColsDot, ...chemColsDot].forEach(([x1, x2]) => {
      tableGridHtml += `<div class="gridcell" style="left:${mmD(x1)}mm;top:${y}mm;width:${mmD(x2 - x1)}mm;height:${rowH}mm;"></div>`;
    });
  }

  function box(x: number, y: number, w: number, h: number, innerHtml: string, noBorder = false): string {
    return `<div class="box${noBorder ? ' noborder' : ''}" style="left:${x}mm;top:${y}mm;width:${w}mm;height:${h}mm;">${innerHtml}</div>`;
  }

  const html = `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Tem ${b.legacy_batch_id || ''}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #000; }
  .slip { position: relative; width: 70mm; height: 100mm; border: 0.3mm solid #000; }
  .box { position: absolute; border: 0.3mm solid #000; overflow: visible; padding: 0.4mm 0.8mm; white-space: nowrap; }
  .box.noborder { border: none; }
  .gridcell { position: absolute; border: 0.2mm solid #000; }
  .label-sm { font-size: 2.3mm; white-space: nowrap; }
  .big { font-size: 3.2mm; font-weight: 700; line-height: 1; white-space: nowrap; }
  .big.code-line { display: block; margin-top: 1.2mm; }
  .zone { font-size: 5.5mm; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; display: block; width: 100%; }
  .med { font-size: 2.6mm; white-space: nowrap; }
  .cellval { font-size: 2.2mm; }
  .cellval-right { display: block; text-align: right; }
  .title { font-size: 2.4mm; font-weight: 700; }
  .qr-block { position: absolute; text-align: center; }
  .qr-block img { width: 100%; height: 100%; object-fit: contain; }
  .qr-block-inline { display: flex; flex-direction: column; align-items: center; gap: 0.5mm; height: 100%; justify-content: center; }
  .qr-block-inline img { width: 10mm; height: 10mm; }
  .placeholder { color: #999; font-style: italic; }
  .footnote { margin-top: 3mm; font-size: 2.3mm; color: #666; }
  @media print {
    body { padding: 0; }
    .footnote { display: none; }
  }
</style>
</head>
<body>
  <div class="slip">
    ${boxDot(0, 0, 206, 112, '<span class="label-sm">DF_WEIGHING_SLIP</span>')}
    ${boxDot(206, 0, 391, 112, `<span class="zone${routing.d1Zone ? '' : ' placeholder'}">${routing.d1Zone || '—'}</span>`)}
    ${boxDot(391, 0, 560, 112, `<div class="qr-block-inline">${modeQrDataUrl ? `<img src="${modeQrDataUrl}" alt="QR mode" />` : ''}<span class="label-sm">${routing.mode}</span></div>`)}

    ${boxDot(0, 114, 278, 200, `<span class="big">${b.color || ''}</span><span class="big code-line">${b.product_code || ''}</span>`)}
    ${boxDot(293, 114, 391, 200, `<span class="big">${b.machine?.code || ''}</span>`)}
    ${boxDot(391, 114, 498, 200, `<span class="big">${b.tank?.code || '-'}</span>`)}
    ${boxDot(498, 114, 560, 200, `<span class="med">${b.level_code || '-'}</span>`)}

    ${tableGridHtml}
    ${tableCellsHtml}

    <div style="position:absolute; left:${mmD(0)}mm; top:${mmD(titleTop)}mm; width:${mmD(278)}mm;" class="title">QR CAN THUOC NHUOM</div>
    <div style="position:absolute; left:${mmD(293)}mm; top:${mmD(titleTop)}mm; width:${mmD(560 - 293)}mm;" class="title">QR CAN CHAT TRO</div>

    <div class="qr-block" style="left:${mmD(0)}mm; top:${mmD(qrTop)}mm; width:${mmD(278)}mm; height:${mmD(qrBottom - qrTop)}mm;">
      ${dyeQrDataUrl ? `<img src="${dyeQrDataUrl}" alt="QR DYE" />` : ''}
    </div>
    <div class="qr-block" style="left:${mmD(293)}mm; top:${mmD(qrTop)}mm; width:${mmD(560 - 293)}mm; height:${mmD(qrBottom - qrTop)}mm;">
      ${chemQrDataUrl ? `<img src="${chemQrDataUrl}" alt="QR CHEM" />` : ''}
    </div>

    <div style="position:absolute; left:${mmD(0)}mm; top:${mmD(routeY)}mm; width:${mmD(560)}mm;" class="med">
      ${routing.b24Route}
    </div>
  </div>

  <p class="footnote">
    Lô: ${b.legacy_batch_id || ''} — In qua trình duyệt (không qua TSPL/Local Agent), bố cục đo đúng từ sheet DF_WEIGHING_SLIP gốc. Khu vực kho/QR chế độ tự tính lại tại đây (khớp backend) — lúc bấm "In tem" thật, hệ thống tính và LƯU lại chính thức, có thể lệch nếu cấu hình routing (feature flag) thay đổi giữa lúc xem trước và lúc in.
  </p>

  <script>
    window.onload = function () { window.print(); };
  <\/script>
</body>
</html>`;

  const win = window.open('', '_blank', 'width=500,height=750');
  if (!win) {
    alert('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }
  win.document.write(html);
  win.document.close();
}

const manualQuery = ref('');
const manualResults = ref<any[]>([]);
const manualError = ref('');
const searching = ref(false);

const reprintReason = ref('');
const reprinting = ref(false);
const reprintError = ref('');
const reprintSuccess = ref('');

let resetTimer: ReturnType<typeof setTimeout> | null = null;

async function loadLabel(labelId: string) {
  manualError.value = '';
  try {
    const res = await axios.get(`/api/material-labels/${labelId}`, getRequestConfig());
    if (res.data?.status === 'SUCCESS') {
      label.value = res.data.data;
      manualResults.value = [];
      manualQuery.value = '';
    }
  } catch (err: any) {
    manualError.value = err.response?.data?.message || 'Không tìm thấy tem vật tư tương ứng.';
  }
}

function handleScan(token: string) {
  if (!token.startsWith('DF:MATERIAL_LABEL:')) {
    manualError.value = 'Mã quét không phải mã QR tem vật tư hợp lệ.';
    return;
  }
  const labelId = token.split(':')[2];
  loadLabel(labelId);
}

async function searchManual() {
  const query = manualQuery.value.trim();
  if (!query) return;
  searching.value = true;
  manualError.value = '';
  manualResults.value = [];
  try {
    const res = await axios.get('/api/material-labels/search', {
      params: { q: query },
      ...getRequestConfig()
    });
    const rows = res.data?.data || [];
    if (rows.length === 0) {
      manualError.value = `Không tìm thấy tem nào khớp mã "${query}".`;
    } else if (rows.length === 1) {
      scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${rows[0].id}`);
    } else {
      manualResults.value = rows;
    }
  } catch (err: any) {
    manualError.value = 'Không thể tìm kiếm tem vật tư. Vui lòng thử lại.';
  } finally {
    searching.value = false;
  }
}

function selectManualResult(l: any) {
  scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${l.id}`);
}

async function submitReprint() {
  if (!label.value || !currentWorkstation.value) return;

  if (!resolvedPrinter.value) {
    reprintError.value = "Không phát hiện máy in nào khả dụng trên trạm này. Kiểm tra Local Agent và máy in Windows rồi thử lại.";
    return;
  }

  let managerPin: string | null = null;
  const authStore = useAuthStore();
  if (!authStore.user) {
    managerPin = prompt('Nhập mã PIN của Giám sát (Supervisor) để in lại tem:');
    if (!managerPin) {
      reprintError.value = 'Cần có mã PIN Giám sát để in lại tem.';
      return;
    }
  }

  reprinting.value = true;
  reprintError.value = '';
  reprintSuccess.value = '';
  try {
    const res = await axios.post(`/api/material-labels/${label.value.id}/reprint`, {
      reason: reprintReason.value,
      workstation_code: currentWorkstation.value.code,
      manager_pin: managerPin
    }, getRequestConfig());
    reprintSuccess.value = res.data.message;
    label.value = res.data.data;
    scannerService.playBeep(2200, 200);
    resetTimer = setTimeout(resetScan, 3000);
  } catch (err: any) {
    reprintError.value = err.response?.data?.message || 'Không thể in lại tem.';
  } finally {
    reprinting.value = false;
  }
}

function resetScan() {
  label.value = null;
  reprintReason.value = '';
  reprintError.value = '';
  reprintSuccess.value = '';
  manualQuery.value = '';
  manualResults.value = [];
  manualError.value = '';
  if (resetTimer) {
    clearTimeout(resetTimer);
    resetTimer = null;
  }
}

function formatTime(v: string) {
  if (!v) return '—';
  return new Date(v).toLocaleString('vi-VN', { hour12: false });
}

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

  scannerService.onScan(handleScan);

  // Đến từ nút "Sang In tem" ở /order-scan — tự tìm theo mã lô, khỏi phải quét lại.
  // Tem gắn theo material_labels (nhiều tem/lô) nên không auto-chọn 1 tem cụ thể,
  // chỉ đưa sẵn kết quả tìm kiếm để người vận hành chọn đúng tem cần in.
  const legacyBatchIdParam = route.query.legacy_batch_id;
  if (legacyBatchIdParam) {
    manualQuery.value = String(legacyBatchIdParam);
    searchManual();
  }

  if (!currentWorkstation.value) {
    await fetchStationOptions();
  }
  fetchInstalledPrinters();
  fetchPendingDispatches();
  fetchPrintHistory();
  pollTimer = setInterval(() => {
    fetchInstalledPrinters();
    fetchPendingDispatches();
    fetchPrintHistory();
  }, 8000);
});

onUnmounted(() => {
  scannerService.offScan(handleScan);
  if (resetTimer) clearTimeout(resetTimer);
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.print-station-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.station-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-lg) var(--space-xl);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
}
.station-badge {
  display: inline-block;
  background-color: var(--primary-bg);
  color: var(--primary-hover);
  font-weight: 700;
  font-size: 0.75rem;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  margin-bottom: 6px;
}
.admin-station-picker {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 4px;
}
.select-sm {
  padding: 2px 8px;
  font-size: 0.85rem;
  width: auto;
}
.dev-badge {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.85rem;
  color: var(--text-muted);
}
.dot-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.dot-green {
  background-color: var(--status-green);
  box-shadow: 0 0 8px var(--status-green);
}

.print-queue-panel,
.print-history-panel {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

/* Đúng màu thật trên VBA TO_SEND.frm: đỏ = vừa tới/chưa in, xanh = đã in */
.row-not-printed td {
  background-color: var(--status-red-bg);
}
.row-printed td {
  background-color: var(--status-green-bg);
}

.printer-config-panel h4 {
  margin: 0 0 var(--space-md) 0;
  color: var(--text-title);
}
.printer-config-form {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) auto;
  gap: var(--space-lg);
  align-items: end;
}
.printer-config-form .form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.printer-config-form label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 600;
}
.queue-header {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  flex-wrap: wrap;
  gap: var(--space-sm);
}
.queue-header h3 {
  margin: 0;
  color: var(--text-title);
}
.table-container-fixed {
  width: 100%;
  overflow-x: auto;
  border-radius: var(--radius-lg);
}
.highlight-code {
  color: var(--primary-hover);
  font-family: monospace;
  font-weight: 700;
}

.scanning-wait-screen {
  padding: var(--space-4xl) var(--space-xl);
}
.scanner-anim-icon {
  font-size: 4rem;
  margin-bottom: var(--space-lg);
}

.manual-entry-widget {
  max-width: 480px;
  margin: 0 auto;
  padding: var(--space-xl);
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  text-align: left;
}
.manual-input-row {
  display: flex;
  gap: var(--space-sm);
}
.manual-input {
  flex: 1;
}
.manual-error {
  color: var(--status-red);
  font-size: 0.85rem;
}
.confirm-success {
  color: var(--status-green);
  font-weight: 600;
}
.manual-results {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.manual-result-item {
  text-align: left;
  padding: 10px 14px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  color: var(--text-body);
  cursor: pointer;
}
.manual-result-item:hover {
  border-color: var(--primary);
  background-color: var(--bg-card-hover);
}

.label-detail {
  max-width: 640px;
  margin: 0 auto;
  width: 100%;
}
.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-md) var(--space-xl);
}
.details-grid .label {
  color: var(--text-muted);
  font-size: 0.85rem;
}
.details-grid .val {
  color: var(--text-body);
  font-weight: 600;
}
.machine-tag {
  background-color: var(--status-blue-bg);
  color: var(--status-blue);
  padding: 2px 10px;
  border-radius: var(--radius-full);
  font-weight: 700;
  font-size: 0.85rem;
}
.text-glow-blue {
  color: var(--primary-hover);
}

.sequence-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.seq-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 14px;
  background-color: var(--bg-sidebar);
  border-radius: var(--radius-md);
}
.item-name {
  font-weight: 600;
  color: var(--text-body);
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

.actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

/* Modal xem trước tem */
.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.preview-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 640px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: var(--shadow-xl);
}
.preview-modal-header {
  padding: var(--space-lg) var(--space-xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.preview-modal-header h4 { margin: 0; color: var(--text-title); font-size: 1rem; }
.close-btn { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
.preview-body {
  padding: var(--space-xl);
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}
.preview-header-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-sm) var(--space-xl);
}
.preview-header-grid .label { color: var(--text-muted); font-size: 0.85rem; }
.preview-header-grid .val { color: var(--text-body); font-weight: 600; }
.preview-qr-row {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: var(--space-lg);
  background-color: var(--bg-sidebar);
  border-radius: var(--radius-md);
}
.preview-qr-canvas {
  background: #fff;
  border-radius: 4px;
  padding: 8px;
}
.preview-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: var(--space-lg) var(--space-xl);
  border-top: 1px solid var(--border-divider);
}
.preview-no-printer-hint {
  padding: 0 var(--space-xl) var(--space-lg);
  text-align: right;
}

/* Bảng RACK/MÃ/KHỐI LƯỢNG tách dòng — layout kiểu scaleform.frm (VBA gốc) */
.rack-tables-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-lg);
}
.rack-table-title {
  display: block;
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}
.rack-table th { font-size: 11px; }
.rack-row-filled td { background-color: var(--status-green-bg); }
.rack-cell-num { width: 60px; font-weight: 700; color: var(--text-muted); }
.rack-cell-code { font-weight: 700; font-family: monospace; }
.rack-cell-weight { font-weight: 700; font-family: monospace; color: var(--status-blue); }

@media (max-width: 768px) {
  .rack-tables-row,
  .preview-header-grid {
    grid-template-columns: 1fr;
  }
}
</style>
