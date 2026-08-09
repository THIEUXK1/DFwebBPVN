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

/**
 * `CleanLeadingGarbage` — port ĐÚNG NGUYÊN VĂN `Mod_delta_raw.bas` dòng 243-261 của workbook
 * cân to `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm`:
 *
 *     For i = 1 To Len(s)
 *         If Mid$(s, i, 1) Like "[A-Za-z0-9]" Then CleanLeadingGarbage = Mid$(s, i): Exit Function
 *
 * Tức là bỏ MỌI ký tự đầu chuỗi cho tới chữ/số đầu tiên — không riêng gì "#".
 *
 * Bản cũ ở đây chỉ `replace(/^#+/, '')`. Máy quét kiểu bàn phím rất hay gõ ra một ký tự lạ trước
 * nội dung (bố cục bàn phím không phải US thì chính "#" là ký tự bị gõ nhầm nhiều nhất), và VBA
 * chạy được với những chuỗi đó suốt bao năm chính là NHỜ hàm này. Đã đo: 3/13 trường hợp thử
 * VBA nhận mà web từ chối đều rơi vào đây (07/08/2026).
 */
export function cleanLeadingGarbage(raw: string): string {
  const s = raw.trim();
  for (let i = 0; i < s.length; i++) {
    if (/[A-Za-z0-9]/.test(s[i])) return s.slice(i);
  }
  return s;
}

/**
 * Đọc chuỗi quét được NẾU nó là payload mẻ nhuộm; trả `null` nếu không phải.
 *
 * Có hàm này để cả ba màn cân định tuyến GIỐNG HỆT nhau. Trước 07/08/2026 mỗi màn tự viết
 * `token.startsWith('#')` — và đó chính là chỗ hỏng: VBA KHÔNG hề đòi "#", nó chỉ gọi
 * `CleanLeadingGarbage` rồi tách chuỗi. Con tem nào tới tay web mà thiếu "#" ở đầu (máy quét gõ
 * nhầm ký tự, quét được thêm một ký tự lạ đứng trước) đều bị đẩy nhầm sang endpoint dành cho
 * token "DF:ORDER:<uuid>" rồi báo sai định dạng, trong khi form VBA vẫn nạp đơn bình thường.
 *
 * Cách nhận biết nay dựa vào CẤU TRÚC, đúng như VBA: cứ có đủ 4 ô đầu (color-code-machine-level)
 * là payload mẻ nhuộm. Riêng token nội bộ "DF:..." được tách ra trước — nó phải hỏi server mới
 * biết cân gì.
 *
 * KHÔNG đòi phải có dòng rack nào: VBA điền được bao nhiêu ô thì điền, không có dòng nào thì để
 * lưới trống và thợ vẫn cân tay được trên đúng màn đó.
 */
export function docQrMeNhuom(raw: string): ParsedDyeQr | null {
  if (/^DF:/i.test(raw.trim())) return null;
  const p = parseDyeQr(raw);
  if (p.color === '' || p.code === '' || p.machine === '' || p.level === '') return null;
  return p;
}

export function parseDyeQr(raw: string): ParsedDyeQr {
  // Dấu phẩy đổi thành chấm vì cân/máy in có thể xuất số theo locale khác.
  let s = cleanLeadingGarbage(raw).replace(/,/g, '.');

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

  // Vòng lặp bám ĐÚNG `For i = 1 To 9` của VBA: gán rack rồi mới kiểm còn phần tử không, gán dye
  // rồi lại kiểm, mới tới weight. Nhờ vậy bộ ba CUỐI bị thiếu vẫn tạo ra một dòng (rack có, dye
  // và weight rỗng) — bản cũ ở đây đòi đủ cả ba nên chuỗi kết thúc giữa chừng bị mất hẳn dòng
  // cuối, mà VBA thì vẫn điền được txt_rack cho dòng đó.
  const rackLines: DyeRackLine[] = [];
  let idx = 4;
  for (let i = 0; i < MAX_RACK_LINES; i++) {
    if (idx > parts.length - 1) break;
    const line: DyeRackLine = { rack: parts[idx++], dye: '', weight: '' };
    if (idx <= parts.length - 1) line.dye = parts[idx++];
    if (idx <= parts.length - 1) line.weight = parts[idx++];
    rackLines.push(line);
  }

  return {
    color: parts[0] ?? '',
    code: parts[1] ?? '',
    machine: parts[2] ?? '',
    level: parts[3] ?? '',
    rack_lines: rackLines,
  };
}
