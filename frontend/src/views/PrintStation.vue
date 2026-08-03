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
        </template>
      </div>
      <div class="banner-right">
        <div class="dev-badge">
          <span>🖥️ In qua hộp thoại in của trình duyệt — không cần chọn/cài máy in trước.</span>
        </div>
      </div>
    </div>

    <!-- Hàng chờ in tem mới — port đúng TO_SEND.frm/LoadGrid (VBA "3.DF028... jit qr
         sending"): đơn vừa được Duyệt ở máy Nhập đơn xuất hiện ở đây, đúng dữ liệu
         tbl_tosend (color/code/machine/tank). VBA tự làm mới mỗi 15s bằng
         Application.OnTime; web dùng interval ngắn hơn vì không cần tiết kiệm tài
         nguyên như Excel. "⚡ In nhanh"/"🖥️ In qua trình duyệt" chỉ MỞ hộp thoại in —
         không xác nhận đơn, nên bấm in được nhiều lần thoải mái (in hỏng/in lại tùy ý)
         mà không mất đơn khỏi hàng chờ. Chỉ khi bấm "✅ OK" đơn mới được xác nhận
         (CONFIRMED) và chuyển xuống bảng lịch sử bên dưới (yêu cầu 2026-07-30). -->
    <section class="section card-sec print-queue-panel mb-4" v-if="!label">
      <div class="queue-header">
        <h3>🖨️ Hàng chờ in tem mới ({{ pendingDispatches.length }})</h3>
        <span class="text-muted font-sm">Tự làm mới mỗi 8 giây — in thoải mái bằng "⚡ In nhanh"/"👁️ Xem trước", xong bấm "✅ OK" để chuyển xuống lịch sử.</span>
      </div>

      <p v-if="confirmError" class="text-error mt-2">❌ {{ confirmError }}</p>

      <div class="queue-columns mt-3" v-if="pendingDispatches.length">
        <div class="table-container-fixed" v-for="(col, colIdx) in queueColumns" :key="colIdx">
          <table class="data-table">
            <thead>
              <tr>
                <th>Màu</th>
                <th>Mã hàng</th>
                <th>Máy</th>
                <th>Thùng</th>
                <th>Mực nước</th>
                <th>Trạng thái</th>
                <th title="Đã từng in ít nhất 1 lần chưa — tự tích khi bấm In nhanh/Xem trước, KHÔNG phải xác nhận xong">Đã từng in</th>
                <th>Mã Lô</th>
                <th class="actions-col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in col" :key="d.id" :class="(confirmedIds.has(d.id) || d.ever_printed) ? 'row-printed' : 'row-not-printed'">
                <td>{{ d.batch?.color }}</td>
                <td>{{ d.batch?.product_code }}</td>
                <td><span class="machine-tag">{{ d.batch?.machine?.code || 'N/A' }}</span></td>
                <td>{{ d.batch?.tank?.code || '-' }}</td>
                <td>{{ d.batch?.level_code || 'Mặc định' }}</td>
                <td>
                  <span v-if="confirmedIds.has(d.id)" class="badge badge-green">Đã in</span>
                  <span v-else class="badge badge-red">Chưa in</span>
                </td>
                <td class="text-center">
                  <input
                    type="checkbox"
                    :checked="!!d.ever_printed"
                    @change="toggleEverPrinted(d, ($event.target as HTMLInputElement).checked)"
                    title="Đã từng in tem này chưa (tick tay được nếu cần sửa lại)"
                  />
                </td>
                <td class="highlight-code">{{ d.batch?.legacy_batch_id }}</td>
                <td class="actions-cell actions-col">
                  <button
                    @click="quickPrintViaBrowser(d)"
                    class="btn btn-primary btn-sm"
                    :disabled="confirmingId === d.id"
                  >
                    ⚡ In nhanh
                  </button>
                  <button
                    @click="openPrintPreview(d)"
                    class="btn btn-secondary btn-sm"
                    :disabled="confirmingId === d.id"
                  >
                    👁️ Xem trước
                  </button>
                  <button
                    @click="confirmDone(d)"
                    class="btn btn-ok btn-sm"
                    :disabled="confirmingId === d.id"
                    title="Đã in xong — chuyển đơn này xuống bảng lịch sử"
                  >
                    {{ confirmingId === d.id ? 'Đang xử lý...' : '✅ OK' }}
                  </button>
                </td>
              </tr>
              <tr v-if="!col.length">
                <td colspan="9" class="text-muted text-center">Không có đơn nào ở cột này.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p v-else class="text-muted text-center mt-3">Không có đơn nào đang chờ in.</p>
    </section>

    <!-- Bảng lịch sử — đơn đã bấm "✅ OK" (CONFIRMED) rơi xuống đây, tách khỏi hàng chờ
         phía trên để người vận hành luôn thấy rõ việc nào còn phải làm (yêu cầu
         2026-07-30: "in thoải mái, có nút ok thì đưa xuống bảng lịch sử"). -->
    <section class="section card-sec print-history-panel mb-4" v-if="!label">
      <div class="queue-header">
        <h3>📋 Lịch sử đã in ({{ printHistory.length }})</h3>
        <span class="text-muted font-sm">Các đơn đã bấm "✅ OK" ở hàng chờ trên — vẫn in lại được, có ghi lý do.</span>
      </div>

      <p v-if="historyError" class="text-error mt-2">❌ {{ historyError }}</p>

      <div class="table-container-fixed mt-3" v-if="printHistory.length">
        <table class="data-table">
          <thead>
            <tr>
              <th>Màu</th>
              <th>Mã hàng</th>
              <th>Máy</th>
              <th>Thùng</th>
              <th>Mã Lô</th>
              <th>Thời gian xác nhận</th>
              <th>Số lần in</th>
              <th>Trạng thái</th>
              <th class="actions-col">Thao tác</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in printHistory" :key="d.id" class="row-printed">
              <td>{{ d.batch?.color }}</td>
              <td>{{ d.batch?.product_code }}</td>
              <td><span class="machine-tag">{{ d.batch?.machine?.code || 'N/A' }}</span></td>
              <td>{{ d.batch?.tank?.code || '-' }}</td>
              <td class="highlight-code">{{ d.batch?.legacy_batch_id }}</td>
              <td>{{ formatTime(d.updated_at || d.created_at) }}</td>
              <td class="text-center">{{ d.print_jobs?.length || 1 }}</td>
              <td><span class="badge badge-green">Đã in</span></td>
              <td class="actions-cell actions-col">
                <button
                  @click="reprintFromHistory(d)"
                  class="btn btn-secondary btn-sm"
                  :disabled="reprintingHistoryId === d.id"
                  title="In lại tem này — bắt buộc nhập lý do, có ghi Audit Log"
                >
                  {{ reprintingHistoryId === d.id ? 'Đang xử lý...' : '🖨️ In lại' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-muted text-center mt-3">Chưa có đơn nào được xác nhận xong.</p>
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

        </div>

        <div class="preview-modal-actions">
          <button class="btn btn-secondary" @click="closePrintPreview">Đóng</button>
          <button
            class="btn btn-primary"
            :disabled="confirmingId === previewDispatch.id"
            @click="printPreviewViaBrowser"
          >
            🖥️ In qua trình duyệt
          </button>
          <button
            class="btn btn-ok"
            :disabled="confirmingId === previewDispatch.id"
            @click="confirmDone(previewDispatch, true)"
          >
            {{ confirmingId === previewDispatch.id ? 'Đang xử lý...' : '✅ OK — Xong, chuyển xuống lịch sử' }}
          </button>
        </div>
        <p v-if="confirmError" class="text-error mt-2">❌ {{ confirmError }}</p>
        <p class="text-muted font-xs mt-2">
          "🖥️ In qua trình duyệt" mở hộp thoại in, bấm được nhiều lần thoải mái (in hỏng cứ in lại). Chỉ khi bấm "✅ OK" đơn mới được xác nhận và chuyển xuống bảng lịch sử.
        </p>
      </div>
    </div>

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
import echo from '../services/echo';
import scannerService from '../services/scanner';
import { currentWorkstation } from '../services/workstation';
import { useAuthStore } from '../stores/auth';
import { parseRackLines } from '../utils/rackParser';
import { writeDispatchSlipToWindow } from '../utils/dispatchSlipPrint';

const route = useRoute();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);

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

// Chia danh sách hàng chờ in thành nhiều cột (yêu cầu 2026-07-27, làm thích ứng
// 2026-07-28) — số cột tự đổi theo bề rộng màn hình thay vì cố định 2, để màn nhỏ
// (laptop/cửa sổ thu nhỏ) không bị vỡ bảng, còn màn lớn (máy trạm xưởng) tận dụng
// được hết chỗ trống thay vì luôn chỉ 2 cột.
const viewportWidth = ref(window.innerWidth);
function onViewportResize() {
  viewportWidth.value = window.innerWidth;
}
// Chỉ chia tối đa 2 bảng (yêu cầu 2026-07-30: 3 bảng làm mỗi cột quá hẹp, đặc biệt sau
// khi thêm nút "✅ OK" khiến cột Thao tác không đủ chỗ hiển thị đầy đủ 3 nút).
const queueColumnCount = computed(() => (viewportWidth.value < 900 ? 1 : 2));
const queueColumns = computed(() => {
  const count = queueColumnCount.value;
  const perCol = Math.ceil(pendingDispatches.value.length / count) || 1;
  return Array.from({ length: count }, (_, i) => pendingDispatches.value.slice(i * perCol, (i + 1) * perCol));
});

async function fetchPendingDispatches() {
  try {
    const res = await axios.get('/api/machine-dispatches');
    pendingDispatches.value = Array.isArray(res.data) ? res.data : (res.data.data || []);
  } catch (err) {
    console.error('Error fetching print queue:', err);
  }
}

// "Đã từng in" — cờ riêng, KHÁC với "✅ OK" (CONFIRMED, chuyển xuống lịch sử). Chỉ đổi
// nền hàng chờ từ đỏ sang bình thường để báo "đã in ít nhất 1 lần", đơn vẫn nằm nguyên
// trong hàng chờ chờ xác nhận thật. Tự tích sau lần in đầu tiên (gọi từ
// printDispatchViaBrowser); ô tick trong bảng vẫn cho tích/bỏ tích tay nếu cần sửa lại.
async function toggleEverPrinted(dispatch: any, value: boolean) {
  dispatch.ever_printed = value;
  try {
    await axios.patch(`/api/machine-dispatches/${dispatch.id}/ever-printed`, { ever_printed: value }, getRequestConfig());
  } catch (err) {
    console.error('Error updating ever_printed flag:', err);
    dispatch.ever_printed = !value;
  }
}

// Bảng lịch sử — đơn đã bấm "✅ OK" (CONFIRMED), tách khỏi hàng chờ (yêu cầu 2026-07-30:
// in thoải mái không mất đơn khỏi hàng chờ, chỉ khi bấm OK mới rơi xuống đây). Lọc theo
// đúng trạm đang đứng để không lẫn lịch sử của trạm khác; admin không đứng trạm nào thì
// xem toàn bộ (giống PrintHistoryAdmin.vue).
const printHistory = ref<any[]>([]);
async function fetchHistory() {
  try {
    const res = await axios.get('/api/machine-dispatches/history', {
      params: { station_code: currentWorkstation.value?.code || undefined },
    });
    printHistory.value = res.data.data || [];
  } catch (err) {
    console.error('Error fetching print history:', err);
  }
}

// viaBrowser=true: đơn được in qua hộp thoại Windows/trình duyệt (window.print(), xem
// printPreviewViaBrowser) — CÁCH IN DUY NHẤT còn lại ở màn hình này (bỏ "In nhanh"/"In
// tem" gửi thẳng Local Agent, 2026-07-30). Báo cho backend qua printed_via_browser để
// ConfirmDispatchService đánh dấu PrintJob PRINTED ngay, không rơi vào hàng chờ Agent
// (AgentJobsController::getJobs chỉ lấy status=PENDING) — nếu không, Agent sẽ gửi lệnh
// TSPL thật xuống máy in vật lý, in trùng bản đã in qua trình duyệt.
// confirmedIds: đánh dấu ngay các đơn vừa bấm "✅ OK" thành công để badge chuyển "Chưa
// in" (đỏ) -> "Đã in" (xanh) NGAY trong hàng chờ, cho người vận hành thấy rõ đã xác nhận
// trước khi dòng thật sự rơi xuống bảng lịch sử (fetchPendingDispatches trễ 600ms).
const confirmedIds = ref<Set<string>>(new Set());

async function confirmAndPrint(dispatch: any, viaBrowser?: boolean) {
  confirmError.value = '';
  confirmingId.value = dispatch.id;
  try {
    await axios.post(`/api/machine-dispatches/${dispatch.id}/confirm`, {
      idempotency_key: `print_${dispatch.id}_${Date.now()}`,
      workstation_id: currentWorkstation.value?.code || undefined,
      printed_via_browser: viaBrowser || undefined,
    }, getRequestConfig());
    confirmedIds.value.add(dispatch.id);
    scannerService.playBeep(1800, 150);
    await fetchHistory();
    setTimeout(() => {
      fetchPendingDispatches();
      confirmedIds.value.delete(dispatch.id);
    }, 600);
  } catch (err: any) {
    confirmError.value = err.response?.data?.message || 'Không thể tạo lệnh in cho đơn này.';
  } finally {
    confirmingId.value = null;
  }
}

// "✅ OK" — người vận hành đã in xong (thoải mái in lại bao nhiêu lần tùy ý bằng "⚡ In
// nhanh"/"🖥️ In qua trình duyệt" phía trên, KHÔNG tự động xác nhận), bấm nút này mới thật
// sự xác nhận đơn (CONFIRMED) và chuyển xuống bảng lịch sử. closeModal=true khi gọi từ
// modal xem trước để đóng modal luôn nếu không lỗi.
async function confirmDone(dispatch: any, closeModal?: boolean) {
  await confirmAndPrint(dispatch, true);
  if (closeModal && !confirmError.value) closePrintPreview();
}

// Xem trước tem — dựng lại QR minh họa + bảng RACK/MÃ/KHỐI LƯỢNG từ chính dữ liệu
// raw_qr_dye/raw_qr_chemical đã lưu trên batch (KHÔNG gọi endpoint confirm thật, tránh
// tạo PrintJob/RoutingDecision chỉ để xem trước).
const previewDispatch = ref<any>(null);
const previewQrCanvas = ref<HTMLCanvasElement | null>(null);

const previewDyeLines = computed(() => parseRackLines(previewDispatch.value?.batch?.raw_qr_dye));
const previewChemLines = computed(() => parseRackLines(previewDispatch.value?.batch?.raw_qr_chemical));

function openPrintPreview(dispatch: any) {
  previewDispatch.value = dispatch;
}

function closePrintPreview() {
  previewDispatch.value = null;
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

// In qua trình duyệt — mở tab mới dựng lại tem dạng HTML (không qua TSPL/Local Agent),
// gọi window.print() để dùng hộp thoại in gốc của Windows, chọn được bất kỳ máy in nào
// đã cài. Bố cục tem nằm trong utils/dispatchSlipPrint.ts (tách ra 2026-08-03 để màn hình
// Nhập đơn — VbaPrintForm — in ra đúng cùng một tem, xem ghi chú trong file đó).
// Tách riêng khỏi state của modal xem trước (previewDispatch/previewDyeLines/
// previewChemLines) để dùng chung được cho cả "⚡ In nhanh" ở hàng chờ (không mở modal)
// LẪN nút "🖥️ In qua trình duyệt" trong modal xem trước (yêu cầu 2026-07-30: người dùng
// muốn có lại đường tắt in nhanh nhưng vẫn dùng cơ chế trình duyệt, không quay lại
// TSPL/Local Agent).
// existingWin: cửa sổ đã được caller mở SẴN (dùng cho luồng in lại từ bảng lịch sử — ở
// đó phải mở cửa sổ trước khi hỏi lý do in lại, nếu mở sau prompt() thì có nguy cơ mất
// "user gesture" và bị chặn popup). markEverPrinted=false cho đơn đã nằm ở lịch sử
// (CONFIRMED rồi, cờ "đã từng in" của hàng chờ không còn ý nghĩa).
async function printDispatchViaBrowser(
  d: any,
  opts: { existingWin?: Window | null; markEverPrinted?: boolean } = {}
) {
  if (!d) return;

  // Mở cửa sổ NGAY (đồng bộ, trước mọi await) — Chrome/Edge chặn window.open() nếu gọi
  // sau 1 tác vụ bất đồng bộ (mất "user gesture" gắn với cú click), khiến bấm "⚡ In
  // nhanh" không hiện hộp thoại in ngay mà im lặng bị chặn hoặc phải bấm 2 lần (yêu cầu
  // 2026-07-30: "ấn vào đấy ra cái in của trình duyệt luôn"). Ghi HTML thật vào sau khi
  // QR (async) dựng xong.
  const win = opts.existingWin ?? window.open('', '_blank', 'width=780,height=980');
  if (!win) {
    alert('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }

  // Tự tích "Đã từng in" ngay khi thật sự mở được hộp thoại in (yêu cầu 2026-07-30) — đổi
  // nền hàng chờ từ đỏ sang bình thường, KHÔNG phải xác nhận xong (chỉ "✅ OK" mới CONFIRMED).
  if (opts.markEverPrinted !== false && !d.ever_printed) toggleEverPrinted(d, true);

  const b = d.batch || {};
  await writeDispatchSlipToWindow(win, {
    color: b.color || '',
    productCode: b.product_code || '',
    machineCode: b.machine?.code || '',
    tankCode: b.tank?.code || '',
    levelCode: b.level_code || '',
    rawQrDye: b.raw_qr_dye || '',
    rawQrChem: b.raw_qr_chemical || '',
    batchId: b.legacy_batch_id || '',
  });
}

// "🖥️ In qua trình duyệt" trong modal xem trước — chỉ MỞ hộp thoại in, không xác nhận
// đơn, nên bấm được nhiều lần thoải mái; đóng modal riêng qua nút "✅ OK" (confirmDone).
async function printPreviewViaBrowser() {
  const d = previewDispatch.value;
  if (!d) return;
  await printDispatchViaBrowser(d);
}

// "⚡ In nhanh" ở hàng chờ — bỏ qua bước xem trước, in thẳng qua trình duyệt, dùng khi
// người vận hành tin tưởng dữ liệu đúng và muốn in ngay không cần xem lại.
async function quickPrintViaBrowser(dispatch: any) {
  await printDispatchViaBrowser(dispatch);
}

// "🖨️ In lại" ở bảng LỊCH SỬ (đơn đã bấm "✅ OK", queue_state=CONFIRMED) — khác hẳn "⚡ In
// nhanh" ở hàng chờ: in lại tem đã xác nhận xong là hành động NHẠY CẢM, bắt buộc ghi Audit
// Log kèm lý do theo CLAUDE.md mục 5 ("In lại tem (Reprint) ... phải ghi Audit Log bất
// biến"). Gọi endpoint reprint sẵn có (tái dùng đúng QrPayload đã sinh lần đầu, ghi
// PRINT_JOB_REPRINTED + REPRINT_REQUESTED), kèm printed_via_browser để job không rơi vào
// hàng chờ Local Agent gây in trùng.
const reprintingHistoryId = ref<string | null>(null);
const historyError = ref('');

async function reprintFromHistory(dispatch: any) {
  historyError.value = '';

  // Mở cửa sổ TRƯỚC khi hỏi lý do: prompt() chặn khá lâu (người dùng gõ), nếu mở sau thì
  // "transient user activation" của cú click có thể đã hết hạn -> trình duyệt chặn popup.
  const win = window.open('', '_blank', 'width=780,height=980');
  if (!win) {
    alert('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }

  const reason = prompt('Lý do in lại tem (bắt buộc, ví dụ: tem bị rách/mất/mờ):');
  if (!reason || reason.trim().length < 3) {
    win.close();
    if (reason !== null) historyError.value = 'Cần nhập lý do in lại (tối thiểu 3 ký tự).';
    return;
  }

  reprintingHistoryId.value = dispatch.id;
  try {
    await printDispatchViaBrowser(dispatch, { existingWin: win, markEverPrinted: false });
    await axios.post(`/api/machine-dispatches/${dispatch.id}/reprint`, {
      reason: reason.trim(),
      workstation_id: currentWorkstation.value?.code || undefined,
      printed_via_browser: true,
    }, getRequestConfig());
    await fetchHistory();
  } catch (err: any) {
    historyError.value = err.response?.data?.message || 'Không ghi nhận được lần in lại (tem vẫn đã in ra).';
  } finally {
    reprintingHistoryId.value = null;
  }
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

// Dựng lại tem vật tư dạng HTML (80x100 QR + 4 dòng text, đúng nội dung TSPL backend
// WeighingJobController::reprintLabel dựng) rồi gọi window.print() — cùng cơ chế
// "in qua trình duyệt" như hàng chờ dispatch phía trên, không qua TSPL/Local Agent.
// Nhận sẵn cửa sổ đã mở (win) từ submitReprint — PHẢI mở window.open() đồng bộ ngay lúc
// bấm nút, trước khi gọi API xác nhận, nếu không Chrome/Edge sẽ chặn popup vì mất "user
// gesture" sau await mạng (yêu cầu 2026-07-30: "ấn vào đấy ra cái in của trình duyệt luôn").
async function printMaterialLabelViaBrowser(l: any, win: Window) {
  const qrToken = `DF:MATERIAL_LABEL:${l.id}`;
  let qrDataUrl = '';
  try {
    // 240 -> 960: cùng lý do như QR tem dispatch — nguồn dư độ phân giải để lúc in là thu
    // nhỏ (nét đen đặc) chứ không phải phóng to (cạnh nhoè xám, in nhiệt ra lấm tấm).
    qrDataUrl = await QRCode.toDataURL(qrToken, { width: 960, margin: 0 });
  } catch (err) {
    console.error('Failed to render QR for material label print:', err);
  }

  const html = `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Tem ${l.batch?.legacy_batch_id || ''}</title>
<style>
  * { box-sizing: border-box; }
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #000; display: flex; justify-content: center; }
  /* border 0.3 -> 0.25mm = đúng 2 dot ở 203dpi (nét tròn dot -> đen đặc, không bị khử răng
     cưa thành xám rồi dither ra mờ). Chữ tăng độ đậm cùng lý do như tem dispatch. */
  .slip { width: 80mm; height: 50mm; border: 0.25mm solid #000; zoom: 2.6; display: flex; align-items: center; gap: 4mm; padding: 3mm; }
  .qr { width: 26mm; height: 26mm; flex-shrink: 0; }
  .qr img { width: 100%; height: 100%; }
  .info { font-size: 3mm; line-height: 1.5; font-weight: 600; }
  .info strong { font-size: 3.4mm; font-weight: 700; }
  /* Cùng lý do đã sửa ở printDispatchViaBrowser — không khai báo @page thì in theo khổ
     giấy mặc định của driver máy in, tem đúng kích cỡ thật nhưng chỉ chiếm 1 góc tờ giấy
     to hơn nhiều, nhìn như bé xíu. */
  @page { size: 80mm 50mm; margin: 0; }
  /* Không scale khi in — scale làm độ dày nét lẻ dot khiến đường thẳng in ra đứt quãng
     (xem chú thích @media print ở printDispatchViaBrowser). */
  @media print { body { padding: 0; } .slip { zoom: 1; } }
</style>
</head>
<body>
  <div class="slip">
    <div class="qr">${qrDataUrl ? `<img src="${qrDataUrl}" alt="QR" />` : ''}</div>
    <div class="info">
      <div><strong>LOT: ${l.batch?.legacy_batch_id || ''} (REPRINT #${l.reprint_count})</strong></div>
      <div>LOAI: ${l.material_type || ''}</div>
      <div>KG: ${((l.weight || 0) / 1000).toFixed(2)}</div>
      <div>MAY: ${l.batch?.machine?.code || 'VD-COMMON'}</div>
    </div>
  </div>
  <script>
    window.onload = function () {
      // In xong (bấm In hoặc Hủy trong hộp thoại) thì tự đóng luôn cửa sổ này. Dùng
      // window.onafterprint không ăn thua trong thực tế (người dùng xác nhận cửa sổ vẫn
      // còn "about:blank" sau khi in, 2026-07-30) — chuyển sang cách chắc chắn hơn:
      // window.print() CHẶN (blocking) tới khi hộp thoại in đóng lại trên Chrome/Edge
      // (2 trình duyệt Windows thực tế đang dùng), nên gọi window.close() ngay dòng kế
      // tiếp là chạy SAU khi người dùng đã bấm In/Hủy, không cần chờ sự kiện afterprint.
      window.print();
      window.close();
    };
  <\/script>
</body>
</html>`;

  win.document.open();
  win.document.write(html);
  win.document.close();
}

async function submitReprint() {
  if (!label.value || !currentWorkstation.value) return;

  // Mở cửa sổ NGAY lúc bấm nút (đồng bộ, trước khi gọi API xác nhận) — nếu đợi API trả
  // về rồi mới window.open(), Chrome/Edge sẽ chặn popup vì đã mất "user gesture" gắn với
  // cú click (yêu cầu 2026-07-30: "ấn vào đấy ra cái in của trình duyệt luôn").
  const printWin = window.open('', '_blank', 'width=780,height=520');
  if (!printWin) {
    reprintError.value = 'Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.';
    return;
  }
  printWin.document.write('<p style="font-family:sans-serif;padding:20px;">Đang xử lý...</p>');

  let managerPin: string | null = null;
  const authStore = useAuthStore();
  if (!authStore.user) {
    managerPin = prompt('Nhập mã PIN của Giám sát (Supervisor) để in lại tem:');
    if (!managerPin) {
      reprintError.value = 'Cần có mã PIN Giám sát để in lại tem.';
      printWin.close();
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
    await printMaterialLabelViaBrowser(label.value, printWin);
    resetTimer = setTimeout(resetScan, 3000);
  } catch (err: any) {
    reprintError.value = err.response?.data?.message || 'Không thể in lại tem.';
    printWin.close();
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
  window.addEventListener('resize', onViewportResize);

  // Đến từ nút "Sang In tem" ở /order-scan — tự tìm theo mã lô, khỏi phải quét lại.
  // Tem gắn theo material_labels (nhiều tem/lô) nên không auto-chọn 1 tem cụ thể,
  // chỉ đưa sẵn kết quả tìm kiếm để người vận hành chọn đúng tem cần in.
  const legacyBatchIdParam = route.query.legacy_batch_id;
  if (legacyBatchIdParam) {
    manualQuery.value = String(legacyBatchIdParam);
    searchManual();
  }

  fetchPendingDispatches();
  fetchHistory();
  pollTimer = setInterval(() => {
    fetchPendingDispatches();
  }, 8000);

  // Realtime qua Reverb — /production-batches và /production-batches/list bắn
  // ProductionBatchUpdated ngay khi Duyệt đơn (tạo dispatch mới vào hàng chờ in), nghe
  // chung kênh "production-batches" để hàng chờ ở đây cập nhật NGAY, không cần đợi
  // polling 8s hay F5.
  echo.channel('production-batches').listen('.updated', () => {
    fetchPendingDispatches();
  });
});

onUnmounted(() => {
  scannerService.offScan(handleScan);
  window.removeEventListener('resize', onViewportResize);
  if (resetTimer) clearTimeout(resetTimer);
  if (pollTimer) clearInterval(pollTimer);
  echo.leaveChannel('production-batches');
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
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
  padding: var(--space-lg) var(--space-xl);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
}
.banner-left {
  min-width: 0;
}
.banner-right {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--space-sm);
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
.dev-badge {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  font-size: 0.85rem;
  color: var(--text-muted);
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
/* Bảng hàng chờ in đè lại padding mặc định khá rộng của .data-table (16px/24px) —
   thu gọn để hiện được nhiều dòng hơn trong 1 màn hình, đỡ phải cuộn (yêu cầu
   2026-07-28: "thông tin sát nhau hơn để tiết kiệm diện tích"). Chỉ áp dụng trong
   bảng hàng chờ vì style ở đây là scoped, không ảnh hưởng data-table ở trang khác. */
.table-container-fixed .data-table th,
.table-container-fixed .data-table td {
  padding: 6px var(--space-md);
  font-size: 0.9rem;
}
/* 8 cột trong mỗi bảng con (queue-columns) vẫn có thể vượt quá bề rộng cột khi zoom
   trình duyệt lớn dù đã chia cột thích ứng -> cột "Thao tác" (Xem trước) bị
   đẩy ra ngoài, phải cuộn ngang mới thấy, người dùng tưởng nút biến mất. Ghim cột này
   bên phải để luôn thấy nút thao tác dù các cột khác có cuộn ngang hay không.
   Lưu ý: PHẢI target đúng .actions-col (class riêng), không dùng :last-child — cột
   "Mã Lô" được thêm vào SAU cột Thao tác nên mới là cột cuối cùng trong bảng, dùng
   :last-child sẽ ghim nhầm Mã Lô thay vì nút thao tác (bug phát hiện 2026-07-28). */
.table-container-fixed .data-table th.actions-col,
.table-container-fixed .data-table td.actions-col {
  position: sticky;
  right: 0;
  z-index: 1;
  box-shadow: -4px 0 6px -4px rgba(0, 0, 0, 0.25);
}
.queue-columns {
  display: grid;
  /* Số cột lấy từ queueColumnCount (JS, phản ứng theo bề rộng cửa sổ thật) thay vì
     media query cố định, để luôn khớp với số mảng đã chia trong queueColumns. */
  grid-template-columns: repeat(v-bind(queueColumnCount), 1fr);
  gap: var(--space-md);
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
  flex-wrap: wrap;
  gap: var(--space-sm);
}
.manual-input {
  flex: 1;
  min-width: 160px;
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
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-md);
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
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-md);
  min-width: 0;
}
.banner-icon {
  font-size: var(--font-lg);
}
.select-mode {
  width: 180px;
  max-width: 100%;
  background-color: var(--bg-card);
  border-color: var(--border-card);
  color: var(--text-body);
}

.actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

.btn-ok {
  background-color: var(--status-green);
  border-color: var(--status-green);
  color: #fff;
}
.btn-ok:hover:not(:disabled) {
  filter: brightness(0.92);
}
.btn-ok:disabled {
  opacity: 0.6;
}

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
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 12px;
  padding: var(--space-lg) var(--space-xl);
  border-top: 1px solid var(--border-divider);
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
  .preview-header-grid,
  .details-grid {
    grid-template-columns: 1fr;
  }
}
</style>
