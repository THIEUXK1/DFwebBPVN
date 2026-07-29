<template>
  <!-- pageWrapper là hằng số (không phải ref/computed đổi được sau khi mount) — quyết định
       CHỈ 1 LẦN lúc khởi tạo component, KHÔNG đổi trong suốt vòng đời trang. Trước đó từng
       dùng 1 ref đổi được (showAdminNav) để bật/tắt việc bọc AppLayout — mỗi lần đổi buộc
       Vue hủy+dựng lại toàn bộ cây con bên trong, làm DOM chứa vis-timeline (.gantt-canvas)
       bị thay bằng node rỗng mới -> biểu đồ mất trắng (đúng lỗi báo cáo "mở layout lên thì
       k thấy gì"). Cố định pageWrapper ngay từ đầu (Admin luôn được bọc sẵn AppLayout,
       chỉ ẩn/hiện sidebar+topbar qua isFullscreen — xem services/layout.ts) loại bỏ hẳn
       nguy cơ remount này, đồng thời tái dùng đúng menu điều hướng quen thuộc của cả hệ
       thống thay vì tự làm 1 panel riêng. -->
  <component :is="pageWrapper">
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
        <label class="field">
          <span class="field-label">Tìm máy</span>
          <input v-model="machineSearch" type="text" class="form-select" placeholder="🔍 Tìm tên máy…" />
        </label>
      </div>
      <div class="toolbar-group">
        <button class="btn btn-secondary btn-sm" @click="loadGantt()" :disabled="loading">{{ loading ? 'Đang tải...' : '🔄 Tải lại' }}</button>
        <button class="btn btn-secondary btn-sm" @click="moveToNow">🕐 Về hiện tại</button>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoMove" /> Auto cuộn</label>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoRefresh" /> Auto tải lại 30s</label>
        <!-- Người xem công khai (không phải Admin) không có AppLayout nên không có nút
             chuyển theme ở topbar chung — trang tự có nút riêng, dùng chung cho cả 2
             trường hợp (kể cả khi Admin đã lộ AppLayout, đỡ phải rẽ nhánh UI). -->
        <button
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="toggleTheme"
          :title="theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
        >
          <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="16" />
        </button>
        <button
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="toggleBrowserFullscreen"
          :title="isBrowserFullscreen ? 'Thoát toàn màn hình (F11)' : 'Toàn màn hình (F11)'"
        >
          {{ isBrowserFullscreen ? '⤢' : '⛶' }}
        </button>
        <!-- Trang public mặc định không sidebar/topbar. Với Admin đã đăng nhập, trang được
             bọc sẵn AppLayout (ẩn qua isFullscreen) — nút này chỉ bật isFullscreen=false để
             lộ ra đúng menu điều hướng quen thuộc của cả hệ thống (yêu cầu 2026-07-29: "mở
             layout hay dùng ấy, mà dữ liệu vẫn nhìn thấy"). Không hiện với người xem công
             khai (không phải Admin, lúc đó pageWrapper = 'div', không có AppLayout để mở). -->
        <button
          v-if="isAdminUser"
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="isFullscreen = !isFullscreen"
          :title="isFullscreen ? 'Mở menu điều hướng (Admin)' : 'Ẩn menu điều hướng'"
        >
          <SvgIcon name="menu" size="16" />
        </button>
      </div>
    </div>

    <div class="gantt-container mt-2">
      <div id="ganttNeedle" class="gantt-needle" :style="needleStyle" :data-time="needleLabel" v-show="needleVisible"></div>
      <div ref="timelineEl" class="gantt-canvas"></div>
      <p v-if="!loading && totalRecords === 0" class="gantt-empty">Không có task nào khớp trong khoảng ngày/tên máy đã chọn.</p>
    </div>
    <p class="footnote">Tổng số task hiển thị: <strong>{{ totalRecords }}</strong> · viền đỏ nhấp nháy = mẻ đang chạy, chưa kết thúc</p>
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';
import { Timeline, DataSet } from 'vis-timeline/standalone';
import 'vis-timeline/styles/vis-timeline-graph2d.min.css';
import { isFullscreen } from '../services/layout';
import { theme, toggleTheme } from '../services/theme';
import SvgIcon from '../components/SvgIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import { useAuthStore } from '../stores/auth';

const authStore = useAuthStore();
// Snapshot MỘT LẦN lúc khởi tạo — KHÔNG dùng ref/computed. Trạng thái đăng nhập không đổi
// trong vòng đời trang này (không có luồng login/logout ngay trên trang Gantt), nên chốt
// cứng giá trị này đảm bảo pageWrapper bên dưới không bao giờ đổi sau khi mount (xem lý do
// tại ghi chú trong <template>).
const isAdminUser = authStore.isAdmin;
const pageWrapper = isAdminUser ? AppLayout : 'div';

// Toàn màn hình trình duyệt thật (Fullscreen API, tương đương phím F11) — khác với
// services/layout.ts isFullscreen (cơ chế ẩn/hiện sidebar+topbar của AppLayout, dùng cho
// nút "Mở menu điều hướng" ở trên).
const isBrowserFullscreen = ref(!!document.fullscreenElement);
const syncBrowserFullscreenState = () => {
  isBrowserFullscreen.value = !!document.fullscreenElement;
  // Khung nhìn đổi kích thước khi vào/thoát fullscreen — vis-timeline không tự vẽ lại
  // (cùng lý do với watch(isFullscreen) bên dưới, đợi 1 nhịp cho reflow xong).
  setTimeout(() => timeline?.redraw(), 60);
};
const toggleBrowserFullscreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {});
  } else {
    document.exitFullscreen().catch(() => {});
  }
};

