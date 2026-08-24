<template>
  <!-- Cùng cách bọc layout với BpdbMachinesGantt.vue: Admin được bọc AppLayout (dùng lại
       menu điều hướng chung), người xem công khai thì không — quyết định MỘT LẦN lúc khởi
       tạo, không đổi trong vòng đời trang (đổi wrapper = remount toàn bộ cây con). -->
  <component :is="pageWrapper">
  <div
    ref="rootRef"
    class="status-chart-page"
    :class="{ 'is-immersive': isBrowserFullscreen, 'is-dense': rowHeight <= 22 }"
    :style="{ '--row-h': rowHeight + 'px', height: pageH ? pageH + 'px' : undefined }"
  >
    <div class="page-head" v-show="!isBrowserFullscreen">
      <h2 class="page-title">
        {{ $t('bpdbMachineStatusChart.title') }}
        <span class="head-kpi" :title="$t('bpdbMachineStatusChart.legendRun')">
          <i></i>{{ fleetRunPct }}%
        </span>
      </h2>
      <div class="toolbar-group">
        <label class="field">
          <span class="field-label">{{ $t('bpdbMachineStatusChart.windowLabel') }}</span>
          <select :value="presetHours" @change="applyPreset(Number(($event.target as HTMLSelectElement).value))" class="form-select">
            <option :value="6">{{ $t('bpdbMachineStatusChart.window6h') }}</option>
            <option :value="12">{{ $t('bpdbMachineStatusChart.window12h') }}</option>
            <option :value="24">{{ $t('bpdbMachineStatusChart.window24h') }}</option>
            <option :value="72">{{ $t('bpdbMachineStatusChart.window3d') }}</option>
            <option :value="168">{{ $t('bpdbMachineStatusChart.window7d') }}</option>
            <option :value="0" disabled>{{ $t('bpdbMachineStatusChart.windowCustom') }}</option>
          </select>
        </label>
        <div class="nav-group">
          <button class="btn btn-secondary btn-sm" @click="panBy(-0.5)" :title="$t('bpdbMachineStatusChart.panLeftTitle')">◀</button>
          <button class="btn btn-secondary btn-sm" @click="zoomBy(1 / ZOOM_STEP)" :title="$t('bpdbMachineStatusChart.zoomInTitle')">＋</button>
          <button class="btn btn-secondary btn-sm" @click="zoomBy(ZOOM_STEP)" :title="$t('bpdbMachineStatusChart.zoomOutTitle')">－</button>
          <button class="btn btn-secondary btn-sm" @click="panBy(0.5)" :title="$t('bpdbMachineStatusChart.panRightTitle')">▶</button>
          <button class="btn btn-sm" :class="followNow ? 'btn-primary' : 'btn-secondary'" @click="goToNow">
            {{ $t('bpdbMachineStatusChart.moveToNowButton') }}
          </button>
        </div>
        <label class="field">
          <span class="field-label">{{ $t('bpdbMachineStatusChart.searchMachineLabel') }}</span>
          <input v-model="machineSearch" type="text" class="form-select" :placeholder="$t('bpdbMachineStatusChart.searchMachinePlaceholder')" />
        </label>
        <label class="realtime-toggle"><input type="checkbox" v-model="hideIdleMachines" /> {{ $t('bpdbMachineStatusChart.hideIdleLabel') }}</label>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoRefresh" /> {{ $t('bpdbMachineStatusChart.autoRefreshLabel') }}</label>
        <button class="btn btn-secondary btn-sm" @click="loadData()" :disabled="loading">
          {{ loading ? $t('common.loading') : $t('bpdbMachineStatusChart.reloadButton') }}
        </button>
        <button
          class="btn btn-secondary btn-sm icon-btn"
          @click="toggleTheme"
          :title="theme === 'dark' ? $t('bpdbMachineStatusChart.themeToLightTitle') : $t('bpdbMachineStatusChart.themeToDarkTitle')"
        >
          <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="16" />
        </button>
        <button
          class="btn btn-secondary btn-sm icon-btn"
          @click="toggleBrowserFullscreen"
          :title="isBrowserFullscreen ? $t('bpdbMachineStatusChart.exitBrowserFullscreenTitle') : $t('bpdbMachineStatusChart.enterBrowserFullscreenTitle')"
        >
          {{ isBrowserFullscreen ? '⤢' : '⛶' }}
        </button>
      </div>
    </div>

    <p v-if="errorMsg" class="text-error">❌ {{ errorMsg }}</p>
    <div v-if="!bpdbConnected" class="stale-banner error-banner">
      {{ $t('bpdbMachineStatusChart.bpdbDisconnected', { time: lastSyncedText }) }}
    </div>

    <!-- Toàn bộ vùng biểu đồ là một mặt phẳng KÉO ĐƯỢC (pointer events: chuột lẫn cảm ứng
         tablet xưởng) + cuộn chuột để phóng/thu, giống thao tác quen tay trên trang Gantt.
         Bắt sự kiện ở đây (bọc cả thước giờ lẫn các dòng máy) thay vì trên từng rãnh: kéo
         ở bất cứ đâu trong biểu đồ đều được, không phải nhắm đúng một thanh. -->
    <div
      class="chart-viewport"
      :class="{ 'is-panning': panning }"
      @pointerdown="onPanStart"
      @pointermove="onPanMove"
      @pointerup="onPanEnd"
      @pointercancel="onPanEnd"
      @wheel="onWheelZoom"
    >
      <!-- Thước giờ dính trên đầu: khi cuộn danh sách máy dài vẫn đọc được mốc giờ. -->
      <div class="chart-ruler">
        <span class="row-label ruler-corner">{{ windowRangeText }}</span>
        <div class="row-track" ref="rulerTrackEl">
          <span v-for="tk in ticks" :key="'tk-' + tk.leftPct" class="ruler-tick" :style="{ left: tk.leftPct + '%' }">
            <i class="tick-line"></i>
            <b class="tick-text">{{ tk.label }}</b>
          </span>
          <span v-if="nowLeftPct !== null" class="now-line" :style="{ left: nowLeftPct + '%' }"></span>
        </div>
      </div>

      <div class="chart-body" ref="chartBodyEl">
      <div v-for="row in visibleRows" :key="row.code" class="chart-row">
        <span class="row-label" :title="row.name">
          <span class="row-name">{{ row.name }}</span>
          <b class="row-runpct" :class="{ 'is-hot': row.runPct >= 60, 'is-cold': row.runPct === 0 }">{{ row.runPct }}%</b>
        </span>
        <div class="row-track">
          <span v-for="tk in ticks" :key="'g-' + row.code + '-' + tk.leftPct" class="grid-line" :style="{ left: tk.leftPct + '%' }"></span>
          <span
            v-for="b in row.blocks"
            :key="b.key"
            class="state-block"
            :class="'blk-' + b.kind"
            :style="{ left: b.leftPct + '%', width: b.widthPct + '%' }"
            :title="b.tip"
          >
            <em v-if="b.widthPct >= 6" class="blk-label">{{ b.label }}</em>
          </span>
          <span v-if="nowLeftPct !== null" class="now-line" :style="{ left: nowLeftPct + '%' }"></span>
        </div>
      </div>

      <p v-if="!loading && visibleRows.length === 0" class="text-muted empty-state">
        {{ $t('bpdbMachineStatusChart.emptyState') }}
      </p>
      </div>
    </div>

    <div class="chart-legend">
      <span><i class="blk-run"></i>{{ $t('bpdbMachineStatusChart.legendRun') }}</span>
      <span><i class="blk-stop"></i>{{ $t('bpdbMachineStatusChart.legendStop') }}</span>
      <span class="legend-note">{{ $t('bpdbMachineStatusChart.legendNote') }}</span>
      <span class="legend-note">{{ $t('bpdbMachineStatusChart.mesOnlyNote') }}</span>
      <span class="legend-note">{{ $t('bpdbMachineStatusChart.lastSynced', { time: lastSyncedText }) }}</span>
    </div>

    <!-- Đồng hồ góc màn hình: ở chế độ toàn màn hình, taskbar hệ điều hành bị che hết mà
         đây là màn hình treo xưởng cả ngày — người đứng xa vẫn phải đọc được giờ. -->
    <div v-if="isBrowserFullscreen" class="fs-clock">
      <b>{{ clockTime }}</b>
      <span>{{ clockDate }}</span>
    </div>
  </div>
  </component>
