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
 * Bản sao của `number_format($x, 2)` bên PHP — 2 số lẻ, dấu phẩy ngăn cách hàng nghìn.
 * Không dùng `toLocaleString` vì kết quả đổi theo ngôn ngữ máy trạm; phiếu in phải giống nhau ở
 * mọi máy và giống hệt bản server dựng.
 */
function numberFormat2(value: number): string {
  const n = Number.isFinite(value) ? value : 0;
  const fixed = Math.abs(n).toFixed(2);
  const [nguyen, le] = fixed.split('.');
  const coDauPhay = nguyen.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return (n < 0 ? '-' : '') + coDauPhay + '.' + le;
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

export function buildSlipTspl(header: SlipHeader, items: SlipItem[]): string {
  const color = header.color ?? '';
  const productCode = header.product_code ?? '';
  const machineCode = header.machine_code ?? 'N/A';
  const levelCode = header.level_code ?? '';

  let tspl =
    'SIZE 55 mm, 35 mm\r\n' +
    'GAP 2 mm, 0 mm\r\n' +
    'DIRECTION 1,0\r\n' +
    'REFERENCE 0,0\r\n' +
    'CLS\r\n' +
    'TEXT 8,6,"2",0,1,1,"DF_WEIGHING_SLIP"\r\n' +
    `TEXT 8,30,"1",0,1,1,"MAU: ${color}"\r\n` +
    `TEXT 8,44,"1",0,1,1,"HANG: ${productCode}"\r\n` +
    `TEXT 8,58,"1",0,1,1,"MAY: ${machineCode}"\r\n` +
    `TEXT 8,72,"1",0,1,1,"MUC: ${levelCode}"\r\n`;

  // Toạ độ x cố định để 5 cột thẳng hàng — giữ y hệt bản PHP.
  const colRack = 8;
  const colDye = 48;
  const colMt = 152;
  const colTt = 232;
  const colStatus = 312;

  tspl +=
    `TEXT ${colRack},88,"1",0,1,1,"RACK"\r\n` +
    `TEXT ${colDye},88,"1",0,1,1,"DYE CODE"\r\n` +
    `TEXT ${colMt},88,"1",0,1,1,"MT"\r\n` +
    `TEXT ${colTt},88,"1",0,1,1,"TT"\r\n` +
    `TEXT ${colStatus},88,"1",0,1,1,"STATUS"\r\n`;

  let y = 102;
  items.forEach((item, idx) => {
    const plannedText = numberFormat2(Number(item.planned_weight)) + 'g';
    const weightText =
      item.actual_weight !== null && item.actual_weight !== undefined
        ? numberFormat2(Number(item.actual_weight)) + 'g'
        : '---';
    const seq = item.sequence_no ?? idx + 1;
    const rackText =
      item.rack_code !== null && item.rack_code !== undefined && item.rack_code !== ''
        ? item.rack_code
        : String(seq);
    // Bỏ dấu " vì chính nó là ký tự đóng/mở chuỗi trong lệnh TSPL — lọt vào là hỏng cả lệnh.
    const dyeText = String(item.material_code ?? '').replace(/"/g, '');

    tspl +=
      `TEXT ${colRack},${y},"1",0,1,1,"${String(rackText).replace(/"/g, '')}"\r\n` +
      `TEXT ${colDye},${y},"1",0,1,1,"${dyeText}"\r\n` +
      `TEXT ${colMt},${y},"1",0,1,1,"${plannedText}"\r\n` +
      `TEXT ${colTt},${y},"1",0,1,1,"${weightText}"\r\n` +
      `TEXT ${colStatus},${y},"1",0,1,1,"${item.process_status}"\r\n`;
    y += 14;
  });

  tspl += `TEXT ${colRack},${y},"1",0,1,1,"In luc: ${header.printed_at ?? nowSlipTimestamp()}"\r\n`;
  tspl += 'PRINT 1,1\r\n';

  return tspl;
}
