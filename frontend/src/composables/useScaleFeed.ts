// frontend/src/composables/useScaleFeed.ts
//
// Đọc số cân sống + trừ bì (tare/delta) — trích từ WeighingStation.vue (V1) để màn hình
// /weighing-station-v2 dùng lại đúng một thuật toán, không chép tay lần hai.
//
// CHỦ Ý: V1 hiện GIỮ NGUYÊN bản của nó, chưa chuyển sang composable này — không sửa màn
// hình đang chạy sản xuất thật trong lúc V2 còn dở. Sau khi V2 được nghiệm thu thì gộp V1
// vào đây và xoá bản trùng bên V1.
//
// Port đúng VBA Mod_delta_raw (workbook "4.semiauto-small scale ... DF026-027.xlsm"):
//   Delta_Begin        -> resetTareForNewSlot()  : mỗi ô/slot mới thì bì reset về null
//   AutoFlow_OnWeight  -> ingestRawWeight()      : net = |raw - bì|, luôn Abs()

import { ref, watch, onUnmounted } from 'vue';
import axios from 'axios';
import { currentWorkstation } from '../services/workstation';

const TARE_STORAGE_KEY = 'df_weigh_tare_state_v2';

/**
 * Số cân cũ hơn ngưỡng này coi như KHÔNG CÒN GIÁ TRỊ: không hiển thị, không được làm bì, không
 * tính delta. Backend cache số cân tới 15 giây — quá thừa so với nhịp đẩy của Agent (~150ms) —
 * nên nếu không có ngưỡng này thì lúc Agent chết/rút cân, màn hình vẫn đứng yên hiển thị số
 * cuối cùng như thể cân đang hoạt động, và tệ hơn: bấm NEXT sẽ chốt bì vào chính số cũ đó.
 *
 * 1500ms = ~10 lần nhịp đẩy của Agent, đủ rộng để không báo động giả khi mạng xưởng chớp.
 */
const STALE_READING_MS = 1500;

