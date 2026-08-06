<template>
  <!-- Bản dựng lại UserForm `scaleform` của workbook "QR PRINTER-send to access- NEW 9ROWS BIG
       QR.xlsm" (BẢN Ở GỐC REPO — không phải bản trong Danny/, bản đó cũ hơn: thiếu nút CLEAR
       WEIGHT và btnSend chưa gọi btnClear_Click).

       Toạ độ đọc thẳng từ designer (`VBComponent.Designer.Controls`: Left/Top/Width/Height tính
       bằng point), nên mọi kích thước dưới đây dùng đơn vị `pt` và khớp 1:1 với form VBA thật:
       588.75pt × 549pt (ClientWidth 11775 / ClientHeight 10980 twips).

       Bảng màu là HẰNG SỐ MÀU HỆ THỐNG Windows mà bản gốc tham chiếu (xem khối :root ở style),
       cố ý KHÔNG dùng design token dark/light của app.

       Cách thu/phóng và bộ class `.vba-*` dùng chung cách làm với /production-batches/grid. -->
  <component :is="pageWrapper">
  <div class="vba-scroll" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <div class="vba-stage-wrap" ref="wrapRef">
      <!-- Lớp đệm mang ĐÚNG kích thước SAU khi thu/phóng: transform: scale() không đổi ô layout
           của phần tử, thiếu lớp này thì lúc phóng > 1 mặt form tràn ra ngoài vùng cuộn. -->
      <div class="vba-fit"
           :style="{ width: Math.round(FORM_W * PT * scale) + 'px', height: Math.round(FORM_H * PT * scale) + 'px' }">
        <div class="vba-form"
             :style="{ width: FORM_W + 'pt', height: FORM_H + 'pt', transform: `scale(${scale})` }">

          <!-- ============ Dải header (T = 0..114pt) ============ -->
          <!-- txt_COLOR: BackColor = COLOR_INFOBACKGROUND. KHÔNG dùng v-model — máy quét bắn cả
               trăm ký tự trong vài chục mili giây, v-model làm Vue re-render sau mỗi ký tự và
               trình duyệt RỚT ký tự giữa chừng (đã xác minh ở /production-batches). Đọc .value
               một lần khi Enter/rời ô, đúng thời điểm `txt_color_AfterUpdate` chạy. -->
          <input
            ref="colorRef"
            class="vba-text vba-scan vba-f162"
            :style="box(6, 6, 96, 25.2)"
            :readonly="headerLocked"
            title="txt_COLOR — quét tem vào đây rồi Enter"
            @keyup.enter="onColorCommit"
            @change="onColorCommit"
          />
          <input v-model="header.machine" class="vba-text vba-f12" :style="box(108, 6, 48, 25.2)" :readonly="headerLocked" title="txt_MACHINE" />
          <button class="vba-btn vba-f12 c-scrollbar" :style="box(162, 6, 54, 24)" @click="machinePickerOpen = true">machine</button>
          <button class="vba-btn vba-f36 c-scrollbar" :style="box(222, 6, 132, 60)" @click="handleClear">CLEAR</button>
          <!-- Caption gốc là "SEND " (có dấu cách cuối) — giữ nguyên, chỉ ảnh hưởng canh chữ. -->
          <button class="vba-btn vba-f36 c-highlight" :style="box(360, 6, 156, 60)" :disabled="sending" @click="handleSend">
            {{ sending ? '...' : 'SEND ' }}
          </button>
          <button class="vba-btn vba-f12 c-scrollbar" :style="box(522, 6, 60, 30)" @click="handleClose">CLOSE</button>

          <input v-model="header.code" class="vba-text vba-f162" :style="box(6, 42, 96, 25.8)" :readonly="headerLocked" title="txt_CODE" />
          <input v-model="header.tank" class="vba-text vba-f12" :style="box(108, 42, 48, 25.2)" title="txt_tank" />
          <button class="vba-btn vba-f12 c-scrollbar" :style="box(162, 42, 54, 24)" @click="tankPickerOpen = true">tank</button>
          <button class="vba-btn vba-f12 c-scrollbar" :style="box(522, 36, 60, 30)" :disabled="printing" @click="handlePrint">print</button>

          <input v-model="header.lv" class="vba-text vba-f162" :style="box(6, 72, 48, 25.2)" :readonly="headerLocked" title="txt_LV" />
          <button class="vba-btn vba-f12 c-scrollbar" :style="box(522, 66, 60, 30)" @click="checkFormOpen = true">check</button>

          <!-- btn_clearWeight — chỉ có ở bản gốc repo: xoá 9 ô WEIGHT rồi focus ô WEIGHT dòng 1. -->
          <button class="vba-btn vba-f12 c-activecaption" :style="box(246, 90, 96, 24)" @click="handleClearWeight">CLEAR WEIGHT</button>

          <!-- ============ Tiêu đề 2 bảng (T = 102pt) ============ -->
          <label class="vba-label" :style="box(12, 102, 30, 12)">RACK</label>
          <label class="vba-label" :style="box(48, 102, 48, 12)">DYE CODE</label>
          <label class="vba-label" :style="box(210, 102, 36, 12)">WEIGHT</label>
          <label class="vba-label" :style="box(366, 102, 30, 12)">RACK</label>
          <label class="vba-label" :style="box(402, 102, 48, 12)">chem CODE</label>
          <label class="vba-label" :style="box(504, 102, 36, 12)">WEIGHT</label>

          <!-- Nhãn số thứ tự dòng (Label1..6, Label15..17): nền COLOR_HIGHLIGHT nên là ô vuông
               xanh nhỏ bên trái mỗi dòng, KHÁC 6 nhãn tiêu đề ở trên (nền = màu mặt form). -->
          <label v-for="(top, i) in ROW_NO_TOP" :key="'no-' + i" class="vba-rowno" :style="box(6, top, 6, 12)">
            {{ i + 1 }}
          </label>

          <!-- Vạch ngăn 2 bảng: bản gốc là TextBox28 (ô rỗng rộng 6pt, nền COLOR_HIGHLIGHT). -->
          <div class="vba-text vba-divider" :style="box(348, 114, 6, 433.5)"></div>

          <!-- ============ 9 dòng THUỐC NHUỘM + 9 dòng HÓA CHẤT ============ -->
          <template v-for="(row, i) in dye" :key="'dye-' + i">
            <input
              v-model="row.rack"
              :ref="el => { if (i === 8) rack9Ref = el as HTMLInputElement | null }"
              class="vba-text vba-rack"
              :style="box(12, DYE_RACK_TOP[i], 30, 44.4)"
            />
            <input
              v-model="row.code"
              class="vba-text vba-big"
              :style="{ ...box(48, DYE_CODE_TOP[i], 156, 44.4), backgroundColor: row.fill }"
              @change="fillDyeRack(i)"
            />
            <input
              v-model="row.weight"
              :ref="el => { if (i === 0) weight1Ref = el as HTMLInputElement | null }"
              class="vba-text vba-big"
              :style="{ ...box(210, DYE_WEIGHT_TOP[i], 132, 44.4), backgroundColor: row.fill }"
            />
          </template>

          <template v-for="(row, i) in chem" :key="'chem-' + i">
            <input v-model="row.rack" class="vba-text vba-rack" :style="box(366, CHEM_RACK_TOP[i], 30, 44.4)" />
            <input
              v-model="row.code"
              class="vba-text vba-big"
              :style="{ ...box(402, CHEM_CODE_TOP[i], 96, 44.4), backgroundColor: row.fill }"
              @change="fillChemRack(i)"
            />
            <input
              v-model="row.weight"
              class="vba-text vba-big"
              :style="{ ...box(504, CHEM_WEIGHT_TOP[i], 78, 44.4), backgroundColor: row.fill }"
            />
          </template>
        </div>
      </div>
    </div>

    <!-- ============ formselect1 — chọn MÁY (VD01..VD18) ============ -->
    <div v-if="machinePickerOpen" class="vba-modal-backdrop" @click.self="machinePickerOpen = false">
      <div class="vba-modal-fit" :style="machineFit.fit" @click.self="machinePickerOpen = false">
        <div class="vba-form vba-modal c-btnface" :style="machineFit.form">
          <div class="vba-listbox" :style="box(18, 12, 84.75, 504.8)">
            <div
              v-for="m in MACHINE_LIST"
              :key="m"
              class="vba-listitem li-22"
              :class="{ 'is-selected': machinePick === m }"
              @click="machinePick = m"
              @dblclick="machinePick = m; confirmMachine()"
            >{{ m }}</div>
          </div>
          <button class="vba-btn vba-f2625 c-btnface" :style="box(18, 522, 84, 48)" @click="confirmMachine">OK</button>
        </div>
      </div>
    </div>

    <!-- ============ Formselect2 — chọn THÙNG (1A/2B/3C/4D/FB) ============ -->
    <div v-if="tankPickerOpen" class="vba-modal-backdrop" @click.self="tankPickerOpen = false">
      <div class="vba-modal-fit" :style="tankFit.fit" @click.self="tankPickerOpen = false">
        <div class="vba-form vba-modal c-btnface" :style="tankFit.form">
          <div class="vba-listbox" :style="box(18, 6, 126, 174)">
            <div
              v-for="t in TANK_LIST"
              :key="t"
              class="vba-listitem li-25"
              :class="{ 'is-selected': tankPick === t }"
              @click="tankPick = t"
              @dblclick="tankPick = t; confirmTank()"
            >{{ t }}</div>
          </div>
          <button class="vba-btn vba-f2625 c-btnface" :style="box(18, 186, 126, 54)" @click="confirmTank">OK</button>
        </div>
      </div>
    </div>

    <!-- ============ checkform — DATABASE CHECKER ============ -->
    <div v-if="checkFormOpen" class="vba-modal-backdrop" @click.self="checkFormOpen = false">
      <div class="vba-modal-fit" :style="checkFit.fit" @click.self="checkFormOpen = false">
        <div class="vba-form vba-modal c-btnface" :style="checkFit.form">
          <label class="vba-label c-btnface vba-f8" :style="box(6, 6, 30, 12)">COLOR</label>
          <input v-model="check.color" class="vba-text vba-f8" :style="box(36, 6, 72, 25)" @change="splitCheckScan" />
          <label class="vba-label c-btnface vba-f8" :style="box(12, 36, 24, 12)">CODE</label>
          <input v-model="check.code" class="vba-text vba-f8" :style="box(36, 36, 72, 25)" />
          <label class="vba-label c-btnface vba-f8" :style="box(120, 6, 72, 24)">RANGE OF TIME</label>
          <input v-model="check.days" class="vba-text vba-f8" :style="box(198, 6, 72, 25)" title="txt27 — số ngày trở về trước" />

          <button class="vba-btn vba-f1425 c-btnface" :style="box(120, 36, 72, 24)" @click="checkFormOpen = false">CLOSE</button>
          <button class="vba-btn vba-f1425 c-btnface" :style="box(198, 36, 72, 24)" @click="clearCheckForm">CLEAR</button>
          <button class="vba-btn vba-f1575 c-btnface" :style="box(276, 6, 150, 54)" :disabled="checking" @click="runCheck">CHECK</button>
          <button class="vba-btn vba-f1425 c-btnface" :style="box(276, 66, 150, 24)" :disabled="checking" @click="runCheckTimeSent">check time sent</button>

          <textarea v-model="check.result" readonly class="vba-text vba-f8 vba-result" :style="box(12, 93.7, 414, 552)"></textarea>
        </div>
      </div>
    </div>

    <!-- Dải trạng thái nằm NGOÀI mặt form — bản VBA không có ô chọn khổ giấy vì nó in ra sheet
         tạm tạo bằng `Sheets.Add`, nhận khổ mặc định của driver máy in lúc chạy nên không lưu
         ở đâu trong .xlsm. 63.5 × 38.5mm là giá trị duy nhất ghi trong workbook (DEVMODE của
         sheet RECORD, máy in "TSC TE200 on vn-ld047"). -->
    <div class="vba-statusbar">
      <span class="vba-zoom">Vừa màn hình: {{ Math.round(scale * 100) }}%</span>
      <label class="vba-paper">
        Khổ giấy (mm):
        <input v-model.number="paperW" type="number" min="10" max="300" step="0.5" />
        ×
        <input v-model.number="paperH" type="number" min="10" max="300" step="0.5" />
      </label>
      <span v-if="statusMsg" :class="{ 'is-error': statusIsError }">{{ statusMsg }}</span>
    </div>

    <FullscreenButton variant="vba" @change="refit" />
    <NavToggleButton variant="vba" />
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import AppLayout from '../components/AppLayout.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import NavToggleButton from '../components/NavToggleButton.vue';
import { isFullscreen } from '../services/layout';
import { useAuthStore } from '../stores/auth';
import { lookupChemRack, lookupDyeRack } from '../utils/qrPrinterRackMap';
import {
  writeSlipToWindow,
  buildRawQr,
  DEFAULT_PAPER_W_MM,
  DEFAULT_PAPER_H_MM,
  type QrPrinterSlipData,
} from '../utils/qrPrinterSlip';