</template>

<script setup lang="ts">
/**
 * Biểu đồ trạng thái máy nhuộm (CHẠY / DỪNG) — mỗi máy VD một dòng, một trục giờ chung.
 *
 * Vì sao KHÔNG gọi /admin/bpdb/machines/{code}/timeline như modal lịch sử trên Dashboard:
 * endpoint đó chỉ trả 1 máy/lượt (mỗi lượt là một cú quét ODBC sang SQL Server nhà máy —
 * khâu chậm nhất trong luồng), 24 máy = 24 lượt và đòi quyền ADMIN. Endpoint công khai
 * /api/public/bpdb-machines-gantt vốn đã trả TOÀN BỘ máy trong 1 lượt, kèm sẵn giờ kết
 * thúc THẬT ghép từ MES (endSource) — đúng thứ màn hình này cần, không phải thêm API mới.
 *
 * Luật dựng đoạn dùng ĐÚNG luật của Gantt/Dashboard, không phát minh luật mới:
 *   - endSource='MES'      : giờ nhuộm xong THẬT -> sau mốc đó máy DỪNG thật.
 *   - endSource='BPDB'     : chỉ là giờ PHA xong (~16 phút/task), mẻ nhiều khả năng VẪN ĐANG
 *                            NHUỘM (xem commit 1086d71) -> KHÔNG có bằng chứng máy đã dừng.
 *   - endSource='BPDB_RUNNING' : chưa có bằng chứng kết thúc -> đang chạy tới hiện tại.
 *   - endSource='MES_ONLY' : bị loại hẳn, xem ghi chú trong machineRuns.
 *
 * Màn hình này CHỈ CÓ 2 MÀU: CHẠY và DỪNG (yêu cầu người dùng 23/08/2026). Trường hợp thứ ba
 * "không đủ bằng chứng" trước đó có màu riêng, nay gộp vào DỪNG — biết trước là làm thời gian
 * dừng bị tính DƯ, chi tiết ở ghi chú trong hàm dựng block bên dưới.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { theme, toggleTheme } from '../services/theme';
import { isFullscreen } from '../services/layout';
import SvgIcon from '../components/SvgIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const { t } = useI18n({ useScope: 'global' });
const authStore = useAuthStore();
const isAdminUser = authStore.isAdmin;
const pageWrapper = isAdminUser ? AppLayout : 'div';

// --- Trạng thái trang -----------------------------------------------------------------
const loading = ref(false);
const errorMsg = ref('');
const bpdbConnected = ref(true);
const lastSyncedAt = ref<string | null>(null);
const machineSearch = ref('');
const hideIdleMachines = ref(false);
const autoRefresh = ref(true);

const nowTick = ref(Date.now());
const clockNow = ref(new Date());

interface GanttItem {
  id: string | number;
  group: string;
  machineCode: string | null;
  taskTitle: string | null;
  color: string | null;
  productCode: string | null;
  endSource: 'MES' | 'MES_ONLY' | 'BPDB' | 'BPDB_RUNNING';
  isDeleted: boolean;
  start: string;
  end: string;
}

interface GanttGroup {
  id: string;
  content: string;
  nestedGroups?: string[];
}

const rawItems = ref<GanttItem[]>([]);
const rawGroups = ref<GanttGroup[]>([]);

// --- Tải dữ liệu ----------------------------------------------------------------------
const ymd = (d: Date) => {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
};

// Backend lọc theo WorkStartTime nằm trong [fromDate 00:00, toDate 23:59]. Mẻ nhuộm trung
// bình ~20 giờ và có mẻ kéo dài nhiều ngày, nên phải lùi mốc lấy dữ liệu vài ngày — lấy
// đúng hôm nay sẽ mất trắng những mẻ khởi động từ hôm trước mà giờ vẫn đang chạy.
const FETCH_LOOKBACK_DAYS = 3;

/** Khoảng ngày ĐANG giữ trong bộ nhớ — dùng để biết khi nào kéo ra ngoài và phải tải thêm. */
const fetchedRange = ref<{ from: string; to: string } | null>(null);

