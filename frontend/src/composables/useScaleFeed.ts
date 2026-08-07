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

/**
 * Số cân cũ hơn ngưỡng này coi như KHÔNG CÒN GIÁ TRỊ: không hiển thị, không được làm bì, không
 * tính delta. Backend cache số cân tới 15 giây — quá thừa so với nhịp đẩy của Agent (~150ms) —
 * nên nếu không có ngưỡng này thì lúc Agent chết/rút cân, màn hình vẫn đứng yên hiển thị số
 * cuối cùng như thể cân đang hoạt động, và tệ hơn: bấm NEXT sẽ chốt bì vào chính số cũ đó.
 *
 * 1500ms = ~10 lần nhịp đẩy của Agent, đủ rộng để không báo động giả khi mạng xưởng chớp.
 */
const STALE_READING_MS = 1500;

/**
 * Ngưỡng để BÁO CHO NGƯỜI NHÌN là mất tín hiệu — cố ý rộng gấp đôi `STALE_READING_MS`.
 *
 * Hai việc khác hẳn nhau, trước 05/08/2026 bị gộp làm một và đó là lỗi:
 *
 *   · CỔNG AN TOÀN (1500ms): số cũ thì không được làm bì, không được tính delta, không được lưu.
 *     Ngưỡng này phải CHẶT, vì sai một lần là ghi xuống DB một trọng lượng không có thật.
 *   · ĐÈN BÁO (3000ms): nói với thợ "cân chết rồi, đi kiểm tra Agent/dây cân". Ngưỡng này phải
 *     RỘNG, vì một khoảng trễ thoáng qua (backend một tiến trình đang bận, Agent chờ một backend
 *     chết trong danh sách) làm số cân trễ hơn 1.5 giây LÀ CHUYỆN THƯỜNG — mà dùng chung ngưỡng
 *     chặt thì cảnh báo đỏ nhấp nháy liên tục ngay giữa lúc số vẫn đang về đều (người dùng báo
 *     05/08/2026: "liên tục báo mất tín hiệu Agent, kiểu nhấp nháy, mặc dù đang nhận").
 *
 * Cảnh báo nhấp nháy tệ hơn hẳn cảnh báo chậm 3 giây: thợ quen mắt với nó rồi thì lúc cân CHẾT
 * THẬT cũng không ai còn nhìn nữa.
 */
const LOST_SIGNAL_MS = 3000;

/**
 * Loại cân mà màn hình đang dùng — cũng chính là loại BỘ CÀI Agent phục vụ nó:
 *   SMALL → Agent "Cân nhỏ" (service DFAgentSmall, mã trạm WS-SCALE-*), màn /weighing-station-v2
 *   LARGE → Agent "Cân to"  (service DFAgentLarge, mã trạm WS-LARGE-*), màn /weighing-station-large
 *
 * Hai bộ cài chạy song song được trên CÙNG một máy, mà backend ghép cặp trình duyệt với Agent
 * theo IP nguồn — nên nếu không nói rõ đang hỏi cân nào thì màn Cân to có thể đọc phải số của
 * cân nhỏ. Tham số này chính là thứ tách hai đường đó ra.
 */
export type ScaleKind = 'SMALL' | 'LARGE';

/**
 * ĐƯỜNG CÂN CỤC BỘ (ADR-013) — cổng mà Agent trên CHÍNH máy trạm này đang nghe.
 *
 * Vì sao có: Agent đọc cân mỗi 10ms, nhưng đi qua backend thì con số phải chờ hai lần nhịp
 * (Agent đẩy 200ms + màn hình hỏi 200ms) cộng hai chặng mạng qua một backend xử lý tuần tự —
 * tổng 400-900ms. Đọc thẳng Agent trên loopback còn ~50ms, tức ngang bản VBA đang chạy ngoài
 * xưởng (nó đọc cùng file log, cùng máy, không qua server nào).
 *
 * Con số cổng là QUY ƯỚC hai bên tự biết: trang web không có cách nào đọc `appsettings.json` của
 * Agent. Đổi ở đây thì phải đổi `LocalWeightServer.CongMacDinh*` bên `agent/LocalWeightServer.cs`.
 * Hai loại cân hai cổng vì hai bộ cài chạy song song được trên cùng một máy.
 */
