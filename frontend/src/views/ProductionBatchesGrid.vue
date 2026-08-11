<template>
  <!-- Bản dựng lại MainForm của Workbook C3 gốc theo ĐÚNG tọa độ trích từ VBA designer
       (Left/Top/Width/Height tính bằng point). Mọi kích thước dưới đây dùng đơn vị `pt`
       nên tỉ lệ khớp 1:1 với form VBA thật: form rộng 768pt × cao 540pt.
       Bảng màu cố định theo hệ màu Windows cổ điển của MSForms (BTNFACE #F0F0F0,
       WINDOW #FFFFFF) — cố ý KHÔNG dùng design token dark/light của app, vì yêu cầu là
       giao diện giống hệt bản gốc.

       `pageWrapper` là HẰNG SỐ quyết định một lần lúc khởi tạo (xem script) — đổi nó sau khi
       mount sẽ buộc Vue dựng lại toàn bộ cây con và mất trạng thái đo/thu phóng của trang. -->
  <component :is="pageWrapper">
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

          <!-- ===================== Dải header (T = 0..48pt) ===================== -->
          <label class="vba-label" :style="box(0, 6, 72, 18)">{{ $t('productionBatchesGrid.labelScanQr') }}</label>

          <button class="vba-btn" :style="box(132, 0, 42, 24)" @click="openMachinePicker">{{ $t('productionBatchesGrid.btnMachine') }}</button>
          <button class="vba-btn" :style="box(174, 0, 36, 24)" @click="openTankPicker">{{ $t('productionBatchesGrid.btnTank') }}</button>
          <!-- CommandButton4_Click: Me.Box7.Text = "OK" — bật cờ confirm2 để Save xong duyệt luôn. -->
          <button class="vba-btn" :style="box(210, 0, 48.05, 24)" @click="form.box7 = 'OK'">{{ $t('productionBatchesGrid.btnOk') }}</button>

          <!-- Box3 (confirm1) trong bản gốc có Visible = False nên KHÔNG vẽ ra đây; nó luôn
               mang giá trị "OK" và chỉ được ghi xuống DB. -->

          <!-- Box1 KHÔNG dùng v-model: máy quét QR bắn ~100 ký tự trong vài chục mili-giây,
               v-model làm Vue re-render sau mỗi ký tự và trình duyệt RỚT ký tự giữa chừng
               (đã xác minh thực tế ở /production-batches). Đọc .value một lần khi Enter. -->
          <input
            ref="box1Ref"
            class="vba-text"
            :style="box(0, 24, 72, 25.5)"
            :disabled="scanning"
            :title="$t('productionBatchesGrid.box1Title')"
            @keyup.enter="handleScan"
          />
          <input v-model="form.code" class="vba-text" :style="box(72, 24, 60, 25.5)" :title="$t('productionBatchesGrid.box2Title')" />
          <!-- Box4/Box5 có Locked = True trong bản gốc: chỉ điền được qua nút MACHINE/TANK. -->
          <input :value="form.machineCode" readonly class="vba-text" :style="box(132, 24, 42, 25.5)" :title="$t('productionBatchesGrid.box4Title')" />
          <input :value="form.tankCode" readonly class="vba-text" :style="box(174, 24, 36, 25.5)" :title="$t('productionBatchesGrid.box5Title')" />
          <input v-model="form.level" class="vba-text" :style="box(210, 24, 24, 25.5)" :title="$t('productionBatchesGrid.box6Title')" />
          <input v-model="form.box7" class="vba-text" :style="box(234.05, 24, 24, 25.5)" :title="$t('productionBatchesGrid.box7Title')" />

          <input :value="form.rawQrDye" readonly class="vba-text vba-raw" :style="box(258, 0, 114, 26.4)" title="raw_qr_dye" />
          <input :value="form.rawQrChem" readonly class="vba-text vba-raw" :style="box(258, 24, 114, 26.4)" title="raw_qr_chem" />

          <button class="vba-btn" :style="box(378, 0, 120, 48)" :disabled="saving" @click="handleSave">
            {{ saving ? $t('productionBatchesGrid.savingEllipsis') : $t('productionBatchesGrid.btnSave') }}
          </button>
          <button class="vba-btn" :style="box(498, 0, 60, 48)" @click="handleClear">{{ $t('productionBatchesGrid.btnClear') }}</button>
          <button class="vba-btn" :style="box(558, 0, 78, 48)" :disabled="saving" @click="handleApproveFromHeader">{{ $t('productionBatchesGrid.btnApprove') }}</button>
          <button class="vba-btn" :style="box(636, 0, 72, 48)" @click="checkFormOpen = true">{{ $t('productionBatchesGrid.btnCheck') }}</button>
          <button class="vba-btn" :style="box(708, 0, 60, 48)" @click="handleClose">{{ $t('productionBatchesGrid.btnClose') }}</button>

          <!-- ===================== Lưới 81 ô: 3 cột × 27 dòng (T = 54..540pt) ===================== -->
          <template v-for="(slot, i) in slots" :key="'slot-' + i">
            <input :value="slot ? i + 1 : ''" readonly class="vba-text vba-id" :style="box(slotLeft(i), slotTop(i), 12, 18)" :title="slot?.id" />
            <input :value="slot?.color || ''" readonly class="vba-text vba-cell" :style="box(slotLeft(i) + 12, slotTop(i), 66, 18)" />
            <input :value="slot?.product_code || ''" readonly class="vba-text vba-cell" :style="box(slotLeft(i) + 78, slotTop(i), 72, 18)" />
            <input :value="slot?.machine?.code || ''" readonly class="vba-text vba-cell" :style="box(slotLeft(i) + 150, slotTop(i), 42, 18)" />
            <input :value="slot?.level_code || ''" readonly class="vba-text vba-cell" :style="box(slotLeft(i) + 192, slotTop(i), 36, 18)" />
            <button
              class="vba-btn vba-btn-plus"
              :style="box(slotLeft(i) + 228, slotTop(i), 24, 18)"
              :disabled="!slot"
              @click="openSubForm(slot)"
            >+</button>
          </template>
        </div>
      </div>
    </div>

    <!-- ===================== SubForm ===================== -->
    <!-- Mọi hộp thoại dưới đây đều đi qua `modalFit`: lớp ngoài mang kích thước SAU khi thu/phóng
         (để canh giữa đúng), lớp trong giữ nguyên toạ độ pt gốc rồi scale(). Xem ghi chú ở script. -->
    <div v-if="subFormBatch" class="vba-modal-backdrop" @click.self="subFormBatch = null">
      <div class="vba-modal-fit" :style="subFormFit.fit" @click.self="subFormBatch = null">
      <div class="vba-form vba-modal" :style="subFormFit.form">
        <button class="vba-btn" :style="box(0, 0, 24, 24)" :title="$t('productionBatchesGrid.subFormDeleteTitle')" @click="deleteFromSubForm">D</button>
        <label class="vba-label" :style="box(48, 1.55, 30, 12)">{{ $t('productionBatchesGrid.labelMaDai') }}</label>
        <input :value="subFormBatch.product_code" readonly class="vba-text" :style="box(84, 0, 108, 25.5)" />
        <label class="vba-label" :style="box(42, 31.5, 36, 12)">{{ $t('productionBatchesGrid.labelMaMau') }}</label>
        <input :value="subFormBatch.color" readonly class="vba-text" :style="box(84, 31.5, 108, 25.5)" />

        <!-- Máy không đổi được sau khi đơn đã lưu (backend không có endpoint) -->
        <button class="vba-btn" :style="box(12, 67.5, 72, 24)" disabled :title="$t('productionBatchesGrid.maMayDisabledTitle')">{{ $t('productionBatchesGrid.btnMaMay') }}</button>
        <input :value="subFormBatch.machine?.code || ''" readonly class="vba-text" :style="box(84, 67.5, 108, 25.5)" />

        <button class="vba-btn" :style="box(12, 103.5, 72, 24)" @click="openTankPickerForSubForm">{{ $t('productionBatchesGrid.btnMaThung') }}</button>
        <input :value="subFormTankCode" readonly class="vba-text" :style="box(84, 103.5, 108, 25.5)" />

        <button class="vba-btn" :style="box(12, 139.5, 72, 24)" @click="subFormTankId = subFormBatch.tank_id ?? null">{{ $t('productionBatchesGrid.btnClear') }}</button>
        <button
          class="vba-btn"
          :style="box(102, 139.5, 90, 60)"
          :disabled="!subFormTankId || approving"
          :title="$t('productionBatchesGrid.subFormApproveTitle')"
          @click="approveFromSubForm"
        >{{ $t('productionBatchesGrid.btnApprove') }}</button>
        <button class="vba-btn" :style="box(12, 175.5, 72, 24)" @click="subFormBatch = null">{{ $t('productionBatchesGrid.btnClose') }}</button>
      </div>
      </div>
    </div>

    <!-- ===================== formselect2 — chọn MÁY ===================== -->
    <div v-if="machinePickerOpen" class="vba-modal-backdrop" @click.self="machinePickerOpen = false">
      <div class="vba-modal-fit" :style="machinePickerFit.fit" @click.self="machinePickerOpen = false">
      <div class="vba-form vba-modal" :style="machinePickerFit.form">
        <div class="vba-listbox vba-listbox-full" :style="box(12, MACHINE_LIST_TOP, 132, machineListHeight)">
          <div
            v-for="m in machines"
            :key="m.id"
            class="vba-listitem"
            :class="{ 'is-selected': machinePickerValue === m.code }"
            @click="machinePickerValue = m.code"
            @dblclick="machinePickerValue = m.code; confirmMachinePick()"
          >{{ m.code }}</div>
        </div>
        <button class="vba-btn vba-btn-big" :style="box(12, machineOkTop, 132, MACHINE_OK_H)" @click="confirmMachinePick">{{ $t('productionBatchesGrid.btnOk') }}</button>
      </div>
      </div>
    </div>

    <!-- ===================== formselect1 — chọn THÙNG ===================== -->
    <div v-if="tankPickerOpen" class="vba-modal-backdrop" @click.self="tankPickerOpen = false">
      <div class="vba-modal-fit" :style="tankPickerFit.fit" @click.self="tankPickerOpen = false">
      <div class="vba-form vba-modal" :style="tankPickerFit.form">
        <div class="vba-listbox vba-listbox-lg" :style="box(12, 6, 132, 152)">
          <div
            v-for="code in tankPickerOptions"
            :key="code"
            class="vba-listitem"
            :class="{ 'is-selected': tankPickerValue === code }"
            @click="tankPickerValue = code"
            @dblclick="tankPickerValue = code; confirmTankPick()"
          >{{ code }}</div>
        </div>
        <button class="vba-btn vba-btn-big" :style="box(12, 166, 132, 44)" @click="confirmTankPick">{{ $t('productionBatchesGrid.btnOk') }}</button>
      </div>
      </div>
    </div>

    <!-- ===================== checkform — kiểm tra trùng ===================== -->
    <div v-if="checkFormOpen" class="vba-modal-backdrop" @click.self="closeCheckForm">
      <div class="vba-modal-fit" :style="checkFormFit.fit" @click.self="closeCheckForm">
      <div class="vba-form vba-modal" :style="checkFormFit.form">
        <label class="vba-label" :style="box(36, 0, 36, 12)">{{ $t('productionBatchesGrid.labelMaMau') }}</label>
        <input
          v-model="checkColor"
          class="vba-text"
          :style="box(72, 0, 90, 25.5)"
          :title="$t('productionBatchesGrid.checkScanTitle')"
          @keyup.enter="splitCheckScan"
        />
        <label class="vba-label" :style="box(36, 36, 30, 12)">{{ $t('productionBatchesGrid.labelMaDai') }}</label>
        <input v-model="checkCode" class="vba-text" :style="box(72, 36, 90, 25.5)" />
        <button class="vba-btn" :style="box(0, 66, 72, 24)" @click="checkColor = ''; checkCode = ''; checkResult = ''">{{ $t('productionBatchesGrid.btnClear') }}</button>
        <button class="vba-btn" :style="box(90, 66, 72, 54)" @click="runCheck">{{ $t('productionBatchesGrid.btnKiemTra') }}</button>
        <button class="vba-btn" :style="box(0, 96, 72, 24)" @click="closeCheckForm">{{ $t('productionBatchesGrid.btnClose') }}</button>
      </div>
      </div>
    </div>

    <!-- Luôn vẽ dải trạng thái (kể cả khi chưa có thông báo): nếu để `v-if`, lúc nó hiện/ẩn thì
         chiều cao vùng chứa mặt form đổi đột ngột và cả lưới nhảy cỡ theo. -->
    <div class="vba-statusbar">
      <span class="vba-zoom">{{ $t('productionBatchesGrid.statusZoomLabel', { percent: Math.round(scale * 100) }) }}</span>
      <span v-if="statusMsg" :class="{ 'is-error': statusIsError }">{{ statusMsg }}</span>
    </div>

    <FullscreenButton variant="vba" @change="refit" />
    <NavToggleButton variant="vba" />
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import AppLayout from '../components/AppLayout.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import NavToggleButton from '../components/NavToggleButton.vue';
import { currentWorkstation } from '../services/workstation';
import { isFullscreen } from '../services/layout';
import { useAuthStore } from '../stores/auth';
import echo from '../services/echo';
import { applyVbaRowLock } from '../utils/vbaRowLock';

// useScope: 'global' — dùng chung 1 instance/state với i18n đăng ký ở main.ts, không tạo
// local scope riêng cho component này (xem mẫu ở AppLayout.vue).
const { t } = useI18n({ useScope: 'global' });

// Trang công khai (requiresAuth:false) — App.vue không bọc AppLayout, trang tự bọc lấy khi
// người xem đã đăng nhập để vẫn có menu điều hướng (mở bằng nút 3 gạch, xem NavToggleButton).
const isLoggedIn = useAuthStore().isAuthenticated;
const pageWrapper = isLoggedIn ? AppLayout : 'div';
const previousIsFullscreen = isFullscreen.value;

// Endpoint /api/public/... — trang không đăng nhập không gọi được nhóm /api/ thường.
const API = '/api/public';

const FORM_W = 768;
const FORM_H = 540;

/* ===== Thu/phóng cả mặt form cho vừa MỘT màn hình (dùng chung cách làm với PrintOrderEntry) ===== */

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
  fitModals();
  // Đo lại khung SAU khi chiều cao mới đã được áp — nếu không, `fitStage` vẫn dùng chiều cao cũ và
  // mặt form bị thu nhỏ hơn mức cần thiết đúng một nhịp.
  nextTick(fitStage);
}

