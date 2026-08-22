<template>
  <div class="realtime-control-center">
    <!-- Realtime Connection Status Banner -->
    <div class="connection-status-banner" :class="'banner-' + connectionStatus.toLowerCase()">
      <span class="status-indicator-dot"></span>
      <span class="status-text">{{ $t('dashboard.connectionStatusPrefix') }}{{ connectionStatusText }}</span>
      <span class="status-subtext" v-if="connectionStatus === 'FALLBACK'">{{ $t('dashboard.connectionFallbackSuffix') }}</span>
    </div>

    <!-- Nội dung chính -->
    <main class="dashboard-tab-content">
      
      <!-- Điều độ tổng thể — màn hình duy nhất của Dashboard (yêu cầu 2026-08-21: bỏ các
           tab Phòng cân / Máy nhuộm / Cảnh báo / KPIs Quản lý). -->
      <div class="tab-panel">
        <div class="panel-header mb-4">
          <h3>{{ $t('dashboard.overviewTitle') }}</h3>
          <p class="text-muted" v-if="authStore.isAdmin">{{ $t('dashboard.overviewSubtitleAdmin') }}</p>
          <p class="text-muted" v-else>{{ $t('dashboard.overviewSubtitleUser') }}</p>
        </div>

        <div class="overview-status-layout">
          <!-- Trạng thái máy thật lấy trực tiếp từ BPDB (giống /bpdb-machines) — chỉ Admin
               thấy vì endpoint /admin/bpdb/machines/status yêu cầu role:ADMIN ở backend (xác
               nhận 2026-07-29). Chỉ hiển thị icon + trạng thái, không kèm chi tiết task/tank
               (chi tiết đầy đủ đã có sẵn ở /bpdb-machines). -->
          <div v-if="authStore.isAdmin" class="machines-grid-simple">
            <div
              v-for="m in bpdbMachines"
              :key="m.machineCode"
              class="machine-status-tile machine-tile-clickable"
              :class="'bpdb-status-' + m.operationalStatus.toLowerCase()"
              :title="$t('dashboard.machineHistoryHint')"
              role="button"
              tabindex="0"
              @click="openMachineHistory(m.machineCode)"
              @keydown.enter="openMachineHistory(m.machineCode)"
            >
              <span class="tile-icon">{{ bpdbStatusIcon(m.operationalStatus) }}</span>
              <span class="tile-code">{{ m.displayName }}</span>
              <span class="tile-status">{{ m.operationalStatus }}</span>
            </div>
            <p v-if="!bpdbMachines.length" class="text-muted font-sm">{{ $t('dashboard.noVdMachineData') }}</p>
          </div>

          <!-- Fallback: trạng thái nội bộ (app.machines) cho tài khoản không phải Admin — vẫn
               giữ nguyên hành vi cũ vì họ không gọi được endpoint BPDB. -->
          <div v-else class="machines-grid-simple">
            <div
              v-for="m in overviewData"
              :key="m.machine_id"
              class="machine-status-tile"
              :class="'card-state-' + m.status.toLowerCase()"
            >
              <span class="tile-icon">{{ appStatusIcon(m.status) }}</span>
              <span class="tile-code">{{ m.machine_code }}</span>
              <span class="tile-status">{{ m.status }}</span>
            </div>
          </div>

          <!-- Bảng chú thích trạng thái, bên phải lưới máy (yêu cầu 2026-07-29) -->
          <aside class="status-legend">
            <h4 class="legend-title">{{ $t('dashboard.legendTitle') }}</h4>
            <ul class="legend-list">
              <li v-for="item in (authStore.isAdmin ? bpdbStatusLegend : appStatusLegend)" :key="item.status" class="legend-item">
                <span class="legend-icon">{{ item.icon }}</span>
                <div class="legend-text">
                  <div class="legend-head">
                    <strong>{{ item.label }}</strong>
                    <span class="legend-code">{{ item.status }}</span>
                  </div>
                  <p class="legend-desc">{{ item.desc }}</p>
                </div>
              </li>
            </ul>
          </aside>
        </div>

        <!-- Báo cáo "máy đã ở trạng thái hiện tại bao lâu rồi" (yêu cầu 2026-08-01) —
             đặt ngay dưới lưới trạng thái vì trả lời đúng câu hỏi tiếp theo mà lưới icon
             không trả lời được: máy nào đang đứng/chờ/chạy lâu bất thường. -->
        <section class="status-duration-report mt-4">
          <div class="report-head">
            <div>
              <h4 class="report-title">{{ $t('dashboard.durationReportTitle') }}</h4>
              <p class="report-sub" v-if="authStore.isAdmin">
                {{ $t('dashboard.durationReportSubAdmin') }}
              </p>
              <p class="report-sub" v-else>
                {{ $t('dashboard.durationReportSubUser') }}
              </p>
            </div>
            <label class="report-filter">
              <input type="checkbox" v-model="onlyStuckMachines" />
              <span>{{ $t('dashboard.filterStuckOnly') }}</span>
            </label>
          </div>

          <div class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ $t('dashboard.thMachine') }}</th>
                  <th>{{ $t('common.status') }}</th>
                  <th>{{ $t('dashboard.thDuration') }}</th>
                  <th>{{ $t('dashboard.thSince') }}</th>
                  <th v-if="authStore.isAdmin">{{ $t('dashboard.thAnchor') }}</th>
                  <th v-if="authStore.isAdmin">{{ $t('dashboard.thCurrentTask') }}</th>
                  <th>{{ $t('common.warning') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in statusDurationRows"
                  :key="row.code"
                  :class="[row.rowClass, row.machineCode ? 'dur-row-clickable' : '']"
                  :title="row.machineCode ? $t('dashboard.machineHistoryHint') : ''"
                  @click="row.machineCode && openMachineHistory(row.machineCode)"
                >
                  <td class="bold-text">{{ row.code }}</td>
                  <td>
                    <span class="dur-status">{{ row.icon }} {{ row.status }}</span>
                  </td>
                  <td class="dur-value">{{ row.durationText }}</td>
                  <td class="dur-since">{{ row.sinceText }}</td>
                  <td v-if="authStore.isAdmin" class="dur-anchor">{{ row.anchorLabel }}</td>
                  <td v-if="authStore.isAdmin" class="dur-task">{{ row.taskTitle || '—' }}</td>
                  <td>
                    <span v-if="row.warningText" class="dur-warn-tag">{{ row.warningText }}</span>
                    <span v-else class="text-muted">—</span>
                  </td>
                </tr>
                <tr v-if="statusDurationRows.length === 0">
                  <td :colspan="authStore.isAdmin ? 7 : 4" class="text-center text-muted">
                    {{ onlyStuckMachines ? $t('dashboard.noStuckMachines') : $t('dashboard.noMachineStatusData') }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

    </main>

    <!-- MOCK printing & DB stats simulator section (Admin/Developer only — WS-011: Dashboard/Giám
         sát phải "chỉ giám sát", nên công cụ giả lập không được lộ ra cho tài khoản trạm MONITORING) -->
    <div v-if="authStore.isAdmin" class="collapsible-mock-section mt-4">
      <button @click="showMockPanel = !showMockPanel" class="mock-panel-toggle">
        {{ $t('dashboard.mockPanelToggle') }}
        <span>{{ showMockPanel ? '▲' : '▼' }}</span>
      </button>

      <div class="mock-panel-body card" v-show="showMockPanel">
        <div class="two-col-grid">
          <!-- Live Workstations readings -->
          <section class="section-sub">
            <h4>{{ $t('dashboard.mockTelemetryTitle') }}</h4>
            <div class="ws-telemetry-row mt-2" v-for="ws in workstations" :key="ws.id">
              <div class="ws-header">
                <strong>{{ $t(ws.nameKey) }}</strong>
                <span :class="['ws-dot', ws.active ? 'dot-active' : 'dot-offline']"></span>
              </div>
              <div class="ws-weight-box">
                <span class="ws-weight">{{ ws.weight.toFixed(2) }} kg</span>
                <span class="ws-time">{{ $t('dashboard.wsUpdatedPrefix') }}{{ ws.lastUpdated }}</span>
              </div>
            </div>
          </section>

          <!-- Printing trigger simulator -->
          <section class="section-sub">
            <h4>{{ $t('dashboard.mockPrintTitle') }}</h4>
            <div class="control-form mt-2">
              <div class="form-group">
                <label>{{ $t('dashboard.targetWorkstationLabel') }}</label>
                <select v-model="targetWorkstation" class="form-select">
                  <option value="WS-01">{{ $t('dashboard.ws01Option') }}</option>
                  <option value="WS-02">{{ $t('dashboard.ws02Option') }}</option>
                </select>
              </div>
              <div class="form-group mt-2">
                <label>{{ $t('dashboard.tsplPayloadLabel') }}</label>
                <textarea v-model="tsplPayload" class="form-control" style="height: 80px; font-family: monospace;"></textarea>
              </div>
              <button @click="triggerMockPrint" class="btn btn-primary mt-2" :disabled="printing">
                {{ printing ? $t('dashboard.sendingLabel') : $t('dashboard.sendPrintCommand') }}
              </button>
              <p v-if="printMessage" :class="printSuccess ? 'text-success' : 'text-error'" class="mt-2">{{ printMessage }}</p>
            </div>
          </section>
        </div>
      </div>
    </div>

    <!-- Lịch sử trạng thái 1 máy VD (yêu cầu 2026-08-21: bấm vào máy ở lưới/bảng để xem).
         Nguồn: /admin/bpdb/machines/{code}/timeline — mỗi task BPDB là một đoạn trạng thái
         của máy, nên bảng này chính là lịch sử đổi trạng thái, không cần API mới. -->
    <div class="modal-backdrop" v-if="machineHistoryOpen" @click.self="closeMachineHistory">
      <div class="timeline-modal">
        <div class="modal-header">
          <h3>{{ $t('dashboard.machineHistoryTitle', { code: machineHistoryCode }) }}</h3>
          <button @click="closeMachineHistory" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div class="history-toolbar">
            <p class="text-muted font-sm mb-0">{{ $t('dashboard.machineHistorySub') }}</p>
            <div class="history-range">
              <button
                v-for="d in [2, 7, 30]"
                :key="'range-' + d"
                class="btn btn-sm"
                :class="machineHistoryDays === d ? 'btn-primary' : 'btn-secondary'"
                @click="setMachineHistoryDays(d)"
              >
                {{ $t('dashboard.machineHistoryRangeDays', { days: d }) }}
              </button>
            </div>
          </div>

          <p v-if="machineHistoryLoading" class="text-muted">{{ $t('common.loading') }}</p>
          <p v-else-if="machineHistoryError" class="text-error">{{ $t('dashboard.machineHistoryError') }}</p>

          <!-- Dải trạng thái theo ngày — cùng dữ liệu với bảng ngay bên dưới, chỉ đổi cách
               nhìn: mỗi ngày một thanh 24h để thấy ngay máy trống vào khung giờ nào. -->
          <div v-if="!machineHistoryLoading && !machineHistoryError && machineHistoryStrips.length" class="state-strips">
            <div class="strip-row strip-ruler">
              <span class="strip-day"></span>
              <div class="strip-track">
                <span v-for="h in 8" :key="'tick-' + h" class="strip-tick" :style="{ left: ((h - 1) * 12.5) + '%' }">
                  {{ String((h - 1) * 3).padStart(2, '0') }}:00
                </span>
              </div>
            </div>
            <div v-for="strip in machineHistoryStrips" :key="strip.key" class="strip-row">
              <span class="strip-day">{{ strip.dayLabel }}</span>
              <div class="strip-track">
                <span
                  v-for="b in strip.blocks"
                  :key="b.key"
                  class="strip-block"
                  :class="b.cls"
                  :style="{ left: b.leftPct + '%', width: b.widthPct + '%' }"
                  :title="b.tip"
                ></span>
              </div>
            </div>
            <div class="strip-legend">
              <span><i class="st-run"></i>{{ $t('dashboard.legendBpdbProcessingLabel') }}</span>
              <span><i class="st-wait"></i>{{ $t('dashboard.legendBpdbWaitingLabel') }}</span>
              <span><i class="st-trans"></i>{{ $t('dashboard.legendBpdbTransitioningLabel') }}</span>
              <span><i class="st-error"></i>{{ $t('dashboard.legendBpdbErrorLabel') }}</span>
              <span><i class="st-cancel"></i>{{ $t('dashboard.legendBpdbCancelledLabel') }}</span>
              <span><i class="st-idle"></i>{{ $t('dashboard.legendBpdbIdleLabel') }}</span>
              <span><i class="st-unknown"></i>{{ $t('dashboard.historyUnknownGap') }}</span>
            </div>
          </div>

          <div v-if="!machineHistoryLoading && !machineHistoryError" class="table-responsive">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ $t('dashboard.thTaskStatus') }}</th>
                  <th>{{ $t('dashboard.thSince') }}</th>
                  <th>{{ $t('dashboard.thUntil') }}</th>
                  <th>{{ $t('dashboard.thTaskDuration') }}</th>
                  <th>{{ $t('dashboard.thIdleAfter') }}</th>
                  <th>{{ $t('dashboard.thCurrentTask') }}</th>
                  <th>{{ $t('dashboard.thTaskError') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in machineHistoryRows" :key="row.key" :class="row.rowClass">
                  <td>
                    <span class="dur-status">{{ row.icon }} {{ row.statusLabel }}</span>
                    <span class="history-status-code">{{ row.status }}</span>
                  </td>
                  <td class="dur-since">{{ row.fromText }}</td>
                  <td class="dur-since">{{ row.toText }}</td>
                  <td class="dur-value">{{ row.durationText }}</td>
                  <td class="dur-value dur-idle-after">{{ row.idleAfterText }}</td>
                  <td class="dur-task">{{ row.title }}</td>
                  <td class="text-error font-xs">{{ row.error }}</td>
                </tr>
                <tr v-if="machineHistoryRows.length === 0">
                  <td colspan="7" class="text-center text-muted">{{ $t('dashboard.machineHistoryEmpty') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeMachineHistory" class="btn btn-secondary">{{ $t('common.close') }}</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';
import echo from '../services/echo';

const { t } = useI18n({ useScope: 'global' });
const authStore = useAuthStore();

const showMockPanel = ref(false);

// Connection Status banner
const connectionStatus = ref('OFFLINE');
const connectionStatusText = ref(t('dashboard.initConnecting'));

// Telemetry State
const workstations = ref([
  { id: 'WS-01', nameKey: 'dashboard.ws1Name', weight: 0.0, active: false, lastUpdated: t('dashboard.noDataShort') },
  { id: 'WS-02', nameKey: 'dashboard.ws2Name', weight: 0.0, active: false, lastUpdated: t('dashboard.noDataShort') }
]);

// Dữ liệu màn hình
const overviewData = ref<any[]>([]);

// Printing mock form state
const targetWorkstation = ref('WS-01');
const tsplPayload = ref(
  "SIZE 80 mm, 50 mm\r\n" +
  "GAP 3 mm, 0 mm\r\n" +
  "CLS\r\n" +
  "QRCODE 50,50,L,6,A,0,\"BATCH-A+110293\"\r\n" +
  "TEXT 50,220,\"3\",0,1,1,\"LOT: A+110293\"\r\n" +
  "PRINT 1\r\n"
);
const printing = ref(false);
const printMessage = ref('');
const printSuccess = ref(true);

// BPDB real machine status (Admin only — TAB 1 overview, chỉ icon + trạng thái) — cùng
// nguồn API với BpdbMachines.vue (/bpdb-machines) nhưng nhúng gọn vào Dashboard theo yêu
// cầu 2026-07-29 (không cần chi tiết task/tank, chỉ cần biết máy "đang là gì").
const bpdbMachines = ref<any[]>([]);

// Thời điểm (đồng hồ máy trạm) nhận được snapshot BPDB gần nhất — dùng để cộng dồn cho
// statusDurationSeconds do server tính, thay vì lấy hiệu new Date() - statusSince. Máy
// trạm nhà xưởng thường lệch giờ so với server/BPDB, nếu trừ trực tiếp sẽ ra số âm hoặc
// vống lên hàng giờ.
const bpdbFetchedAtLocal = ref<number | null>(null);

const fetchBpdbMachines = async () => {
  try {
    const res = await axios.get('/api/admin/bpdb/machines/status');
    bpdbMachines.value = res.data.data;
    bpdbFetchedAtLocal.value = Date.now();
  } catch (err) {
    console.error('Failed to load BPDB machine status:', err);
  }
};

let bpdbPollTimer: ReturnType<typeof setInterval> | null = null;

// Báo cáo "đã ở trạng thái này bao lâu" — đồng hồ nhích mỗi giây để người vận hành thấy
// số đang chạy thật, không phải bảng chết chỉ đổi mỗi 5s theo nhịp poll.
const nowTick = ref(Date.now());
let durationTicker: ReturnType<typeof setInterval> | null = null;
const onlyStuckMachines = ref(false);

const formatDuration = (totalSeconds: number | null): string => {
  if (totalSeconds === null) return t('dashboard.durationUnknown');
  const s = Math.max(0, Math.floor(totalSeconds));
  const days = Math.floor(s / 86400);
  const hours = Math.floor((s % 86400) / 3600);
  const minutes = Math.floor((s % 3600) / 60);
  const seconds = s % 60;
  if (days > 0) return t('dashboard.durationDaysHours', { days, hours });
  if (hours > 0) return t('dashboard.durationHoursMinutes', { hours, minutes });
  if (minutes > 0) return t('dashboard.durationMinutesSeconds', { minutes, seconds });
  return t('dashboard.durationSeconds', { seconds });
};

// --- Lịch sử trạng thái của 1 máy (modal khi bấm vào máy) ------------------------------
// Chỉ Admin: endpoint /admin/bpdb/machines/... yêu cầu role ADMIN ở backend, tài khoản
// thường không gọi được nên không bật click cho lưới fallback (app.machines).
const machineHistoryOpen = ref(false);
const machineHistoryCode = ref('');
const machineHistoryDays = ref(7);
const machineHistoryLoading = ref(false);
const machineHistoryError = ref(false);
const machineHistoryRaw = ref<any[]>([]);

const fetchMachineHistory = async () => {
  if (!machineHistoryCode.value) return;
  machineHistoryLoading.value = true;
  machineHistoryError.value = false;
  try {
    // BPDB chạy giờ Việt Nam còn máy trạm có thể lệch — gửi mốc theo giờ VN như backend
    // vẫn tự làm khi thiếu tham số, chỉ khác là đổi được số ngày.
    const res = await axios.get(
      `/api/admin/bpdb/machines/${encodeURIComponent(machineHistoryCode.value)}/timeline`,
      { params: { from: vnDateTimeDaysAgo(machineHistoryDays.value), to: vnDateTimeDaysAgo(0), limit: 200 } }
    );
    machineHistoryRaw.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load machine status history:', err);
    machineHistoryError.value = true;
    machineHistoryRaw.value = [];
  } finally {
    machineHistoryLoading.value = false;
  }
};

// Mốc 'Y-m-d H:i:s' theo giờ Việt Nam, không phụ thuộc múi giờ máy trạm.
const vnDateTimeDaysAgo = (days: number): string => {
  const vnNow = new Date(Date.now() + 7 * 3600 * 1000 - days * 86400 * 1000);
  return vnNow.toISOString().slice(0, 19).replace('T', ' ');
};

const openMachineHistory = (code: string) => {
  if (!authStore.isAdmin || !code) return;
  machineHistoryCode.value = code;
  machineHistoryOpen.value = true;
  fetchMachineHistory();
};

const closeMachineHistory = () => {
  machineHistoryOpen.value = false;
  machineHistoryRaw.value = [];
};

const setMachineHistoryDays = (days: number) => {
  machineHistoryDays.value = days;
  fetchMachineHistory();
};

// Nhãn tiếng người của 7 trạng thái vận hành — DÙNG LẠI đúng chuỗi của bảng "Chú thích
// trạng thái" ngay trên Dashboard, để lịch sử và lưới máy không bao giờ gọi tên khác nhau
// cho cùng một trạng thái.
const OPERATIONAL_STATUS_LABEL_KEYS: Record<string, string> = {
  PROCESSING: 'dashboard.legendBpdbProcessingLabel',
  WAITING: 'dashboard.legendBpdbWaitingLabel',
  TRANSITIONING: 'dashboard.legendBpdbTransitioningLabel',
  COMPLETED_RECENTLY: 'dashboard.legendBpdbCompletedLabel',
  CANCELLED: 'dashboard.legendBpdbCancelledLabel',
  ERROR: 'dashboard.legendBpdbErrorLabel',
  IDLE: 'dashboard.legendBpdbIdleLabel',
  // Không có trong lưới máy: chỉ lịch sử mới phân biệt được "khoảng không có bằng chứng".
  UNKNOWN: 'dashboard.historyUnknownGap',
};

// Khoảng trống giữa 2 task ngắn hơn ngưỡng này thì không dựng dòng IDLE riêng — nếu không
// bảng sẽ đầy những dòng "nhàn rỗi 12 giây" vô nghĩa giữa các lần pha liên tiếp.
const IDLE_GAP_MIN_SECONDS = 60;

// BPDB không lưu "lịch sử đổi trạng thái" — chỉ lưu các mốc của từng task. Lịch sử trạng
// thái vì vậy được DỰNG LẠI từ các mốc đó, dùng đúng quy tắc mà backend dùng để suy trạng
// thái hiện tại (xem BpdbMachineMonitoringService::reduceMachineStatus):
//   CreateTime -> WorkStartTime : máy đang CHỜ task chạy
//   WorkStartTime -> FinishTime : máy ĐANG XỬ LÝ (hoặc CHUYỂN TRẠNG THÁI / LỖI / ĐÃ HỦY)
//   FinishTime -> task kế tiếp  : máy TRỐNG (vừa hoàn thành nếu trong 24h, sau đó nhàn rỗi)
const machineHistoryRows = computed(() => {
  const tasks = [...machineHistoryRaw.value]
    .filter((task: any) => task.CreateTime)
    .sort((a: any, b: any) => new Date(a.CreateTime).getTime() - new Date(b.CreateTime).getTime());

  const segments: any[] = [];
  const ms = (v: string | null | undefined) => (v ? new Date(v).getTime() : null);
  let prevEnd: number | null = null;
  // prevEnd có phải mốc kết thúc CHẮC CHẮN (từ MES) hay chỉ là giờ pha xong của BPDB.
  let prevEndKnown = false;

  const push = (status: string, fromMs: number, toMs: number | null, task: any, ongoing: boolean) => {
    const endMs = toMs ?? nowTick.value;
    const seconds = Math.max(0, Math.floor((endMs - fromMs) / 1000));
    segments.push({
      key: `${task?.Id ?? 'gap'}-${status}-${fromMs}`,
      status,
      icon: bpdbStatusIcon(status),
      statusLabel: t(OPERATIONAL_STATUS_LABEL_KEYS[status] ?? status),
      fromText: formatTime(new Date(fromMs).toISOString()),
      toText: ongoing ? t('dashboard.historyOngoing') : formatTime(new Date(endMs).toISOString()),
      durationText: formatDuration(seconds),
      title: task?.TaskTitle || '—',
      error: task?.ErrorMsg || '',
      // Mốc số (không phải chuỗi đã format) để tính cột "Dừng tới mẻ sau" ở lượt quét
      // thứ hai bên dưới — lúc push chưa biết mẻ kế tiếp bắt đầu khi nào.
      fromMs,
      endMs: ongoing ? null : endMs,
      idleAfterText: '—',
      // Đoạn còn "đang tiếp diễn" quá 24h là dấu hiệu task BPDB chưa được đóng (task
      // orphan) chứ không phải máy chạy thật — tô cảnh báo để không bị đọc nhầm.
      rowClass: status === 'ERROR'
        ? 'dur-row-danger'
        : (status === 'CANCELLED' || (ongoing && seconds > 24 * 3600) ? 'dur-row-warn' : ''),
    });
  };

  // Các mẻ MES đã dựng đoạn — mỗi mẻ chỉ một lần, xem giải thích ở vòng lặp bên dưới.
  const seenBatches = new Set<string>();

  tasks.forEach((task: any) => {
    const created = ms(task.CreateTime)!;
    const started = ms(task.WorkStartTime);
    // FinishTime của BPDB là lúc PHA THUỐC xong (đo 22/08: trung bình 16 phút/task), KHÔNG
    // phải lúc máy rảnh — mẻ nhuộm chạy tiếp trung bình ~20 giờ sau đó. Ưu tiên giờ nhuộm
    // xong thật từ MES (backend gắn sẵn ở MesEndTime), đúng nguồn mà Gantt đang dùng.
    const mesEnd = ms(task.MesEndTime);
    const finished = mesEnd ?? ms(task.FinishTime);
    const rawStatus = Number(task.TaskStatus);
    const isDeleted = !!task.IsDeleted;

    // Một mẻ được pha nhiều lần trong lúc đang nhuộm; các lần pha sau nằm TRỌN trong đoạn
    // chạy đã dựng từ lần pha đầu, nên bỏ qua — nếu không bảng sẽ có 5-6 dòng "đang xử lý"
    // trùng giờ nhau cho cùng một mẻ.
    const batchKey = task.MesBatchNo ? `${task.MesBatchNo}|${task.MesLineNo ?? '1'}` : null;
    if (batchKey !== null) {
      if (seenBatches.has(batchKey)) return;
      seenBatches.add(batchKey);
    }

    // Khoảng giữa task trước và task này. CHỈ dám gọi là "máy trống" khi biết chắc mẻ
    // trước đã nhuộm xong (mốc kết thúc lấy từ MES). Nếu mốc kết thúc chỉ là FinishTime của
    // BPDB (pha xong ~16 phút) thì mẻ nhiều khả năng VẪN ĐANG NHUỘM — gọi là nhàn rỗi là
    // sai, nên đánh dấu KHÔNG RÕ thay vì bịa ra trạng thái không có bằng chứng.
    if (prevEnd !== null && created - prevEnd >= IDLE_GAP_MIN_SECONDS * 1000) {
      const gapStatus = !prevEndKnown
        ? 'UNKNOWN'
        : (prevEnd >= nowTick.value - 24 * 3600 * 1000 ? 'COMPLETED_RECENTLY' : 'IDLE');
      push(gapStatus, prevEnd, created, null, false);
    }

    // Đoạn CHỜ: từ lúc tạo task tới lúc thật sự bắt đầu chạy.
    const waitEnd = started ?? finished;
    if (waitEnd === null) {
      // Task chưa từng chạy và chưa đóng -> vẫn đang chờ tới bây giờ.
      push(isDeleted || rawStatus === 99 ? 'CANCELLED' : 'WAITING', created, null, task, !isDeleted && rawStatus !== 99);
      prevEnd = null;
      prevEndKnown = false;
      return;
    }
    if (waitEnd - created >= IDLE_GAP_MIN_SECONDS * 1000) {
      push('WAITING', created, waitEnd, task, false);
    }

    // Đoạn CHẠY: trạng thái lấy theo cùng thứ tự ưu tiên với backend — lỗi > hủy/xóa >
    // mã trạng thái thô của BPDB.
    const runFrom = started ?? created;
    const runStatus = task.ErrorMsg
      ? 'ERROR'
      : (isDeleted || rawStatus === 99 ? 'CANCELLED' : (rawStatus === 20 ? 'TRANSITIONING' : 'PROCESSING'));
    push(runStatus, runFrom, finished, task, finished === null);
    // Đoạn chạy chỉ được coi là "biết chắc đã kết thúc" khi mốc kết thúc đến từ MES — cột
    // "Dừng tới mẻ sau" chỉ tính cho những đoạn như vậy.
    segments[segments.length - 1].endKnown = mesEnd !== null;

    prevEnd = finished;
    prevEndKnown = mesEnd !== null;
  });

  // Khoảng từ task cuối cùng tới bây giờ — cùng luật với các khoảng ở giữa.
  if (prevEnd !== null && nowTick.value - prevEnd >= IDLE_GAP_MIN_SECONDS * 1000) {
    const tailStatus = !prevEndKnown
      ? 'UNKNOWN'
      : (nowTick.value - prevEnd <= 24 * 3600 * 1000 ? 'COMPLETED_RECENTLY' : 'IDLE');
    push(tailStatus, prevEnd, null, null, true);
  }

  // Cột "Dừng tới mẻ sau": với mỗi đoạn máy ĐANG CHIẾM (chạy/chuyển/lỗi), đo tới lúc máy
  // thật sự chạy lại — tức mốc bắt đầu của đoạn RUN kế tiếp, KHÔNG phải mốc tạo task kế
  // tiếp. Task thường được tạo trước hàng giờ so với lúc máy chạy thật, lấy mốc tạo sẽ
  // báo thiếu thời gian dừng. Đoạn cuối chưa có mẻ kế tiếp thì để trống thay vì đo tới
  // hiện tại — dòng IDLE/COMPLETED_RECENTLY cuối bảng đã nói đúng điều đó rồi.
  const RUN_STATUSES = ['PROCESSING', 'TRANSITIONING', 'ERROR'];
  segments.forEach((seg, i) => {
    if (!RUN_STATUSES.includes(seg.status) || seg.endMs === null) return;
    // Không có giờ kết thúc thật từ MES thì không biết mẻ nhuộm xong lúc nào — báo một con
    // số "dừng 4 giờ" ở đây là bịa, để trống đúng hơn.
    if (!seg.endKnown) return;
    const next = segments.slice(i + 1).find((s) => RUN_STATUSES.includes(s.status));
    if (!next) return;
    const idleSeconds = Math.floor((next.fromMs - seg.endMs) / 1000);
    if (idleSeconds >= IDLE_GAP_MIN_SECONDS) {
      seg.idleAfterText = formatDuration(idleSeconds);
    }
  });

  // Mới nhất lên đầu — người vận hành quan tâm "vừa rồi máy làm gì" trước tiên.
  return segments.reverse();
});

const HISTORY_STATE_CLASS: Record<string, string> = {
  PROCESSING: 'st-run',
  TRANSITIONING: 'st-trans',
  WAITING: 'st-wait',
  ERROR: 'st-error',
  CANCELLED: 'st-cancel',
  IDLE: 'st-idle',
  COMPLETED_RECENTLY: 'st-idle',
  UNKNOWN: 'st-unknown',
};

// Dải trạng thái: mỗi ngày một thanh ngang 24h, tô lại ĐÚNG các đoạn của bảng bên dưới —
// dùng chung machineHistoryRows chứ không tính lại, để biểu đồ và bảng không bao giờ lệch
// nhau. Khoảng nào không có đoạn nào phủ thì để nền trơ của track (nghĩa là máy trống,
// cùng màu với IDLE).
const machineHistoryStrips = computed(() => {
  const segs = [...machineHistoryRows.value].sort((a: any, b: any) => a.fromMs - b.fromMs);
  if (segs.length === 0) return [];

  const lastEnd = segs.reduce((max: number, s: any) => Math.max(max, s.endMs ?? nowTick.value), 0);
  const cursor = new Date(segs[0].fromMs);
  cursor.setHours(0, 0, 0, 0);

  const strips: any[] = [];
  while (cursor.getTime() < lastEnd) {
    const dayStart = cursor.getTime();
    const nextDay = new Date(cursor);
    nextDay.setDate(nextDay.getDate() + 1);
    const dayEnd = nextDay.getTime();
    // Lấy độ dài thật của ngày thay vì hằng số 24h — ngày đổi giờ mùa vẫn ra đúng tỉ lệ.
    const span = dayEnd - dayStart;

    const blocks: any[] = [];
    segs.forEach((s: any) => {
      const from = Math.max(s.fromMs, dayStart);
      const to = Math.min(s.endMs ?? nowTick.value, dayEnd);
      if (to <= from) return;
      const seconds = Math.floor((to - from) / 1000);
      blocks.push({
        key: `${s.key}-${dayStart}`,
        cls: HISTORY_STATE_CLASS[s.status] ?? 'st-idle',
        leftPct: ((from - dayStart) / span) * 100,
        widthPct: ((to - from) / span) * 100,
        tip: [
          s.statusLabel,
          `${formatTime(new Date(from).toISOString())} → ${formatTime(new Date(to).toISOString())}`,
          formatDuration(seconds),
          s.title && s.title !== '—' ? s.title : null,
        ].filter(Boolean).join(' · '),
      });
    });

    strips.push({
      key: dayStart,
      dayLabel: new Date(dayStart).toLocaleDateString(undefined, { day: '2-digit', month: '2-digit' }),
      blocks,
    });
    cursor.setDate(cursor.getDate() + 1);
  }

  // Ngày mới nhất lên đầu, cùng chiều với bảng bên dưới.
  return strips.reverse();
});

const anchorLabelKeys: Record<string, string> = {
  WORK_START: 'dashboard.anchorWorkStart',
  CREATE: 'dashboard.anchorCreate',
  FINISH: 'dashboard.anchorFinish',
  LAST_ACTIVITY: 'dashboard.anchorLastActivity',
  BATCH_UPDATED_AT: 'dashboard.anchorBatchUpdatedAt',
};

const anchorLabel = (source: string): string =>
  anchorLabelKeys[source] ? t(anchorLabelKeys[source]) : '—';

const warningLabel = (w: any): string => {
  if (!w) return '';
  const minutes = w.minutes != null ? Math.round(w.minutes) : null;
  switch (w.code) {
    case 'WAITING_TOO_LONG':
      return t('dashboard.warnWaitingTooLong', { minutes, threshold: w.threshold });
    case 'TRANSITION_STUCK':
      return t('dashboard.warnTransitionStuck', { minutes, threshold: w.threshold });
    case 'PROCESSING_TOO_LONG':
      return t('dashboard.warnProcessingTooLong', { minutes, threshold: w.threshold });
    case 'ABORTED_OR_STALE':
      return t('dashboard.warnAbortedOrStale');
    case 'DATA_INCONSISTENT':
      return t('dashboard.warnDataInconsistent');
    default:
      return w.code;
  }
};

// So sánh mã máy theo thứ tự tự nhiên: "VD9" phải đứng trước "VD10", không phải sau như
// khi so sánh chuỗi thuần. Mã hiện tại có padding số ("VD003") nên chuỗi thuần vẫn đúng,
// nhưng numeric:true để không vỡ nếu sau này có mã không padding.
const compareMachineCode = (a: string, b: string) =>
  String(a).localeCompare(String(b), 'vi', { numeric: true, sensitivity: 'base' });

const statusDurationRows = computed(() => {
  const elapsedSinceFetch = bpdbFetchedAtLocal.value
    ? Math.floor((nowTick.value - bpdbFetchedAtLocal.value) / 1000)
    : 0;

  const rows = authStore.isAdmin
    ? bpdbMachines.value.map((m: any) => {
        const base = m.statusDurationSeconds;
        const idleNoData = m.operationalStatus === 'IDLE' && !m.statusSince;
        const seconds = base != null ? base + elapsedSinceFetch : null;

        return {
          code: m.displayName || m.machineCode,
          machineCode: m.machineCode,
          status: m.operationalStatus,
          icon: bpdbStatusIcon(m.operationalStatus),
          durationText: idleNoData ? t('dashboard.durationOver30Days') : formatDuration(seconds),
          sinceText: m.statusSince ? formatTime(m.statusSince) : (idleNoData ? t('dashboard.noTaskIn30Days') : '—'),
          anchorLabel: anchorLabel(m.statusSinceSource),
          taskTitle: m.currentTask?.taskTitle || null,
          warningText: warningLabel(m.stuckWarning),
          hasWarning: !!m.stuckWarning || m.operationalStatus === 'ERROR',
          rowClass: m.operationalStatus === 'ERROR'
            ? 'dur-row-danger'
            : (m.stuckWarning ? 'dur-row-warn' : ''),
        };
      })
    : overviewData.value.map((m: any) => {
        const seconds = m.status_since
          ? Math.max(0, Math.floor((nowTick.value - new Date(m.status_since).getTime()) / 1000))
          : null;

        return {
          code: m.machine_code,
          machineCode: null,
          status: m.status,
          icon: appStatusIcon(m.status),
          durationText: formatDuration(seconds),
          sinceText: m.status_since ? formatTime(m.status_since) : '—',
          anchorLabel: anchorLabel(m.status_since_source),
          taskTitle: m.current_batch?.legacy_batch_id || null,
          warningText: '',
          hasWarning: false,
          rowClass: '',
        };
      });

  return rows
    .filter((r) => !onlyStuckMachines.value || r.hasWarning)
    .sort((a, b) => compareMachineCode(a.code, b.code));
});

// Icon theo trạng thái BPDB (operationalStatus) và trạng thái nội bộ (app.machines) — chỉ
// mang tính minh họa nhanh trên Dashboard, không thay thế nhãn chữ đứng cạnh.
const bpdbStatusIcon = (status: string) => {
  const mapping: Record<string, string> = {
    PROCESSING: '⚙️',
    WAITING: '⏳',
    TRANSITIONING: '🔄',
    COMPLETED_RECENTLY: '✅',
    CANCELLED: '🚫',
    ERROR: '❌',
    IDLE: '💤',
  };
  return mapping[status] || '❔';
};

const appStatusIcon = (status: string) => {
  const mapping: Record<string, string> = {
    IDLE: '💤',
    NEW: '🆕',
    READY_TO_WEIGH: '⚖️',
    WEIGHING: '⚖️',
    WEIGHED: '✅',
    SENT: '🚀',
    DONE: '🏁',
  };
  return mapping[status] || '❔';
};

// Chú thích trạng thái hiển thị cạnh lưới máy — BPDB: khớp logic suy luận operationalStatus
// ở BpdbMachineMonitoringService::reduceMachineStatus (backend); nội bộ: khớp state machine
// Batch trong .claude/rules/architecture-workflow.md.
const bpdbStatusLegend = computed(() => [
  { status: 'PROCESSING', icon: '⚙️', label: t('dashboard.legendBpdbProcessingLabel'), desc: t('dashboard.legendBpdbProcessingDesc') },
  { status: 'WAITING', icon: '⏳', label: t('dashboard.legendBpdbWaitingLabel'), desc: t('dashboard.legendBpdbWaitingDesc') },
  { status: 'TRANSITIONING', icon: '🔄', label: t('dashboard.legendBpdbTransitioningLabel'), desc: t('dashboard.legendBpdbTransitioningDesc') },
  { status: 'COMPLETED_RECENTLY', icon: '✅', label: t('dashboard.legendBpdbCompletedLabel'), desc: t('dashboard.legendBpdbCompletedDesc') },
  { status: 'CANCELLED', icon: '🚫', label: t('dashboard.legendBpdbCancelledLabel'), desc: t('dashboard.legendBpdbCancelledDesc') },
  { status: 'ERROR', icon: '❌', label: t('dashboard.legendBpdbErrorLabel'), desc: t('dashboard.legendBpdbErrorDesc') },
  { status: 'IDLE', icon: '💤', label: t('dashboard.legendBpdbIdleLabel'), desc: t('dashboard.legendBpdbIdleDesc') },
]);

const appStatusLegend = computed(() => [
  { status: 'IDLE', icon: '💤', label: t('dashboard.legendAppIdleLabel'), desc: t('dashboard.legendAppIdleDesc') },
  { status: 'NEW', icon: '🆕', label: t('dashboard.legendAppNewLabel'), desc: t('dashboard.legendAppNewDesc') },
  { status: 'READY_TO_WEIGH', icon: '⚖️', label: t('dashboard.legendAppReadyLabel'), desc: t('dashboard.legendAppReadyDesc') },
  { status: 'WEIGHING', icon: '⚖️', label: t('dashboard.legendAppWeighingLabel'), desc: t('dashboard.legendAppWeighingDesc') },
  { status: 'WEIGHED', icon: '✅', label: t('dashboard.legendAppWeighedLabel'), desc: t('dashboard.legendAppWeighedDesc') },
  { status: 'SENT', icon: '🚀', label: t('dashboard.legendAppSentLabel'), desc: t('dashboard.legendAppSentDesc') },
  { status: 'DONE', icon: '🏁', label: t('dashboard.legendAppDoneLabel'), desc: t('dashboard.legendAppDoneDesc') },
]);


// Setup Realtime Connection — qua Reverb (kênh public "dashboard-events"), thay cho SSE
// cũ (/api/realtime/stream) đã bị gỡ 2026-07-25 vì giữ 1 kết nối HTTP sống mãi khiến
// php artisan serve trên Windows (không có concurrency thật) bị treo chỉ với 1 tab mở.
let liveWeightPoller: any = null;

const fetchLiveWeights = async () => {
  for (const ws of workstations.value) {
    try {
      const res = await axios.get(`/api/devices/readings/${ws.id}`);
      const live = res.data;
      ws.active = !!live?.active;
      ws.weight = live?.weight ?? 0;
      ws.lastUpdated = ws.active ? new Date().toLocaleTimeString('vi-VN', { hour12: false }) : t('dashboard.noDataShort');
    } catch {
      // Bỏ qua lỗi 1 lần đọc — sẽ tự thử lại ở vòng poll tiếp theo.
    }
  }
};

const initRealtimeConnection = () => {
  // 1. Theo dõi trạng thái kết nối WebSocket (Reverb) qua pusher-js connector.
  const pusherConnection = (echo.connector as any)?.pusher?.connection;
  const mapping: Record<string, string> = {
    connected: t('dashboard.connStateConnected'),
    connecting: t('dashboard.connStateConnecting'),
    unavailable: t('dashboard.connStateUnavailable'),
    disconnected: t('dashboard.connStateDisconnected'),
    failed: t('dashboard.connStateDisconnected'),
  };
  const applyStatus = (state: string) => {
    connectionStatus.value = state === 'connected' ? 'ONLINE' : (state === 'connecting' ? 'RECONNECTING' : 'OFFLINE');
    connectionStatusText.value = mapping[state] || t('dashboard.connStateWaiting');
  };
  if (pusherConnection) {
    applyStatus(pusherConnection.state);
    pusherConnection.bind('state_change', (states: any) => applyStatus(states.current));
  }

  // 2. Lắng nghe mọi sự kiện nghiệp vụ (batch/dispatch/alert/...) qua kênh public — làm mới
  // snapshot ngay khi có thay đổi thay vì đợi polling.
  echo.channel('dashboard-events').listen('.event', () => {
    fetchOverviewSnapshot();
  });

  // 3. Cân điện tử đọc trọng lượng live qua REST polling nhẹ (giống WeighingStation.vue),
  // không cần kênh riêng vì tần suất đổi rất nhanh (mỗi giây) không hợp broadcast từng sự kiện.
  fetchLiveWeights();
  liveWeightPoller = setInterval(fetchLiveWeights, 2000);

  return () => {
    echo.leaveChannel('dashboard-events');
    if (pusherConnection) {
      pusherConnection.unbind('state_change');
    }
    if (liveWeightPoller) {
      clearInterval(liveWeightPoller);
      liveWeightPoller = null;
    }
  };
};

let disposeRealtime: any = null;

onMounted(() => {
  // Trạng thái máy cho tài khoản KHÔNG phải admin lấy từ bảng nội bộ app.machines —
  // admin dùng nguồn BPDB ở dưới, nhưng cả hai đều cần snapshot này cho bảng "đã kéo dài".
  fetchOverviewSnapshot();

  if (authStore.isAdmin) {
    fetchBpdbMachines();
    // 5s theo đúng cadence của BpdbMachines.vue — backend đã cache ~4s nên nhiều tab admin
    // mở cùng lúc không dội query trực tiếp vào BPDB.
    bpdbPollTimer = setInterval(fetchBpdbMachines, 5000);
  }

  disposeRealtime = initRealtimeConnection();
  durationTicker = setInterval(() => { nowTick.value = Date.now(); }, 1000);
});

onUnmounted(() => {
  if (disposeRealtime) {
    disposeRealtime();
  }
  if (bpdbPollTimer) {
    clearInterval(bpdbPollTimer);
  }
  if (durationTicker) {
    clearInterval(durationTicker);
  }
});

// Snapshot API Pulls
const fetchOverviewSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/overview');
    overviewData.value = res.data.data.overview;
  } catch (err) {
    console.error('Failed to load overview:', err);
  }
};

// Mock Printing simulation
const triggerMockPrint = async () => {
  printing.value = true;
  printMessage.value = '';
  try {
    const response = await axios.post('/api/print-jobs', {
      workstation_id: targetWorkstation.value,
      label_payload: tsplPayload.value,
      printer_connection_type: 'USB',
      printer_address: 'PRINTER_DYES_1'
    });

    if (response.data.status === 'SUCCESS') {
      printSuccess.value = true;
      printMessage.value = t('dashboard.printSuccessMsg', { id: response.data.data.id });
      // Trigger local agent heartbeat refresh in UI
      fetchOverviewSnapshot();
    } else {
      printSuccess.value = false;
      printMessage.value = t('dashboard.printFailMsg');
    }
  } catch (err: any) {
    printSuccess.value = false;
    printMessage.value = err.response?.data?.message || t('dashboard.printServerErrorMsg');
  } finally {
    printing.value = false;
  }
};

const formatTime = (dateStr: string) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', { hour12: false });
};

</script>

<style scoped>
.realtime-control-center {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

/* Connection Status banner */
.connection-status-banner {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px var(--space-xl);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.status-indicator-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--status-grey);
}

.banner-online {
  background-color: var(--status-green-bg);
  border: 1px solid var(--status-green-border);
  color: var(--status-green);
}
.banner-online .status-indicator-dot {
  background-color: var(--status-green);
  box-shadow: 0 0 8px var(--status-green);
  animation: blink 2s infinite;
}

.banner-reconnecting, .banner-fallback {
  background-color: var(--status-yellow-bg);
  border: 1px solid var(--status-yellow-border);
  color: var(--status-yellow);
}
.banner-reconnecting .status-indicator-dot, .banner-fallback .status-indicator-dot {
  background-color: var(--status-yellow);
  box-shadow: 0 0 8px var(--status-yellow);
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

/* Tab Navigation styles */
.tabs-nav-bar {
  display: flex;
  gap: var(--space-sm);
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  padding: 6px;
  border-radius: var(--radius-lg);
  overflow-x: auto;
}

.tab-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  height: 38px;
  padding: 0 var(--space-lg);
  border-radius: var(--radius-md);
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.tab-nav-btn:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}

.tab-nav-btn.active {
  background-color: var(--primary);
  color: var(--text-white);
  box-shadow: var(--shadow-sm);
}

.dashboard-tab-content {
  flex: 1;
}

/* Tab 1: Overall overview machines grid */
.machines-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-xl);
}