// Trang công khai (requiresAuth:false) — App.vue không bọc AppLayout, trang tự bọc lấy khi người
// xem đã đăng nhập để vẫn có menu điều hướng (mở bằng nút 3 gạch, xem NavToggleButton).
const isLoggedIn = useAuthStore().isAuthenticated;
const pageWrapper = isLoggedIn ? AppLayout : 'div';
const previousIsFullscreen = isFullscreen.value;

// Endpoint /api/public/... — trang không đăng nhập không gọi được nhóm /api/ thường.
const API = '/api/public';

/* ===== Kích thước mặt form (pt) = ClientWidth/ClientHeight chia 20 twips/pt ===== */
const FORM_W = 588.75;
const FORM_H = 549;

/** Toạ độ form là pt (đơn vị MSForms); ở 96dpi 1pt = 4/3 px — cần để tính ô layout theo px. */
const PT = 4 / 3;

const box = (l: number, t: number, w: number, h: number) => ({
  left: l + 'pt',
  top: t + 'pt',
  width: w + 'pt',
  height: h + 'pt',
});

/* ===== Toạ độ Top của từng dòng =====
   Chép NGUYÊN VĂN từ designer, KHÔNG tính bằng công thức đều nhau: người dựng form kéo tay nên
   3 cột của cùng một dòng lệch nhau vài phần trăm point (dòng 3 khối hoá chất: 210 / 210 /
   209.95). Thay bằng "114 + i*48" sẽ khớp dòng 1 rồi trôi dần xuống dưới. */