const RAW_TASK_STATUS_LABELS: Record<string, string> = {
  '10': 'Chờ hệ thống xử lý',
  '20': 'Đang chuyển trạng thái',
  '30': 'Đang được hệ thống xử lý',
  '40': 'Task đã kết thúc',
  '99': 'Task bị hủy/xóa',
};

// Độ dài vẽ tối thiểu của mỗi thanh Gantt — mẻ ngắn hơn vẫn được vẽ rộng bằng đúng ngần
// này thời gian (không phải ép cứng theo px), giữ đúng bản chất "thanh dài = thời gian dài".
const MIN_VISUAL_DURATION_MS = 2 * 60 * 60 * 1000;

// Độ rộng cột tên máy/tank — mã máy (vd "VD006") và tên tank (vd "1A") đều ngắn, 170px dư
// quá nhiều diện tích lẽ ra dành cho phần vẽ Gantt (yêu cầu 2026-07-29). Một hằng số dùng
// chung cho cả CSS (qua v-bind trong <style>) lẫn tính vị trí kim đỏ (calculateNeedle) và
// header tìm máy, để không bao giờ bị lệch nhau. KHÔNG hạ xuống dưới ~120px: đã kiểm chứng
// bằng thực nghiệm (chụp màn hình headless Chromium, cuộn thật) rằng ép cột nhãn xuống đúng
// khoảng 100-115px khiến vis-timeline tính sai layout nội bộ cho các hàng Máy VD CHƯA từng
// lọt vào khung nhìn — tên máy biến mất hoàn toàn khỏi pixel vẽ ra dù DOM/computed style vẫn
// báo bình thường (đúng lỗi báo cáo 2026-07-29 "chỉ máy đầu tiên có tên, các máy sau trống",
// tái hiện được ở TOÀN BỘ máy nằm ngoài khung nhìn ban đầu, không riêng máy đầu). 130px đã
// test ổn định qua nhiều lần cuộn sâu, còn 100-115px vỡ gần như luôn luôn — không phải lỗi
// ngẫu nhiên/paint mà là ngưỡng cứng của thư viện, nên đừng chỉnh lại con số này về gần 100
// nếu chưa test lại đúng cách (cuộn bằng chuột thật xuống hết danh sách, không phải chỉ xem
// vài máy đầu).
const LABEL_COLUMN_WIDTH = 130;
const labelColumnWidthCss = `${LABEL_COLUMN_WIDTH}px`;

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
const machineSearch = ref('');