/* ===== Thu/phóng các hộp thoại (MACHINE / TANK / SubForm / CHECK) ===== */

/**
 * Hộp thoại cũng dựng bằng toạ độ pt cố định như mặt form, nhưng trước đây KHÔNG được thu/phóng:
 * khi phóng to trình duyệt (Ctrl +) hoặc trên màn hình thấp, vùng nhìn thấy tính theo px co lại
 * trong khi hộp chọn MÁY vẫn cao 470pt (≈627px) nên nút OK bị cắt mất, không bấm được.
 * Đo trực tiếp cửa sổ chứ không đo phần tử cha: hộp thoại nằm trên nền `position: fixed`, kích
 * thước dùng được của nó là cả cửa sổ, không liên quan tới vùng cuộn của mặt form.
 */
const MODAL_MARGIN = 16;
const viewport = reactive({ w: 0, h: 0 });

function fitModals() {
  viewport.w = window.innerWidth;
  viewport.h = window.innerHeight;
}

function modalFit(wPt: number, hPt: number) {
  const wPx = wPt * PT;
  const hPx = hPt * PT;
  const availW = Math.max(160, (viewport.w || wPx) - MODAL_MARGIN * 2);
  const availH = Math.max(160, (viewport.h || hPx) - MODAL_MARGIN * 2);
  // Cùng biên độ với mặt form (`fitStage`) để cả trang thu/phóng đồng bộ.
  const s = Math.max(0.3, Math.min(availW / wPx, availH / hPx, 2));
  return {
    fit: { width: Math.round(wPx * s) + 'px', height: Math.round(hPx * s) + 'px' },
    form: { width: wPt + 'pt', height: hPt + 'pt', transform: `scale(${s})` },
  };
}

