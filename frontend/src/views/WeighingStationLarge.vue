<template>
  <div class="wsl-root" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <!-- ===== BẢN SAO ĐÚNG TỈ LỆ CỦA UserForm `scaleform` =====
         Khung 734.26 x 546.01 pt (= 979 x 728 px) đúng bằng form gốc; mọi control đặt tuyệt đối
         theo toạ độ/kích thước thật đọc từ chính file .xlsm. Cả khung được thu/phóng bằng MỘT
         phép transform: scale() nên tỉ lệ giữa các control, cỡ chữ, độ dày viền không bao giờ
         lệch so với bản VBA dù màn hình to nhỏ thế nào. -->
    <div class="stage-wrap" :class="{ zoomed: heSoPhong > 1 }" ref="wrapRef">
      <!-- Lớp đệm mang ĐÚNG kích thước SAU khi thu/phóng. `transform: scale()` không đổi ô layout
           của phần tử, nên thiếu lớp này thì lúc phóng to > 1 mặt form tràn ra ngoài vùng cuộn và
           bị cắt mất mép. -->
      <div class="stage-fit" :style="{ width: `${STAGE_W * scale}px`, height: `${STAGE_H * scale}px` }">
        <div class="stage" :style="{ transform: `scale(${scale})` }">

        <!-- Nhãn tiêu đề cột -->
        <div v-for="h in HEADERS" :key="h.text" class="vv-head" :style="box(h)">{{ $t(h.text!) }}</div>

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
            @focus="oNhap = { idx: i - 1, cot: 'rack' }"
            @input="onUpdateRack(i - 1, ($event.target as HTMLInputElement).value)"
            @click.stop
          />
          <div class="vv-text ro dye" :class="rowCls(i - 1)" :style="box(dyeBox(i - 1))">
            {{ jobItems[i - 1]?.material_code || '' }}
          </div>
          <!-- WEIGHT = mục tiêu cân. Sửa được nhưng CHỈ trước khi bấm NEXT lần đầu (yêu cầu người
               dùng 10/08/2026): bấm NEXT là bắt đầu chốt bì và cân theo mục tiêu, đổi mục tiêu
               giữa mẻ thì những ô đã cân xong bị đánh giá theo một thước khác. Sửa xong ô nào thì ô
               đó đổi màu (`.sua`) — số trên phiếu in và số dưới DB sẽ khác tem, phải nhìn ra ngay. -->
          <input
            class="vv-text num plan"
            :class="[rowCls(i - 1), { ro: !suaDuocMucTieu(i - 1), sua: daSuaMucTieu(i - 1) }]"
            :style="box(weightBox(i - 1))"
            :value="plannedText(i - 1)"
            :readonly="!suaDuocMucTieu(i - 1)"
            :title="tipMucTieu(i - 1)"
            inputmode="decimal"
            @focus="oNhap = { idx: i - 1, cot: 'weight' }; phienAo = null"
            @input="onNhapMucTieu(i - 1, $event)"
            @blur="ketThucNhapMucTieu"
            @click.stop
          />
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
        <button class="vv-btn key" :style="box(C.btn_del)" @click="numDel">{{ $t('weighingStationLarge.btnDel') }}</button>
        <button class="vv-btn ghost" :style="box(C.btnPrint)" @click="printSlip()">{{ $t('weighingStationLarge.btnPrint') }}</button>
        <button class="vv-btn ghost" :style="box(C.btnCheck)" @click="moLichSuCan">{{ $t('weighingStationLarge.btnCheck') }}</button>

        <button
          v-for="k in NUMPAD"
          :key="'k' + k.d"
          class="vv-btn key"
          :style="box(k)"
          @click="numClick(k.d)"
        >{{ k.d }}</button>

        <button class="vv-btn ghost" :style="box(C.btnClose)" @click="onClose">{{ $t('weighingStationLarge.btnClose') }}</button>
        <button class="vv-btn out" :style="box(C.btn_out)" :disabled="sendingRack" @click="onOut">{{ $t('weighingStationLarge.btnOut') }}</button>
        <button class="vv-btn in" :style="box(C.btn_in)" :disabled="sendingRack" @click="onIn">{{ $t('weighingStationLarge.btnIn') }}</button>
        <!-- onClear() có ngoặc: để "onClear" trần thì Vue truyền MouseEvent vào tham số
             skipConfirm (truthy) -> bỏ qua luôn hộp xác nhận. -->
        <button class="vv-btn danger" :style="box(C.btnCLEAR)" :disabled="saving" @click="onClear()">{{ $t('weighingStationLarge.btnClear') }}</button>
        <button class="vv-btn primary" :style="box(C.btnSAVE)" :disabled="saving" @click="onSave">
          {{ saving ? '…' : $t('weighingStationLarge.btnSave') }}
        </button>
        <button class="vv-btn accent" :style="box(C.btnNext)" :disabled="saving || !canPressNext" @click="onNext">
          {{ $t('weighingStationLarge.btnNext') }}
        </button>

        <!-- Nhãn cho các ô dưới — bản VBA để trống trơn, ai không thuộc form thì không đoán được ô
             nào là gì. Đặt vừa khít khe 9pt giữa lưới và hàng ô, không đụng vào toạ độ gốc. -->
        <div v-for="g in LEGENDS" :key="g.text" class="vv-legend" :style="box(g)">{{ $t(g.text!) }}</div>

        <!-- Khối dưới trái: COLOR / MACHINE / CODE / LV + ô delta khổng lồ + rawline -->
        <input
          ref="scanInputRef"
          class="vv-text scan"
          :class="{ scanning }"
          :style="box(C.txt_COLOR)"
          :value="activeBatch?.color || ''"
          :placeholder="scanning ? '…' : $t('weighingStationLarge.scanPlaceholder')"
          :readonly="scanning"
          @keyup.enter="docOQuet"
          @change="docOQuet"
          @focus="boiDenOQuet"
        />
        <div class="vv-text ro" :style="box(C.txt_MACHINE)">{{ activeBatch?.machine?.code || '' }}</div>
        <div class="vv-text ro field" :style="box(C.txt_CODE)">
          <i class="cap">{{ $t('weighingStationLarge.capCode') }}</i><span>{{ activeBatch?.product_code || '' }}</span>
        </div>
        <div class="vv-text ro field" :style="box(C.txt_LV)">
          <i class="cap">{{ $t('weighingStationLarge.capLv') }}</i><span>{{ activeBatch?.level_code || '' }}</span>
        </div>

        <!-- delta_rawline: số thợ thực sự nhìn khi đang đổ vật tư -->
        <div class="vv-delta" :class="'tone-' + deltaTone" :style="box(C.delta)">
          <span class="delta-num">{{ liveWeight.toFixed(2) }}</span>
          <span v-if="currentTarget !== null" class="delta-target">/ {{ currentTarget.toFixed(2) }}</span>
        </div>

          <div class="vv-text ro num field raw" :style="box(C.rawline)">
            <i class="cap">{{ $t('weighingStationLarge.capRaw') }}</i><span>{{ grossWeight.toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== CỘT THÔNG TIN CỦA RIÊNG BẢN WEB =====
         Cố ý nằm NGOÀI khung form: bản VBA không có mấy thứ này (mất tín hiệu cân, hàng đợi gửi
         mẻ, giả lập, thông báo lỗi) nhưng bỏ đi thì thợ không có cách nào biết máy đang hỏng gì.
         Để ngoài khung thì phần form vẫn giống hệt bản gốc.
         Đứng ở MÉ PHẢI (yêu cầu 09/08/2026) — lý do chọn bên phải xem ghi chú ở `.wsl-root`. -->
    <!-- Cột đang THU GỌN: chỉ còn một dải mỏng có nút bật lại (mặc định của mọi máy trạm, yêu cầu
         09/08/2026 — cột này chiếm 208px của mặt form mà phần lớn thời gian không ai nhìn).
         Vẫn là khối TRONG luồng bố cục chứ không phải nút nổi: mặt form được `fitStage` đo theo
         bề rộng còn lại, nên thu gọn xong form tự phóng to ra chiếm chỗ, còn nút nổi thì vừa che
         mất một góc form vừa không trả lại được chỗ đó.
         KHÔNG nuốt mất tín hiệu hỏng: có sự cố thì chính nút này chuyển đỏ và nhấp nháy — thợ vẫn
         biết có chuyện để mà bấm xem, đó là lý do cả cột này được dựng ra từ đầu. -->
    <div v-if="!hienWebbar" class="webbar-collapsed" :class="{ alarm: coSuCo }">
      <button
        class="wb-toggle"
        :title="coSuCo
          ? (statusMsg?.text || $t('weighingStationLarge.wbToggleAlarmDefaultMsg')) + $t('weighingStationLarge.wbToggleAlarmTitleSuffix')
          : $t('weighingStationLarge.wbToggleInfoTitle')"
        @click="datHienWebbar(true)"
      >
        <span class="wb-toggle-icon">{{ coSuCo ? '❗' : '‹' }}</span>
        <span class="wb-toggle-label">{{ coSuCo ? $t('weighingStationLarge.wbToggleAlarmLabel') : $t('weighingStationLarge.wbToggleInfoLabel') }}</span>
      </button>
    </div>

    <div v-else class="webbar" :class="{ alarm: statusMsg?.bad }">
      <button class="wb-hide" :title="$t('weighingStationLarge.wbHideTitle')" @click="datHienWebbar(false)">
        {{ $t('weighingStationLarge.wbHideLabel') }}
      </button>
      <span class="wb-ws">
        <!-- `signalLost` (3s) chứ không phải `signalLive` (1.5s): ngưỡng chặt là CỔNG AN TOÀN
             cho việc chốt bì/lưu số, dùng nó để bật đèn báo thì đèn nhấp nháy mỗi lần số về trễ
             một nhịp — xem ghi chú LOST_SIGNAL_MS trong useScaleFeed. -->
        <span class="wb-dot" :class="scaleOnline && !signalLost ? 'on' : 'off'"></span>
        {{ currentWorkstation?.code || $t('weighingStationLarge.noWorkstationShort') }}
      </span>
      <span class="wb-pill" :class="isStable ? 'ok' : 'wait'">
        {{ signalLost && !useSimValue ? $t('weighingStationLarge.signalLostLabel') : (isStable ? $t('weighingStationLarge.stableLabel') : $t('weighingStationLarge.waitStableLabel')) }}
      </span>
      <span v-if="tareBaseline !== null" class="wb-tare">{{ $t('weighingStationLarge.tarePrefix') }} {{ tareBaseline.toFixed(2) }}</span>
      <button v-if="currentIndex >= 0 && tareBaseline !== null" class="wb-btn" @click="retare">{{ $t('weighingStationLarge.retareBtn') }}</button>

      <span v-if="rackBatchText" class="wb-rack" :title="$t('weighingStationLarge.rackBatchTitle')">
        {{ $t('weighingStationLarge.rackBatchPrefix') }} {{ rackBatchText }}
        <button class="wb-btn" @click="onCopyRacks">{{ $t('weighingStationLarge.copyBtn') }}</button>
      </span>

      <button v-if="queueCount > 0" class="wb-queue" :class="{ stuck: stuckCount > 0 }" @click="showQueue = true">
        {{ stuckCount > 0 ? '✕' : '⏳' }} {{ queueCount }} {{ $t('weighingStationLarge.queueWaitingSuffix') }}
      </button>

      <!-- Cỡ hiển thị — để ngay trong dải thao tác chứ không giấu vào một trang cài đặt: thợ đeo
           găng, đổi ca là đổi người, phải chỉnh được tại chỗ. Máy này nhớ lựa chọn.
           100% = VỪA KHÍT khung đang có, tức mặc định đã là to nhất mà vẫn thấy trọn mặt form.
           Các nấc trên 100% cố ý cho tràn và phải cuộn — dành cho người đứng xa cần chữ to hơn
           là cần nhìn thấy cả form cùng lúc. -->
      <span class="zoom-ctl">
        <button class="wb-btn" :disabled="heSoPhong <= MUC_PHONG[0]" :title="$t('weighingStationLarge.zoomShrinkTitle')" @click="doiCoManHinh(-1)">A−</button>
        <button class="zoom-val" :title="$t('weighingStationLarge.zoomFitTitle')" @click="datMucPhong(1)">
          {{ Math.round(heSoPhong * 100) }}%
        </button>
        <button
          class="wb-btn"
          :disabled="heSoPhong >= MUC_PHONG[MUC_PHONG.length - 1]"
          :title="$t('weighingStationLarge.zoomEnlargeTitle')"
          @click="doiCoManHinh(1)"
        >A+</button>
      </span>

      <!-- Đứng TRƯỚC ô thông báo chứ không phải sau: `.wb-msg` là phần tử duy nhất co giãn, nên
           để sau nó thì mỗi lần có thông báo dài là cụm giả lập bị đẩy ra sát rìa phải, có màn
           hình hẹp còn đẩy khuất hẳn. Trước nó thì vị trí đứng yên bất kể thông báo dài ngắn. -->
      <label class="wb-sim">
        <input type="checkbox" v-model="useSimValue" /> {{ $t('weighingStationLarge.simLabel') }}
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
          <strong>{{ $t('weighingStationLarge.queueModalTitle', { count: queueCount }) }}</strong>
          <button class="wb-btn" @click="showQueue = false">{{ $t('weighingStationLarge.queueCloseBtn') }}</button>
        </div>
        <p class="queue-note">
          {{ $t('weighingStationLarge.queueNotePrefix') }}<strong>{{ $t('weighingStationLarge.queueNoteStrong1') }}</strong>{{ $t('weighingStationLarge.queueNoteMiddle') }}<strong>{{ $t('weighingStationLarge.queueNoteStrong2') }}</strong>{{ $t('weighingStationLarge.queueNoteSuffix') }}
        </p>
        <table class="queue-table">
          <thead>
            <tr><th>{{ $t('weighingStationLarge.thBatch') }}</th><th>{{ $t('weighingStationLarge.thQueuedAt') }}</th><th class="c-num">{{ $t('weighingStationLarge.thAttempts') }}</th><th>{{ $t('common.status') }}</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="q in queueItems" :key="q.idempotency_key">
              <td class="strong">{{ q.nhan || '—' }}</td>
              <td>{{ formatQueueTime(q.queued_at) }}</td>
              <td class="c-num">{{ q.so_lan_thu }}</td>
              <td>
                <span v-if="q.loi_nghiep_vu" class="queue-err">
                  {{ q.loi_nghiep_vu }} <em>{{ $t('weighingStationLarge.queueRetryingNote') }}</em>
                </span>
                <span v-else-if="duongThong">{{ $t('weighingStationLarge.queueWaitingTurn') }}</span>
                <span v-else>{{ $t('weighingStationLarge.queueOffline') }}</span>
              </td>
              <td class="queue-act">
                <button v-if="q.loi_nghiep_vu" class="wb-btn" @click="onThuLai(q.idempotency_key)">{{ $t('weighingStationLarge.queueRetryBtn') }}</button>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="queue-foot">
          <button class="wb-btn" :disabled="flushing" @click="onGuiNgay">
            {{ flushing ? $t('weighingStationLarge.queueSendingBtn') : $t('weighingStationLarge.queueSendNowBtn') }}
          </button>
        </div>
      </div>
    </div>

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
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import FullscreenButton from '../components/FullscreenButton.vue';
import HopThoaiVba from '../components/HopThoaiVba.vue';
import { useHopThoai } from '../composables/useHopThoai';
import { currentWorkstation, adoptLocalWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { isFullscreen } from '../services/layout';
import { useScaleFeed } from '../composables/useScaleFeed';
import { inPhieuTrongTrang } from '../utils/slipPrint';
import { parseDyeQr, docQrMeNhuom, MAX_RACK_LINES, type ParsedDyeQr } from '../utils/qrDyeParser';
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
  { l: 12.02, t: 0, w: 30.02, h: 11.99, f: 7.8, text: 'weighingStationLarge.headerRack' },
  { l: 65.99, t: 0, w: 47.99, h: 11.99, f: 7.8, text: 'weighingStationLarge.headerDyeCode' },
  { l: 257.98, t: 0, w: 36, h: 11.99, f: 7.8, text: 'weighingStationLarge.headerWeight' },
  { l: 396.03, t: 0, w: 42.01, h: 11.99, f: 7.8, text: 'weighingStationLarge.headerProcess' },
];

/**
 * Nhãn cho hàng ô dưới cùng — KHÔNG có trong form gốc (bên đó 4 ô COLOR/MACHINE/CODE/LV trần
 * trụi, ai không thuộc form thì không đoán được ô nào là gì). Đặt vừa khe 9.3pt giữa đáy lưới
 * (440.7) và đỉnh hàng ô (450), nên không control gốc nào phải xê dịch.
 * CODE / LV / RAW không có khe trống phía trên nên nhãn nằm luôn trong ô (xem `.field .cap`).
 */
const LEGENDS: Box[] = [
  { l: 11.99, t: 441, w: 90, h: 8.4, f: 6.4, text: 'weighingStationLarge.legendColor' },
  { l: 108, t: 441, w: 47.99, h: 8.4, f: 6.4, text: 'weighingStationLarge.legendMachine' },
  { l: 162, t: 441, w: 384.01, h: 8.4, f: 6.4, text: 'weighingStationLarge.legendDelta' },
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

const { t } = useI18n({ useScope: 'global' });

const rootRef = ref<HTMLElement | null>(null);
const wrapRef = ref<HTMLElement | null>(null);
const scale = ref(1);
const rootH = ref<number | null>(null);
let ro: ResizeObserver | null = null;
let roKhung: ResizeObserver | null = null;

/**
 * Lớp đặt trên `<body>` để gỡ padding 24px của `.content-container` trong AppLayout — xem khối
 * `<style>` KHÔNG scoped ở cuối file.
 *
 * Chỉ tồn tại trong lúc màn này đang mở, nên không rò sang trang khác.
 */
const LOP_TRAN_VIEN = 'df-man-can-tran-vien';

/** Lề của `.stage-wrap` (padding 8px mỗi bên) — clientWidth/Height đã tính cả padding. */
const WRAP_PAD = 16;

/* ============================================================================
 * CỠ HIỂN THỊ
 *
 * Màn này KHÔNG phóng bằng `zoom` như /weighing-station-v2. Bên đó bố cục chảy theo dòng nên
 * `zoom` tính lại layout thật; bên này mặt form là khổ CỐ ĐỊNH 979x728 đã được `transform:
 * scale()` thu cho vừa khung — đặt thêm `zoom` lên trên thì `fitStage` đo được khung "to ra"
 * đúng bấy nhiêu lần rồi thu nhỏ lại y chừng đó, tức bấm A+ không thấy gì đổi.
 *
 * Nên ở đây hệ số của thợ NHÂN VÀO chính tỉ lệ vừa khung: 100% = vừa khít, các nấc trên là cố ý
 * cho tràn ra ngoài và cuộn (xem `.stage-wrap.zoomed`).
 *
 * Không đặt cứng một con số vì mỗi trạm một cỡ màn hình và khoảng cách đứng khác nhau — đoán sai
 * thì hoặc chữ vẫn bé, hoặc phải cuộn mới thấy hết 9 dòng.
 */
const MUC_PHONG = [1, 1.15, 1.3, 1.5, 1.75];
const KHOA_MUC_PHONG = 'wslarge.co-hien-thi';
const heSoPhong = ref(napMucPhong());

function napMucPhong(): number {
  const v = Number(localStorage.getItem(KHOA_MUC_PHONG));
  // Mặc định 100% (vừa khít) chứ không phải 125% như màn cân nhỏ: bên đó 100% là khổ gốc của
  // form nên nhỏ thật, còn ở đây 100% đã là mức lớn nhất mà vẫn thấy trọn mặt form.
  return MUC_PHONG.includes(v) ? v : 1;
}

function datMucPhong(muc: number) {
  heSoPhong.value = muc;
  localStorage.setItem(KHOA_MUC_PHONG, String(muc));
  // Đo lại SAU khi lớp `zoomed` đã được áp: lớp đó bật `overflow: auto`, mà thanh cuộn hiện lên
  // là `clientWidth` hụt đi vài chục px — tính trước thì ra tỉ lệ của khung cũ.
  nextTick(fitStage);
}

/**
 * Cột thông tin bên phải (trạm, hàng đợi, cỡ chữ, giả lập, thông báo) — MẶC ĐỊNH THU GỌN.
 *
 * Cột này rộng 208px và phần lớn thời gian không ai nhìn tới; thu gọn đi thì `fitStage` có thêm
 * chừng đó bề ngang để phóng mặt form. Máy nào bật ra thì máy đó nhớ (localStorage), giống cách
 * `KHOA_MUC_PHONG` nhớ cỡ hiển thị — hai thợ hai ca có thể thích khác nhau.
 *
 * Không đụng tới `isFullscreen` dùng chung: đó là việc ẩn sidebar/thanh trên của cả app, còn đây
 * là một cột của riêng màn này.
 */
const KHOA_HIEN_WEBBAR = 'wslarge.hien-cot-thong-tin';
const hienWebbar = ref(localStorage.getItem(KHOA_HIEN_WEBBAR) === '1');

function datHienWebbar(hien: boolean) {
  hienWebbar.value = hien;
  localStorage.setItem(KHOA_HIEN_WEBBAR, hien ? '1' : '0');
  // Bề rộng khung mặt form vừa đổi — đo lại ngay, đừng đợi ResizeObserver bắn ở nhịp sau.
  nextTick(fitStage);
}

/**
 * Có chuyện cần thợ biết ngay: lỗi thao tác, mất tín hiệu cân, mất kết nối máy chủ (đều nằm trong
 * `statusMsg.bad`), hoặc có mẻ nằm kẹt trong hàng đợi chưa gửi được.
 *
 * Dùng để nút thu gọn tự chuyển đỏ. CỐ Ý không tự bung cả cột ra: bung ra là mặt form co lại giữa
 * lúc thợ đang cân, mà mất kết nối máy chủ thì lặp lại liên tục ở xưởng.
 */
const coSuCo = computed(() => !!statusMsg.value?.bad || stuckCount.value > 0);

function doiCoManHinh(buoc: number) {
  const dangO = MUC_PHONG.indexOf(heSoPhong.value);
  datMucPhong(MUC_PHONG[Math.min(MUC_PHONG.length - 1, Math.max(0, (dangO < 0 ? 0 : dangO) + buoc))]);
}

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
  const cao = Math.max(320, Math.floor(window.innerHeight - top - padBottom));
  // Chỉ ghi khi thực sự lệch: hàm này còn được gọi từ ResizeObserver đặt trên khung CHA, mà gán
  // lại cùng một giá trị vẫn đủ để trình duyệt bắn thêm một vòng đo nữa.
  if (rootH.value === null || Math.abs(rootH.value - cao) > 1) rootH.value = cao;
}

/** Tỉ lệ để mặt form vừa khít khung — đây chính là mốc "100%" trên nút A−/A+. */
function tiLeVuaKhung(): number | null {
  const el = wrapRef.value;
  if (!el) return null;
  const w = el.clientWidth - WRAP_PAD;
  const h = el.clientHeight - WRAP_PAD;
  if (w <= 0 || h <= 0) return null;
  return Math.min(w / STAGE_W, h / STAGE_H);
}

function fitStage() {
  const vua = tiLeVuaKhung();
  if (vua === null) return;
  // Chặn trên 3.0: toạ độ form là số cố định, phóng quá to thì chỉ còn vài control chiếm cả màn
  // hình chứ không đọc được thêm gì. Trước đây chặn 2.0 — đủ cho lúc chỉ có thu-cho-vừa, nhưng
  // nấc 175% chồng lên một khung đã vừa 1.3x thì đụng trần và hai nấc cuối thành vô nghĩa.
  scale.value = Math.max(0.3, Math.min(vua * heSoPhong.value, 3));
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
/**
 * `LastInputBox` của VBA — ô vừa gõ, để bàn phím số bên phải biết gõ vào đâu.
 *
 * Bản gốc chỉ nhớ SỐ DÒNG vì bàn phím số chỉ gõ được vào ô RACK. Nay cột WEIGHT cũng sửa được nên
 * phải nhớ cả CỘT, không thì bấm số sau khi vừa sửa mục tiêu lại nhảy vào ô rack cùng dòng.
 */
const oNhap = ref<{ idx: number; cot: 'rack' | 'weight' } | null>(null);

/**
 * Mục tiêu gốc in trên tem, chốt lại lúc nạp mẻ — để biết dòng nào đã bị sửa và sửa từ số nào.
 * Giữ riêng thay vì so với `parseDyeQr` lần nữa: dòng có công thức duyệt thì mục tiêu tới từ server
 * chứ không từ tem.
 */
const mucTieuGoc = ref<Record<number, number | null>>({});

/**
 * Ô mục tiêu đang gõ dở bằng bàn phím máy tính — giữ NGUYÊN VĂN chuỗi đang gõ.
 *
 * Cần trạng thái riêng vì `plannedText()` luôn định dạng 2 chữ số thập phân: không có nó thì vừa
 * gõ dấu chấm là bị viết lại thành "12.00" ngay dưới ngón tay, không ai gõ nổi "12.5".
 */
const nhapMucTieu = ref<{ idx: number; text: string } | null>(null);

/**
 * Phiên gõ BÀN PHÍM ẢO cho ô mục tiêu: chuỗi chữ số thô, giá trị = chuỗi / 100.
 *
 * Bàn phím số của form gốc KHÔNG có phím dấu chấm (btn_0..btn_9 + DEL, xem hằng `NUMPAD` — toạ độ
 * là bản sao form thật, không tự thêm nút mới), mà mục tiêu thuốc nhuộm luôn có phần thập phân.
 * Nên phím ảo gõ theo lối CÂN/MÁY TÍNH TIỀN: mỗi số đẩy dần từ hàng xu lên (5 -> 0.05, 52 -> 0.52,
 * 523 -> 5.23). Không cần dấu chấm, và số hiện ngay trong ô nên gõ quá/thiếu là thấy liền.
 * Bấm phím đầu tiên là GÕ LẠI TỪ ĐẦU chứ không nối vào số cũ — sửa mục tiêu là thay số, không phải
 * thêm chữ số vào số của tem.
 */
const phienAo = ref<{ idx: number; so: string } | null>(null);

const jobItems = computed(() => activeJob.value?.items || []);

/** VBA: NEXT chạy hết 9 DÒNG LƯỚI, không phải hết số vật tư trong đơn. */
const canPressNext = computed(() => currentIndex.value < MAX_RACK_LINES - 1);

const currentTarget = computed<number | null>(() => {
  const item = currentIndex.value >= 0 ? jobItems.value[currentIndex.value] : null;
  const t = item ? Number(item.planned_weight) : NaN;
  return Number.isFinite(t) && t > 0 ? t : null;
});

/* ===== Hiển thị lưới ===== */

/**
 * Dung sai ±1% mục tiêu — đúng `ScannerController::TOLERANCE_RATIO` và gốc VBA
 * `Mod_UI_processcolor.CheckRange`. Lệch với server là màu đèn lúc cân khác kết quả chốt lúc lưu.
 */
const TOLERANCE_RATIO = 0.01;

function plannedText(i: number): string {
  if (nhapMucTieu.value?.idx === i) return nhapMucTieu.value.text;
  const v = jobItems.value[i]?.planned_weight;
  if (v === null || v === undefined || v === '') return '';
  return Number(v).toFixed(2);
}

/* ===== Sửa mục tiêu (cột WEIGHT) — chỉ TRƯỚC khi bấm NEXT lần đầu ===== */

/** Sai lệch coi như "vẫn là số của tem" — cùng ngưỡng đối soát ±0.000001 của dự án. */
const NGUONG_LECH_MUC_TIEU = 0.000001;

/** Nhiều nhất 7 chữ số thô cho mục tiêu = 99999.99 — quá thừa cho mọi mẻ, và chặn tràn ô. */
const MAX_SO_MUC_TIEU = 7;

/**
 * Dòng nào sửa được mục tiêu: phải CÓ vật tư trong đơn và CHƯA bấm NEXT lần nào.
 *
 * Dòng trống (mẻ cân tay, hoặc tem ít dòng hơn 9) để nguyên không sửa được: dưới server không có
 * item nào để mà gắn mục tiêu vào (`weighFromQr` cố ý không tự chế thêm vật tư ngoài đơn), gõ được
 * mà không lưu được thì tệ hơn là không cho gõ.
 */
function suaDuocMucTieu(i: number): boolean {
  const item = jobItems.value[i];
  return currentIndex.value === -1 && !saving.value && !!item && item.status !== 'COMPLETED';
}

function daSuaMucTieu(i: number): boolean {
  if (!(i in mucTieuGoc.value)) return false;
  const goc = mucTieuGoc.value[i];
  const nay = jobItems.value[i]?.planned_weight ?? null;
  if (goc === null || nay === null) return goc !== nay;
  return Math.abs(Number(nay) - Number(goc)) > NGUONG_LECH_MUC_TIEU;
}

function tipMucTieu(i: number): string {
  if (daSuaMucTieu(i)) {
    const goc = mucTieuGoc.value[i];
    return t('weighingStationLarge.tipEdited', {
      value: goc === null ? t('weighingStationLarge.tipEditedEmpty') : Number(goc).toFixed(2),
    });
  }
  if (suaDuocMucTieu(i)) return t('weighingStationLarge.tipEditable');
  if (currentIndex.value !== -1) return t('weighingStationLarge.tipLocked');
  return '';
}

/** Ghi mục tiêu mới vào dòng `i`. `null` = để trống ô đó (đúng VBA: dòng không có WEIGHT thì không lưu). */
function datMucTieu(i: number, val: number | null) {
  const item = jobItems.value[i];
  if (!item) return;
  item.planned_weight = val;
  // Dung sai phải đi theo mục tiêu MỚI, nếu không đèn ĐẠT/KHÔNG ĐẠT lúc cân vẫn đo theo dải ±1%
  // của số cũ — server cũng tính lại đúng như vậy (ScannerController::weighFromQr).
  const t = Number(val) || 0;
  item.tolerance_minus = t * TOLERANCE_RATIO;
  item.tolerance_plus = t * TOLERANCE_RATIO;
  saveSession();
}

function onNhapMucTieu(i: number, e: Event) {
  if (!suaDuocMucTieu(i)) return;
  const el = e.target as HTMLInputElement;
  // Cân/tem có thể xuất số theo locale khác -> nhận cả dấu phẩy, đúng như qrDyeParser.
  // Cắt độ dài: ô này có thể ăn nguyên một cú quét nếu thợ sửa mục tiêu rồi quên bấm ra ngoài
  // (đúng lớp rủi ro của ô RACK). Cắt xuống 9 ký tự thì thứ còn lại là một con số vô lý, được tô
  // vàng như mọi mục tiêu sửa tay — thấy ngay, thay vì một chuỗi dài 30 chữ số trông như lỗi máy.
  const sach = el.value.replace(/,/g, '.').replace(/[^0-9.]/g, '').slice(0, MAX_SO_MUC_TIEU + 2);
  // Ràng buộc `:value` chỉ ghi lại DOM khi giá trị vnode đổi; ký tự bị lọc bỏ ở đây không làm nó
  // đổi, nên phải tự đặt lại — không thì chữ lạ vẫn nằm trong ô.
  if (el.value !== sach) el.value = sach;

  phienAo.value = null;
  nhapMucTieu.value = { idx: i, text: sach };

  const n = Number(sach);
  datMucTieu(i, sach === '' || !Number.isFinite(n) ? null : n);
}

/** Rời ô: bỏ bản nháp nguyên văn để ô hiển thị lại dạng chuẩn 2 số thập phân. */
function ketThucNhapMucTieu() {
  nhapMucTieu.value = null;
}

/** Các dòng có mục tiêu KHÁC tem, dạng `{ sequence_no: mục tiêu mới }` — để lưu phiên và gửi server. */
function mucTieuDaSua(): Record<number, number | null> {
  const ra: Record<number, number | null> = {};
  jobItems.value.forEach((it: any, i: number) => {
    if (!daSuaMucTieu(i)) return;
    const v = it?.planned_weight;
    ra[it?.sequence_no ?? i + 1] = v === null || v === undefined || v === '' ? null : Number(v);
  });
  return ra;
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
 * báo — thợ đang nhìn số cân thì màn hình nhấp nháy đổi cỡ. Đưa vào cột trạng thái (BỀ NGANG cố
 * định, thông báo dài thì xuống dòng trong cột) thì mặt form không bao giờ đổi cỡ nữa.
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
      text: t('weighingStationLarge.signalLostMsg')
        + (readingAgeMs.value !== null
          ? t('weighingStationLarge.signalLostAgeSuffix', { sec: (readingAgeMs.value / 1000).toFixed(1) })
          : ''),
      bad: true,
    };
  if (!useSimValue.value && !scaleOnline.value)
    return { text: t('weighingStationLarge.serverDisconnectedMsg'), bad: true };
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
    rackMsg.value = t('weighingStationLarge.rackNoneToSend');
    return;
  }
  sendingRack.value = true;
  // Chờ tới khi Agent ack (tối đa ~12 giây) nên phải nói rõ đang chờ — nút xám mà không có chữ
  // thì thợ tưởng máy treo và bấm lại.
  rackOk.value = true;
  rackMsg.value = t('weighingStationLarge.rackSendingOut');
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
  rackMsg.value = t('weighingStationLarge.rackSendingIn');
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
    rackMsg.value = t('weighingStationLarge.rackCopyEmpty');
    return;
  }
  try {
    await navigator.clipboard.writeText(text);
    rackOk.value = true;
    rackMsg.value = t('weighingStationLarge.rackCopySuccess');
  } catch {
    rackOk.value = false;
    rackMsg.value = t('weighingStationLarge.rackCopyFail');
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
  const o = oNhap.value;
  if (!o) return;

  if (o.cot === 'weight') {
    if (!suaDuocMucTieu(o.idx)) return;
    const cu = phienAo.value?.idx === o.idx ? phienAo.value.so : '';
    const so = (cu + d).replace(/^0+(?=\d)/, '').slice(0, MAX_SO_MUC_TIEU);
    phienAo.value = { idx: o.idx, so };
    nhapMucTieu.value = null; // rời chế độ gõ nguyên văn: từ đây ô hiện số đã quy đổi
    datMucTieu(o.idx, Number(so) / 100);
    return;
  }

  onUpdateRack(o.idx, rackAt(o.idx) + d);
}

function numDel() {
  const o = oNhap.value;
  if (!o) return;

  if (o.cot === 'weight') {
    if (!suaDuocMucTieu(o.idx)) return;
    // Chưa gõ phím ảo nào thì lấy chính số đang hiện làm điểm bắt đầu (12.34 -> "1234"), nhờ vậy
    // DEL luôn có nghĩa "xoá một chữ số của số đang thấy", không phải "xoá hết rồi làm lại".
    const dangCo = phienAo.value?.idx === o.idx
      ? phienAo.value.so
      : String(Math.round((Number(jobItems.value[o.idx]?.planned_weight) || 0) * 100));
    const so = dangCo.replace(/^0+(?=\d)/, '').slice(0, -1);
    phienAo.value = { idx: o.idx, so };
    nhapMucTieu.value = null;
    datMucTieu(o.idx, so === '' ? null : Number(so) / 100);
    return;
  }

  const cur = rackAt(o.idx);
  if (cur.length > 0) onUpdateRack(o.idx, cur.slice(0, -1));
}

/* ============================================================================
 * NHỊP ĐỌC CÂN
 * ========================================================================== */

const POLL_MS_WEIGHING = 200;
const POLL_MS_IDLE = 1000;

/**
 * Nhịp khi đọc thẳng Agent "Cân to" trên máy này (ADR-013) — cuộc gọi không rời khỏi máy nên dày
 * gấp 8 lần mà không tốn gì của máy chủ. Hạ 60 -> 25ms ngày 07/08/2026; lý do và giới hạn (nhịp
 * phát của chính cái cân) xem ghi chú đầy đủ ở WeighingStationV2.vue.
 */
const POLL_MS_CUC_BO = 25;

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
      // Mục tiêu sửa tay phải sống qua F5: mẻ quét QR được DỰNG LẠI từ chuỗi tem lúc khôi phục
      // (restoreSession), nên không giữ riêng thì số vừa sửa âm thầm quay về số của tem.
      mucTieuSua: mucTieuDaSua(),
    }));
  } catch {
    // Hết dung lượng / chế độ riêng tư: mất khả năng khôi phục thôi, không làm hỏng luồng cân.
  }
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