const CONG_AGENT_CUC_BO: Record<ScaleKind, number> = { SMALL: 8770, LARGE: 8771 };

/**
 * Cắt cuộc gọi cục bộ sau ngần này. Trên loopback, trả lời mất chưa tới 1ms và cổng đóng thì bị
 * từ chối ngay — ngưỡng này chỉ để phòng trường hợp bệnh hoạn (Agent treo giữa lúc ghi), không
 * phải để chờ đợi gì.
 */
const TIMEOUT_CUC_BO_MS = 300;

/**
 * Hỏng đường cục bộ thì nghỉ ngần này rồi thử lại — Agent khởi động lại (cập nhật, restart
 * service) là tự dùng lại đường nhanh mà thợ không phải F5.
 *
 * Đừng hạ xuống quá thấp: máy văn phòng mở màn hình này KHÔNG có Agent, nên với chúng mọi lần
 * thử đều hỏng và chỉ tổ đốt thêm một cuộc gọi mỗi nhịp.
 */
const THU_LAI_CUC_BO_MS = 30_000;

export function useScaleFeed(scaleKind: ScaleKind = 'SMALL') {
  // liveWeight = NET (đã trừ bì) — giá trị dùng để hiển thị/so dung sai/gửi khi lưu.
  const liveWeight = ref<number>(0);
  const grossWeight = ref<number>(0);
  const isStable = ref<boolean>(false);

  // scaleOnline = CÓ NGUỒN số cân đang trả lời — Agent cục bộ hoặc backend, tuỳ đường nào đang
  // dùng (ADR-013). KHÔNG đồng nghĩa với "cân đang hoạt động": Agent có thể đã chết hoặc cân bị
  // rút dây mà nguồn vẫn trả 200 kèm số cũ (xem `signalLost`).
  //
  // Từ ADR-013 nó cũng KHÔNG còn đồng nghĩa với "backend sống": đang chạy đường cục bộ thì màn
  // hình không hề gọi backend trong nhịp này. Chỗ theo dõi backend là `duongThong` của hàng đợi
  // gửi mẻ (services/saveQueue) — đúng chỗ, vì hậu quả của backend chết là KHÔNG LƯU ĐƯỢC chứ
  // không phải mất số cân.
  const scaleOnline = ref<boolean>(false);

  /**
   * Đang lấy số từ Agent cục bộ (true) hay vòng qua backend (false).
   *
   * Xuất ra ngoài để màn hình vừa dày được nhịp poll khi đường nhanh còn sống, vừa nói cho người
   * đứng máy biết đang chạy đường nào — không nhìn thấy thì lúc đường nhanh chết âm thầm sẽ
   * không ai hiểu vì sao số cân bỗng ì ra.
   */
  const nguonCucBo = ref<boolean>(false);

  // 0 = cứ thử đường cục bộ. Khác 0 = mốc thời gian được phép thử lại (xem THU_LAI_CUC_BO_MS).
  let mocThuLaiCucBo = 0;

  // signalLive = Agent thực sự vừa đẩy số lên trong vòng STALE_READING_MS. Đây là CỔNG AN TOÀN:
  // điều kiện chốt bì, tính delta và cho phép lưu. KHÔNG dùng nó để bật đèn cảnh báo — xem
  // `signalLost` ngay dưới.
  const signalLive = ref<boolean>(false);

  /**
   * signalLost = đã QUÁ LÂU (LOST_SIGNAL_MS) không có số nào mới. Đây mới là thứ dùng cho MÀN
   * HÌNH: đèn đỏ, chữ "MẤT TÍN HIỆU", bỏ màu ô delta. Tắt ngay lập tức khi có số mới.
   */
  const signalLost = ref<boolean>(false);

  /** Tuổi của số đọc gần nhất theo đồng hồ máy chủ (ms) — hiện ra để còn đoán được nguyên nhân. */
  const readingAgeMs = ref<number | null>(null);

  // Mốc lần cuối nhận được số TƯƠI. Khởi đầu là lúc mở màn hình để không loé cảnh báo đỏ ngay
  // giây đầu tiên, trước cả khi nhịp poll kịp chạy lần nào.
  let mocTuoiCuoi = Date.now();

  function danhGiaMatTinHieu() {
    signalLost.value = Date.now() - mocTuoiCuoi > LOST_SIGNAL_MS;
  }

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

  /*
   * ĐÃ GỠ (2026-08-02) bộ 3 hàm lưu/khôi phục bì qua localStorage (`df_weigh_tare_state_v2`).
   * Chúng chưa bao giờ chạy: `saveTareToStorage` không có chỗ nào gọi, nên khoá đó không bao giờ
   * được ghi, và `restoreTareFromStorage` — tuy có xuất ra ngoài — chỉ có thể trả về null. Giữ
   * lại một hàm khôi phục luôn trả null là cái bẫy cho người dùng nó sau này.
   *
   * Việc nhớ bì qua F5 hiện do màn hình tự lo, gói chung trong phiên làm việc của trạm
   * (`df_ws2_session_v1` trong WeighingStationV2) — ở đó bì được lưu KÈM vị trí ô đang cân và số
   * cân gộp để còn đối chiếu xem đĩa cân có bị đụng vào hay không. Bản trong composable này
   * không mang theo hai thứ đó nên dù có chạy cũng không đủ an toàn.
   */

  /** Delta_Begin: bắt đầu 1 ô mới — bỏ bì cũ, chờ chốt bì lại từ đầu. */
  function resetTareForNewSlot() {
    tareBaseline.value = null;
    liveWeight.value = 0;
    grossWeight.value = 0;
    isStable.value = false;

    // CỐ Ý không có nhánh riêng cho giả lập ở đây. Bì phải chốt bằng số ĐANG CÓ trên cân lúc bấm
    // NEXT — vật tư ô trước vẫn nằm nguyên trên đĩa, nên chỉ có vậy thì ô mới mới đếm đúng phần
    // đổ thêm ("cân lại từ 0 dù là cân tiếp"). Đặt cứng bì = 0 cho giả lập là biến số gõ vào
    // thành số cân luôn, tức bỏ mất chính cái hành vi đang cần thử.
    //
    // Việc để `null` ở đây chạy đúng được với giả lập là nhờ `fetchLiveWeight` nạp lại số mỗi
    // nhịp: sau NEXT, nhịp kế tiếp chốt ngay bì = số đang gõ và ô DELTA về 0.00 trong 200ms.
    // Trước khi có nhịp đó thì nguồn duy nhất là `watch(simulatedWeight)` — chỉ chạy khi giá trị
    // ĐỔI — nên số đầu tiên gõ vào bị ăn làm bì và màn hình đứng ở 0.00 cho tới khi gõ số thứ hai.
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
    // Số rác thì BỎ QUA HẲN, giữ nguyên số đang hiển thị — đúng như cái cân thật giữ nguyên mặt
    // số khi không đọc được gì mới.
    //
    // Chỗ này bắt được hai nguồn rác thật:
    //   · Ô nhập giả lập: `<input type="number">` trả CHUỖI RỖNG ở mọi trạng thái gõ dở — gõ
    //     "12." là trình duyệt coi chưa hợp lệ. Mà JS thì `'' - bì` ra số chứ không ra lỗi, nên
    //     cứ gõ tới dấu chấm thập phân là ô DELTA nháy một cái về 0.00.
    //   · Cân thật: `parseFloat(res.data.weight)` ra NaN khi Agent đẩy lên chuỗi hỏng — trước
    //     đây NaN chạy thẳng vào `liveWeight` và mặt số hiện "NaN".
    if (!Number.isFinite(raw)) return;

    signalLive.value = fresh;
    if (!fresh) {
      isStable.value = false; // không cho phép thao tác nào coi số cũ là "đã ổn định"
      danhGiaMatTinHieu();
      return;
    }

    mocTuoiCuoi = Date.now();
    signalLost.value = false;

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
  }

  /**
   * Áp một bộ số đọc lên màn hình. Dùng CHUNG cho cả hai nguồn — Agent cục bộ cố ý trả về đúng
   * hình dạng JSON của `DeviceController::getReading` (xem `agent/LocalWeightServer.cs`), nên ở
   * đây không có nhánh nào rẽ theo nguồn. Lệch một tên trường là sinh ra hai đoạn xử lý phải giữ
   * đồng bộ với nhau mãi mãi.
   */
  function apDungSoDoc(d: any) {
    // has_reading/age_ms là trường mới; backend cũ chưa có thì `age_ms === undefined` — coi như
    // còn tươi để không làm hỏng màn hình khi frontend deploy trước backend.
    const ageMs = d?.age_ms;
    readingAgeMs.value = typeof ageMs === 'number' ? ageMs : null;
    const fresh = d?.has_reading !== false && (ageMs == null || ageMs <= STALE_READING_MS);

    ingestRawWeight(parseFloat(d?.weight ?? 0), Boolean(d?.is_stable), fresh);
    scaleOnline.value = true;
  }

  /**
   * Hỏi Agent trên chính máy này. Trả `null` nghĩa là không có đường đó — người gọi phải tự rơi
   * về backend.
   *
   * Dùng `fetch` chứ không phải `axios`: axios đã bị đặt `baseURL` trỏ backend và có interceptor
   * gắn token đăng nhập. Agent là một tiến trình khác, không liên quan gì tới phiên đăng nhập —
   * gửi token sang đó là rò một thứ nó không cần biết.
   */
  async function docTuAgentCucBo(): Promise<any | null> {
    // AbortController + setTimeout chứ không phải `AbortSignal.timeout()`: bản build còn hạ mục
    // tiêu xuống chrome >= 49 (vite.config), mà `AbortSignal.timeout` không có polyfill trong
    // @vitejs/plugin-legacy — dùng thẳng là máy trạm đời cũ ném TypeError mỗi nhịp.
    const huy = new AbortController();
    const hetGio = setTimeout(() => huy.abort(), TIMEOUT_CUC_BO_MS);
    try {
      const res = await fetch(`http://127.0.0.1:${CONG_AGENT_CUC_BO[scaleKind]}/weight`, {
        cache: 'no-store',
        credentials: 'omit',
        signal: huy.signal,
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      // Agent không chạy / cổng đóng / Chrome chặn (xem ghi chú Private Network Access trong
      // agent/LocalWeightServer.cs) — mọi trường hợp đều xử lý y nhau: về đường backend.
      return null;
    } finally {
      clearTimeout(hetGio);
    }
  }

  const fetchLiveWeight = async () => {
    // GIẢ LẬP: nạp lại số đang gõ mỗi nhịp, đúng như cái cân thật bắn số liên tục.
    //
    // Trước đây chỗ này `return` thẳng, nên nguồn duy nhất là `watch(simulatedWeight)` — mà
    // watch chỉ chạy khi giá trị ĐỔI. Bấm NEXT xong màn hình đứng im cho tới khi gõ một số
    // KHÁC; gõ lại đúng số cũ thì không có gì xảy ra cả.
    if (useSimValue.value) {
      ingestRawWeight(simulatedWeight.value, true);
      return; // vẫn không hỏi Agent: đang giả lập thì số thật phải bị bỏ qua hoàn toàn
    }
    if (!currentWorkstation.value) return;

    // ĐƯỜNG NHANH TRƯỚC (ADR-013). Hỏng thì rơi thẳng xuống đường backend NGAY TRONG NHỊP NÀY,
    // không bỏ trắng một nhịp — thợ không được phép thấy số cân khựng lại chỉ vì Agent vừa restart.
    if (Date.now() >= mocThuLaiCucBo) {
      const cucBo = await docTuAgentCucBo();
      if (cucBo) {
        nguonCucBo.value = true;
        mocThuLaiCucBo = 0; // còn dùng được thì lần sau vẫn thử trước, không nghỉ
        apDungSoDoc(cucBo);
        return;
      }
      nguonCucBo.value = false;
      mocThuLaiCucBo = Date.now() + THU_LAI_CUC_BO_MS;
    }

    try {
      // Response API là object PHẲNG ({status, workstation_id, weight, is_stable}) — không
      // lồng thêm lớp "data" (bug đã sửa ở V1 ngày 2026-07-17).
      // local=1: ưu tiên cái cân cắm ở CHÍNH máy đang mở màn hình này, nhận diện qua IP nguồn
      // (Agent và trình duyệt cùng chạy trên máy trạm nên backend thấy chung một IP). Nhờ vậy
      // cài bộ MSI y hệt nhau lên bao nhiêu máy cũng được, không phải sửa Workstation:Id từng
      // máy — trước đây mọi máy đều đóng cứng "WS-WEIGH-SCALE" nên hai trạm cân cùng lúc là
      // đè số của nhau. Backend chỉ dùng tới IP khi số theo mã trạm cũ hơn, nên trạm đã cấu
      // hình tay vẫn chạy y nguyên (xem DeviceController::getReading).
      // Gửi MÃ trạm, không phải id. `resolveReadingKey` phía server chỉ tra DB khi tham số là
      // SỐ (id -> code); đưa thẳng mã là bớt đúng một truy vấn mỗi 200ms mà kết quả không đổi —
      // khoá cache vốn đã đánh theo mã (`storeReading` ghi bằng chính chuỗi `workstation_id`
      // Agent gửi lên, mà chuỗi đó là mã trạm chứ không phải số).
      //
      // Không có rủi ro thứ tự deploy: backend nhận cả hai dạng, nên frontend mới chạy được với
      // backend cũ. Lùi về `id` khi trạm chưa có mã, để không gãy ở cấu hình lạ.
      //
      // `kind` chỉ định rõ đang hỏi cái cân NÀO ở máy này (xem ScaleKind ở đầu file). Backend
      // đọc khóa cache riêng cho từng loại, nên máy cài cả 2 Agent không bị lẫn số. Backend cũ
      // chưa biết tham số này thì bỏ qua nó và trả về đúng như trước — không cần deploy đồng bộ.
      const khoaCan = currentWorkstation.value.code || currentWorkstation.value.id;
      const res = await axios.get(
        `/api/devices/readings/${encodeURIComponent(String(khoaCan))}?local=1&kind=${scaleKind}`
      );
      if (res.data?.status === 'SUCCESS') apDungSoDoc(res.data);
    } catch {
      // Không gọi tới được backend (mất mạng, server chết, DNS hỏng...). Khác với nhánh
      // `fresh = false` ở trên — chỗ đó backend CÓ trả lời, chỉ là số đã cũ.
      scaleOnline.value = false;
      nguonCucBo.value = false;
      signalLive.value = false;
      // Không gọi được backend cũng là "không có số mới" — nhưng vẫn phải qua cùng một ngưỡng
      // thời gian, nếu không thì một cú rớt mạng thoáng qua lại bật đèn đỏ tức thì.
      danhGiaMatTinHieu();
      // BẮT BUỘC hạ cờ ổn định: `ingestRawWeight` không hề chạy trong nhánh này nên `isStable`
      // sẽ GIỮ NGUYÊN giá trị cũ. Mất mạng đúng lúc cân đang đứng yên là màn hình treo lại ở
      // "ỔN ĐỊNH" với một con số đông cứng — thợ tưởng cân vẫn sống, mà server lại dùng chính
      // cờ này để cho phép ghi (weighFromQr chặn khi stable=false).
      isStable.value = false;
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

  // Nạp ngay lúc gõ, không đợi nhịp kế. Trùng việc với `fetchLiveWeight` là có chủ ý: nhịp poll
  // đã bảo đảm số luôn được nạp lại, còn watch này chỉ để gõ xong thấy đổi tức thì.
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
    nguonCucBo,
    signalLive,
    signalLost,
    readingAgeMs,
    tareBaseline,
    armed,
    useSimValue,
    simulatedWeight,
    ingestRawWeight,
    retare,
    resetTareForNewSlot,
    fetchLiveWeight,
    startPolling,
    stopPolling,
  };
}
