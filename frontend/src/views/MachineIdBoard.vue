<template>
  <!-- Bản dựng lại UserForm "mainform" của workbook MACHINE_ID_LOCKED.xlsm theo ĐÚNG toạ độ
       trích từ VBA designer (Left/Top/Width/Height tính bằng point): form 1413 × 755.25 pt,
       font Tahoma 8.25pt, hệ màu Windows cổ điển của MSForms (BTNFACE #F0F0F0, WINDOW
       #FFFFFF). Cố ý KHÔNG dùng design token của app — yêu cầu là giống hệt bản gốc.

       Bản gốc là màn hình CHỈ ĐỌC (không có nút, không nhập liệu): 18 máy VD, mỗi máy gồm
       khối "đã gửi" 4 thùng 1A/2B/3C/4D (tbl_SentLog, 24h gần nhất) và khối "đang chờ" 6
       dòng (tbl_input_all). Nạp lại mỗi 3 phút — Mod_time3min.RunAutoVD. -->
  <div class="vba-scroll" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <div class="vba-stage-wrap" ref="wrapRef">
      <div class="vba-fit"
           :style="{ width: Math.round(FORM_W * PT * scale) + 'px', height: Math.round(FORM_H * PT * scale) + 'px' }">
        <div class="vba-form"
             :style="{ width: FORM_W + 'pt', height: FORM_H + 'pt', transform: `scale(${scale})` }">

          <!-- Label113 -->
          <div class="vba-label vba-title" :style="box(18, 0, 330, 24)">DF PRODUCTION ORDER INFORMATION</div>

          <template v-for="row in ROWS" :key="'row-' + row.waitTop">
            <template v-for="(num, i) in row.nums" :key="'m-' + num">

              <!-- Nhãn tên máy (Tahoma 12 bold) -->
              <div class="vba-label vba-machine" :style="box(blockLeft(i), row.labelTop, 60, 12)">{{ vd(num) }}</div>

              <!-- Khối ĐÃ GỬI: mỗi thùng 1 dòng code/color/lv + 1 dòng thời gian -->
              <template v-for="(tank, t) in TANKS" :key="'s-' + num + tank">
                <div class="vba-label vba-tank" :style="box(blockLeft(i) - 12, row.sentTops[t], 12, 12)">{{ tank }}</div>

                <div class="vba-text vba-b" :style="cellStyle(blockLeft(i), row.sentTops[t], 60, sent(num, tank))">
                  {{ sent(num, tank)?.code || '' }}
                </div>
                <div class="vba-text vba-b" :style="cellStyle(blockLeft(i) + 60, row.sentTops[t], 54, sent(num, tank))">
                  {{ sent(num, tank)?.color || '' }}
                </div>
                <div class="vba-text" :style="cellStyle(blockLeft(i) + 114, row.sentTops[t], 24, sent(num, tank))">
                  {{ sent(num, tank)?.lv || '' }}
                </div>
                <!-- Ô thời gian KHÔNG bị tô màu trong bản gốc (LoadAllVD chỉ set BackColor cho
                     code/color/lv), giữ nguyên nền trắng. -->
                <div class="vba-text vba-b" :style="box(blockLeft(i), row.sentTops[t] + 18, 138, 18)">
                  {{ sent(num, tank)?.time || '' }}
                </div>
              </template>

              <!-- Khối ĐANG CHỜ: 6 dòng, không có nhãn thùng -->
              <template v-for="w in 6" :key="'w-' + num + '-' + w">
                <div class="vba-text vba-b" :style="cellStyle(blockLeft(i), row.waitTop + (w - 1) * 18, 60, waiting(num, w))">
                  {{ waiting(num, w)?.code || '' }}
                </div>
                <div class="vba-text vba-b" :style="cellStyle(blockLeft(i) + 60, row.waitTop + (w - 1) * 18, 54, waiting(num, w))">
                  {{ waiting(num, w)?.color || '' }}
                </div>
                <div class="vba-text" :style="cellStyle(blockLeft(i) + 114, row.waitTop + (w - 1) * 18, 24, waiting(num, w))">
                  {{ waiting(num, w)?.lv || '' }}
                </div>
              </template>

            </template>
          </template>

          <!-- TextBox541: vạch ngăn 2 nửa form (cao 3pt, nền COLOR_HIGHLIGHT) -->
          <div class="vba-text vba-rule" :style="box(18, 372, 1386, 3)"></div>

        </div>
      </div>
    </div>

    <div class="vba-statusbar">
      <span>{{ loading ? 'Đang nạp...' : 'Cập nhật lúc ' + lastLoadedText }}</span>
      <span class="vba-legend">
        Đã gửi:
        <i class="sw" style="background:#00b050"></i>&lt;6h
        <i class="sw" style="background:#ffc000"></i>&lt;12h
        <i class="sw" style="background:#ff0000"></i>≥12h
        &nbsp;|&nbsp; Đang chờ:
        <i class="sw" style="background:#ffffff"></i>&lt;24h
        <i class="sw" style="background:#b4cde6"></i>&lt;48h
        <i class="sw" style="background:#b4a0c8"></i>≥48h
      </span>
    </div>

    <FullscreenButton variant="vba" @change="refit" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import FullscreenButton from '../components/FullscreenButton.vue';

/* ===== Kích thước thật của UserForm gốc (InsideWidth/InsideHeight, đơn vị point) ===== */
const FORM_W = 1413;
const FORM_H = 755.25;
const PT = 4 / 3; // 96dpi: 1pt = 4/3 px

/* ===== Bố cục gốc trích từ designer =====
   - Nửa trên: VD10..VD18 (trái → phải), nửa dưới: VD09..VD01 (trái → phải).
   - Mỗi khối máy rộng 138pt (code 60 + color 54 + lv 24), cách nhau 156pt, bắt đầu ở Left 18.
   - Khoảng cách dòng "đã gửi" của nửa trên và nửa dưới KHÔNG bằng nhau (42/42/42 so với
     42/48/48) — đây là sai lệch có thật của bản gốc do đặt tay, giữ nguyên cho khớp. */
const ROWS = [
  { nums: [10, 11, 12, 13, 14, 15, 16, 17, 18], labelTop: 30, sentTops: [48, 90, 132, 174], waitTop: 252 },
  { nums: [9, 8, 7, 6, 5, 4, 3, 2, 1], labelTop: 396, sentTops: [414, 456, 504, 552], waitTop: 636 },
];
const TANKS = ['1A', '2B', '3C', '4D'];

const blockLeft = (i: number) => 18 + i * 156;
const vd = (n: number) => 'VD' + String(n).padStart(2, '0');

const box = (l: number, t: number, w: number, h: number) => ({
  left: l + 'pt',
  top: t + 'pt',
  width: w + 'pt',
  height: h + 'pt',
});

const cellStyle = (l: number, t: number, w: number, cell: Cell | null) => ({
  ...box(l, t, w, 18),
  backgroundColor: cell ? cell.bg : '#ffffff',
});

/* ===== Thu/phóng cả mặt form cho vừa MỘT màn hình (dùng chung cách làm với PrintOrderEntry) ===== */
const rootRef = ref<HTMLElement | null>(null);
const wrapRef = ref<HTMLElement | null>(null);
const scale = ref(1);
const rootH = ref<number | null>(null);
let ro: ResizeObserver | null = null;

function fitRoot() {
  const el = rootRef.value;
  if (!el) return;
  const top = el.getBoundingClientRect().top;
  const parent = el.parentElement;
  const padBottom = parent ? parseFloat(getComputedStyle(parent).paddingBottom) || 0 : 0;
  rootH.value = Math.max(360, Math.floor(window.innerHeight - top - padBottom));
}

function fitStage() {
  const el = wrapRef.value;
  if (!el) return;
  const w = el.clientWidth - 4;
  const h = el.clientHeight - 4;
  if (w <= 0 || h <= 0) return;
  scale.value = Math.max(0.2, Math.min(w / (FORM_W * PT), h / (FORM_H * PT), 2));
}

function fitAll() {
  fitRoot();
  nextTick(fitStage);
}

const refit = () => requestAnimationFrame(fitAll);

/* ===== Dữ liệu ===== */
type Cell = { code: string; color: string; lv: string; time: string; bg: string };

const sentMap = ref<Record<string, Cell>>({});
const waitMap = ref<Record<string, Cell[]>>({});
const loading = ref(false);
const lastLoadedText = ref('—');

const sent = (num: number, tank: string): Cell | null => sentMap.value[num + '|' + tank] ?? null;
const waiting = (num: number, idx: number): Cell | null => (waitMap.value[num] ?? [])[idx - 1] ?? null;

/** Mã máy trong web là "VD001..VD018", bản gốc dùng "VD01..VD18" — khớp theo phần số. */
const machineNum = (code: any): number | null => {
  const digits = String(code ?? '').replace(/\D/g, '');
  if (!digits) return null;
  const n = parseInt(digits, 10);
  return n >= 1 && n <= 18 ? n : null;
};

const ageHours = (iso: string) => (Date.now() - new Date(iso).getTime()) / 3_600_000;

/** Format$(dt3, "dd/mm hh:nn") của bản gốc. */
const fmtTime = (iso: string) => {
  const d = new Date(iso);
  const p = (v: number) => String(v).padStart(2, '0');
  return `${p(d.getDate())}/${p(d.getMonth() + 1)} ${p(d.getHours())}:${p(d.getMinutes())}`;
};

// LoadAllVD: <6h xanh, <12h vàng, còn lại đỏ.
const sentBg = (h: number) => (h < 6 ? '#00b050' : h < 12 ? '#ffc000' : '#ff0000');
// LoadAllVD_Input: <24h trắng, <48h xanh nhạt, còn lại tím.
const waitBg = (h: number) => (h < 24 ? '#ffffff' : h < 48 ? '#b4cde6' : '#b4a0c8');

/** Web không có cột TIME3 riêng: mốc "đã gửi" lấy print job ĐẦU TIÊN của đơn (cùng quy ước
    với /print-sent-log), fallback về created_at của dispatch. */
const confirmTimeOf = (d: any): string =>
  d.print_jobs?.[0]?.created_at ?? d.printJobs?.[0]?.created_at ?? d.created_at;

/** tbl_SentLog: mỗi (máy, thùng) lấy bản ghi mới nhất còn trong 24h. */
const loadSent = async () => {
  const res = await axios.get('/api/machine-dispatches/history');
  const rows: any[] = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
  const next: Record<string, Cell> = {};
  const bestTs: Record<string, number> = {};

  rows.forEach(d => {
    const b = d.batch || {};
    const num = machineNum(b.machine?.code);
    const tank = String(b.tank?.code ?? '');
    if (num === null || !TANKS.includes(tank)) return;

    const ts = confirmTimeOf(d);
    if (!ts) return;
    const h = ageHours(ts);
    if (h < 0 || h >= 24) return; // InLast24h

    const key = num + '|' + tank;
    const ms = new Date(ts).getTime();
    if (bestTs[key] !== undefined && bestTs[key] >= ms) return; // giữ bản ghi mới nhất

    bestTs[key] = ms;
    next[key] = {
      code: String(b.product_code ?? ''),
      color: String(b.color ?? ''),
      lv: String(b.level_code ?? ''),
      time: fmtTime(ts),
      bg: sentBg(h),
    };
  });

  sentMap.value = next;
};

/** tbl_input_all: mỗi máy TOP 6 theo TIME1 tăng dần. */
const loadWaiting = async () => {
  const res = await axios.get('/api/machine-dispatches');
  const rows: any[] = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
  const buckets: Record<string, { ts: string; cell: Cell }[]> = {};

  rows.forEach(d => {
    const b = d.batch || {};
    const num = machineNum(b.machine?.code);
    if (num === null) return;
    const ts = d.created_at;
    if (!ts) return;

    (buckets[num] ||= []).push({
      ts,
      cell: {
        code: String(b.product_code ?? ''),
        color: String(b.color ?? ''),
        lv: String(b.level_code ?? ''),
        time: '',
        bg: waitBg(ageHours(ts)),
      },
    });
  });

  const next: Record<string, Cell[]> = {};
  Object.entries(buckets).forEach(([num, list]) => {
    next[num] = list
      .sort((a, b) => new Date(a.ts).getTime() - new Date(b.ts).getTime())
      .slice(0, 6)
      .map(x => x.cell);
  });

  waitMap.value = next;
};

const loadAll = async () => {
  loading.value = true;
  try {
    await Promise.all([loadSent(), loadWaiting()]);
    lastLoadedText.value = new Date().toLocaleTimeString('vi-VN', { hour12: false });
  } catch (e) {
    console.error('MachineIdBoard load error:', e);
  } finally {
    loading.value = false;
  }
};

// RunAutoVD: nạp lại mỗi 3 phút.
let timer: any = null;

onMounted(() => {
  fitAll();
  window.addEventListener('resize', refit);
  if (typeof ResizeObserver !== 'undefined' && wrapRef.value) {
    ro = new ResizeObserver(() => fitStage());
    ro.observe(wrapRef.value);
  }

  loadAll();
  timer = setInterval(loadAll, 180_000);
  echo.channel('production-batches').listen('.updated', loadAll);
});

onUnmounted(() => {
  window.removeEventListener('resize', refit);
  ro?.disconnect();
  if (timer) clearInterval(timer);
  echo.leaveChannel('production-batches');
});
</script>

<style scoped>
.vba-scroll {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 8px;
  background-color: #808080;
  min-height: 360px;
}

.vba-stage-wrap {
  flex: 1;
  min-height: 0;
  overflow: auto;
  display: flex;
  justify-content: center;
  align-items: flex-start;
}

.vba-fit {
  flex: none;
}

/* Nền form = COLOR_BTNFACE của Windows, viền nổi kiểu cửa sổ MSForms */
.vba-form {
  position: relative;
  transform-origin: top left;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 0 0 0 1px #404040;
  font: 8.25pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

.vba-form > * {
  position: absolute;
  box-sizing: border-box;
  font: inherit;
  margin: 0;
}

/* TextBox chỉ đọc: nền trắng, viền chìm (fmSpecialEffectSunken) */
.vba-text {
  background-color: #ffffff;
  color: #000000;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 0 2px;
  overflow: hidden;
  white-space: nowrap;
  display: flex;
  align-items: center;
  cursor: default;
}

.vba-b { font-weight: bold; }

.vba-label {
  background-color: transparent;
  display: flex;
  align-items: center;
}

.vba-title { font-size: 14pt; }
.vba-machine { font-size: 12pt; font-weight: bold; }
.vba-tank { font-size: 9pt; }

/* TextBox541 — vạch ngăn, nền COLOR_HIGHLIGHT */
.vba-rule {
  background-color: #0078d7;
  border: none;
  padding: 0;
}

.vba-statusbar {
  flex: none;
  margin-top: 8px;
  padding: 4px 8px;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.vba-legend { display: flex; align-items: center; gap: 4px; }

.sw {
  display: inline-block;
  width: 10px;
  height: 10px;
  border: 1px solid #808080;
  margin-left: 4px;
}
</style>