.machine-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  position: relative;
  overflow: hidden;
}

.machine-card:hover {
  transform: translateY(-2px);
  border-color: var(--border-card-hover);
  box-shadow: var(--shadow-md);
}

.machine-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background-color: var(--status-grey);
}

/* Card States color themes */
.card-state-idle::before { background-color: var(--status-grey); }
.card-state-new::before, .card-state-ready_to_weigh::before { background-color: var(--status-blue); }
.card-state-weighing::before { background-color: var(--status-yellow); }
.card-state-weighed::before { background-color: var(--status-green); }
.card-state-sent::before { background-color: var(--status-orange); }

.has-alert {
  border-color: var(--status-red) !important;
  box-shadow: 0 0 12px rgba(244, 63, 94, 0.15) !important;
}

.m-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.m-code {
  font-family: 'Outfit', sans-serif;
  font-weight: 700;
  font-size: 1.2rem;
  color: var(--text-title);
}

.m-connection-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.conn-idle { background-color: var(--status-grey); }
.conn-running { background-color: var(--status-green); box-shadow: 0 0 6px var(--status-green); }

.m-idle-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl) 0;
  gap: 6px;
  color: var(--text-disabled);
}

.idle-icon {
  font-size: 2.2rem;
  opacity: 0.25;
}

