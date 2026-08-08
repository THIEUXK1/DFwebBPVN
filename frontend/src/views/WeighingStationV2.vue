<template>
  <!-- `zoom` phóng to CẢ màn hình cân theo nấc thợ chọn (xem doiCoManHinh). `--df-zoom` để các
       chỗ dùng vw/vh chia ngược lại — zoom nhân cả đơn vị theo màn hình, quên chia là hộp thoại
       rộng hơn màn hình thật. -->
  <div class="ws2-root" :style="{ zoom: mucPhongTo, '--df-zoom': mucPhongTo }">
    <!-- Form LUÔN hiện sẵn ngay khi vào màn, kể cả chưa có mẻ nào — đúng app VBA gốc: form
         mở là đứng đó với các ô trống, thao tác viên quét thẳng vào ô COLOR để nạp đơn
         (txt_color_AfterUpdate). Không còn màn quét riêng chắn phía trước. -->

      <!-- ===== BĂNG TRÊN — port đúng scaleform.frm ===== -->
      <div class="ws2-topband">
        <!-- 4 ô thông tin: COLOR/MACHINE hàng trên, CODE/LV hàng dưới -->
        <div class="ws2-fields panel">
          <div class="fld">
            <label>COLOR — quét mã vào đây</label>
            <!-- KHÔNG dùng v-model: máy quét kiểu "giả bàn phím" bắn cả trăm ký tự trong vài
                 chục mili-giây; nếu mỗi ký tự làm Vue re-render thì trình duyệt xử lý không
                 kịp và RỚT ký tự giữa chừng (lỗi thật đã gặp ở /production-batches). Để input
                 tự do (uncontrolled), chỉ đọc .value một lần khi Enter. :value chỉ dùng để
                 hiển thị lại mã màu sau khi nạp được đơn. -->
            <input
              ref="scanInputRef"
              class="vba-txt txt-color"
              :class="{ scanning }"
              :value="activeBatch?.color || ''"
              :placeholder="scanning ? 'Đang nạp đơn…' : (activeJob ? '' : 'Quét QR...')"
              :readonly="scanning"
              @keyup.enter="onScanEnter"
            />
          </div>
          <div class="fld">
            <label>MACHINE</label>
            <input class="vba-txt txt-sm" :value="activeBatch?.machine?.code || ''" readonly />
          </div>
          <div class="fld">
            <label>CODE</label>
            <input class="vba-txt" :value="activeBatch?.product_code || ''" readonly />
          </div>
          <div class="fld">
            <label>LV</label>
            <input class="vba-txt txt-sm" :value="activeBatch?.level_code || ''" readonly />
          </div>
        </div>

        <!-- delta_rawline: số DELTA (đã trừ bì) cỡ lớn — số thao tác viên thực sự nhìn.
             Khung tô theo ĐÚNG 3 màu dung sai của ô PROCESS (chung một hàm suy màu, xem
             utils/processColor): thợ đang đổ vật tư thì mắt dán vào số to này, bắt họ liếc
             xuống bảng mới biết đủ hay chưa là thừa một nhịp. -->
        <div class="ws2-delta panel" :class="'tone-' + deltaTone">
          <div class="delta-band">
            <span class="delta-caption">DELTA — đã trừ bì</span>
            <span v-if="currentTarget !== null" class="delta-target">
              mục tiêu {{ currentTarget.toFixed(2) }}
            </span>
          </div>
          <div class="delta-body">
            <div class="delta-value" :class="{ dead: signalLost && !useSimValue }">
              {{ liveWeight.toFixed(2) }}
            </div>
            <div class="delta-sub">
              <!-- Mất tín hiệu là một trạng thái RIÊNG, không được gộp vào "chờ ổn định": số
                   đang hiện là số CŨ đông cứng, không phải số đang dao động.
                   Dùng `signalLost` (ngưỡng 3s) chứ KHÔNG dùng `signalLive` (ngưỡng 1.5s dành
                   cho cổng an toàn) — xem ghi chú LOST_SIGNAL_MS trong useScaleFeed. -->
              <span v-if="signalLost && !useSimValue" class="pill dead">✕ MẤT TÍN HIỆU</span>
              <span v-else class="pill" :class="isStable ? 'ok' : 'wait'">
                {{ isStable ? '● ỔN ĐỊNH' : '○ CHỜ ỔN ĐỊNH' }}
              </span>
              <span v-if="tareBaseline !== null" class="delta-tare">Bì {{ tareBaseline.toFixed(2) }}</span>
              <span v-else-if="currentIndex >= 0" class="delta-tare">chờ chốt bì</span>
            </div>
          </div>
        </div>

        <!-- Nhóm nút lớn — kích thước bám đúng form gốc để bấm được bằng găng tay -->
        <div class="ws2-buttons">
          <!-- onClear() có ngoặc: nếu để "onClear" trần thì Vue truyền MouseEvent vào tham số
               skipConfirm (truthy) -> bỏ qua luôn hộp xác nhận. -->
          <button class="vba-btn big danger" @click="onClear()" :disabled="saving">CLEAR</button>
          <!-- Không khoá theo "đã cân ô nào chưa": VBA cho SAVE bất cứ lúc nào, mọi dòng có
               WEIGHT mục tiêu đều được ghi (ô chưa cân -> REJECTED).
               Cũng KHÔNG khoá theo "đã có đơn chưa" (2026-08-02): cân tay lưu được, và chính điều
               kiện `!activeJob` cũ đã khoá chặt toàn bộ đường đó lại. Lý do không lưu được thì
               `onSave` nói rõ từng trường hợp (mất tín hiệu / chưa đứng yên / cân rỗng) — nói ra
               được vẫn hơn một cái nút xám không giải thích gì. -->
          <button class="vba-btn big primary" @click="onSave" :disabled="saving">
            {{ saving ? '…' : 'SAVE' }}
          </button>
          <!-- KHÔNG khoá theo "đã có đơn chưa": bấm NEXT trên form trắng là dùng màn hình như một
               cái cân thường (yêu cầu 2026-08-02). Không lưu gì cả, xem cảnh báo dưới lưới. -->
          <button class="vba-btn big accent" @click="onNext" :disabled="saving || !canPressNext">NEXT</button>
          <button class="vba-btn sm" @click="printSlip()">PRINT</button>
          <!-- CHECK mở LỊCH SỬ CÂN sang tab mới (yêu cầu 07/08/2026) — xem moLichSuCan(). -->
          <button class="vba-btn sm" @click="moLichSuCan()">CHECK</button>
          <button class="vba-btn wide" @click="onClose">CLOSE</button>
        </div>
      </div>

      <!-- rawline: số THÔ trước khi lọc + công tắc giả lập (giữ lại để UAT khi chưa có cân) -->
      <div class="ws2-rawline">
        <span class="raw-label">RAW</span>
        <span class="raw-value">{{ grossWeight.toFixed(2) }}</span>
        <span class="raw-ws">
          <span class="raw-dot" :class="scaleOnline && !signalLost ? 'on' : 'off'"></span>
          {{ currentWorkstation?.code || 'chưa gán trạm' }}
        </span>

        <!-- Đang chạy đường nào (ADR-013). Phải nhìn thấy được: đường cục bộ chết là số cân chậm
             lại 5-10 lần mà KHÔNG có lỗi nào hiện ra, không có chỉ báo này thì không ai đoán nổi
             vì sao mặt số bỗng ì đi. -->
        <span class="raw-nguon" :class="nguonCucBo ? 'nhanh' : 'cham'"
              :title="nguonCucBo
                ? 'Đọc thẳng Agent trên máy này (~70ms) — nhanh như bản Excel VBA'
                : 'Đang vòng qua máy chủ (~400-900ms). Kiểm tra service DFAgent trên máy này nếu muốn nhanh hơn.'">
          {{ nguonCucBo ? '⚡ Agent tại chỗ' : '☁ qua máy chủ' }}
        </span>

        <!-- HAI sự cố khác hẳn nhau, cách xử lý cũng khác, nên phải nói rõ là cái nào:
             · gọi được backend nhưng số đã cũ  -> Agent/PuTTY/dây cân
             · KHÔNG gọi được backend           -> mạng hoặc máy chủ
             Trước 2026-08-02 chỉ có vế đầu, mà vế đầu lại đòi `scaleOnline === true` — nên đúng
             lúc mất mạng thì màn hình IM LẶNG hoàn toàn, chỉ còn một chấm đỏ 9px. -->
        <span v-if="!useSimValue && scaleOnline && signalLost" class="raw-warn">
          ⚠ MẤT TÍN HIỆU CÂN — kiểm tra Agent / PuTTY / dây cân
          <!-- Tuổi số đọc: phân biệt "Agent chết hẳn" (số nhảy lên vô hạn) với "Agent còn sống
               nhưng số về chậm" (số dừng quanh vài giây) — hai thứ này sửa ở hai chỗ khác nhau. -->
          <em v-if="readingAgeMs !== null"> (số cân cũ {{ (readingAgeMs / 1000).toFixed(1) }}s)</em>
        </span>
        <span v-else-if="!useSimValue && !scaleOnline" class="raw-warn">
          ⚠ MẤT KẾT NỐI MÁY CHỦ — số cân không cập nhật. Số đã cân KHÔNG mất, cân lại được ngay
          khi có mạng.
        </span>

        <!-- Bì giờ TỰ ĐỘNG lấy ở lần đọc ổn định đầu tiên sau NEXT (đúng VBA) — nút này chỉ
             là van an toàn khi bì bị chốt nhầm, không nằm trong luồng thao tác bình thường. -->
        <button
          v-if="currentIndex >= 0 && tareBaseline !== null"
          class="vba-btn tiny"
          @click="retare"
          title="Bì bị chốt nhầm? Bỏ bì hiện tại, lần đọc ổn định kế tiếp sẽ tự lấy bì mới"
        >
          BÌ LẠI
        </button>

        <!-- Chỉ báo hàng đợi — luôn nằm cạnh đèn tín hiệu cân để thợ thấy cùng một chỗ. Ẩn hẳn
             khi hàng đợi rỗng, đó là trạng thái bình thường và không cần chiếm chỗ. -->
        <button
          v-if="queueCount > 0"
          class="queue-chip"
          :class="{ stuck: stuckCount > 0 }"
          :title="stuckCount > 0 ? 'Máy chủ từ chối — vẫn đang tự gửi lại, bấm để xem lý do' : 'Mẻ chưa gửi được, sẽ tự gửi khi có mạng — bấm để xem'"
          @click="showQueue = true"
        >
          {{ stuckCount > 0 ? '✕' : '⏳' }} {{ queueCount }} mẻ chờ gửi
          <span v-if="flushing" class="queue-spin">đang gửi…</span>
          <span v-else-if="!duongThong" class="queue-spin">mất kết nối</span>
        </button>

        <!-- Cỡ hiển thị — để ở đây thay vì trong một trang cài đặt: thợ đứng cân, đeo găng, cần
             chỉnh được ngay tại chỗ khi đổi ca hoặc đổi người. -->
        <span class="zoom-ctl" title="Phóng to/thu nhỏ toàn màn hình cân (máy này nhớ lựa chọn)">
          <button class="vba-btn tiny" :disabled="mucPhongTo <= MUC_PHONG[0]" @click="doiCoManHinh(-1)">A−</button>
          <b>{{ Math.round(mucPhongTo * 100) }}%</b>
          <button class="vba-btn tiny" :disabled="mucPhongTo >= MUC_PHONG[MUC_PHONG.length - 1]" @click="doiCoManHinh(1)">A+</button>
        </span>

        <label class="sim-toggle">
          <input type="checkbox" v-model="useSimValue" /> giả lập
        </label>
        <input
          v-if="useSimValue"
          type="number"
          step="0.1"
          class="sim-input"
          v-model.number="simulatedWeight"
        />
      </div>

      <!-- ===== BẢNG 9 DÒNG ===== -->
      <VbaRackGrid
        class="ws2-grid"
        :items="jobItems"
        :current-index="currentIndex"
        :captured-weights="capturedWeights"
        :live-weight="liveWeight"
        :manual-racks="manualRacks"
        :allow-manual-rack="!activeJob"
        @select="onSelectRow"
        @update-rack="onUpdateRack"
      />

      <p v-if="onBlankRow" class="ws2-notice">
        <template v-if="!activeJob">
          ℹ Đang <strong>cân tay</strong> (chưa quét đơn nào) — SAVE vẫn in phiếu và lưu bình thường,
          chỉ không có màu/mã hàng/mục tiêu để đối chiếu. Quét mã QR nếu cần cân theo đơn.
        </template>
        <template v-else>
          ⚠ Dòng {{ currentIndex + 1 }} không có vật tư trong mã QR — cân được để tham khảo, nhưng
          <strong>SAVE sẽ không lưu số ở dòng này</strong>.
        </template>
      </p>
      <p v-if="parallelNotice" class="ws2-notice">ℹ {{ parallelNotice }}</p>
      <p v-if="errorMsg" class="ws2-error">❌ {{ errorMsg }}</p>
      <p v-else-if="!activeJob" class="ws2-hint">
        Chưa có mẻ nào — đưa con trỏ vào ô <strong>COLOR</strong> rồi quét mã QR trên phiếu để nạp đơn.
        Cần cân tạm thì <strong>không cần đơn</strong>: đặt vật tư lên cân, thấy số ở ô
        <strong>RAW</strong> là bấm <strong>SAVE</strong> lưu và in phiếu được ngay. Muốn cân nhiều
        thứ thành một phiếu thì bấm <strong>NEXT</strong> cho từng thứ rồi mới SAVE.
      </p>
      <p v-else class="ws2-hint">
        Bấm <strong>NEXT</strong> để bắt đầu ô 1. Mỗi lần NEXT: chốt số ô đang cân rồi sang ô kế và lấy bì mới.
        Cân xong hết thì bấm <strong>SAVE</strong> để lưu cả mẻ.
      </p>

    <!-- Bảng hàng đợi — chỉ mở khi thợ chủ động bấm, không tự bật lên chắn màn hình cân. -->
    <div v-if="showQueue" class="queue-overlay" @click.self="showQueue = false">
      <div class="queue-modal">
        <div class="queue-head">
          <strong>Mẻ chờ gửi lên máy chủ ({{ queueCount }})</strong>
          <button class="vba-btn tiny" @click="showQueue = false">ĐÓNG</button>
        </div>

        <p class="queue-note">
          Các mẻ này <strong>đã cân xong và đã in phiếu</strong>, chỉ chưa lên được máy chủ. Máy tự
          gửi lại khi có mạng. Mẻ nào hỏng hẳn (quét nhầm, phiếu bỏ đi) thì bấm <strong>BỎ MẺ</strong>
          — mẻ bị bỏ vẫn được giữ vết trong máy để đối chiếu với tờ phiếu đã in.
          <strong>Đừng xoá dữ liệu trình duyệt</strong> khi danh sách còn mẻ.
        </p>

        <!-- Kết quả của lần bấm THỬ LẠI/BỎ MẺ gần nhất. Bắt buộc phải có: trước đây bấm THỬ LẠI
             xong màn hình im lặng, thợ không biết mẻ đã lên server hay vẫn kẹt y như cũ. -->
        <p v-if="queueMsg" class="queue-msg" :class="queueMsgOk ? 'ok' : 'bad'">
          {{ queueMsgOk ? '✔' : '❌' }} {{ queueMsg }}
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
              <!-- THỬ LẠI hiện cho MỌI mẻ (không chỉ mẻ bị server chê): mẻ đang chờ vì mất mạng
                   cũng cần một đường bấm tay để biết ngay đường đã thông chưa. BỎ MẺ là đường
                   thoát cuối cho mẻ hỏng hẳn — có hỏi xác nhận và có lưu vết (xem saveQueue). -->
              <td class="queue-act">
                <button class="vba-btn tiny" :disabled="flushing" @click="onThuLai(q.idempotency_key)">
                  THỬ LẠI
                </button>
                <button class="vba-btn tiny danger" :disabled="flushing" @click="onBoMe(q)">
                  BỎ MẺ
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="queue-foot">
          <button class="vba-btn tiny" :disabled="flushing" @click="onGuiNgay">
            {{ flushing ? 'ĐANG GỬI…' : 'GỬI NGAY' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Hộp thoại thay alert/confirm — xem composables/useHopThoai.ts. -->
    <HopThoaiVba :thoai="thoai" @dong="dongThoai" />

    <!-- z-index 40: dưới lớp phủ bảng hàng đợi (.queue-overlay = 50) -->
    <FullscreenButton variant="vba" :z-index="40" />
  </div>
</template>

<script setup lang="ts">
// /weighing-station-v2 — dựng lại giao diện VÀ cách thao tác của UserForm `scaleform` trong
// workbook VBA "4.semiauto-small scale - delta-stable-final_DF026-027.xlsm".
//
// Khác bản cũ /weighing-station ở 3 điểm cốt lõi (đều theo yêu cầu người dùng 2026-07-31):
//   1. 9 dòng hiển thị song song, thao tác theo NEXT chạy hết ô rồi mới SAVE cả mẻ 1 lần
//      (bản cũ: cân 1 vật tư -> lưu ngay từng dòng).
//   2. Chiếm trọn màn hình, ẩn menu — form VBA gốc ẩn hẳn Excel (Application.Visible=False).
//   3. Ô PROCESS tô đúng mã màu RGB gốc của Mod_UI_processcolor.CheckRange.
//
// ĐÁNH ĐỔI ĐÃ BIẾT của mô hình VBA: giá trị 9 ô chỉ nằm trên client tới lúc bấm SAVE — đóng
// trình duyệt/mất điện giữa chừng là mất hết số vừa cân. Bản cũ không có rủi ro này vì lưu
// ngay từng dòng. Người dùng đã chọn phương án này khi duyệt kế hoạch.

import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import VbaRackGrid from '../components/weighing/VbaRackGrid.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import HopThoaiVba from '../components/HopThoaiVba.vue';
import { useHopThoai } from '../composables/useHopThoai';
import { currentWorkstation, adoptLocalWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { isFullscreen } from '../services/layout';
import { useScaleFeed } from '../composables/useScaleFeed';
import { inPhieuTrongTrang } from '../utils/slipPrint';
import { parseDyeQr, MAX_RACK_LINES, type ParsedDyeQr } from '../utils/qrDyeParser';
import { processTone } from '../utils/processColor';
import { buildSlipHtml, processStatus, nowSlipTimestamp, MANUAL_MATERIAL_CODE } from '../utils/weighSlip';
import {
  xepHang, danhDauDaGui, danhDauKet, dayHangDoi, danhSachChoGui, guiMotNgay, boMe,
  batTuDay, tatTuDay, queueCount, stuckCount, flushing, duongThong,
} from '../services/saveQueue';

const router = useRouter();

const {
  liveWeight, grossWeight, isStable, scaleOnline, nguonCucBo, signalLive, signalLost, readingAgeMs, tareBaseline, armed,
  useSimValue, simulatedWeight,
  retare, resetTareForNewSlot, fetchLiveWeight, startPolling, stopPolling,
  // 'SMALL' = chỉ nhận số từ bộ cài Agent "Cân nhỏ" (service DFAgentSmall, mã trạm WS-SCALE-*).
  // Cũng là giá trị mặc định, nhưng ghi tường minh để đọc màn nào biết ngay màn đó cắm vào
  // cái cân nào — hai bộ cài Agent chạy song song được trên cùng một máy.
} = useScaleFeed('SMALL');

const activeJob = ref<any | null>(null);
const activeBatch = ref<any | null>(null);
const showQueue = ref(false);
const queueItems = ref<ReturnType<typeof danhSachChoGui>>([]);
/** Kết quả lần bấm THỬ LẠI / BỎ MẺ / GỬI NGAY gần nhất — hiện ngay trong bảng hàng đợi. */
const queueMsg = ref('');
const queueMsgOk = ref(false);
let queueMsgTimer: ReturnType<typeof setTimeout> | null = null;
const saving = ref(false);
const errorMsg = ref('');

/**
 * Ghi chú "đơn này cũng đang được cân ở máy khác" — server phát khi 2 máy quét trùng đơn.
 * Không phải lỗi: mỗi máy có vòng cân riêng, cả hai đều lưu được đầy đủ.
 */
const parallelNotice = ref('');

/** Ô đang cân (0-based). -1 = chưa bấm NEXT lần nào (AutoRunning=False trong VBA). */
const currentIndex = ref(-1);
/** Giá trị delta đã chốt cho từng ô — giữ ở client tới lúc SAVE, đúng biến p1..p9 của VBA. */
const capturedWeights = ref<Record<number, number>>({});
/**
 * Bì/cân gộp tại thời điểm chốt TỪNG ô. Phải lưu riêng theo ô: mỗi lần NEXT là lấy bì mới,
 * nên tới lúc SAVE thì tareBaseline chỉ còn là bì của ô CUỐI — dùng nó cho cả 9 dòng sẽ ghi
 * sai trường audit tare_weight/gross_weight của các ô trước đó.
 */
const capturedTare = ref<Record<number, { tare: number | null; gross: number }>>({});

/**
 * Mã RACK gõ tay cho các dòng KHÔNG có vật tư trong đơn (cân tay, hoặc dòng trống bên dưới đơn).
 *
 * Phải để riêng vì `onUpdateRack` bình thường ghi thẳng vào `jobItems[idx].rack_code`, mà những
 * dòng này không có item nào để ghi vào — trước đây gõ xong là rơi vào hư không.
 */
const manualRacks = ref<Record<number, string>>({});

const jobItems = computed(() => activeJob.value?.items || []);
/**
 * VBA: bấm NEXT tới ô 9 là hết (`CurrentBoxIndex < 9` mới tăng tiếp) — mốc là 9 DÒNG LƯỚI, không
 * phải số vật tư trong đơn. Trước đây chặn theo `jobItems.length` nên QR 3 dòng là NEXT tắt ngay ở
 * R3; theo yêu cầu 2026-08-02 trả lại đúng hành vi gốc: chạy tiếp xuống các dòng trống bên dưới.
 */
const canPressNext = computed(() => currentIndex.value < MAX_RACK_LINES - 1);

/** Đang đứng ở dòng KHÔNG có vật tư nào trong đơn — số cân ở đó không được SAVE ghi lại. */
const onBlankRow = computed(() => currentIndex.value >= 0 && !jobItems.value[currentIndex.value]);

/** Mục tiêu của ô đang cân — hiện ngay cạnh số DELTA để khỏi phải liếc xuống bảng. */
const currentTarget = computed<number | null>(() => {
  const item = currentIndex.value >= 0 ? jobItems.value[currentIndex.value] : null;
  const t = item ? Number(item.planned_weight) : NaN;
  return Number.isFinite(t) && t > 0 ? t : null;
});

/**
 * Màu của ô số DELTA — CÙNG hàm suy màu với ô PROCESS trong lưới (utils/processColor), nên hai
 * chỗ không thể báo khác nhau.
 *
 * Chưa chốt bì thì để trung tính: lúc đó `liveWeight` chưa trừ được gì nên tô màu chỉ là đoán
 * bừa, mà một ô to đùng màu vàng "chưa đủ" ngay khi vừa bấm NEXT thì gây hiểu nhầm hơn là giúp.
 */
const deltaTone = computed(() => {
  if (tareBaseline.value === null) return 'none';
  // Mất tín hiệu -> bỏ màu. Số đang hiện là số CŨ đông cứng; tô xanh "ĐẠT" cho một con số không
  // còn phản ánh cái đĩa cân là kiểu nói dối nguy hiểm nhất trên màn hình này.
  //
  // Dùng ngưỡng BÁO (3s) chứ không phải ngưỡng an toàn (1.5s): màu ô delta là thứ thợ nhìn liên
  // tục trong lúc đổ vật tư, nhấp nháy mất màu mỗi lần số về trễ một nhịp còn khó chịu hơn cả
  // dòng cảnh báo. Cổng an toàn thật (không cho chốt bì/lưu số cũ) vẫn nguyên ở `signalLive`.
  if (signalLost.value && !useSimValue.value) return 'none';
  return processTone(liveWeight.value, currentTarget.value);
});

/**
 * Bỏ một vòng cân CHƯA HỀ GHI GÌ xuống server (2026-08-01) — dọn dấu vết khi thao tác viên quét
 * nhầm đơn/đổi ý và rời đi trước khi bấm SAVE. Kể từ khi mỗi máy có vòng cân riêng, mỗi lần bỏ
 * dở như vậy để lại một WeighingJob mồ côi khiến lô KHÔNG BAO GIỜ về được WEIGHED (xem
 * WeighingJobController::cancel).
 *
 * Best-effort, không chặn thao tác của thợ: chạy nền, nuốt lỗi. Server tự đối chiếu — chỉ hủy
 * khi job CHƯA có dòng nào COMPLETED, nên gọi thừa (job đã có số cân thật, hoặc job đã COMPLETED)
 * là vô hại, backend tự chối mà không ảnh hưởng gì tới thao tác viên.
 */
function cancelAbandonedJob(jobId: string) {
  axios.post(`/api/weighing-jobs/${jobId}/cancel`).catch(() => {});
}

/*
 * Nhịp lấy số cân đổi theo trạng thái màn hình (2026-08-02).
 *
 * 200ms khi ĐANG CÂN là bắt buộc: bì được chốt từ lần đọc ổn định ĐẦU TIÊN sau khi bấm NEXT, nhịp
 * càng thưa thì càng dễ chốt bì vào lúc thợ đã bắt đầu đổ vật tư — bì tính luôn cả phần vừa đổ,
 * delta hiển thị thiếu, thợ đổ dư mà màn hình vẫn báo chưa đủ.
 *
 * Nhưng khi màn hình đứng yên chờ quét thì không có gì để cân, giữ 200ms chỉ tổ dội 5 request/giây
 * vào backend. Backend dev/pilot chạy `php artisan serve` — MỘT tiến trình, xử lý tuần tự từng
 * request — nên chính những lượt poll đó xếp hàng chặn trước request quét đơn: đo được cùng một
 * endpoint lúc 19ms lúc 2117ms (trung vị 239ms) chỉ vì hàng đợi. Thưa nhịp lúc nhàn rỗi trả lại
 * đường thông cho đúng thao tác thợ đang chờ.
 *
 * Mốc "đang cân" là ĐÃ BẤM NEXT, không phải "đã có đơn": cân tay không đơn (currentIndex >= 0,
 * activeJob null) cũng chốt bì y hệt nên cũng cần nhịp dày, nếu không thì đúng cái luồng đó lại
 * dính lỗi bì-tính-luôn-phần-vừa-đổ nói ở trên.
 */
const POLL_MS_WEIGHING = 200;
const POLL_MS_IDLE = 1000;

/**
 * Nhịp khi đang đọc thẳng Agent trên máy này (ADR-013).
 *
 * Dày gấp 8 lần nhịp qua backend mà KHÔNG tốn gì của máy chủ: cuộc gọi không rời khỏi máy, không
 * chạm mạng, không tốn một vòng bootstrap Laravel nào.
 *
 * 25ms (hạ từ 60ms ngày 07/08/2026 theo yêu cầu "nhanh hơn nữa"): chờ nhịp trung bình còn ~12ms,
 * cộng tuổi bản chụp của Agent (tối đa 15ms) và ~2ms cho cuộc gọi -> tổng ~25ms.
 *
 * ĐỪNG hạ tiếp nếu chưa đo NHỊP PHÁT CỦA CHÍNH CÁI CÂN. Cân nối qua PuTTY chỉ bắn ra một dòng
 * mỗi lần nó muốn (thường 5-20 lần/giây); hỏi dày hơn nhịp đó chỉ đọc lại đúng con số cũ và đốt
 * CPU máy trạm chứ không làm mặt số đổi nhanh hơn. Muốn xuống dưới mốc này thì đổi sang Agent tự
 * ĐẨY (SSE) chứ không phải hỏi dày thêm — hỏi bao nhiêu cũng vẫn còn một khoảng chờ nhịp.
 */
const POLL_MS_CUC_BO = 25;

const dangCan = computed(() => activeJob.value !== null || currentIndex.value >= 0);

function pollIntervalForState() {
  if (!dangCan.value) return POLL_MS_IDLE;
  return nguonCucBo.value ? POLL_MS_CUC_BO : POLL_MS_WEIGHING;
}

/**
 * Đặt lại nhịp poll theo trạng thái hiện tại — và DỪNG HẲN khi trang không ai nhìn.
 *
 * Máy trạm ngoài xưởng hay bị cửa sổ khác che hoặc bị chuyển tab. Mỗi nhịp poll là 9 truy vấn DB
 * phía server (xác thực + tra mã trạm + 2 bộ khoá cache), 5 lần/giây — chạy tiếp trong lúc không
 * ai nhìn là phí trắng.
 *
 * An toàn vì không có thao tác cân nào xảy ra khi trang bị ẩn: không bấm được NEXT, không quét
 * được mã. Hiện lại là đọc số ngay lập tức rồi mới vào nhịp, nên không phải chờ tới nhịp kế.
 *
 * KHÔNG đụng tới nhịp của hàng đợi (`batTuDay`): mẻ chưa gửi vẫn phải được đẩy lên kể cả khi thợ
 * đã chuyển sang cửa sổ khác.
 */
function capNhatNhipPoll() {
  if (document.hidden) {
    stopPolling();
    return;
  }
  startPolling(pollIntervalForState());
}

// Đổi nhịp ngay khi bắt đầu/kết thúc cân. `dangCan` là boolean nên chỉ khởi động lại bộ đếm khi
// thực sự đổi trạng thái, không phải mỗi lần object job được gán lại hay mỗi lần sang ô kế.
watch(dangCan, capNhatNhipPoll);

// Đường cục bộ sống lại (Agent vừa được cài/restart) hoặc chết đi -> đổi nhịp ngay, không đợi
// thợ bấm NEXT. `nguonCucBo` là boolean nên chỉ khởi động lại bộ đếm khi thật sự đổi đường.
watch(nguonCucBo, capNhatNhipPoll);

function onVisibilityChange() {
  if (!document.hidden) fetchLiveWeight();
  capNhatNhipPoll();
}

function applyActiveJob(job: any, batch: any) {
  // Đơn cũ chưa lưu bị thay bởi đơn mới (quét chồng, không qua CLEAR) — dọn nó trước khi mất
  // dấu vết id, nếu không lô của đơn cũ sẽ kẹt vĩnh viễn không về được WEIGHED.
  // Bỏ qua khung tạm (id null, chưa có gì dưới DB để mà hủy).
  if (activeJob.value?.id && activeJob.value.id !== job.id) {
    cancelAbandonedJob(activeJob.value.id);
  }

  activeJob.value = job;
  activeBatch.value = batch;
  currentIndex.value = -1;
  capturedWeights.value = {};
  capturedTare.value = {};
  resetTareForNewSlot();
  saveSession();
}

/* ===================== PHIÊN LÀM VIỆC CỦA RIÊNG MÁY NÀY =====================
 *
 * Màn hình này chạy trên NHIỀU máy trạm cùng lúc. Quét một đơn ở máy nào thì đơn đó phải ở lại
 * đúng máy đó cho tới khi CLEAR hoặc SAVE — F5, đóng nhầm tab, mất điện màn hình đều phải quay
 * lại cân tiếp được, và tuyệt đối không nhảy sang máy khác.
 *
 * Hai lớp cùng bảo đảm điều đó:
 *   - Server: /api/weighing-jobs/active lọc theo `assigned_operation_client_id` nên chỉ trả về
 *     đơn của đúng trạm đang hỏi, và đã loại job COMPLETED.
 *   - localStorage (dưới đây): riêng từng máy, và là thứ DUY NHẤT nhớ được 9 ô đã cân — chúng
 *     chỉ nằm trong RAM cho tới lúc bấm SAVE. Không có lớp này thì khôi phục chỉ dựng lại cái
 *     khung với mọi ô PROCESS trắng: nhìn như đang cân dở nhưng số đã bay sạch, thao tác viên
 *     rất dễ bấm SAVE và chốt luôn mọi dòng thành KHÔNG ĐẠT.
 *
 * CLEAR/SAVE xoá dấu vết này, nên sau đó vào lại là form trắng — đúng yêu cầu "lúc nào cũng sẵn
 * sàng để cân đợt mới" mà vẫn giữ được mẻ đang dở.
 */
const SESSION_KEY = 'df_ws2_session_v1';

function saveSession() {
  if (!currentWorkstation.value) return;

  // Mẻ CÂN TAY cũng phải sống qua F5 (yêu cầu 2026-08-02: "vẫn cân tiếp được trừ khi ấn clean").
  // Trước đây chỗ này thoát ngay khi không có `activeJob`, nên cân tay dở dang mà F5 là mất sạch.
  // Nhưng KHÔNG ghi khi chưa có gì: chưa quét, chưa bấm NEXT, chưa chốt ô nào thì mỗi lần cân
  // đứng yên lại ghi một bản rỗng vào localStorage vô ích.
  const coGiDoDangDeGiu =
    !!activeJob.value || currentIndex.value >= 0 || Object.keys(capturedWeights.value).length > 0;
  if (!coGiDoDangDeGiu) return;

  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify({
      workstationId: currentWorkstation.value.id,
      // Không có đơn nào = mẻ cân tay. Đánh dấu tường minh thay vì để `restoreSession` suy ra từ
      // chỗ thiếu jobId/rawQr — suy ra thì không phân biệt được với một bản ghi hỏng.
      canTay: !activeJob.value,
      manualRacks: manualRacks.value,
      jobId: activeJob.value?.id ?? null,
      // Mẻ đọc từ QR không có id nào dưới DB — chuỗi QR CHÍNH LÀ mẻ. Lưu nó là đủ để F5 xong
      // dựng lại nguyên bảng mà không hỏi server câu nào (parseDyeQr thuần tuý, cùng đầu vào
      // luôn cho cùng kết quả).
      rawQr: activeJob.value?.raw_qr ?? null,
      capturedWeights: capturedWeights.value,
      capturedTare: capturedTare.value,
      // Đang cân dở tới đâu: ô nào, bì đã chốt là bao nhiêu, và số cân GỘP lúc ghi. Cái cuối
      // là mốc để biết đĩa cân có bị đụng vào trong lúc trang tải lại hay không (xem watch bên
      // dưới) — không có nó thì không cách nào phân biệt "F5 xong cân tiếp" với "ai đó nhấc
      // đĩa ra rồi mới F5".
      currentIndex: currentIndex.value,
      tareBaseline: tareBaseline.value,
      grossAtSave: grossWeight.value,
      // Giả lập phải sống qua F5, nếu không việc nối lại ở trên KHÔNG BAO GIỜ chạy đúng: tải
      // lại xong màn hình quay về đọc cân thật (số 0 hoặc mất tín hiệu), đối chiếu với
      // `grossAtSave` của giả lập là lệch -> luôn báo "đĩa cân đã thay đổi".
      useSim: useSimValue.value,
      simWeight: useSimValue.value ? simulatedWeight.value : null,
    }));
  } catch {
    // Hết dung lượng / chế độ riêng tư: mất khả năng khôi phục thôi, không được làm hỏng
    // luồng cân đang chạy.
  }
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
}

/**
 * Bật lại giả lập kèm đúng số đang gõ, TRƯỚC khi đặt `pendingResume`.
 *
 * Thứ tự này là bắt buộc: việc nối lại ô đang cân dở so `grossWeight` hiện tại với `grossAtSave`.
 * Không dựng lại nguồn số giả lập trước thì nhịp poll đầu tiên sẽ nạp số của cân THẬT (0, hoặc
 * mất tín hiệu) và phép so luôn lệch — F5 lần nào cũng ăn "đĩa cân đã thay đổi".
 */
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

  // Không có dấu vết (chưa quét, hoặc đã CLEAR/SAVE) -> mở trắng, không hỏi server.
  if (!saved?.jobId && !saved?.rawQr && !saved?.canTay) return;

  // Dấu vết của trạm khác (máy được gán lại trạm) -> bỏ, không nạp nhầm đơn của trạm kia.
  if (saved.workstationId !== currentWorkstation.value.id) {
    clearSession();
    return;
  }

  // ===== Mẻ CÂN TAY: không có đơn nào để dựng lại, chỉ có số đã cân =====
  // Không hỏi server câu nào — mẻ cân tay chưa SAVE thì dưới DB không hề tồn tại.
  if (saved.canTay) {
    capturedWeights.value = saved.capturedWeights || {};
    capturedTare.value = saved.capturedTare || {};
    manualRacks.value = saved.manualRacks || {};
    khoiPhucGiaLap(saved);

    resetTareForNewSlot();
    currentIndex.value = -1;

    // Cùng cách nối lại như mẻ theo đơn: chờ đối chiếu số cân gộp rồi mới nhận lại bì cũ.
    if (saved.currentIndex >= 0 && typeof saved.tareBaseline === 'number' && typeof saved.grossAtSave === 'number') {
      pendingResume.value = {
        index: saved.currentIndex,
        tare: saved.tareBaseline,
        gross: saved.grossAtSave,
      };
    }
    return;
  }

  // ===== Mẻ đọc từ QR: dựng lại HOÀN TOÀN tại client, không hỏi server câu nào =====
  // Quét mà chưa SAVE thì dưới DB không có gì để mà hỏi — chuỗi QR đã lưu chính là cả mẻ.
  if (saved.rawQr) {
    const parsed = parseDyeQr(saved.rawQr);
    if (parsed.color === '' || parsed.code === '' || parsed.rack_lines.length === 0) {
      clearSession(); // chuỗi hỏng (sửa tay localStorage?) -> mở trắng còn hơn dựng bảng sai
      return;
    }

    buildLocalJob(saved.rawQr, parsed);
    capturedWeights.value = saved.capturedWeights || {};
    capturedTare.value = saved.capturedTare || {};
    khoiPhucGiaLap(saved);

    resetTareForNewSlot();
    currentIndex.value = -1;

    // Chờ đối chiếu số cân gộp trước khi nhận lại bì cũ — xem giải thích ở nhánh dưới.
    if (saved.currentIndex >= 0 && typeof saved.tareBaseline === 'number' && typeof saved.grossAtSave === 'number') {
      pendingResume.value = {
        index: saved.currentIndex,
        tare: saved.tareBaseline,
        gross: saved.grossAtSave,
      };
    }
    return;
  }

  // ===== Mẻ mở bằng token "DF:ORDER:<uuid>": vòng cân có thật dưới DB, phải hỏi server =====
  try {
    const res = await axios.get('/api/weighing-jobs/active', {
      params: { workstation_id: currentWorkstation.value.id },
    });
    const job = res.data?.data?.job;

    // Server không còn coi đây là đơn đang dở (đã SAVE xong, hoặc đã bị đơn khác thay chỗ)
    // -> dấu vết cũ hết giá trị.
    if (!job || job.id !== saved.jobId) {
      clearSession();
      return;
    }

    activeJob.value = job;
    activeBatch.value = res.data.data.batch;
    capturedWeights.value = saved.capturedWeights || {};
    capturedTare.value = saved.capturedTare || {};
    khoiPhucGiaLap(saved);

    resetTareForNewSlot();
    currentIndex.value = -1;

    // Đang cân dở một ô: KHÔNG nhận bì cũ ngay lập tức. Bì là trạng thái VẬT LÝ của cái đĩa —
    // F5 không đụng vào đĩa, nhưng "ai đó nhấc đĩa ra rồi mới F5" thì cũng chẳng để lại dấu vết
    // gì khác. Nhận bừa bì cũ trong trường hợp đó sẽ cho ra số cân sai mà vẫn tô xanh ĐẠT.
    // Nên: chờ số cân ổn định đầu tiên rồi ĐỐI CHIẾU với số gộp đã lưu (xem watch bên dưới).
    if (saved.currentIndex >= 0 && typeof saved.tareBaseline === 'number' && typeof saved.grossAtSave === 'number') {
      pendingResume.value = {
        index: saved.currentIndex,
        tare: saved.tareBaseline,
        gross: saved.grossAtSave,
      };
    }
  } catch {
    clearSession();
  }
}

