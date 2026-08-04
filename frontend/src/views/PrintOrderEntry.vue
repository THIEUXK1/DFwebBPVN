<template>
  <!-- Dựng lại UserForm TO_SEND của workbook "Copy of Copy of DF002 ... PRINTER LANDSCAPE
       - jit qr sending - 15l special(1).xlsm" theo ĐÚNG tọa độ trích từ VBA designer
       (đơn vị pt, form 1416 × 733pt — đúng tên "PRINTER LANDSCAPE").
       Bảng màu cố định theo hệ màu Windows cổ điển của MSForms, cố ý KHÔNG dùng design
       token dark/light của app vì yêu cầu là giống hệt bản gốc. -->
  <div class="vba-scroll" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <!-- Cả mặt form được thu/phóng bằng MỘT phép transform: scale() nên tỉ lệ giữa các ô, cỡ chữ
         và độ dày viền không bao giờ lệch so với bản gốc dù màn hình to nhỏ thế nào. -->
    <div class="vba-stage-wrap" ref="wrapRef">
      <!-- Lớp đệm mang ĐÚNG kích thước SAU khi thu/phóng: transform: scale() không đổi ô layout của
           phần tử, thiếu lớp này thì lúc phóng > 1 mặt form tràn ra ngoài vùng cuộn và bị cắt mép. -->
      <div class="vba-fit"
           :style="{ width: Math.round(FORM_W * PT * scale) + 'px', height: Math.round(FORM_H * PT * scale) + 'px' }">
        <div class="vba-form"
             :style="{ width: FORM_W + 'pt', height: FORM_H + 'pt', transform: `scale(${scale})` }">

          <!-- ============ Lưới TO_SEND: 27 dòng, 3 khối cột × 9 dòng ============ -->
          <template v-for="(slot, i) in sendSlots" :key="'send-' + i">
            <input
              :value="slot ? i + 1 : ''"
              readonly
              class="vba-text vba-cell"
              :style="{ ...box(sendLeft(i), sendTop(i), 48, 25.5), backgroundColor: rowBg(slot) }"
              :title="slot?.id"
            />
            <input :value="slot?.batch?.color || ''" readonly class="vba-text vba-cell"
                   :style="{ ...box(sendLeft(i) + 48, sendTop(i), 120, 25.5), backgroundColor: rowBg(slot) }" />
            <input :value="slot?.batch?.product_code || ''" readonly class="vba-text vba-cell"
                   :style="{ ...box(sendLeft(i) + 168, sendTop(i), 72, 25.5), backgroundColor: rowBg(slot) }" />
            <input :value="slot?.batch?.machine?.code || ''" readonly class="vba-text vba-cell"
                   :style="{ ...box(sendLeft(i) + 240, sendTop(i), 54, 25.5), backgroundColor: rowBg(slot) }" />
            <input :value="slot?.batch?.tank?.code || ''" readonly class="vba-text vba-cell"
                   :style="{ ...box(sendLeft(i) + 294, sendTop(i), 36, 25.5), backgroundColor: rowBg(slot) }" />

            <button class="vba-btn" :style="box(sendLeft(i) + 330, sendTop(i), 48, 24)"
                    :disabled="!slot" title="Mở phiếu xem trước để in (TO_SEND.HandleSendPrint)"
                    @click="onSendPrint(slot)">print</button>

            <input type="checkbox" class="vba-check" :style="box(sendLeft(i) + 384, sendTop(i) + 6, 11.25, 18)"
                   :checked="!!slot?.scale_checked" :disabled="!slot"
                   title="scale_check — tick là ghi thẳng xuống DB ngay (TO_SEND.SavePrintCheck)"
                   @change="onToggleScaleCheck(slot, ($event.target as HTMLInputElement).checked)" />

            <button class="vba-btn" :style="box(sendLeft(i) + 402, sendTop(i), 36, 24)"
                    :disabled="!slot || confirmingId === slot?.id"
                    title="Xác nhận đã in & đã gửi — đơn rời hàng chờ sang lịch sử (TO_SEND.ConfirmRow)"
                    @click="onConfirm(slot)">OK</button>
          </template>

          <!-- ============ 18 bảng chờ in theo máy, 2 băng × 9 máy ============ -->
          <template v-for="p in panels" :key="'panel-' + p.machineNo">
            <label class="vba-label vba-panel-label" :style="box(p.left + 6, p.labelTop, 42, 18)">{{ p.label }}</label>

            <template v-for="(b, r) in p.rows" :key="'panel-' + p.machineNo + '-' + r">
              <!-- Ô id rộng 6pt: bản gốc dùng nó làm dải màu báo tuổi đơn, không hiện chữ -->
              <input readonly class="vba-text vba-cell"
                     :style="{ ...box(p.left, p.rowTop + r * 18, 6, 18), backgroundColor: vbaAgeColor(b?.created_at) }"
                     :title="b ? 'Tạo lúc ' + fmt(b.created_at) : ''" />
              <input :value="b?.color || ''" readonly class="vba-text vba-cell"
                     :style="box(p.left + 6, p.rowTop + r * 18, 48, 18)" />
              <input :value="b?.product_code || ''" readonly class="vba-text vba-cell"
                     :style="box(p.left + 54, p.rowTop + r * 18, 36, 18)" />
              <input :value="b?.level_code || ''" readonly class="vba-text vba-cell"
                     :style="box(p.left + 90, p.rowTop + r * 18, 24, 18)" />
              <button class="vba-btn vba-btn-tiny" :style="box(p.left + 114, p.rowTop + r * 18, 24, 18)"
                      :disabled="!b" title="Mở phiếu xem trước để in (TO_SEND.HandlewaitPrint)"
                      @click="onWaitPrint(b)">print</button>
              <input type="checkbox" class="vba-check" :style="box(p.left + 138, p.rowTop + r * 18, 11.5, 18.75)"
                     disabled title="Bảng production_batches chưa có cột scale_check tương ứng — xem ghi chú" />
            </template>
          </template>
        </div>
      </div>
    </div>

    <!-- printform / wait_printform — cùng một hộp thoại, chỉ khác nguồn dữ liệu -->
    <VbaPrintForm v-if="previewData" :data="previewData" @close="previewData = null" />

    <div class="vba-statusbar">
      <router-link to="/print-sent-log" class="sent-log-link">📄 SENT LOG — xem đơn đã tích &amp; đã bấm OK →</router-link>
      <span class="vba-zoom">Vừa màn hình: {{ Math.round(scale * 100) }}%</span>
      <span v-if="statusMsg" :class="{ 'is-error': statusIsError }">{{ statusMsg }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import { currentWorkstation } from '../services/workstation';
import { applyVbaRowLock, vbaAgeColor } from '../utils/vbaRowLock';
import VbaPrintForm, { type VbaPrintFormData } from '../components/VbaPrintForm.vue';

const FORM_W = 1416;
const FORM_H = 733;

/* ===== Thu/phóng cả mặt form cho vừa MỘT màn hình ===== */

/** Toạ độ form là pt (đơn vị của MSForms); ở 96dpi 1pt = 4/3 px — cần để tính ô layout theo px. */
const PT = 4 / 3;

const rootRef = ref<HTMLElement | null>(null);
const wrapRef = ref<HTMLElement | null>(null);
const scale = ref(1);
const rootH = ref<number | null>(null);
let ro: ResizeObserver | null = null;

/**
 * Chiều cao màn hình PHẢI đo, không đặt cứng `100vh`: route này nằm trong `AppLayout` nên nội dung
 * đã bị đẩy xuống dưới thanh trên và nằm trong `.content-container` (bản thân nó đã là vùng cuộn,
 * có padding 24px). Đo từ mép trên thật của chính phần tử này thì đúng trong mọi trường hợp — kể cả
 * khi bấm nút toàn màn hình của AppLayout (ẩn sidebar + thanh trên) hay bấm F11.
 */
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
  // Chừa 4px cho viền + đổ bóng của mặt form, không thì mép ngoài bị vùng cuộn cắt mất.
  const w = el.clientWidth - 4;
  const h = el.clientHeight - 4;
  if (w <= 0 || h <= 0) return;
  // Chặn trên 2.0: toạ độ form là số cố định, phóng quá to thì chỉ còn vài ô chiếm cả màn hình.
  scale.value = Math.max(0.3, Math.min(w / (FORM_W * PT), h / (FORM_H * PT), 2));
}