const loadData = async (fromDate?: string, toDate?: string) => {
  const from = fromDate ?? ymd(new Date(viewStart.value - FETCH_LOOKBACK_DAYS * 86400 * 1000));
  const to = toDate ?? ymd(new Date(Math.max(viewEnd.value, Date.now())));
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await axios.get('/api/public/bpdb-machines-gantt', { params: { fromDate: from, toDate: to } });
    rawItems.value = res.data.items || [];
    rawGroups.value = res.data.groups || [];
    bpdbConnected.value = res.data.bpdbConnected !== false;
    lastSyncedAt.value = res.data.lastSyncedAt || null;
    fetchedRange.value = { from, to };
  } catch (err) {
    console.error('Failed to load machine status chart:', err);
    errorMsg.value = t('bpdbMachineStatusChart.errorFallback');
  } finally {
    loading.value = false;
  }
};

/**
 * Chỉ tải lại khi khoảng NGÀY cần xem đổi (không phải mỗi lần nhích chuột): backend lọc
 * theo ngày và cache theo đúng cặp fromDate/toDate, nên kéo qua lại trong cùng ngày không
 * tốn thêm cú quét BPDB nào.
 */
let ensureTimer: ReturnType<typeof setTimeout> | null = null;
const ensureDataForView = (force = false) => {
  if (ensureTimer) clearTimeout(ensureTimer);
  ensureTimer = setTimeout(() => {
    const from = ymd(new Date(viewStart.value - FETCH_LOOKBACK_DAYS * 86400 * 1000));
    const to = ymd(new Date(Math.max(viewEnd.value, Date.now())));
    if (!force && fetchedRange.value && fetchedRange.value.from === from && fetchedRange.value.to === to) return;
    loadData(from, to);
  }, 300);
};

// --- Cửa sổ thời gian đang vẽ (kéo/zoom được) -------------------------------------------
const HOUR = 3600 * 1000;
const MIN_SPAN = 30 * 60 * 1000;      // thu hết cỡ: 30 phút
const MAX_SPAN = 30 * 86400 * 1000;   // giãn hết cỡ: 30 ngày
const ZOOM_STEP = 1.6;
const DEFAULT_SPAN = 12 * HOUR;

/** Mốc cuối bo lên đầu giờ kế tiếp — nhãn giờ rơi đúng mốc tròn, thanh không rung từng giây. */
const ceilHour = (ms: number) => {
  const d = new Date(ms);
  d.setMinutes(0, 0, 0);
  d.setHours(d.getHours() + 1);
  return d.getTime();
};

const viewEnd = ref(ceilHour(Date.now()));
const viewStart = ref(viewEnd.value - DEFAULT_SPAN);
// Bám hiện tại: bật thì cửa sổ tự trôi theo giờ. Kéo/zoom lùi về quá khứ là TẮT ngay, nếu
// không mỗi nhịp đồng hồ sẽ giật màn hình về hiện tại đúng lúc người dùng đang xem lại.
const followNow = ref(true);

const windowSpan = computed(() => viewEnd.value - viewStart.value);

/** Preset đang khớp cửa sổ hiện tại (0 = người dùng đã tự kéo/zoom sang khoảng khác). */
const presetHours = computed(() => {
  const h = Math.round(windowSpan.value / HOUR);
  return [6, 12, 24, 72, 168].includes(h) && followNow.value ? h : 0;
});

const applyPreset = (hours: number) => {
  if (!hours) return;
  viewEnd.value = ceilHour(Date.now());
  viewStart.value = viewEnd.value - hours * HOUR;
  followNow.value = true;
  ensureDataForView();
};

const goToNow = () => applyPreset(Math.round(windowSpan.value / HOUR) || 12);

/** Dời cửa sổ theo tỉ lệ bề rộng của chính nó (-0.5 = lùi nửa màn hình về quá khứ). */
const panBy = (ratio: number) => {
  const delta = windowSpan.value * ratio;
  viewStart.value += delta;
  viewEnd.value += delta;
  followNow.value = false;
  ensureDataForView();
};

/** factor > 1 = thu nhỏ (nhìn khoảng dài hơn). anchor 0..1 = điểm giữ nguyên trên trục. */
const zoomBy = (factor: number, anchor = 0.5) => {
  const span = windowSpan.value;
  const next = Math.min(MAX_SPAN, Math.max(MIN_SPAN, span * factor));
  if (next === span) return;
  const pivot = viewStart.value + span * anchor;
  viewStart.value = pivot - next * anchor;
  viewEnd.value = pivot + next * (1 - anchor);
  followNow.value = false;
  ensureDataForView();
};

// --- Kéo bằng chuột/cảm ứng ---------------------------------------------------------------
const rulerTrackEl = ref<HTMLElement | null>(null);
const panning = ref(false);
let panPointerId: number | null = null;
let panStartX = 0;
let panStartView = { start: 0, end: 0 };

/** Số mili-giây tương ứng 1 pixel của rãnh thời gian — cần bề rộng THẬT của rãnh đang vẽ. */
const msPerPx = () => {
  const w = rulerTrackEl.value?.clientWidth || 1;
  return windowSpan.value / w;
};

const onPanStart = (e: PointerEvent) => {
  // Chỉ nút trái/cảm ứng; đừng cướp thao tác gõ trong ô tìm máy hay bấm nút trên thanh công cụ.
  if (e.button !== 0) return;
  if ((e.target as HTMLElement).closest('input, select, button')) return;
  panning.value = true;
  panPointerId = e.pointerId;
  panStartX = e.clientX;
  panStartView = { start: viewStart.value, end: viewEnd.value };
  (e.currentTarget as HTMLElement).setPointerCapture?.(e.pointerId);
};

const onPanMove = (e: PointerEvent) => {
  if (!panning.value || e.pointerId !== panPointerId) return;
  // Kéo sang PHẢI = lùi về quá khứ, cùng chiều trực giác với Gantt (kéo tờ giấy dưới tay).
  const delta = (e.clientX - panStartX) * msPerPx();
  viewStart.value = panStartView.start - delta;
  viewEnd.value = panStartView.end - delta;
  followNow.value = false;
};