const ROW_NO_TOP = [126, 180, 228, 270, 324, 372, 420, 468, 516];

const DYE_RACK_TOP = [114, 161.95, 210, 258.1, 306.15, 354.2, 402.25, 450.35, 498.35];
const DYE_CODE_TOP = [114, 161.95, 210, 258.05, 306.05, 354.1, 402.2, 450.25, 498.25];
const DYE_WEIGHT_TOP = [114, 162, 210, 258.05, 306.05, 354.1, 402.1, 450.15, 498.1];

const CHEM_RACK_TOP = [114, 162, 210, 258.1, 306.15, 354.2, 402.25, 450.35, 498.35];
const CHEM_CODE_TOP = [114, 161.95, 210, 258.05, 306.05, 354, 402.2, 450.25, 498.25];
const CHEM_WEIGHT_TOP = [114, 161.95, 209.95, 258, 306.05, 354.05, 402.05, 450.1, 498.1];

/* ===== Danh sách 2 hộp chọn — MẢNG CỐ ĐỊNH trong VBA (btnMachine_Click / btnTank_Click),
   không đọc từ danh mục nào. Giữ nguyên để hộp chọn giống hệt bản gốc; việc đối chiếu với
   danh mục máy/thùng thật chỉ xảy ra lúc bấm SEND. ===== */
const MACHINE_LIST = Array.from({ length: 18 }, (_, i) => 'VD' + String(i + 1).padStart(2, '0'));
const TANK_LIST = ['1A', '2B', '3C', '4D', 'FB'];

/* ===== Màu nền FillBlock tô cho ô mã + ô khối lượng ===== */
const FILL_WHITE = '';        // vbWhite — để rỗng cho ô dùng nền mặc định của .vba-text
const FILL_GREEN = '#c8ffc8'; // RGB(200, 255, 200) — rack khác 0
const FILL_YELLOW = '#ffffb4'; // RGB(255, 255, 180) — rack bằng 0

interface Row { rack: string; code: string; weight: string; fill: string }
const blankRow = (): Row => ({ rack: '', code: '', weight: '', fill: FILL_WHITE });

const header = reactive({ code: '', machine: '', tank: '', lv: '' });
const headerLocked = ref(false);
const dye = reactive<Row[]>(Array.from({ length: 9 }, blankRow));
const chem = reactive<Row[]>(Array.from({ length: 9 }, blankRow));

const colorRef = ref<HTMLInputElement | null>(null);
/** `txt_RACK9` — bản gốc nhảy focus xuống ô RACK DÒNG 9 sau khi tách mã. */
const rack9Ref = ref<HTMLInputElement | null>(null);
/** `txt_WEIGHT1` — đích focus của nút CLEAR WEIGHT. */
const weight1Ref = ref<HTMLInputElement | null>(null);

const paperW = ref(DEFAULT_PAPER_W_MM);
const paperH = ref(DEFAULT_PAPER_H_MM);

const sending = ref(false);
const statusMsg = ref('');
const statusIsError = ref(false);
const say = (msg: string, isError = false) => {
  statusMsg.value = msg;
  statusIsError.value = isError;
};

