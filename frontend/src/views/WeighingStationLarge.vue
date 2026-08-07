<template>
  <div class="wsl-root" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <!-- ===== BẢN SAO ĐÚNG TỈ LỆ CỦA UserForm `scaleform` =====
         Khung 734.26 x 546.01 pt (= 979 x 728 px) đúng bằng form gốc; mọi control đặt tuyệt đối
         theo toạ độ/kích thước thật đọc từ chính file .xlsm. Cả khung được thu/phóng bằng MỘT
         phép transform: scale() nên tỉ lệ giữa các control, cỡ chữ, độ dày viền không bao giờ
         lệch so với bản VBA dù màn hình to nhỏ thế nào. -->
    <div class="stage-wrap" ref="wrapRef">
      <!-- Lớp đệm mang ĐÚNG kích thước SAU khi thu/phóng. `transform: scale()` không đổi ô layout
           của phần tử, nên thiếu lớp này thì lúc phóng to > 1 mặt form tràn ra ngoài vùng cuộn và
           bị cắt mất mép. -->
      <div class="stage-fit" :style="{ width: `${STAGE_W * scale}px`, height: `${STAGE_H * scale}px` }">
        <div class="stage" :style="{ transform: `scale(${scale})` }">

        <!-- Nhãn tiêu đề cột -->
        <div v-for="h in HEADERS" :key="h.text" class="vv-head" :style="box(h)">{{ h.text }}</div>

        <!-- Số thứ tự dòng 1..9 -->
        <div v-for="i in 9" :key="'n' + i" class="vv-rowno" :style="box(rowNoBox(i - 1))">{{ i }}</div>

        <!-- Lưới 9 dòng: RACK / DYE CODE / WEIGHT / PROCESS -->
        <template v-for="i in 9" :key="'r' + i">
          <!-- txt_RACK: ô DUY NHẤT gõ tay được, đúng bản gốc (chỉ txt_rackN đặt LastInputBox) -->
          <input
            class="vv-text rack"
            :class="rowCls(i - 1)"
            :style="box(rackBox(i - 1))"
            :value="rackAt(i - 1)"
            @focus="lastInputIdx = i - 1"
            @input="onUpdateRack(i - 1, ($event.target as HTMLInputElement).value)"
            @click.stop
          />
          <div class="vv-text ro dye" :class="rowCls(i - 1)" :style="box(dyeBox(i - 1))">
            {{ jobItems[i - 1]?.material_code || '' }}
          </div>
          <div class="vv-text ro num plan" :class="rowCls(i - 1)" :style="box(weightBox(i - 1))">
            {{ plannedText(i - 1) }}
          </div>
          <!-- txt_PROCESS: vừa hiện số cân vừa là đèn dung sai — trong VBA màu nền ô này CHÍNH LÀ
               trạng thái nghiệp vụ (btnSave_Click đọc ngược BackColor ra ACCEPTED/REJECTED). -->
          <div
            class="vv-text num proc"
            :class="rowCls(i - 1)"
            :style="processBox(i - 1)"
            @click="onSelectRow(i - 1)"
          >{{ processText(i - 1) }}</div>
        </template>

        <!-- Cột nút bên phải -->
        <button class="vv-btn key" :style="box(C.btn_del)" @click="numDel">DEL</button>
        <button class="vv-btn ghost" :style="box(C.btnPrint)" @click="printSlip()">PRINT</button>
        <button class="vv-btn ghost" :style="box(C.btnCheck)" @click="showChecker = true">CHECK</button>

        <button
          v-for="k in NUMPAD"
          :key="'k' + k.d"
          class="vv-btn key"
          :style="box(k)"
          @click="numClick(k.d)"
        >{{ k.d }}</button>

        <button class="vv-btn ghost" :style="box(C.btnClose)" @click="onClose">CLOSE</button>
        <button class="vv-btn out" :style="box(C.btn_out)" :disabled="sendingRack" @click="onOut">OUT</button>
        <button class="vv-btn in" :style="box(C.btn_in)" :disabled="sendingRack" @click="onIn">IN</button>
        <!-- onClear() có ngoặc: để "onClear" trần thì Vue truyền MouseEvent vào tham số
             skipConfirm (truthy) -> bỏ qua luôn hộp xác nhận. -->
        <button class="vv-btn danger" :style="box(C.btnCLEAR)" :disabled="saving" @click="onClear()">CLEAR</button>
        <button class="vv-btn primary" :style="box(C.btnSAVE)" :disabled="saving" @click="onSave">
          {{ saving ? '…' : 'SAVE' }}
        </button>
        <button class="vv-btn accent" :style="box(C.btnNext)" :disabled="saving || !canPressNext" @click="onNext">
          NEXT
        </button>

        <!-- Nhãn cho các ô dưới — bản VBA để trống trơn, ai không thuộc form thì không đoán được ô
             nào là gì. Đặt vừa khít khe 9pt giữa lưới và hàng ô, không đụng vào toạ độ gốc. -->
        <div v-for="g in LEGENDS" :key="g.text" class="vv-legend" :style="box(g)">{{ g.text }}</div>

        <!-- Khối dưới trái: COLOR / MACHINE / CODE / LV + ô delta khổng lồ + rawline -->
        <input
          ref="scanInputRef"
          class="vv-text scan"
          :class="{ scanning }"
          :style="box(C.txt_COLOR)"
          :value="activeBatch?.color || ''"
          :placeholder="scanning ? '…' : 'quét QR'"
          :readonly="scanning"
          @keyup.enter="onScanEnter"
        />
        <div class="vv-text ro" :style="box(C.txt_MACHINE)">{{ activeBatch?.machine?.code || '' }}</div>
        <div class="vv-text ro field" :style="box(C.txt_CODE)">
          <i class="cap">CODE</i><span>{{ activeBatch?.product_code || '' }}</span>
        </div>
        <div class="vv-text ro field" :style="box(C.txt_LV)">
          <i class="cap">LV</i><span>{{ activeBatch?.level_code || '' }}</span>
        </div>

        <!-- delta_rawline: số thợ thực sự nhìn khi đang đổ vật tư -->
        <div class="vv-delta" :class="'tone-' + deltaTone" :style="box(C.delta)">
          <span class="delta-num">{{ liveWeight.toFixed(2) }}</span>
          <span v-if="currentTarget !== null" class="delta-target">/ {{ currentTarget.toFixed(2) }}</span>
        </div>

          <div class="vv-text ro num field raw" :style="box(C.rawline)">
            <i class="cap">RAW</i><span>{{ grossWeight.toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== DẢI THÔNG TIN CỦA RIÊNG BẢN WEB =====
         Cố ý nằm NGOÀI khung form: bản VBA không có mấy thứ này (mất tín hiệu cân, hàng đợi gửi
         mẻ, giả lập, thông báo lỗi) nhưng bỏ đi thì thợ không có cách nào biết máy đang hỏng gì.
         Để ngoài khung thì phần form vẫn giống hệt bản gốc. -->
    <div class="webbar" :class="{ alarm: statusMsg?.bad }">
      <span class="wb-ws">
        <!-- `signalLost` (3s) chứ không phải `signalLive` (1.5s): ngưỡng chặt là CỔNG AN TOÀN
             cho việc chốt bì/lưu số, dùng nó để bật đèn báo thì đèn nhấp nháy mỗi lần số về trễ
             một nhịp — xem ghi chú LOST_SIGNAL_MS trong useScaleFeed. -->
        <span class="wb-dot" :class="scaleOnline && !signalLost ? 'on' : 'off'"></span>
        {{ currentWorkstation?.code || 'chưa gán trạm' }}
      </span>
      <span class="wb-pill" :class="isStable ? 'ok' : 'wait'">
        {{ signalLost && !useSimValue ? '✕ MẤT TÍN HIỆU' : (isStable ? '● ỔN ĐỊNH' : '○ CHỜ ỔN ĐỊNH') }}
      </span>
      <span v-if="tareBaseline !== null" class="wb-tare">Bì {{ tareBaseline.toFixed(2) }}</span>
      <button v-if="currentIndex >= 0 && tareBaseline !== null" class="wb-btn" @click="retare">BÌ LẠI</button>

      <span v-if="rackBatchText" class="wb-rack" :title="'Lô rack sẽ gửi khi bấm OUT'">
        LÔ 1: {{ rackBatchText }}
        <button class="wb-btn" @click="onCopyRacks">COPY</button>
      </span>

      <button v-if="queueCount > 0" class="wb-queue" :class="{ stuck: stuckCount > 0 }" @click="showQueue = true">
        {{ stuckCount > 0 ? '✕' : '⏳' }} {{ queueCount }} mẻ chờ gửi
      </button>

      <!-- Đứng TRƯỚC ô thông báo chứ không phải sau: `.wb-msg` là phần tử duy nhất co giãn, nên
           để sau nó thì mỗi lần có thông báo dài là cụm giả lập bị đẩy ra sát rìa phải, có màn
           hình hẹp còn đẩy khuất hẳn. Trước nó thì vị trí đứng yên bất kể thông báo dài ngắn. -->
      <label class="wb-sim">
        <input type="checkbox" v-model="useSimValue" /> giả lập
      </label>
      <input v-if="useSimValue" type="number" step="0.1" class="wb-siminput" v-model.number="simulatedWeight" />

      <!-- Ô thông báo: là phần tử DUY NHẤT được co giãn trong dải, nên khi chật thì nó bị cắt
           bớt chứ không đẩy mất mấy nút bấm bên cạnh. Chữ đầy đủ xem ở tooltip. -->
      <span class="wb-msg" :class="{ bad: statusMsg?.bad }" :title="statusMsg?.text || ''">
        {{ statusMsg ? (statusMsg.bad ? '❌ ' : '') + statusMsg.text : '' }}
      </span>
    </div>

    <!-- Bảng hàng đợi — chỉ mở khi thợ chủ động bấm -->
    <div v-if="showQueue" class="queue-overlay" @click.self="showQueue = false">
      <div class="queue-modal">
        <div class="queue-head">
          <strong>Mẻ chờ gửi lên máy chủ ({{ queueCount }})</strong>
          <button class="wb-btn" @click="showQueue = false">ĐÓNG</button>
        </div>
        <p class="queue-note">
          Các mẻ này <strong>đã cân xong và đã in phiếu</strong>, chỉ chưa lên được máy chủ. Máy tự
          gửi lại khi có mạng. <strong>Đừng xoá dữ liệu trình duyệt</strong> khi danh sách còn mẻ.
        </p>
        <table class="queue-table">
          <thead>
            <tr><th>Mẻ</th><th>Xếp hàng lúc</th><th class="c-num">Lần thử</th><th>Trạng thái</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="q in queueItems" :key="q.idempotency_key">
              <td class="strong">{{ q.nhan || '—' }}</td>
              <td>{{ formatQueueTime(q.queued_at) }}</td>
              <td class="c-num">{{ q.so_lan_thu }}</td>
              <td>
                <span v-if="q.loi_nghiep_vu" class="queue-err">
                  {{ q.loi_nghiep_vu }} <em>(vẫn đang tự gửi lại mỗi 15 giây)</em>
                </span>
                <span v-else-if="duongThong">đang chờ tới lượt gửi</span>
                <span v-else>mất kết nối — đang dò lại mỗi 15 giây</span>
              </td>
              <td class="queue-act">
                <button v-if="q.loi_nghiep_vu" class="wb-btn" @click="onThuLai(q.idempotency_key)">THỬ LẠI</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="queue-foot">
          <button class="wb-btn" :disabled="flushing" @click="onGuiNgay">
            {{ flushing ? 'ĐANG GỬI…' : 'GỬI NGAY' }}
          </button>
        </div>
      </div>
    </div>

    <WeighingCheckerModal :show="showChecker" @close="showChecker = false" />

    <!-- z-index 40: dưới lớp phủ bảng hàng đợi (.queue-overlay = 60) -->
    <!-- Hộp thoại thay alert/confirm — xem composables/useHopThoai.ts. Không dùng hộp thoại gốc
         vì Chrome đá màn hình khỏi F11 mỗi lần nó bật lên. -->
    <HopThoaiVba :thoai="thoai" @dong="dongThoai" />

    <FullscreenButton variant="vba" :z-index="40" @change="refit" />
  </div>
</template>

<script setup lang="ts">
/**
 * /weighing-station-large — TRẠM CÂN TO.
 *
 * Bản sao giao diện VÀ cách thao tác của UserForm `scaleform` trong workbook VBA
 * "5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm" (trạm cân lớn >= 6kg).
 *
 * ĐÂY LÀ MÀN HÌNH RIÊNG, KHÔNG phải biến thể của /weighing-station-v2 (workbook cân nhỏ): hai
 * trạm là hai công đoạn vật lý khác nhau. Khác biệt nghiệp vụ lớn nhất là cặp nút OUT/IN — gửi
 * mã rack sang hệ pha màu (khối "SEND OVER 6").
 *
 * ===== TOẠ ĐỘ LẤY TỪ ĐÂU =====
 * VBA project của workbook này BỊ KHOÁ MẬT KHẨU (`DPx=` trong stream PROJECT) nên không mở được
 * designer qua Excel COM. Toạ độ/kích thước/cỡ chữ dưới đây đọc thẳng từ binary MS-OFORMS
 * (`xl/vbaProject.bin` -> storage `scaleform` -> stream `f` cho vị trí, stream `o` cho kích
 * thước + caption + font). Đơn vị gốc là himetric, đã quy về POINT.
 *
 * **KHÔNG chỉnh tay các số trong hằng `C` / `HEADERS` / `NUMPAD`** — chúng là bản sao của form
 * thật. Muốn đổi bố cục thì đổi trong file .xlsm rồi trích lại.
 *
 * Dùng chung phần HẠ TẦNG với các màn cân khác (useScaleFeed, saveQueue, weighSlip, qrDyeParser,
 * processColor): đó là bản port DUY NHẤT của các thuật toán VBA gốc (delta/bì, dung sai ±1%, bố
 * cục phiếu, đọc QR). Chép tay lần nữa là mở đường cho hai màn hình cùng cân một mẻ mà ra hai
 * kết quả khác nhau.
 */

import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import WeighingCheckerModal from '../components/weighing/WeighingCheckerModal.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import HopThoaiVba from '../components/HopThoaiVba.vue';
import { useHopThoai } from '../composables/useHopThoai';
import { currentWorkstation, adoptLocalWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { isFullscreen } from '../services/layout';
import { useScaleFeed } from '../composables/useScaleFeed';
import { printSlipHtml, cuaSoThat } from '../utils/slipPrint';
import { parseDyeQr, MAX_RACK_LINES, type ParsedDyeQr } from '../utils/qrDyeParser';
import { processTone, processBackground } from '../utils/processColor';
import { buildSlipHtml, processStatus, nowSlipTimestamp, MANUAL_MATERIAL_CODE } from '../utils/weighSlip';
import { guiRackSangAgent, RACK_BATCH_SIZE } from '../services/rackDispatch';
import {
  xepHang, danhDauDaGui, thuLai, danhDauKet, dayHangDoi, danhSachChoGui,
  batTuDay, tatTuDay, queueCount, stuckCount, flushing, duongThong,
} from '../services/saveQueue';

/* ============================================================================
 * BỐ CỤC GỐC — mọi số đo theo POINT, đúng như trong scaleform.frm
 * ========================================================================== */

/** Khổ form gốc (pt). ClientWidth/ClientHeight trong VBFrame là twip, đã chia 20. */
const FORM_W_PT = 734.26;
const FORM_H_PT = 546.01;
/** 1 pt = 4/3 px ở 96 dpi — khung dựng bằng px rồi thu/phóng bằng transform. */
const PX = 4 / 3;
const STAGE_W = FORM_W_PT * PX;
const STAGE_H = FORM_H_PT * PX;

interface Box { l: number; t: number; w: number; h: number; f?: number; text?: string }

/** Toạ độ hàng: txt_RACK1 ở 11.99pt, bước dòng 48.04pt (đo từ txt_RACK1..9). */
const ROW_TOP_0 = 11.99;
const ROW_STEP = 48.04;
/**
 * Trong form gốc DYE/WEIGHT/PROCESS bị đặt thấp hơn ô RACK 3.65pt — gần như chắc chắn là do kéo
 * thả bằng chuột chứ không có ý gì, và nhìn ra là 4 ô cùng một dòng lại so le nhau. Đặt 0 để
 * chúng thẳng hàng. Đây là sai lệch DUY NHẤT về toạ độ so với bản gốc, và chỉ 4.9px.
 */
const ROW_OFFSET = 0;

/** Nhãn cột — bản gốc cũng so le (RACK ở 0, ba cái kia ở 3.63pt); cho về cùng 0 luôn. */
const HEADERS: Box[] = [
  { l: 12.02, t: 0, w: 30.02, h: 11.99, f: 7.8, text: 'RACK' },
  { l: 65.99, t: 0, w: 47.99, h: 11.99, f: 7.8, text: 'DYE CODE' },
  { l: 257.98, t: 0, w: 36, h: 11.99, f: 7.8, text: 'WEIGHT' },
  { l: 396.03, t: 0, w: 42.01, h: 11.99, f: 7.8, text: 'PROCESS' },
];

/**
 * Nhãn cho hàng ô dưới cùng — KHÔNG có trong form gốc (bên đó 4 ô COLOR/MACHINE/CODE/LV trần
 * trụi, ai không thuộc form thì không đoán được ô nào là gì). Đặt vừa khe 9.3pt giữa đáy lưới
 * (440.7) và đỉnh hàng ô (450), nên không control gốc nào phải xê dịch.
 * CODE / LV / RAW không có khe trống phía trên nên nhãn nằm luôn trong ô (xem `.field .cap`).
 */
const LEGENDS: Box[] = [
  { l: 11.99, t: 441, w: 90, h: 8.4, f: 6.4, text: 'COLOR' },
  { l: 108, t: 441, w: 47.99, h: 8.4, f: 6.4, text: 'MACHINE' },
  { l: 162, t: 441, w: 384.01, h: 8.4, f: 6.4, text: 'DELTA — ĐÃ TRỪ BÌ' },
];

/** Bàn phím số: 3 cột x 3 hàng + phím 0. Trùng khít btn_1..btn_9, btn_0. */
const NUMPAD: (Box & { d: string })[] = [
  { d: '1', l: 551.99, t: 65.99, w: 48.02, h: 42.01, f: 16.2 },
  { d: '2', l: 612, t: 65.99, w: 48.02, h: 42.01, f: 16.2 },
  { d: '3', l: 672.01, t: 65.99, w: 48.02, h: 42.01, f: 16.2 },
  { d: '4', l: 551.99, t: 114.01, w: 48.02, h: 42.01, f: 16.2 },
  { d: '5', l: 612, t: 114.01, w: 48.02, h: 42.01, f: 16.2 },
  { d: '6', l: 672.01, t: 114.01, w: 48.02, h: 42.01, f: 16.2 },
  { d: '7', l: 551.99, t: 162, w: 48.02, h: 41.98, f: 16.2 },
  { d: '8', l: 612, t: 162, w: 48.02, h: 41.98, f: 16.2 },
  { d: '9', l: 672.01, t: 162, w: 48.02, h: 41.98, f: 16.2 },
  { d: '0', l: 551.99, t: 209.99, w: 47.99, h: 42.01, f: 16.2 },
];

const C: Record<string, Box> = {
  btn_del:    { l: 551.99, t: 6.01, w: 48.02, h: 54, f: 16.2 },
  // 16.2pt thay cho 12pt của bản gốc: PRINT nằm giữa DEL và CHECK vốn đều 16.2pt, để nguyên 12pt
  // thì riêng nó trông như bị hụt. Kích thước nút không đổi.
  btnPrint:   { l: 605.99, t: 6.01, w: 60.01, h: 54, f: 16.2 },
  btnCheck:   { l: 672.01, t: 6.01, w: 59.98, h: 54, f: 16.2 },
  btnClose:   { l: 612, t: 209.99, w: 119.99, h: 42.01, f: 24 },
  btn_out:    { l: 551.99, t: 258.01, w: 90, h: 83.99, f: 36 },
  btn_in:     { l: 648, t: 258.01, w: 84.02, h: 83.99, f: 36 },
  btnCLEAR:   { l: 551.99, t: 348.01, w: 180, h: 57.63, f: 36 },
  btnSAVE:    { l: 551.99, t: 407.99, w: 180, h: 54, f: 36 },
  btnNext:    { l: 551.99, t: 468, w: 180, h: 72, f: 36 },
  txt_COLOR:  { l: 11.99, t: 450, w: 90, h: 25.2, f: 16.2 },
  txt_MACHINE:{ l: 108, t: 450, w: 47.99, h: 25.2, f: 12 },
  txt_CODE:   { l: 11.99, t: 479.99, w: 90, h: 25.8, f: 16.2 },
  txt_LV:     { l: 108, t: 479.99, w: 47.99, h: 25.2, f: 16.2 },
  delta:      { l: 162, t: 450, w: 384.01, h: 93, f: 80.2 },
  rawline:    { l: 11.99, t: 511.51, w: 144, h: 29.99, f: 15.8 },
};

/** pt -> px, gói thành style tuyệt đối. Cỡ chữ cũng theo pt nên phóng to y hệt bản gốc. */
function box(b: Box): Record<string, string> {
  const s: Record<string, string> = {
    left: `${b.l * PX}px`,
    top: `${b.t * PX}px`,
    width: `${b.w * PX}px`,
    height: `${b.h * PX}px`,
  };
  if (b.f) s.fontSize = `${b.f * PX}px`;
  return s;
}

/*
 * Cỡ chữ trong lưới: bản gốc để CẢ BỐN cột 36pt, mà ô RACK chỉ rộng 48pt — Arial Narrow 36pt vừa
 * đúng 2 chữ số, nên mã rack 3 ký tự bị CẮT MẤT ngay trên form thật. Cột DYE cũng cắt khi mã dài.
 * Cỡ chữ dưới đây chọn theo bề rộng thật của từng ô để giá trị dài nhất có thể gặp vẫn đọc trọn:
 *   RACK 24pt   -> 4 ký tự trong 64px (lề 2px mỗi bên, xem `.vv-text.rack`)
 *   DYE 30pt    -> 12 ký tự trong 248px
 *   WEIGHT 30pt -> "1234.75" trong 176px
 *   PROCESS 34pt-> giữ to nhất, đây là con số mang tín hiệu ĐẠT/KHÔNG ĐẠT
 */
const rowTop = (i: number) => ROW_TOP_0 + i * ROW_STEP;
const rackBox = (i: number): Box => ({ l: 12.02, t: rowTop(i), w: 47.99, h: 44.39, f: 24 });
const dyeBox = (i: number): Box => ({ l: 65.99, t: rowTop(i) + ROW_OFFSET, w: 186.01, h: 44.39, f: 30 });
const weightBox = (i: number): Box => ({ l: 257.98, t: rowTop(i) + ROW_OFFSET, w: 132.01, h: 44.39, f: 30 });
const procBoxAt = (i: number): Box => ({ l: 396, t: rowTop(i) + ROW_OFFSET, w: 150.01, h: 44.39, f: 34 });
const rowNoBox = (i: number): Box => ({ l: 6.01, t: rowTop(i) + 12, w: 6.01, h: 12, f: 7.8 });

/* ===== Thu/phóng khung cho vừa MỘT màn hình, không phải cuộn ===== */
// Hộp thoại hỏi/báo vẽ TRONG TRANG. Dùng chung với /weighing-station-v2 — hai màn đều chạy F11
// cả ca, mà hộp thoại gốc của trình duyệt đá màn hình ra khỏi toàn màn hình. Xem useHopThoai.ts.
const { thoai, hoiXacNhan, baoTin, dongThoai } = useHopThoai();

const rootRef = ref<HTMLElement | null>(null);
const wrapRef = ref<HTMLElement | null>(null);
const scale = ref(1);
const rootH = ref<number | null>(null);
let ro: ResizeObserver | null = null;

/** Lề của `.stage-wrap` (padding 8px mỗi bên) — clientWidth/Height đã tính cả padding. */
const WRAP_PAD = 16;

/**
 * Chiều cao màn hình PHẢI đo, không được đặt cứng `100vh`.
 *
 * Route này nằm trong `AppLayout`, tức nội dung đã bị đẩy xuống dưới thanh trên và nằm trong
 * `.content-container` (bản thân nó đã là vùng cuộn, có padding 24px). Đặt `min-height: 100vh`
 * như bản trước là bắt màn hình cao BẰNG CẢ CỬA SỔ rồi cộng thêm thanh trên -> luôn dài hơn màn
 * hình, và thợ phải cuộn mới thấy hết mặt form. Đo từ mép trên thật của chính phần tử này thì
 * đúng trong mọi trường hợp, kể cả khi bật/tắt toàn màn hình hay đổi chiều cao thanh trên.
 */
function fitRoot() {
  const el = rootRef.value;
  if (!el) return;
  const top = el.getBoundingClientRect().top;
  const parent = el.parentElement;
  const padBottom = parent ? parseFloat(getComputedStyle(parent).paddingBottom) || 0 : 0;
  rootH.value = Math.max(320, Math.floor(window.innerHeight - top - padBottom));
}

function fitStage() {
  const el = wrapRef.value;
  if (!el) return;
  const w = el.clientWidth - WRAP_PAD;
  const h = el.clientHeight - WRAP_PAD;
  if (w <= 0 || h <= 0) return;
  // Chặn trên 2.0: toạ độ form là số cố định, phóng quá to thì chỉ còn vài control chiếm cả màn
  // hình chứ không đọc được thêm gì.
  scale.value = Math.max(0.3, Math.min(w / STAGE_W, h / STAGE_H, 2));
}

function fitAll() {
  fitRoot();
  // Đo lại khung SAU khi chiều cao mới đã được áp — nếu không, `fitStage` vẫn dùng chiều cao cũ
  // và mặt form bị thu nhỏ hơn mức cần thiết đúng một nhịp.
  nextTick(fitStage);
}

// Bật/tắt Toàn màn hình: đợi trình duyệt vẽ lại xong khung mới rồi mới đo, không thì đo trúng
// kích thước cũ.
const refit = () => requestAnimationFrame(fitAll);

/* ============================================================================
 * TRẠNG THÁI
 * ========================================================================== */

const router = useRouter();

const {
  liveWeight, grossWeight, isStable, scaleOnline, nguonCucBo, signalLive, signalLost, readingAgeMs, tareBaseline, armed,
  useSimValue, simulatedWeight,
  retare, resetTareForNewSlot, fetchLiveWeight, startPolling, stopPolling,
  // 'LARGE' = chỉ nhận số từ bộ cài Agent "Cân to" (service DFAgentLarge, mã trạm WS-LARGE-*).
  // Bắt buộc phải nói rõ: máy có thể cài SONG SONG cả 2 bộ Agent, backend ghép cặp trình duyệt
  // với Agent theo IP nguồn nên không có tham số này thì màn hình lấy nhầm số của cân nhỏ.
} = useScaleFeed('LARGE');

const activeJob = ref<any | null>(null);
const activeBatch = ref<any | null>(null);
const showChecker = ref(false);
const showQueue = ref(false);
const queueItems = ref<ReturnType<typeof danhSachChoGui>>([]);
const saving = ref(false);
const errorMsg = ref('');

/** Ô đang cân (0-based). -1 = chưa bấm NEXT lần nào (AutoRunning = False trong VBA). */
const currentIndex = ref(-1);
/** Delta đã chốt cho từng ô — giữ ở client tới lúc SAVE, đúng biến p1..p9 của VBA. */
const capturedWeights = ref<Record<number, number>>({});
/** Bì + cân gộp lúc chốt TỪNG ô (mỗi lần NEXT lấy bì mới nên phải lưu riêng theo ô). */
const capturedTare = ref<Record<number, { tare: number | null; gross: number }>>({});
/** Mã RACK gõ tay cho dòng KHÔNG có vật tư trong đơn. */
const manualRacks = ref<Record<number, string>>({});
/** `LastInputBox` của VBA — bàn phím số chỉ gõ vào ô RACK, đúng bản gốc. */
const lastInputIdx = ref<number | null>(null);

const jobItems = computed(() => activeJob.value?.items || []);

/** VBA: NEXT chạy hết 9 DÒNG LƯỚI, không phải hết số vật tư trong đơn. */
const canPressNext = computed(() => currentIndex.value < MAX_RACK_LINES - 1);

const currentTarget = computed<number | null>(() => {
  const item = currentIndex.value >= 0 ? jobItems.value[currentIndex.value] : null;
  const t = item ? Number(item.planned_weight) : NaN;
  return Number.isFinite(t) && t > 0 ? t : null;
});

/* ===== Hiển thị lưới ===== */

function plannedText(i: number): string {
  const v = jobItems.value[i]?.planned_weight;
  if (v === null || v === undefined || v === '') return '';
  return Number(v).toFixed(2);
}

/** Ô đang cân lấy số sống; ô khác lấy giá trị đã chốt (hoặc số đã lưu khi khôi phục job). */
function valueFor(i: number): number | null {
  if (i === currentIndex.value) return liveWeight.value;
  if (capturedWeights.value[i] !== undefined) return capturedWeights.value[i];
  const actual = jobItems.value[i]?.actual_weight;
  return actual !== null && actual !== undefined ? Number(actual) : null;
}

function processText(i: number): string {
  const v = valueFor(i);
  return v === null ? '' : v.toFixed(2);
}

/** Nền ô PROCESS theo dung sai ±1% — port `Mod_UI_processcolor.CheckRange`. */
function processBox(i: number): Record<string, string> {
  const st = box(procBoxAt(i));
  const v = valueFor(i);
  if (v !== null) {
    st.backgroundColor = processBackground(processTone(v, jobItems.value[i]?.planned_weight));
  }
  return st;
}

/** Lớp trạng thái của một dòng lưới: sọc chẵn/lẻ + dòng đang cân. */
function rowCls(i: number): Record<string, boolean> {
  return { alt: i % 2 === 1, cur: currentIndex.value === i };
}

/**
 * Màu ô delta khổng lồ. Bản gốc để nền trắng; ở đây theo ĐÚNG 3 màu dung sai của ô PROCESS vì
 * lúc đang đổ vật tư mắt thợ dán vào con số này, bắt họ liếc xuống bảng mới biết đủ hay chưa là
 * thừa một nhịp. Chưa chốt bì hoặc mất tín hiệu thì để trung tính — tô màu cho một con số không
 * còn phản ánh cái đĩa cân là kiểu nói dối nguy hiểm nhất trên màn hình này.
 */
const deltaTone = computed(() => {
  if (tareBaseline.value === null) return 'none';
  // Ngưỡng BÁO (3s), không phải ngưỡng an toàn (1.5s) — mất màu nhấp nháy giữa lúc đang đổ vật
  // tư còn khó chịu hơn cả dòng cảnh báo. Cổng an toàn thật vẫn nằm ở `signalLive`.
  if (signalLost.value && !useSimValue.value) return 'none';
  return processTone(liveWeight.value, currentTarget.value);
});

/* ============================================================================
 * SEND OVER 6 (btn_Out / btn_In)
 * ========================================================================== */

const rackBatch1 = ref<string[]>(Array(RACK_BATCH_SIZE).fill(''));
const rackBatch2 = ref<string[]>(Array(RACK_BATCH_SIZE).fill(''));
/** `rackLoaded` của VBA: đã gom lô lần nào chưa. OUT chỉ tự gom khi chưa gom. */
const rackLoaded = ref(false);
const sendingRack = ref(false);
const rackMsg = ref('');
const rackOk = ref(true);

const rackBatchText = computed(() => rackBatch1.value.filter(Boolean).join(' · '));

/**
 * Thông báo gộp về MỘT chỗ duy nhất, nằm ngay trong dải trạng thái.
 *
 * Trước đây mỗi thông báo là một thẻ <p> riêng nằm dưới cùng, hiện lên là đẩy `.stage-wrap` hụt
 * đi ~30px, `fitStage` tính lại `scale` và cả mặt form co lại rồi phình ra mỗi lần có/hết thông
 * báo — thợ đang nhìn số cân thì màn hình nhấp nháy đổi cỡ. Đưa vào dải trạng thái (chiều cao
 * CỐ ĐỊNH) thì mặt form không bao giờ đổi cỡ nữa.
 *
 * Thứ tự ưu tiên giữ đúng như chuỗi v-if/v-else-if cũ.
 */
const statusMsg = computed<{ text: string; bad: boolean } | null>(() => {
  if (errorMsg.value) return { text: errorMsg.value, bad: true };
  if (rackMsg.value) return { text: rackMsg.value, bad: !rackOk.value };
  if (signalLost.value && !useSimValue.value && scaleOnline.value)
    return {
      // Kèm tuổi số đọc để phân biệt "Agent chết hẳn" (số tăng vô hạn) với "Agent sống nhưng số
      // về chậm" (dừng quanh vài giây) — hai thứ này sửa ở hai chỗ khác nhau.
      text: '⚠ MẤT TÍN HIỆU CÂN — kiểm tra Agent / dây cân'
        + (readingAgeMs.value !== null ? ` (số cân cũ ${(readingAgeMs.value / 1000).toFixed(1)}s)` : ''),
      bad: true,
    };
  if (!useSimValue.value && !scaleOnline.value)
    return { text: '⚠ MẤT KẾT NỐI MÁY CHỦ — số cân không cập nhật. Số đã cân KHÔNG mất.', bad: true };
  return null;
});

/** Mã rack đang hiện ở dòng `i` — dòng có vật tư lấy từ đơn, dòng trống lấy ô gõ tay. */
function rackAt(i: number): string {
  const item = jobItems.value[i];
  return String((item ? item.rack_code : manualRacks.value[i]) || '');
}

/**
 * Mod_sendRackauto.BuildRackBatch — duyệt 9 ô RACK, BỎ ô rỗng và ô "0", dồn liên tục: 6 mã đầu
 * vào lô 1, phần dư vào lô 2. Bỏ "0" là quy ước của bản gốc (`If v <> "" And v <> "0"`), không
 * phải lọc số: "0" nghĩa là dòng không dùng rack nào.
 */
function buildRackBatch() {
  const tmp: string[] = [];
  for (let i = 0; i < MAX_RACK_LINES; i++) {
    const v = rackAt(i).trim();
    if (v !== '' && v !== '0') tmp.push(v);
  }
  const b1 = Array(RACK_BATCH_SIZE).fill('');
  const b2 = Array(RACK_BATCH_SIZE).fill('');
  tmp.forEach((v, i) => {
    if (i < RACK_BATCH_SIZE) b1[i] = v;
    else if (i - RACK_BATCH_SIZE < RACK_BATCH_SIZE) b2[i - RACK_BATCH_SIZE] = v;
  });
  rackBatch1.value = b1;
  rackBatch2.value = b2;
  rackLoaded.value = true;
}

/**
 * btn_Out_Click — gửi lô 1 sang hệ pha màu.
 *
 * VBA có 2 nhánh: ô WEIGHT1 trống thì bắn thẳng txt_RACK1..6 (chưa gom lô), ngược lại gom lô rồi
 * bắn `rackBatch1`. Ở đây gộp về MỘT nhánh: luôn gom lô trước nếu chưa gom. Hai nhánh của bản gốc
 * chỉ khác nhau ở chỗ có lọc ô rỗng/"0" hay không, mà nhánh không lọc sẽ dán cả ô trống sang ứng
 * dụng đích — không có lý do nghiệp vụ nào cần giữ lại hành vi đó.
 */
async function onOut() {
  if (!rackLoaded.value) buildRackBatch();
  if (rackBatch1.value.every((r) => !r)) {
    rackOk.value = false;
    rackMsg.value = 'Không có mã rack nào để gửi — nhập mã ở cột RACK hoặc quét đơn trước.';
    return;
  }
  sendingRack.value = true;
  // Chờ tới khi Agent ack (tối đa ~12 giây) nên phải nói rõ đang chờ — nút xám mà không có chữ
  // thì thợ tưởng máy treo và bấm lại.
  rackOk.value = true;
  rackMsg.value = 'Đang gửi mã rack sang hệ pha màu — chờ Agent xác nhận…';
  const res = await guiRackSangAgent('OUT', rackBatch1.value, currentWorkstation.value?.code || 'ws');
  sendingRack.value = false;
  rackOk.value = res.ok;
  rackMsg.value = res.message;
  if (res.ok) scannerService.playBeep(1500, 120);
}

/**
 * btn_In_Click — báo hệ pha màu nhận hàng, rồi DỒN lô 2 lên thành lô 1 (VBA:
 * `rackBatch1(i) = rackBatch2(i) : rackBatch2(i) = ""`), để lượt OUT kế bắn phần còn lại.
 */
async function onIn() {
  sendingRack.value = true;
  rackOk.value = true;
  rackMsg.value = 'Đang gửi lệnh NHẬN (IN) — chờ Agent xác nhận…';
  const res = await guiRackSangAgent('IN', rackBatch1.value, currentWorkstation.value?.code || 'ws');
  sendingRack.value = false;
  rackOk.value = res.ok;
  rackMsg.value = res.message;

  // Dồn lô DÙ gửi hỏng hay không — đúng bản gốc: btn_In_Click dồn vô điều kiện, việc gửi và việc
  // dồn lô là hai chuyện tách rời. Gửi hỏng thì thợ bấm OUT lại, không mất thứ tự rack.
  rackBatch1.value = [...rackBatch2.value];
  rackBatch2.value = Array(RACK_BATCH_SIZE).fill('');
  if (res.ok) scannerService.playBeep(1500, 120);
}

/** Đường thoát khi agent chưa sẵn sàng: chép lô 1 ra clipboard để dán tay sang app đích. */
async function onCopyRacks() {
  const text = rackBatch1.value.filter(Boolean).join('\n');
  if (!text) {
    rackOk.value = false;
    rackMsg.value = 'Lô 1 đang trống, không có gì để chép.';
    return;
  }
  try {
    await navigator.clipboard.writeText(text);
    rackOk.value = true;
    rackMsg.value = 'Đã chép lô 1 ra clipboard — dán sang hệ pha màu bằng Ctrl+V.';
  } catch {
    rackOk.value = false;
    rackMsg.value = 'Trình duyệt không cho chép clipboard — chép tay từ dải thông tin bên dưới.';
  }
}

function resetRackBatches() {
  rackBatch1.value = Array(RACK_BATCH_SIZE).fill('');
  rackBatch2.value = Array(RACK_BATCH_SIZE).fill('');
  rackLoaded.value = false;
  rackMsg.value = '';
  rackOk.value = true;
}

/* ============================================================================
 * BÀN PHÍM SỐ (NumClick / NumDel)
 * ========================================================================== */

function numClick(d: string) {
  const i = lastInputIdx.value;
  if (i === null) return;
  onUpdateRack(i, rackAt(i) + d);
}

function numDel() {
  const i = lastInputIdx.value;
  if (i === null) return;
  const cur = rackAt(i);
  if (cur.length > 0) onUpdateRack(i, cur.slice(0, -1));
}

/* ============================================================================
 * NHỊP ĐỌC CÂN
 * ========================================================================== */

const POLL_MS_WEIGHING = 200;
const POLL_MS_IDLE = 1000;

/**
 * Nhịp khi đọc thẳng Agent "Cân to" trên máy này (ADR-013) — cuộc gọi không rời khỏi máy nên
 * dày gấp 3 lần mà không tốn gì của máy chủ. Xem ghi chú đầy đủ ở WeighingStationV2.vue.
 */
const POLL_MS_CUC_BO = 60;

const dangCan = computed(() => activeJob.value !== null || currentIndex.value >= 0);

function capNhatNhipPoll() {
  if (document.hidden) {
    stopPolling();
    return;
  }
  if (!dangCan.value) {
    startPolling(POLL_MS_IDLE);
    return;
  }
  startPolling(nguonCucBo.value ? POLL_MS_CUC_BO : POLL_MS_WEIGHING);
}

watch(dangCan, capNhatNhipPoll);
watch(nguonCucBo, capNhatNhipPoll);

function onVisibilityChange() {
  if (!document.hidden) fetchLiveWeight();
  capNhatNhipPoll();
}

/* ============================================================================
 * PHIÊN LÀM VIỆC CỦA RIÊNG MÁY NÀY
 *
 * Khoá localStorage RIÊNG của trạm cân to — KHÔNG dùng chung `df_ws2_session_v1` với màn cân
 * nhỏ. Hai màn có thể mở trên cùng một máy lúc kiểm thử; dùng chung khoá thì mở màn này sẽ nuốt
 * mất mẻ đang cân dở của màn kia.
 * ========================================================================== */

const SESSION_KEY = 'df_wslarge_session_v1';

function saveSession() {
  if (!currentWorkstation.value) return;
  const coGiDoDangDeGiu =
    !!activeJob.value || currentIndex.value >= 0 || Object.keys(capturedWeights.value).length > 0;
  if (!coGiDoDangDeGiu) return;

  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify({
      workstationId: currentWorkstation.value.id,
      canTay: !activeJob.value,
      manualRacks: manualRacks.value,
      jobId: activeJob.value?.id ?? null,
      rawQr: activeJob.value?.raw_qr ?? null,
      capturedWeights: capturedWeights.value,
      capturedTare: capturedTare.value,
      currentIndex: currentIndex.value,
      tareBaseline: tareBaseline.value,
      grossAtSave: grossWeight.value,
      useSim: useSimValue.value,
      simWeight: useSimValue.value ? simulatedWeight.value : null,
      // Lô rack đang dở cũng phải sống qua F5: đã bấm OUT lô 1 mà tải lại trang xong lô 2 biến
      // mất thì thợ không còn cách nào biết phần rack còn lại là những mã nào.
      rackBatch1: rackBatch1.value,
      rackBatch2: rackBatch2.value,
      rackLoaded: rackLoaded.value,
    }));
  } catch {
    // Hết dung lượng / chế độ riêng tư: mất khả năng khôi phục thôi, không làm hỏng luồng cân.
  }
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

function khoiPhucGiaLap(saved: any) {
  if (!saved?.useSim) return;
  useSimValue.value = true;
  if (typeof saved.simWeight === 'number') simulatedWeight.value = saved.simWeight;
}

async function restoreSession() {
  if (!currentWorkstation.value) return;

  let saved: any = null;
  try {
    saved = JSON.parse(localStorage.getItem(SESSION_KEY) || 'null');
  } catch {
    clearSession();
    return;
  }
  if (!saved?.jobId && !saved?.rawQr && !saved?.canTay) return;
  if (saved.workstationId !== currentWorkstation.value.id) {
    clearSession();
    return;
  }

  const napChung = () => {
    capturedWeights.value = saved.capturedWeights || {};
    capturedTare.value = saved.capturedTare || {};
    manualRacks.value = saved.manualRacks || {};
    khoiPhucGiaLap(saved);
    if (Array.isArray(saved.rackBatch1)) rackBatch1.value = saved.rackBatch1;
    if (Array.isArray(saved.rackBatch2)) rackBatch2.value = saved.rackBatch2;
    rackLoaded.value = Boolean(saved.rackLoaded);
    resetTareForNewSlot();
    currentIndex.value = -1;
    if (saved.currentIndex >= 0 && typeof saved.tareBaseline === 'number' && typeof saved.grossAtSave === 'number') {
      pendingResume.value = { index: saved.currentIndex, tare: saved.tareBaseline, gross: saved.grossAtSave };
    }
  };

  // Mẻ CÂN TAY: dưới DB chưa hề tồn tại, không hỏi server câu nào.
  if (saved.canTay) {
    napChung();
    return;
  }

  // Mẻ đọc từ QR: dựng lại HOÀN TOÀN tại client.
  if (saved.rawQr) {
    const parsed = parseDyeQr(saved.rawQr);
    if (parsed.color === '' || parsed.code === '' || parsed.rack_lines.length === 0) {
      clearSession(); // chuỗi hỏng -> mở trắng còn hơn dựng bảng sai
      return;
    }
    buildLocalJob(saved.rawQr, parsed);
    napChung();
    return;
  }

  // Mẻ mở bằng token "DF:ORDER:<uuid>": vòng cân có thật dưới DB.
  try {
    const res = await axios.get('/api/weighing-jobs/active', {
      params: { workstation_id: currentWorkstation.value.id },
    });
    const job = res.data?.data?.job;
    if (!job || job.id !== saved.jobId) {
      clearSession();
      return;
    }
    activeJob.value = job;
    activeBatch.value = res.data.data.batch;
    napChung();
  } catch {
    clearSession();
  }
}

/** Ô đang cân dở chờ nối lại sau F5 — chỉ nối khi xác nhận đĩa cân chưa bị đụng vào. */
const pendingResume = ref<{ index: number; tare: number; gross: number } | null>(null);

/** Sai lệch cân gộp còn coi là "đĩa chưa bị đụng vào" (gram). */
const RESUME_GROSS_TOLERANCE = 0.5;

watch([isStable, grossWeight], () => {
  const p = pendingResume.value;
  if (!p || !isStable.value || !signalLive.value) return;
  pendingResume.value = null;

  if (Math.abs(grossWeight.value - p.gross) <= RESUME_GROSS_TOLERANCE) {
    currentIndex.value = p.index;
    tareBaseline.value = p.tare;
    armed.value = true;
  } else {
    errorMsg.value =
      `Đĩa cân đã thay đổi trong lúc tải lại trang (${p.gross.toFixed(2)} → ${grossWeight.value.toFixed(2)}). `
      + 'Các ô đã cân vẫn còn nguyên — bấm NEXT để cân tiếp ô kế và lấy bì mới.';
  }
});

// Ghi mốc cân gộp mỗi khi cân đứng ở giá trị mới — mốc để lần F5 sau đối chiếu.
let lastPersistedGross: number | null = null;
watch([isStable, grossWeight], () => {
  if (!isStable.value || !signalLive.value) return;
  if (lastPersistedGross !== null && Math.abs(grossWeight.value - lastPersistedGross) < 0.01) return;
  lastPersistedGross = grossWeight.value;
  saveSession();
});

/* ============================================================================
 * QUÉT ĐƠN (txt_color_AfterUpdate)
 * ========================================================================== */

const scanInputRef = ref<HTMLInputElement | null>(null);
const scanning = ref(false);

function onScanEnter() {
  const token = (scanInputRef.value?.value ?? '').trim();
  if (!token || scanning.value) return;
  handleBarcodeScan(token);
}

function cancelAbandonedJob(jobId: string) {
  axios.post(`/api/weighing-jobs/${jobId}/cancel`).catch(() => {});
}

const handleBarcodeScan = async (token: string) => {
  if (!currentWorkstation.value) return;
  scanning.value = true;
  scannerService.playBeep(1200, 80); // bíp NGAY khi nhận mã, không đợi server

  // QUÉT KHÔNG CHẠM MẠNG: chuỗi QR đã chứa đủ rack/dye/weight của cả mẻ. Cả mẻ chỉ đi mạng đúng
  // MỘT lần lúc bấm SAVE — cũng chính là cách VBA gốc làm (btnSave_Click mới INSERT).
  if (token.startsWith('#')) {
    const parsed = parseDyeQr(token);
    if (parsed.color === '' || parsed.code === '' || parsed.rack_lines.length === 0) {
      scannerService.playBeep(600, 400);
      scanning.value = false;
      await baoTin('Không đọc được mã QR này — kiểm tra lại đầu đọc hoặc mã tem.');
      nextTick(() => scanInputRef.value?.focus());
      return;
    }
    applyLocalJob(token, parsed);
    scanning.value = false;
    nextTick(() => scanInputRef.value?.focus());
    return;
  }

  // Token "DF:ORDER:<uuid>" không mang dữ liệu dòng nào — buộc phải hỏi server.
  try {
    const res = await axios.post('/api/scanner/scan', {
      qr_token: token,
      workstation_code: currentWorkstation.value.code,
    });
    if (res.data?.status === 'SUCCESS') {
      const data = res.data.data;
      if (data.empty) {
        scannerService.playBeep(800, 300);
        await baoTin(res.data.message);
        return;
      }
      if (activeJob.value?.id && activeJob.value.id !== data.job.id) {
        cancelAbandonedJob(activeJob.value.id);
      }
      activeJob.value = data.job;
      activeBatch.value = data.batch;
      currentIndex.value = -1;
      capturedWeights.value = {};
      capturedTare.value = {};
      resetRackBatches();
      buildRackBatch();
      resetTareForNewSlot();
      saveSession();
    }
  } catch (err: any) {
    scannerService.playBeep(600, 400);
    await baoTin(err.response?.data?.message || 'Không thể mở lệnh sản xuất này.');
  } finally {
    scanning.value = false;
    nextTick(() => scanInputRef.value?.focus());
  }
};

/**
 * Dựng khung mẻ cân từ chuỗi QR. `id: null` = dưới DB chưa có gì; cả mẻ chỉ ghi xuống lúc SAVE.
 * Dung sai ±1% mục tiêu — đúng công thức server dùng cho nhánh cân tự do
 * (ScannerController::TOLERANCE_RATIO); lệch là màu lúc cân khác kết quả server chốt lúc lưu.
 */
function buildLocalJob(rawQr: string, parsed: ParsedDyeQr) {
  activeJob.value = {
    id: null,
    raw_qr: rawQr,
    items: parsed.rack_lines.map((line, i) => {
      const target = Number(line.weight) || 0;
      return {
        id: null,
        sequence_no: i + 1,
        rack_code: line.rack,
        material_code: line.dye,
        material: { code: line.dye, name: line.dye },
        planned_weight: target,
        tolerance_minus: target * 0.01,
        tolerance_plus: target * 0.01,
        status: 'PENDING',
      };
    }),
  };
  activeBatch.value = {
    color: parsed.color,
    product_code: parsed.code,
    level_code: parsed.level,
    machine: { code: parsed.machine },
  };
}

function applyLocalJob(rawQr: string, parsed: ParsedDyeQr) {
  if (activeJob.value?.id) cancelAbandonedJob(activeJob.value.id);

  buildLocalJob(rawQr, parsed);
  currentIndex.value = -1;
  capturedWeights.value = {};
  capturedTare.value = {};
  manualRacks.value = {};
  resetTareForNewSlot();

  // Gom lô rack NGAY khi nạp đơn: mã rack đã có sẵn trong QR nên bấm OUT được luôn.
  buildRackBatch();
  rackMsg.value = '';
  rackOk.value = true;
  saveSession();
}

/* ============================================================================
 * THAO TÁC CÂN
 * ========================================================================== */

function captureCurrentSlot() {
  if (currentIndex.value < 0 || tareBaseline.value === null) return;
  capturedWeights.value = { ...capturedWeights.value, [currentIndex.value]: liveWeight.value };
  capturedTare.value = {
    ...capturedTare.value,
    [currentIndex.value]: { tare: tareBaseline.value, gross: grossWeight.value },
  };
  saveSession(); // ghi ngay từng ô — mất điện giữa mẻ chỉ mất đúng ô đang cân dở
}

/** btnNext_Click */
function onNext() {
  errorMsg.value = '';
  if (currentIndex.value === -1) {
    const firstPending = jobItems.value.findIndex(
      (it: any, idx: number) => it.status !== 'COMPLETED' && capturedWeights.value[idx] === undefined
    );
    currentIndex.value = firstPending >= 0
      ? firstPending
      : Math.min(jobItems.value.length, MAX_RACK_LINES - 1);
    resetTareForNewSlot();
    armed.value = true; // AutoFlow_Start: lần đọc ổn định đầu tiên tự thành bì
    pendingResume.value = null;
    saveSession();
    return;
  }

  captureCurrentSlot();
  if (currentIndex.value < MAX_RACK_LINES - 1) {
    currentIndex.value += 1;
    resetTareForNewSlot(); // Delta_Begin
    armed.value = true;
  }
  saveSession();
}

function onSelectRow(idx: number) {
  if (jobItems.value[idx]?.status === 'COMPLETED') return;
  captureCurrentSlot();
  currentIndex.value = idx;
  resetTareForNewSlot();
  armed.value = true;
  pendingResume.value = null;
  saveSession();
}

function onUpdateRack(idx: number, value: string) {
  const item = jobItems.value[idx];
  if (item) {
    item.rack_code = value;
  } else {
    manualRacks.value = { ...manualRacks.value, [idx]: value };
  }
  // Lô rack phải bám theo ô RACK đang gõ, nếu không nút OUT gửi mã của lần gom trước. Chỉ gom lại
  // khi ĐÃ gom lần nào (rackLoaded), để lần đầu vẫn giữ đúng thứ tự "gõ xong mới gom".
  if (rackLoaded.value) buildRackBatch();
  saveSession();
}

/** btnClear_Click — xoá SẠCH form, đúng `For Each c In Me.Controls ... c.text = ""` của VBA. */
async function onClear(skipConfirm = false, alreadySaved = false) {
  const hasUnsaved = Object.keys(capturedWeights.value).length > 0;
  const hasSomething = hasUnsaved || activeJob.value !== null;
  if (hasSomething && !skipConfirm) {
    const ok = await hoiXacNhan(
      hasUnsaved
        ? 'CLEAR sẽ xoá sạch màn hình, kể cả số đã cân nhưng CHƯA bấm SAVE.\n\nVẫn xoá?'
        : 'CLEAR sẽ xoá đơn đang mở khỏi màn hình. Quét lại mã QR để cân từ đầu.\n\nVẫn xoá?'
    );
    if (!ok) return;
  }

  if (!alreadySaved && activeJob.value?.id) cancelAbandonedJob(activeJob.value.id);

  capturedWeights.value = {};
  capturedTare.value = {};
  manualRacks.value = {};
  currentIndex.value = -1;
  lastInputIdx.value = null;
  errorMsg.value = '';
  activeJob.value = null;
  activeBatch.value = null;
  armed.value = false; // AutoRunning = False
  pendingResume.value = null;
  resetTareForNewSlot();
  resetRackBatches(); // btnClear_Click của VBA cũng xoá rackBatch1/2 và hạ rackLoaded
  clearSession();
  nextTick(() => {
    if (scanInputRef.value) scanInputRef.value.value = '';
    scanInputRef.value?.focus();
  });
}

/** Các dòng sẽ lưu cho mẻ CÂN TAY (không quét đơn nào). */
function dongCanTayDeLuu(): any[] {
  const daChot = Object.keys(capturedWeights.value).map(Number).sort((a, b) => a - b);

  if (daChot.length > 0) {
    return daChot.map((idx) => {
      const meta = capturedTare.value[idx];
      return {
        sequence_no: idx + 1,
        weight: capturedWeights.value[idx] ?? null,
        rack_code: manualRacks.value[idx] || null,
        tare_weight: meta?.tare ?? null,
        gross_weight: meta?.gross ?? null,
      };
    });
  }

  // Chưa bấm NEXT lần nào: dùng màn hình như cái cân thường. Chưa chốt bì nên không có số net —
  // thứ duy nhất có thật là cân gộp, ghi đúng nó và để tare_weight = null.
  if (!signalLive.value || !isStable.value) return [];
  if (grossWeight.value === 0) return [];

  return [{
    sequence_no: 1,
    weight: grossWeight.value,
    rack_code: manualRacks.value[0] || null,
    tare_weight: null,
    gross_weight: grossWeight.value,
  }];
}

/** btnSave_Click — gửi mọi ô có số về server 1 lệnh, in phiếu rồi xoá màn hình. */
async function onSave() {
  errorMsg.value = '';
  captureCurrentSlot();

  const localQr: string | null = activeJob.value?.raw_qr ?? null;
  const canTay = !activeJob.value;

  const rows = canTay
    ? dongCanTayDeLuu()
    : jobItems.value
        .map((item: any, idx: number) => {
          if (!item || item.status === 'COMPLETED') return null;
          if (item.planned_weight === null || item.planned_weight === undefined || item.planned_weight === '') return null;
          const meta = capturedTare.value[idx];
          return {
            ...(localQr ? { sequence_no: item.sequence_no ?? idx + 1 } : { item_id: item.id }),
            weight: capturedWeights.value[idx] ?? null,
            rack_code: item.rack_code || null,
            tare_weight: meta?.tare ?? null,
            gross_weight: meta?.gross ?? null,
          };
        })
        .filter(Boolean);

  if (rows.length === 0) {
    if (!canTay) {
      errorMsg.value = 'Không có dòng nào để lưu.';
    } else if (!signalLive.value) {
      errorMsg.value = 'Mất tín hiệu cân — số đang hiện là số cũ, không lưu được. Kiểm tra Agent / dây cân.';
    } else if (!isStable.value) {
      errorMsg.value = 'Số cân chưa đứng yên — chờ cân ổn định rồi bấm SAVE lại.';
    } else {
      errorMsg.value = 'Cân đang rỗng (0.00) — đặt vật tư lên cân rồi bấm SAVE.';
    }
    return;
  }

  // Mở cửa sổ in NGAY TẠI ĐÂY, trước mọi `await` — còn trong "user activation" của cú click SAVE
  // nên trình duyệt không chặn. Mở sau khi lưu xong là bị chặn.
  //
  // PHẢI mở TRƯỚC hộp xác nhận, không phải sau như trước đây. `window.confirm` cũ là hàm ĐỒNG BỘ
  // nên mở sau nó vẫn còn user activation; hộp thoại vẽ trong trang thì bắt buộc `await`, mà mở
  // cửa sổ sau một `await` là đúng trường hợp Chrome/Edge chặn. Thợ bấm "KHÔNG" thì đóng lại.
  const printWin = window.open('', '_blank', 'width=780,height=980');

  const unweighed = rows.filter((r: any) => r.weight === null).length;
  if (unweighed > 0) {
    const ok = await hoiXacNhan(
      `Còn ${unweighed} dòng CHƯA CÂN. Lưu bây giờ sẽ chốt các dòng đó là KHÔNG ĐẠT và không cân lại được nữa.\n\nVẫn lưu?`
    );
    if (!ok) {
      printWin?.close();
      return;
    }
  }

  saving.value = true;

  if (localQr || canTay) {
    const mocGioIn = nowSlipTimestamp();
    const phieuCucBo = canTay ? dungPhieuCanTay(rows, mocGioIn) : dungPhieuCucBo(mocGioIn);

    const item = xepHang(
      {
        ...(canTay ? { manual: true } : { raw_qr: localQr }),
        workstation_code: currentWorkstation.value?.code,
        scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
        // Mọi số trong `rows` đều đứng yên theo cấu tạo (chỉ `liveWeight` đã qua lọc ổn định mới
        // được chốt), nên gửi cứng true. Gửi cờ SỐNG của lần đọc hiện tại là dựng bẫy: mất mạng
        // đúng lúc SAVE thì mẻ vào hàng đợi với stable=false và bị 422 mãi mãi.
        stable: true,
        printed_at: mocGioIn,
        rows,
      },
      canTay
        ? `Cân tay · ${rows.length} dòng`
        : `${activeBatch.value?.color ?? ''} · ${activeBatch.value?.product_code ?? ''}`,
      currentWorkstation.value?.code || 'ws'
    );

    // IN NGAY, TRƯỚC khi chạm mạng — mọi dữ liệu cần in đã nằm trên màn hình.
    if (printWin) await printSlipHtml(phieuCucBo, printWin);

    let daLenServer = false;
    let loiNghiepVu: string | null = null;

    try {
      await axios.post('/api/scanner/weigh-from-qr', {
        ...item.payload,
        idempotency_key: item.idempotency_key,
      });
      danhDauDaGui(item.idempotency_key);
      daLenServer = true;
    } catch (err: any) {
      const status = err?.response?.status;
      if (status && status >= 400 && status < 500 && status !== 408 && status !== 429) {
        const loi: string = err?.response?.data?.message || `Máy chủ từ chối mẻ này (HTTP ${status}).`;
        loiNghiepVu = loi;
        danhDauKet(item.idempotency_key, loi);
      }
      // Mất mạng / 5xx: để yên trong hàng đợi, tự đẩy sau.
    }

    saving.value = false;
    scannerService.playBeep(1800, 150);
    clearSession();
    await onClear(true, true);

    if (!printWin) {
      errorMsg.value = 'Trình duyệt đã chặn cửa sổ in nên CHƯA IN được phiếu. Cho phép popup cho trang này để lần sau in được.';
    } else if (loiNghiepVu) {
      errorMsg.value = `Máy chủ TỪ CHỐI mẻ này: ${loiNghiepVu} Phiếu đã in, mẻ vẫn nằm trong hàng đợi.`;
    } else if (!daLenServer) {
      errorMsg.value =
        'Chưa gửi được lên máy chủ — mẻ đang nằm trong HÀNG ĐỢI của máy này và sẽ tự gửi khi có mạng. '
        + 'Phiếu đã in. Cứ quét mẻ tiếp theo bình thường, ĐỪNG xoá dữ liệu trình duyệt.';
    }
    return;
  }

  // ===== Mẻ mở bằng token "DF:ORDER:<uuid>" — vòng cân đã có sẵn dưới DB =====
  try {
    const saveRes = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/weigh-batch`, {
      rows,
      scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
      stable: isStable.value,
      slip_workstation_code: currentWorkstation.value?.code || undefined,
    });
    scannerService.playBeep(1800, 150);
    clearSession();

    if (!printWin) {
      errorMsg.value = 'ĐÃ LƯU XONG mẻ cân, nhưng trình duyệt chặn cửa sổ in. Cho phép popup rồi bấm PRINT để in phiếu.';
      return;
    }
    const slipPayload = saveRes.data?.data?.slip?.label_payload;
    if (slipPayload) {
      await printSlipHtml(slipPayload, printWin);
    } else {
      await printSlip(printWin);
    }
    await onClear(true, true);
  } catch (err: any) {
    printWin?.close();
    // KHÔNG xoá capturedWeights khi lỗi — số vừa cân phải còn nguyên để bấm SAVE lại.
    errorMsg.value = err.response?.data?.message || 'Không lưu được mẻ cân. Số đã cân vẫn còn trên màn hình — thử SAVE lại.';
  } finally {
    saving.value = false;
  }
}

function dungPhieuCanTay(rows: any[], mocGioIn?: string): string {
  return buildSlipHtml(
    { color: '', product_code: '', machine_code: 'N/A', level_code: '', printed_at: mocGioIn },
    rows.map((r: any) => ({
      sequence_no: r.sequence_no,
      rack_code: r.rack_code,
      material_code: MANUAL_MATERIAL_CODE,
      planned_weight: 0,
      actual_weight: r.weight,
      process_status: 'MANUAL',
    }))
  );
}

function dungPhieuCucBo(mocGioIn?: string): string {
  const items = jobItems.value
    .map((item: any, idx: number) => {
      if (!item) return null;
      if (item.planned_weight === null || item.planned_weight === undefined || item.planned_weight === '') return null;
      const actual = capturedWeights.value[idx] ?? null;
      return {
        sequence_no: item.sequence_no ?? idx + 1,
        rack_code: item.rack_code,
        material_code: item.material_code,
        planned_weight: item.planned_weight,
        actual_weight: actual,
        process_status: processStatus({
          planned_weight: item.planned_weight,
          tolerance_minus: item.tolerance_minus,
          tolerance_plus: item.tolerance_plus,
          actual_weight: actual,
        }),
      };
    })
    .filter(Boolean) as any[];

  return buildSlipHtml(
    {
      color: activeBatch.value?.color ?? '',
      product_code: activeBatch.value?.product_code ?? '',
      machine_code: activeBatch.value?.machine?.code ?? 'N/A',
      level_code: activeBatch.value?.level_code ?? '',
      printed_at: mocGioIn,
    },
    items
  );
}

/**
 * 9 dòng phiếu dựng ĐÚNG NHƯ ĐANG HIỆN trên lưới — bản dựng lại `For i = 1 To 9` của
 * `btnPrint_Click`, vốn đổ thẳng `txt_rack/dye/weight/process` của cả 9 ô ra sheet bất kể ô đó có
 * gì hay không. Bản song sinh nằm ở `WeighingStationV2.vue`, xem ghi chú dài ở đó.
 */
function dongPhieuTheoLuoi() {
  return Array.from({ length: MAX_RACK_LINES }, (_, idx) => {
    const item: any = jobItems.value[idx];
    const actual = capturedWeights.value[idx] ?? null;

    if (!item) {
      return {
        sequence_no: idx + 1,
        rack_code: manualRacks.value[idx] || '',
        material_code: actual !== null ? MANUAL_MATERIAL_CODE : '',
        planned_weight: null,
        actual_weight: actual,
        process_status: actual !== null ? 'MANUAL' : '',
      };
    }

    return {
      sequence_no: item.sequence_no ?? idx + 1,
      rack_code: item.rack_code || '',
      material_code: item.material_code,
      planned_weight: item.planned_weight ?? null,
      actual_weight: actual,
      process_status: processStatus({
        planned_weight: item.planned_weight,
        tolerance_minus: item.tolerance_minus,
        tolerance_plus: item.tolerance_plus,
        actual_weight: actual,
      }),
    };
  });
}

/** Phiếu của nút PRINT: cả 9 dòng đang hiện + phần đầu lấy từ đơn đang mở (rỗng nếu cân tay). */
function dungPhieuTuLuoi(): string {
  return buildSlipHtml(
    {
      color: activeBatch.value?.color ?? '',
      product_code: activeBatch.value?.product_code ?? '',
      machine_code: activeBatch.value?.machine?.code ?? 'N/A',
      level_code: activeBatch.value?.level_code ?? '',
    },
    dongPhieuTheoLuoi()
  );
}

/**
 * btnPrint_Click. `preOpened` bắt buộc khi in nằm sau một `await` (luồng SAVE) — xem onSave.
 *
 * In VÔ ĐIỀU KIỆN đúng bản gốc: `btnPrint_Click` của workbook cân to (giống hệt workbook cân nhỏ)
 * đổ thẳng 9 ô `txt_rack/dye/weight/process` ra sheet, không hỏi trạm và không đòi phải có số cân
 * — form trắng vẫn ra một tờ 9 dòng trống. Mọi cửa chặn thêm đều biến nút PRINT thành nút im lặng.
 */
const printSlip = async (preOpened?: Window | null) => {
  const win = cuaSoThat(preOpened) ?? window.open('', '_blank', 'width=780,height=980');
  if (!win) {
    await baoTin('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }
  win.document.write('<p style="font-family:sans-serif;padding:20px;">Đang xử lý...</p>');

  // Cân tay, mẻ đọc từ QR, và cả form trắng: đều dựng phiếu ngay tại trình duyệt từ 9 dòng đang
  // hiện. Chưa gắn trạm cũng rơi vào đây thay vì thoát im lặng: server cần `workstation_code` để
  // ghi PrintJob, nhưng tờ giấy thì không — dữ liệu để in đã nằm sẵn trên màn hình.
  if (!activeJob.value?.id || !currentWorkstation.value) {
    await printSlipHtml(dungPhieuTuLuoi(), win);
    return;
  }

  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print-slip`, {
      workstation_code: currentWorkstation.value.code,
    });
    await printSlipHtml(res.data?.data?.label_payload || '', win);
  } catch (err: any) {
    win.close();
    await baoTin(err.response?.data?.message || 'Không thể in phiếu cân.');
  }
};

/* ===== HÀNG ĐỢI GỬI MẺ ===== */

watch([showQueue, queueCount, stuckCount, flushing, duongThong], () => {
  if (showQueue.value) queueItems.value = danhSachChoGui();
});

function formatQueueTime(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  });
}

