/**
 * Đọc chuỗi QR mẻ nhuộm NGAY TẠI TRÌNH DUYỆT.
 *
 * Đây là bản port của `QrPayloadService::parseDyeScan` (PHP), bản thân nó port từ VBA
 * `txt_color_AfterUpdate`. CỐ Ý nhân bản logic thay vì gọi server: toàn bộ mục đích là để
 * Trạm cân vẽ được bảng 9 dòng mà KHÔNG phải đi mạng — chuỗi QR đã chứa sẵn rack/dye/weight.
 *
 * Server vẫn parse lại độc lập và server mới là nguồn sự thật của những gì ghi xuống DB; bản
 * này chỉ để hiện cho thợ nhìn ngay. **Đổi định dạng QR thì phải sửa CẢ HAI chỗ** — có test
 * đối chiếu hai bản trong `qrDyeParser.spec` (chạy bằng scripts/check-qr-parser.mjs).
 *
 * Định dạng: `#COLOR-CODE-MACHINE-LEVEL` rồi lặp lại bộ ba `RACK-DYE-WEIGHT` (tối đa 9 bộ).
 */
/**
 * Số dòng tối đa của một mẻ — vừa là giới hạn parse của chuỗi QR, vừa là số dòng lưới trên màn
 * hình cân (txt_RACK1..9 của scaleform.frm). Hai thứ này là MỘT trong form VBA gốc, nên để chung
 * một hằng: lưới luôn vẽ đủ 9 dòng và NEXT luôn chạy được hết 9 dòng kể cả khi QR ít dòng hơn.
 */
export const MAX_RACK_LINES = 9;

export interface DyeRackLine {
  rack: string;
  dye: string;
  weight: string;
}

export interface ParsedDyeQr {
  color: string;
  code: string;
  machine: string;
  level: string;
  rack_lines: DyeRackLine[];
}

export function parseDyeQr(raw: string): ParsedDyeQr {
  // CleanLeadingGarbage (VBA): QR sinh ra luôn có "#" đứng đầu. Dấu phẩy đổi thành chấm vì
  // cân/máy in có thể xuất số theo locale khác.
  let s = raw.trim().replace(/^#+/, '').replace(/,/g, '.');

  // Bỏ mọi cụm "-dye-" (không phân biệt hoa thường), đúng vòng while của bản PHP.
  let pos = s.toLowerCase().indexOf('-dye-');
  while (pos !== -1) {
    s = s.slice(0, pos) + '-' + s.slice(pos + 5);
    pos = s.toLowerCase().indexOf('-dye-');
  }

  // Phần hoá chất nằm sau "chem" không thuộc mẻ nhuộm.
  const chemPos = s.toLowerCase().indexOf('chem');
  if (chemPos !== -1) s = s.slice(0, chemPos);

  if (s === '') {
    return { color: '', code: '', machine: '', level: '', rack_lines: [] };
  }

  const parts = s
    .split('-')
    .map((p) => p.trim())
    .filter((p) => p !== '');

  const rackLines: DyeRackLine[] = [];
  for (let i = 4; i + 2 < parts.length + 1 && rackLines.length < MAX_RACK_LINES; i += 3) {
    if (parts[i] === undefined) break;
    rackLines.push({
      rack: parts[i],
      dye: parts[i + 1] ?? '',
      weight: parts[i + 2] ?? '',
    });
  }

  return {
    color: parts[0] ?? '',
    code: parts[1] ?? '',
    machine: parts[2] ?? '',
    level: parts[3] ?? '',
    rack_lines: rackLines,
  };
}
