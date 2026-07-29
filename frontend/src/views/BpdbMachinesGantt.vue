<template>
  <div class="gantt-page">
    <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>
    <div v-if="!bpdbConnected" class="stale-banner error-banner mt-2">
      ⚠️ BPDB mất kết nối — biểu đồ đang hiển thị dữ liệu cache gần nhất (lúc {{ formatTime(lastSyncedAt) }}).
    </div>
    <div v-else-if="dataStale" class="stale-banner mt-2">
      ⏱️ Dữ liệu có thể đã cũ — lần đồng bộ gần nhất lúc {{ formatTime(lastSyncedAt) }} ({{ dataAgeSeconds }}s trước).
    </div>

    <div class="toolbar mt-2">
      <div class="toolbar-group">
        <label class="field">
          <span class="field-label">Từ ngày</span>
          <input type="date" v-model="fromDate" class="form-select" />
        </label>
        <label class="field">
          <span class="field-label">Đến ngày</span>
          <input type="date" v-model="toDate" class="form-select" />
        </label>
        <button class="btn btn-primary btn-sm" @click="loadGantt()" :disabled="loading">🔍 Lọc đồ thị</button>
      </div>
      <div class="toolbar-group">
        <button class="btn btn-secondary btn-sm" @click="loadGantt()" :disabled="loading">{{ loading ? 'Đang tải...' : '🔄 Tải lại' }}</button>
        <button class="btn btn-secondary btn-sm" @click="moveToNow">🕐 Về hiện tại</button>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoMove" /> Auto cuộn</label>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoRefresh" /> Auto tải lại 30s</label>
        <!-- Trang này không bọc AppLayout (route public, xem qua link không đăng nhập -
             App.vue) nên không có nút chuyển theme ở topbar chung, phải tự có nút riêng. -->
        <button
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="toggleTheme"
          :title="theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
        >
          <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="16" />
        </button>
      </div>
    </div>

    <div class="gantt-container mt-2">
      <div id="ganttNeedle" class="gantt-needle" :style="needleStyle" :data-time="needleLabel" v-show="needleVisible"></div>
      <div ref="timelineEl" class="gantt-canvas"></div>
      <p v-if="!loading && totalRecords === 0" class="gantt-empty">Không có task nào bắt đầu trong khoảng ngày đã chọn.</p>
    </div>
    <p class="footnote">Tổng số task hiển thị: <strong>{{ totalRecords }}</strong> · viền đỏ nhấp nháy = mẻ đang chạy, chưa kết thúc</p>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';
import { Timeline, DataSet } from 'vis-timeline/standalone';
import 'vis-timeline/styles/vis-timeline-graph2d.min.css';
import { isFullscreen } from '../services/layout';
import { theme, toggleTheme } from '../services/theme';
import SvgIcon from '../components/SvgIcon.vue';

const RAW_TASK_STATUS_LABELS: Record<string, string> = {
  '10': 'Chờ hệ thống xử lý',
  '20': 'Đang chuyển trạng thái',
  '30': 'Đang được hệ thống xử lý',
  '40': 'Task đã kết thúc',
  '99': 'Task bị hủy/xóa',
};

const toDateInput = (d: Date) => d.toISOString().slice(0, 10);
const fromDate = ref(toDateInput(new Date(Date.now() - 7 * 24 * 3600 * 1000)));
const toDate = ref(toDateInput(new Date()));

const loading = ref(false);
const errorMsg = ref('');
const bpdbConnected = ref(true);
const lastSyncedAt = ref<string | null>(null);
const dataAgeSeconds = ref(0);
const dataStale = ref(false);
const totalRecords = ref(0);
const autoMove = ref(true);
const autoRefresh = ref(true);

const timelineEl = ref<HTMLDivElement | null>(null);
let timeline: any = null;
const groupsDataSet = new DataSet<any>();
const itemsDataSet = new DataSet<any>();