.idle-text {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.code-link {
  color: var(--primary-hover);
  text-decoration: underline;
  cursor: pointer;
}

.m-meta-text {
  font-size: 12px;
  color: var(--text-muted);
}

.progress-bar-container {
  width: 100%;
  height: 5px;
  background-color: var(--border-divider);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.progress-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-disabled);
  margin-top: 2px;
}

.m-alert-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--status-red);
  background-color: var(--status-red-bg);
  padding: 2px 6px;
  border-radius: var(--radius-sm);
  display: inline-block;
}

/* Bố cục Tab 1: lưới trạng thái máy bên trái + bảng chú thích bên phải (yêu cầu
   2026-07-29). wrap để tự xuống dòng trên màn hình hẹp/tablet dọc thay vì bị bóp méo. */
.overview-status-layout {
  display: flex;
  gap: var(--space-xl);
  align-items: flex-start;
  flex-wrap: wrap;
}

.overview-status-layout .machines-grid-simple {
  flex: 3 1 480px;
}

.status-legend {
  flex: 1 1 240px;
  max-width: 320px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
}

.legend-title {
  font-size: 0.85rem;
  color: var(--text-title);
  margin-bottom: var(--space-md);
}

.legend-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.legend-item {
  display: flex;
  gap: var(--space-sm);
  align-items: flex-start;
}