/* ===================================================================================
 * Tách chuỗi quét — `txt_color_AfterUpdate` + `FillBlock`
 * =================================================================================== */

/** `Mod_put_rawtoform.CleanLeadingGarbage` — bỏ rác đầu chuỗi tới CHỮ CÁI đầu tiên (chỉ A-Z/a-z). */
function cleanLeadingGarbage(s: string): string {
  for (let i = 0; i < s.length; i++) {
    if (/[A-Za-z]/.test(s[i])) return s.slice(i);
  }
  return s;
}

/** Port `Val()` của VBA: đọc số ở đầu chuỗi, bỏ qua phần đuôi không phải số. */
function vbaVal(s: string): number {
  const m = /^\s*[+-]?(\d+\.?\d*|\.\d+)/.exec(String(s ?? ''));
  return m ? parseFloat(m[0]) : 0;
}

/**
 * `FillBlock parts, startIdx, endIdx, rackPrefix, codePrefix, weightPrefix`.
 *
 * Đọc lần lượt từng bộ ba rack / mã / khối lượng, tối đa 9 dòng, và DỪNG NGAY khi hết vùng —
 * kể cả đang dở một bộ ba (đúng `Exit For` giữa chừng của VBA, nên dòng dở KHÔNG được tô màu).
 * Không xoá dữ liệu cũ trước khi điền: quét lần 2 chỉ ghi đè đúng số dòng mới có, giống bản gốc.
 */
function fillBlock(parts: string[], startIdx: number, endIdx: number, target: Row[]): void {
  let idx = startIdx;

  for (let i = 0; i < 9; i++) {
    if (idx > endIdx) return;
    const rackVal = parts[idx];
    target[i].rack = rackVal;
    idx++;

    if (idx > endIdx) return;
    target[i].code = parts[idx];
    idx++;

    if (idx > endIdx) return;
    target[i].weight = parts[idx];
    idx++;

    if (rackVal !== '') {
      target[i].fill = vbaVal(rackVal) !== 0 ? FILL_GREEN : FILL_YELLOW;
    }
  }
}

function onColorCommit(): void {
  // Ô đã khoá sau lần tách đầu tiên thì AfterUpdate của VBA không thể chạy nữa (Locked textbox
  // không sửa được) — chặn ở đây cho khớp, đồng thời tránh @keyup.enter và @change cùng bắn.
  if (headerLocked.value) return;

  const el = colorRef.value;
  const s = cleanLeadingGarbage((el?.value ?? '').trim());
  if (s === '') return;

  const parts = s.split('-');

  // ===== HEADER =====
  if (el) el.value = parts[0];
  if (parts.length >= 2) header.code = parts[1];
  if (parts.length >= 3) header.machine = parts[2];
  if (parts.length >= 4) header.lv = parts[3];

  // ===== TÌM DYE / CHEM =====
  let dyePos = -1;
  let chemPos = -1;
  for (let i = 0; i < parts.length; i++) {
    if (parts[i].toUpperCase() === 'DYE') dyePos = i + 1;
    if (parts[i].toUpperCase() === 'CHEM') chemPos = i + 1;
  }

  const last = parts.length - 1;

  // Workbook này BẮT BUỘC có từ khoá "DYE" mới đổ được khối thuốc nhuộm — KHÔNG có nhánh dự
  // phòng "lấy từ vị trí thứ 5" như workbook "in tem Copower.xlsm". Đúng vậy vì tem do chính
  // nó in ra luôn có "-DYE-...-CHEM-".
  if (dyePos !== -1) {
    fillBlock(parts, dyePos, chemPos !== -1 ? chemPos - 2 : last, dye);
  }
  if (chemPos !== -1) {
    fillBlock(parts, chemPos, last, chem);
  }

  headerLocked.value = true;
  say(`Đã tách tem: ${parts[0]} — ${header.code || '(không có mã hàng)'}.`);

  nextTick(() => rack9Ref.value?.focus());
}

/** `txt_dye{i}_AfterUpdate` → `FillDyeRack i` (cột E → cột D của sheet `semi`; không thấy → "0"). */
function fillDyeRack(i: number): void {
  dye[i].rack = lookupDyeRack(dye[i].code);
}

/** `txt_chem_code{i}_AfterUpdate` → `FillRack i` (cột B → cột A; không thấy → "0"). */
function fillChemRack(i: number): void {
  chem[i].rack = lookupChemRack(chem[i].code);
}

/* ===================================================================================
 * CLEAR / CLEAR WEIGHT / CLOSE
 * =================================================================================== */

/** `btnClear_Click` — xoá trắng MỌI TextBox, trả nền về trắng, mở khoá 4 ô header, focus COLOR. */
function handleClear(): void {
  if (colorRef.value) colorRef.value.value = '';
  header.code = '';
  header.machine = '';
  header.tank = '';
  header.lv = '';
  for (const list of [dye, chem]) {
    for (const row of list) {
      row.rack = '';
      row.code = '';
      row.weight = '';
      row.fill = FILL_WHITE;
    }
  }
  headerLocked.value = false;
  nextTick(() => colorRef.value?.focus());
}

/** `btn_clearWeight_Click` — chỉ xoá 9 ô WEIGHT của khối thuốc nhuộm rồi focus ô dòng 1. */
function handleClearWeight(): void {
  for (const row of dye) row.weight = '';
  nextTick(() => weight1Ref.value?.focus());
}

/** `btnClose_Click` — bản gốc Unload form và hiện lại Excel; ở web tương đương xoá trắng phiên. */
function handleClose(): void {
  handleClear();
  say('Đã đóng phiên nhập (CLOSE) — form đã được xóa trắng.');
}

/* ===================================================================================
 * SEND — `btnSend_Click`
 * =================================================================================== */