async function onGuiNgay() {
  await dayHangDoi();
  queueItems.value = danhSachChoGui();
}

function onThuLai(key: string) {
  thuLai(key);
  queueItems.value = danhSachChoGui();
  dayHangDoi();
}

function onClose() {
  isFullscreen.value = false;
  router.back();
}

onMounted(() => {
  // 'LARGE': hỏi backend "trạm CÂN TO của máy này là trạm nào?" — máy cài cả 2 bộ Agent có 2
  // trạm cùng lúc, không nói rõ là nhận nhầm trạm cân nhỏ.
  adoptLocalWorkstation('LARGE').then((doi) => {
    if (doi) fetchLiveWeight();
  });
  restoreSession();
  fetchLiveWeight();
  capNhatNhipPoll();
  document.addEventListener('visibilitychange', onVisibilityChange);
  batTuDay();

  fitAll();
  // Đo lại một nhịp nữa sau khi trình duyệt vẽ xong: lúc onMounted chạy, AppLayout có thể chưa
  // dựng xong thanh trên nên `getBoundingClientRect().top` còn là số tạm.
  requestAnimationFrame(fitAll);
  if (typeof ResizeObserver !== 'undefined' && wrapRef.value) {
    // Dải thông tin/dòng lỗi dưới cùng hiện rồi ẩn làm khung cao thấp khác nhau -> đo lại.
    ro = new ResizeObserver(fitStage);
    ro.observe(wrapRef.value);
  }
  window.addEventListener('resize', fitAll);

  // txt_COLOR.SetFocus của UserForm_Activate
  nextTick(() => scanInputRef.value?.focus());
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange);
  window.removeEventListener('resize', fitAll);
  ro?.disconnect();
  tatTuDay();
});
</script>