.legend-icon { font-size: 1.1rem; line-height: 1.3; }

.legend-head {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-wrap: wrap;
}

.legend-head strong { font-size: 0.82rem; color: var(--text-title); }

.legend-code {
  font-size: 0.62rem;
  font-family: monospace;
  color: var(--text-disabled);
}

.legend-desc {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 2px;
}

/* Trạng thái máy đơn giản (Tab 1) — chỉ icon + mã máy + nhãn trạng thái, dùng chung cho
   cả lưới BPDB (Admin) và lưới nội bộ (non-admin), theo yêu cầu 2026-07-29. */
.machines-grid-simple {
  display: grid;
  /* auto-fit (thay vì auto-fill) để ô tự giãn lấp đầy hàng khi ít máy, không để trống
     cột thừa; clamp() cho min-width co giãn mượt theo bề rộng màn hình/tablet nhà xưởng
     thay vì nhảy bậc theo breakpoint cố định (yêu cầu "linh động" 2026-07-29). */
  grid-template-columns: repeat(auto-fit, minmax(clamp(110px, 12vw, 160px), 1fr));
  gap: clamp(var(--space-sm), 1.5vw, var(--space-md));
}

.machine-status-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-top: 3px solid var(--status-grey);
  border-radius: var(--radius-lg);
  padding: clamp(0.6rem, 1.4vw, var(--space-lg)) var(--space-sm);
  text-align: center;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.machine-status-tile:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.tile-icon { font-size: clamp(1.4rem, 2.4vw, 1.9rem); line-height: 1; }