const timelineEl = ref<HTMLDivElement | null>(null);
let timeline: any = null;
const groupsDataSet = new DataSet<any>();
const itemsDataSet = new DataSet<any>();
// Danh sách gốc (chưa lọc theo machineSearch) — giữ lại để lọc lại tại chỗ mỗi khi người
// dùng gõ ô tìm máy, không cần gọi lại API.
let allGroups: any[] = [];
let allItems: any[] = [];

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
    const start = new Date(it.start);
    const realEnd = it.uncompleted ? syncSnapshot.value : new Date(it.end);
    // Độ dài hiển thị tối thiểu 2h (yêu cầu 2026-07-29) — mẻ chạy thật sự ngắn hơn vẫn được
    // vẽ thanh rộng bằng đúng 2h theo tỉ lệ thời gian hiện tại (co giãn đúng theo zoom, khác
    // với ép min-width theo px cố định), đủ chỗ cho nhãn trong đa số trường hợp mà vẫn giữ
    // đúng bản chất "biểu thị theo thời gian" của Gantt chart. Chỉ ảnh hưởng bề rộng vẽ —
    // tooltip/nhãn vẫn dùng realEnd (giờ kết thúc thật) để không hiển thị sai lệch dữ liệu.
    const displayEnd = realEnd.getTime() - start.getTime() < MIN_VISUAL_DURATION_MS
      ? new Date(start.getTime() + MIN_VISUAL_DURATION_MS)
      : realEnd;
    const label = it.color && it.productCode ? `${it.color}-${it.productCode}` : (it.taskTitle || '—');
    const color = barColor(it.group + it.color + it.productCode);
    return {
      id: it.id,
      group: it.group,
      start,
      end: displayEnd,
      className: it.uncompleted ? 'gantt-item-running' : '',
      style: `background-color: ${color};`,
      // Nền màu đặt luôn trên nhãn (thẻ) chứ không chỉ trên thanh ngoài — phòng trường hợp
      // zoom quá xa khiến 2h vẫn chưa đủ chỗ cho nhãn (xem CSS .vis-item min-width và
      // .vis-item-overflow overflow:visible bên dưới); nếu không tô lại màu ở đây, phần chữ
      // tràn ra sẽ không có nền, trông như bị "hụt" nền so với chữ.
      content: `<div class="gantt-item-label" style="background-color: ${color};">${label}</div>`,
      title: buildTooltip({ ...it, end: realEnd }),
    };
  });

  allGroups = groups;
  allItems = items;
  applyMachineFilter();
};

// Máy đã ghim (bấm icon 📌 trên tiêu đề máy) — id máy, không phải Tank. Sống độc lập với
// allGroups/allItems nên không mất khi auto tải lại 30s hay đổi ô tìm kiếm.
const pinnedMachineIds = ref<Set<string>>(new Set());

const togglePinMachine = (machineId: string) => {
  if (!machineId) return;
  const next = new Set(pinnedMachineIds.value);
  if (next.has(machineId)) next.delete(machineId);
  else next.add(machineId);
  pinnedMachineIds.value = next;
  applyMachineFilter();
};