/** Ô đang cân dở chờ nối lại sau F5 — chỉ nối khi xác nhận đĩa cân chưa bị đụng vào. */
const pendingResume = ref<{ index: number; tare: number; gross: number } | null>(null);

/**
 * Sai lệch số cân gộp còn được coi là "đĩa chưa bị đụng vào". Cân đọc theo gram, vật tư nhẹ
 * nhất trong công thức cũng vài gram, nên 0.5g đủ rộng để bỏ qua trôi số/rung nền mà vẫn bắt
 * được mọi thao tác thật (thêm bớt vật tư, nhấc đĩa).
 */
const RESUME_GROSS_TOLERANCE = 0.5;

watch([isStable, grossWeight], () => {
  const p = pendingResume.value;
  if (!p || !isStable.value || !signalLive.value) return;
  pendingResume.value = null;

  if (Math.abs(grossWeight.value - p.gross) <= RESUME_GROSS_TOLERANCE) {
    // Đĩa y nguyên -> nối lại đúng chỗ đang cân dở, giữ nguyên bì cũ. Cân tiếp như chưa hề F5.
    currentIndex.value = p.index;
    tareBaseline.value = p.tare;
    armed.value = true;
  } else {
    // Đĩa đã khác -> bì cũ vô nghĩa. Nói rõ ra thay vì âm thầm cho ra số sai.
    errorMsg.value =
      `Đĩa cân đã thay đổi trong lúc tải lại trang (${p.gross.toFixed(2)} → ${grossWeight.value.toFixed(2)}). `
      + 'Các ô đã cân vẫn còn nguyên — bấm NEXT để cân tiếp ô kế và lấy bì mới.';
  }
});

