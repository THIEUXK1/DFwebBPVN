/**
 * Dựng NỘI DUNG phiếu cân (chuỗi TSPL) NGAY TẠI TRÌNH DUYỆT.
 *
 * Đây là bản port của `WeighingJobController::buildSlipTspl` (PHP). CỐ Ý nhân bản logic: khi mất
 * mạng, mẻ cân nằm lại hàng đợi chờ đẩy lên (xem `services/saveQueue.ts`) nhưng thợ VẪN PHẢI IN
 * được phiếu ngay để dán theo hàng — mà lúc đó không hỏi server được câu nào.
 *
 * Server vẫn là nguồn sự thật: lưu thành công thì phiếu in ra lấy từ response của server, bản này
 * chỉ dùng cho đúng đường mất mạng. **Đổi bố cục phiếu thì phải sửa CẢ HAI chỗ** — có script đối
 * chiếu hai bản: `frontend/scripts/check-weigh-slip.mjs`.
 */
export interface SlipHeader {
  color: string;
  product_code: string;
  machine_code: string;
  level_code: string | null;
  /** Mốc giờ in, định dạng dd/mm/yyyy HH:MM:SS. Bỏ trống thì lấy giờ máy trạm. */
  printed_at?: string;
}

/**
 * Mã vật tư mồi của dòng CÂN TAY — phải trùng `WeighingJobItem::MANUAL_MATERIAL_CODE` bên PHP,
 * vì nó được in thẳng lên phiếu ở cột DYE CODE và phiếu hai bên phải giống nhau từng ký tự.
 */
export const MANUAL_MATERIAL_CODE = 'CANTAY';

export interface SlipItem {
  sequence_no?: number | null;
  rack_code?: string | null;
  material_code: string;
  planned_weight: number | string | null;
  actual_weight: number | string | null;
  /** ACCEPTED | REJECTED | PENDING — xem processStatus() bên dưới. */
  process_status: string;
}

/**
 * Port của `WeighingJobItem::getProcessStatusAttribute`.
 *
 * `completed` = dòng này có nằm trong mẻ vừa chốt hay không. Ở luồng lưu cả mẻ thì MỌI dòng có
 * mục tiêu đều được ghi, nên đều là true — dòng chưa cân (actual = null) thành REJECTED, đúng
 * VBA btnSave_Click (ô PROCESS trống nên nền không xanh).
 */
export function processStatus(
  item: {
    planned_weight: any;
    tolerance_minus: any;
    tolerance_plus: any;
    actual_weight: any;
    material_code?: any;
  },
  completed = true
): string {
  if (!completed) return 'PENDING';

  // Thứ tự các nhánh phải TRÙNG bản PHP, không chỉ trùng kết quả: dòng cân tay chưa có số cân
  // phải ra MANUAL chứ không phải REJECTED, nên nhánh này bắt buộc đứng TRƯỚC nhánh actual=null.
  if (item.material_code === MANUAL_MATERIAL_CODE) return 'MANUAL';

  if (item.actual_weight === null || item.actual_weight === undefined) return 'REJECTED';

  const planned = Number(item.planned_weight) || 0;
  const min = planned - (Number(item.tolerance_minus) || 0);
  const max = planned + (Number(item.tolerance_plus) || 0);
  const actual = Number(item.actual_weight);

  return actual >= min && actual <= max ? 'ACCEPTED' : 'REJECTED';
}

/**
 * Bản sao của `number_format($x, 2, '.', '')` bên PHP — 2 số lẻ, KHÔNG dấu phẩy hàng nghìn.
 *
 * Bỏ dấu phẩy (2026-08-05) vì tem chỉ rộng 55mm: dấu phẩy và chữ "g" ăn thêm 2 ký tự mỗi ô số,
 * mà đúng 2 ký tự đó là thứ phải cắt để nhét vừa cỡ chữ to gấp đôi. Đơn vị nay ghi một lần ở
 * tiêu đề cột ("MT(g)"/"TT(g)") thay vì lặp lại ở từng dòng.
 *
 * Không dùng `toLocaleString` vì kết quả đổi theo ngôn ngữ máy trạm; phiếu in phải giống nhau ở
 * mọi máy và giống hệt bản server dựng.
 */
function numberFormat2(value: number): string {
  const n = Number.isFinite(value) ? value : 0;
  return n.toFixed(2);
}

/**
 * Nhãn ngắn cho cột kết quả. ACCEPTED/REJECTED dài 8-9 ký tự — ở cỡ chữ đọc được thì riêng nó
 * chiếm gần một phần ba bề ngang tem 55mm, nên rút về 3-4 ký tự tiếng Việt không dấu cho đồng bộ
 * với MAU/HANG/MAY/MUC ở phần đầu.
 */
function ketQua(status: string): string {
  switch (status) {
    case 'ACCEPTED':
      return 'DAT';
    case 'REJECTED':
      return 'LECH';
    case 'MANUAL':
      return 'TAY';
    case 'PENDING':
      return 'CHO';
    default:
      return String(status ?? '').slice(0, 4);
  }
}

