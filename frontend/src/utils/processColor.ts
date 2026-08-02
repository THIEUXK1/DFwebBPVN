/**
 * Suy trạng thái dung sai của một ô cân — port nguyên văn `Mod_UI_processcolor.CheckRange` (VBA).
 *
 *   ratio = số cân / mục tiêu
 *   ratio < 0.99            -> vàng  RGB(250,230,5)   chưa đủ
 *   0.99 <= ratio <= 1.01   -> xanh  RGB(120,250,20)  đạt (đúng ±1%)
 *   ratio > 1.01            -> đỏ    RGB(255,20,0)    vượt
 *   mục tiêu rỗng/<=0       -> không màu
 *
 * Tách ra file riêng vì có HAI chỗ tô theo cùng quy tắc này: ô PROCESS trong lưới 9 dòng và ô số
 * DELTA cỡ lớn ở băng trên. Hai chỗ đó nằm cạnh nhau trên cùng màn hình, nên lệch nhau dù chỉ ở
 * ranh giới 0.99/1.01 là thợ thấy số to báo ĐẠT mà ô trong bảng vẫn vàng — mất luôn niềm tin vào
 * cả hai. Một nguồn duy nhất thì không có đường nào để trôi dạt.
 *
 * Số ÂM (lấy vật tư ra tụt xuống dưới mốc bì) rơi vào nhánh `ratio < 0.99` -> vàng "chưa đủ", và
 * quan trọng là KHÔNG có đường nào cho số âm ăn màu xanh.
 */
export type ProcessTone = 'none' | 'under' | 'ok' | 'over';

/** Dùng ĐÚNG mã màu RGB của form gốc (yêu cầu người dùng) — không thay bằng biến theme. */
export const PROCESS_COLORS: Record<Exclude<ProcessTone, 'none'>, string> = {
  under: '#FAE605',
  ok: '#78FA14',
  over: '#FF1400',
};

export function processTone(value: number | null | undefined, target: unknown): ProcessTone {
  if (value === null || value === undefined) return 'none';
  const t = Number(target);
  if (!t || t <= 0) return 'none';

  const ratio = value / t;
  if (ratio < 0.99) return 'under';
  if (ratio <= 1.01) return 'ok';
  return 'over';
}

/** Nền của ô PROCESS. `none` = trắng (đúng nhánh "mục tiêu rỗng" của VBA). */
export function processBackground(tone: ProcessTone): string {
  return tone === 'none' ? '#FFFFFF' : PROCESS_COLORS[tone];
}