// Ghi lại mốc số cân gộp mỗi khi cân đứng ở một giá trị mới — đây là thứ để lần F5 sau đối
// chiếu. Chỉ ghi khi ĐÃ ỔN ĐỊNH nên lúc đang đổ vật tư không ghi gì, và chỉ ghi khi giá trị
// thật sự đổi nên không đụng localStorage mỗi vòng poll.
let lastPersistedGross: number | null = null;
watch([isStable, grossWeight], () => {
  // Bỏ điều kiện `activeJob` (2026-08-02): mẻ cân tay không có đơn nào nhưng vẫn phải ghi mốc
  // này, nếu không F5 giữa chừng sẽ không nối lại được ô đang cân dở. `saveSession` tự bỏ qua
  // khi chưa có gì đáng giữ.
  if (!isStable.value || !signalLive.value) return;
  if (lastPersistedGross !== null && Math.abs(grossWeight.value - lastPersistedGross) < 0.01) return;
  lastPersistedGross = grossWeight.value;
  saveSession();
});

/**
 * txt_color_AfterUpdate — máy quét gõ thẳng chuỗi QR vào ô COLOR rồi bắn Enter. Đọc .value
 * một lần tại đây (input để uncontrolled, xem ghi chú ở template) rồi nạp đơn.
 */