<style scoped>
/* ============================================================================
 * BỐ CỤC giữ nguyên 100% toạ độ form VBA; chỉ thay CÁCH VẼ.
 *
 * Bỏ giả kiểu Windows 95 (viền outset/inset, xám #f0f0f0, Tahoma) — nó chỉ khiến màn hình trông
 * cũ mà không giúp đọc nhanh hơn. Thay bằng: nền sáng trung tính, ô bo góc có viền mảnh, sọc
 * chẵn/lẻ để mắt bám đúng dòng, chữ số dùng Inter tabular (mọi chữ số rộng bằng nhau nên cột số
 * không nhảy khi giá trị đổi), nút phân màu theo vai trò.
 *
 * BA MÀU TÍN HIỆU (vàng/xanh/đỏ của ô PROCESS) GIỮ NGUYÊN mã RGB gốc — xem utils/processColor.
 * Đó là thứ duy nhất trên màn hình mang nghĩa nghiệp vụ, đổi tông là đổi luôn thói quen của thợ.
 * ========================================================================== */

/* Chiều cao do JS đo và gán inline (xem fitRoot) — TUYỆT ĐỐI không đặt `min-height: 100vh` ở
   đây: màn hình này nằm trong AppLayout nên 100vh luôn dài hơn chỗ thật sự còn lại. */