/**
 * Bản gốc `INSERT INTO tbl_tosend (...)` thẳng vào Access. Trong hệ web, `tbl_tosend` = hàng chờ
 * ĐÃ DUYỆT (`machine_dispatches`), nên tương đương là TẠO đơn rồi DUYỆT ngay: đúng 2 bước mà
 * /production-batches/grid làm khi ô confirm2 = "OK". Đơn sẽ hiện ở lưới TO_SEND của
 * /print-order-entry, KHÔNG nằm trong 81 ô "chờ duyệt" của /production-batches/grid.
 *
 * Bản gốc ghi thẳng nên không cần khoá ngoại; ở đây phải đối chiếu mã máy/thùng với danh mục
 * thật để lấy id — mã hợp lệ theo VBA (VD01..VD18) mà chưa có trong danh mục thì báo lỗi rõ
 * thay vì tạo đơn mồ côi.
 */
async function handleSend(): Promise<void> {
  say('');

  // Kiểm tra y hệt bản gốc, kể cả nội dung thông báo.
  const machineCode = (header.machine || '').trim().toUpperCase();
  if (!MACHINE_LIST.includes(machineCode)) {
    say('Machine khong hop le. Chi cho phep: VD01 ~ VD18', true);
    return;
  }
  const tankCode = (header.tank || '').trim().toUpperCase();
  if (!TANK_LIST.includes(tankCode)) {
    say('Tank khong hop le. Chi cho phep: 1A, 2B, 3C, 4D, FB', true);
    return;
  }

  const color = (colorRef.value?.value ?? '').trim();
  const rawqrdye = buildRawQr(dye);
  const rawqrchem = buildRawQr(chem);

  sending.value = true;
  try {
    const [machinesRes, tanksRes] = await Promise.all([
      axios.get(`${API}/machines`),
      axios.get(`${API}/tanks`),
    ]);
    const machine = (machinesRes.data.data || []).find(
      (m: any) => String(m.code).toUpperCase() === machineCode
    );
    if (!machine) {
      say(`Máy ${machineCode} chưa có trong danh mục máy của hệ thống web — không gửi được.`, true);
      return;
    }
    const tank = (tanksRes.data.data || []).find(
      (t: any) => t.machine_id === machine.id && String(t.code).toUpperCase() === tankCode
    );
    if (!tank) {
      say(`Máy ${machineCode} chưa có thùng ${tankCode} trong danh mục — không gửi được.`, true);
      return;
    }

    const created = await axios.post(`${API}/production-batches`, {
      legacy_batch_id: `${color}-${header.code}-${Date.now()}`,
      color,
      product_code: header.code,
      machine_id: machine.id,
      tank_id: tank.id,
      level_code: header.lv || null,
      raw_qr_dye: rawqrdye || null,
      raw_qr_chemical: rawqrchem || null,
      // Bản gốc chỉ chặn trùng ở tbl_input_all (Exists_ColorCode), còn đường btnSend ghi thẳng
      // tbl_tosend KHÔNG kiểm tra trùng — bỏ qua cảnh báo trùng để giữ đúng hành vi đó.
      confirm_duplicate: true,
    });

    await axios.post(`${API}/production-batches/${created.data.data.id}/approve`, {});

    // Bản gốc repo GỌI btnClear_Click trước khi báo "Send OK" (bản cũ trong Danny/ comment dòng
    // này lại) — form được xoá trắng sau khi gửi.
    handleClear();
    say('Send OK');
  } catch (error: any) {
    say(error.response?.data?.message || 'Không gửi được đơn.', true);
  } finally {
    sending.value = false;
  }
}

/* ===================================================================================
 * print — `btnPrint_Click`
 * =================================================================================== */

function slipData(): QrPrinterSlipData {
  return {
    color: colorRef.value?.value ?? '',
    code: header.code,
    machine: header.machine,
    level: header.lv,
    dye: dye.map(r => ({ rack: r.rack, code: r.code, weight: r.weight })),
    chem: chem.map(r => ({ rack: r.rack, code: r.code, weight: r.weight })),
    paperWmm: paperW.value,
    paperHmm: paperH.value,
  };
}

/**
 * Bấm `print` mở phiếu ra một CỬA SỔ RIÊNG nổi bên ngoài (yêu cầu 06/08) — người vận hành nhìn
 * thấy phiếu rồi tự bấm in, thay vì hộp thoại in bung ngay trên trang.
 *
 * `window.open` phải chạy ĐỒNG BỘ ngay trong trình xử lý click, TRƯỚC mọi `await` dựng ảnh QR:
 * gọi sau tác vụ bất đồng bộ là mất "user gesture" và Chrome/Edge chặn pop-up, khi đó bấm nút in
 * không hiện gì cả.
 */
const printing = ref(false);

async function handlePrint(): Promise<void> {
  if (printing.value) return;
  const win = window.open('', '_blank', 'width=900,height=700');
  if (!win) {
    say('Trình duyệt đã chặn cửa sổ in — cho phép pop-up cho trang này rồi bấm lại.', true);
    return;
  }
  printing.value = true;
  try {
    await writeSlipToWindow(win, slipData());
    say('Đã mở phiếu để in.');
  } catch (err) {
    win.close();
    say('Không dựng được phiếu in: ' + (err instanceof Error ? err.message : String(err)), true);
  } finally {
    printing.value = false;
  }
}

/* ===================================================================================
 * 2 hộp chọn — formselect1 / Formselect2
 * =================================================================================== */

const machinePickerOpen = ref(false);
const machinePick = ref('');
const tankPickerOpen = ref(false);
const tankPick = ref('');

/** `btnOK_Click`: chỉ gán khi có dòng đang chọn (`ListIndex >= 0`), rồi đóng form. */
function confirmMachine(): void {
  if (machinePick.value) header.machine = machinePick.value;
  machinePickerOpen.value = false;
}
function confirmTank(): void {
  if (tankPick.value) header.tank = tankPick.value;
  tankPickerOpen.value = false;
}

/* ===================================================================================
 * checkform — DATABASE CHECKER
 * =================================================================================== */

const checkFormOpen = ref(false);
const checking = ref(false);
const check = reactive({ color: '', code: '', days: '', result: '' });