// Hai hộp chọn MÁY/THÙNG rộng hơn bản gốc (90pt) theo yêu cầu vận hành: chữ to, mỗi lựa chọn một
// dòng có vạch ngăn — bấm bằng ngón tay trên tablet nhà xưởng thay vì chuột.
//
// Hộp MÁY cao theo ĐÚNG số máy đang có (thực tế ≤ 20) để không phải cuộn: `modalFit` đã tự thu
// nhỏ cả hộp khi cửa sổ không đủ chỗ, nên cho cao tự do vẫn không bị cắt mất nút OK.
const MACHINE_ROW_PT = 26;
const MACHINE_LIST_TOP = 6;
const MACHINE_OK_H = 48;
// +1.5pt bù 2 viền 1px của khung (box-sizing: border-box) để dòng cuối không bị hụt.
const machineListHeight = computed(() => Math.max(1, machines.value.length) * MACHINE_ROW_PT + 1.5);
const machineOkTop = computed(() => MACHINE_LIST_TOP + machineListHeight.value + 8);
const machinePickerFit = computed(() =>
  modalFit(156, machineOkTop.value + MACHINE_OK_H + 12)
);
const tankPickerFit = computed(() => modalFit(156, 222));
const subFormFit = computed(() => modalFit(204, 204));
const checkFormFit = computed(() => modalFit(170, 126));