.wsl-root {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;              /* mọi thứ phải vừa MỘT khung hình, không có thanh cuộn */
  border-radius: 12px;
  background:
    radial-gradient(1200px 500px at 50% -10%, #223049 0%, transparent 70%),
    #151a25;
  font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
  color: #0d1520;
}

.stage-wrap {
  flex: 1;
  min-height: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  padding: 8px;
}

/* Mặt form: khổ CỐ ĐỊNH bằng đúng form VBA, chỉ thu/phóng bằng transform nên mọi tỉ lệ bên
   trong không đổi. 979 x 728 px = 734.26 x 546.01 pt. */
.stage-fit {
  position: relative;
  flex: 0 0 auto;
}

.stage {
  position: absolute;
  left: 0;
  top: 0;
  width: 979px;
  height: 728px;
  background: #eef1f6;
  border-radius: 16px;
  box-shadow: 0 18px 50px rgba(0, 0, 0, 0.45), 0 0 0 1px rgba(255, 255, 255, 0.06);
  transform-origin: top left;
}

.stage > * {
  position: absolute;
  box-sizing: border-box;
}

/* ===== Nhãn ===== */

/* Tiêu đề cột: bản gốc để Tahoma 7.8pt thường, gần như không đọc được từ xa. Viết hoa + giãn
   chữ + đậm để nó ra dáng tiêu đề mà vẫn nằm gọn trong ô nhãn 12pt của form. */
.vv-head {
  display: flex;
  align-items: center;
  font-size: 11px !important;
  font-weight: 800;
  letter-spacing: 0.09em;
  color: #6b7488;
  white-space: nowrap;
  overflow: visible;
}

/* Số thứ tự dòng — ô gốc chỉ rộng 6pt nên phải cho tràn ra, nếu không chữ số bị cắt đôi. */
.vv-rowno {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px !important;
  font-weight: 800;
  color: #a6aec0;
  overflow: visible;
}

.vv-legend {
  display: flex;
  align-items: flex-end;
  font-size: 9px !important;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #7c8598;
  white-space: nowrap;
  overflow: visible;
}

/* ===== Ô dữ liệu ===== */

/*
 * Ô dữ liệu GIỮ font hẹp của bản gốc, KHÔNG đổi sang Inter.
 *
 * Không phải vì hoài cổ mà vì kích thước: ô RACK rộng 48pt (64px) trong khi chữ trong đó là 36pt
 * (48px). Arial Narrow nhét vừa 4 chữ số, Inter thì tràn ngay từ chữ số thứ ba. Cột WEIGHT cũng
 * vậy với "1234.56". Inter chỉ dùng cho nhãn/nút — nơi có chỗ thở.
 */
.vv-text {
  display: flex;
  align-items: center;
  padding: 0 6px;
  background: #fff;
  border: 1px solid #d7dde8;
  border-radius: 8px;
  font-family: 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif;
  font-weight: 700;
  line-height: 1;
  overflow: hidden;
  white-space: nowrap;
  color: #0d1520;
  outline: none;
}

/* Sọc chẵn/lẻ: 9 dòng ô trắng giống hệt nhau thì mắt rất dễ nhảy nhầm dòng khi liếc từ cân lên. */
.vv-text.alt { background: #f7f9fc; }

/* Dòng đang cân — VBA không có dấu này, nhưng 9 dòng giống hệt nhau mà không biết đang ở ô nào
   thì thợ phải dò bằng mắt. Chỉ thêm viền, KHÔNG đụng vào nền (nền là tín hiệu dung sai). */
.vv-text.cur {
  border-color: #1f6bff;
  box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.22);
}

input.vv-text:focus {
  border-color: #1f6bff;
  box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.22);
}

