/**
 * Hàng đợi lưu mẻ cân của /weighing-station-v2 (2026-08-02).
 *
 * Vấn đề giải quyết: cả mẻ chỉ chạm mạng đúng MỘT lần, lúc bấm SAVE. Mất mạng ngay lúc đó thì
 * trước đây thợ phải đứng chờ, không quét được mẻ mới, và nếu bấm CLEAR là mất trắng.
 *
 * Nay: bấm SAVE là mẻ vào hàng đợi trong localStorage rồi màn hình xoá trắng ngay — thợ quét mẻ
 * kế tiếp không phải chờ. Có mạng thì hàng đợi tự đẩy lên. Phiếu vẫn in được ngay vì trình duyệt
 * tự dựng được nội dung phiếu (xem `utils/weighSlip.ts`).
 *
 * BỐN điều kiện phải giữ, mất một cái là hỏng cả cơ chế:
 *
 *   1. `idempotency_key` sinh MỘT LẦN lúc xếp hàng và KHÔNG BAO GIỜ đổi qua các lần gửi lại.
 *      Đây là thứ duy nhất chặn được ca hiểm nhất: request đã tới server và ghi xong, nhưng phản
 *      hồi rớt giữa đường nên hàng đợi tưởng thất bại và gửi lại.
 *
 *   2. KHÔNG BAO GIỜ NGỪNG THỬ LẠI, dù lỗi thuộc loại nào. Lỗi nghiệp vụ (4xx) vẫn được ghi lại
 *      và hiện cho thợ, nhưng chỉ để BIẾT chứ không phải để DỪNG. Bản đầu ngừng thử lại khi gặp
 *      4xx với lý do "gửi lại bao nhiêu lần cũng thế" — sai, vì phần lớn 4xx thực tế ở đây tự
 *      khỏi khi người ta sửa nguyên nhân (401 hết phiên, 403 thiếu quyền trạm, khoá lô).
 *
 *   3. BỎ MẺ ĐƯỢC, NHƯNG PHẢI CÓ NGƯỜI CHỦ ĐỘNG BẤM VÀ PHẢI ĐỂ LẠI VẾT (đổi 2026-08-05).
 *      Bản 2026-08-02 cấm hẳn: đã in phiếu thì bắt buộc phải lên được server, bỏ được nghĩa là
 *      có tờ phiếu trên bàn mà dưới DB không có gì đối chiếu. Thực tế UAT cho thấy cấm tuyệt đối
 *      còn tệ hơn: mẻ hỏng vĩnh viễn (quét nhầm, lô đã đóng, phiếu bỏ đi) nằm lại chặn màn hình
 *      và bắt thợ nhìn chỉ báo đỏ mãi mãi. Nay:
 *        - KHÔNG có đường tự động bỏ mẻ — chỉ `boMe()` do người bấm tay, sau xác nhận.
 *        - Mẻ bị bỏ KHÔNG biến mất: chép nguyên payload + lỗi cuối sang nhật ký `df_ws2_da_bo_v1`
 *          để còn đối chiếu với tờ phiếu đã in.
 *
 *   4. Dùng localStorage chứ KHÔNG dùng cookie: cookie bị đính vào mọi request (tốn băng thông
 *      cho thứ thuần tuý của máy trạm) và trần ~4KB — một mẻ 9 dòng kèm bì/gộp là chạm.
 */
import { ref } from 'vue';
import axios from 'axios';

const QUEUE_KEY = 'df_ws2_save_queue_v1';

export interface QueuedSave {
  idempotency_key: string;
  /** Thời điểm xếp hàng (ISO) — để hiện "chờ từ lúc nào" và sắp thứ tự gửi. */
  queued_at: string;
  /** Nhãn ngắn cho người nhìn: màu + mã hàng. */
  nhan: string;
  /** Payload gửi thẳng lên /api/scanner/weigh-from-qr, giữ nguyên không sửa. */
  payload: Record<string, any>;
  /** Số lần đã thử gửi. */
  so_lan_thu: number;
  /**
   * Lời từ chối của server ở lần gửi gần nhất (4xx). CHỈ để hiện cho người xem — không còn là
   * cờ dừng: mẻ có lỗi này vẫn được thử lại mỗi nhịp (xem điều kiện 2 ở đầu file).
   */
  loi_nghiep_vu?: string | null;
}