// Bật/tắt Toàn màn hình: đợi trình duyệt vẽ lại xong khung mới rồi mới đo, không thì đo trúng
// kích thước cũ.
const refit = () => requestAnimationFrame(fitAll);

// Lưới gốc: 81 ô chia 3 khối cột (Left 0 / 258 / 516), mỗi khối 27 dòng cao 18pt bắt đầu từ T=54.
const GRID_SLOTS = 81;
const ROWS_PER_BLOCK = 27;
const BLOCK_LEFTS = [0, 258, 516];

const box = (l: number, t: number, w: number, h: number) => ({
  left: l + 'pt',
  top: t + 'pt',
  width: w + 'pt',
  height: h + 'pt',
});

const slotLeft = (i: number) => BLOCK_LEFTS[Math.floor(i / ROWS_PER_BLOCK)];
const slotTop = (i: number) => 54 + (i % ROWS_PER_BLOCK) * 18;

const machines = ref<any[]>([]);
const tanks = ref<any[]>([]);
const batches = ref<any[]>([]);

// ROW LOCK (Mod_loadgrid.LoadGrid_safe) — thứ được đặt tên trong "grid load row lock id".
// Thuật toán dùng chung với lưới TO_SEND bên /print-order-entry, xem utils/vbaRowLock.ts.
const slotTags = ref<(string | null)[]>(Array(GRID_SLOTS).fill(null));
const batchById = ref(new Map<string, any>());

const slots = computed(() =>
  slotTags.value.map(id => (id ? batchById.value.get(id) ?? null : null))
);