// Lọc theo tên/mã máy gõ ở ô tìm kiếm (header cột tên máy) — khớp theo group CHA (Máy VD,
// có nestedGroups), giữ nguyên toàn bộ Tank con của máy khớp chứ không lọc riêng từng Tank
// (đúng nhu cầu "tìm tên máy", không phải "tìm tank"). Lọc tại chỗ trên dữ liệu đã tải,
// không gọi lại API. Đồng thời vẽ lại icon ghim + đẩy máy đã ghim lên đầu danh sách mỗi
// khi hàm này chạy lại (yêu cầu 2026-07-29).
const applyMachineFilter = () => {
  const q = machineSearch.value.trim().toLowerCase();
  let visibleGroups = allGroups;
  if (q) {
    const matchedMachineIds = new Set(
      allGroups.filter(g => g.nestedGroups && String(g.content).toLowerCase().includes(q)).map(g => g.id)
    );
    visibleGroups = allGroups.filter(g => {
      if (g.nestedGroups) return matchedMachineIds.has(g.id);
      return matchedMachineIds.has(String(g.id).split('::')[0]);
    });
  }
  const visibleGroupIds = new Set(visibleGroups.map(g => g.id));
  const visibleItems = allItems.filter(it => visibleGroupIds.has(it.group));

  // Chỉ group CHA (nestedGroups) mới có nút ghim — Tank con giữ content gốc, không đổi.
  // Máy đã ghim trừ hẳn 100000 vào order (danh sách gốc chỉ vài chục máy, order gốc luôn
  // nhỏ hơn nhiều) để luôn đứng trước máy chưa ghim, vẫn giữ đúng thứ tự tương đối trong
  // từng nhóm ghim/chưa ghim.
  const renderedGroups = visibleGroups.map(g => {
    if (!g.nestedGroups) return g;
    const isPinned = pinnedMachineIds.value.has(g.id);
    // Icon ghim đặt bên PHẢI tên máy (không sát mũi tên đóng/mở ở đầu dòng nữa) — dễ ấn
    // hơn, tránh đứng chen giữa 2 mục tiêu bấm nhỏ sát nhau (yêu cầu 2026-07-29).
    return {
      ...g,
      order: isPinned ? g.order - 100000 : g.order,
      // Chỉ ghim tên máy (sticky, xem CSS .gantt-machine-sticky) khi máy có > 2 Tank —
      // máy chỉ 1-2 Tank thì toàn bộ Tank đã vừa khung nhìn, ghim không có tác dụng, mà
      // lại làm Tank đầu tiên (vd "1A") bị dính/che dưới thanh tên máy mỗi khi cuộn nhẹ
      // qua machine đó (đúng lỗi báo cáo "A1 tank đang bị khuất", 2026-07-29). Máy nhiều
      // Tank vẫn ghim bình thường vì lợi ích thấy tên máy khi cuộn sâu lớn hơn nhược điểm
      // che thoáng qua đúng 1 Tank đầu trong lúc cuộn.
      className: g.nestedGroups.length > 2 ? 'gantt-machine-sticky' : '',
      content: `<span class="gantt-machine-row"><span class="gantt-machine-name">${g.content}</span><span class="gantt-pin-btn${isPinned ? ' is-pinned' : ''}" data-machine-id="${g.id}" title="${isPinned ? 'Bỏ ghim' : 'Ghim lên đầu'}">📌</span></span>`,
    };
  });

  groupsDataSet.clear();
  groupsDataSet.add(renderedGroups);
  itemsDataSet.clear();
  itemsDataSet.add(visibleItems);
  totalRecords.value = visibleItems.length;

  // clear()+add() phát sự kiện 'add' bình thường (đủ để vẽ lại nội dung/nhãn — search và
  // icon ghim đã chạy đúng qua đường này), nhưng THỨ TỰ hàng dựa trên field order chỉ được
  // tính lại chắc chắn khi gọi setGroups() — API chính thức của vis-timeline để "nạp lại
  // toàn bộ", đảm bảo _orderGroups() chạy lại (đúng lỗi báo cáo: bấm ghim không đẩy máy lên
  // đầu — icon đổi màu nhưng vị trí hàng không đổi).
  if (timeline) timeline.setGroups(groupsDataSet);
};