const onPanEnd = (e: PointerEvent) => {
  if (!panning.value) return;
  panning.value = false;
  panPointerId = null;
  (e.currentTarget as HTMLElement).releasePointerCapture?.(e.pointerId);
  ensureDataForView();
};

const onWheelZoom = (e: WheelEvent) => {
  // Cuộn TRƠN vẫn phải cuộn trang: danh sách hơn 20 máy dài hơn màn hình, nuốt mất cuộn dọc
  // thì không xuống được máy cuối. Giữ Ctrl (hoặc Shift) mới là zoom — cùng quy ước zoomKey
  // của vis-timeline trên trang Gantt.
  if (!e.ctrlKey && !e.shiftKey) return;
  e.preventDefault();
  const track = rulerTrackEl.value;
  // Phóng/thu quanh đúng vị trí con trỏ (không phải giữa màn hình) để mốc giờ đang xem
  // không trượt đi mất sau mỗi nấc cuộn.
  let anchor = 0.5;
  if (track) {
    const rect = track.getBoundingClientRect();
    anchor = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width));
  }
  zoomBy(e.deltaY > 0 ? ZOOM_STEP : 1 / ZOOM_STEP, anchor);
};

const pctOf = (ms: number) => ((ms - viewStart.value) / windowSpan.value) * 100;

const nowLeftPct = computed(() => {
  const p = pctOf(nowTick.value);
  return p >= 0 && p <= 100 ? p : null;
});

const hhmm = (ms: number) => {
  const d = new Date(ms);
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
};

const dmy = (ms: number) => new Date(ms).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });

const ticks = computed(() => {
  const hours = windowSpan.value / HOUR;
  // Zoom ra càng xa thì nhãn càng thưa, nếu không các mốc giờ chồng chữ lên nhau.
  const step = hours <= 8 ? 1 : hours <= 16 ? 2 : hours <= 36 ? 3 : hours <= 96 ? 6 : hours <= 240 ? 12 : 24;
  const out: { leftPct: number; label: string }[] = [];
  const cursor = new Date(viewStart.value);
  cursor.setMinutes(0, 0, 0);
  if (cursor.getTime() < viewStart.value) cursor.setHours(cursor.getHours() + 1);
  while (cursor.getTime() <= viewEnd.value) {
    if (cursor.getHours() % step === 0) {
      const ms = cursor.getTime();
      // Mốc 00:00 ghi kèm ngày — kéo lùi nhiều ngày mà chỉ thấy "00:00" thì không biết ngày nào.
      out.push({ leftPct: pctOf(ms), label: cursor.getHours() === 0 ? dmy(ms) : hhmm(ms) });
    }
    cursor.setHours(cursor.getHours() + 1);
  }
  return out;
});

const windowRangeText = computed(() => {
  const sameDay = dmy(viewStart.value) === dmy(viewEnd.value);
  return sameDay
    ? `${dmy(viewStart.value)} ${hhmm(viewStart.value)}→${hhmm(viewEnd.value)}`
    : `${dmy(viewStart.value)} ${hhmm(viewStart.value)} → ${dmy(viewEnd.value)} ${hhmm(viewEnd.value)}`;
});

const lastSyncedText = computed(() =>
  lastSyncedAt.value ? new Date(lastSyncedAt.value).toLocaleTimeString('vi-VN', { hour12: false }) : '—'
);

