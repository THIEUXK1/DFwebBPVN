<template>
  <div class="ws2-root">
    <!-- Form LUÔN hiện sẵn ngay khi vào màn, kể cả chưa có mẻ nào — đúng app VBA gốc: form
         mở là đứng đó với các ô trống, thao tác viên quét thẳng vào ô COLOR để nạp đơn
         (txt_color_AfterUpdate). Không còn màn quét riêng chắn phía trước. -->

      <!-- ===== BĂNG TRÊN — port đúng scaleform.frm ===== -->
      <div class="ws2-topband">
        <!-- 4 ô thông tin: COLOR/MACHINE hàng trên, CODE/LV hàng dưới -->
        <div class="ws2-fields">
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

        <!-- delta_rawline: số DELTA (đã trừ bì) cỡ lớn — số thao tác viên thực sự nhìn -->
        <div class="ws2-delta">
          <div class="delta-caption">DELTA (đã trừ bì)</div>
          <div class="delta-value">{{ liveWeight.toFixed(2) }}</div>
          <div class="delta-sub">
            <span :class="isStable ? 'ok' : 'wait'">{{ isStable ? 'ỔN ĐỊNH' : 'CHỜ ỔN ĐỊNH' }}</span>
            <span v-if="tareBaseline !== null"> · Bì {{ tareBaseline.toFixed(2) }}</span>
            <span v-else-if="currentIndex >= 0"> · chờ chốt bì</span>
          </div>
        </div>

        <!-- Nhóm nút lớn — kích thước bám đúng form gốc để bấm được bằng găng tay -->
        <div class="ws2-buttons">
          <!-- onClear() có ngoặc: nếu để "onClear" trần thì Vue truyền MouseEvent vào tham số
               skipConfirm (truthy) -> bỏ qua luôn hộp xác nhận. -->
          <button class="vba-btn big" @click="onClear()" :disabled="saving">CLEAR</button>
          <!-- Không khoá theo "đã cân ô nào chưa": VBA cho SAVE bất cứ lúc nào, mọi dòng có
               WEIGHT mục tiêu đều được ghi (ô chưa cân -> REJECTED). -->
          <button class="vba-btn big primary" @click="onSave" :disabled="saving || !activeJob">
            {{ saving ? '...' : 'SAVE' }}
          </button>
          <button class="vba-btn big" @click="onNext" :disabled="saving || !activeJob || !canPressNext">NEXT</button>
          <button class="vba-btn sm" @click="printSlip" :disabled="!activeJob">PRINT</button>
          <button class="vba-btn sm" @click="showChecker = true">CHECK</button>
          <button class="vba-btn wide" @click="onClose">CLOSE</button>
        </div>
      </div>

      <!-- rawline: số THÔ trước khi lọc + công tắc giả lập (giữ lại để UAT khi chưa có cân) -->
      <div class="ws2-rawline">
        <span class="raw-label">RAW</span>
        <span class="raw-value">{{ grossWeight.toFixed(2) }}</span>
        <span class="raw-dot" :class="scaleOnline && signalLive ? 'on' : 'off'"></span>
        <span class="raw-ws">{{ currentWorkstation?.code || 'chưa gán trạm' }}</span>

        <!-- Phân biệt rõ "mất tín hiệu" với "cân đang rỗng": cả hai đều hiện RAW 0.00 nếu không
             có cảnh báo này, và thợ sẽ tưởng cân đang chờ đặt vật tư. -->
        <span v-if="scaleOnline && !signalLive && !useSimValue" class="raw-warn">
          ⚠ MẤT TÍN HIỆU CÂN — kiểm tra Agent / dây cân
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
        :items="jobItems"
        :current-index="currentIndex"
        :captured-weights="capturedWeights"
        :live-weight="liveWeight"
        @select="onSelectRow"
        @update-rack="onUpdateRack"
      />

      <p v-if="parallelNotice" class="ws2-notice">ℹ {{ parallelNotice }}</p>
      <p v-if="errorMsg" class="ws2-error">❌ {{ errorMsg }}</p>
      <p v-else-if="!activeJob" class="ws2-hint">
        Chưa có mẻ nào — đưa con trỏ vào ô <strong>COLOR</strong> rồi quét mã QR trên phiếu để nạp đơn.
      </p>
      <p v-else class="ws2-hint">
        Bấm <strong>NEXT</strong> để bắt đầu ô 1. Mỗi lần NEXT: chốt số ô đang cân rồi sang ô kế và lấy bì mới.
        Cân xong hết thì bấm <strong>SAVE</strong> để lưu cả mẻ.
      </p>

    <WeighingCheckerModal :show="showChecker" @close="showChecker = false" />
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