/** Bỏ dấu " — chính nó là ký tự đóng/mở chuỗi của lệnh TSPL, lọt vào là hỏng cả lệnh. */
function sachTspl(s: unknown): string {
  return String(s ?? '').replace(/"/g, '');
}

function haiChuSo(n: number): string {
  return String(n).padStart(2, '0');
}

/** Đúng định dạng `Carbon::now()->format('d/m/Y H:i:s')` bên PHP. */
export function nowSlipTimestamp(d = new Date()): string {
  return (
    `${haiChuSo(d.getDate())}/${haiChuSo(d.getMonth() + 1)}/${d.getFullYear()} ` +
    `${haiChuSo(d.getHours())}:${haiChuSo(d.getMinutes())}:${haiChuSo(d.getSeconds())}`
  );
}

/*
 * BỐ CỤC TEM 55x35mm — 440 x 280 dot ở 203dpi (8 dot/mm). Toạ độ dưới đây tính sát mép nên
 * ĐỔI MỘT SỐ LÀ PHẢI TÍNH LẠI CẢ CỘT/HÀNG, và phải đổi y hệt bên PHP.
 *
 * Bản 05/08/2026 sáng dùng font "1" (ô chữ 8x12 dot) cho toàn bộ bảng: in ra chữ cao chưa tới
 * 1mm, máy in nhiệt dither ra lấm tấm nên **không đọc được** (người dùng báo "chữ nhỏ quá, bị vỡ
 * chữ"). Nay bảng dùng font "2" (12x20 dot ≈ 2.5mm) — cao gấp đôi. Chỗ để nhét cỡ chữ đó lấy từ
 * ba khoản đang phí:
 *   1. Bốn dòng đầu MAU/HANG/MAY/MUC gộp còn hai (màu + mã hàng là một dòng lớn).
 *   2. Cột STATUS bỏ chữ ACCEPTED/REJECTED dài ngoằng, còn DAT/LECH.
 *   3. Số cân bỏ dấu phẩy hàng nghìn và chữ "g" lặp ở từng dòng (đơn vị ghi ở tiêu đề cột).
 *
 * Ngân sách chiều dọc: 2 dòng đầu + tiêu đề bảng hết 80 dot, 9 dòng x 21 dot = 189, tổng 272/280.
 * Ngân sách chiều ngang cột DYE CODE là 122 dot ≈ 10 ký tự font "2" — mã dài hơn sẽ lấn sang cột
 * MT (mã thực tế đang dùng dài 6-9 ký tự). Tương tự, màu + mã hàng quá dài sẽ chạy quá mép phải
 * của dòng tiêu đề; nó nằm riêng một hàng nên chỉ bị cắt cụt chứ không đè lên thứ khác.
 */
const SLIP_COL_RACK = 8;
const SLIP_COL_DYE = 48;
const SLIP_COL_MT = 186;
const SLIP_COL_TT = 280;
const SLIP_COL_KQ = 374;
/** Dòng đầu tiên của bảng và bước nhảy giữa các dòng (dot). */
const SLIP_ROW_Y0 = 84;
const SLIP_ROW_STEP = 21;

export function buildSlipTspl(header: SlipHeader, items: SlipItem[]): string {
  const color = sachTspl(header.color ?? '');
  const productCode = sachTspl(header.product_code ?? '');
  const machineCode = sachTspl(header.machine_code ?? 'N/A');
  const levelCode = sachTspl(header.level_code ?? '');
  const printedAt = sachTspl(header.printed_at ?? nowSlipTimestamp());

  // Cân tay không quét đơn nên không có màu/mã hàng — ghi thẳng "CAN TAY" vào chỗ đó thay vì để
  // dòng to nhất của tem trống trơn.
  const tieuDe = `${color} ${productCode}`.trim() || 'CAN TAY';

  let tspl =
    'SIZE 55 mm, 35 mm\r\n' +
    'GAP 2 mm, 0 mm\r\n' +
    'DIRECTION 1,0\r\n' +
    'REFERENCE 0,0\r\n' +
    'CLS\r\n' +
    'TEXT 8,2,"1",0,1,1,"DF_WEIGHING_SLIP"\r\n' +
    `TEXT 240,2,"1",0,1,1,"${printedAt}"\r\n` +
    `TEXT 8,16,"3",0,1,1,"${tieuDe}"\r\n` +
    `TEXT 8,44,"2",0,1,1,"MAY: ${machineCode}  MUC: ${levelCode}"\r\n`;

  tspl +=
    `TEXT ${SLIP_COL_RACK},68,"1",0,1,1,"RACK"\r\n` +
    `TEXT ${SLIP_COL_DYE},68,"1",0,1,1,"DYE CODE"\r\n` +
    `TEXT ${SLIP_COL_MT},68,"1",0,1,1,"MT(g)"\r\n` +
    `TEXT ${SLIP_COL_TT},68,"1",0,1,1,"TT(g)"\r\n` +
    `TEXT ${SLIP_COL_KQ},68,"1",0,1,1,"KQ"\r\n`;

  let y = SLIP_ROW_Y0;
  items.forEach((item, idx) => {
    const plannedText = numberFormat2(Number(item.planned_weight));
    const weightText =
      item.actual_weight !== null && item.actual_weight !== undefined
        ? numberFormat2(Number(item.actual_weight))
        : '---';
    const seq = item.sequence_no ?? idx + 1;
    const rackText =
      item.rack_code !== null && item.rack_code !== undefined && item.rack_code !== ''
        ? item.rack_code
        : String(seq);

    tspl +=
      `TEXT ${SLIP_COL_RACK},${y},"2",0,1,1,"${sachTspl(rackText)}"\r\n` +
      `TEXT ${SLIP_COL_DYE},${y},"2",0,1,1,"${sachTspl(item.material_code)}"\r\n` +
      `TEXT ${SLIP_COL_MT},${y},"2",0,1,1,"${plannedText}"\r\n` +
      `TEXT ${SLIP_COL_TT},${y},"2",0,1,1,"${weightText}"\r\n` +
      `TEXT ${SLIP_COL_KQ},${y},"2",0,1,1,"${ketQua(item.process_status)}"\r\n`;
    y += SLIP_ROW_STEP;
  });

  tspl += 'PRINT 1,1\r\n';

  return tspl;
}