const scanInputRef = ref<HTMLInputElement | null>(null);

/**
 * Nạp đơn cần vài chục truy vấn DB (tạo lô/job/9 dòng vật tư). Khi backend không nằm cùng máy
 * với DB thì mỗi truy vấn tốn ~20ms, cộng lại thành gần 1 giây — trong khoảng đó màn hình đứng
 * im, thao tác viên tưởng máy quét không ăn và bắn lại mã lần nữa. `scanning` để khoá ô quét,
 * đổi placeholder thành "Đang nạp đơn…" và chặn quét chồng.
 */
const scanning = ref(false);

function onScanEnter() {
  const token = (scanInputRef.value?.value ?? '').trim();
  if (!token || scanning.value) return;
  handleBarcodeScan(token);
}

const handleBarcodeScan = async (token: string) => {
  if (!currentWorkstation.value) return;
  scanning.value = true;
  // Bíp NGAY khi nhận mã, không đợi server — thao tác viên biết máy quét đã ăn.
  scannerService.playBeep(1200, 80);
  // Cùng cách định tuyến với bản cũ: QR thật từ trạm in luôn bắt đầu bằng "#", token giả lập
  // dạng "DF:ORDER:<uuid>" đi endpoint khác.
  const isRealVbaQr = token.startsWith('#');
  const url = isRealVbaQr ? '/api/scanner/scan-dye-qr' : '/api/scanner/scan';
  const payload = isRealVbaQr
    ? { raw_qr: token, workstation_code: currentWorkstation.value.code }
    : { qr_token: token, workstation_code: currentWorkstation.value.code };

  // ===== QUÉT KHÔNG CHẠM MẠNG (2026-08-02, yêu cầu "dùng 100% là bằng JS") =====
  // Chuỗi QR đã chứa ĐỦ rack/dye/weight của cả mẻ nên trình duyệt tự dựng được bảng 9 dòng.
  // Không gọi server lúc này: cả mẻ chỉ chạm mạng đúng MỘT lần, lúc bấm SAVE
  // (/api/scanner/weigh-from-qr) — cũng chính là cách VBA gốc làm, btnSave_Click mới INSERT.
  //
  // Hệ quả có chủ ý: quét xong mà chưa SAVE thì dưới DB KHÔNG có gì cả, nên không còn cảnh báo
  // "đơn này cũng đang được cân ở máy khác" nữa. Đổi lại, quét không tốn vòng mạng nào và
  // không bao giờ sinh vòng cân mồ côi khi thợ quét nhầm rồi bỏ đi.
  if (isRealVbaQr) {
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

  // Token giả lập "DF:ORDER:<uuid>" KHÔNG mang dữ liệu dòng nào — buộc phải hỏi server mới biết
  // cân gì, nên vẫn đi đường cũ.
  try {
    const res = await axios.post(url, payload);
    if (res.data?.status === 'SUCCESS') {
      const data = res.data.data;
      if (data.empty) {
        scannerService.playBeep(800, 300);
        await baoTin(res.data.message);
        return;
      }
      applyActiveJob(data.job, data.batch);
      parallelNotice.value = res.data.notice || '';
    }
  } catch (err: any) {
    scannerService.playBeep(600, 400);
    await baoTin(err.response?.data?.message || 'Không thể mở lệnh sản xuất này.');
  } finally {
    scanning.value = false;
    // Trả con trỏ về ô quét để bắn mã kế tiếp ngay, không phải bấm chuột lại.
    nextTick(() => scanInputRef.value?.focus());
  }
};

/**
 * Dựng khung mẻ cân từ chuỗi QR — bảng 9 dòng thợ nhìn thấy. `id: null` nghĩa là dưới DB chưa
 * có gì cả; cả mẻ chỉ được ghi xuống lúc bấm SAVE (xem onSave).
 *
 * Tách riêng khỏi applyLocalJob() để khôi phục sau F5 dùng lại được mà KHÔNG xoá mất số đã cân.
 *
 * Dung sai ±1% mục tiêu, đúng công thức server dùng cho nhánh cân tự do
 * (ScannerController::TOLERANCE_RATIO) — nếu lệch, màu LED lúc cân sẽ khác với kết quả
 * ĐẠT/KHÔNG ĐẠT mà server chốt lúc lưu.
 */
function buildLocalJob(rawQr: string, parsed: ParsedDyeQr) {
  activeJob.value = {
    id: null,
    // Giữ nguyên chuỗi QR đã quét: lúc SAVE gửi thẳng chuỗi này lên, server tự parse lại và
    // mở lệnh sản xuất. Client không cần biết id nào của DB cả. Đây cũng là thứ DUY NHẤT cần
    // lưu để dựng lại nguyên mẻ sau khi F5.
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

/** Quét một mã mới: dựng bảng và xoá sạch mọi thứ của mẻ trước. */
function applyLocalJob(rawQr: string, parsed: ParsedDyeQr) {
  if (activeJob.value?.id) cancelAbandonedJob(activeJob.value.id);

  buildLocalJob(rawQr, parsed);

  currentIndex.value = -1;
  capturedWeights.value = {};
  capturedTare.value = {};
  parallelNotice.value = '';
  resetTareForNewSlot();
  saveSession();
}

/** Chốt giá trị đang hiện của ô đang cân vào bộ nhớ tạm (tương đương p1..p9 của VBA). */
function captureCurrentSlot() {
  if (currentIndex.value < 0 || tareBaseline.value === null) return;
  capturedWeights.value = { ...capturedWeights.value, [currentIndex.value]: liveWeight.value };
  capturedTare.value = {
    ...capturedTare.value,
    [currentIndex.value]: { tare: tareBaseline.value, gross: grossWeight.value },
  };
  saveSession(); // ghi ngay từng ô — mất điện giữa mẻ chỉ mất đúng ô đang cân dở
}

/**
 * btnNext_Click: lần đầu bắt đầu ở ô chưa cân đầu tiên; các lần sau chốt ô hiện tại rồi sang
 * ô kế + Delta_Begin (reset bì, chờ chốt bì mới).
 */
function onNext() {
  errorMsg.value = '';
  if (currentIndex.value === -1) {
    // Bỏ qua cả ô đã lưu ở server LẪN ô vừa cân xong đang giữ ở máy này (khôi phục sau F5) —
    // nếu không, bấm NEXT sau khi khôi phục sẽ nhảy về ô 1 và ghi đè số đã cân.
    const firstPending = jobItems.value.findIndex(
      (i: any, idx: number) => i.status !== 'COMPLETED' && capturedWeights.value[idx] === undefined
    );
    // Cân xong hết vật tư của đơn rồi mới bấm NEXT: xuống dòng trống kế tiếp chứ KHÔNG quay về
    // ô 1 — quay về là ghi đè số đã cân, và giờ dòng trống bên dưới đã cân được.
    currentIndex.value = firstPending >= 0
      ? firstPending
      : Math.min(jobItems.value.length, MAX_RACK_LINES - 1);
    resetTareForNewSlot();
    armed.value = true; // AutoFlow_Start: từ đây lần đọc ổn định đầu tiên tự thành bì
    pendingResume.value = null; // thao tác tay thắng mọi việc nối lại còn treo
    saveSession();
    return;
  }

  captureCurrentSlot();
  if (currentIndex.value < MAX_RACK_LINES - 1) {
    currentIndex.value += 1;
    resetTareForNewSlot(); // Delta_Begin: bì về null, chờ lần đọc ổn định kế tiếp
    armed.value = true;
  }
  // Ghi lại NGAY vị trí ô mới: captureCurrentSlot ở trên còn ghi theo ô CŨ, nếu F5 rơi đúng
  // khoảng giữa đây và lần đọc ổn định kế tiếp thì phiên sẽ chỉ sai vị trí đúng 1 ô.
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
    return;
  }
  // Dòng không có vật tư (cân tay) — giữ riêng, xem `manualRacks`.
  manualRacks.value = { ...manualRacks.value, [idx]: value };
}

/**
 * btnClear_Click — port đúng VBA: xoá SẠCH toàn bộ form, không chỉ số đã cân. VBA duyệt mọi
 * TextBox trên form (`For Each c In Me.Controls ... c.text = ""`) nên COLOR/CODE/MACHINE/LV
 * và cả 9 dòng rack/dye/weight/process đều trắng, rồi `txt_COLOR.SetFocus` để quét đơn mới.
 *
 * Đơn đã quét vẫn còn nguyên trong `weighing_jobs` (không xoá vật lý — CLAUDE.md mục 3), nhưng
 * từ 2026-08-01 CLEAR khi CHƯA lưu dòng nào sẽ hủy nó (status CANCELLED, xem
 * cancelAbandonedJob/WeighingJobController::cancel) — quét lại đúng mã QR đó sau CLEAR là mở
 * MỘT VÒNG CÂN MỚI, không còn hiện lại vòng cũ như trước. Không hủy được thì server tự chối êm
 * (job đã có số cân thật) — không ảnh hưởng gì tới thao tác viên.
 *
 * KHÁC VBA một điểm có chủ ý (yêu cầu 2026-07-31): hỏi xác nhận trước khi xoá nếu đang có số
 * cân chưa lưu. VBA xoá thẳng không hỏi, mà nút CLEAR lại to bằng và nằm sát nút SAVE nên
 * bấm nhầm là mất trắng cả mẻ vừa cân. Không có số nào chưa lưu thì xoá thẳng, không hỏi.
 */
async function onClear(skipConfirm = false, alreadySaved = false) {
  const hasUnsaved = Object.keys(capturedWeights.value).length > 0;
  // Hỏi kể cả khi CHƯA cân ô nào, miễn là đang có đơn trên màn hình: bấm nhầm lúc đó tuy không
  // mất số cân nhưng vẫn mất đơn vừa quét, phải chạy đi lấy phiếu quét lại. Màn hình đang trắng
  // thì xoá thẳng, không hỏi (bấm CLEAR trên form trống là vô hại).
  const hasSomething = hasUnsaved || activeJob.value !== null;
  if (hasSomething && !skipConfirm) {
    const ok = await hoiXacNhan(
      hasUnsaved
        ? 'CLEAR sẽ xoá sạch màn hình, kể cả số đã cân nhưng CHƯA bấm SAVE.\n\nVẫn xoá?'
        : 'CLEAR sẽ xoá đơn đang mở khỏi màn hình. Quét lại mã QR để cân từ đầu.\n\nVẫn xoá?'
    );
    if (!ok) return;
  }

  // Hủy vòng cân đang mở — CHỈ khi CLEAR thật sự bỏ dở nó (không phải sau khi SAVE thành công,
  // job đó đã COMPLETED và không còn gì để hủy). `alreadySaved` tránh gọi thừa 1 request mỗi
  // lần SAVE (server vẫn tự chối job COMPLETED nếu lỡ gọi, nhưng không cần tốn round-trip đó).
  //
  // Mẻ đọc từ QR chưa hề ghi gì xuống DB (id null) nên CLEAR chỉ là xoá màn hình — không còn
  // vòng cân mồ côi nào để dọn. Chỉ mẻ mở bằng token "DF:ORDER:<uuid>" mới có job thật cần hủy.
  if (!alreadySaved && activeJob.value?.id) {
    cancelAbandonedJob(activeJob.value.id);
  }

  capturedWeights.value = {};
  capturedTare.value = {};
  manualRacks.value = {};
  currentIndex.value = -1;
  errorMsg.value = '';
  parallelNotice.value = '';
  activeJob.value = null;
  activeBatch.value = null;
  armed.value = false; // AutoRunning = False
  pendingResume.value = null;
  resetTareForNewSlot();
  // Xoá dấu vết phiên -> lần vào trang sau là form trắng, không nạp lại đơn vừa bỏ.
  clearSession();
  nextTick(() => {
    if (scanInputRef.value) scanInputRef.value.value = '';
    scanInputRef.value?.focus();
  });
}

/**
 * Các dòng sẽ lưu cho mẻ CÂN TAY (không quét đơn nào).
 *
 * HAI cách dùng, cố ý nhận cả hai (yêu cầu 2026-08-02: "chỉ cân không nhập đơn, khi cân liệu có
 * chỉ số là save được"):
 *
 *   1. Bấm NEXT chạy nhiều ô như cân theo đơn -> lấy các ô ĐÃ CHỐT SỐ.
 *   2. KHÔNG bấm NEXT gì cả: đặt vật tư lên cân, thấy số ở ô RAW rồi bấm SAVE luôn. Đây là cách
 *      dùng cái cân như một cái cân thường, và là đường nhanh nhất khi chỉ cần cân một thứ.
 *
 * Ở cách 2 chưa hề chốt bì nên KHÔNG có số net để lấy — `liveWeight` vẫn đứng ở 0 vì
 * `ingestRawWeight` thoát sớm khi chưa `armed`. Thứ duy nhất có thật là số cân gộp (RAW), nên
 * lưu đúng nó và ghi `tare_weight = null` cho khớp sự thật: không trừ bì lần nào cả.
 */
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

  // Số phải SỐNG và ĐỨNG YÊN. Số chết là con số đông cứng từ lần đọc cuối trước khi mất tín
  // hiệu; số chưa đứng yên là đang đổ dở. Ghi cái nào cũng là ghi một trọng lượng không có thật.
  if (!signalLive.value || !isStable.value) return [];
  // Cân rỗng (0.00) không phải "có chỉ số". Chặn ở đây vì bản ghi tạo ra KHÔNG XOÁ ĐƯỢC
  // (CLAUDE.md mục 3, không xoá vật lý) — bấm nhầm một cái là để lại rác vĩnh viễn.
  if (grossWeight.value === 0) return [];

  return [{
    sequence_no: 1,
    weight: grossWeight.value,
    rack_code: manualRacks.value[0] || null,
    tare_weight: null,
    gross_weight: grossWeight.value,
  }];
}

/**
 * btnSave_Click — gửi TẤT CẢ ô đã có số về server trong 1 lệnh, xong tự in phiếu rồi xoá màn
 * hình (đúng VBA: `btnPrint.Value = True` rồi `btnCLEAR.Value = True` ở cuối).
 */
async function onSave() {
  errorMsg.value = '';
  captureCurrentSlot(); // chốt nốt ô đang cân dở trước khi gửi

  // Port đúng `For i = 1 To 9: If Trim(txt_weight{i}) <> "" Then INSERT`: ghi MỌI dòng có
  // WEIGHT mục tiêu, KHÔNG chỉ những ô đã cân. Ô chưa cân gửi weight = null và bị gắn
  // REJECTED (VBA: ô PROCESS trống nên nền không xanh -> processColor = REJECTED).
  // Mẻ đọc thẳng từ QR (đường thường của V2) chưa hề có id nào dưới DB — khớp dòng theo
  // sequence_no. Mẻ mở bằng token "DF:ORDER:<uuid>" thì có item_id thật, gửi theo id.
  const localQr: string | null = activeJob.value?.raw_qr ?? null;

  // CÂN TAY: bấm NEXT cân luôn, không quét đơn nào (yêu cầu 2026-08-02 "không quét QR mà cân
  // không vẫn lưu bình thường"). Không có `jobItems` để duyệt nên lấy thẳng những ô ĐÃ CHỐT SỐ.
  // Ô chưa cân thì không gửi: mẻ theo đơn phải ghi cả ô bỏ trống vì đơn quy định sẵn phải cân
  // những gì, còn cân tay không có danh sách nào để mà thiếu.
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
    // Nói ĐÚNG lý do: ba nguyên nhân dưới đây cách xử lý khác hẳn nhau, gộp thành một câu chung
    // là thợ đứng bấm lại mãi mà không biết phải làm gì.
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

  // Dòng chưa cân sẽ bị chốt luôn thành KHÔNG ĐẠT và KHÔNG cân lại được (server chặn ghi đè
  // dòng đã COMPLETED). VBA lưu thẳng không hỏi, nhưng hậu quả ở đây là không thể hoàn tác
  // nên hỏi lại — chỉ hiện khi thật sự còn ô chưa cân, không cản luồng cân đủ 9 ô.
  //
  // KHÔNG mở sẵn cửa sổ in ở đây nữa (07/08/2026). Phiếu in bằng iframe ẩn trong chính trang này
  // — xem `inPhieuTrongTrang()`: không có cửa sổ mới nên không văng khỏi F11, và cũng không còn
  // ràng buộc "user activation" bắt phải mở trước mọi `await`.
  const unweighed = rows.filter((r: any) => r.weight === null).length;
  if (unweighed > 0) {
    const ok = await hoiXacNhan(
      `Còn ${unweighed} dòng CHƯA CÂN. Lưu bây giờ sẽ chốt các dòng đó là KHÔNG ĐẠT và không cân lại được nữa.\n\nVẫn lưu?`
    );
    if (!ok) return;
  }

  saving.value = true;

  // ===== Mẻ đọc từ QR VÀ mẻ cân tay: đi qua HÀNG ĐỢI =====
  // Xếp hàng TRƯỚC khi gửi, không phải sau khi gửi hỏng. Xếp sau thì có kẽ hở: trình duyệt bị
  // đóng/mất điện đúng lúc request đang bay là mẻ bay theo, không còn dấu vết nào để gửi lại.
  //
  // Cân tay đi CHUNG nhánh này chứ không có đường riêng: in ngay, hàng đợi, chống ghi trùng,
  // xoá form — mọi thứ y hệt. Tách ra là có hai bộ ngữ nghĩa hàng đợi phải giữ đồng bộ với nhau.
  if (localQr || canTay) {
    // Mốc giờ in chốt MỘT LẦN ở đây rồi gửi kèm lên server, để bản ghi print_jobs lưu đúng cái
    // đã in ra giấy chứ không phải một chuỗi khác chỉ vì server dựng lại vào thời điểm khác.
    const mocGioIn = nowSlipTimestamp();
    const phieuCucBo = canTay ? dungPhieuCanTay(rows, mocGioIn) : dungPhieuCucBo(mocGioIn);

    const item = xepHang(
      {
        ...(canTay ? { manual: true } : { raw_qr: localQr }),
        workstation_code: currentWorkstation.value?.code,
        scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
        // KHÔNG gửi `isStable` (cờ SỐNG của lần đọc ngay lúc này) — đó là câu trả lời cho một
        // câu hỏi khác. Server hỏi "mấy con số trong gói này có phải số đã đứng yên không";
        // `isStable` trả lời "cái cân LÚC NÀY có đang đứng yên không". Ở màn V2 hai thứ đó cách
        // nhau hàng phút vì cân cả mẻ xong mới SAVE một lần.
        //
        // Mọi số trong `rows` đều đứng yên theo cấu tạo: `capturedWeights` chỉ nhận `liveWeight`,
        // mà `liveWeight` chỉ nhúc nhích sau `if (!stable) return` trong ingestRawWeight; ô chưa
        // có bì (bì cũng chỉ chốt từ lần đọc ổn định) thì không chốt được số nào. Ô không cân
        // gửi weight = null và bị gắn KHÔNG ĐẠT, không phải số rác.
        //
        // Gửi cờ sống là dựng sẵn một cái bẫy CHẾT: bấm NEXT xong bấm SAVE luôn thì
        // resetTareForNewSlot() vừa hạ isStable = false, hoặc mất mạng thì fetchLiveWeight()
        // cũng hạ nó xuống — mà mất mạng chính là lúc hàng đợi được dùng. Mẻ vào hàng đợi với
        // stable=false sẽ bị 422 NOT_STABLE mãi mãi, gửi lại bao nhiêu lần cũng thế. Giờ không
        // bỏ mẻ được nữa thì đó là kẹt vĩnh viễn kèm một tờ phiếu đã in.
        stable: true,
        printed_at: mocGioIn,
        rows,
      },
      canTay
        ? `Cân tay · ${rows.length} dòng`
        : `${activeBatch.value?.color ?? ''} · ${activeBatch.value?.product_code ?? ''}`,
      currentWorkstation.value?.code || 'ws'
    );

    // IN NGAY, TRƯỚC khi chạm mạng (yêu cầu 2026-08-02 "lấy những cái đang hiển thị trên web để
    // in off luôn). Toàn bộ dữ liệu cần in đã nằm sẵn trên màn hình, nên chờ server trả về chỉ để
    // lấy đúng thứ mình đã có là thừa một vòng mạng — mà đó chính là chỗ trước đây thợ kêu "bấm
    // SAVE xong chờ mãi tem mới hiện".
    //
    // An toàn vì bản dựng của trình duyệt đã được đối chiếu TỪNG KÝ TỰ với bản server
    // (frontend/scripts/check-weigh-slip.mjs). Iframe tự in sau khi nạp xong font, không chặn
    // dòng chảy ở đây, nên request bên dưới vẫn bay đi bình thường.
    inPhieuTrongTrang(phieuCucBo);

    let daLenServer = false;
    let loiNghiepVu: string | null = null;

    try {
      await axios.post('/api/scanner/weigh-from-qr', {
        ...item.payload,
        idempotency_key: item.idempotency_key,
      });
      danhDauDaGui(item.idempotency_key); // server đã nhận -> rút khỏi hàng đợi
      daLenServer = true;
    } catch (err: any) {
      const status = err?.response?.status;
      // Server CÓ trả lời và chê payload (4xx) — gửi lại bao nhiêu lần cũng thế, nên ngừng tự
      // gửi. Nhưng VẪN GIỮ trong hàng đợi: phiếu đã in ra giấy ở trên rồi, xoá đi sẽ để lại một
      // tờ phiếu trên hàng mà trong máy không còn dấu vết nào để đối chiếu.
      if (status && status >= 400 && status < 500 && status !== 408 && status !== 429) {
        const loi: string = err?.response?.data?.message || `Máy chủ từ chối mẻ này (HTTP ${status}).`;
        loiNghiepVu = loi;
        danhDauKet(item.idempotency_key, loi);
      }
      // Còn lại (mất mạng, 5xx): cứ để yên trong hàng đợi, tự đẩy sau.
    }

    saving.value = false;
    scannerService.playBeep(1800, 150);
    clearSession();

    // Phiếu đã in xong từ trước khi gọi mạng, nên dù kết quả thế nào cũng xoá form để thợ quét
    // mẻ kế tiếp ngay — không mất gì cả, mọi mẻ chưa lên được server đều nằm trong hàng đợi.
    await onClear(true, true);

    if (loiNghiepVu) {
      errorMsg.value =
        `Máy chủ TỪ CHỐI mẻ này: ${loiNghiepVu} Phiếu đã in, mẻ vẫn nằm trong hàng đợi — `
        + 'bấm chỉ báo "mẻ chờ gửi" để xem và xử lý.';
    } else if (!daLenServer) {
      errorMsg.value =
        'Chưa gửi được lên máy chủ — mẻ đang nằm trong HÀNG ĐỢI của máy này và sẽ tự gửi khi có mạng. '
        + 'Phiếu đã in. Cứ quét mẻ tiếp theo bình thường, ĐỪNG xoá dữ liệu trình duyệt.';
    }
    return;
  }

  // ===== Mẻ mở bằng token "DF:ORDER:<uuid>" =====
  // KHÔNG qua hàng đợi: vòng cân đã có sẵn dưới DB từ lúc quét, và endpoint weigh-batch chưa có
  // khoá chống ghi trùng. Giữ nguyên hành vi cũ (hỏng thì báo lỗi, giữ số trên màn hình).
  try {
    const saveRes = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/weigh-batch`, {
      rows,
      scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
      stable: isStable.value,
      // Tên khoá KHÔNG phải 'workstation_code' là có chủ ý — xem ghi chú ở weighBatch().
      slip_workstation_code: currentWorkstation.value?.code || undefined,
    });
    scannerService.playBeep(1800, 150);
    clearSession();

    // Đúng đuôi btnSave_Click của VBA: `btnPrint.Value = True` rồi `btnCLEAR.Value = True`
    // — in phiếu xong là form trắng, sẵn sàng quét đơn kế.
    const slipPayload = saveRes.data?.data?.slip?.label_payload;
    if (slipPayload) {
      inPhieuTrongTrang(slipPayload);
    } else {
      await printSlip();
    }
    await onClear(true, true);
  } catch (err: any) {
    // KHÔNG xoá capturedWeights khi lỗi — số thao tác viên vừa cân phải còn nguyên để bấm
    // SAVE lại, nếu không họ mất trắng cả mẻ.
    errorMsg.value = err.response?.data?.message || 'Không lưu được mẻ cân. Số đã cân vẫn còn trên màn hình — thử SAVE lại.';
  } finally {
    saving.value = false;
  }
}

/**
 * Dựng nội dung phiếu cân từ dữ liệu ĐANG CÓ TRÊN MÀN HÌNH — dùng khi mất mạng, lúc đó không hỏi
 * server được câu nào. Bố cục do `utils/weighSlip.ts` lo, và bản đó được đối chiếu với bản server
 * bằng `frontend/scripts/check-weigh-slip.mjs`.
 */
/**
 * Phiếu của mẻ CÂN TAY, dựng ngay tại trình duyệt.
 *
 * Phần đầu phiếu phải ra ĐÚNG chuỗi mà server dựng, nếu không tờ giấy và bản ghi `print_jobs`
 * nói hai chuyện khác nhau. Server lấy từ lô cân tay: `color`/`product_code`/`level_code` đều
 * NULL và `machine` không có -> `buildAndStoreSlip` đưa xuống `null, null, 'N/A', null`, mà
 * `buildSlipHtml` quy null thành chuỗi rỗng. Nên ở đây truyền '' và 'N/A' là trùng khít.
 */
function dungPhieuCanTay(rows: any[], mocGioIn?: string): string {
  return buildSlipHtml(
    { color: '', product_code: '', machine_code: 'N/A', level_code: '', printed_at: mocGioIn },
    rows.map((r: any) => ({
      sequence_no: r.sequence_no,
      rack_code: r.rack_code,
      material_code: MANUAL_MATERIAL_CODE,
      planned_weight: 0,
      actual_weight: r.weight,
      // Cân tay không có mục tiêu nên không có ĐẠT/KHÔNG ĐẠT — cùng nhãn mà
      // `WeighingJobItem::getProcessStatusAttribute` trả về cho dòng mang mã mồi này.
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
 * gì hay không.
 *
 * Khác `dungPhieuCucBo()` (dùng cho luồng SAVE) ở chỗ KHÔNG lọc bỏ dòng: V2 cho bấm NEXT xuống
 * dòng trống để dùng màn hình như một cái cân thường, nên có thể có số cân ở dòng mà QR không
 * mang vật tư tới. `dungPhieuCucBo` bỏ những dòng đó (đúng, vì SAVE cũng không ghi chúng) — còn
 * nút PRINT thì phải in ra, y như VBA in cả ô trống lẫn ô thợ vừa gõ tay vào.
 *
 * Dòng trống hoàn toàn trả về mọi trường rỗng: `buildSlipHtml` in ra ô trống + STATUS REJECTED,
 * trùng khít cái mà nó tự sinh cho vị trí không có dữ liệu.
 */
function dongPhieuTheoLuoi() {
  return Array.from({ length: MAX_RACK_LINES }, (_, idx) => {
    const item: any = jobItems.value[idx];
    const actual = capturedWeights.value[idx] ?? null;

    if (!item) {
      return {
        sequence_no: idx + 1,
        rack_code: manualRacks.value[idx] || '',
        // Dòng không có vật tư mà đã cân = dòng CÂN TAY; mang đúng mã mồi mà SAVE sẽ ghi, để tờ
        // in thử và tờ in lúc lưu không nói hai chuyện khác nhau.
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
 * In phiếu qua iframe ẩn trong chính trang này (`inPhieuTrongTrang`) — không mở cửa sổ mới, nên
 * không văng khỏi F11 và không còn ràng buộc "user activation" của `window.open`. Nhờ vậy gọi
 * được ở bất kỳ đâu, kể cả sau `await axios.post(...)` trong luồng SAVE.
 *
 * Hàm này in VÔ ĐIỀU KIỆN, đúng `btnPrint_Click` của VBA: không hỏi đã gắn trạm chưa, không đòi
 * phải có sẵn số cân. Mọi cửa chặn thêm đều biến nút PRINT thành nút im lặng.
 */
const printSlip = async () => {
  // Cân tay, mẻ đọc từ QR (chưa có id nào dưới DB — đường thường của V2), và cả form trắng: đều
  // dựng phiếu ngay tại trình duyệt từ 9 dòng đang hiện. Form TRẮNG vẫn ra một tờ 9 dòng trống,
  // KHÔNG chặn lại — `btnPrint_Click` của VBA in bất kể ô có gì hay không. Bản trước chặn bằng
  // alert "Chưa có số cân nào để in": tiện cho web nhưng lệch bản gốc.
  //
  // Chưa gắn trạm cũng rơi vào đây thay vì thoát im lặng: server cần `workstation_code` để ghi
  // PrintJob, nhưng tờ giấy thì không — dữ liệu để in đã nằm sẵn trên màn hình.
  //
  // (Trước đây nhánh mẻ QR gọi `/api/weighing-jobs/null/print-slip` và luôn 404, tức nút PRINT
  // hỏng với chính luồng chạy chính.)
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
    await baoTin(err.response?.data?.message || 'Không thể in phiếu cân.');
  }
};

/**
 * Nút CHECK — mở LỊCH SỬ CÂN (`/weighing-history`) sang một TAB MỚI.
 *
 * Tab mới chứ không phải điều hướng: màn cân đang giữ nguyên cả mẻ dở lẫn số đã cân chưa lưu
 * (`capturedWeights`), rời trang là mất sạch. Mở tab riêng thì thợ tra cứu xong đóng tab là quay
 * lại đúng chỗ đang đứng.
 *
 * Tài khoản trạm bị khoá công đoạn nên `/weighing-history` phải nằm trong `MAN_PHU_TRO` của
 * `router/index.ts` — thiếu là tab mới bị đá ngược về màn cân ngay lúc mở.
 *
 * Trước 07/08/2026 nút này mở hộp "Tra cứu bán thành phẩm" (`WeighingCheckerModal`, tra theo
 * COLOR+CODE, KHÔNG có nút in lại). Component đó vẫn còn nguyên trong repo, chỉ không còn chỗ gọi
 * ở màn này — dựng lại thành nút riêng là một dòng, nếu vẫn cần dùng.
 */
function moLichSuCan() {
  window.open('/weighing-history', '_blank');
}

/* ===================== HỘP THOẠI VẼ TRONG TRANG ===================== */

// Vì sao không dùng window.confirm/alert: xem composables/useHopThoai.ts (Chrome đá khỏi F11).
// Dùng chung với /weighing-station-large — hai màn cùng chạy toàn màn hình cả ca.
const { thoai, hoiXacNhan, baoTin, dongThoai } = useHopThoai();

/* ===================== CỠ HIỂN THỊ ===================== */

/**
 * Phóng to toàn màn hình cân theo nấc, nhớ lại cho lần sau (yêu cầu 07/08/2026 "muốn to hơn để
 * dễ nhìn hơn").
 *
 * Dùng `zoom` chứ không phải `transform: scale()`: zoom tính lại bố cục thật nên chữ vẫn nét và
 * không sinh thanh cuộn ngang giả. Đổi lại, mọi đơn vị `vw`/`vh` bên trong cũng bị nhân theo —
 * chỗ nào dùng đã chia lại cho `--df-zoom`, xem phần style.
 *
 * Để thợ tự chỉnh chứ không chốt cứng một con số: mỗi trạm một cỡ màn hình, đứng gần xa khác nhau,
 * mà đoán sai thì hoặc chữ vẫn bé hoặc bảng 9 dòng tràn khỏi màn.
 */
const MUC_PHONG = [1, 1.25, 1.5, 1.75];
const KHOA_MUC_PHONG = 'ws2.co-hien-thi';
const mucPhongTo = ref(napMucPhong());

function napMucPhong(): number {
  const v = Number(localStorage.getItem(KHOA_MUC_PHONG));
  // Mặc định 1.25 chứ không phải 1: người dùng đã nói thẳng là bản 100% khó nhìn.
  return MUC_PHONG.includes(v) ? v : 1.25;
}

function doiCoManHinh(buoc: number) {
  const dangO = MUC_PHONG.indexOf(mucPhongTo.value);
  const moi = MUC_PHONG[Math.min(MUC_PHONG.length - 1, Math.max(0, (dangO < 0 ? 1 : dangO) + buoc))];
  mucPhongTo.value = moi;
  localStorage.setItem(KHOA_MUC_PHONG, String(moi));
}

/* ===================== HÀNG ĐỢI GỬI MẺ ===================== */

// Bảng chỉ đọc lại danh sách khi thực sự cần nhìn (mở bảng, hoặc số lượng đổi) — không đọc
// localStorage mỗi lần render.
watch([showQueue, queueCount, stuckCount, flushing, duongThong], () => {
  if (showQueue.value) queueItems.value = danhSachChoGui();
  // Đóng bảng thì bỏ luôn thông báo cũ, tránh lần mở sau đọc phải kết quả của lần trước.
  else queueMsg.value = '';
});

function formatQueueTime(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  });
}

async function onGuiNgay() {
  const truoc = queueCount.value;
  const { da_gui } = await dayHangDoi();
  queueItems.value = danhSachChoGui();

  if (da_gui > 0 && queueCount.value === 0) {
    datQueueMsg(true, `Đã gửi hết ${da_gui}/${truoc} mẻ lên máy chủ.`);
  } else if (da_gui > 0) {
    datQueueMsg(false, `Gửi được ${da_gui}/${truoc} mẻ, còn ${queueCount.value} mẻ chưa lên được — xem lý do ở cột Trạng thái.`);
  } else {
    datQueueMsg(false, duongThong.value
      ? 'Không gửi được mẻ nào — máy chủ có trả lời nhưng từ chối, xem lý do ở cột Trạng thái.'
      : 'Không gửi được mẻ nào — không kết nối được máy chủ.');
  }
}

/**
 * Bấm THỬ LẠI trên một mẻ: gửi ĐÚNG mẻ đó rồi báo kết quả THẬT ngay tại bảng.
 *
 * Bản cũ chỉ xoá cờ lỗi rồi gọi `dayHangDoi()` mà không chờ (không await): mẻ hết đỏ trong chớp
 * mắt nên nhìn cứ như đã gửi xong, trong khi thực tế có thể vẫn hỏng y hệt. Nay chờ kết quả và
 * hiện nguyên văn lời từ chối của máy chủ.
 */
async function onThuLai(key: string) {
  datQueueMsg(true, 'Đang gửi lại…');
  const { ok, message } = await guiMotNgay(key);
  queueItems.value = danhSachChoGui();
  datQueueMsg(ok, message);
}

/**
 * Bỏ một mẻ khỏi hàng đợi. Hỏi xác nhận vì đây là thao tác KHÔNG hoàn tác được từ màn hình cân:
 * phiếu đã in ra giấy rồi mà mẻ thì sẽ không bao giờ lên máy chủ nữa.
 */
async function onBoMe(q: { idempotency_key: string; nhan: string; queued_at: string }) {
  const dong = [
    `Bỏ hẳn mẻ "${q.nhan || '—'}" (xếp hàng lúc ${formatQueueTime(q.queued_at)}) khỏi hàng đợi?`,
    '',
    'Mẻ này sẽ KHÔNG BAO GIỜ được gửi lên máy chủ nữa, trong khi phiếu đã in ra giấy.',
    'Chỉ bỏ khi phiếu đó cũng bị huỷ (quét nhầm, cân lại mẻ khác).',
  ].join('\n');
  if (!await hoiXacNhan(dong)) return;

  const daBo = boMe(q.idempotency_key);
  queueItems.value = danhSachChoGui();
  datQueueMsg(daBo, daBo
    ? `Đã bỏ mẻ "${q.nhan || '—'}" khỏi hàng đợi (vẫn lưu vết trong máy để đối chiếu).`
    : 'Mẻ này không còn trong hàng đợi.');
  if (queueCount.value === 0) showQueue.value = false;
}

/** Hiện kết quả thao tác hàng đợi; tự tắt sau 8 giây để không đọng lại từ lần bấm trước. */
function datQueueMsg(ok: boolean, message: string) {
  queueMsgOk.value = ok;
  queueMsg.value = message;
  if (queueMsgTimer) clearTimeout(queueMsgTimer);
  queueMsgTimer = setTimeout(() => { queueMsg.value = ''; }, 8000);
}

function onClose() {
  // Nếu thao tác viên có bật toàn màn hình thì trả lại menu trước khi rời trang, tránh màn
  // hình kế tiếp cũng mất sidebar.
  isFullscreen.value = false;
  router.back();
}

// KHÔNG tự bật toàn màn hình khi vào trang (yêu cầu 2026-07-31) — mặc định giữ menu như mọi
// màn hình khác, ai cần thì tự bấm nút ⛶ trên thanh trên. Bản đầu ép isFullscreen=true để
// bám theo VBA (form gốc ẩn hẳn Excel), nhưng mất luôn đường điều hướng ngay khi mở trang.
onMounted(() => {
  // Tự nhận trạm của CHÍNH máy này (xem adoptLocalWorkstation). Không await: số cân và mẻ đang
  // dở phải chạy ngay, còn việc đổi trạm chỉ ảnh hưởng các lượt gọi sau. Máy chưa cài Agent thì
  // hàm này không đổi gì cả.
  adoptLocalWorkstation('SMALL').then((doi) => {
    if (doi) fetchLiveWeight();
  });
  // Khôi phục mẻ đang dở CỦA RIÊNG MÁY NÀY (nếu có) — xem restoreSession. Không await để số
  // cân bắt đầu chạy ngay, không phải chờ lượt gọi mạng này.
  restoreSession();
  fetchLiveWeight();
  capNhatNhipPoll();
  document.addEventListener('visibilitychange', onVisibilityChange);
  // Bật tự đẩy hàng đợi. Đặt ở đây (không phải ở module) để chỉ chạy khi thợ đang đứng ở màn
  // hình cân — mẻ tồn từ ca trước sẽ được gửi ngay lúc mở màn.
  batTuDay();
  // txt_COLOR.SetFocus của UserForm_Activate — con trỏ nằm sẵn ở ô quét, thao tác viên chỉ
  // việc bắn máy quét là chạy, không phải bấm chuột vào ô trước.
  nextTick(() => scanInputRef.value?.focus());
});

onUnmounted(() => {
  document.removeEventListener('visibilitychange', onVisibilityChange);
  if (queueMsgTimer) clearTimeout(queueMsgTimer);
  tatTuDay();
});
</script>

<style scoped>
/* Toàn màn hình luôn nền sáng như form VBA gốc — thợ quen bảng trắng chữ đen, và 3 màu
   vàng/xanh/đỏ của ô PROCESS chỉ đọc đúng trên nền sáng. Đổi tông xám be Windows cũ (#ece9d8)
   sang xám xanh trung tính: cùng độ sáng nên 3 màu tín hiệu vẫn đọc y hệt, nhưng không còn cảm
   giác phần mềm hai mươi năm trước. */
.ws2-root {
  background: #eef1f6;
  color: #0d1520;
  /* Chia cho mức phóng: `zoom` nhân mọi chiều dài bên trong, để nguyên 100vh là nền cao hơn màn
     hình đúng bằng hệ số phóng và trang luôn có thanh cuộn dọc thừa. */
  min-height: calc(100vh / var(--df-zoom, 1));
  /* Lề và khoảng cách bóp lại (07/08/2026, yêu cầu "đẩy thông tin ở trên cao lên"): mỗi px tiết
     kiệm ở đây là một px BẢNG 9 DÒNG được đẩy lên — mà bảng mới là thứ thợ nhìn cả ca. */
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

/* Khối nổi trên nền — thay cho viền chìm/nổi kiểu Windows 95 (border: 2px inset/outset). */
.panel {
  background: #fff;
  border: 1px solid #d5dbe6;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(15, 30, 55, 0.08);
}

/* Bảng 9 dòng ăn HẾT phần cao còn lại sau băng trên / dải RAW / dòng gợi ý — xem VbaRackGrid.
   `min-height: 0` bắt buộc, nếu không thì trên màn thấp bảng không chịu co và tràn ra ngoài.

   Trần 700px (~76px/dòng): không có nó thì trên màn 4K ở mức phóng 100% mỗi dòng cao hơn 200px,
   bảng thành 4 dòng chữ khổng lồ. Chạm trần rồi thì phần thừa nằm dưới đáy trang, NGOÀI khung
   bảng — chấp nhận được, hơn là một mảng trắng nằm bên trong khung. */
.ws2-grid {
  flex: 1 1 auto;
  min-height: 0;
  max-height: 700px;
}

/* ===== Băng trên ===== */
.ws2-topband {
  display: flex;
  gap: 12px;
  align-items: stretch;
}

/* Ba khối của băng trên kéo bằng chiều cao nhau (align-items: stretch ở .ws2-topband), nên các ô
   ở đây dàn đều theo chiều dọc thay vì dồn lên trên để lại một mảng trắng. */
.ws2-fields {
  display: grid;
  grid-template-columns: 168px 116px;
  gap: 8px 10px;
  padding: 10px;
  align-content: space-evenly;
}

.fld label {
  display: block;
  /* 10px là cỡ chữ đọc-được-nếu-ngồi-gần, mà thợ đứng cách màn hình 1-2m. Nới lên 12px và bù lại
     bằng chỗ tiết kiệm được ở nút bấm — xem .vba-btn.big. */
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.7px;
  color: #7b8598;
  margin-bottom: 2px;
}

.vba-txt {
  width: 168px;
  height: 44px;
  border: 1px solid #c2cad8;
  border-radius: 8px;
  background: #f7f9fc;
  color: #0d1520;
  font-size: 24px;
  font-weight: 700;
  padding: 0 10px;
  font-family: inherit;
  outline: none;
}

/* 3 ô MACHINE/CODE/LV chỉ để đọc — làm nhạt đi để mắt dồn về ô COLOR (ô duy nhất phải thao tác)
   và về số DELTA. */
.vba-txt[readonly] {
  background: #f2f4f8;
  border-color: #dde2ea;
  color: #2c3648;
}

.vba-txt.txt-sm {
  width: 116px;
}

/* Ô nhận mã quét — vàng nhạt như form gốc (InfoBackground), thêm viền đậm hơn để nhận ra ngay
   đây là ô DUY NHẤT cần đưa con trỏ vào. */
.vba-txt.txt-color {
  background: #fffbe6;
  border: 2px solid #e0b400;
}

.vba-txt.txt-color:focus {
  border-color: #1f6bff;
  box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.18);
}

/* Đang gọi server nạp đơn — nhấp nháy để thấy rõ máy đang làm việc, đứng cách 1-2m vẫn nhận ra. */
.vba-txt.txt-color.scanning {
  background: #cfe4ff;
  animation: ws2-scan-pulse 0.8s ease-in-out infinite;
}

@keyframes ws2-scan-pulse {
  50% { background: #fffbe6; }
}

/* delta_rawline — ô số lớn nhất trên form (288×113pt gốc) */
.ws2-delta {
  width: 400px;
  min-height: 132px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  /* Đổi màu theo dung sai (xem .tone-*) — chuyển mượt để không nhấp nháy khi số dao động quanh
     đúng ranh giới 0.99/1.01. */
  transition: border-color 0.15s ease;
}

/* Dải màu trên đỉnh ô — chỗ mang tín hiệu dung sai, giữ phần số nền trắng để luôn dễ đọc. */
.delta-band {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 5px 12px;
  background: #f1f4f9;
  border-bottom: 1px solid #e3e7ee;
  transition: background 0.15s ease;
}

.delta-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 6px 12px 8px;
}

.delta-caption {
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.7px;
  color: #56617a;
}

.delta-target {
  font-size: 15px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  color: #2c3648;
}

.delta-value {
  font-family: ui-monospace, 'Cascadia Mono', 'Consolas', 'Courier New', monospace;
  font-variant-numeric: tabular-nums;
  font-size: 72px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -2px;
  color: #0d1520;
}

.delta-sub {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  font-weight: 700;
}

.delta-tare {
  color: #6b7488;
  font-variant-numeric: tabular-nums;
}

.pill {
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.4px;
}

.pill.ok {
  background: #d8f5e0;
  color: #0a6b2c;
}

.pill.wait {
  background: #fdeecd;
  color: #8a5a00;
}

.pill.dead {
  background: #ffdcdc;
  color: #b32b21;
}

/* Số đông cứng vì mất tín hiệu — làm mờ để không ai đọc nó như số đang sống. */
.delta-value.dead {
  color: #a8b0be;
}

/* 3 tông dung sai — DÙNG CHUNG mã màu với ô PROCESS (utils/processColor). Chữ trên dải: đen cho
   vàng/xanh, trắng cho đỏ (nền đỏ đặc quá tối để đọc chữ đen). */
.ws2-delta.tone-under { border-color: #FAE605; }
.ws2-delta.tone-under .delta-band { background: #FAE605; }
.ws2-delta.tone-under .delta-caption,
.ws2-delta.tone-under .delta-target { color: #3a2f00; }

.ws2-delta.tone-ok { border-color: #78FA14; }
.ws2-delta.tone-ok .delta-band { background: #78FA14; }
.ws2-delta.tone-ok .delta-caption,
.ws2-delta.tone-ok .delta-target { color: #143a00; }

.ws2-delta.tone-over { border-color: #FF1400; }
.ws2-delta.tone-over .delta-band { background: #FF1400; }
.ws2-delta.tone-over .delta-caption,
.ws2-delta.tone-over .delta-target { color: #fff; }
.ws2-delta.tone-over .delta-value { color: #c81000; }

/* ===== Nút bấm — cỡ lớn cho thao tác bằng găng tay, bám kích thước form gốc ===== */
.ws2-buttons {
  display: grid;
  grid-template-columns: 208px 208px 192px;
  gap: 8px;
  align-content: start;
}

.vba-btn {
  border: 1px solid #c2cad8;
  border-radius: 10px;
  background: #fff;
  color: #2c3648;
  font-weight: 800;
  letter-spacing: 0.5px;
  cursor: pointer;
  font-family: inherit;
  box-shadow: 0 1px 2px rgba(15, 30, 55, 0.08);
  transition: transform 0.06s ease, box-shadow 0.12s ease, background 0.12s ease;
}

.vba-btn:hover:not(:disabled) {
  background: #f5f8ff;
  border-color: #9fb4d8;
}

/* Lún xuống khi bấm — thay cho border-style: inset của form cũ. Thợ đeo găng không cảm nhận
   được cú bấm bằng tay, phản hồi thị giác là thứ duy nhất báo máy đã nhận. */
.vba-btn:active:not(:disabled) {
  transform: translateY(2px);
  box-shadow: none;
}

.vba-btn:disabled {
  color: #a8b0be;
  background: #f2f4f8;
  border-color: #dde2ea;
  box-shadow: none;
  cursor: not-allowed;
}

/* Chiều cao nút là thứ QUYẾT ĐỊNH bảng 9 dòng nằm cao hay thấp: cả băng trên bị kéo bằng chiều
   cao khối nút (align-items: stretch), nên hạ 152 -> 104 là bảng lên đúng 48px + phần nút nhỏ.
   104px vẫn gấp đôi ngưỡng bấm-bằng-găng-tay thông thường (~48px), nên không mất tính bấm được —
   thứ duy nhất mất là khoảng trắng. Đừng hạ dưới ~90px. */
.vba-btn.big {
  height: 104px;
  font-size: 30px;
}

/* SAVE — hành động chốt cả mẻ, xanh lá đặc để không thể bấm nhầm sang CLEAR ngay bên cạnh. */
.vba-btn.primary {
  background: #1a8b3c;
  border-color: #15702f;
  color: #fff;
}

.vba-btn.primary:hover:not(:disabled) {
  background: #21a247;
  border-color: #15702f;
}

/* NEXT — nút bấm nhiều nhất trong một mẻ. */
.vba-btn.accent {
  background: #1f6bff;
  border-color: #1857cf;
  color: #fff;
}

.vba-btn.accent:hover:not(:disabled) {
  background: #3a80ff;
  border-color: #1857cf;
}

/* CLEAR — xoá trắng cả mẻ. Viền đỏ chứ KHÔNG nền đỏ đặc: nó nằm cạnh SAVE và được bấm thường
   xuyên, tô đỏ rực sẽ thành nhiễu thị giác và làm nhạt luôn cảnh báo mất tín hiệu cân. */
.vba-btn.danger {
  border: 2px solid #d43a2f;
  color: #b32b21;
}

.vba-btn.danger:hover:not(:disabled) {
  background: #fdeceb;
  border-color: #b32b21;
}

.vba-btn.sm {
  height: 58px;
  font-size: 18px;
}

/* CLOSE trải hết cột thứ 3 để không lệch lưới — cùng chiều cao .sm để hàng 2 không lệch. */
.vba-btn.wide {
  height: 58px;
  font-size: 18px;
}

.vba-btn.tiny {
  height: 30px;
  padding: 0 12px;
  font-size: 12px;
  border-radius: 8px;
}

/* ===== rawline ===== */
.ws2-rawline {
  display: flex;
  align-items: center;
  /* Cho phép xuống dòng: dải này chứa cả cảnh báo dài ("MẤT KẾT NỐI MÁY CHỦ…"). Không cho xuống
     dòng thì hoặc nó ép cả dải cao lên, hoặc đẩy nút A−/A+ và ô giả lập tràn khỏi màn. */
  flex-wrap: wrap;
  gap: 8px;
  font-size: 14px;
  padding: 5px 10px;
  background: #fff;
  border: 1px solid #d5dbe6;
  border-radius: 10px;
}

.raw-label {
  font-weight: 800;
  font-size: 12px;
  letter-spacing: 0.7px;
  color: #7b8598;
}

.raw-value {
  font-family: ui-monospace, 'Cascadia Mono', 'Consolas', 'Courier New', monospace;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  font-size: 18px;
  min-width: 92px;
  border: 1px solid #dde2ea;
  border-radius: 6px;
  background: #f7f9fc;
  padding: 2px 10px;
  text-align: right;
}

.raw-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
}

.raw-dot.on {
  background: #17b317;
  box-shadow: 0 0 0 3px rgba(23, 179, 23, 0.2);
}

.raw-dot.off {
  background: #d40000;
  box-shadow: 0 0 0 3px rgba(212, 0, 0, 0.2);
}

.raw-ws {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  color: #56617a;
  font-weight: 700;
}

/* Nền đỏ đặc: thợ đứng cách màn hình 1-2m, chữ đỏ trên nền sáng không đủ nổi. Không đổi theo
   theme tối — cùng chủ ý với ô PROCESS, giữ đúng cảm nhận màu ở nhà xưởng. */
.raw-warn {
  background: #d40000;
  color: #fff;
  font-weight: 800;
  padding: 2px 10px;
  border-radius: 3px;
  letter-spacing: 0.3px;
}

.sim-toggle {
  margin-left: auto;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  font-weight: 700;
  color: #7b8598;
}

.sim-input {
  width: 104px;
  border: 1px solid #c2cad8;
  border-radius: 6px;
  padding: 4px 8px;
  font-family: inherit;
  font-weight: 700;
}

.ws2-error {
  background: #fdeceb;
  border: 1px solid #d43a2f;
  border-radius: 8px;
  color: #a32219;
  font-weight: 700;
  padding: 8px 12px;
  margin: 0;
}

/* Ghi chú trùng đơn giữa 2 máy — XANH THÔNG TIN, cố ý không dùng vàng/đỏ: đây không phải cảnh
   báo, mỗi máy có vòng cân riêng nên cả hai đều lưu đầy đủ. Tô vàng ở đây sẽ khiến thợ tưởng
   phải dừng lại xử lý. Cùng hệ nền sáng của cả màn hình. */
.ws2-notice {
  background: #e7f1fb;
  border: 1px solid #5b8db8;
  border-radius: 8px;
  color: #17435f;
  font-weight: 700;
  padding: 8px 12px;
  margin: 0;
}

.ws2-hint {
  color: #7b8598;
  font-size: 12px;
  margin: 0;
  padding: 0 2px;
}

/* ===== Hàng đợi gửi mẻ ===== */
.queue-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e0b400;
  background: #fffbe6;
  color: #7a5c00;
  font: inherit;
  font-size: 12px;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 999px;
  cursor: pointer;
}

.queue-chip:hover { background: #fff4c2; }

/* Kẹt vì máy chủ từ chối = KHÔNG tự khắc hết, phải có người xử lý -> đổi sang đỏ. */
.queue-chip.stuck {
  border-color: #d43a2f;
  background: #fdeceb;
  color: #b32b21;
}

.queue-spin { font-weight: 600; opacity: 0.75; }

.queue-overlay {
  position: fixed;
  inset: 0;
  background: rgba(10, 20, 40, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.queue-modal {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  /* vw/vh chia lại cho mức phóng — xem ghi chú ở .ws2-root. */
  width: min(860px, calc(94vw / var(--df-zoom, 1)));
  max-height: calc(86vh / var(--df-zoom, 1));
  overflow: auto;
  box-shadow: 0 12px 40px rgba(10, 20, 40, 0.3);
}

/* Chỉ báo đường lấy số cân — chữ nhỏ, không tranh chỗ với số cân, nhưng luôn có mặt. */
.raw-nguon {
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  padding: 2px 8px;
  border-radius: 999px;
  cursor: help;
}

.raw-nguon.nhanh { background: #e6f7ec; color: #1a7f43; border: 1px solid #b7e3c8; }
.raw-nguon.cham { background: #f1f4f9; color: #6b7488; border: 1px solid #dde2ea; }

/* ===== Nút chỉnh cỡ hiển thị ===== */
.zoom-ctl {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  color: #56617a;
}

.queue-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 17px;
  margin-bottom: 10px;
}

.queue-note {
  background: #fffbe6;
  border: 1px solid #e0b400;
  border-radius: 8px;
  color: #7a5c00;
  font-size: 13px;
  padding: 8px 12px;
  margin: 0 0 12px;
}

.queue-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.queue-table th,
.queue-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #e3e7ee;
  text-align: left;
}

.queue-table thead th {
  font-size: 11px;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  color: #7b8598;
}

.queue-table .c-num { text-align: right; }
.queue-table .strong { font-weight: 700; }
.queue-err { color: #b32b21; font-weight: 700; }

/* Kết quả lần bấm gần nhất — nằm ngay trên bảng, đủ tương phản để đọc từ xa nửa mét ở xưởng. */
.queue-msg {
  border-radius: 8px;
  font-size: 14px;
  font-weight: 700;
  padding: 8px 12px;
  margin: 0 0 12px;
}
.queue-msg.ok { background: #e6f6ea; border: 1px solid #2e7d32; color: #1b5e20; }
.queue-msg.bad { background: #fdecea; border: 1px solid #b32b21; color: #8c1d16; }

.queue-act {
  display: flex;
  gap: 6px;
  justify-content: flex-end;
}

.queue-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: 12px;
}

/* Màn hình hẹp hơn form gốc (1449px): cho băng trên xuống dòng thay vì vỡ lưới */
@media (max-width: 1240px) {
  .ws2-topband {
    flex-wrap: wrap;
  }
}
</style>