.vv-text.ro { cursor: default; }

.vv-text.num {
  justify-content: flex-end;
  font-variant-numeric: tabular-nums;
}

/* Mã vật tư là chuỗi cần đọc từng ký tự -> giãn nhẹ. Mục tiêu là số tham chiếu, không phải số
   thợ đang theo dõi -> hạ tương phản để nó không tranh chấp với ô PROCESS ngay bên cạnh. */
.vv-text.rack { text-align: center; padding: 0 2px; }
.vv-text.dye { letter-spacing: 0.01em; }
.vv-text.plan { color: #56617a; font-weight: 600; }

.vv-text.proc {
  cursor: pointer;
  font-weight: 700;
  /* Nền do JS gán (3 màu RGB gốc). Viền trong mảnh giữ cho ô có hình khối khi nền là màu tín hiệu. */
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.07);
}

.vv-text.proc.cur { box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.22), inset 0 0 0 1px rgba(0, 0, 0, 0.07); }

.vv-text.scan { font-weight: 800; letter-spacing: 0.02em; }
.vv-text.scan::placeholder { color: #b3bbc9; font-weight: 600; letter-spacing: 0.04em; }
.vv-text.scanning { background: #fff7dd; border-color: #e0b400; }

/* Ô có nhãn nằm luôn bên trong (CODE / LV / RAW) — phía trên chúng không còn khe trống nào. */
.vv-text.field { justify-content: space-between; gap: 4px; padding: 0 5px; }

.vv-text.field .cap {
  font-family: 'Inter', system-ui, sans-serif;
  font-size: 8px;
  font-style: normal;
  font-weight: 800;
  letter-spacing: 0.1em;
  color: #9aa3b5;
  flex: 0 0 auto;
}

.vv-text.field > span {
  overflow: hidden;
  text-overflow: ellipsis;
}

.vv-text.raw { color: #46506a; }

/* ===== Ô DELTA ===== */

.vv-delta {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  background: #10151f;
  border: 3px solid #2b3345;
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.12s ease;
}

.vv-delta .delta-num {
  font-family: 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
  line-height: 1;
  color: #f2f6ff;
}

.vv-delta .delta-target {
  font-size: 20px;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  color: #7b869e;
  align-self: flex-end;
  padding-bottom: 14px;
}

/* Ba màu tín hiệu, dùng đúng mã RGB của form gốc */
.vv-delta.tone-under { border-color: #FAE605; }
.vv-delta.tone-under .delta-num { color: #FAE605; }
.vv-delta.tone-ok { border-color: #78FA14; }
.vv-delta.tone-ok .delta-num { color: #8dfd35; }
.vv-delta.tone-over { border-color: #FF1400; }
.vv-delta.tone-over .delta-num { color: #ff5a49; }

/* ===== Nút ===== */

.vv-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  border-radius: 10px;
  font-family: 'Inter', system-ui, sans-serif;
  font-weight: 800;
  /* Giãn chữ vừa phải: nút hẹp nhất là CHECK (80px) với chữ 21.6px — giãn rộng hơn là tràn. */
  letter-spacing: 0.02em;
  cursor: pointer;
  padding: 0 4px;
  line-height: 1;
  white-space: nowrap;
  overflow: hidden;
  background: #fff;
  color: #2a3346;
  box-shadow: 0 1px 2px rgba(15, 30, 55, 0.12);
  transition: filter 0.1s ease, transform 0.05s ease;
}

.vv-btn:hover:not(:disabled) { filter: brightness(0.97); }
.vv-btn:active:not(:disabled) { transform: translateY(1px); }
.vv-btn:disabled { opacity: 0.42; cursor: not-allowed; box-shadow: none; }

/* Bàn phím số và các nút phụ trợ để trung tính — chúng bấm nhiều nhưng không phải quyết định. */
.vv-btn.key { background: #fff; border-color: #d7dde8; color: #2a3346; }
.vv-btn.ghost { background: #e7ebf2; border-color: #d0d7e3; color: #46506a; }

/* Nút quyết định: mỗi việc một màu, không đọc chữ cũng bấm đúng. */
.vv-btn.primary { background: #1f6bff; color: #fff; }
.vv-btn.accent { background: #12a150; color: #fff; }
.vv-btn.danger { background: #e23c2c; color: #fff; }
.vv-btn.out { background: #7a3ff0; color: #fff; }
.vv-btn.in { background: #0f7f9c; color: #fff; }

/* ===== Dải thông tin của riêng bản web (NGOÀI khung form) ===== */
/* Chiều cao CỐ ĐỊNH là điểm mấu chốt: `.stage-wrap` là flex:1 nên mọi thay đổi chiều cao ở dải
   này đều làm `fitStage` tính lại `scale` và cả mặt form co/phình theo. Vì thế ở đây KHÔNG được
   `flex-wrap: wrap` (xuống dòng là cao gấp đôi) và mọi phần tử ẩn/hiện đều phải nằm gọn một
   hàng. Phần tử duy nhất được co giãn là `.wb-msg`. */
.webbar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: nowrap;
  flex: 0 0 34px;
  height: 34px;
  overflow: hidden;
  padding: 0 12px;
  background: rgba(12, 16, 26, 0.72);
  border-top: 1px solid rgba(255, 255, 255, 0.07);
  color: #c9d1e2;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  font-size: 12px;
  transition: background 0.15s ease;
}

/* Có lỗi thì đổi màu CẢ dải — nổi hơn hẳn một dòng chữ đỏ, mà không tốn thêm chiều cao nào. */
.webbar.alarm {
  background: #4d1f1a;
  border-top-color: #e2564a;
  color: #ffc9c3;
}

.webbar > * { flex: 0 0 auto; white-space: nowrap; }

/* Phần tử co giãn duy nhất. `min-width: 0` là bắt buộc, thiếu nó thì flex item không chịu hẹp
   hơn nội dung và chữ dài vẫn đẩy các nút bên phải văng khỏi dải. */
.wb-msg {
  flex: 1 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 700;
}
.wb-msg.bad { color: #ffd9d4; }

.wb-ws { display: flex; align-items: center; gap: 6px; font-weight: 700; }
.wb-dot { width: 9px; height: 9px; border-radius: 50%; background: #7a8296; }
.wb-dot.on { background: #35c46a; }
.wb-dot.off { background: #e2564a; }

.wb-pill { padding: 2px 8px; border-radius: 999px; background: #3c455c; font-weight: 700; }
.wb-pill.ok { background: #1e5c36; color: #b9f2ce; }
.wb-pill.wait { background: #5c4a17; color: #ffe6a3; }

.wb-tare { opacity: 0.85; }

/* Danh sách 6 mã rack có thể rất dài — cho phép nó hẹp lại và cắt bớt, chứ đừng đẩy nút COPY
   hay ô giả lập ra khỏi dải. */
.wb-rack {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 2px 8px;
  border-radius: 6px;
  background: #3c455c;
  font-weight: 700;
  flex: 0 1 auto;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}

.wb-btn {
  border: 1px solid #6a7490;
  background: #4a5470;
  color: #eef1f7;
  border-radius: 5px;
  padding: 2px 9px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}

.wb-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.wb-queue {
  border: 1px solid #d9a400;
  background: #4a3c10;
  color: #ffe6a3;
  border-radius: 999px;
  padding: 2px 12px;
  font-weight: 800;
  font-size: 11px;
  cursor: pointer;
  font-family: inherit;
}

.wb-queue.stuck { border-color: #e2564a; background: #4d1f1a; color: #ffc9c3; }

/* KHÔNG `margin-left: auto`: margin auto hút hết khoảng trống còn lại TRƯỚC khi flex-grow được
   chia, nên nó sẽ đẩy cụm giả lập sang phải và bóp `.wb-msg` về đúng bề rộng chữ. Từ khi cụm này
   đứng trước ô thông báo, chỗ trống phải để dành cho `.wb-msg` co giãn. */
.wb-sim { display: flex; align-items: center; gap: 5px; }

.wb-siminput {
  width: 90px;
  height: 24px;
  border: 1px solid #6a7490;
  border-radius: 5px;
  background: #1f2534;
  color: #eef1f7;
  padding: 0 8px;
  font-weight: 700;
}

/* ===== Bảng hàng đợi ===== */
.queue-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 18, 32, 0.55);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 60;
  padding: 20px;
}

.queue-modal {
  background: #fff;
  border-radius: 12px;
  padding: 18px;
  width: min(900px, 100%);
  max-height: 85vh;
  overflow: auto;
  font-family: 'Segoe UI', sans-serif;
  color: #0d1520;
}

.queue-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.queue-note { font-size: 12px; color: #56617a; line-height: 1.5; }
.queue-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.queue-table th, .queue-table td { text-align: left; padding: 7px 8px; border-bottom: 1px solid #e3e7ee; }
.queue-table .c-num { text-align: right; }
.queue-table .strong { font-weight: 700; }
.queue-err { color: #a2310f; font-weight: 700; }
.queue-act { text-align: right; }
.queue-foot { display: flex; justify-content: flex-end; margin-top: 10px; }
.queue-modal .wb-btn { background: #4a5470; }
</style>