/**
 * Áp lại mục tiêu sửa tay sau F5. Ánh xạ theo `sequence_no` chứ không theo chỉ số dòng: mẻ khôi
 * phục từ server có thể xếp dòng khác thứ tự đã lưu.
 */
function apLaiMucTieuSua(daSua: any) {
  if (!daSua || typeof daSua !== 'object') return;
  jobItems.value.forEach((it: any, i: number) => {
    const seq = it?.sequence_no ?? i + 1;
    if (!(seq in daSua)) return;
    const v = daSua[seq];
    datMucTieu(i, v === null || v === undefined ? null : Number(v));
  });
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
    apLaiMucTieuSua(saved.mucTieuSua);
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
    chotMucTieuGoc(); // trước khi áp lại số sửa tay, không thì chính số sửa lại bị coi là "số của tem"
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
    errorMsg.value = t('weighingStationLarge.resumeMismatch', { from: p.gross.toFixed(2), to: grossWeight.value.toFixed(2) });
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

/**
 * Chuỗi vừa xử lý + mốc giờ — chốt chống chạy hai lần.
 *
 * Cần vì ô quét giờ nghe CẢ `keyup.enter` LẪN `change`, mà bấm Enter trong một ô text thì Chrome
 * bắn cả hai. Cửa sổ 2 giây (bằng `DUPLICATE_WINDOW_MS` của services/scanner) nên quét lại đúng
 * con tem đó để nạp lại mẻ vẫn được, chỉ cần cách nhau hơn 2 giây.
 */
let tokenCuoi = '';
let mocTokenCuoi = 0;

/**
 * Bôi đen sẵn nội dung ô quét mỗi lần nó nhận tiêu điểm.
 *
 * Ô này vừa là ô HIỂN THỊ mã màu (`:value` đổ `activeBatch.color` vào, đúng như txt_COLOR của
 * form VBA) vừa là ô BẮT phím của máy quét. Không bôi đen thì con trỏ đứng ở CUỐI chuỗi cũ, và
 * cú quét kế tiếp NỐI THÊM vào phía sau: "DF9001" + "#DF9002-LG2509-..." -> mã màu đọc ra thành
 * "DF9001#DF9002". Sai một kiểu khác nhau mỗi lần, vì còn tuỳ mẻ trước đó là mẻ nào — đúng
 * triệu chứng "mỗi lần quét lại ra 1 cái khác".
 */
function boiDenOQuet(e: Event) {
  (e.target as HTMLInputElement).select();
}

/**
 * Trả tiêu điểm về ô quét và bôi đen sẵn — dùng sau mỗi lần quét/xoá/lưu.
 *
 * Kèm việc ÉP nội dung ô về đúng thứ nó phải hiển thị (mã màu của mẻ đang mở, y như ràng buộc
 * `:value` trên thẻ input) thay vì trông chờ Vue tự làm. Vue chỉ ghi lại `el.value` khi mã màu
 * MỚI khác mã màu CŨ; quét hai con tem cùng mã màu liền nhau thì nó bỏ qua, và ô vẫn còn nguyên
 * chuỗi QR dài của cú quét vừa rồi. Chuỗi đó nằm đó là một cú "quét lại chính nó" chờ sẵn: hễ ô
 * mất tiêu điểm sau 2 giây (chốt chống trùng hết hiệu lực) là `change` nạp lại mẻ và xoá trắng
 * những số đã cân. Ép ở đây để nội dung ô luôn là thứ ta tự đặt vào, nhờ vậy `docOQuet` phân
 * biệt được đâu là cú quét thật.
 */
function veOQuet() {
  const o = scanInputRef.value;
  if (!o) return;
  o.value = activeBatch.value?.color ?? '';
  o.focus();
  o.select();
}

/**
 * VBA `txt_color_AfterUpdate` chạy khi ô MẤT TIÊU ĐIỂM — tức cả khi máy quét bắn hậu tố Enter
 * LẪN khi nó bắn hậu tố Tab. Bản web trước đây chỉ nghe `@keyup.enter`, nên một máy quét cấu
 * hình hậu tố TAB (chạy hoàn hảo bên Excel, cùng máy cùng con tem) thì ở đây:
 *
 *   1. Chuỗi nằm im trong ô quét, KHÔNG ai đọc.
 *   2. Tab đẩy tiêu điểm sang phần tử kế — chính là ô RACK dòng 1.
 *   3. Cú quét SAU đổ nguyên chuỗi vào ô rack đó, rồi Tab tiếp sang rack dòng 2...
 *
 * Đó là "quét ra không đúng ô". Nghe thêm `change` là hết cả hai đường.
 */
function docOQuet() {
  const token = (scanInputRef.value?.value ?? '').trim();
  if (!token || scanning.value) return;

  /*
   * Ô này VỪA bắt phím máy quét VỪA hiển thị mã màu của mẻ đang mở. Nghe `change` để đỡ máy quét
   * hậu tố Tab (xem khối chú thích trên) kéo theo một hệ quả: trình duyệt cũng bắn `change` khi ô
   * MẤT TIÊU ĐIỂM — mà bấm NEXT, bấm bàn phím số, bấm vào ô RACK đều làm ô mất tiêu điểm. Lúc đó
   * nội dung ô không còn là chuỗi QR nữa, nó là mã màu ta vừa đổ vào để hiện lên (ví dụ "DF9002").
   * Chuỗi một đoạn ấy không có CODE nên `docQrMeNhuom` trả null, và thợ vừa quét xong đã ăn ngay
   * câu "Mã quét thiếu COLOR hoặc CODE" dù cú quét hoàn toàn tốt.
   *
   * Thoát im lặng ở đây KHÔNG làm mất cú quét nào: mã màu đang hiện là thứ CHÍNH TA đặt vào ô,
   * không phải thứ máy quét bắn ra. Cú quét thật luôn ghi đè nội dung ô (`boiDenOQuet` bôi đen sẵn,
   * `batPhimLacRaNgoai` kéo phím lạc về đây) nên chuỗi đọc được luôn khác mã màu.
   */
  if (token === (activeBatch.value?.color ?? '').trim()) return;

  const gio = Date.now();
  if (token === tokenCuoi && gio - mocTokenCuoi < 2000) return;
  tokenCuoi = token;
  mocTokenCuoi = gio;

  handleBarcodeScan(token);
}

/**
 * Máy quét là BÀN PHÍM GIẢ: nó gõ vào bất cứ thứ gì đang giữ tiêu điểm. Bấm một nút bất kỳ trên
 * màn hình (NEXT, bàn phím số, OUT/IN, CLEAR) là trình duyệt chuyển tiêu điểm sang chính cái nút
 * đó, và cú quét ngay sau rơi vào nút — mất hẳn, không ai đọc, không một tiếng bíp.
 *
 * Form VBA không có cảnh này vì UserForm ôm trọn bàn phím của cửa sổ. Lấy lại đúng hành vi đó:
 * hễ có ký tự gõ ra mà tiêu điểm KHÔNG nằm trong một ô nhập nào thì kéo về ô quét NGAY TỪ KÝ TỰ
 * ĐẦU TIÊN.
 *
 * Tự chèn ký tự đầu rồi `preventDefault` chứ không trông chờ trình duyệt chèn hộ sau khi đổi tiêu
 * điểm: hành vi đó khác nhau giữa các trình duyệt, mà mất đúng ký tự đầu của mã màu thì chuỗi vẫn
 * "đọc được" — chỉ là sai, kiểu sai im lặng tệ nhất.
 *
 * KHÔNG đụng tới ô RACK và ô giả lập (đang gõ tay thì phải để yên), và không đụng khi có hộp
 * thoại/bảng đang mở.
 */
function batPhimLacRaNgoai(e: KeyboardEvent) {
  if (e.ctrlKey || e.altKey || e.metaKey || e.key.length !== 1) return;
  if (scanning.value || showQueue.value || thoai.value.hien) return;

  const dangO = document.activeElement as HTMLElement | null;
  if (dangO && (dangO.tagName === 'INPUT' || dangO.tagName === 'TEXTAREA' || dangO.isContentEditable)) return;

  const o = scanInputRef.value;
  if (!o) return;

  e.preventDefault();
  o.focus();
  o.value = e.key;
  o.setSelectionRange(1, 1);
}

function cancelAbandonedJob(jobId: string) {
  axios.post(`/api/weighing-jobs/${jobId}/cancel`).catch(() => {});
}

const handleBarcodeScan = async (token: string) => {
  /*
   * TUYỆT ĐỐI không thoát im lặng ở đây.
   *
   * Chưa nhận được trạm thì cú quét chết ngay tại dòng này: không bíp, không chữ, không gì cả —
   * mà bản thân cái máy quét vẫn kêu "bíp" của nó, nên thợ đinh ninh là đã quét xong và cứ đứng
   * quét lại. Đây là cách hỏng KHÓ ĐOÁN NHẤT trên màn này: mọi thứ khác trên màn hình vẫn chạy
   * bình thường.
   *
   * Trình duyệt nhận trạm bằng `adoptLocalWorkstation('LARGE')` lúc mở màn — nó hỏi backend "trạm
   * CÂN TO ở địa chỉ IP này là trạm nào?", và backend chỉ trả lời được nếu Agent "Cân to" đã báo
   * danh (60 giây một lần). Máy vừa cài lại Agent, Agent chưa kịp báo danh, hoặc service không
   * chạy đều rơi vào cảnh này.
   */
  if (!currentWorkstation.value) {
    scannerService.playBeep(600, 400);
    await baoTin(t('weighingStationLarge.noWorkstationDialog'));
    nextTick(veOQuet);
    return;
  }

  scanning.value = true;
  scannerService.playBeep(1200, 80); // bíp NGAY khi nhận mã, không đợi server

  // QUÉT KHÔNG CHẠM MẠNG: chuỗi QR đã chứa đủ rack/dye/weight của cả mẻ. Cả mẻ chỉ đi mạng đúng
  // MỘT lần lúc bấm SAVE — cũng chính là cách VBA gốc làm (btnSave_Click mới INSERT).
  // Định tuyến theo CẤU TRÚC chuỗi, không theo ký tự "#" đứng đầu — xem docQrMeNhuom().
  if (!/^DF:/i.test(token)) {
    const parsed = docQrMeNhuom(token);
    if (!parsed) {
      scannerService.playBeep(600, 400);
      scanning.value = false;
      // Kèm luôn chuỗi đọc được: ô quét bị đặt lại về mã màu ngay sau đây nên đây là chỗ DUY NHẤT
      // thợ/kỹ thuật còn thấy máy quét thực sự bắn ra cái gì.
      await baoTin(t('weighingStationLarge.scanParseFail', { token: `${token.slice(0, 80)}${token.length > 80 ? '…' : ''}` }));
      nextTick(veOQuet);
      return;
    }
    applyLocalJob(token, parsed);
    scanning.value = false;
    nextTick(veOQuet);
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
      chotMucTieuGoc();
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
    await baoTin(err.response?.data?.message || t('weighingStationLarge.scanOpenFail'));
  } finally {
    scanning.value = false;
    nextTick(veOQuet);
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
        tolerance_minus: target * TOLERANCE_RATIO,
        tolerance_plus: target * TOLERANCE_RATIO,
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
  chotMucTieuGoc();
}

/** Chốt lại mục tiêu ĐANG có của từng dòng làm "số của tem" để về sau biết dòng nào bị sửa tay. */
function chotMucTieuGoc() {
  const goc: Record<number, number | null> = {};
  jobItems.value.forEach((it: any, i: number) => {
    const v = it?.planned_weight;
    goc[i] = v === null || v === undefined || v === '' ? null : Number(v);
  });
  mucTieuGoc.value = goc;
}

function applyLocalJob(rawQr: string, parsed: ParsedDyeQr) {
  if (activeJob.value?.id) cancelAbandonedJob(activeJob.value.id);

  buildLocalJob(rawQr, parsed);
  currentIndex.value = -1;
  capturedWeights.value = {};
  capturedTare.value = {};
  manualRacks.value = {};
  nhapMucTieu.value = null;
  phienAo.value = null;
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
        ? t('weighingStationLarge.clearConfirmUnsaved')
        : t('weighingStationLarge.clearConfirmOrder')
    );
    if (!ok) return;
  }

  if (!alreadySaved && activeJob.value?.id) cancelAbandonedJob(activeJob.value.id);

  capturedWeights.value = {};
  capturedTare.value = {};
  manualRacks.value = {};
  currentIndex.value = -1;
  oNhap.value = null;
  mucTieuGoc.value = {};
  nhapMucTieu.value = null;
  phienAo.value = null;
  errorMsg.value = '';
  activeJob.value = null;
  activeBatch.value = null;
  armed.value = false; // AutoRunning = False
  pendingResume.value = null;
  resetTareForNewSlot();
  resetRackBatches(); // btnClear_Click của VBA cũng xoá rackBatch1/2 và hạ rackLoaded
  clearSession();
  // Bỏ luôn chốt chống-quét-hai-lần: sau CLEAR thì quét lại đúng con tem vừa xoá là thao tác
  // hợp lệ (xoá nhầm rồi làm lại), không được bắt thợ chờ hết 2 giây.
  tokenCuoi = '';
  nextTick(veOQuet); // veOQuet tự đặt lại nội dung ô = mã màu đang hiện, sau CLEAR là chuỗi rỗng
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
            // Mục tiêu sửa tay PHẢI đi lên server: server dựng lại mẻ từ chính chuỗi tem, nên
            // không gửi thì nó chấm ĐẠT/KHÔNG ĐẠT theo số của tem trong khi phiếu in trong tay thợ
            // ghi số đã sửa — hai bản ghi cùng một mẻ mà lệch nhau, không ai biết bên nào đúng.
            // Chỉ gửi dòng THỰC SỰ khác tem; server ghi Audit Log cho từng dòng như vậy.
            ...(daSuaMucTieu(idx) ? { planned_weight: item.planned_weight } : {}),
          };
        })
        .filter(Boolean);

  if (rows.length === 0) {
    if (!canTay) {
      errorMsg.value = t('weighingStationLarge.saveNoRows');
    } else if (!signalLive.value) {
      errorMsg.value = t('weighingStationLarge.saveSignalLost');
    } else if (!isStable.value) {
      errorMsg.value = t('weighingStationLarge.saveNotStable');
    } else {
      errorMsg.value = t('weighingStationLarge.saveEmptyScale');
    }
    return;
  }

  // KHÔNG mở sẵn cửa sổ in ở đây nữa (07/08/2026). Phiếu in bằng iframe ẩn ngay trong trang —
  // xem `inPhieuTrongTrang()`: không có cửa sổ mới nên không văng khỏi F11, và cũng hết ràng buộc
  // "user activation" bắt phải mở trước mọi `await`.
  const unweighed = rows.filter((r: any) => r.weight === null).length;
  if (unweighed > 0) {
    const ok = await hoiXacNhan(t('weighingStationLarge.unweighedConfirm', { count: unweighed }));
    if (!ok) return;
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
        ? t('weighingStationLarge.manualBatchLabel', { count: rows.length })
        : `${activeBatch.value?.color ?? ''} · ${activeBatch.value?.product_code ?? ''}`,
      currentWorkstation.value?.code || 'ws'
    );

    // IN NGAY, TRƯỚC khi chạm mạng — mọi dữ liệu cần in đã nằm trên màn hình.
    inPhieuTrongTrang(phieuCucBo);

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
        const loi: string = err?.response?.data?.message || t('weighingStationLarge.serverRejectFallback', { status });
        loiNghiepVu = loi;
        danhDauKet(item.idempotency_key, loi);
      }
      // Mất mạng / 5xx: để yên trong hàng đợi, tự đẩy sau.
    }

    saving.value = false;
    scannerService.playBeep(1800, 150);
    clearSession();
    await onClear(true, true);

    if (loiNghiepVu) {
      errorMsg.value = t('weighingStationLarge.saveRejected', { reason: loiNghiepVu });
    } else if (!daLenServer) {
      errorMsg.value = t('weighingStationLarge.saveQueuedOffline');
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

    const slipPayload = saveRes.data?.data?.slip?.label_payload;
    if (slipPayload) {
      inPhieuTrongTrang(slipPayload);
    } else {
      await printSlip();
    }
    await onClear(true, true);
  } catch (err: any) {
    // KHÔNG xoá capturedWeights khi lỗi — số vừa cân phải còn nguyên để bấm SAVE lại.
    errorMsg.value = err.response?.data?.message || t('weighingStationLarge.saveFailedFallback');
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
 * btnPrint_Click. In qua iframe ẩn trong chính trang này (`inPhieuTrongTrang`) — không mở cửa sổ
 * mới nên không văng khỏi F11, và gọi được ở bất kỳ đâu kể cả sau `await` (luồng SAVE).
 *
 * In VÔ ĐIỀU KIỆN đúng bản gốc: `btnPrint_Click` của workbook cân to (giống hệt workbook cân nhỏ)
 * đổ thẳng 9 ô `txt_rack/dye/weight/process` ra sheet, không hỏi trạm và không đòi phải có số cân
 * — form trắng vẫn ra một tờ 9 dòng trống. Mọi cửa chặn thêm đều biến nút PRINT thành nút im lặng.
 */
const printSlip = async () => {
  // Cân tay, mẻ đọc từ QR, và cả form trắng: đều dựng phiếu ngay tại trình duyệt từ 9 dòng đang
  // hiện. Chưa gắn trạm cũng rơi vào đây thay vì thoát im lặng: server cần `workstation_code` để
  // ghi PrintJob, nhưng tờ giấy thì không — dữ liệu để in đã nằm sẵn trên màn hình.
  if (!activeJob.value?.id || !currentWorkstation.value) {
    inPhieuTrongTrang(dungPhieuTuLuoi());
    return;
  }

  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print-slip`, {
      workstation_code: currentWorkstation.value.code,
    });
    inPhieuTrongTrang(res.data?.data?.label_payload || '');
  } catch (err: any) {
    await baoTin(err.response?.data?.message || t('weighingStationLarge.printSlipFail'));
  }
};

/**
 * Nút CHECK — mở LỊCH SỬ CÂN (`/weighing-history`) sang một TAB MỚI, giống hệt nút CHECK của
 * `/weighing-station-v2`.
 *
 * Tab mới chứ không phải điều hướng: màn này đang giữ nguyên cả mẻ dở lẫn số đã cân chưa lưu
 * (`capturedWeights` chỉ nằm trong RAM), rời trang là mất sạch. Mở tab riêng thì thợ tra cứu xong
 * đóng tab là quay lại đúng chỗ đang đứng, F11 của màn cân cũng không bị phá.
 *
 * Tài khoản trạm bị khoá công đoạn nên `/weighing-history` phải nằm trong `MAN_PHU_TRO` của
 * `router/index.ts` cho `/weighing-station-large` — thiếu là tab mới bị đá ngược về màn cân ngay
 * lúc mở.
 *
 * Trước 10/08/2026 nút này mở hộp "Tra cứu bán thành phẩm" (`WeighingCheckerModal`, tra theo
 * COLOR+CODE, KHÔNG có nút in lại). Component đó vẫn còn nguyên trong repo, chỉ không còn chỗ gọi
 * ở màn này.
 */
function moLichSuCan() {
  window.open('/weighing-history', '_blank');
}

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
  // Không dùng F11 thì phần lớn chỗ bị mất là padding của khung nội dung, không phải thanh trên.
  // Gỡ nó ra là mặt form tự lớn thêm mà thợ không phải bấm gì (yêu cầu 09/08/2026).
  document.body.classList.add(LOP_TRAN_VIEN);

  // 'LARGE': hỏi backend "trạm CÂN TO của máy này là trạm nào?" — máy cài cả 2 bộ Agent có 2
  // trạm cùng lúc, không nói rõ là nhận nhầm trạm cân nhỏ.
  adoptLocalWorkstation('LARGE').then((doi) => {
    if (doi) fetchLiveWeight();
  });
  restoreSession();
  fetchLiveWeight();
  capNhatNhipPoll();
  document.addEventListener('visibilitychange', onVisibilityChange);
  // `capture: true` để chạy TRƯỚC mọi thứ khác — kể cả bộ đệm máy quét toàn cục trong
  // services/scanner.ts, vốn cũng nghe keydown ở tầng window.
  document.addEventListener('keydown', batPhimLacRaNgoai, true);
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

  // Khung CHA (`.content-container` của AppLayout) đổi cỡ mà cửa sổ thì không: bật/tắt thanh trên
  // bằng nút "▾ Thanh trên", hiện/ẩn sidebar, vào/ra Toàn màn hình. Không nghe ở đây thì `resize`
  // không bắn và mặt form giữ nguyên cỡ cũ cho tới lần F5 kế — đúng cảnh hay gặp khi KHÔNG dùng
  // F11, vì lúc đó thanh trên là thứ thợ bật tắt suốt.
  const khung = rootRef.value?.parentElement;
  if (typeof ResizeObserver !== 'undefined' && khung) {
    roKhung = new ResizeObserver(fitAll);
    roKhung.observe(khung);
  }

  window.addEventListener('resize', fitAll);

  // txt_COLOR.SetFocus của UserForm_Activate
  nextTick(veOQuet);
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange);
  document.removeEventListener('keydown', batPhimLacRaNgoai, true);
  window.removeEventListener('resize', fitAll);
  ro?.disconnect();
  roKhung?.disconnect();
  document.body.classList.remove(LOP_TRAN_VIEN);
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
/*
 * Dải thông tin nằm BÊN PHẢI, không nằm dưới đáy (yêu cầu 09/08/2026).
 *
 * Không chỉ là chỗ đứng cho đẹp: mặt form tỉ lệ 979x728 (1.35) trên màn 16:9 (1.78) LUÔN bị chặn
 * theo chiều cao, tức mỗi pixel dọc lấy được đổi thẳng thành cỡ chữ, còn chỗ thừa theo chiều
 * ngang thì không dùng vào việc gì. Đưa dải sang phải là trả lại 34px dọc và tiêu tốn phần bề
 * ngang vốn đang bỏ không.
 */
.wsl-root {
  height: 100%;
  display: flex;
  flex-direction: row;
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

/*
 * Các nấc trên 100% cố ý để mặt form TRÀN khỏi khung — đổi lại là phải cuộn mới thấy hết.
 *
 * `display: block` chứ không giữ flex: một flex item bị căn giữa mà lớn hơn khung thì phần tràn
 * bị cắt ở MÉP TRÊN và MÉP TRÁI, và không có cách nào cuộn tới được (`safe center` chưa dùng
 * được vì bản build còn hạ mục tiêu xuống Chrome đời cũ). Khối thường + `margin: 0 auto` thì
 * căn ngang vẫn giữa, còn chiều dọc bắt đầu từ mép trên và cuộn được bình thường.
 */
.stage-wrap.zoomed {
  display: block;
  overflow: auto;
}

.stage-wrap.zoomed .stage-fit { margin: 0 auto; }

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

/* Mục tiêu sửa được (chưa bấm NEXT): tô nhẹ để thợ thấy đây là ô GÕ ĐƯỢC, không phải số chỉ để đọc
   như DYE CODE ngay bên cạnh. Bấm NEXT xong ô nhận lại lớp `.ro` và trở về hình dáng cũ. */
.vv-text.plan:not(.ro) {
  color: #0d1520;
  cursor: text;
  border-color: #b9c4d6;
  border-style: dashed;
}

/* Mục tiêu KHÁC số in trên tem — số này sẽ theo lên phiếu in và xuống DB, phải nhìn ra ngay từ xa. */
.vv-text.plan.sua {
  background: #fff3d6;
  border-color: #d99b00;
  border-style: solid;
  color: #7a4f00;
  font-weight: 800;
}

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
/*
 * Cột dọc sát mép phải. BỀ NGANG cố định là điểm mấu chốt: `.stage-wrap` là flex:1 nên mọi thay
 * đổi bề ngang ở đây đều làm `fitStage` tính lại `scale` và cả mặt form co/phình theo. Vì thế
 * chiều ngang không bao giờ được co giãn theo nội dung — chữ dài thì XUỐNG DÒNG trong cột, việc
 * mà bản nằm ngang trước đây không làm được (xuống dòng là cao gấp đôi, mặt form thụt ngay).
 *
 * 208px: vừa đủ cho mã trạm dài nhất đang có (WS-LARGE-ZP-IT003) trên một dòng.
 */
/* Dải mỏng thay chỗ cột thông tin khi thu gọn — 26px so với 208px. */
.webbar-collapsed {
  flex: 0 0 26px;
  width: 26px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding-top: 10px;
  background: rgba(12, 16, 26, 0.72);
  border-left: 1px solid rgba(255, 255, 255, 0.07);
}

.wb-toggle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 8px 2px;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.05);
  color: #c9d1e2;
  font: 600 10px/1 'Inter', 'Segoe UI', sans-serif;
  letter-spacing: 0.06em;
  cursor: pointer;
}

.wb-toggle:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.wb-toggle-icon { font-size: 12px; }

/* Chữ dọc để nhét vừa dải 26px mà vẫn đọc được — không dùng ảnh/icon rời. */
.wb-toggle-label {
  writing-mode: vertical-rl;
  text-orientation: upright;
}

/* Có sự cố: dải mỏng tự đỏ và nhấp nháy. Đây là thứ giữ cho việc thu gọn KHÔNG nuốt mất tín
   hiệu hỏng — thợ vẫn thấy có chuyện, chỉ là phải bấm mới xem được chi tiết. */
.webbar-collapsed.alarm {
  background: rgba(120, 20, 24, 0.85);
  border-left-color: #ff5f57;
}

.webbar-collapsed.alarm .wb-toggle {
  border-color: #ff5f57;
  background: rgba(255, 95, 87, 0.18);
  color: #ffd9d7;
  animation: wb-alarm 1.1s ease-in-out infinite;
}

@keyframes wb-alarm {
  50% { background: rgba(255, 95, 87, 0.45); color: #fff; }
}

.wb-hide {
  align-self: flex-end;
  padding: 3px 8px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 5px;
  background: rgba(255, 255, 255, 0.05);
  color: #9fb0c9;
  font: 600 10px/1 'Inter', 'Segoe UI', sans-serif;
  letter-spacing: 0.05em;
  cursor: pointer;
}

.wb-hide:hover {
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
}

.webbar {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 9px;
  flex: 0 0 208px;
  width: 208px;
  /* Chừa chỗ cho nút "⛶ Toàn màn hình" nổi cố định ở góc phải dưới — không có nó thì nút đè lên
     đúng ô thông báo lỗi, thứ bắt buộc phải đọc được. */
  padding: 12px 12px 52px;
  overflow-y: auto;
  overflow-x: hidden;
  background: rgba(12, 16, 26, 0.72);
  border-left: 1px solid rgba(255, 255, 255, 0.07);
  color: #c9d1e2;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  font-size: 12px;
  transition: background 0.15s ease;
}

/* Có lỗi thì đổi màu CẢ cột — nổi hơn hẳn một dòng chữ đỏ, mà không tốn thêm chỗ nào. */
.webbar.alarm {
  background: #4d1f1a;
  border-left-color: #e2564a;
  color: #ffc9c3;
}

.webbar > * { flex: 0 0 auto; min-width: 0; }

/* Đẩy xuống đáy cột: thông báo là thứ hiện/ẩn liên tục, để nó trôi ngay dưới các mục cố định thì
   mỗi lần có/hết thông báo là cả cụm bên trên nhảy vị trí. Neo ở đáy thì không gì xê dịch. */
.wb-msg {
  margin-top: auto;
  padding-top: 8px;
  font-weight: 700;
  /* Có cả chiều cao để mà xuống dòng — không cắt bằng ellipsis nữa, đọc trọn lý do ngay tại chỗ
     thay vì phải rê chuột xem tooltip. */
  white-space: normal;
  overflow-wrap: anywhere;
  line-height: 1.35;
}
.wb-msg.bad { color: #ffd9d4; }

.wb-ws { display: flex; align-items: center; gap: 6px; font-weight: 700; overflow-wrap: anywhere; }
.wb-dot { flex: 0 0 auto; width: 9px; height: 9px; border-radius: 50%; background: #7a8296; }
.wb-dot.on { background: #35c46a; }
.wb-dot.off { background: #e2564a; }

.wb-pill {
  padding: 3px 8px;
  border-radius: 999px;
  background: #3c455c;
  font-weight: 700;
  text-align: center;
}
.wb-pill.ok { background: #1e5c36; color: #b9f2ce; }
.wb-pill.wait { background: #5c4a17; color: #ffe6a3; }

.wb-tare { opacity: 0.85; }

/* Cột dọc có bề ngang cố định nên danh sách 6 mã rack được XUỐNG DÒNG cho hiện đủ — bản nằm
   ngang trước đây phải cắt bằng ellipsis, mà cắt đúng cái danh sách sắp bắn sang hệ pha màu là
   thứ tệ nhất để giấu. */
.wb-rack {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
  padding: 5px 8px;
  border-radius: 6px;
  background: #3c455c;
  font-weight: 700;
  overflow-wrap: anywhere;
  line-height: 1.4;
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
/* Cỡ hiển thị — xem ghi chú dài ở phần script (MUC_PHONG). */
.zoom-ctl { display: flex; align-items: center; justify-content: space-between; gap: 5px; }

/* Con số vừa là nhãn vừa là nút "về vừa màn hình": thợ bấm quá tay lên 175% rồi phải bấm A− bốn
   lần mới về được là thừa, mà thêm hẳn một nút RESET nữa thì dải này đã chật sẵn. */
.zoom-val {
  min-width: 46px;
  padding: 2px 4px;
  border: 1px solid transparent;
  border-radius: 5px;
  background: none;
  color: inherit;
  font: inherit;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}

.zoom-val:hover { border-color: #6a7490; }

.wb-sim { display: flex; align-items: center; gap: 5px; }

.wb-siminput {
  width: 100%;
  height: 26px;
  border: 1px solid #6a7490;
  border-radius: 5px;
  background: #1f2534;
  color: #eef1f7;
  padding: 0 8px;
  font-weight: 700;
}

/*
 * Màn hẹp (tablet dựng đứng, cửa sổ chia đôi): trả dải về ĐÁY.
 *
 * Cột phải chỉ có lãi khi màn rộng hơn tỉ lệ của mặt form — lúc đó nó tiêu vào phần bề ngang đang
 * bỏ không. Dưới ngưỡng này thì mặt form đã bị chặn theo chiều NGANG, cắt thêm 208px bề ngang là
 * mặt form nhỏ đi thật, đúng ngược với mục đích.
 */
@media (max-width: 1000px) {
  .wsl-root { flex-direction: column; }

  .webbar {
    flex: 0 0 auto;
    width: auto;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 10px;
    padding: 6px 12px;
    border-left: none;
    border-top: 1px solid rgba(255, 255, 255, 0.07);
  }

  .webbar.alarm { border-top-color: #e2564a; }

  .wb-msg { margin-top: 0; padding-top: 0; flex: 1 1 100%; }
  .wb-siminput { width: 90px; }
  .zoom-ctl { justify-content: flex-start; }
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

<!--
  KHÔNG `scoped`: quy tắc này phải với tới `.content-container` của AppLayout — phần tử CHA của
  màn hình này, nằm ngoài tầm của style scoped.

  Vì sao gỡ padding: không dùng F11 thì mặt form bị bó trong khung nội dung có sẵn padding 24px
  mỗi bên. Mà form là khổ cố định tỉ lệ 979x728, luôn bị chặn theo CHIỀU CAO — nên 48px dọc đó
  đổi thẳng thành ~6% cỡ chữ trên toàn bộ mặt form, không mất đi đâu cả.

  Ràng buộc bằng lớp trên `<body>` do chính màn này gắn/gỡ (xem LOP_TRAN_VIEN), nên các trang khác
  giữ nguyên padding của chúng.
-->
<style>
body.df-man-can-tran-vien .content-container {
  padding: 0;
  /* Mặt form đã tự thu cho vừa khung nên không bao giờ cần cuộn ở tầng này; để `auto` thì lúc đo
     hụt một hai pixel là hiện một thanh cuộn dọc mảnh, và thanh đó lại ăn bớt bề ngang. */
  overflow: hidden;
}
</style>