const formatDuration = (seconds: number): string => {
  const s = Math.max(0, Math.floor(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  if (h > 0) return t('bpdbMachineStatusChart.durHoursMinutes', { hours: h, minutes: m });
  return t('bpdbMachineStatusChart.durMinutes', { minutes: m });
};

// --- Dựng đoạn CHẠY / DỪNG cho từng máy --------------------------------------------------
// Hai mẻ cách nhau dưới ngưỡng này thì coi như chạy liền — khe vài chục giây giữa hai lần
// pha của cùng một mẻ không phải là "máy đã dừng".
const MERGE_GAP_SECONDS = 300;

interface RunSeg {
  from: number;
  to: number;
  /** true khi mốc kết thúc lấy từ MES (giờ nhuộm xong thật), false khi chỉ là giờ pha xong. */
  endKnown: boolean;
  running: boolean;
  titles: string[];
}

const machineRuns = computed<Record<string, RunSeg[]>>(() => {
  const byMachine: Record<string, GanttItem[]> = {};
  rawItems.value.forEach((it) => {
    // Mẻ đã hủy/xóa không phải máy chạy — Gantt vẫn trả về để người dùng thấy, nhưng ở đây
    // tô thành CHẠY là sai sự thật.
    if (it.isDeleted) return;
    // MES_ONLY (mẻ chỉ có trên MES, không có task BPDB nào): mốc bắt đầu của nó là beginTime
    // của MES = lúc TẠO/nhận mẻ chứ KHÔNG phải lúc máy bắt đầu nhuộm. Đo trên dữ liệu thật
    // 23/08: 345/614 mẻ loại này dài quá 24 giờ, cá biệt 780 giờ (32 ngày) — vẽ nguyên si sẽ
    // thành "máy chạy liên tục cả tháng". Gantt không lộ ra vì nó xếp lại vị trí các thanh
    // trước khi vẽ; biểu đồ này vẽ đúng mốc thật nên phải loại hẳn thay vì bịa giờ bắt đầu.
    if (it.endSource === 'MES_ONLY') return;
    const code = it.machineCode || String(it.group).split('::')[0];
    if (!code) return;
    (byMachine[code] ||= []).push(it);
  });

  const out: Record<string, RunSeg[]> = {};
  Object.entries(byMachine).forEach(([code, items]) => {
    const sorted = [...items]
      .map((it) => ({
        from: new Date(it.start).getTime(),
        to: new Date(it.end).getTime(),
        endKnown: it.endSource === 'MES',
        running: it.endSource === 'BPDB_RUNNING',
        title: (it.taskTitle || '').trim(),
      }))
      .filter((s) => Number.isFinite(s.from) && Number.isFinite(s.to) && s.to > s.from)
      .sort((a, b) => a.from - b.from);

    const segs: RunSeg[] = [];
    sorted.forEach((s) => {
      const last = segs[segs.length - 1];
      if (last && s.from - last.to <= MERGE_GAP_SECONDS * 1000) {
        // Gộp: thuộc tính "kết thúc" của đoạn gộp luôn theo mẻ kết thúc MUỘN NHẤT, vì đó
        // mới là mốc quyết định máy rảnh lúc nào.
        if (s.to >= last.to) {
          last.to = s.to;
          last.endKnown = s.endKnown;
          last.running = s.running;
        }
        if (s.title && !last.titles.includes(s.title)) last.titles.push(s.title);
        return;
      }
      segs.push({ from: s.from, to: s.to, endKnown: s.endKnown, running: s.running, titles: s.title ? [s.title] : [] });
    });
    out[code] = segs;
  });

  return out;
});

interface Block {
  key: string;
  kind: 'run' | 'stop';
  leftPct: number;
  widthPct: number;
  label: string;
  tip: string;
}

interface Row {
  code: string;
  name: string;
  blocks: Block[];
  runPct: number;
  hasData: boolean;
}

// Máy KHÔNG hiển thị trên biểu đồ này (yêu cầu 2026-08-23: bỏ nhóm VDG). Lọc ở trình duyệt
// chứ KHÔNG sửa GANTT_HIDDEN_MACHINES của backend: endpoint đó đang phục vụ chung cho trang
// Gantt, ẩn ở đó là ẩn luôn trên màn hình của người khác — vượt quá phạm vi yêu cầu.
const HIDDEN_MACHINE_PREFIXES = ['VDG'];
const isHiddenMachine = (code: string) =>
  HIDDEN_MACHINE_PREFIXES.some((p) => code.toUpperCase().startsWith(p));

const rows = computed<Row[]>(() => {
  // Chỉ lấy group CHA (mỗi Máy VD một group cha, các group con là Tank/MES) — giữ nguyên
  // thứ tự backend trả về để hàng máy không nhảy loạn giữa các lần tải.
  const parents = rawGroups.value.filter(
    (g) => Array.isArray(g.nestedGroups) && g.nestedGroups.length > 0 && !isHiddenMachine(g.id)
  );
  const winStart = viewStart.value;
  const winEnd = viewEnd.value;
  const span = windowSpan.value;

  return parents.map((g) => {
    const segs = machineRuns.value[g.id] || [];
    const blocks: Block[] = [];
    let runMs = 0;

    const push = (kind: Block['kind'], from: number, to: number, tipExtra?: string) => {
      const a = Math.max(from, winStart);
      const b = Math.min(to, winEnd);
      if (b <= a) return;
      if (kind === 'run') runMs += b - a;
      const labelKey = kind === 'run'
        ? 'bpdbMachineStatusChart.legendRun'
        : 'bpdbMachineStatusChart.legendStop';
      blocks.push({
        key: `${g.id}-${kind}-${a}`,
        kind,
        leftPct: ((a - winStart) / span) * 100,
        widthPct: ((b - a) / span) * 100,
        label: t(labelKey),
        tip: [
          t(labelKey),
          `${hhmm(a)} → ${hhmm(b)}`,
          formatDuration((b - a) / 1000),
          tipExtra || null,
        ].filter(Boolean).join(' · '),
      });
    };

    // Mọi khoảng ngoài đoạn CHẠY đều tô DỪNG (quyết định của người dùng 2026-08-23), kể cả
    // khi KHÔNG có bằng chứng máy đã dừng thật — trước đó những khoảng này được tô riêng là
    // "KHÔNG RÕ". Hệ quả đã báo trước và người dùng chấp nhận: đoạn mẻ trước chưa được xác
    // nhận kết thúc trên MES nhiều khả năng máy VẪN ĐANG NHUỘM (đo 23/08: 42% số lô còn được
    // cấp hóa chất sau hơn 1 giờ), nên thời gian dừng bị tính DƯ và cột % chạy bị thấp hơn
    // thực tế. Giữ nguyên lời cảnh báo trong tooltip để người xem còn phân biệt được đoạn nào
    // là bằng chứng thật, đoạn nào là suy đoán.
    let cursor: number | null = null;
    let cursorKnown = false;

    segs.forEach((s) => {
      if (cursor === null) {
        push('stop', winStart, s.from, t('bpdbMachineStatusChart.unknownReason'));
      } else if (s.from > cursor) {
        push('stop', cursor, s.from, cursorKnown ? undefined : t('bpdbMachineStatusChart.unknownReason'));
      }
      // Không bao giờ vẽ quá vạch hiện tại: mốc kết thúc từ MES đôi khi là giờ DỰ KIẾN nằm ở
      // tương lai — vẽ nguyên si thì thanh CHẠY dài hơn cả thời gian đã trôi qua.
      const end = Math.min(s.running ? Math.max(s.to, nowTick.value) : s.to, nowTick.value);
      push('run', s.from, end, s.titles.slice(0, 3).join(', ') || undefined);
      cursor = end;
      cursorKnown = s.endKnown;
    });

    // Từ mẻ cuối tới hiện tại. Phần cửa sổ NẰM SAU hiện tại (khi chọn cửa sổ bo lên đầu giờ
    // kế tiếp) để trống — tương lai chưa xảy ra, tô bất cứ màu gì cũng là bịa.
    const tailEnd = Math.min(winEnd, nowTick.value);
    if (cursor === null) {
      push('stop', winStart, tailEnd, t('bpdbMachineStatusChart.unknownReason'));
    } else if (tailEnd > cursor) {
      push('stop', cursor, tailEnd, cursorKnown ? undefined : t('bpdbMachineStatusChart.unknownReason'));
    }

    const visibleSpan = Math.max(1, Math.min(winEnd, nowTick.value) - winStart);
    return {
      code: g.id,
      name: g.content,
      blocks,
      runPct: Math.round((runMs / visibleSpan) * 100),
      hasData: segs.length > 0,
    };
  });
});

const visibleRows = computed(() => {
  const kw = machineSearch.value.trim().toLowerCase();
  return rows.value.filter((r) => {
    if (hideIdleMachines.value && r.runPct === 0) return false;
    if (!kw) return true;
    return r.name.toLowerCase().includes(kw) || r.code.toLowerCase().includes(kw);
  });
});

/** % chạy trung bình toàn xưởng trong cửa sổ đang xem — số tổng quan đọc từ xa. */
const fleetRunPct = computed(() => {
  const list = visibleRows.value;
  if (!list.length) return 0;
  return Math.round(list.reduce((s, r) => s + r.runPct, 0) / list.length);
});

// --- Vừa khít một màn hình, KHÔNG cuộn (yêu cầu người dùng 24/08/2026) ---------------------
// Hai phép đo tách biệt, cùng kỹ thuật với WeighingStationLarge.vue (fitRoot đo từ mép trên
// thật của phần tử thay vì đặt cứng 100vh — route này có thể nằm trong AppLayout, tức đã bị
// đẩy xuống dưới thanh trên và nằm trong .content-container có padding riêng):
//   1. pageH: chiều cao TOÀN TRANG, ép bằng đúng khoảng trống còn lại của khung chứa.
//   2. rowHeight: đo THẬT vùng chứa các dòng máy (chartBodyEl) rồi chia đều cho số dòng đang
//      hiện — không đoán trước "thanh công cụ cao bao nhiêu" nữa, nên dù bật/tắt banner lỗi,
//      toolbar rớt xuống 2 dòng trên máy hẹp, hay đổi cỡ chữ trình duyệt, biểu đồ vẫn tự co
//      đúng, không bao giờ tràn khỏi khung.
const rootRef = ref<HTMLElement | null>(null);
const chartBodyEl = ref<HTMLElement | null>(null);
const pageH = ref<number | null>(null);
const rowsAreaH = ref(0);
const ROW_GAP = 6;

const rowHeight = computed(() => {
  const count = visibleRows.value.length || 1;
  const raw = rowsAreaH.value / count - ROW_GAP;
  // Sàn thấp (6px) chỉ để tránh chia cho 0/âm khi khung còn chưa kịp đo lần đầu — không đặt
  // sàn cao như bản trước vì mục tiêu là KHÔNG BAO GIỜ tràn, thà dòng mảnh hơn là phải cuộn.
  // Trần 46px để vài máy lẻ không bị kéo thành thanh khổng lồ vô nghĩa.
  return Math.round(Math.min(46, Math.max(6, raw)));
});

/** Lớp gắn tạm trên `<body>`: gỡ padding/khoá cuộn của `.content-container` (AppLayout) khi
 *  trang này đang mở, y hệt cơ chế LOP_TRAN_VIEN của WeighingStationLarge.vue. Không ảnh hưởng
 *  gì khi xem công khai (không có AppLayout bao ngoài, selector không khớp phần tử nào). */
const BODY_FIT_CLASS = 'df-bpdb-status-fit';

function fitRoot() {
  const el = rootRef.value;
  if (!el) return;
  const top = el.getBoundingClientRect().top;
  const parent = el.parentElement;
  const padBottom = parent ? parseFloat(getComputedStyle(parent).paddingBottom) || 0 : 0;
  const next = Math.max(320, Math.floor(window.innerHeight - top - padBottom));
  if (pageH.value === null || Math.abs(pageH.value - next) > 1) pageH.value = next;
}

function fitRows() {
  const h = chartBodyEl.value?.clientHeight || 0;
  if (Math.abs(rowsAreaH.value - h) > 1) rowsAreaH.value = h;
}

// --- Toàn màn hình + đồng hồ (cùng cơ chế với BpdbMachinesGantt.vue) ----------------------
// F11 KHÔNG set document.fullscreenElement — media query bắt được cả hai đường vào.
const fullscreenMedia = window.matchMedia('(display-mode: fullscreen)');
const isBrowserFullscreen = ref(!!document.fullscreenElement || fullscreenMedia.matches);

const syncBrowserFullscreenState = () => {
  const was = isBrowserFullscreen.value;
  isBrowserFullscreen.value = !!document.fullscreenElement || fullscreenMedia.matches;
  if (isAdminUser && !was && isBrowserFullscreen.value) isFullscreen.value = true;
  // Ẩn/hiện .page-head đổi hẳn chiều cao còn lại cho biểu đồ — không đợi sự kiện resize (trình
  // duyệt thường bắn resize khi vào/thoát fullscreen, nhưng không đảm bảo trên mọi nền tảng).
  requestAnimationFrame(fitAll);
};

const toggleBrowserFullscreen = () => {
  if (document.fullscreenElement) {
    document.exitFullscreen().catch(() => {});
  } else {
    document.documentElement.requestFullscreen?.().catch(() => {});
  }
};

const clockTime = computed(() => clockNow.value.toLocaleTimeString('vi-VN', { hour12: false }));
const clockDate = computed(() =>
  clockNow.value.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' })
);

// --- Vòng đời -----------------------------------------------------------------------------
let refreshTimer: ReturnType<typeof setInterval> | null = null;
let tickTimer: ReturnType<typeof setInterval> | null = null;
let roRoot: ResizeObserver | null = null;
let roRows: ResizeObserver | null = null;

const fitAll = () => {
  fitRoot();
  fitRows();
};

onMounted(() => {
  loadData();
  document.body.classList.add(BODY_FIT_CLASS);
  // Đo lần đầu ngay khi DOM vừa dựng xong (trước khi ảnh/font tải xong `top` còn là số tạm,
  // rAF đợi đúng một khung vẽ để lấy số thật).
  requestAnimationFrame(fitAll);
  if (typeof ResizeObserver !== 'undefined') {
    // Khung CHA (ví dụ .content-container của AppLayout) đổi cao khi bật/tắt toàn màn hình,
    // xoay màn hình tablet... — đo lại chiều cao trang.
    if (rootRef.value?.parentElement) {
      roRoot = new ResizeObserver(fitRoot);
      roRoot.observe(rootRef.value.parentElement);
    }
    // Vùng chứa dòng máy đổi cao khi toolbar rớt xuống 2 dòng, banner lỗi hiện/ẩn, hay chính
    // pageH vừa đổi — đo lại số dòng vừa khung.
    if (chartBodyEl.value) {
      roRows = new ResizeObserver(fitRows);
      roRows.observe(chartBodyEl.value);
    }
  }
  window.addEventListener('resize', fitAll);
  // Kim giờ hiện tại và các đoạn "đang chạy" phải nhích theo thời gian thật; 10s là đủ mượt
  // cho màn hình treo mà không ép Vue tính lại toàn bộ biểu đồ mỗi giây.
  tickTimer = setInterval(() => {
    nowTick.value = Date.now();
    clockNow.value = new Date();
    // Chỉ trôi cửa sổ khi đang bám hiện tại — người dùng kéo về quá khứ thì để yên màn hình
    // của họ, giật về hiện tại giữa chừng là mất chỗ đang xem.
    if (followNow.value) {
      const span = windowSpan.value;
      viewEnd.value = ceilHour(nowTick.value);
      viewStart.value = viewEnd.value - span;
    }
  }, 10000);
  // BPDB cache phía server 15s — gọi lại dày hơn 60s chỉ tốn round-trip mà không có dữ liệu mới.
  refreshTimer = setInterval(() => {
    if (autoRefresh.value && !document.hidden) ensureDataForView(true);
  }, 60000);
  document.addEventListener('fullscreenchange', syncBrowserFullscreenState);
  fullscreenMedia.addEventListener('change', syncBrowserFullscreenState);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  if (tickTimer) clearInterval(tickTimer);
  if (ensureTimer) clearTimeout(ensureTimer);
  roRoot?.disconnect();
  roRows?.disconnect();
  window.removeEventListener('resize', fitAll);
  document.removeEventListener('fullscreenchange', syncBrowserFullscreenState);
  fullscreenMedia.removeEventListener('change', syncBrowserFullscreenState);
  document.body.classList.remove(BODY_FIT_CLASS);
});
</script>

<style scoped>
.status-chart-page {
  /* --row-h do script đo THẬT vùng chứa dòng máy rồi chia đều (fitRows), không còn đoán mò;
     --label-w co theo bề ngang màn hình để trên tablet dọc phần nhãn máy không ăn mất nửa
     biểu đồ. Chiều cao trang do fitRoot() ép bằng JS qua style height ở template — CSS chỉ
     cần khai display:flex + overflow:hidden để khoá cứng, không cho tràn ra ngoài. */
  --row-h: 26px;
  --label-w: clamp(76px, 11vw, 132px);
  --row-gap: 6px;
  display: flex;
  flex-direction: column;
  padding: clamp(8px, 1.4vw, 18px) clamp(10px, 1.6vw, 22px) 12px;
  background: var(--color-bg, #fff);
  overflow: hidden;
}
.status-chart-page.is-immersive { padding: 6px clamp(8px, 1vw, 14px) 8px; }

.page-head {
  flex: 0 0 auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px 14px;
  margin-bottom: 10px;
}
.page-title {
  margin: 0;
  font-size: clamp(15px, 1.4vw, 20px);
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
/* Chỉ số tổng quan cạnh tiêu đề: người quản lý đi ngang liếc một cái là biết xưởng đang
   chạy bao nhiêu %, không cần đọc từng dòng máy. */
.head-kpi {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 9px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  background: color-mix(in srgb, #16a34a 12%, transparent);
  color: #16a34a;
}
.head-kpi i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: currentColor;
}

.toolbar-group {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  padding: 6px 8px;
  border-radius: 10px;
  background: var(--color-surface-2, #f6f7f9);
  border: 1px solid var(--color-border, #e5e7eb);
}
.field { display: flex; flex-direction: column; gap: 2px; }
.field-label { font-size: 11px; color: var(--color-text-muted, #6b7280); }
.realtime-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  white-space: nowrap;
  cursor: pointer;
}
.nav-group {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  padding: 2px;
  border-radius: 8px;
  background: var(--color-bg, #fff);
  border: 1px solid var(--color-border, #e5e7eb);
}
.nav-group .btn { border-radius: 6px; }

.icon-btn { display: inline-flex; align-items: center; }

.stale-banner {
  flex: 0 0 auto;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 13px;
  margin-bottom: 8px;
  background: var(--color-warning-bg, #fff7ed);
  color: var(--color-warning-text, #9a3412);
}
.stale-banner.error-banner {
  background: var(--color-error-bg, #fef2f2);
  color: var(--color-error-text, #b91c1c);
}
.text-error { flex: 0 0 auto; }

/* --- Lưới biểu đồ: nhãn máy cố định bên trái + rãnh thời gian co giãn ---
   Toàn bộ khối này là MỘT cột flex chiếm hết phần cao còn lại của trang (flex:1), không
   cuộn riêng — mục tiêu là vừa khít một màn, không phải cuộn để thấy hết (yêu cầu 24/08/2026).
   touch-action:none là BẮT BUỘC cho tablet xưởng: nếu không, trình duyệt nuốt cử chỉ kéo
   ngang thành thao tác cuộn trang và pointermove không bao giờ tới tay mình. */
.chart-viewport {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  touch-action: none;
  cursor: grab;
  user-select: none;
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 0 clamp(6px, 0.8vw, 12px) 8px;
  background: var(--color-surface, #fff);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
  overflow: hidden;
}
.chart-viewport.is-panning { cursor: grabbing; }
.chart-ruler,
.chart-row {
  display: flex;
  align-items: stretch;
  gap: 8px;
}
.chart-ruler {
  flex: 0 0 auto;
  padding: 6px 0 10px;
  background: var(--color-surface, #fff);
  border-bottom: 1px solid var(--color-border, #e5e7eb);
  margin-bottom: 6px;
}
.chart-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow: hidden;
}
.row-label {
  flex: 0 0 var(--label-w);
  width: var(--label-w);
  font-size: clamp(11px, 0.85vw, 13px);
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  overflow: hidden;
  white-space: nowrap;
}
.row-name { overflow: hidden; text-overflow: ellipsis; }
.ruler-corner {
  font-size: clamp(10px, 0.7vw, 11px);
  font-weight: 500;
  color: var(--color-text-muted, #6b7280);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row-runpct {
  flex: none;
  font-size: 10px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  padding: 1px 5px;
  border-radius: 999px;
  background: var(--color-surface-2, #f1f2f4);
  color: var(--color-text-muted, #6b7280);
}
.row-runpct.is-hot {
  background: color-mix(in srgb, #16a34a 14%, transparent);
  color: #15803d;
}
.row-runpct.is-cold { opacity: 0.55; }
/* Máy đang chật vật (dòng quá thấp) thì bỏ hẳn % — chữ 10px trong ô 16px là nhiễu. */
.is-dense .row-runpct { display: none; }

.row-track {
  position: relative;
  flex: 1 1 auto;
  min-width: 0;
  height: var(--row-h);
  border-radius: 5px;
  background: var(--color-surface-2, #f3f4f6);
  transition: height 0.15s ease;
}
.chart-ruler .row-track { height: 20px; background: transparent; }

.ruler-tick {
  position: absolute;
  top: 0;
  bottom: 0;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
}
.tick-text { font-size: 11px; font-weight: 500; font-variant-numeric: tabular-nums; }
.tick-line { width: 1px; flex: 1 1 auto; background: var(--color-border, #d1d5db); }
.grid-line {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 1px;
  background: var(--color-border, #e5e7eb);
  opacity: 0.6;
}
.now-line {
  position: absolute;
  top: -2px;
  bottom: -2px;
  width: 2px;
  /* Vạch giờ hiện tại dùng vàng hổ phách: xanh dương và đỏ nay đều là màu trạng thái. */
  background: #f59e0b;
  z-index: 2;
}

.chart-row {
  margin-bottom: var(--row-gap);
  border-radius: 6px;
  transition: background 0.12s ease;
}
.chart-row:hover { background: color-mix(in srgb, var(--color-text, #111827) 4%, transparent); }
.state-block {
  position: absolute;
  top: 2px;
  bottom: 2px;
  border-radius: 4px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
/* Màu trạng thái (yêu cầu 24/08 → đổi xanh lá 24/08 buổi 2): CHẠY = xanh lá đặc, DỪNG = ô
   xám trung tính. Chỉ trạng thái CHẠY mới có màu, DỪNG để xám nhạt — màn hình treo xưởng cả
   ngày, nếu cả hai trạng thái đều rực màu thì mắt không bắt được thông tin nào. */
.blk-run {
  background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
  box-shadow: 0 1px 2px rgba(22, 163, 74, 0.28);
}
/* Viền vẽ bằng inset shadow để không đổi kích thước ô. */
.blk-stop {
  background: #eceef1;
  box-shadow: inset 0 0 0 1px #d5d9df;
}
.blk-label {
  font-size: clamp(9px, calc(var(--row-h) * 0.4), 12px);
  font-style: normal;
  font-weight: 700;
  letter-spacing: 0.04em;
  white-space: nowrap;
}
.blk-run .blk-label { color: #f0fdf4; }
.blk-stop .blk-label { color: #6b7280; }

.chart-legend {
  flex: 0 0 auto;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-top: 10px;
  font-size: 12px;
  color: var(--color-text-muted, #6b7280);
}
.chart-legend i {
  display: inline-block;
  width: 22px;
  height: 10px;
  border-radius: 2px;
  margin-right: 6px;
  vertical-align: middle;
}
.chart-legend .blk-run { background: #16a34a; }
.chart-legend .blk-stop {
  background: #eceef1;
  box-shadow: inset 0 0 0 1px #d5d9df;
}
.legend-note { font-size: 11px; }
.empty-state { padding: 24px 0; text-align: center; }

/* Dòng bị nén quá thấp thì chữ trong ô chỉ còn là vệt nhiễu — bỏ đi, màu ô đã đủ nghĩa. */
.is-dense .blk-label { display: none; }

/* --- Thích nghi màn hình hẹp (tablet dọc / điện thoại) ---
   Không xếp nhãn máy xuống dưới rãnh thời gian: đọc theo dòng ngang mới so sánh được các
   máy với nhau — đó là toàn bộ mục đích của biểu đồ này. Chỉ thu nhãn và thanh công cụ. */
@media (max-width: 900px) {
  .status-chart-page { --label-w: clamp(64px, 18vw, 96px); }
  .page-head { align-items: stretch; flex-direction: column; }
  .toolbar-group { justify-content: flex-start; }
  .toolbar-group .field { flex: 1 1 140px; }
  .chart-legend { gap: 8px 12px; }
}
@media (max-width: 560px) {
  .status-chart-page { --label-w: 58px; }
  .head-kpi { font-size: 11px; }
  .legend-note { flex: 1 1 100%; }
}

/* Gắn/gỡ bởi script lúc mount/unmount (BODY_FIT_CLASS). Khi trang này nằm trong AppLayout,
   `.content-container` vốn có padding + overflow-y:auto riêng — giữ nguyên thì fitRoot() đo
   đúng khoảng trống còn lại nhưng khung chứa vẫn có thể tự sinh thanh cuộn của chính nó (lệch
   vài pixel do border/scrollbar). Bỏ padding và khoá cuộn tại đây, ép trang con tự chịu trách
   nhiệm vừa khít — cùng kỹ thuật LOP_TRAN_VIEN trong WeighingStationLarge.vue. Khi xem công
   khai (không có AppLayout) selector này không khớp phần tử nào, vô hại. */
:global(body.df-bpdb-status-fit .content-container) {
  padding: 0;
  overflow: hidden;
}

.fs-clock {
  position: fixed;
  top: 8px;
  right: 14px;
  text-align: right;
  z-index: 5;
  line-height: 1.1;
}
.fs-clock b { font-size: 26px; font-variant-numeric: tabular-nums; }
.fs-clock span { display: block; font-size: 12px; color: var(--color-text-muted, #6b7280); }

/* Theme tối: xanh lá sáng thêm một nấc, ô DỪNG chuyển thành xám tối. */
:global([data-theme='dark']) .blk-run {
  background: linear-gradient(180deg, #4ade80 0%, #22c55e 100%);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.35);
}
:global([data-theme='dark']) .chart-legend .blk-run { background: #22c55e; }
:global([data-theme='dark']) .chart-viewport,
:global([data-theme='dark']) .chart-ruler { background: var(--color-surface, #111827); }
:global([data-theme='dark']) .head-kpi { color: #4ade80; }
:global([data-theme='dark']) .row-runpct.is-hot { color: #86efac; }
:global([data-theme='dark']) .blk-run .blk-label { color: #f0fdf4; }
:global([data-theme='dark']) .blk-stop,
:global([data-theme='dark']) .chart-legend .blk-stop {
  background: #374151;
  box-shadow: inset 0 0 0 1px #4b5563;
}
:global([data-theme='dark']) .blk-stop .blk-label { color: #9ca3af; }
</style>