const applyRowLock = (rows: any[]) => {
  const { tags, byId } = applyVbaRowLock(slotTags.value, rows, GRID_SLOTS);
  slotTags.value = tags;
  batchById.value = byId;
};

const box1Ref = ref<HTMLInputElement | null>(null);
const form = reactive({
  code: '',
  machineCode: '',
  tankCode: '',
  level: '',
  // Box7 = confirm2. "OK" + đã chọn Thùng => Save xong duyệt luôn (VBA: MoveToSend idNew).
  box7: '',
  rawQrDye: '',
  rawQrChem: '',
});

const scanning = ref(false);
// Chặn SAVE khi máy quét rớt ký tự giữa chừng (QrPayloadService::scan_looks_incomplete).
// KHÔNG có trong VBA gốc — đây là chốt an toàn riêng của bản web, thêm sau sự cố lưu nhầm
// dữ liệu lô LEP70158; bỏ nó đi là mở lại đúng lỗi cũ. Xem ghi chú ở ProductionBatches.vue.
const scanIncomplete = ref(false);
const saving = ref(false);
const approving = ref(false);
const statusMsg = ref('');
const statusIsError = ref(false);

const say = (msg: string, isError = false) => {
  statusMsg.value = msg;
  statusIsError.value = isError;
};

const findMachineByCode = (code: string) =>
  machines.value.find(m => m.code.toUpperCase() === (code || '').toUpperCase().trim());

/** "VD" & Format(Val(Mid(s,3)),"000") — khớp QrPayloadService::normalizeVdCode (VD2 -> VD002). */
const normalizeVdCode = (code: string) => {
  const upper = (code || '').toUpperCase().trim();
  if (!upper.startsWith('VD')) return upper;
  return 'VD' + String(parseInt(upper.slice(2), 10) || 0).padStart(3, '0');
};

/** Dạng 2 chữ số VD01-VD18 — đúng arrVD của VBA gốc, cũng là mã trong danh mục máy. */
const shortVdCode = (code: string) => {
  const upper = (code || '').toUpperCase().trim();
  if (!upper.startsWith('VD')) return upper;
  return 'VD' + String(parseInt(upper.slice(2), 10) || 0).padStart(2, '0');
};

// Thử cả 3 dạng: nguyên văn -> 3 chữ số -> 2 chữ số. QR ngoài xưởng bắn mã 2 chữ số ("VD04",
// xác nhận qua QR thật 2026-07-18) nhưng vẫn có nguồn ghi 3 chữ số, và danh mục máy đã đổi về
// 2 chữ số — thiếu bước lui này thì một trong hai dạng sẽ không tra ra máy nào.
const resolveMachine = (code: string) =>
  findMachineByCode(code) || findMachineByCode(normalizeVdCode(code)) || findMachineByCode(shortVdCode(code));

const currentMachine = computed(() => resolveMachine(form.machineCode));
const currentTank = computed(() =>
  tanks.value.find(t => t.machine_id === currentMachine.value?.id && t.code === form.tankCode)
);

// ---------- Box1: quét QR ----------
const handleScan = async () => {
  const el = box1Ref.value;
  const raw = (el?.value ?? '').trim();
  if (!raw || scanning.value) return;

  scanning.value = true;
  say('');
  try {
    const res = await axios.post(`${API}/production-batches/scan-parse`, { raw_scan: raw });
    const d = res.data.data;
    // PUT INTO BOX1, BOX2, BOX4, BOX6 — đúng Box1_AfterUpdate của VBA gốc.
    if (el) el.value = d.color;
    form.code = d.code;
    form.machineCode = d.machine;
    form.level = d.level;
    form.rawQrDye = d.raw_qr_dye || '';
    form.rawQrChem = d.raw_qr_chemical || '';
    form.tankCode = '';
    scanIncomplete.value = !!d.scan_looks_incomplete;
    if (scanIncomplete.value) {
      say(t('productionBatchesGrid.msgScanIncomplete'), true);
    }
  } catch (error: any) {
    say(error.response?.data?.message || t('productionBatchesGrid.errScanRead'), true);
  } finally {
    scanning.value = false;
    nextTick(() => box1Ref.value?.focus());
  }
};

const handleClear = () => {
  if (box1Ref.value) box1Ref.value.value = '';
  form.code = '';
  form.machineCode = '';
  form.tankCode = '';
  form.level = '';
  form.box7 = '';
  scanIncomplete.value = false;
  form.rawQrDye = '';
  form.rawQrChem = '';
  say('');
  box1Ref.value?.focus();
};

const handleClose = () => {
  handleClear();
  say(t('productionBatchesGrid.msgClosed'));
};

// ---------- SAVE (btnSAVE_Click) ----------
const doApprove = async (id: string) => {
  approving.value = true;
  try {
    await axios.post(`${API}/production-batches/${id}/approve`, {
      workstation_id: currentWorkstation.value?.code,
    });
  } finally {
    approving.value = false;
  }
};

