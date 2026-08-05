/**
 * Gửi mã rack sang hệ pha màu — port phần "SEND OVER 6" của workbook VBA
 * "5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm".
 *
 * VBA gốc làm bằng cách ĐIỀU KHIỂN CHUỘT: `Mod_sendRackauto.FireRackBatch` đặt clipboard rồi
 * `ClickAt 345,200` … `ClickAt 345,440` + `SendKeys "^v"` vào 6 toạ độ MÀN HÌNH cố định của
 * ứng dụng pha màu, cuối cùng `ClickAt 750,215` để xác nhận. Cách đó không port sang web được
 * (trình duyệt không chạm được cửa sổ ứng dụng khác) và cũng vi phạm ADR-002 — trình duyệt
 * tuyệt đối không giao tiếp trực tiếp với thiết bị/ứng dụng cục bộ.
 *
 * Nên ở đây web chỉ PHÁT LỆNH: đẩy đúng danh sách 6 mã rack xuống backend, agent phía máy trạm
 * nhận và tự lo phần đưa vào ứng dụng đích. `idempotency_key` bắt buộc (rules/database-safety
 * mục 4) — agent đồng bộ lại sau khi mất mạng không được bắn trùng lệnh.
 *
 * Agent thực hiện việc này là bộ cài RIÊNG `DFAgentSetup-CanTo-InOut.msi` — nó chạy trong phiên
 * đăng nhập của thợ chứ không phải Windows Service, vì tiến trình service nằm ở session 0 không
 * chạm được desktop người dùng (xem ghi chú RunMode trong agent/installer/DFAgentSetup.wxs).
 * Bộ cài nhận cân là bộ khác, chạy song song, dùng chung mã trạm.
 */
import axios from 'axios';

/** Số ô rack mà ứng dụng đích nhận được trong MỘT lượt — đúng 6 toạ độ của FireRackBatch. */
export const RACK_BATCH_SIZE = 6;

export type RackAction = 'OUT' | 'IN';

export interface RackDispatchResult {
  ok: boolean;
  /** Thông báo đã sẵn sàng để hiện thẳng cho thao tác viên (tiếng Việt, nói rõ phải làm gì). */
  message: string;
}

/**
 * Chờ bao lâu để Agent nhận và thực hiện xong lệnh trước khi coi là "chưa tới nơi".
 *
 * Agent poll mỗi 2 giây (`Rack:PollIntervalMs`), một lượt OUT mất ~2.5 giây thao tác chuột
 * (150 + 220 + 6×200 + 200). 12 giây là dư cho cả hai, mà vẫn đủ ngắn để thợ không đứng chờ
 * mãi trước khi biết là hỏng.
 */
const CHO_KET_QUA_MS = 12_000;
const NHIP_HOI_MS = 700;

const nghi = (ms: number) => new Promise((r) => setTimeout(r, ms));

type TrangThaiLenh = 'PENDING' | 'SENT' | 'DONE' | 'FAILED';

/**
 * Hỏi lại trạng thái lệnh tới khi Agent ack xong hoặc hết thời gian chờ.
 *
 * Lỗi mạng giữa chừng KHÔNG dừng vòng hỏi: mất mạng chốc lát không có nghĩa là lệnh hỏng, và
 * lệnh vẫn nằm nguyên dưới server. Hết giờ thì trả về trạng thái cuối cùng đọc được.
 */
async function choAgentThucHien(id: string): Promise<TrangThaiLenh> {
  const hetHan = Date.now() + CHO_KET_QUA_MS;
  let cuoiCung: TrangThaiLenh = 'PENDING';

  while (Date.now() < hetHan) {
    await nghi(NHIP_HOI_MS);
    try {
      const res = await axios.get(`/api/rack-dispatch/${id}`);
      const st = res.data?.data?.command_status as TrangThaiLenh | undefined;
      if (st) {
        cuoiCung = st;
        if (st === 'DONE' || st === 'FAILED') return st;
      }
    } catch {
      // bỏ qua, hỏi lại nhịp sau
    }
  }

  return cuoiCung;
}

function newIdempotencyKey(wsCode: string, action: RackAction): string {
  const rand = Math.random().toString(36).slice(2, 10);
  return `rack-${wsCode}-${action}-${Date.now()}-${rand}`;
}

/**
 * `racks` là mảng 6 phần tử, ô trống để chuỗi rỗng — giữ nguyên ĐỘ DÀI và THỨ TỰ vì mỗi vị trí
 * ứng với một ô nhập cố định bên ứng dụng đích (VBA bắn theo toạ độ, không theo tên trường).
 */
export async function guiRackSangAgent(
  action: RackAction,
  racks: string[],
  workstationCode: string
): Promise<RackDispatchResult> {
  try {
    const res = await axios.post('/api/rack-dispatch', {
      workstation_code: workstationCode,
      action,
      racks,
      idempotency_key: newIdempotencyKey(workstationCode, action),
    });

    const id: string | undefined = res.data?.data?.id;
    const viecDaLam = action === 'OUT'
      ? `${racks.filter(Boolean).length} mã rack`
      : 'lệnh NHẬN (IN)';

    // Xếp được lệnh CHƯA phải là hệ pha màu đã nhận. Hỏi lại tới khi Agent ack — Agent tắt,
    // sai toạ độ hay thao tác hỏng đều phải hiện ra chữ khác nhau, nếu không thợ tưởng đã cấp
    // rack rồi mà thực tế chưa có gì.
    if (!id) {
      return { ok: true, message: `Đã xếp ${viecDaLam} vào hàng đợi gửi sang hệ pha màu.` };
    }

    const ketQua = await choAgentThucHien(id);

    if (ketQua === 'DONE') {
      return { ok: true, message: `Hệ pha màu đã nhận ${viecDaLam}.` };
    }
    if (ketQua === 'FAILED') {
      return {
        ok: false,
        message: `Agent KHÔNG thực hiện được ${viecDaLam} (xem log Agent trên máy trạm). `
          + 'Dùng nút COPY để dán tay sang hệ pha màu.',
      };
    }
    // PENDING/SENT quá thời gian chờ: lệnh còn nằm đó, Agent sẽ vẫn chạy nếu bật lại — không
    // nói là hỏng hẳn, nhưng cũng tuyệt đối không được nói là đã gửi xong.
    return {
      ok: false,
      message: ketQua === 'PENDING'
        ? 'Agent trên máy trạm CHƯA lấy lệnh — kiểm tra Agent còn chạy không (Start Menu > DF Local Agent). '
          + 'Trong lúc đó dùng nút COPY để dán tay.'
        : 'Agent đã nhận lệnh nhưng chưa báo xong — kiểm tra lại bên hệ pha màu xem đã vào chưa.',
    };
  } catch (err: any) {
    const status = err?.response?.status;
    if (status === 404) {
      return {
        ok: false,
        message: 'Máy chủ chưa bật chức năng gửi rack (endpoint /api/rack-dispatch chưa có). '
          + 'Dùng nút COPY để lấy danh sách mã rack rồi dán tay sang hệ pha màu.',
      };
    }
    return {
      ok: false,
      message: err?.response?.data?.message
        || 'Không gửi được lệnh xuống agent — kiểm tra mạng và agent trên máy trạm. '
        + 'Trong lúc đó dùng nút COPY để dán tay sang hệ pha màu.',
    };
  }
}