import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import WeighingCheckerModal from '../components/weighing/WeighingCheckerModal.vue';
import VbaRackGrid from '../components/weighing/VbaRackGrid.vue';
import { currentWorkstation, adoptLocalWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';
import { isFullscreen } from '../services/layout';
import { useScaleFeed } from '../composables/useScaleFeed';
import { printTsplViaBrowser } from '../utils/tsplPrint';

const router = useRouter();

const {
  liveWeight, grossWeight, isStable, scaleOnline, signalLive, tareBaseline, armed,
  useSimValue, simulatedWeight,
  retare, resetTareForNewSlot, fetchLiveWeight, startPolling,
} = useScaleFeed();

const activeJob = ref<any | null>(null);
const activeBatch = ref<any | null>(null);
const showChecker = ref(false);
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

const jobItems = computed(() => activeJob.value?.items || []);
// VBA: bấm NEXT tới ô 9 là hết (CurrentBoxIndex < 9 mới tăng tiếp).
const canPressNext = computed(() => currentIndex.value < jobItems.value.length - 1 || currentIndex.value === -1);

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

function applyActiveJob(job: any, batch: any) {
  // Đơn cũ chưa lưu bị thay bởi đơn mới (quét chồng, không qua CLEAR) — dọn nó trước khi mất
  // dấu vết id, nếu không lô của đơn cũ sẽ kẹt vĩnh viễn không về được WEIGHED.
  if (activeJob.value && activeJob.value.id !== job.id) {
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
  if (!activeJob.value || !currentWorkstation.value) return;
  try {
    localStorage.setItem(SESSION_KEY, JSON.stringify({
      workstationId: currentWorkstation.value.id,
      jobId: activeJob.value.id,
      capturedWeights: capturedWeights.value,
      capturedTare: capturedTare.value,
      // Đang cân dở tới đâu: ô nào, bì đã chốt là bao nhiêu, và số cân GỘP lúc ghi. Cái cuối
      // là mốc để biết đĩa cân có bị đụng vào trong lúc trang tải lại hay không (xem watch bên
      // dưới) — không có nó thì không cách nào phân biệt "F5 xong cân tiếp" với "ai đó nhấc
      // đĩa ra rồi mới F5".
      currentIndex: currentIndex.value,
      tareBaseline: tareBaseline.value,
      grossAtSave: grossWeight.value,
    }));
  } catch {
    // Hết dung lượng / chế độ riêng tư: mất khả năng khôi phục thôi, không được làm hỏng
    // luồng cân đang chạy.
  }
}

function clearSession() {
  localStorage.removeItem(SESSION_KEY);
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
  if (!saved?.jobId) return;

  // Dấu vết của trạm khác (máy được gán lại trạm) -> bỏ, không nạp nhầm đơn của trạm kia.
  if (saved.workstationId !== currentWorkstation.value.id) {
    clearSession();
    return;
  }

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
  if (!activeJob.value || !isStable.value || !signalLive.value) return;
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

  try {
    const res = await axios.post(url, payload);
    if (res.data?.status === 'SUCCESS') {
      const data = res.data.data;
      if (data.empty) {
        scannerService.playBeep(800, 300);
        alert(res.data.message);
        return;
      }
      applyActiveJob(data.job, data.batch);

      // Máy khác cũng đang cân đúng đơn này. THUẦN THÔNG TIN — mỗi máy một vòng cân riêng nên
      // hai bên lưu đầy đủ, không ai đè ai (xem ScannerController::handleOrderScan). Vẫn hiển
      // thị để hai thợ biết mà tránh cân trùng cùng một đơn. Dùng dải tại chỗ chứ không
      // alert(): alert() nuốt mất phát bắn mã kế tiếp của máy quét và buộc phải bấm chuột.
      parallelNotice.value = res.data.notice || '';
    }
  } catch (err: any) {
    scannerService.playBeep(600, 400);
    alert(err.response?.data?.message || 'Không thể mở lệnh sản xuất này.');
  } finally {
    scanning.value = false;
    // Trả con trỏ về ô quét để bắn mã kế tiếp ngay, không phải bấm chuột lại.
    nextTick(() => scanInputRef.value?.focus());
  }
};

// KHÔNG khôi phục mẻ đang dở khi vào trang (yêu cầu 2026-08-01: "lúc nào cũng trong trạng thái
// sẵn sàng để cân đợt mới"). Trước đây onMounted gọi /api/weighing-jobs/active và tự nạp lại
// đơn cũ — quay về màn hình là thấy nhảy lại mã cũ.
//
// Bỏ hẳn là ĐÚNG cho V2, không chỉ vì tiện: giá trị 9 ô sống trong RAM của trang cho tới lúc
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
    currentIndex.value = firstPending >= 0 ? firstPending : 0;
    resetTareForNewSlot();
    armed.value = true; // AutoFlow_Start: từ đây lần đọc ổn định đầu tiên tự thành bì
    pendingResume.value = null; // thao tác tay thắng mọi việc nối lại còn treo
    saveSession();
    return;
  }

  captureCurrentSlot();
  if (currentIndex.value < jobItems.value.length - 1) {
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
  if (item) item.rack_code = value;
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
function onClear(skipConfirm = false, alreadySaved = false) {
  const hasUnsaved = Object.keys(capturedWeights.value).length > 0;
  // Hỏi kể cả khi CHƯA cân ô nào, miễn là đang có đơn trên màn hình: bấm nhầm lúc đó tuy không
  // mất số cân nhưng vẫn mất đơn vừa quét, phải chạy đi lấy phiếu quét lại. Màn hình đang trắng
  // thì xoá thẳng, không hỏi (bấm CLEAR trên form trống là vô hại).
  const hasSomething = hasUnsaved || activeJob.value !== null;
  if (hasSomething && !skipConfirm) {
    const ok = window.confirm(
      hasUnsaved
        ? 'CLEAR sẽ xoá sạch màn hình, kể cả số đã cân nhưng CHƯA bấm SAVE.\n\nVẫn xoá?'
        : 'CLEAR sẽ xoá đơn đang mở khỏi màn hình. Quét lại mã QR để cân từ đầu.\n\nVẫn xoá?'
    );
    if (!ok) return;
  }

  // Hủy vòng cân đang mở — CHỈ khi CLEAR thật sự bỏ dở nó (không phải sau khi SAVE thành công,
  // job đó đã COMPLETED và không còn gì để hủy). `alreadySaved` tránh gọi thừa 1 request mỗi
  // lần SAVE (server vẫn tự chối job COMPLETED nếu lỡ gọi, nhưng không cần tốn round-trip đó).
  if (activeJob.value && !alreadySaved) cancelAbandonedJob(activeJob.value.id);

  capturedWeights.value = {};
  capturedTare.value = {};
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
 * btnSave_Click — gửi TẤT CẢ ô đã có số về server trong 1 lệnh, xong tự in phiếu rồi xoá màn
 * hình (đúng VBA: `btnPrint.Value = True` rồi `btnCLEAR.Value = True` ở cuối).
 */
async function onSave() {
  errorMsg.value = '';
  captureCurrentSlot(); // chốt nốt ô đang cân dở trước khi gửi

  // Port đúng `For i = 1 To 9: If Trim(txt_weight{i}) <> "" Then INSERT`: ghi MỌI dòng có
  // WEIGHT mục tiêu, KHÔNG chỉ những ô đã cân. Ô chưa cân gửi weight = null và bị gắn
  // REJECTED (VBA: ô PROCESS trống nên nền không xanh -> processColor = REJECTED).
  const rows = jobItems.value
    .map((item: any, idx: number) => {
      if (!item || item.status === 'COMPLETED') return null;
      if (item.planned_weight === null || item.planned_weight === undefined || item.planned_weight === '') return null;
      const meta = capturedTare.value[idx];
      return {
        item_id: item.id,
        weight: capturedWeights.value[idx] ?? null,
        rack_code: item.rack_code || null,
        tare_weight: meta?.tare ?? null,
        gross_weight: meta?.gross ?? null,
      };
    })
    .filter(Boolean);

  if (rows.length === 0) {
    errorMsg.value = 'Không có dòng nào để lưu.';
    return;
  }

  // Dòng chưa cân sẽ bị chốt luôn thành KHÔNG ĐẠT và KHÔNG cân lại được (server chặn ghi đè
  // dòng đã COMPLETED). VBA lưu thẳng không hỏi, nhưng hậu quả ở đây là không thể hoàn tác
  // nên hỏi lại — chỉ hiện khi thật sự còn ô chưa cân, không cản luồng cân đủ 9 ô.
  const unweighed = rows.filter((r: any) => r.weight === null).length;
  if (unweighed > 0) {
    const ok = window.confirm(
      `Còn ${unweighed} dòng CHƯA CÂN. Lưu bây giờ sẽ chốt các dòng đó là KHÔNG ĐẠT và không cân lại được nữa.\n\nVẫn lưu?`
    );
    if (!ok) return;
  }

  saving.value = true;

  // Mở cửa sổ in NGAY TẠI ĐÂY, trước mọi `await` — vẫn còn trong "user activation" của cú click
  // SAVE nên trình duyệt không chặn. Mở sau khi lưu xong là bị chặn (xem ghi chú ở printSlip).
  // window.confirm ở trên KHÔNG phá chuỗi này vì nó đồng bộ.
  const printWin = window.open('', '_blank', 'width=780,height=980');

  try {
    await axios.post(`/api/weighing-jobs/${activeJob.value.id}/weigh-batch`, {
      rows,
      scale_device_id: currentWorkstation.value?.assigned_scale_device_id || 'MOCK_SCALE',
      stable: isStable.value,
    });
    scannerService.playBeep(1800, 150);
    // Đã ghi xuống DB xong -> dấu vết phiên hết giá trị. Xoá NGAY, trước cả nhánh popup bị
    // chặn bên dưới: nếu để lại, vào trang sau sẽ khôi phục một mẻ đã lưu rồi.
    clearSession();

    // Popup vẫn bị chặn (người dùng chưa cho phép): mẻ ĐÃ LƯU rồi, tuyệt đối không được xoá
    // form âm thầm — giữ nguyên màn hình và chỉ đường bấm PRINT (nút đó gọi thẳng từ click nên
    // không bị chặn). Trước bản vá này chỗ này xoá luôn, thao tác viên mất phiếu mà không hiểu
    // vì sao và bấm SAVE lại thì chỉ còn báo lỗi.
    if (!printWin) {
      errorMsg.value = 'ĐÃ LƯU XONG mẻ cân, nhưng trình duyệt chặn cửa sổ in. Cho phép popup cho trang này rồi bấm PRINT để in phiếu.';
      return;
    }
    // Đúng đuôi btnSave_Click của VBA: `btnPrint.Value = True` rồi `btnCLEAR.Value = True`
    // — in phiếu xong là form trắng, sẵn sàng quét đơn kế. Không hộp thoại xác nhận nào
    // (VBA cũng không có): tiếng bíp + cửa sổ in hiện ra + form trắng đã là tín hiệu.
    await printSlip(printWin);
    onClear(true, true); // đã lưu xong: không hỏi xác nhận, không gọi hủy vòng cân (đã COMPLETED)
  } catch (err: any) {
    printWin?.close(); // không để lại cửa sổ trắng lơ lửng khi lưu hỏng
    // KHÔNG xoá capturedWeights khi lỗi — số thao tác viên vừa cân phải còn nguyên để bấm
    // SAVE lại, nếu không họ mất trắng cả mẻ.
    errorMsg.value = err.response?.data?.message || 'Không lưu được mẻ cân. Số đã cân vẫn còn trên màn hình — thử SAVE lại.';
  } finally {
    saving.value = false;
  }
}

/**
 * `preOpened` = cửa sổ đã được mở sẵn bởi chỗ gọi. BẮT BUỘC dùng khi in nằm sau một `await`
 * (luồng SAVE): Chrome/Edge chỉ cho `window.open` trong lúc còn "user activation" — tức ngay
 * trong handler của cú click, chưa qua await nào. Gọi sau `await axios.post(...)` là bị chặn
 * ngay, và trước bản vá này luồng SAVE dính đúng lỗi đó: mẻ ĐÃ lưu xong nhưng phiếu không in
 * được, form vẫn bị onClear() xoá sạch, nên bấm SAVE lại chỉ còn báo lỗi — thao tác viên tưởng
 * là chưa lưu được gì.
 *
 * Nút PRINT gọi thẳng từ cú click nên không cần truyền gì, tự mở như cũ.
 */
const printSlip = async (preOpened?: Window | null) => {
  if (!activeJob.value || !currentWorkstation.value) {
    preOpened?.close();
    return;
  }
  const win = preOpened ?? window.open('', '_blank', 'width=780,height=980');
  if (!win) {
    alert('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }
  win.document.write('<p style="font-family:sans-serif;padding:20px;">Đang xử lý...</p>');
  try {
    const res = await axios.post(`/api/weighing-jobs/${activeJob.value.id}/print-slip`, {
      workstation_code: currentWorkstation.value.code,
    });
    await printTsplViaBrowser(res.data?.data?.label_payload || '', win);
  } catch (err: any) {
    win.close();
    alert(err.response?.data?.message || 'Không thể in phiếu cân.');
  }
};

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
  adoptLocalWorkstation().then((doi) => {
    if (doi) fetchLiveWeight();
  });
  // Khôi phục mẻ đang dở CỦA RIÊNG MÁY NÀY (nếu có) — xem restoreSession. Không await để số
  // cân bắt đầu chạy ngay, không phải chờ lượt gọi mạng này.
  restoreSession();
  fetchLiveWeight();
  // 200ms thay cho 1000ms: bì được chốt từ lần đọc ổn định ĐẦU TIÊN sau khi bấm NEXT, nên nhịp
  // lấy số càng thưa thì càng dễ chốt bì vào lúc thợ đã bắt đầu đổ vật tư — bì tính luôn cả
  // phần vừa đổ, delta hiển thị thiếu, thợ đổ dư mà màn hình vẫn báo chưa đủ. VBA đọc 10ms;
  // 200ms là mức thoả hiệp với việc mỗi lần lấy số là một request Laravel đầy đủ.
  startPolling(200);
  // txt_COLOR.SetFocus của UserForm_Activate — con trỏ nằm sẵn ở ô quét, thao tác viên chỉ
  // việc bắn máy quét là chạy, không phải bấm chuột vào ô trước.
  nextTick(() => scanInputRef.value?.focus());
});
</script>

<style scoped>
/* Toàn màn hình luôn nền sáng như form VBA gốc — thợ quen bảng trắng chữ đen, và 3 màu
   vàng/xanh/đỏ của ô PROCESS chỉ đọc đúng trên nền sáng. */
.ws2-root {
  background: #ece9d8;
  color: #000;
  min-height: 100vh;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

/* ===== Băng trên ===== */
.ws2-topband {
  display: flex;
  gap: 10px;
  align-items: flex-start;
}

.ws2-fields {
  display: grid;
  grid-template-columns: 144px 104px;
  gap: 6px;
}

.fld label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  color: #333;
}

.vba-txt {
  width: 144px;
  height: 47px;
  border: 2px inset #b0b0b0;
  background: #fff;
  color: #000;
  font-size: 20px;
  font-weight: 700;
  padding: 0 6px;
  font-family: inherit;
}

.vba-txt.txt-sm {
  width: 104px;
}

/* txt_COLOR nền vàng nhạt (InfoBackground) — đúng form gốc, đây là ô nhận mã quét. */
.vba-txt.txt-color {
  background: #ffffe1;
}

/* Đang gọi server nạp đơn — nhấp nháy để thấy rõ máy đang làm việc, đứng cách 1-2m vẫn nhận ra. */
.vba-txt.txt-color.scanning {
  background: #cfe4ff;
  animation: ws2-scan-pulse 0.8s ease-in-out infinite;
}

@keyframes ws2-scan-pulse {
  50% { background: #ffffe1; }
}

/* delta_rawline — ô số lớn nhất trên form (288×113pt gốc) */
.ws2-delta {
  width: 384px;
  height: 151px;
  border: 2px inset #b0b0b0;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.delta-caption {
  font-size: 11px;
  font-weight: 700;
  color: #666;
}

.delta-value {
  font-family: 'Courier New', monospace;
  font-size: 68px;
  font-weight: 700;
  line-height: 1;
  color: #000;
}

.delta-sub {
  font-size: 12px;
  font-weight: 700;
  color: #444;
}

.delta-sub .ok { color: #0a7d00; }
.delta-sub .wait { color: #b06a00; }

/* ===== Nút bấm — cỡ lớn cho thao tác bằng găng tay, bám kích thước form gốc ===== */
.ws2-buttons {
  display: grid;
  grid-template-columns: 208px 208px 192px;
  gap: 6px;
  align-content: start;
}

.vba-btn {
  border: 2px outset #d4d0c8;
  background: #d4d0c8;
  color: #000;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
}

.vba-btn:active:not(:disabled) {
  border-style: inset;
}

.vba-btn:disabled {
  color: #888;
  cursor: not-allowed;
}

.vba-btn.big {
  height: 152px;
  font-size: 30px;
}

.vba-btn.primary {
  background: #c8e0c8;
}

.vba-btn.sm {
  height: 80px;
  font-size: 16px;
}

/* CLOSE trải hết cột thứ 3 để không lệch lưới */
.vba-btn.wide {
  height: 64px;
  font-size: 16px;
}

.vba-btn.tiny {
  height: 28px;
  padding: 0 10px;
  font-size: 12px;
}

/* ===== rawline ===== */
.ws2-rawline {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
}

.raw-label {
  font-weight: 700;
  color: #555;
}

.raw-value {
  font-family: 'Courier New', monospace;
  font-weight: 700;
  font-size: 16px;
  min-width: 90px;
  border: 1px inset #b0b0b0;
  background: #fff;
  padding: 2px 8px;
  text-align: right;
}

.raw-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.raw-dot.on { background: #17b317; }
.raw-dot.off { background: #d40000; }

.raw-ws {
  color: #555;
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
  font-size: 12px;
  color: #555;
}

.sim-input {
  width: 100px;
  border: 1px inset #b0b0b0;
  padding: 2px 6px;
  font-family: inherit;
}

.ws2-error {
  color: #c00;
  font-weight: 700;
  margin: 0;
}

/* Ghi chú trùng đơn giữa 2 máy — XANH THÔNG TIN, cố ý không dùng vàng/đỏ: đây không phải cảnh
   báo, mỗi máy có vòng cân riêng nên cả hai đều lưu đầy đủ. Tô vàng ở đây sẽ khiến thợ tưởng
   phải dừng lại xử lý. Cùng hệ nền sáng của cả màn hình. */
.ws2-notice {
  background: #e7f1fb;
  border: 1px solid #5b8db8;
  color: #17435f;
  font-weight: 700;
  padding: 6px 10px;
  margin: 0;
}

.ws2-hint {
  color: #555;
  font-size: 12px;
  margin: 0;
}

/* Màn hình hẹp hơn form gốc (1449px): cho băng trên xuống dòng thay vì vỡ lưới */
@media (max-width: 1240px) {
  .ws2-topband {
    flex-wrap: wrap;
  }
}
</style>