function fitAll() {
  fitRoot();
  // Đo lại khung SAU khi chiều cao mới đã được áp — nếu không, `fitStage` vẫn dùng chiều cao cũ và
  // mặt form bị thu nhỏ hơn mức cần thiết đúng một nhịp.
  nextTick(fitStage);
}

// Lưới TO_SEND: 27 dòng chia 3 khối cột (Left 18 / 492 / 978), mỗi khối 9 dòng cao 24pt từ T=6.
const SEND_SLOTS = 27;
const SEND_ROWS_PER_BLOCK = 9;
const SEND_BLOCK_LEFTS = [18, 492, 978];

const box = (l: number, t: number, w: number, h: number) => ({
  left: l + 'pt', top: t + 'pt', width: w + 'pt', height: h + 'pt',
});

const sendLeft = (i: number) => SEND_BLOCK_LEFTS[Math.floor(i / SEND_ROWS_PER_BLOCK)];
const sendTop = (i: number) => 6 + (i % SEND_ROWS_PER_BLOCK) * 24;

const dispatches = ref<any[]>([]);
const sendTags = ref<(string | null)[]>(Array(SEND_SLOTS).fill(null));
const dispatchById = ref(new Map<string, any>());

const sendSlots = computed(() =>
  sendTags.value.map(id => (id ? dispatchById.value.get(id) ?? null : null))
);

