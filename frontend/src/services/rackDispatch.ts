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
    await axios.post('/api/rack-dispatch', {
      workstation_code: workstationCode,
      action,
      racks,
      idempotency_key: newIdempotencyKey(workstationCode, action),
    });
    return {
      ok: true,
      message: action === 'OUT'
        ? `Đã gửi ${racks.filter(Boolean).length} mã rack sang hệ pha màu.`
        : 'Đã gửi lệnh NHẬN (IN) sang hệ pha màu.',
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