// Mốc "hiện tại" dùng để vẽ kim đỏ + tự cuộn — chốt theo lần đồng bộ dữ liệu gần nhất
// (lastSyncedAt trả về từ backend), KHÔNG dùng đồng hồ trình duyệt — nhất quán với cách
// các trang BPDB khác trong hệ thống báo "dữ liệu có thể đã cũ" thay vì giả vờ realtime.
const syncSnapshot = ref(new Date());
const needleVisible = ref(false);
const needleLabel = ref('');
const needleStyle = ref('left: 0px;');

const formatTime = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

const rawTaskStatusLabel = (status: number) => RAW_TASK_STATUS_LABELS[String(status)] || `Không xác định (${status})`;

// Màu thanh Gantt sinh theo hash (mã máy+màu+mã hàng) — chỉ để phân biệt trực quan các
// mẻ khác nhau, không mang ý nghĩa nghiệp vụ.
const barColor = (seed: string) => {
  let hash = 0;
  for (let i = 0; i < seed.length; i++) hash = seed.charCodeAt(i) + ((hash << 5) - hash);
  return `hsl(${Math.abs(hash) % 360}, 70%, 42%)`;
};

const buildTooltip = (item: any) => {
  // group id có dạng "{machineCode}::{tankLabel}" (xem BpdbMachineMonitoringService::
  // getGanttTimeline) — tách lại để hiển thị, không cần field riêng từ backend.
  const [machineCode, tankLabel] = String(item.group).split('::');
  const lines = [
    `<strong>Máy:</strong> ${machineCode}${tankLabel ? ' · Tank ' + tankLabel : ''}`,
    item.color && item.productCode
      ? `<strong>Mã màu - Mã hàng:</strong> ${item.color} - ${item.productCode}`
      : `<strong>TaskTitle gốc (không tách được mã màu/mã hàng):</strong> ${item.taskTitle}`,
    `<strong>Trạng thái:</strong> ${rawTaskStatusLabel(item.taskStatus)}${item.uncompleted ? ' <span class="gantt-blink">(đang chạy)</span>' : ''}`,
    `<strong>Bắt đầu:</strong> ${formatTime(item.start)}`,
    `<strong>Kết thúc:</strong> ${item.uncompleted ? 'Chưa kết thúc' : formatTime(item.end)}`,
  ];
  if (item.errorMessage) lines.push(`<strong class="text-error">Lỗi:</strong> ${item.errorMessage}`);
  return `<div class="gantt-tooltip">${lines.join('<br/>')}</div>`;
};

const fetchGantt = async () => {
  const res = await axios.get('/api/public/bpdb-machines-gantt', { params: { fromDate: fromDate.value, toDate: toDate.value } });
  bpdbConnected.value = res.data.bpdbConnected;
  lastSyncedAt.value = res.data.lastSyncedAt ?? null;
  dataAgeSeconds.value = Math.round(res.data.dataAgeSeconds ?? 0);
  dataStale.value = !!res.data.stale;
  totalRecords.value = res.data.totalRecords ?? 0;
  syncSnapshot.value = new Date();

  // groups gồm cả group cha (Máy VD, có nestedGroups) lẫn group con (từng Tank thật của
  // máy đó) — vis-timeline tự vẽ Tank thành các hàng con thụt vào dưới Máy VD, đóng/mở
  // được (xem getGanttTimeline() backend).
  const groups = (res.data.groups || []).map((g: any) => ({
    id: g.id,
    content: g.content,
    order: g.order,
    nestedGroups: g.nestedGroups && g.nestedGroups.length ? g.nestedGroups : undefined,
  }));
  const items = (res.data.items || []).map((it: any) => {
    const end = it.uncompleted ? syncSnapshot.value : new Date(it.end);
    const label = it.color && it.productCode ? `${it.color}-${it.productCode}` : (it.taskTitle || '—');
    return {
      id: it.id,
      group: it.group,
      start: new Date(it.start),
      end,
      className: it.uncompleted ? 'gantt-item-running' : '',
      style: `background-color: ${barColor(it.group + it.color + it.productCode)};`,
      content: `<div class="gantt-item-label">${label}</div>`,
      title: buildTooltip({ ...it, end }),
    };
  });

  groupsDataSet.clear();
  groupsDataSet.add(groups);
  itemsDataSet.clear();
  itemsDataSet.add(items);
};

