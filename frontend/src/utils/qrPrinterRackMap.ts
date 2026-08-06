/**
 * Bảng tra VỊ TRÍ RACK theo mã — port 1:1 từ sheet `semi` của workbook
 * "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm" (bản ở gốc repo).
 *
 * Nguồn: `xl/worksheets/sheet2.xml` (sheet tên `semi`, vùng dùng A1:E84), giải nén trực tiếp
 * từ file .xlsm:
 *   - Cột A = rack, cột B = mã HÓA CHẤT   → `scaleform.FillRack`    (txt_chem_code{i}_AfterUpdate)
 *   - Cột D = rack, cột E = mã THUỐC NHUỘM → `scaleform.FillDyeRack` (txt_dye{i}_AfterUpdate)
 *
 * VBA tra bằng `ws.Columns("B").Find(What:=..., LookAt:=xlWhole)` rồi lấy ô cùng dòng ở cột A.
 * **Không tìm thấy thì ghi "0"** (khác workbook "in tem Copower.xlsm" vốn xoá trắng ô rack) —
 * và "0" chính là giá trị làm `FillBlock` tô ô VÀNG thay vì XANH, nên đừng đổi thành rỗng.
 *
 * Bảng này là DỮ LIỆU TĨNH của workbook, chưa có bảng master tương ứng trong schema `app`
 * (không có bảng nào ánh xạ mã ↔ rack — xem `.claude/business-modules.md` M01). Khi danh mục
 * vật tư có cột vị trí rack thật thì thay chỗ này bằng API, ĐỪNG sửa tay số liệu ở đây vì nó là
 * bản sao đối soát được với workbook gốc.
 *
 * Lưu ý dữ liệu gốc: `AC20` xuất hiện ở dòng 21 và 33, `0817` ở dòng 29 và 34 — cả 2 lần đều
 * cùng rack (30 và 31) nên `Find` lấy dòng nào cũng ra kết quả như nhau.
 */

/** Cột B → cột A: mã hóa chất → rack. */
const CHEM_RACK: Record<string, string> = {
  AC91: '1', AC94: '2', PC08: '3', AC82: '4', AC101: '5',
  '0507': '6', '0550': '7', '0553': '8', '0574': '9', '0645': '10',
  '0627': '11', AC10: '15', SC28: '32', SC19: '12', SC27: '22',
  VN62: '34', SC02: '35', AC78: '36', AC77: '37', AC68: '38',
  AC20: '30', AC06: '19', AC03: '41', AC02: '42', '0557': '43',
  '0554': '25', '0526': '13', AC63: '39', '0817': '31', '0732': '24',
  AC122: '17', AC123: '18', AC117: '14',
};

/**
 * Cột E → cột D: mã thuốc nhuộm → rack. Trong sheet gốc cột D chính là số thứ tự dòng (1..84),
 * nên rack của mã ở dòng thứ n là n — giữ nguyên trật tự mảng dưới đây để đối chiếu được.
 */
const DYE_CODES: string[] = [
  'B3035A', 'B3128', 'B3024G', 'B3129', 'G4010G', 'B6306C', 'B6303C', 'V5105', 'R6203C',
  'R2143', 'R2102', 'B3110', 'B3026A', 'B3022G', 'B3006G', 'G4024A', 'B3136', 'B6304C',
  'V4017A', 'R6204C', 'R7202H', 'R2124', 'Y7102H', 'B3034A', 'B3038A', 'B3124', 'G4003A',
  'B3071A', 'B6305C', 'V4007A', 'R2104', 'R2145', 'R2112', 'Y1120', 'Y1121', 'Y1026G',
  'Y1132', 'Y1003A', 'Y6102C', 'C4013G', 'BK5002', 'BK5028', 'R2132', 'R2128', 'R2034G',
  'R2110', 'R2140', 'R2130', 'BK5019', 'B3106', 'Y1104', 'Y1130', 'Y1017G', 'Y1008A',
  'Y1023A', 'Y6103C', 'C6104', 'BK9107', 'BK8817D', 'R2129', 'R2131', 'R2064G', 'R2008G',
  'R2103', 'R2011A', 'C4028G', 'P7102', 'Y1103', 'Y1047G', 'Y1108', 'Y1009A', 'Y1031A',
  'Y1019A', 'Y6105C', 'BK5026', 'Y1005G', 'R2142', 'R2107', 'R2106', 'R7201H', 'R2136',
  'B3055G', 'P7104', 'W5011',
];

const DYE_RACK: Record<string, string> = Object.fromEntries(
  DYE_CODES.map((code, i) => [code, String(i + 1)])
);

/** `scaleform.FillRack` — không tìm thấy thì trả "0" (đúng bản gốc, KHÔNG phải chuỗi rỗng). */
export function lookupChemRack(chemCode: string): string {
  return CHEM_RACK[String(chemCode ?? '').trim().toUpperCase()] ?? '0';
}

/** `scaleform.FillDyeRack` — không tìm thấy thì trả "0". */
export function lookupDyeRack(dyeCode: string): string {
  return DYE_RACK[String(dyeCode ?? '').trim().toUpperCase()] ?? '0';
}