// ReApplyRowColors: ô trống trắng, có dữ liệu mà chưa tick thì ĐỎ, đã tick thì xanh lá nhạt.
const rowBg = (slot: any) => {
  if (!slot) return '#ffffff';
  return slot.scale_checked ? 'rgb(144,238,144)' : 'rgb(255,0,0)';
};

// 18 bảng máy: băng trên VD10..VD18, băng dưới VD09..VD01 (đúng thứ tự nhãn bản gốc).
const PANEL_LEFTS = [12, 168, 324, 480, 636, 792, 948, 1104, 1260];
const BAND_TOP = { labelTop: 288, rowTop: 312, machines: [10, 11, 12, 13, 14, 15, 16, 17, 18] };
const BAND_BOTTOM = { labelTop: 546, rowTop: 570, machines: [9, 8, 7, 6, 5, 4, 3, 2, 1] };
const PANEL_ROWS = 9;

const waitingBatches = ref<any[]>([]);

// Mã máy trong DB là VD001..VD018 (3 chữ số) còn nhãn form là VD01..VD18 — khớp theo SỐ,
// không so chuỗi, nếu không mọi bảng đều rỗng.
const machineNoOf = (code: string | undefined) => {
  if (!code) return null;
  const m = /(\d+)/.exec(code);
  return m ? parseInt(m[1], 10) : null;
};

const panels = computed(() => {
  const out: any[] = [];
  for (const band of [BAND_TOP, BAND_BOTTOM]) {
    band.machines.forEach((no, idx) => {
      const rows = waitingBatches.value
        .filter(b => machineNoOf(b.machine?.code) === no)
        .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
        .slice(0, PANEL_ROWS);
      out.push({
        machineNo: no,
        label: 'VD' + String(no).padStart(2, '0'),
        left: PANEL_LEFTS[idx],
        labelTop: band.labelTop,
        rowTop: band.rowTop,
        rows: Array.from({ length: PANEL_ROWS }, (_, r) => rows[r] ?? null),
      });
    });
  }
  return out;
});

const statusMsg = ref('');
const statusIsError = ref(false);
const say = (m: string, err = false) => { statusMsg.value = m; statusIsError.value = err; };