watch(machineSearch, applyMachineFilter);

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
    // stack:false (yêu cầu 2026-07-29) — 1 Tank chỉ có đúng 1 hàng; các mẻ trùng thời gian
    // trong cùng Tank đó vẽ đè lên nhau ngay trên hàng đó thay vì tự tách thêm hàng phụ
    // (mặc định stack:true của vis-timeline). Đè lên nhau không mất dữ liệu — mỗi mẻ vẫn
    // bấm/hover riêng được để xem tooltip.
    stack: false,
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
    // Tên option đúng là overflowMethod, không phải overflow (console cảnh báo "Unknown
    // option detected" trước khi sửa) — 'flip' để tooltip tự lật sang trái khi sát mép phải.
    tooltip: { followMouse: true, overflowMethod: 'flip' },
    // vis-timeline mặc định lọc XSS trên MỌI content HTML tự cung cấp (group content, item
    // content, item title/tooltip) — bộ lọc mặc định của thư viện `xss` xóa sạch class/
    // data-*/title, khiến .gantt-machine-row/.gantt-machine-name/.gantt-pin-btn và
    // data-machine-id biến mất khỏi DOM thật dù HTML string vẫn đúng (đúng lỗi báo cáo
    // 2026-07-29: icon ghim bấm không ăn — do data-machine-id đã bị lọc mất, không phải do
    // logic click sai). Phải khai báo rõ whiteList đủ các thẻ/thuộc tính đang thực sự dùng
    // (span cho group content + <span class="gantt-blink">, div/strong/br cho item content
    // và tooltip trong buildTooltip()) thay vì tắt hẳn xss — tắt hẳn sẽ mất luôn bảo vệ cho
    // nội dung có lẫn dữ liệu từ BPDB (taskTitle/errorMessage) hiển thị trong tooltip. Thiếu
    // 1 thẻ nào trong whiteList sẽ khiến thẻ đó hiện ra dạng chữ HTML thô thay vì bị render
    // (đã gặp khi mới thêm whiteList chỉ có span — content thanh Gantt vỡ thành text thô).
    xss: {
      filterOptions: {
        whiteList: {
          span: ['class', 'title', 'data-machine-id'],
          div: ['class', 'style'],
          strong: ['class'],
          br: [],
        },
      },
    },
  } as any);
  timeline.on('rangechange', calculateNeedle);
  timeline.on('rangechanged', calculateNeedle);

  // Icon ghim (.gantt-pin-btn) được vẽ bằng HTML thô trong content của group (vis-timeline
  // không có slot/sự kiện riêng cho nhãn group) — bắt sự kiện ở đây bằng delegation.
  // vis-timeline dùng hammerjs để nhận diện tap/đóng-mở nhóm, hammerjs lắng nghe mousedown/
  // touchstart trực tiếp trên .vis-panel.vis-left (bubble phase, KHÔNG phải click) — nên
  // phải chặn từ mousedown/touchstart ở PHA CAPTURE, sớm hơn hammerjs, mới ngăn được nó
  // toggle đóng/mở nhóm. CHỈ stopPropagation, KHÔNG preventDefault ở bước này — gọi
  // preventDefault trên touchstart sẽ khiến trình duyệt (trên thiết bị/màn hình cảm ứng)
  // không bắn sự kiện 'click' tổng hợp nữa, làm icon ghim im re không phản ứng gì (đúng lỗi
  // báo cáo). Việc toggle ghim thật sự vẫn xử lý ở 'click' bên dưới.
  const stopIfPinButton = (event: Event) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.gantt-pin-btn')) return;
    event.stopPropagation();
  };
  timelineEl.value.addEventListener('mousedown', stopIfPinButton, true);
  timelineEl.value.addEventListener('touchstart', stopIfPinButton, true);
  timelineEl.value.addEventListener('click', (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    const btn = target.closest('.gantt-pin-btn') as HTMLElement | null;
    if (!btn) return;
    event.stopPropagation();
    event.preventDefault();
    togglePinMachine(btn.dataset.machineId || '');
  }, true);
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
  needleStyle.value = `left: calc(${LABEL_COLUMN_WIDTH}px + (100% - ${LABEL_COLUMN_WIDTH}px) * ${pct / 100});`;
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