export function useScaleFeed() {
  // liveWeight = NET (đã trừ bì) — giá trị dùng để hiển thị/so dung sai/gửi khi lưu.
  const liveWeight = ref<number>(0);
  const grossWeight = ref<number>(0);
  const isStable = ref<boolean>(false);

  // scaleOnline = gọi được API backend. KHÔNG đồng nghĩa với "cân đang hoạt động": Agent có thể
  // đã chết hoặc cân bị rút dây mà backend vẫn trả 200 kèm số cũ trong cache.
  const scaleOnline = ref<boolean>(false);

  // signalLive = Agent thực sự vừa đẩy số lên trong vòng STALE_READING_MS. Đây mới là đèn báo
  // "cân sống" đúng nghĩa, dùng cho UI và làm điều kiện chốt bì.
  const signalLive = ref<boolean>(false);

  // Bì sống trong phiên làm việc (không lưu DB) — đúng bản chất VBA (biến module trong form
  // đang mở, mất khi đóng form/chuyển sang ô khác).
  const tareBaseline = ref<number | null>(null);

  // Tương đương cặp cờ AutoRunning/DeltaEnabled của VBA: chỉ khi đang có ô được mở (bấm NEXT)
  // thì AutoFlow_OnWeight mới chạy — và khi đó lần đọc ỔN ĐỊNH ĐẦU TIÊN tự động trở thành bì.
  const armed = ref<boolean>(false);

  // Mặc định TẮT giả lập: khi bật, fetchLiveWeight() thoát ngay nên số cân thật từ Agent bị
  // bỏ qua hoàn toàn mà không có dấu hiệu gì (bài học từ V1).
  const useSimValue = ref<boolean>(false);
  const simulatedWeight = ref<number>(0);

  function saveTareToStorage(slotKey: string, tare: number) {
    localStorage.setItem(TARE_STORAGE_KEY, JSON.stringify({ slotKey, tare }));
  }

  function clearTareStorage() {
    localStorage.removeItem(TARE_STORAGE_KEY);
  }

  function restoreTareFromStorage(slotKey: string): number | null {
    try {
      const raw = localStorage.getItem(TARE_STORAGE_KEY);
      if (!raw) return null;
      const parsed = JSON.parse(raw);
      return parsed.slotKey === slotKey ? parsed.tare : null;
    } catch {
      return null;
    }
  }

  /** Delta_Begin: bắt đầu 1 ô mới — bỏ bì cũ, chờ chốt bì lại từ đầu. */
  function resetTareForNewSlot() {
    tareBaseline.value = null;
    liveWeight.value = 0;
    grossWeight.value = 0;
    isStable.value = false;
    clearTareStorage();
  }

  /**
   * Cổng vào DUY NHẤT cho mọi nguồn số cân thô (cân thật qua Agent, hoặc giả lập). Số chưa
   * ổn định chỉ cập nhật "cân gộp" hiển thị, KHÔNG đụng vào bì/net — đúng VBA: chỉ số đã qua
   * StableFilter mới được đưa vào CheckRange.
   *
   * `fresh = false` nghĩa là số này lấy từ cache cũ, Agent không còn đẩy số mới. Khi đó GIỮ
   * NGUYÊN mọi thứ đang hiển thị và thoát — đúng quy ước TV6 của VBA (ReadLastLineFast trả ""
   * thì PushRawToForm Exit Sub, không ghi đè kết quả cũ). Tuyệt đối không được để số cũ này
   * lọt vào nhánh chốt bì bên dưới.
   */
  function ingestRawWeight(raw: number, stable: boolean, fresh = true) {
    signalLive.value = fresh;
    if (!fresh) {
      isStable.value = false; // không cho phép thao tác nào coi số cũ là "đã ổn định"
      return;
    }

    grossWeight.value = raw;
    isStable.value = stable;
    if (!stable) return;

    // Chưa mở ô nào (chưa bấm NEXT) thì chỉ hiển thị cân gộp, không đụng bì/net —
    // đúng `If Not AutoRunning Then Exit Sub` của AutoFlow_OnWeight.
    if (!armed.value) return;

    // TỰ ĐỘNG lấy bì ở lần đọc ổn định ĐẦU TIÊN sau khi mở ô, rồi thoát luôn (lần đọc này
    // KHÔNG tính là kết quả) — port nguyên văn:
    //     If DeltaBaseWeight = -1 Then DeltaBaseWeight = rawW : Exit Sub
    if (tareBaseline.value === null) {
      tareBaseline.value = raw;
      liveWeight.value = 0;
      return;
    }

    // CÓ DẤU — bớt vật tư ra khỏi đĩa xuống dưới mốc bì thì hiện số ÂM (yêu cầu 2026-08-01).
    //
    // Lịch sử chỗ này, vì nó từng sai 2 lần:
    //   - Bản port đầu dùng Math.abs(), theo `deltaVal = Abs(rawW - DeltaBaseWeight)` đọc được
    //     từ BẢN SAO ĐÃ MỞ KHOÁ trong git. SAI NGUỒN: workbook đang chạy thật
    //     ("4.semiauto-small scale ... DF026-027.xlsm", khoá VBA project — phải đọc chuỗi thẳng
    //     từ xl/vbaProject.bin mới thấy) dùng:
    //         deltaVal = rawW - DeltaBaseWeight
    //         If deltaVal < 0 Then deltaVal = 0
    //     tức KẸP về 0, không hề có Abs. Hai file lệch nhau đúng ở đoạn CALC này.
    //   - Abs() là lựa chọn tệ nhất trong ba: nhấc đĩa ra khỏi cân cho số cân về 0 thì
    //     |0 - bì| = đúng bằng bì, một số DƯƠNG lớn — thậm chí có thể rơi trúng dải ±1% và ăn
    //     nền XANH "đạt" cho một ô chưa hề cân.
    //   - Bản gốc kẹp về 0 thì không nói dối, nhưng giấu mất chuyện đã tụt xuống dưới mốc bì.
    //
    // Để có dấu là rõ nhất: tụt dưới bì hiện số âm -> ratio âm < 0.99 -> nền vàng "chưa đủ",
    // thao tác viên thấy ngay mình đã lấy quá tay, không có cách nào nhầm thành ĐẠT.
    liveWeight.value = raw - tareBaseline.value;
  }

  /**
   * Bỏ bì đang giữ để lần đọc ổn định kế tiếp tự lấy bì mới. Dùng khi bì bị chốt nhầm (đặt
   * lệch, rung tay lúc cân chưa đứng hẳn). VBA không có nút này — muốn làm lại phải CLEAR cả
   * form — nhưng đây chỉ là van an toàn, không xen vào luồng thao tác bình thường.
   */
  function retare() {
    tareBaseline.value = null;
    liveWeight.value = 0;
    clearTareStorage();
  }

  const fetchLiveWeight = async () => {
    if (useSimValue.value) return; // đang giả lập thì bỏ qua số từ Agent
    if (!currentWorkstation.value) return;
    try {
      // Response API là object PHẲNG ({status, workstation_id, weight, is_stable}) — không
      // lồng thêm lớp "data" (bug đã sửa ở V1 ngày 2026-07-17).
      // local=1: ưu tiên cái cân cắm ở CHÍNH máy đang mở màn hình này, nhận diện qua IP nguồn
      // (Agent và trình duyệt cùng chạy trên máy trạm nên backend thấy chung một IP). Nhờ vậy
      // cài bộ MSI y hệt nhau lên bao nhiêu máy cũng được, không phải sửa Workstation:Id từng
      // máy — trước đây mọi máy đều đóng cứng "WS-WEIGH-SCALE" nên hai trạm cân cùng lúc là
      // đè số của nhau. Backend chỉ dùng tới IP khi số theo mã trạm cũ hơn, nên trạm đã cấu
      // hình tay vẫn chạy y nguyên (xem DeviceController::getReading).
      const res = await axios.get(`/api/devices/readings/${currentWorkstation.value.id}?local=1`);
      if (res.data?.status === 'SUCCESS') {
        // has_reading/age_ms là trường mới; backend cũ chưa có thì `age_ms === undefined` —
        // coi như còn tươi để không làm hỏng màn hình khi frontend deploy trước backend.
        const ageMs = res.data.age_ms;
        const fresh = res.data.has_reading !== false && (ageMs == null || ageMs <= STALE_READING_MS);

        ingestRawWeight(parseFloat(res.data.weight ?? 0), Boolean(res.data.is_stable), fresh);
        scaleOnline.value = true;
      }
    } catch {
      scaleOnline.value = false;
      signalLive.value = false;
    }
  };

  let livePoller: ReturnType<typeof setInterval> | null = null;

  function startPolling(intervalMs = 1000) {
    stopPolling();
    livePoller = setInterval(fetchLiveWeight, intervalMs);
  }

  function stopPolling() {
    if (livePoller) {
      clearInterval(livePoller);
      livePoller = null;
    }
  }

  watch(simulatedWeight, (newVal) => {
    if (useSimValue.value) ingestRawWeight(newVal, true);
  });

  // Bật/tắt giả lập — không trộn bì giữa cân thật và giả lập.
  watch(useSimValue, () => resetTareForNewSlot());

  onUnmounted(stopPolling);

  return {
    liveWeight,
    grossWeight,
    isStable,
    scaleOnline,
    signalLive,
    tareBaseline,
    armed,
    useSimValue,
    simulatedWeight,
    ingestRawWeight,
    retare,
    resetTareForNewSlot,
    restoreTareFromStorage,
    fetchLiveWeight,
    startPolling,
    stopPolling,
  };
}