.tile-code { font-weight: 700; color: var(--text-title); font-size: clamp(0.8rem, 1.1vw, 0.95rem); }
.tile-status { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; color: var(--text-muted); }

.machine-status-tile.bpdb-status-processing { border-top-color: #2563eb; }
.machine-status-tile.bpdb-status-waiting { border-top-color: #ca8a04; }
.machine-status-tile.bpdb-status-transitioning { border-top-color: #9333ea; }
.machine-status-tile.bpdb-status-completed_recently { border-top-color: #16a34a; }
.machine-status-tile.bpdb-status-cancelled, .machine-status-tile.bpdb-status-error { border-top-color: #dc2626; }
.machine-status-tile.bpdb-status-idle { border-top-color: #9ca3af; }

.machine-status-tile.card-state-idle { border-top-color: var(--status-grey); }
.machine-status-tile.card-state-new, .machine-status-tile.card-state-ready_to_weigh { border-top-color: var(--status-blue); }
.machine-status-tile.card-state-weighing { border-top-color: var(--status-yellow); }
.machine-status-tile.card-state-weighed { border-top-color: var(--status-green); }
.machine-status-tile.card-state-sent { border-top-color: var(--status-orange); }

/* Báo cáo thời lượng trạng thái máy (dưới lưới Tab 1) */
.status-duration-report {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-lg) var(--space-xl);
}

.report-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: var(--space-lg);
  flex-wrap: wrap;
  margin-bottom: var(--space-md);
}

.report-title {
  font-size: 0.95rem;
  color: var(--text-title);
}

.report-sub {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 2px;
  max-width: 70ch;
}

.report-filter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.78rem;
  color: var(--text-muted);
  cursor: pointer;
  white-space: nowrap;
}

.dur-status {
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.dur-value {
  font-family: monospace;
  font-weight: 700;
  color: var(--text-title);
  white-space: nowrap;
}

/* ── Dải trạng thái theo ngày trong modal Lịch sử trạng thái máy ── */
.state-strips {
  margin-bottom: 16px;
}

.strip-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 3px;
}

.strip-day {
  flex: 0 0 46px;
  font-size: 0.7rem;
  font-family: monospace;
  color: var(--text-muted);
  text-align: right;
}

.strip-track {
  position: relative;
  flex: 1;
  height: 18px;
  /* Nền track = máy trống: khoảng nào không có đoạn nào phủ lên thì hiểu là không có mẻ,
     cùng màu với ô IDLE để không phải vẽ thêm block rỗng cho từng khe. */
  background: var(--bg-muted, #e9ecef);
  border-radius: 3px;
  overflow: hidden;
}

.strip-block {
  position: absolute;
  top: 0;
  bottom: 0;
  /* Mẻ ngắn vài phút vẫn phải thấy được, nếu không dải sẽ nuốt mất các lần pha ngắn. */
  min-width: 2px;
}

.strip-block.st-run { background: #2f9e44; }
.strip-block.st-trans { background: #1c7ed6; }
.strip-block.st-wait { background: #f59f00; }
.strip-block.st-error { background: #e03131; }
.strip-block.st-cancel { background: #868e96; }
.strip-block.st-idle { background: var(--bg-muted, #e9ecef); }
/* Sọc chéo = không có bằng chứng, cố tình KHÔNG dùng màu đặc để không bị đọc thành một
   trạng thái đã xác định. */
.strip-block.st-unknown {
  background: repeating-linear-gradient(45deg, #ced4da 0 4px, #f1f3f5 4px 8px);
}

.strip-ruler .strip-track {
  height: 14px;
  background: none;
  overflow: visible;
}

.strip-tick {
  position: absolute;
  top: 0;
  font-size: 0.65rem;
  font-family: monospace;
  color: var(--text-muted);
  transform: translateX(-50%);
}

.strip-ruler .strip-tick:first-child { transform: none; }

.strip-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 8px 0 0 54px;
  font-size: 0.7rem;
  color: var(--text-muted);
}

.strip-legend i {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 2px;
  margin-right: 4px;
  vertical-align: -1px;
}

.strip-legend .st-run { background: #2f9e44; }
.strip-legend .st-trans { background: #1c7ed6; }
.strip-legend .st-wait { background: #f59f00; }
.strip-legend .st-error { background: #e03131; }
.strip-legend .st-cancel { background: #868e96; }
.strip-legend .st-idle { background: var(--bg-muted, #e9ecef); border: 1px solid var(--border-color, #ced4da); }
.strip-legend .st-unknown { background: repeating-linear-gradient(45deg, #ced4da 0 3px, #f1f3f5 3px 6px); }

/* Thời gian máy nằm không sau mẻ đó — nhạt hơn cột "Kéo dài" để không tranh mắt với
   thời lượng chạy, vốn là số chính của bảng. */
.dur-idle-after {
  font-weight: 500;
  color: var(--text-muted);
}

.dur-since, .dur-anchor {
  font-size: 0.75rem;
  color: var(--text-muted);
  white-space: nowrap;
}

.dur-task {
  font-size: 0.75rem;
  color: var(--text-body);
}

.dur-warn-tag {
  display: inline-block;
  font-size: 0.68rem;
  font-weight: 700;
  background-color: var(--status-yellow-bg);
  border: 1px solid var(--status-yellow-border);
  color: var(--status-yellow);
  padding: 2px 8px;
  border-radius: var(--radius-sm);
}

.dur-row-warn td { background-color: var(--status-yellow-bg); }

/* Máy bấm được để mở lịch sử trạng thái — phải nhìn thấy rõ là bấm được, nếu không người
   vận hành sẽ không biết có chức năng này. */
.machine-tile-clickable { cursor: pointer; }
.machine-tile-clickable:focus-visible { outline: 2px solid var(--color-primary, #2563eb); outline-offset: 2px; }
.dur-row-clickable { cursor: pointer; }
.dur-row-clickable:hover td { background-color: var(--bg-hover, rgba(0, 0, 0, 0.04)); }

.history-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-md);
  flex-wrap: wrap;
  margin-bottom: var(--space-md);
}
.history-range { display: flex; gap: 6px; }
/* Mã trạng thái gốc đứng cạnh nhãn tiếng Việt — cùng cách trình bày với bảng chú thích,
   để người dùng đối chiếu được với tài liệu/BPDB. */
.history-status-code {
  display: block;
  font-size: 0.72rem;
  color: var(--text-muted, #6b7280);
  letter-spacing: 0.03em;
}

.dur-row-danger td { background-color: var(--status-red-bg); }
.dur-row-danger .dur-warn-tag {
  background-color: var(--status-red-bg);
  border-color: var(--status-red-border);
  color: var(--status-red);
}

/* Tab 3: interlocks rows list */
.machine-status-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.machine-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-lg) var(--space-2xl);
  gap: var(--space-xl);
}

.m-row-header {
  display: flex;
  flex-direction: column;
  min-width: 150px;
}

.m-row-code {
  font-family: 'Outfit', sans-serif;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-title);
}

.m-row-name {
  font-size: 12px;
  color: var(--text-muted);
}

.m-row-body {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-xl);
}

.interlock-checklist {
  display: flex;
  gap: var(--space-xl);
}

.checklist-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background-color: var(--status-red-bg);
  border: 1px solid var(--status-red-border);
  color: var(--status-red);
  padding: 4px 10px;
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 700;
}

.checklist-item.check-success {
  background-color: var(--status-green-bg);
  border-color: var(--status-green-border);
  color: var(--status-green);
}

/* Tab 4: alert cards styling */
.alerts-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.alert-action-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  position: relative;
  overflow: hidden;
}

.alert-action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 5px;
  height: 100%;
  background-color: var(--status-yellow);
}

.alert-sev-critical::before {
  background-color: var(--status-red);
}

.alert-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-badge-sev {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.08em;
  background-color: var(--status-yellow-bg);
  border: 1px solid var(--status-yellow-border);
  color: var(--status-yellow);
  padding: 1px 6px;
  border-radius: var(--radius-sm);
}

.alert-sev-critical .alert-badge-sev {
  background-color: var(--status-red-bg);
  border-color: var(--status-red-border);
  color: var(--status-red);
}

.alert-time {
  font-size: 11px;
  color: var(--text-disabled);
}

.alert-message {
  font-weight: 600;
  color: var(--text-title);
}

.meta-tag {
  font-size: 11px;
  font-weight: 700;
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-divider);
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  margin-right: 6px;
}

.alert-actions-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border-divider);
  padding-top: var(--space-lg);
}

.action-buttons {
  display: flex;
  gap: var(--space-sm);
}

/* Tab 5: management KPIs styling */
.kpi-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-xl);
}

.kpi-detail-card {
  text-align: center;
  padding: var(--space-3xl) var(--space-xl) !important;
}

.kpi-detail-value {
  font-family: 'Outfit', sans-serif;
  font-size: 2.8rem;
  font-weight: 800;
  margin: var(--space-sm) 0;
}

/* Collapsible Mock section styling */
.collapsible-mock-section {
  border-top: 1px solid var(--border-divider);
  padding-top: var(--space-xl);
}

.mock-panel-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-card);
  padding: var(--space-md) var(--space-xl);
  border-radius: var(--radius-md);
  font-weight: 600;
  cursor: pointer;
}

.ws-telemetry-row {
  background-color: var(--bg-main);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-md) var(--space-lg);
  margin-bottom: var(--space-sm);
}

.ws-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ws-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot-active { background-color: var(--status-green); box-shadow: 0 0 6px var(--status-green); }
.dot-offline { background-color: var(--status-grey); }

.ws-weight-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 4px;
}

.ws-weight {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-title);
  font-family: monospace;
}

.ws-time {
  font-size: 10px;
  color: var(--text-disabled);
}

/* Modals Backdrop & structure */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.timeline-modal, .action-modal {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-xl);
  width: 90%;
  max-width: 580px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.modal-header {
  height: 60px;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
}

.modal-close-btn {
  font-size: 2rem;
  color: var(--text-muted);
  cursor: pointer;
}

.modal-body {
  flex: 1;
  padding: var(--space-2xl);
  overflow-y: auto;
}

.modal-footer {
  height: 60px;
  padding: 0 var(--space-2xl);
  border-top: 1px solid var(--border-divider);
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: var(--space-md);
  background-color: var(--bg-sidebar);
}

/* Timeline specific nodes list styling */
.timeline-steps {
  position: relative;
  padding-left: 20px;
}

.timeline-steps::before {
  content: '';
  position: absolute;
  top: 8px;
  left: 4px;
  bottom: 8px;
  width: 2px;
  background-color: var(--border-divider);
}

.timeline-step-item {
  position: relative;
  margin-bottom: var(--space-2xl);
}

.timeline-step-item:last-child {
  margin-bottom: 0;
}

.step-dot {
  position: absolute;
  left: -20px;
  top: 5px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background-color: var(--border-card);
  border: 2px solid var(--bg-card);
  z-index: 2;
}

.step-status-completed .step-dot {
  background-color: var(--status-green);
  border-color: var(--bg-card);
  box-shadow: 0 0 6px var(--status-green);
}

.step-status-warning .step-dot {
  background-color: var(--status-yellow);
  border-color: var(--bg-card);
  box-shadow: 0 0 6px var(--status-yellow);
}

.step-content {
  background-color: var(--bg-main);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-md) var(--space-lg);
}

.step-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.step-title {
  color: var(--text-title);
}

.step-time {
  font-size: 11px;
  color: var(--text-disabled);
}

.step-meta {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 2px;
}

.step-notes {
  margin-top: 6px;
  font-size: 12px;
  color: var(--text-body);
}

@media (max-width: 768px) {
  .tabs-nav-bar {
    width: 100%;
  }
  .machine-status-row {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-md);
  }
  .m-row-body {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }
  .interlock-checklist {
    flex-wrap: wrap;
    gap: var(--space-sm);
  }
}
</style>