const loadGantt = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    await fetchGantt();
    if (!timeline) {
      await nextTick();
      initTimeline();
    }
    calculateNeedle();
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Không tải được dữ liệu Gantt.';
  } finally {
    loading.value = false;
  }
};

const initTimeline = () => {
  if (!timelineEl.value) return;
  timeline = new Timeline(timelineEl.value, itemsDataSet, groupsDataSet, {
    stack: true,
    groupOrder: 'order',
    orientation: 'top',
    zoomKey: 'ctrlKey',
    height: '100%',
    // BẮT BUỘC khi height là giá trị cố định (không phải 'auto') — thiếu option này,
    // vis-timeline âm thầm CẮT các hàng vượt quá chiều cao container thay vì cho cuộn
    // (đúng lỗi báo cáo 2026-07-29: không cuộn xuống được để xem hết Máy VD/Tank).
    verticalScroll: true,
    showCurrentTime: false,
    locale: 'vi',
    start: new Date(syncSnapshot.value.getTime() - 12 * 3600 * 1000),
    end: new Date(syncSnapshot.value.getTime() + 12 * 3600 * 1000),
    tooltip: { followMouse: true, overflow: 'flip' },
  } as any);
  timeline.on('rangechange', calculateNeedle);
  timeline.on('rangechanged', calculateNeedle);
};

const calculateNeedle = () => {
  if (!timeline) return;
  const range = timeline.getWindow();
  const startMs = range.start.getTime();
  const endMs = range.end.getTime();
  const syncMs = syncSnapshot.value.getTime();
  if (syncMs < startMs || syncMs > endMs) {
    needleVisible.value = false;
    return;
  }
  const pct = ((syncMs - startMs) / (endMs - startMs)) * 100;
  needleVisible.value = true;
  needleStyle.value = `left: calc(170px + (100% - 170px) * ${pct / 100});`;
  needleLabel.value = formatTime(syncSnapshot.value.toISOString());
};

const moveToNow = () => {
  if (!timeline) return;
  timeline.setWindow(
    new Date(syncSnapshot.value.getTime() - 12 * 3600 * 1000),
    new Date(syncSnapshot.value.getTime() + 12 * 3600 * 1000),
    { animation: { duration: 500, easingFunction: 'easeInOutQuad' } }
  );
};

// Không còn nút Toàn màn hình riêng ở trang này (đã bỏ banner để tiết kiệm không gian,
// dùng chung nút ⛶ có sẵn ở topbar AppLayout) — vẫn cần vẽ lại timeline khi kích thước
// khung đổi do bật/tắt Toàn màn hình, nếu không vis-timeline giữ nguyên layout cũ.
watch(isFullscreen, () => setTimeout(() => timeline?.redraw(), 60));

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let moveTimer: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
  await loadGantt();
  refreshTimer = setInterval(() => {
    if (autoRefresh.value) loadGantt();
  }, 30000);
  moveTimer = setInterval(() => {
    if (autoMove.value && timeline) moveToNow();
  }, 5000);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  if (moveTimer) clearInterval(moveTimer);
  timeline?.destroy();
});
</script>

<style scoped>
.gantt-page { padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }

