import { ref } from 'vue';

/**
 * Hộp thoại hỏi/báo VẼ TRONG TRANG, thay cho `window.confirm` / `window.alert`.
 *
 * ==================== LÝ DO KHÔNG PHẢI THẨM MỸ ====================
 * Chrome/Edge **thoát toàn màn hình** ngay khi một hộp thoại GỐC (alert/confirm/prompt) bật lên —
 * quy tắc chống giả mạo giao diện của trình duyệt, không có cách nào tắt. Hai trạm cân chạy F11
 * suốt ca, nên mỗi lần bấm CLEAR là thợ bị đẩy về cửa sổ thường và phải bấm F11 lại (người dùng
 * báo 07/08/2026 cho `/weighing-station-v2`, rồi báo tiếp cho `/weighing-station-large`).
 *
 * ==================== GIỮ ĐÚNG NGỮ NGHĨA CHẶN ====================
 * `alert()` là hàm CHẶN: dòng sau nó chỉ chạy khi thợ đã bấm OK. Mọi chỗ thay thế phải
 * `await baoTin(...)` chứ không gọi rồi bỏ đó — nếu không, phần việc phía sau (trả con trỏ về ô
 * quét, khối `finally`) chạy ngay trong lúc hộp thoại còn che màn hình, và ô quét giành lại tiêu
 * điểm khiến phím Enter (hoặc một cú quét nhầm) rơi vào đó thay vì vào hộp thoại.
 *
 * ==================== BẪY: CỬA SỔ IN BỊ CHẶN ====================
 * `window.open` chỉ chạy được khi còn "user activation", tức TRƯỚC mọi `await`. Bản cũ đặt nó sau
 * `window.confirm` vẫn được vì confirm ĐỒNG BỘ; đổi sang hộp thoại này là bắt buộc `await`, nên
 * chỗ nào vừa hỏi vừa mở cửa sổ in (luồng SAVE) phải **mở cửa sổ TRƯỚC rồi mới hỏi**, thợ bấm
 * "KHÔNG" thì `printWin?.close()`. Quên đổi thứ tự là mẻ vẫn lưu nhưng KHÔNG in ra phiếu.
 */
export interface TrangThaiThoai {
  hien: boolean;
  /** `hoi` = hai nút KHÔNG/ĐỒNG Ý (trả true/false). `bao` = một nút OK (luôn trả true). */
  kieu: 'hoi' | 'bao';
  /** Nội dung đã tách theo `\n` — dòng rỗng giữ lại thành khoảng thở, xem `HopThoaiVba.vue`. */
  dong: string[];
}

export function useHopThoai() {
  const thoai = ref<TrangThaiThoai>({ hien: false, kieu: 'bao', dong: [] });
  let chotThoai: ((ok: boolean) => void) | null = null;

  function moThoai(kieu: 'hoi' | 'bao', noiDung: string): Promise<boolean> {
    // Hộp thoại trước còn treo (bấm dồn) -> chốt nó thành "không đồng ý". Bỏ qua là lời hứa cũ
    // nằm lại mãi không ai giải quyết, và hàm đang `await` nó đứng im vĩnh viễn.
    if (chotThoai) chotThoai(false);
    chotThoai = null;
    thoai.value = { hien: true, kieu, dong: String(noiDung ?? '').split('\n') };
    return new Promise<boolean>((resolve) => { chotThoai = resolve; });
  }

  function dongThoai(ok: boolean) {
    thoai.value = { ...thoai.value, hien: false };
    const chot = chotThoai;
    chotThoai = null;
    if (chot) chot(ok);
  }

  /** Hỏi có/không. Trả `true` khi thợ đồng ý. */
  const hoiXacNhan = (noiDung: string) => moThoai('hoi', noiDung);

  /** Báo một tin, chỉ có nút OK. `await` nó để giữ đúng thứ tự như `alert()` cũ. */
  const baoTin = (noiDung: string) => moThoai('bao', noiDung);

  return { thoai, hoiXacNhan, baoTin, dongThoai };
}