const fmt = (d: string) => (d ? new Date(d).toLocaleString('vi-VN', { hour12: false }) : '');

// TO_SEND.SavePrintCheck — tick là ghi ngay xuống DB, không đợi nút nào khác.
const onToggleScaleCheck = async (slot: any, checked: boolean) => {
  if (!slot) return;
  try {
    await axios.patch(`/api/machine-dispatches/${slot.id}/scale-checked`, { scale_checked: checked });
    slot.scale_checked = checked;
    say(`Đã ${checked ? 'tick' : 'bỏ tick'} đối chiếu cân cho đơn ${slot.batch?.color || ''}.`);
  } catch (e: any) {
    say(e.response?.data?.message || 'Không cập nhật được cờ đối chiếu cân.', true);
  }
};

// HandleSendPrint / HandlewaitPrint — bản gốc CHỈ mở form xem trước, không in ngay.
// Nút PRINT nằm trong hộp thoại đó mới thực sự in.
const previewData = ref<VbaPrintFormData | null>(null);

const onSendPrint = (slot: any) => {
  if (!slot) return;
  previewData.value = {
    color: slot.batch?.color || '',
    code: slot.batch?.product_code || '',
    machine: slot.batch?.machine?.code || '',
    tank: slot.batch?.tank?.code || '',
    level: slot.batch?.level_code || '',
    rawQrDye: slot.raw_qr_dye || slot.batch?.raw_qr_dye || '',
    rawQrChem: slot.raw_qr_chemical || slot.batch?.raw_qr_chemical || '',
    batchId: slot.batch?.legacy_batch_id || '',
  };
};

const onWaitPrint = (b: any) => {
  if (!b) return;
  previewData.value = {
    color: b.color || '',
    code: b.product_code || '',
    machine: b.machine?.code || '',
    tank: b.tank?.code || '',
    level: b.level_code || '',
    rawQrDye: b.raw_qr_dye || '',
    rawQrChem: b.raw_qr_chemical || '',
    batchId: b.legacy_batch_id || '',
  };
};

// TO_SEND.ConfirmRow — bản gốc chép dòng sang tbl_sentlog (TIME3=Now) rồi xóa khỏi
// tbl_tosend, giải phóng ô lưới. Web tương đương: POST /confirm đặt queue_state=CONFIRMED,
// nên dòng rơi khỏi danh sách hàng chờ và row lock tự trả ô về trống.
//
// printed_via_browser=true vì ở màn hình này tem đã được in từ hộp thoại xem trước trước
// đó (đúng thứ tự thao tác của bản gốc: print -> PRINT -> OK). Cờ này đánh dấu print job
// PRINTED ngay thay vì đẩy xuống hàng chờ Local Agent — không thì Agent sẽ in TSPL lần
// nữa, trùng với bản vừa in qua trình duyệt.
const confirmingId = ref<string | null>(null);

const onConfirm = async (slot: any) => {
  if (!slot) return;
  confirmingId.value = slot.id;
  try {
    await axios.post(`/api/machine-dispatches/${slot.id}/confirm`, {
      idempotency_key: `printorder_${slot.id}_${Date.now()}`,
      workstation_id: currentWorkstation.value?.code || undefined,
      printed_via_browser: true,
    });
    say(`Đã xác nhận đơn ${slot.batch?.color || ''} - ${slot.batch?.product_code || ''}. Xem ở màn hình SENT LOG.`);
    fetchDispatches();
  } catch (e: any) {
    say(e.response?.data?.message || 'Không xác nhận được đơn.', true);
  } finally {
    confirmingId.value = null;
  }
};

const fetchDispatches = async () => {
  try {
    const res = await axios.get('/api/machine-dispatches');
    // VBA đọc "ORDER BY id" (cũ nhất trước), API trả created_at DESC -> đảo lại.
    const rows = [...res.data].reverse();
    dispatches.value = rows;
    const { tags, byId } = applyVbaRowLock(sendTags.value, rows, SEND_SLOTS);
    sendTags.value = tags;
    dispatchById.value = byId;
  } catch (e) {
    console.error('Error fetching dispatches:', e);
  }
};