// Chỉ có hiệu lực thị giác khi isAdminUser (trang được bọc AppLayout) — bật/tắt
// isFullscreen đổi kích thước khung .content-container (ẩn/hiện sidebar+topbar), vis-timeline
// không tự vẽ lại theo nên phải ép redraw().
watch(isFullscreen, () => setTimeout(() => timeline?.redraw(), 60));

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let moveTimer: ReturnType<typeof setInterval> | null = null;
// isFullscreen là singleton DÙNG CHUNG toàn app (services/layout.ts) — nếu Admin điều
// hướng sang trang Gantt bằng router-link từ trang khác (không reload trang, singleton giữ
// nguyên giá trị cũ), phải lưu lại giá trị đó để khôi phục khi rời trang, tránh làm "rò rỉ"
// trạng thái ẩn/hiện sidebar sang các trang khác trong cùng phiên SPA.
const previousIsFullscreen = isFullscreen.value;

onMounted(async () => {
  if (isAdminUser) {
    // Mặc định ẩn sidebar+topbar khi vừa vào trang Gantt — giữ đúng giao diện gọn quen
    // thuộc của trang public, chỉ lộ ra khi Admin bấm nút "Mở menu điều hướng".
    isFullscreen.value = true;
  }
  await loadGantt();
  refreshTimer = setInterval(() => {
    if (autoRefresh.value) loadGantt();
  }, 30000);
  moveTimer = setInterval(() => {
    if (autoMove.value && timeline) moveToNow();
  }, 5000);
  document.addEventListener('fullscreenchange', syncBrowserFullscreenState);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  if (moveTimer) clearInterval(moveTimer);
  document.removeEventListener('fullscreenchange', syncBrowserFullscreenState);
  if (isAdminUser) isFullscreen.value = previousIsFullscreen;
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
  height: calc(100vh - 180px);
  min-height: 560px;
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
/* Ép độ rộng cột nhãn qua .vis-panel.vis-left (khung cuộn dọc thật) — CỐ Ý KHÔNG ép qua
   .vis-labelset/.vis-label như bản trước đó. Đã kiểm chứng bằng thực nghiệm: ép width lên
   chính .vis-labelset và/hoặc từng .vis-label (dù dùng !important hay không, dù ép ở mức
   nào ~100-115px) khiến vis-timeline tính sai layout nội bộ cho các hàng nhóm CHƯA từng
   render trong khung nhìn ban đầu — tên máy biến mất khỏi pixel vẽ ra (DOM/computed style
   vẫn đúng, chỉ paint sai) ngay khi cuộn chuột thật xuống tới chúng, xem ghi chú tại
   LABEL_COLUMN_WIDTH phía trên. Ép ở .vis-panel.vis-left thay vào đó (để vis-timeline tự do
   tính toán .vis-labelset/.vis-label bên trong theo cách riêng của nó) tránh được lỗi này
   hoàn toàn qua nhiều lần test cuộn sâu. */
:deep(.vis-panel.vis-left) { width: v-bind(labelColumnWidthCss) !important; }
:deep(.vis-panel) { background-color: var(--bg-card, #fff) !important; }
:deep(.vis-label),
:deep(.vis-label .vis-inner) {
  background-color: var(--bg-card, #fff) !important;
  color: var(--text-title, #111827) !important;
}
:deep(.vis-nesting-group) { font-weight: 700; }
/* z-index (KHÔNG kèm position:sticky) trên MỌI hàng tên Máy VD — bắt buộc phải có, không
   phải để ghim/hiệu ứng thị giác. Đã kiểm chứng bằng thực nghiệm (Chromium headless, cuộn
   chuột thật, lặp lại nhiều lần): thiếu z-index này, vis-timeline vẽ SAI hoàn toàn các hàng
   Máy VD (không riêng máy nào) CHƯA từng lọt vào khung nhìn ban đầu — tên máy biến mất khỏi
   pixel vẽ ra dù DOM/computed style vẫn báo bình thường (đúng lỗi báo cáo 2026-07-29 "từ
   VD006 => VD013 không thấy tên máy", tái diễn ở BẤT KỲ máy nào ngoài khung nhìn lúc mở
   trang, không cố định ở VD006-013 — vị trí cụ thể phụ thuộc kích thước màn hình/vị trí
   cuộn). Chỉ z-index đơn thuần (Chromium tự thăng cấp compositing layer) là đủ để buộc
   trình duyệt vẽ lại đúng — KHÔNG cần position:sticky cho việc này (đã thử width nhỏ hơn,
   translateZ, will-change trên container: đều không ăn thua; chỉ z-index trên chính hàng
   mới hết). Xem thêm ghi chú tại LABEL_COLUMN_WIDTH phía trên <script>. */
:deep(.vis-nesting-group) { z-index: 6; }
/* Ghim tên Máy VD ở đầu khung nhìn khi cuộn qua các Tank con của nó (yêu cầu 2026-07-29) —
   group cha (có nestedGroups) là hàng .vis-label thường (không absolute-position, chỉ
   append vào .vis-labelset theo flow bình thường — xem Group._create trong vis-timeline),
   nên position:sticky áp được thẳng lên nó, dính vào mép trên của .vis-panel.vis-left
   (panel cuộn dọc thật khi verticalScroll:true) tới khi nhóm kế tiếp đẩy nó lên. Đây là hiệu
   ứng UI THUẦN TÚY (khác hẳn z-index ở rule ngay trên — rule đó là fix bug bắt buộc áp dụng
   toàn bộ, rule này là tính năng chỉ áp cho máy đủ lớn) nên vẫn giữ đúng như cũ: CHỈ áp dụng
   cho máy có class .gantt-machine-sticky (>2 Tank, gán ở applyMachineFilter) — máy 1-2 Tank
   không ghim vì phần .vis-label thường (không sticky) của Tank đầu tiên (vd "1A") nằm ngay
   dưới header trong luồng bình thường, khi header dính lại (sticky) nó sẽ đè lên đúng Tank
   đó khiến Tank bị khuất hoàn toàn trong lúc cuộn qua máy — không có Tank nào khác đủ xa để
   bù lại, khác với máy nhiều Tank (lỗi báo cáo "A1 tank đang bị khuất", 2026-07-29). Đã kiểm
   chứng lại: nếu bỏ điều kiện >2 Tank này và ghim sticky cho TẤT CẢ (kể cả máy 1 Tank như
   VDG01-08), Tank duy nhất của các máy đó biến mất khỏi màn hình hoàn toàn trong lúc cuộn —
   đúng lỗi cũ tái diễn — nên tuyệt đối không gộp chung với rule z-index-cho-tất-cả phía trên. */
:deep(.vis-label.vis-nesting-group.gantt-machine-sticky) {
  position: sticky;
  top: 0;
  z-index: 6;
}
/* .vis-inner mặc định inline-block, co theo nội dung — ép width cố định (đúng bằng cột
   nhãn) để .gantt-machine-row bên trong dùng justify-content:space-between đẩy được icon
   ghim ra sát mép phải cột, cách xa mũi tên đóng/mở ở đầu dòng (yêu cầu 2026-07-29: "để
   icon ghim ra chỗ dễ ấn, bên phải tên máy"). Giữ nguyên display mặc định (inline-block vẫn
   tôn trọng width tường minh, không cần đổi sang block) — xem ghi chú đầy đủ về ngưỡng width
   an toàn tại LABEL_COLUMN_WIDTH phía trên <script>. */
:deep(.vis-nesting-group .vis-inner) { width: v-bind(labelColumnWidthCss); box-sizing: border-box; }
:deep(.gantt-machine-row) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 4px;
  width: 100%;
}
:deep(.gantt-machine-name) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
:deep(.gantt-pin-btn) {
  flex: 0 0 auto;
  cursor: pointer;
  opacity: 0.35;
  font-size: 0.85rem;
  line-height: 1;
  padding: 2px;
  user-select: none;
}
:deep(.gantt-pin-btn:hover) { opacity: 0.75; }
:deep(.gantt-pin-btn.is-pinned) { opacity: 1; }
:deep(.vis-label .vis-inner) { padding: 0 10px !important; font-size: 0.82rem; }
/* Tên Tank (1A, 2B...) căn xuống MÉP DƯỚI của hàng thay vì mặc định nằm sát mép trên
   (yêu cầu 2026-07-29) — hàng Tank co giãn chiều cao theo số mẻ chồng lên nhau trong cùng
   khung giờ (stack:false vẫn tính chiều cao theo mẻ dài nhất còn hiển thị lúc đó), nên khi
   hàng cao hơn 20px mặc định, tên Tank cần dính xuống dưới để luôn ngang hàng với thanh
   Gantt mới nhất/gần trục thời gian nhất thay vì trôi lên đầu hàng trống phía trên. Parent
   .vis-label đã sẵn position:relative (CSS gốc vis-timeline), chỉ cần định vị tuyệt đối
   .vis-inner theo mép dưới. CHỈ áp dụng cho Tank con (.vis-nested-group) — tên Máy VD giữ
   nguyên canh giữa dọc như cũ, không đổi. */
:deep(.vis-nested-group .vis-inner) { position: absolute; left: 0; bottom: 0; }
:deep(.vis-time-axis .vis-text) { color: var(--text-body, #374151) !important; font-size: 0.78rem; }
:deep(.vis-time-axis .vis-grid.vis-minor) { border-color: var(--border-divider, #e2e8f0) !important; }
:deep(.vis-time-axis .vis-grid.vis-major) { border-color: var(--border-color, #cbd5e1) !important; }
:deep(.vis-grid.vis-vertical) { border-color: var(--border-divider, #e2e8f0) !important; }
:deep(.vis-panel.vis-center) { border-color: var(--border-color, #e2e8f0) !important; }
/* Trước đó thử cách để nền màu chỉ nằm trên .gantt-item-label (nhãn) rồi tràn ra ngoài
   thanh .vis-item hẹp qua overflow:visible — lỗi: vis-timeline tự dịch chuyển
   .vis-item-content bằng transform riêng (tính năng "giữ nhãn hiển thị khi thanh bị kéo
   lệch khỏi khung nhìn", RangeItem.repositionX) dựa trên đúng bề rộng .vis-item thật hẹp
   — khi zoom/kéo thu nhỏ khung giờ, transform này lệch khỏi vị trí nền màu, làm chữ và
   nền tách rời nhau. Ép min-width theo max-content NGAY TRÊN .vis-item (thanh + nền màu
   thật, không phải lớp phủ riêng) mới tránh được: chữ và nền luôn là cùng 1 khối, không
   thể tách nhau ở bất kỳ mức zoom nào. Đánh đổi: mẻ ngắn đứng sát nhau có thể đè nhẹ lên
   nhau theo chiều ngang — chấp nhận được vì đây là chart chỉ để xem, không kéo/thả. */
:deep(.vis-item) {
  border-radius: 4px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  border: 1px solid rgba(255,255,255,0.25) !important;
  min-width: max(70px, max-content) !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.25);
}
:deep(.vis-item-overflow) { overflow: visible !important; }
:deep(.gantt-item-label) {
  display: inline-block;
  width: max-content;
  border-radius: 4px;
  padding: 3px 7px;
  color: #fff;
  text-shadow: 0 1px 2px rgba(0,0,0,0.6);
  white-space: nowrap;
  position: relative;
  z-index: 1;
}
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