/** `checkform.CleanString` — giữ A-Z/a-z/0-9 và `+ - . ,` (KHÁC hàm cùng tên ở Mod_put_rawtoform). */
function checkCleanString(s: string): string {
  let out = '';
  for (const c of String(s ?? '')) {
    const code = c.charCodeAt(0);
    if ((code >= 48 && code <= 57) || (code >= 65 && code <= 90) || (code >= 97 && code <= 122)) out += c;
    else if (c === '+' || c === '-' || c === '.' || c === ',') out += c;
  }
  return out;
}

/** `txtCOLOR_AfterUpdate` — quét thẳng vào ô COLOR rồi tách phần tử 0/1 ra COLOR/CODE. */
function splitCheckScan(): void {
  const s = checkCleanString(check.color.trim());
  if (s === '') return;
  const p = s.split('-');
  check.color = p[0];
  check.code = p.length >= 2 ? p[1] : '';
}

function clearCheckForm(): void {
  check.color = '';
  check.code = '';
  check.days = '';
  check.result = '';
}

/**
 * `btnCheck_Click` — bản gốc `SELECT DISTINCT batchID FROM tblRECORD WHERE COLOR=? AND CODE=?
 * [AND TIME >= today-N]` rồi loop từng lô lấy chi tiết RACK/DYECODE/WEIGHT/PROCESS. Web dùng
 * endpoint đã port sẵn (`ScaleMeasurementController::checker`); ở đây chỉ dựng lại đúng khối
 * văn bản phân cách bằng Tab mà txtRESULT hiển thị.
 */
async function runCheck(): Promise<void> {
  check.result = '';
  const c = check.color.trim();
  const d = check.code.trim();
  if (c === '' || d === '') {
    check.result = 'VUI LONG NHAP COLOR VA CODE';
    return;
  }
  if (check.days.trim() !== '') {
    if (!/^-?\d+$/.test(check.days.trim())) {
      check.result = 'SO NGAY PHAI LA SO NGUYEN';
      return;
    }
    if (parseInt(check.days.trim(), 10) < 0) {
      check.result = 'SO NGAY KHONG HOP LE';
      return;
    }
  }

  checking.value = true;
  try {
    const params: Record<string, string> = { color: c, code: d };
    if (check.days.trim() !== '') params.days_back = String(parseInt(check.days.trim(), 10));
    const res = await axios.get(`${API}/scale-measurements/checker`, { params });
    const batches = res.data.data || [];
    if (!batches.length) {
      check.result = 'KHONG CO DU LIEU';
      return;
    }
    let s = '';
    for (const b of batches) {
      s += [b.batch_id, b.color, b.product_code, b.machine_code, b.level_code,
            fmtDateTime(b.measured_at), ''].join('\t') + '\n';
      for (const it of b.items || []) {
        s += [it.rack_code, it.dye_code, it.weight, it.process_code, '', '', it.process_status].join('\t') + '\n';
      }
      s += '\n';
    }
    check.result = s;
  } catch (error: any) {
    check.result = error.response?.data?.message || 'Loi khi tra cuu.';
  } finally {
    checking.value = false;
  }
}

/**
 * `btnCheck2_Click` — `SELECT TIME1, TIME3 FROM tbl_sentlog WHERE COLOR=? AND CODE=? ORDER BY
 * TIME3`. Web: `tbl_sentlog` ↔ lịch sử gửi máy (`machine-dispatches/history`), TIME1 = lúc tạo,
 * TIME3 = lúc gửi/xác nhận.
 */
async function runCheckTimeSent(): Promise<void> {
  check.result = '';
  const c = check.color.trim();
  const d = check.code.trim();
  if (c === '' || d === '') {
    check.result = 'VUI LONG NHAP COLOR VA CODE';
    return;
  }

  checking.value = true;
  try {
    const res = await axios.get(`${API}/machine-dispatches/history`);
    const all = Array.isArray(res.data) ? res.data : (res.data.data ?? []);
    const rows = all.filter((r: any) => {
      const b = r.production_batch ?? r.batch ?? r;
      return String(b.color ?? '').trim() === c && String(b.product_code ?? '').trim() === d;
    });
    if (!rows.length) {
      check.result = 'KHONG CO DU LIEU';
      return;
    }
    rows.sort((a: any, b: any) => new Date(a.sent_at ?? a.updated_at ?? 0).getTime() - new Date(b.sent_at ?? b.updated_at ?? 0).getTime());
    check.result = rows
      .map((r: any) => `${fmtDateTime(r.created_at)}\t${fmtDateTime(r.sent_at ?? r.confirmed_at ?? r.updated_at)}`)
      .join('\n');
  } catch (error: any) {
    check.result = error.response?.data?.message || 'Loi khi tra cuu.';
  } finally {
    checking.value = false;
  }
}

/** `Format$(..., "dd\mm\yyyy HH:nn:ss")` — chính bản gốc dùng "\" chứ không phải "/". */
function fmtDateTime(v: any): string {
  if (!v) return '';
  const dt = new Date(v);
  if (Number.isNaN(dt.getTime())) return String(v);
  const p2 = (n: number) => String(n).padStart(2, '0');
  return `${p2(dt.getDate())}\\${p2(dt.getMonth() + 1)}\\${dt.getFullYear()} ${p2(dt.getHours())}:${p2(dt.getMinutes())}:${p2(dt.getSeconds())}`;
}

/* ===================================================================================
 * Thu/phóng — dùng chung cách làm với ProductionBatchesGrid
 * =================================================================================== */

const rootRef = ref<HTMLElement | null>(null);
const wrapRef = ref<HTMLElement | null>(null);
const scale = ref(1);
const rootH = ref<number | null>(null);
let ro: ResizeObserver | null = null;

/**
 * Chiều cao màn hình PHẢI đo, không đặt cứng `100vh`: route này nằm trong `AppLayout` nên nội
 * dung đã bị đẩy xuống dưới thanh trên và nằm trong `.content-container` (bản thân nó đã là vùng
 * cuộn, có padding 24px). Đo từ mép trên thật của chính phần tử này thì đúng trong mọi trường
 * hợp — kể cả khi bấm nút toàn màn hình của AppLayout hay bấm F11.
 */