// Mod_load_input.LoadAllVD_Input — đơn CHƯA duyệt (tbl_input_all), nhóm theo máy, cũ nhất trước.
const fetchWaiting = async () => {
  try {
    const res = await axios.get('/api/production-batches', { params: { status: 'NEW', per_page: 100 } });
    waitingBatches.value = res.data.data;
  } catch (e) {
    console.error('Error fetching waiting batches:', e);
  }
};

const refreshAll = () => { fetchDispatches(); fetchWaiting(); };

let pollInterval: any = null;

onMounted(() => {
  refreshAll();
  // Mod_FE_REFRESH: bản gốc tự làm mới mỗi 15s.
  echo.channel('production-batches').listen('.updated', refreshAll);
  pollInterval = setInterval(refreshAll, 15000);

  fitAll();
  // Đo lại một nhịp nữa sau khi trình duyệt vẽ xong: lúc onMounted chạy, AppLayout có thể chưa dựng
  // xong thanh trên nên `getBoundingClientRect().top` còn là số tạm.
  requestAnimationFrame(fitAll);
  if (typeof ResizeObserver !== 'undefined') {
    ro = new ResizeObserver(fitAll);
    // Nút toàn màn hình của AppLayout chỉ ẩn sidebar/topbar bằng CSS, KHÔNG bắn `resize` — phải
    // quan sát khung chứa mới bắt được nhịp đổi kích thước đó. Quan sát khung NGOÀI chứ không phải
    // `wrapRef`: `fitAll` đặt lại chiều cao cho chính vùng bên trong nên quan sát vùng đó sẽ tự
    // kích hoạt lại chính nó thành vòng lặp.
    const outer = rootRef.value?.parentElement;
    if (outer) ro.observe(outer);
  }
  window.addEventListener('resize', fitAll);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  echo.leaveChannel('production-batches');
  window.removeEventListener('resize', fitAll);
  ro?.disconnect();
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

/* Vùng chứa mặt form: chiếm hết chỗ còn lại sau dải trạng thái. Vẫn để `auto` chứ không `hidden` —
   khi cửa sổ bé hơn cả mức thu nhỏ tối thiểu (30%) thì còn cuộn được thay vì mất nội dung. */
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

.vba-form {
  position: relative;
  transform-origin: top left;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 0 0 0 1px #404040;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

.vba-form > * {
  position: absolute;
  box-sizing: border-box;
  font: inherit;
  margin: 0;
}

.vba-text {
  background-color: #ffffff;
  color: #000000;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 0 2px;
  outline: none;
}

.vba-cell {
  cursor: default;
  overflow: hidden;
}

.vba-btn {
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  cursor: pointer;
  padding: 0;
  line-height: 1;
  white-space: nowrap;
  overflow: hidden;
}

.vba-btn:active:not(:disabled) {
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 1px 0 0 1px;
}

.vba-btn:disabled {
  color: #808080;
  text-shadow: 1px 1px 0 #ffffff;
  cursor: not-allowed;
}

.vba-btn-tiny {
  font-size: 7pt;
}

.vba-check {
  accent-color: #000080;
  cursor: pointer;
}

.vba-check:disabled {
  cursor: not-allowed;
}

.vba-label {
  background-color: transparent;
  display: flex;
  align-items: center;
}

.vba-panel-label {
  font-weight: bold;
}

.vba-statusbar {
  flex: none;
  margin-top: 8px;
  padding: 4px 8px;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

.vba-statusbar {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.vba-zoom {
  color: #404040;
  white-space: nowrap;
}

.vba-statusbar .is-error {
  color: #a00000;
  font-weight: bold;
}

.sent-log-link {
  color: #000080;
  font-weight: bold;
  text-decoration: none;
  white-space: nowrap;
}

.sent-log-link:hover { text-decoration: underline; }
</style>