const saveOrder = async (confirmDuplicate: boolean): Promise<any | null> => {
  if (scanIncomplete.value) {
    say(t('productionBatchesGrid.errScanIncompleteSave'), true);
    return null;
  }
  const color = (box1Ref.value?.value ?? '').trim();
  const machine = currentMachine.value;
  if (!color || !form.code || !machine) {
    say(t('productionBatchesGrid.errMissingInfo'), true);
    return null;
  }

  saving.value = true;
  try {
    const res = await axios.post(`${API}/production-batches`, {
      legacy_batch_id: `${color}-${form.code}-${Date.now()}`,
      color,
      product_code: form.code,
      machine_id: machine.id,
      tank_id: currentTank.value?.id ?? null,
      level_code: form.level || null,
      raw_qr_dye: form.rawQrDye || null,
      raw_qr_chemical: form.rawQrChem || null,
      confirm_duplicate: confirmDuplicate,
    });
    return res.data.data;
  } catch (error: any) {
    if (error.response?.data?.status === 'DUPLICATE_WARNING') {
      if (confirm(`${error.response.data.message}\n\n${t('productionBatchesGrid.confirmStillSave')}`)) {
        saving.value = false;
        return saveOrder(true);
      }
      say(t('productionBatchesGrid.msgSaveCancelled'), true);
    } else {
      say(error.response?.data?.message || t('productionBatchesGrid.errSaveGeneric'), true);
    }
    return null;
  } finally {
    saving.value = false;
  }
};

// "If UCase$(c2) = "OK" And Trim(Me.Box5.Text) <> "" Then MoveToSend idNew"
// c2 = Box7, Box5 = Thùng — thiếu Thùng thì chỉ lưu, KHÔNG tự duyệt (đúng VBA gốc).
const handleSave = async () => {
  say('');
  const autoApprove = form.box7.trim().toUpperCase() === 'OK' && !!form.tankCode.trim();
  const batch = await saveOrder(false);
  if (!batch) return;

  if (autoApprove) {
    try {
      await doApprove(batch.id);
      say(t('productionBatchesGrid.msgSavedApproved', { color: batch.color, code: batch.product_code }));
    } catch (error: any) {
      say(t('productionBatchesGrid.msgAutoApproveFailed', { error: error.response?.data?.message || t('productionBatchesGrid.errUnknownFallback') }), true);
    }
  } else {
    say(t('productionBatchesGrid.msgSavedPending', { color: batch.color, code: batch.product_code }));
  }
  handleClear();
  fetchWaiting();
};

const handleApproveFromHeader = async () => {
  say('');
  const batch = await saveOrder(false);
  if (!batch) return;
  try {
    await doApprove(batch.id);
    say(t('productionBatchesGrid.msgSavedApproved', { color: batch.color, code: batch.product_code }));
    handleClear();
    fetchWaiting();
  } catch (error: any) {
    say(t('productionBatchesGrid.msgApproveFailedHeader', { error: error.response?.data?.message || t('productionBatchesGrid.errUnknownFallback') }), true);
    fetchWaiting();
  }
};

// ---------- SubForm ----------
const subFormBatch = ref<any | null>(null);
const subFormTankId = ref<number | null>(null);

const subFormTankCode = computed(() => tanks.value.find(t => t.id === subFormTankId.value)?.code || '');

const openSubForm = (batch: any) => {
  if (!batch) return;
  subFormBatch.value = batch;
  subFormTankId.value = batch.tank_id ?? null;
};

const approveFromSubForm = async () => {
  const batch = subFormBatch.value;
  if (!batch || !subFormTankId.value) return;
  try {
    if (subFormTankId.value !== batch.tank_id) {
      await axios.put(`${API}/production-batches/${batch.id}/tank`, { tank_id: subFormTankId.value });
    }
    await doApprove(batch.id);
    say(t('productionBatchesGrid.msgSubApproved', { color: batch.color, code: batch.product_code }));
    subFormBatch.value = null;
    fetchWaiting();
  } catch (error: any) {
    say(error.response?.data?.message || t('productionBatchesGrid.errCannotApprove'), true);
  }
};

// "D" — VBA xóa vật lý; hệ mới chuyển trạng thái CANCELLED (không hard-delete dữ liệu giao dịch).
const deleteFromSubForm = async () => {
  const batch = subFormBatch.value;
  if (!batch) return;
  if (!confirm(t('productionBatchesGrid.confirmCancelOrder', { color: batch.color, code: batch.product_code }))) return;
  try {
    await axios.put(`${API}/production-batches/${batch.id}/status`, { status: 'CANCELLED' });
    say(t('productionBatchesGrid.msgCancelled', { color: batch.color, code: batch.product_code }));
    subFormBatch.value = null;
    fetchWaiting();
  } catch (error: any) {
    say(error.response?.data?.message || t('productionBatchesGrid.errCannotCancel'), true);
  }
};