/** Số mẻ đang chờ gửi — màn hình cân đọc để hiện chỉ báo. */
export const queueCount = ref(0);
/** Số mẻ mà lần gửi gần nhất bị server từ chối — để hiện chỉ báo đỏ, KHÔNG phải để ngừng gửi. */
export const stuckCount = ref(0);
/** Đang trong một lượt đẩy hàng đợi. */
export const flushing = ref(false);
/**
 * Lần ping gần nhất có tới được server không. Để thợ phân biệt hai tình huống nhìn giống hệt
 * nhau mà cách xử lý khác hẳn: "mất mạng, đứng đợi là xong" với "mạng thông nhưng máy chủ chê
 * mẻ này, phải gọi người". Khởi đầu là `true` — chưa ping lần nào thì không có cớ gì báo đứt.
 */
export const duongThong = ref(true);

function doc(): QueuedSave[] {
  try {
    const raw = localStorage.getItem(QUEUE_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

function ghi(items: QueuedSave[]) {
  try {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(items));
  } catch {
    // Hết dung lượng: không được ném lỗi ra ngoài làm hỏng luồng cân. Đếm lại theo cái đọc được.
  }
  demLai(items);
}

function demLai(items: QueuedSave[]) {
  queueCount.value = items.length;
  stuckCount.value = items.filter((i) => !!i.loi_nghiep_vu).length;
}

export function danhSachChoGui(): QueuedSave[] {
  return doc();
}

/** Sinh khoá chống ghi trùng. `crypto.randomUUID` không có trên mọi trình duyệt cũ ở xưởng. */
function sinhKhoa(prefix: string): string {
  const rnd =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
  return `ws2-${prefix}-${rnd}`.slice(0, 100); // cột DB giới hạn 100 ký tự
}

/**
 * Xếp một mẻ vào hàng đợi. Trả về bản ghi đã xếp (kèm khoá) để chỗ gọi dùng lại.
 * KHÔNG tự gửi ngay — chỗ gọi quyết định gửi luôn hay chỉ xếp hàng.
 */
export function xepHang(payload: Record<string, any>, nhan: string, prefix = 'ws'): QueuedSave {
  const item: QueuedSave = {
    idempotency_key: sinhKhoa(prefix),
    queued_at: new Date().toISOString(),
    nhan,
    payload,
    so_lan_thu: 0,
    loi_nghiep_vu: null,
  };

  const items = doc();
  items.push(item);
  ghi(items);
  return item;
}

function xoaKhoiHang(key: string) {
  ghi(doc().filter((i) => i.idempotency_key !== key));
}

function capNhat(key: string, doi: Partial<QueuedSave>) {
  ghi(doc().map((i) => (i.idempotency_key === key ? { ...i, ...doi } : i)));
}

export type KetQuaGui = 'da_gui' | 'mang' | 'nghiep_vu';

/**
 * Gửi MỘT mẻ. `kq` cho biết:
 *   'da_gui'  — server nhận (kể cả trường hợp trùng khoá, server trả reused=true)
 *   'mang'    — không tới được server, để lại hàng đợi thử sau
 *   'nghiep_vu' — server từ chối (4xx), ghi lại lời chê rồi đi tiếp mẻ sau; nhịp sau vẫn thử lại
 *
 * `message` là nguyên văn để hiện cho thợ. Trả kèm chứ không chỉ trả mã, vì nút THỬ LẠI bấm tay
 * phải nói được HỎNG VÌ CÁI GÌ ngay tại chỗ — bấm xong mà màn hình im lặng thì thợ không phân
 * biệt được "gửi xong rồi" với "vẫn hỏng y như cũ".
 */
async function guiMot(item: QueuedSave): Promise<{ kq: KetQuaGui; message: string }> {
  try {
    await axios.post('/api/scanner/weigh-from-qr', {
      ...item.payload,
      idempotency_key: item.idempotency_key,
      // Cắt ngang nếu quá lâu, thay vì treo theo mặc định của trình duyệt (có thể hàng phút với
      // mạng nửa sống nửa chết) — treo là `flushing` kẹt bật, chặn luôn mọi nhịp sau.
      // Cắt ngang KHÔNG sợ mất mẻ: nếu request thật ra đã tới server và ghi xong thì lần gửi lại
      // mang đúng `idempotency_key` cũ, server nhận ra và trả về bản đã ghi (reused=true).
    }, { timeout: 20000 });
    duongThong.value = true;
    xoaKhoiHang(item.idempotency_key);
    return { kq: 'da_gui', message: 'Máy chủ đã nhận mẻ.' };
  } catch (err: any) {
    const status = err?.response?.status;
    // Cùng quy tắc với pingThong: có mã HTTP trả về là đường thông, kể cả khi mã đó là lỗi.
    // Đặt ở đây để nút GỬI NGAY bấm tay (đi thẳng, không qua ping) vẫn cập nhật đúng chỉ báo.
    duongThong.value = !!err?.response;

    // Server CÓ trả lời và chê (4xx): ghi lại nguyên văn lời chê để thợ đọc và biết đường sửa
    // (đăng nhập lại / gọi admin cấp quyền / đợi trạm kia nhả lô). Ghi để BIẾT, không phải để
    // dừng — nhịp sau vẫn thử lại mẻ này.
    // 408/429 tách riêng vì chúng chỉ là "chậm/quá tải", không có gì để thợ sửa cả.
    if (status && status >= 400 && status < 500 && status !== 408 && status !== 429) {
      const loi = err?.response?.data?.message || `Máy chủ từ chối (HTTP ${status}).`;
      capNhat(item.idempotency_key, {
        so_lan_thu: item.so_lan_thu + 1,
        loi_nghiep_vu: loi,
      });
      return { kq: 'nghiep_vu', message: loi };
    }

    // Không có phản hồi (mất mạng/server chết) hoặc 5xx: giữ lại, thử tiếp — KHÔNG BAO GIỜ bỏ
    // cuộc. Trước đây quá 20 lần là tự đánh dấu kẹt, tức chỉ sau 5 phút mất mạng (nhịp 15s) là
    // ngừng tự gửi và bắt người bấm tay từng mẻ — mạng xưởng chết nửa tiếng hay khởi động lại
    // server là dính. Đếm mãi cũng không tốn gì: `dayHangDoi` dừng cả lượt ngay lần hỏng đầu,
    // nên tổng cộng vẫn chỉ một request mỗi 15s dù hàng đợi có bao nhiêu mẻ.
    capNhat(item.idempotency_key, { so_lan_thu: item.so_lan_thu + 1 });
    return {
      kq: 'mang',
      message: status
        ? `Máy chủ đang lỗi (HTTP ${status}) — ${err?.response?.data?.message || 'thử lại sau.'}`
        : err?.code === 'ECONNABORTED'
          ? 'Máy chủ không trả lời trong 20 giây (mạng chậm hoặc máy chủ quá tải).'
          : 'Không kết nối được máy chủ — kiểm tra dây mạng/Wi-Fi hoặc máy chủ đã tắt.',
    };
  }
}

/**
 * Đẩy toàn bộ hàng đợi, theo ĐÚNG thứ tự xếp hàng và TUẦN TỰ.
 *
 * Tuần tự chứ không song song là có chủ ý: nhiều mẻ cùng lúc có thể trỏ về cùng một lô, mà
 * `handleOrderScan` phía server có khoá chống hai máy cân chung một vòng — bắn song song là tự
 * mình tranh chấp với chính mình.
 */
export async function dayHangDoi(): Promise<{ da_gui: number; con_lai: number }> {
  if (flushing.value) return { da_gui: 0, con_lai: queueCount.value };

  flushing.value = true;
  let daGui = 0;
  try {
    // Thử LẠI TẤT CẢ, kể cả mẻ lần trước bị server từ chối.
    //
    // Bản đầu lọc bỏ mẻ có `loi_nghiep_vu` với lý do "4xx thì gửi lại bao nhiêu lần cũng thế".
    // SAI: phần lớn 4xx thực tế ở đây là TẠM THỜI và tự khỏi khi người ta sửa nguyên nhân —
    // 401 hết phiên (đăng nhập lại là xong), 403 thiếu quyền trạm (admin cấp), khoá lô (trạm
    // kia nhả ra). Lọc bỏ nghĩa là bắt thợ nhớ quay lại bấm THỬ LẠI đúng lúc, mà nếu quên thì
    // mẻ nằm đó vĩnh viễn — nay không bỏ mẻ được nữa nên "nằm đó vĩnh viễn" là kết cục thật.
    //
    // Tệ hơn: lọc ở đây cộng với cổng `queueCount <= stuckCount` bên `thuDay` làm hàng đợi chỉ
    // còn mẻ kẹt là ĐỨNG IM HOÀN TOÀN — bấm GỬI NGAY cũng không gửi gì, không có dấu hiệu nào
    // cho biết vì sao.
    //
    // Thử lại tất cả không tốn gì: đã có ping chặn ở trên nên chỉ chạy khi đường thông, và mẻ
    // hỏng thật thì `continue` chứ không `break` nên không chặn mẻ phía sau.
    for (const item of doc()) {
      const { kq } = await guiMot(item);
      if (kq === 'da_gui') {
        daGui++;
        continue;
      }
      // Mất mạng: dừng cả lượt luôn, các mẻ sau chắc chắn cũng hỏng, gửi tiếp chỉ tốn thời gian.
      if (kq === 'mang') break;
    }
  } finally {
    flushing.value = false;
  }

  return { da_gui: daGui, con_lai: queueCount.value };
}

/**
 * Rút một mẻ khỏi hàng đợi vì SERVER ĐÃ NHẬN — đây là đường DUY NHẤT một mẻ được rời hàng đợi.
 *
 * Cố ý không có hàm "bỏ mẻ" đi kèm: xem điều kiện 3 ở đầu file. Chỗ gọi chỉ được dùng hàm này
 * sau khi cầm trong tay một phản hồi 2xx thật, không phải khi "đoán là chắc xong rồi".
 */
export function danhDauDaGui(key: string) {
  xoaKhoiHang(key);
}

/**
 * Đánh dấu một mẻ là KẸT vì lỗi nghiệp vụ: ngừng tự gửi lại, nhưng GIỮ NGUYÊN trong hàng đợi.
 *
 * Giữ lại chứ không xoá là có chủ ý: phiếu đã in ra giấy rồi (màn hình cân in ngay từ dữ liệu
 * trên màn, không chờ server). Xoá khỏi hàng đợi sẽ để lại một tờ phiếu trên hàng mà không còn
 * dấu vết nào trong máy — không ai đối chiếu được nữa.
 */
export function danhDauKet(key: string, message: string) {
  capNhat(key, { loi_nghiep_vu: message });
}

/** Cho một mẻ đang kẹt được thử lại (sau khi người dùng đã sửa nguyên nhân). */
export function thuLai(key: string) {
  capNhat(key, { loi_nghiep_vu: null, so_lan_thu: 0 });
}

/**
 * THỬ LẠI MỘT MẺ, ngay lập tức, và trả lời thẳng là ĐƯỢC hay KHÔNG kèm lý do.
 *
 * Khác `dayHangDoi` ở đúng chỗ quan trọng: hàm kia đẩy cả hàng và không nói được mẻ NÀO hỏng vì
 * sao — thợ bấm THỬ LẠI rồi nhìn màn hình im lặng, không biết là đã lên server hay vẫn kẹt.
 *
 * Vẫn dùng chung cờ `flushing` với nhịp tự động: hai đường cùng gửi một mẻ tuy không sinh dữ liệu
 * trùng (đã có `idempotency_key`) nhưng làm server xử lý thừa và kết quả trả về khó hiểu.
 */
export async function guiMotNgay(key: string): Promise<{ ok: boolean; message: string }> {
  if (flushing.value) {
    return { ok: false, message: 'Đang có lượt gửi khác chạy — đợi vài giây rồi bấm lại.' };
  }

  const item = doc().find((i) => i.idempotency_key === key);
  if (!item) return { ok: false, message: 'Mẻ này không còn trong hàng đợi.' };

  flushing.value = true;
  try {
    // Xoá lời chê cũ trước khi gửi để chỉ báo đỏ không còn dính lại lỗi của lần trước khi lần
    // này thành công; hỏng tiếp thì `guiMot` tự ghi lỗi mới.
    capNhat(key, { loi_nghiep_vu: null });
    const lai = doc().find((i) => i.idempotency_key === key)!;
    const { kq, message } = await guiMot(lai);
    return { ok: kq === 'da_gui', message };
  } finally {
    flushing.value = false;
  }
}

/* ---------- Bỏ mẻ (chỉ do người bấm tay) ---------- */

const BO_KEY = 'df_ws2_da_bo_v1';
/** Giữ lại 100 mẻ bị bỏ gần nhất — đủ để đối chiếu vài ca, không phình localStorage. */
const BO_TOI_DA = 100;

export interface MeDaBo extends QueuedSave {
  /** Thời điểm bấm bỏ (ISO). */
  bo_luc: string;
  /** Lời chê cuối cùng của server trước khi bị bỏ (nếu có) — để biết bỏ vì lý do gì. */
  loi_cuoi?: string | null;
}

export function danhSachDaBo(): MeDaBo[] {
  try {
    const raw = localStorage.getItem(BO_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

/**
 * BỎ một mẻ khỏi hàng đợi. CHỈ được gọi từ một nút do người bấm, sau khi đã xác nhận.
 *
 * Không xoá trắng: mẻ được chép nguyên (payload + số lần thử + lỗi cuối) sang nhật ký
 * `df_ws2_da_bo_v1`. Vì lúc mẻ vào hàng đợi là phiếu đã in ra giấy — bỏ mà không để lại gì thì
 * tờ phiếu đó thành vô chủ, không ai truy được nó từng tồn tại. Nhật ký này là thứ duy nhất còn
 * đối chiếu được, nên đừng đổi nó thành xoá thẳng.
 */
export function boMe(key: string): boolean {
  const item = doc().find((i) => i.idempotency_key === key);
  if (!item) return false;

  try {
    const nhatKy = danhSachDaBo();
    nhatKy.unshift({ ...item, bo_luc: new Date().toISOString(), loi_cuoi: item.loi_nghiep_vu ?? null });
    localStorage.setItem(BO_KEY, JSON.stringify(nhatKy.slice(0, BO_TOI_DA)));
  } catch {
    // Hết dung lượng: vẫn cho bỏ mẻ (nếu không thì thợ kẹt màn hình vì một lý do không liên
    // quan), chỉ mất phần lưu vết.
  }

  xoaKhoiHang(key);
  return true;
}

let heartbeat: ReturnType<typeof setInterval> | null = null;

function goHet() {
  window.removeEventListener('online', thuDay);
  if (heartbeat) {
    clearInterval(heartbeat);
    heartbeat = null;
  }
}

/**
 * Gõ cửa server xem đường đã thông chưa. Gói tin bé tí, cắt ngang sau 8 giây.
 *
 * Vì sao phải ping trước thay vì cứ đẩy thẳng cả mẻ: mẻ 9 dòng là gói vài KB, mất mạng thì mỗi
 * nhịp lại ném nó ra rồi ngồi chờ hết giờ. Ping hỏi đúng một câu "server còn đó không" và biết
 * kết quả trong vài chục mili giây khi thông.
 *
 * 8 giây là ĐO ĐƯỢC chứ không phải đoán: ping lúc nóng mất ~25ms, nhưng lần gọi đầu sau khi
 * server nằm im mất **2.1s**, và lần đầu tiên sau khi khởi động mất **4.2s** (PHP dựng lại tiến
 * trình + bootstrap Laravel). Đặt 4s như bản đầu là thỉnh thoảng báo "mất kết nối" trong khi
 * server vẫn sống nguyên.
 *
 * QUY TẮC ĐỌC KẾT QUẢ — nhận BẤT KỲ mã HTTP nào là THÔNG, kể cả 404/500:
 * có mã trả về nghĩa là gói tin đã đi tới nơi và có thứ gì đó trả lời — đúng cái cần biết.
 * Đây không phải chuyện vụn: server chưa deploy route /api/ping sẽ trả 404, coi 404 là tắc thì
 * hàng đợi đứng im vĩnh viễn trên đúng những trạm cần nó nhất. Chỉ khi KHÔNG có phản hồi nào
 * (mất mạng, server chết, hết giờ chờ) mới là tắc.
 */
async function pingThong(): Promise<boolean> {
  try {
    await axios.get('/api/ping', { timeout: 8000 });
    duongThong.value = true;
  } catch (err: any) {
    duongThong.value = !!err?.response;
  }
  return duongThong.value;
}

/**
 * Vá mẻ đã nằm sẵn trong hàng đợi từ bản code cũ. Chạy mỗi lần mở màn hình cân.
 *
 * Bản cũ gửi `stable: isStable.value` — cờ SỐNG của lần đọc ngay lúc bấm SAVE, không phải câu
 * trả lời cho "mấy con số trong gói này có đứng yên không" (xem ghi chú dài ở `onSave`). Mẻ nào
 * bấm SAVE đúng lúc mất mạng, hoặc bấm NEXT xong bấm SAVE luôn, đều bị đóng băng với
 * `stable: false` và ăn 422 NOT_STABLE mãi mãi.
 *
 * Sửa code chỉ cứu được mẻ MỚI. Mẻ đã nằm trong localStorage mang theo payload cũ, mà giờ không
 * bỏ mẻ được nữa — không vá thì nó nằm đó vĩnh viễn kèm một tờ phiếu đã in ra giấy.
 *
 * Vá là ĐÚNG chứ không phải lách: mọi `weight` khác null trong payload đều đã chốt từ một lần
 * đọc ổn định (`capturedWeights` chỉ nhận `liveWeight`, mà `liveWeight` chỉ nhúc nhích sau
 * `if (!stable) return`). Cái sai nằm ở cờ, không nằm ở số.
 */
function vaMeCu() {
  const items = doc();
  let coDoi = false;

  for (const it of items) {
    if (it.payload?.stable === false) {
      it.payload.stable = true;
      // Lỗi cũ do chính cái cờ này gây ra -> xoá luôn để chỉ báo đỏ tắt đi, không doạ thợ vì
      // một nguyên nhân vừa được gỡ. Lần gửi tới có hỏng thật thì nó tự ghi lại lỗi mới.
      it.loi_nghiep_vu = null;
      coDoi = true;
    }
  }

  if (coDoi) ghi(items);
}

/** Một nhịp tự động: ping trước, thông thì mới đẩy. */
async function thuDay() {
  if (flushing.value) return;
  // Chỉ hàng đợi RỖNG mới khỏi ping. Trước đây cổng này là `queueCount <= stuckCount`, tức còn
  // đúng một mẻ bị server từ chối là cả cơ chế đứng im — không ping, không đẩy, không dấu hiệu.
  if (queueCount.value === 0) return;
  if (!(await pingThong())) return;
  await dayHangDoi();
}

// Đã cân nhắc và BỎ chặn `beforeunload` khi hàng đợi còn mẻ: main.ts bắt lỗi 401 bằng
// `authStore.logout()` + `window.location.reload()`, nên phiên hết hạn sẽ bung ra hộp thoại
// "rời trang?" mà thợ không hề bấm gì — bấm Huỷ còn kẹt lại ở trang đã đăng xuất. Đóng tab
// KHÔNG mất mẻ (localStorage còn nguyên) và nhịp tự đẩy dưới đây vẫn sống khi rời màn hình cân,
// nên đổi lấy một hộp thoại doạ người là lỗ.

/**
 * Bật nhịp ping định kỳ. Gọi khi vào màn hình cân.
 *
 * Ba mốc kích hoạt, cố ý chồng lên nhau: sự kiện `online` của trình duyệt bắt được ngay lúc cắm
 * lại dây mạng, nhưng nó KHÔNG bắt được trường hợp có mạng LAN mà máy chủ chết — nên vẫn cần
 * nhịp định kỳ. Và thử một lượt ngay lúc mở màn hình để mẻ tồn từ ca trước được gửi đi.
 *
 * `online` cũng đi qua `thuDay` (ping trước) chứ không đẩy thẳng: trình duyệt chỉ biết card mạng
 * đã lên, nó không biết server đã với tới được hay chưa.
 */
export function batTuDay(nhipMs = 15000) {
  goHet();
  vaMeCu();
  demLai(doc());
  window.addEventListener('online', thuDay);
  heartbeat = setInterval(thuDay, nhipMs);
  thuDay();
}

/**
 * Rời màn hình cân. CHỈ tắt khi hàng đợi đã rỗng.
 *
 * Còn mẻ chưa gửi mà tắt nhịp là mẻ nằm im cho tới lần sau có người mở lại đúng màn hình cân —
 * trái hẳn với điều kiện 3 (đã xếp hàng thì bắt buộc phải gửi). Để nhịp chạy tiếp không tốn gì:
 * hàng đợi rỗng thì mỗi 15s nó chỉ so hai con số rồi thôi.
 */
export function tatTuDay() {
  if (queueCount.value > 0) return;
  goHet();
}