function fitRoot(): void {
  const el = rootRef.value;
  if (!el) return;
  const top = el.getBoundingClientRect().top;
  const parent = el.parentElement;
  const padBottom = parent ? parseFloat(getComputedStyle(parent).paddingBottom) || 0 : 0;
  rootH.value = Math.max(360, Math.floor(window.innerHeight - top - padBottom));
}

function fitStage(): void {
  const el = wrapRef.value;
  if (!el) return;
  // Chừa 4px cho viền + đổ bóng của mặt form, không thì mép ngoài bị vùng cuộn cắt mất.
  const w = el.clientWidth - 4;
  const h = el.clientHeight - 4;
  if (w <= 0 || h <= 0) return;
  // Chặn trên 2.0: toạ độ form là số cố định, phóng quá to thì chỉ còn vài ô chiếm cả màn hình.
  scale.value = Math.max(0.3, Math.min(w / (FORM_W * PT), h / (FORM_H * PT), 2));
}

/* Hộp thoại cũng dựng bằng toạ độ pt cố định; đo trực tiếp CỬA SỔ chứ không đo phần tử cha vì
   chúng nằm trên nền `position: fixed`. Không có bước này thì hộp chọn MÁY (cao 573.75pt ≈ 765px)
   bị cắt mất nút OK trên màn hình thấp. */
const MODAL_MARGIN = 16;
const viewport = reactive({ w: 0, h: 0 });

function modalFit(wPt: number, hPt: number) {
  const wPx = wPt * PT;
  const hPx = hPt * PT;
  const availW = Math.max(160, (viewport.w || wPx) - MODAL_MARGIN * 2);
  const availH = Math.max(160, (viewport.h || hPx) - MODAL_MARGIN * 2);
  const s = Math.max(0.3, Math.min(availW / wPx, availH / hPx, 2));
  return {
    fit: { width: Math.round(wPx * s) + 'px', height: Math.round(hPx * s) + 'px' },
    form: { width: wPt + 'pt', height: hPt + 'pt', transform: `scale(${s})` },
  };
}

// Kích thước 3 form phụ = ClientWidth/ClientHeight chia 20.
const machineFit = computed(() => modalFit(120.75, 573.75)); // formselect1: 2415 × 11475
const tankFit = computed(() => modalFit(158.25, 246));       // Formselect2: 3165 × 4920
const checkFit = computed(() => modalFit(441, 659.25));      // checkform:   8820 × 13185

function fitAll(): void {
  fitRoot();
  viewport.w = window.innerWidth;
  viewport.h = window.innerHeight;
  // Đo lại khung SAU khi chiều cao mới đã được áp — nếu không, `fitStage` vẫn dùng chiều cao cũ
  // và mặt form bị thu nhỏ hơn mức cần thiết đúng một nhịp.
  nextTick(fitStage);
}

// Bật/tắt Toàn màn hình: đợi trình duyệt vẽ lại xong khung mới rồi mới đo.
const refit = () => requestAnimationFrame(fitAll);

onMounted(() => {
  // Người ĐÃ đăng nhập vào trang là thấy menu ngay — muốn xem rộng thì tự bấm nút 3 gạch.
  if (isLoggedIn) isFullscreen.value = false;
  colorRef.value?.focus();

  fitAll();
  // Đo lại một nhịp nữa sau khi trình duyệt vẽ xong: lúc onMounted chạy, AppLayout có thể chưa
  // dựng xong thanh trên nên `getBoundingClientRect().top` còn là số tạm.
  requestAnimationFrame(fitAll);
  if (typeof ResizeObserver !== 'undefined') {
    ro = new ResizeObserver(fitAll);
    // Nút toàn màn hình của AppLayout chỉ ẩn sidebar/topbar bằng CSS, KHÔNG bắn `resize` — phải
    // quan sát khung chứa mới bắt được nhịp đổi kích thước đó. Quan sát khung NGOÀI chứ không
    // phải `wrapRef`: `fitAll` đặt lại chiều cao cho chính vùng bên trong nên quan sát vùng đó
    // sẽ tự kích hoạt lại chính nó thành vòng lặp.
    const outer = rootRef.value?.parentElement;
    if (outer) ro.observe(outer);
  }
  window.addEventListener('resize', fitAll);
});

onUnmounted(() => {
  window.removeEventListener('resize', fitAll);
  ro?.disconnect();
  isFullscreen.value = previousIsFullscreen;
});
</script>

<style scoped>
/* ===== Màu hệ thống Windows mà bản gốc tham chiếu (BackColor/ForeColor là HẰNG SỐ hệ thống,
   không phải mã màu cứng) — giá trị đọc bằng `GetSysColor` trên máy trạm đang chạy workbook:
     COLOR_SCROLLBAR(0)=#C8C8C8      COLOR_ACTIVECAPTION(2)=#99B4D1  COLOR_MENU(4)=#F0F0F0
     COLOR_WINDOW(5)=#FFFFFF         COLOR_HIGHLIGHT(13)=#0078D7     COLOR_BTNFACE(15)=#F0F0F0
     COLOR_INFOBACKGROUND(24)=#FFFFE1
   Mọi control đều BackStyle = fmBackStyleOpaque nên các màu này ĐỀU hiện ra thật. ===== */
.vba-scroll {
  --sys-scrollbar: #c8c8c8;
  --sys-activecaption: #99b4d1;
  --sys-menu: #f0f0f0;
  --sys-highlight: #0078d7;
  --sys-btnface: #f0f0f0;
  --sys-infobk: #ffffe1;

  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 8px;
  background-color: #808080;
  min-height: 360px;
}

/* Vùng chứa mặt form: chiếm hết chỗ còn lại sau dải trạng thái. Vẫn để `auto` chứ không `hidden`
   — khi cửa sổ bé hơn cả mức thu nhỏ tối thiểu (30%) thì còn cuộn được thay vì mất nội dung. */
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