// ---------- Picker máy / thùng ----------
const machinePickerOpen = ref(false);
const machinePickerValue = ref('');
const tankPickerOpen = ref(false);
const tankPickerValue = ref('');
// Picker thùng dùng chung cho header (Box5) và SubForm — cờ này quyết định trả kết quả về đâu.
const tankPickerTarget = ref<'header' | 'subform'>('header');

const openMachinePicker = () => {
  machinePickerValue.value = form.machineCode;
  machinePickerOpen.value = true;
};

const confirmMachinePick = () => {
  form.machineCode = machinePickerValue.value;
  form.tankCode = '';
  machinePickerOpen.value = false;
};

// VBA gốc (`mainform.CommandButton3_Click`) đổ vào ListBox một MẢNG CỐ ĐỊNH:
//     arrVD = Array("1A", "2B", "3C", "4D", "FB")
// — không liên quan gì tới máy đang chọn, nên bấm nút TANK lúc nào cũng có đủ 5 thùng, kể cả
// khi Box4 (máy) còn trống. Bản port trước đây lọc `t.machine_id === machineId`, mà Box4 mặc
// định rỗng nên hộp chọn thùng RỖNG HOÀN TOÀN cho tới khi chọn máy trước — không có thông báo
// nào, nhìn y như hỏng (lỗi thật 2026-08-04). VBA cũng không bắt thứ tự: thùng chọn trước hay
// sau máy đều được, và SAVE của bản gốc chỉ đòi Box1 + Box2.
//
// Vẫn lấy danh sách từ danh mục thật thay vì chép cứng mảng trên, để thêm loại thùng trong
// Master Data là màn này có ngay; mảng VBA chỉ dùng khi danh mục chưa nạp được (mất API) — đúng
// bằng cái bản gốc luôn có sẵn ngay cả lúc mất kết nối.
const TANK_CODES_VBA = ['1A', '2B', '3C', '4D', 'FB'];

const tankPickerOptions = computed<string[]>(() => {
  const codes = [...new Set(tanks.value.map(t => t.code))].sort();
  return codes.length ? codes : TANK_CODES_VBA;
});

const openTankPicker = () => {
  tankPickerTarget.value = 'header';
  tankPickerValue.value = form.tankCode;
  tankPickerOpen.value = true;
};

const openTankPickerForSubForm = () => {
  tankPickerTarget.value = 'subform';
  tankPickerValue.value = subFormTankCode.value;
  tankPickerOpen.value = true;
};

const confirmTankPick = () => {
  if (tankPickerTarget.value === 'subform') {
    // SubForm ghi thẳng `tank_id` (khóa ngoại theo TỪNG máy) nên phải quy mã thùng về đúng bản
    // ghi thùng của máy thuộc đơn này — danh sách chọn nay không còn lọc sẵn theo máy nữa.
    const machineId = subFormBatch.value?.machine_id;
    const tank = tanks.value.find(t => t.machine_id === machineId && t.code === tankPickerValue.value);
    subFormTankId.value = tank?.id ?? null;
    if (!tank) {
      say(t('productionBatchesGrid.errNoTankForMachine', { code: tankPickerValue.value }), true);
    }
  } else {
    form.tankCode = tankPickerValue.value;
  }
  tankPickerOpen.value = false;
};

// ---------- checkform ----------
const checkFormOpen = ref(false);
const checkColor = ref('');
const checkCode = ref('');
const checkResult = ref('');

/** Mod_clean_garbage.CleanLeadingGarbage — bỏ rác đầu chuỗi, giữ từ ký tự A-Z/0-9 đầu tiên. */
const cleanLeadingGarbage = (s: string) => {
  for (let i = 0; i < s.length; i++) {
    const c = s.charCodeAt(i);
    if ((c >= 48 && c <= 57) || (c >= 65 && c <= 90)) return s.slice(i);
  }
  return '';
};