.stale-banner {
  background: rgba(202, 138, 4, 0.12);
  border: 1px solid rgba(202, 138, 4, 0.3);
  color: #92400e;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.82rem;
}
.stale-banner.error-banner { background: rgba(220, 38, 38, 0.1); border-color: rgba(220, 38, 38, 0.3); color: #991b1b; }

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 0.75rem;
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 0.75rem 1rem;
}
.toolbar-group { display: flex; align-items: flex-end; gap: 0.6rem; flex-wrap: wrap; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.72rem; color: var(--text-muted, #6b7280); font-weight: 600; }
.realtime-toggle {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
  font-size: 0.8rem;
  color: var(--text-body, #374151);
  height: 34px;
}

.gantt-container {
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 0.75rem;
  position: relative;
  height: calc(100vh - 230px);
  min-height: 480px;
}
.gantt-canvas { width: 100%; height: 100%; }
.gantt-empty {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted, #6b7280);
  font-size: 0.9rem;
  pointer-events: none;
}
.footnote { font-size: 0.75rem; color: var(--text-muted, #6b7280); margin: 0; }

.gantt-needle {
  position: absolute;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background-color: #f43f5e;
  z-index: 50;
  pointer-events: none;
  box-shadow: 0 0 6px #f43f5e;
}
.gantt-needle::before {
  content: attr(data-time);
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%);
  background: #f43f5e;
  color: white;
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 3px;
  font-weight: bold;
  white-space: nowrap;
}

/* Thư viện vis-timeline không tự đặt màu chữ/nền theo theme sáng/tối của app — nếu
   không ép rõ ràng, tên Máy VD/Tank kế thừa màu chữ từ theme tối (gần trắng) trong khi
   panel nhãn của thư viện vẫn nền trắng mặc định -> chữ trắng trên nền trắng, không đọc
   được (đúng lỗi báo cáo 2026-07-29 "không nhìn thấy tên của các tank"). */
:deep(.vis-labelset .vis-label),
:deep(.vis-labelset) { width: 170px !important; min-width: 170px !important; max-width: 170px !important; }
:deep(.vis-panel) { background-color: var(--bg-card, #fff) !important; }
:deep(.vis-label),
:deep(.vis-label .vis-inner) {
  background-color: var(--bg-card, #fff) !important;
  color: var(--text-title, #111827) !important;
}
:deep(.vis-nesting-group) { font-weight: 700; }
:deep(.vis-label .vis-inner) { padding: 0 10px !important; font-size: 0.82rem; }
:deep(.vis-time-axis .vis-text) { color: var(--text-body, #374151) !important; font-size: 0.78rem; }
:deep(.vis-time-axis .vis-grid.vis-minor) { border-color: var(--border-divider, #e2e8f0) !important; }
:deep(.vis-time-axis .vis-grid.vis-major) { border-color: var(--border-color, #cbd5e1) !important; }
:deep(.vis-grid.vis-vertical) { border-color: var(--border-divider, #e2e8f0) !important; }
:deep(.vis-panel.vis-center) { border-color: var(--border-color, #e2e8f0) !important; }
:deep(.vis-item) {
  border-radius: 4px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  border: 1px solid rgba(255,255,255,0.25) !important;
  min-width: 70px !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.25);
}
:deep(.gantt-item-label) { padding: 3px 7px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.6); white-space: nowrap; }
:deep(.vis-tooltip) {
  background-color: var(--bg-card, #fff) !important;
  color: var(--text-title, #111827) !important;
  border: 1px solid var(--border-color, #e2e8f0) !important;
  border-radius: 8px !important;
  padding: 10px 12px !important;
  font-size: 0.8rem !important;
  line-height: 1.7 !important;
  box-shadow: 0 10px 25px rgba(0,0,0,0.25) !important;
  max-width: 340px !important;
}
:deep(.gantt-item-running) {
  border: 2px solid #ef4444 !important;
  animation: ganttPulse 0.9s infinite alternate ease-in-out;
}
@keyframes ganttPulse {
  from { box-shadow: 0 0 2px rgba(239,68,68,0.3); opacity: 0.8; }
  to { box-shadow: 0 0 14px rgba(239,68,68,0.9); opacity: 1; }
}
:deep(.gantt-blink) { color: #f43f5e; font-weight: 700; }
</style>