/* Nền form = COLOR_BTNFACE, viền nổi kiểu cửa sổ MSForms.
   Font mặc định của form gốc: Tahoma 8.25pt ĐẬM (đọc từ `Designer.Font`). */
.vba-form {
  position: relative;
  transform-origin: top left;
  background-color: var(--sys-btnface, #f0f0f0);
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  font: bold 8.25pt Tahoma, 'MS Sans Serif', sans-serif;
  box-shadow: 0 0 0 1px #404040;
  color: #000000;
}

.vba-form > * {
  position: absolute;
  box-sizing: border-box;
  font: inherit;
  margin: 0;
}

/* TextBox: nền trắng, viền chìm (fmSpecialEffectSunken) */
.vba-text {
  background-color: #ffffff;
  color: #000000;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 0 2px;
  outline: none;
}

.vba-text:focus {
  border-color: #316ac5;
}

/* txt_COLOR — BackColor = COLOR_INFOBACKGROUND */
.vba-scan {
  background-color: var(--sys-infobk);
}

/* ===== Cỡ chữ đọc từ designer (Font.Name / Font.Size / Font.Bold của từng control) =====
   Mọi ô đều TextAlign = fmTextAlignLeft và canh giữa theo chiều dọc như TextBox 1 dòng của
   MSForms. Arial Narrow là font gốc — dự phòng sang họ condensed khác nếu máy không có, KHÔNG
   thay bằng Arial thường vì chữ rộng hơn và 36pt sẽ tràn khỏi ô. */
.vba-f8    { font: 8pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f12   { font: 12pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f162  { font: 16.2pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f1425 { font: bold 14.25pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f1575 { font: bold 15.75pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f2625 { font: 26.25pt Tahoma, 'MS Sans Serif', sans-serif; }
.vba-f36   { font: 36pt Tahoma, 'MS Sans Serif', sans-serif; }

.vba-rack {  /* txt_RACK* / txt_chem_rack* */
  font: bold 14.25pt 'Arial Narrow', 'Liberation Sans Narrow', 'Nimbus Sans Narrow', Arial, sans-serif;
}

.vba-big {   /* txt_DYE* / txt_WEIGHT* / txt_chem_code* / txt_chem_weight* */
  font: bold 36pt 'Arial Narrow', 'Liberation Sans Narrow', 'Nimbus Sans Narrow', Arial, sans-serif;
}

/* TextBox28 — ô rỗng làm vạch ngăn 2 bảng; BackColor = COLOR_HIGHLIGHT nên là VẠCH XANH. */
.vba-divider {
  background-color: var(--sys-highlight);
  pointer-events: none;
}

/* CommandButton: mặt nổi, nhấn xuống thì đảo viền. Màu nền do class c-* quyết định. */
.vba-btn {
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
  cursor: default;
}

.c-scrollbar    { background-color: var(--sys-scrollbar); }
.c-highlight    { background-color: var(--sys-highlight); }
.c-btnface      { background-color: var(--sys-btnface); }
.c-activecaption { background-color: var(--sys-activecaption); }

/* Label11..13 / 18..20 (tiêu đề cột) — Tahoma 7.8pt, nền COLOR_MENU (trùng màu mặt form) */
.vba-label {
  background-color: var(--sys-menu);
  display: flex;
  align-items: center;
  font: 7.8pt Tahoma, 'MS Sans Serif', sans-serif;
}

/* Label1..6 / 15..17 (số thứ tự dòng) — cùng cỡ chữ nhưng nền COLOR_HIGHLIGHT */
.vba-rowno {
  background-color: var(--sys-highlight);
  display: flex;
  align-items: center;
  font: 7.8pt Tahoma, 'MS Sans Serif', sans-serif;
}

/* txtRESULT — TextBox MultiLine, Locked */
.vba-result {
  resize: none;
  overflow: auto;
  white-space: pre;
  line-height: 1.25;
}

/* ListBox của 2 form chọn. Mỗi lựa chọn là một dòng riêng: `<option>` của select gốc không nhận
   được border/chiều cao trên Chromium nên phải tự dựng danh sách bằng div. */
.vba-listbox {
  background-color: #ffffff;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  overflow-y: auto;
  overflow-x: hidden;
}

.vba-listitem {
  display: flex;
  align-items: center;
  padding: 0 4pt;
  font-family: Calibri, Carlito, 'Segoe UI', sans-serif;
  border-bottom: 1px solid #d8d8d8;
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

/* Cỡ chữ do UserForm_Initialize đặt: formselect1 = Calibri 22, Formselect2 = Calibri 25. */
.li-22 { font-size: 22pt; height: 28pt; }
.li-25 { font-size: 25pt; height: 32pt; }

.vba-listitem:last-child { border-bottom: none; }
.vba-listitem:hover { background-color: #d8e6f8; }

.vba-listitem.is-selected {
  background-color: var(--sys-highlight);
  color: #ffffff;
}

.vba-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  /* Chốt chặn cuối: cửa sổ bé hơn cả mức thu nhỏ tối thiểu thì còn cuộn tới được nút OK. */
  overflow: auto;
  padding: 16px;
  z-index: 1000;
}

/* Lớp đệm mang ĐÚNG kích thước sau khi scale() — thiếu nó thì hộp thoại vẫn chiếm ô layout theo
   kích thước gốc và bị canh giữa lệch. */
.vba-modal-fit { flex: none; }

.vba-modal {
  transform-origin: top left;
  box-shadow: 4px 4px 12px rgba(0, 0, 0, 0.5);
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
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.vba-zoom {
  color: #404040;
  white-space: nowrap;
}

.vba-paper {
  display: flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}

.vba-paper input {
  width: 52px;
  font: inherit;
  padding: 1px 2px;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  background-color: #ffffff;
}

.vba-statusbar .is-error {
  color: #a00000;
  font-weight: bold;
}
</style>