// checkform.TextBox1_AfterUpdate — quét vào ô MA MAU rồi tách "/"->"-", gộp "--", split "-".
const splitCheckScan = () => {
  let cleaned = cleanLeadingGarbage(checkColor.value).replace(/\//g, '-');
  while (cleaned.includes('--')) cleaned = cleaned.replace(/--/g, '-');
  const arr = cleaned.split('-');
  checkColor.value = arr[0] ?? '';
  checkCode.value = arr[1] ?? '';
};

const runCheck = () => {
  const exists = batches.value.some(
    b => b.color === checkColor.value.trim() && b.product_code === checkCode.value.trim()
  );
  checkResult.value = exists ? 'YES' : 'NO';
  say(exists ? t('productionBatchesGrid.checkResultYes') : t('productionBatchesGrid.checkResultNo'));
};

const closeCheckForm = () => {
  checkFormOpen.value = false;
  checkResult.value = '';
};

// ---------- Dữ liệu ----------
const fetchMetadata = async () => {
  try {
    const [machinesRes, tanksRes] = await Promise.all([axios.get(`${API}/machines`), axios.get(`${API}/tanks`)]);
    machines.value = machinesRes.data.data || [];
    tanks.value = tanksRes.data.data || [];
  } catch (error) {
    console.error('Error fetching metadata:', error);
  }
};

// WAITING = tbl_input_all gốc: chỉ đơn chưa duyệt.
//
// Phải xin per_page = 100 (trần của API) chứ KHÔNG phải 81. Trước đây xin đúng 81 nên khi
// hàng chờ vượt 81 đơn, API trả về 81 đơn MỚI NHẤT — trong khi bảng chờ theo máy bên
// /print-order-entry xin 100, tức là có đơn hiện bên đó mà bên này mất tăm dù cùng một
// nguồn dữ liệu. VBA cũng không cắt: LoadGrid_safe đọc TOÀN BỘ tbl_input_all "ORDER BY id"
// (cũ nhất trước) rồi lấp dần vào 81 ô, dư thì thôi. Nên ở đây sắp xếp CŨ NHẤT TRƯỚC rồi
// đưa cả danh sách cho row lock tự lấp — hết ô thì nó tự dừng, đúng hành vi bản gốc.
const fetchWaiting = async () => {
  try {
    const res = await axios.get(`${API}/production-batches`, { params: { status: 'NEW', per_page: 100 } });
    const rows = [...res.data.data].sort(
      (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime()
    );
    batches.value = rows;
    applyRowLock(rows);
  } catch (error) {
    console.error('Error fetching WAITING:', error);
  }
};

let pollInterval: any = null;

onMounted(() => {
  // Người ĐÃ đăng nhập vào trang là thấy menu ngay (yêu cầu 2026-08-04) — muốn xem rộng thì
  // tự bấm nút 3 gạch để thu gọn. Người xem công khai không được bọc AppLayout nên không
  // có menu nào để hiện.
  if (isLoggedIn) isFullscreen.value = false;
  fetchMetadata();
  fetchWaiting();
  box1Ref.value?.focus();
  // Thay cho Mod_TIMER_AUTOGRID (VBA tự nạp lại lưới theo timer).
  echo.channel('production-batches').listen('.updated', fetchWaiting);
  pollInterval = setInterval(fetchWaiting, 15000);

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
  isFullscreen.value = previousIsFullscreen;
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

/* Nền form = COLOR_BTNFACE của Windows, viền nổi kiểu cửa sổ MSForms */
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

.vba-text:disabled {
  background-color: #f0f0f0;
}

.vba-cell,
.vba-id {
  padding: 0 1px;
  cursor: default;
}

/* Cột id dùng COLOR_WINDOWFRAME trong bản gốc — nền tối, chữ sáng */
.vba-id {
  background-color: #646464;
  color: #ffffff;
  text-align: center;
}

.vba-raw {
  font-size: 7pt;
}

/* CommandButton: mặt BTNFACE, viền nổi, nhấn xuống thì đảo viền */
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
  cursor: default;
}

.vba-btn-plus {
  font-weight: bold;
}

.vba-label {
  background-color: transparent;
  display: flex;
  align-items: center;
}

.vba-listbox {
  background-color: #ffffff;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  font: inherit;
  outline: none;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Mỗi lựa chọn là một dòng riêng có vạch ngăn: `<option>` của select gốc không nhận được
   border/chiều cao trên Chromium nên phải tự dựng danh sách bằng div. */
.vba-listitem {
  height: 26pt;
  display: flex;
  align-items: center;
  padding: 0 6pt;
  font-size: 13pt;
  font-weight: bold;
  border-bottom: 1px solid #b0b0b0;
  cursor: pointer;
  user-select: none;
  white-space: nowrap;
}

.vba-listitem:last-child {
  border-bottom: none;
}

.vba-listitem:hover {
  background-color: #d8e6f8;
}

.vba-listitem.is-selected {
  background-color: #316ac5;
  color: #ffffff;
}

/* Hộp chọn MÁY cao đúng bằng số máy nên không bao giờ cần cuộn. */
.vba-listbox-full {
  overflow-y: hidden;
}

.vba-listbox-lg .vba-listitem {
  height: 30pt;
  font-size: 15pt;
}

.vba-btn-big {
  font-size: 12pt;
  font-weight: bold;
}

.vba-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  /* Chốt chặn cuối: nếu cửa sổ bé hơn cả mức thu nhỏ tối thiểu (30%) thì còn cuộn tới được nút OK. */
  overflow: auto;
  padding: 16px;
  z-index: 1000;
}

/* Lớp đệm mang ĐÚNG kích thước sau khi scale() — thiếu nó thì hộp thoại vẫn chiếm ô layout theo
   kích thước gốc và bị canh giữa lệch (thừa/hụt đúng phần đã thu/phóng). */
.vba-modal-fit {
  flex: none;
}

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

.vba-statusbar .is-error {
  color: #a00000;
  font-weight: bold;
}
</style>
