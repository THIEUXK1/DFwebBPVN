/**
 * Dựng tem "DF_WEIGHING_SLIP" để in QUA TRÌNH DUYỆT (không qua TSPL/Local Agent).
 *
 * ===== NGUỒN SỰ THẬT (2026-08-05) =====
 * Bản này KHÔNG còn tự vẽ theo toạ độ dot của TSPL nữa. Toàn bộ hình học + nội dung QR
 * được port 1:1 từ chính workbook đang chạy ngoài xưởng:
 *   `3.DF028  formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm`
 *     - sheet `DF_WEIGHING_SLIP` (xl/worksheets/sheet2.xml + xl/styles.xml): độ rộng cột,
 *       chiều cao dòng, viền, cỡ chữ, in đậm, canh lề của TỪNG ô.
 *     - module VBA `Mod_printslip.PrintSlip_70x100` (giải nén trực tiếp từ vbaProject.bin):
 *       thứ tự điền ô, chuỗi payload qrDye/qrChem/qrProcess/qrExtra/qrFB, quy tắc D1/B24.
 *     - `xl/printerSettings/printerSettings2.bin` (DEVMODE): máy in `TSC TE200`, khổ giấy
 *       tuỳ biến 72.6 × 97.5 mm (paperSize=256) — Excel in `$B$1:$H$24` ép vừa 1 trang khổ này.
 *
 * Vì vậy MỌI thay đổi ở đây phải đối chiếu lại workbook trên, KHÔNG đối chiếu backend
 * `QrPayloadService::buildTsplLabel70x100` (tem TSPL của Local Agent hiện vẫn giữ bố cục
 * riêng đã căn theo ảnh tem thật — hai đường in đang cố ý khác nhau, xem session-log).
 *
 * Các lệch có chủ ý TRƯỚC ĐÂY (2026-07-22 → 07-31: gộp ô Màu+Mã hàng, kéo tem tràn hết
 * 70mm, phóng to QR, in đậm toàn bộ, viền 3 dot, dòng trống xen giữa các trường QR) ĐÃ BỊ
 * GỠ theo yêu cầu 2026-08-05 "tem in ra ở web giống hệt VBA 100%".
 */
import QRCode from 'qrcode';

/** Dữ liệu tối thiểu để dựng một tem — cố ý PHẲNG, không phụ thuộc hình dạng bản ghi dispatch/batch. */
export interface DispatchSlipData {
  color: string;
  productCode: string;
  machineCode: string;
  tankCode: string;
  levelCode: string;
  rawQrDye: string;
  rawQrChem: string;
  /** Mã lô — chỉ dùng cho tiêu đề cửa sổ in và dòng ghi chú, không ảnh hưởng bố cục tem. */
  batchId?: string;
}

/* ===================================================================================
 * 1. HÌNH HỌC SHEET `DF_WEIGHING_SLIP` (đơn vị gốc: point)
 * =================================================================================== */

/**
 * Bề rộng cột B..H quy từ thuộc tính `width` trong sheet2.xml sang pixel theo công thức
 * OOXML (`Trunc(((256*w + Trunc(128/MDW))/256) * MDW)`, MDW = 7px với Calibri 11), rồi
 * đổi px → pt (× 0.75): B=78px, C=68, D=51, E=11, F=69, G=76, H=44 (tổng 397px = 297.75pt).
 */
const COL_PT: Record<string, number> = { B: 58.5, C: 51, D: 38.25, E: 8.25, F: 51.75, G: 57, H: 33 };
const COL_ORDER = ['B', 'C', 'D', 'E', 'F', 'G', 'H'];

/** Chiều cao từng dòng 1..24 (thuộc tính `ht` trong sheet2.xml). */
const ROW_PT: number[] = [
  70.5,   // 1  nhãn + khu D1 + QR chế độ
  1.5,    // 2  dòng đệm
  26.45,  // 3  màu | máy | thùng | lv
  27.75,  // 4  mã hàng
  25.9, 25.9, 25.9, 25.9, 25.9, 25.9, 25.9, 25.9, 25.9, // 5..13 bảng cân 9 dòng
  18,     // 14 tiêu đề 2 QR
  4.5,    // 15 đệm
  14.25, 14.25, 14.25, 14.25, 14.25, 14.25, 14.25,      // 16..22 khung QR
  2.25,   // 23 đệm
  21,     // 24 chuỗi B24 (khu vực kho)
];

const CONTENT_W_PT = COL_ORDER.reduce((s, c) => s + COL_PT[c], 0); // 297.75pt = 105.03mm
const CONTENT_H_PT = ROW_PT.reduce((s, h) => s + h, 0);            // 504.80pt = 178.13mm

/** Khổ giấy trong DEVMODE của sheet (dmPaperWidth/dmPaperLength = 726/975, đơn vị 0.1mm). */
const PAPER_W_MM = 72.6;
const PAPER_H_MM = 97.5;

const PT_TO_MM = 25.4 / 72;

/**
 * `pageSetUpPr fitToPage="1"` + `fitToWidth/fitToHeight` mặc định = 1 → Excel ép cả vùng in
 * vừa ĐÚNG 1 trang, và làm tròn XUỐNG thành số phần trăm nguyên (ô Scale hiện 54%).
 * Ở đây chiều cao là cạnh bó buộc: 97.5 / 178.13 = 54.7% → 54%.
 */
const FIT_SCALE = Math.floor(Math.min(PAPER_W_MM / (CONTENT_W_PT * PT_TO_MM), PAPER_H_MM / (CONTENT_H_PT * PT_TO_MM)) * 100) / 100;

/**
 * Hệ số bù khi trình duyệt tự co trang. Excel in trực tiếp qua driver nên không bị co;
 * Chrome/Edge lại co nội dung cho vừa VÙNG IN ĐƯỢC (nhỏ hơn khổ giấy) — nếu tem in ra
 * nhỏ hơn bản Excel đúng vài phần trăm thì hạ hằng số này (0.955 là mức đã đo hồi 07-31).
 * Giữ 1 để đúng tỉ lệ 100% như VBA.
 */
const BROWSER_FIT = 1;

const S = FIT_SCALE * BROWSER_FIT;

/** pt trên sheet → mm trên giấy (đã nhân hệ số ép vừa trang). */
const mm = (pt: number) => pt * PT_TO_MM * S;

/** Lề trái do `printOptions horizontalCentered="1"` (sheet KHÔNG bật căn giữa theo chiều dọc). */
const OFFSET_X_MM = (PAPER_W_MM - CONTENT_W_PT * PT_TO_MM * S) / 2;

/**
 * Viền "thin" của Excel in ra đúng 1 dot ở máy in nhiệt 203dpi = 0.125mm. Nếu driver
 * dither làm đường kẻ bị đứt/răng cưa thì nâng lên 0.25mm (2 dot) — đổi ở đúng một chỗ này.
 */
const BORDER_MM = 0.125;

function colLeftPt(col: string): number {
  let x = 0;
  for (const c of COL_ORDER) {
    if (c === col) return x;
    x += COL_PT[c];
  }
  return x;
}

function rowTopPt(row: number): number {
  let y = 0;
  for (let r = 1; r < row; r++) y += ROW_PT[r - 1];
  return y;
}

interface Rect { left: number; top: number; width: number; height: number }

/** Hình chữ nhật (mm) của vùng ô từ (col1,row1) tới (col2,row2) — bao gồm cả 2 đầu. */
function rect(col1: string, row1: number, col2: string, row2: number): Rect {
  const x1 = colLeftPt(col1);
  const x2 = colLeftPt(col2) + COL_PT[col2];
  const y1 = rowTopPt(row1);
  const y2 = rowTopPt(row2) + ROW_PT[row2 - 1];
  return { left: mm(x1), top: mm(y1), width: mm(x2 - x1), height: mm(y2 - y1) };
}

type Align = 'left' | 'center' | 'right';
type VAlign = 'bottom' | 'center';
type Borders = { t?: boolean; r?: boolean; b?: boolean; l?: boolean };

interface CellStyle {
  fontPt?: number;
  bold?: boolean;
  align?: Align;
  valign?: VAlign;
  /** B24 để chữ tràn tự do sang phải như Excel (các ô C24..H24 rỗng). */
  overflow?: boolean;
}

const esc = (v: string) =>
  String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/**
 * Đường kẻ được vẽ RIÊNG, nằm ĐÈ lên gridline chứ không phải border của từng ô. Excel
 * chia chung 1 nét cho 2 ô liền kề; nếu để mỗi ô tự vẽ border thì 2 nét nằm sát nhau
 * cộng lại thành đường dày gấp đôi (đúng lỗi "viền to đè chữ" từng gặp 2026-07-30).
 * Set khử trùng để 2 ô cùng khai báo một cạnh chỉ sinh ra 1 phần tử.
 */
class LineSet {
  private keys = new Set<string>();
  private html = '';

  private add(leftMm: number, topMm: number, wMm: number, hMm: number) {
    const key = `${leftMm.toFixed(3)}|${topMm.toFixed(3)}|${wMm.toFixed(3)}|${hMm.toFixed(3)}`;
    if (this.keys.has(key)) return;
    this.keys.add(key);
    this.html += `<div style="position:absolute;left:${leftMm.toFixed(3)}mm;top:${topMm.toFixed(3)}mm;width:${wMm.toFixed(3)}mm;height:${hMm.toFixed(3)}mm;background:#000"></div>`;
  }

  /** Nét ngang trên gridline y, kéo từ x1 tới x2 (mm). */
  h(x1: number, x2: number, y: number) {
    this.add(x1, Math.max(0, y - BORDER_MM / 2), x2 - x1, BORDER_MM);
  }

  /** Nét dọc trên gridline x, kéo từ y1 tới y2 (mm). */
  v(y1: number, y2: number, x: number) {
    this.add(Math.max(0, x - BORDER_MM / 2), y1, BORDER_MM, y2 - y1);
  }

  /** Viền quanh một vùng ô, chọn từng cạnh theo đúng style của sheet. */
  box(r: Rect, b: Borders) {
    if (b.t) this.h(r.left, r.left + r.width, r.top);
    if (b.b) this.h(r.left, r.left + r.width, r.top + r.height);
    if (b.l) this.v(r.top, r.top + r.height, r.left);
    if (b.r) this.v(r.top, r.top + r.height, r.left + r.width);
  }

  toString() { return this.html; }
}

/** Chữ của một ô (hoặc vùng gộp) — khung do LineSet vẽ riêng. */
function cell(r: Rect, text: string, st: CellStyle = {}): string {
  if (text === '') return '';
  const style = [
    'position:absolute',
    `left:${r.left.toFixed(3)}mm`,
    `top:${r.top.toFixed(3)}mm`,
    `width:${r.width.toFixed(3)}mm`,
    `height:${r.height.toFixed(3)}mm`,
    st.overflow ? 'overflow:visible' : 'overflow:hidden',
    'display:flex',
    `align-items:${st.valign === 'center' ? 'center' : 'flex-end'}`,
    `justify-content:${st.align === 'center' ? 'center' : st.align === 'right' ? 'flex-end' : 'flex-start'}`,
    st.fontPt ? `font-size:${(st.fontPt * PT_TO_MM * S).toFixed(3)}mm` : '',
    st.bold ? 'font-weight:700' : 'font-weight:400',
    // Excel chừa ~2px mỗi bên (ở 100%) trước khi vẽ chữ trong ô.
    `padding:0 ${mm(1.5).toFixed(3)}mm`,
    'white-space:nowrap',
    'line-height:1.05',
  ].filter(Boolean).join(';');
  return `<div style="${style}">${esc(text)}</div>`;
}

/* ===================================================================================
 * 2. PORT `Mod_printslip.PrintSlip_70x100` (DF028) — TÁCH DÒNG, ROUTING, PAYLOAD QR
 * =================================================================================== */

/** VBA `Trim$` chỉ cắt DẤU CÁCH, không cắt CR/LF — dùng đúng vậy để payload khớp từng ký tự. */
const trimSpaces = (s: string) => String(s ?? '').replace(/^ +| +$/g, '');

const CRLF = '\r\n';

interface SlipLine { rack: string; code: string; weight: string }

/**
 * `arr = Split(raw, "-")` rồi `For i = 0 To UBound(arr) Step 3` với điều kiện thoát
 * `idx >= 9 Or i + 2 > UBound(arr)`: CHỈ nhận bộ ba đầy đủ, tối đa 9 dòng, KHÔNG bỏ phần
 * tử rỗng (khác `utils/rackParser.ts` vốn lọc rỗng — chỗ này phải bám sát VBA để không
 * lệch cột khi chuỗi thô có dấu "-" thừa).
 */
function splitVbaTriples(raw: string): SlipLine[] {
  const arr = String(raw ?? '').split('-');
  const out: SlipLine[] = [];
  for (let i = 0; i + 2 <= arr.length - 1 && out.length < 9; i += 3) {
    out.push({ rack: arr[i], code: arr[i + 1], weight: arr[i + 2].split(',').join('.') });
  }
  return out;
}

/** Port `"VD" & Format$(Val(Mid$(v,3)), "000")` — chỉ đổi khi chuỗi bắt đầu bằng "VD". */
function normalizeVdCode(code: string): string {
  const c = trimSpaces(String(code ?? '')).toUpperCase();
  if (c.slice(0, 2) === 'VD') {
    return 'VD' + String(Math.trunc(vbaVal(c.slice(2)))).padStart(3, '0');
  }
  return c;
}

/** Port hàm `Val()` của VBA: đọc số ở đầu chuỗi, bỏ qua phần đuôi không phải số. */
function vbaVal(s: string): number {
  const m = /^\s*[+-]?(\d+\.?\d*|\.\d+)/.exec(String(s ?? ''));
  return m ? parseFloat(m[0]) : 0;
}

/** Port `Format(n, "0.###")` — tối đa 3 số lẻ, cắt số 0 thừa. */
const formatVba0d3 = (n: number) => String(parseFloat(n.toFixed(3)));

/** Port `Format(Now, ...)` — chỉ 2 mẫu Mod_printslip dùng ("hh" là 24 giờ). */
function nowStamp(pattern: 'yyyymmddhhmm' | 'hhmm'): string {
  const n = new Date();
  const p2 = (v: number) => String(v).padStart(2, '0');
  const hhmm = p2(n.getHours()) + p2(n.getMinutes());
  if (pattern === 'hhmm') return hhmm;
  return `${n.getFullYear()}${p2(n.getMonth() + 1)}${p2(n.getDate())}${hhmm}`;
}

/** `Int(9 * Rnd) + 1` → 1..9. */
const rnd1to9 = () => 1 + Math.floor(Math.random() * 9);

/**
 * Port nguyên khối "B24 NORMALIZE" + "QR MODE SELECT" + gán D1 của Mod_printslip, GIỮ
 * NGUYÊN phép so sánh CHUỖI của VBA (`f3Val >= "VD06" And f3Val <= "VD13"`). Mã máy trong
 * DB đã đưa về đúng quy ước 2 chữ số VD01..VD18 (commit fcbbf9b) nên so chuỗi cho kết quả
 * y hệt VBA; nếu sau này mã máy đổi sang 3 chữ số thì chỗ này SẼ lệch — phải sửa cùng lúc
 * với danh mục máy.
 */
export function calculateRoutingPreview(machineCode: string, tankCode: string, levelCode: string) {
  const f3 = trimSpaces(String(machineCode ?? '')).toUpperCase();
  const g3 = trimSpaces(String(tankCode ?? '')).toUpperCase();
  const tank1A2B = g3 === '1A' || g3 === '2B';
  const tank3C4D = g3 === '3C' || g3 === '4D';

  let b24 = '';
  if (f3 >= 'VD06' && f3 <= 'VD13' && tank1A2B) {
    b24 = 'THUNG SAT CAO, MAY E13, MAY A11';
  } else if ((f3 === 'VD17' || f3 === 'VD18') && tank1A2B) {
    b24 = 'THUNG SAT CAO, MAY E12, MAY A11';
  } else if ((f3 === 'VD17' || f3 === 'VD18') && tank3C4D) {
    b24 = vbaVal(levelCode) === 50 ? 'PHA TAY, HOA CHAT DLG' : 'THUNG SAT CAO, MAY E12, MAY DLG';
  } else if (((f3 >= 'VD01' && f3 <= 'VD05') || (f3 >= 'VD14' && f3 <= 'VD16')) && tank1A2B) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY A11';
  } else if (f3 >= 'VD01' && f3 <= 'VD16' && tank3C4D) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY DLG';
  }

  let mode: 'PROCESS' | 'EXTRA' | 'FB';
  if (b24.indexOf('MAY JIT') >= 0) {
    mode = 'PROCESS';
  } else if (b24.indexOf('THUNG SAT CAO') >= 0) {
    mode = 'EXTRA';
  } else {
    mode = 'FB';
    b24 = 'PHU BAN-LAY LIEU COPOWER';
  }

  let d1Zone = '';
  if (f3 >= 'VD06' && f3 <= 'VD13' && tank1A2B) {
    d1Zone = 'E13';
  } else if ((f3 === 'VD17' || f3 === 'VD18') && (tank1A2B || tank3C4D)) {
    d1Zone = 'E12';
  } else if (f3 >= 'VD01' && f3 <= 'VD05' && tank1A2B) {
    d1Zone = 'JIT3';
  } else if (f3 >= 'VD01' && f3 <= 'VD09' && tank3C4D) {
    d1Zone = 'JIT2';
  } else if (f3 >= 'VD14' && f3 <= 'VD16' && tank1A2B) {
    d1Zone = 'JIT4';
  } else if (f3 >= 'VD10' && f3 <= 'VD16' && tank3C4D) {
    d1Zone = 'JIT1';
  }

  return { d1Zone, b24Route: b24, mode };
}

/**
 * Port khối "PROCESS CHECK": quét mã thuốc nhuộm ở CỘT C, và quét CỘT F của bảng hóa chất
 * (cột F là rack/vị trí, KHÔNG phải mã hóa chất — bám đúng `vF = Trim$(ws.Cells(r,"F"))`).
 */
function computeDyesProcess(dyeLines: SlipLine[], chemLines: SlipLine[]): string {
  let dyesProcess = 'Nylon Dyes';
  let isDisperse = false;

  for (const l of dyeLines) {
    const vC = trimSpaces(l.code).toUpperCase();
    if (vC === '') continue;
    if (vC.slice(-1) === 'C') dyesProcess = 'Cation Dyes';
    if (vC.slice(-1) === 'D' || vC.slice(0, 3) === 'Y13' || vC.slice(0, 3) === 'R23' || vC.slice(0, 3) === 'B33') {
      isDisperse = true;
    }
  }

  let hasChemKey = false;
  for (const l of chemLines) {
    const vF = trimSpaces(l.rack);
    if (vF.indexOf('0574') >= 0 || vF.indexOf('0507') >= 0) {
      hasChemKey = true;
      break;
    }
  }

  if (dyesProcess !== 'Cation Dyes' && isDisperse && hasChemKey) dyesProcess = 'Disperse Dyes';
  return dyesProcess;
}

/* ===================================================================================
 * 3. DỰNG TRANG IN
 * =================================================================================== */

/**
 * Dựng toàn bộ HTML một trang in chứa đúng 1 tem (kèm sẵn script tự gọi window.print()).
 * Khu vực D1 và chuỗi B24 tự tính lại tại đây bằng calculateRoutingPreview() vì dispatch
 * chưa confirm nên chưa có RoutingDecision lưu thật trong DB.
 */
export async function buildDispatchSlipHtml(data: DispatchSlipData): Promise<string> {
  const c = data.color ?? '';
  const cd = data.productCode ?? '';
  const mc = data.machineCode ?? '';
  const tk = data.tankCode ?? '';
  const lv = data.levelCode ?? '';

  const dyeLines = splitVbaTriples(data.rawQrDye);
  const chemLines = splitVbaTriples(data.rawQrChem);

  const routing = calculateRoutingPreview(mc, tk, lv);
  const g3Val = trimSpaces(tk).toUpperCase();

  // ---- qrDye ----
  const qrDye = `#${c}-${cd}-${mc}-${lv}-${data.rawQrDye ?? ''}`;

  // ---- qrChem: 1 CRLF giữa mỗi trường, phần lặp lấy CỘT F (rack) + CỘT H (khối lượng),
  // dừng ở dòng đầu tiên có khối lượng rỗng (`Do While ws.Cells(r,"H").Value <> ""`) ----
  let qrChem = [normalizeVdCode(mc), tk.slice(0, 1), `#${c}-${cd}`, String(rnd1to9()), lv].join(CRLF);
  for (const l of chemLines) {
    if (l.weight === '') break;
    qrChem += CRLF + l.rack + CRLF + l.weight;
  }
  qrChem += '#';

  // ---- QR chế độ ở G1:H1 ----
  let qrMode = '';
  if (routing.mode === 'PROCESS') {
    const newLevel = g3Val === '1A' ? '450' : g3Val === '2B' ? '250' : lv;
    const dyesProcess = computeDyesProcess(dyeLines, chemLines);
    qrMode = `${c}-${cd} ${nowStamp('yyyymmddhhmm')}${CRLF}${mc}-${tk}-${newLevel}${CRLF}${dyesProcess}`;
  } else if (routing.mode === 'EXTRA') {
    const totalD = dyeLines.reduce((sum, l) => sum + vbaVal(l.weight), 0);
    qrMode = [
      normalizeVdCode(mc),
      tk.slice(0, 1),
      `${c} ${cd}`,
      String(rnd1to9()),
      lv,
      '1',
      formatVba0d3(totalD),
    ].join(CRLF);
  } else {
    let fb = `${c}-${cd} ${nowStamp('hhmm')}`;
    for (const l of dyeLines) {
      if (trimSpaces(l.code) !== '') fb += CRLF + l.code + CRLF + l.weight;
    }
    for (const l of chemLines) {
      if (trimSpaces(l.code) !== '') fb += CRLF + l.code + CRLF + l.weight;
    }
    qrMode = fb;
  }

  // VBA sinh QR qua api.qrserver.com (mặc định ecc=L, không quiet zone). CLAUDE.md cấm gọi
  // dịch vụ ngoài nên vẫn sinh nội bộ, nhưng đặt ĐÚNG mức sửa lỗi L để số module — tức
  // hình dạng QR — trùng với tem VBA. `CleanString` của VBA = Trim trước khi encode.
  // width 960: chỉ là độ phân giải NGUỒN cho nét khi in (ảnh luôn bị THU NHỎ lúc in),
  // không ảnh hưởng nội dung.
  const qrOpts = { errorCorrectionLevel: 'L' as const, margin: 0, width: 960 };
  let dyeQrDataUrl = '';
  let chemQrDataUrl = '';
  let modeQrDataUrl = '';
  try {
    dyeQrDataUrl = await QRCode.toDataURL(trimSpaces(qrDye), qrOpts);
    chemQrDataUrl = await QRCode.toDataURL(trimSpaces(qrChem), qrOpts);
    modeQrDataUrl = await QRCode.toDataURL(trimSpaces(qrMode), qrOpts);
  } catch (err) {
    console.error('Failed to render QR for browser print:', err);
  }

  /* ----- Khung kẻ + chữ ----- */
  const lines = new LineSet();
  let html = '';
  const boxAll: Borders = { t: true, r: true, b: true, l: true };

  // Dòng 1: B1:C1 nhãn (12pt đậm), D1:F1 khu vực kho (36pt đậm), G1:H1 khung chứa QR chế độ.
  lines.box(rect('B', 1, 'C', 1), boxAll);
  lines.box(rect('D', 1, 'F', 1), boxAll);
  lines.box(rect('G', 1, 'H', 1), boxAll);
  html += cell(rect('B', 1, 'C', 1), 'DF_WEIGHING_SLIP', { fontPt: 12, bold: true, align: 'center', valign: 'center' });
  html += cell(rect('D', 1, 'F', 1), routing.d1Zone, { fontPt: 36, bold: true, align: 'center', valign: 'center' });

  // Dòng 2 (1.5pt): các vệt viền còn sót của bản gốc — B2/C2 kẻ 2 cạnh dọc, F2 thêm cạnh
  // dưới, G2/H2 kẻ đủ 4 cạnh.
  lines.box(rect('B', 2, 'B', 2), { l: true, r: true });
  lines.box(rect('C', 2, 'C', 2), { l: true, r: true });
  lines.box(rect('F', 2, 'F', 2), { l: true, r: true, b: true });
  lines.box(rect('G', 2, 'G', 2), boxAll);
  lines.box(rect('H', 2, 'H', 2), boxAll);

  // Dòng 3-4: màu / mã hàng (20pt đậm, CĂN TRÁI, 2 khung riêng có đường kẻ giữa),
  // máy / thùng (20pt đậm, căn giữa), lv (14pt thường, căn giữa).
  lines.box(rect('B', 3, 'D', 3), boxAll);
  lines.box(rect('B', 4, 'D', 4), boxAll);
  lines.box(rect('F', 3, 'F', 3), boxAll);
  lines.box(rect('G', 3, 'G', 3), boxAll);
  lines.box(rect('H', 3, 'H', 3), boxAll);
  // F4/G4/H4 chỉ có viền dưới (style s=12).
  for (const col of ['F', 'G', 'H']) lines.box(rect(col, 4, col, 4), { b: true });
  html += cell(rect('B', 3, 'D', 3), c, { fontPt: 20, bold: true, align: 'left' });
  html += cell(rect('B', 4, 'D', 4), cd, { fontPt: 20, bold: true, align: 'left' });
  html += cell(rect('F', 3, 'F', 3), mc, { fontPt: 20, bold: true, align: 'center' });
  html += cell(rect('G', 3, 'G', 3), tk, { fontPt: 20, bold: true, align: 'center' });
  html += cell(rect('H', 3, 'H', 3), lv, { fontPt: 14, align: 'center' });

  // Dòng 5-13: bảng cân — mọi ô B/C/D/F/G/H đều kẻ đủ 4 cạnh, cột đệm E kẻ 2 cạnh dọc.
  // Canh lề theo ĐÚNG style từng dòng của sheet:
  //  - D (khối lượng thuốc nhuộm): dòng 5-8 căn phải, dòng 9-13 để General → chữ căn trái.
  //  - F (rack hóa chất): dòng 5-9 căn giữa, dòng 10-13 căn trái.
  for (let i = 0; i < 9; i++) {
    const row = 5 + i;
    const d = dyeLines[i];
    const ch = chemLines[i];
    for (const col of ['B', 'C', 'D', 'F', 'G', 'H']) lines.box(rect(col, row, col, row), boxAll);
    lines.box(rect('E', row, 'E', row), { l: true, r: true });
    html += cell(rect('B', row, 'B', row), d?.rack ?? '', { fontPt: 12, align: 'left' });
    html += cell(rect('C', row, 'C', row), d?.code ?? '', { fontPt: 12, align: 'left' });
    html += cell(rect('D', row, 'D', row), d?.weight ?? '', { fontPt: 12, align: row <= 8 ? 'right' : 'left' });
    html += cell(rect('F', row, 'F', row), ch?.rack ?? '', { fontPt: 12, align: row <= 9 ? 'center' : 'left' });
    html += cell(rect('G', row, 'G', row), ch?.code ?? '', { fontPt: 12, align: 'left' });
    html += cell(rect('H', row, 'H', row), ch?.weight ?? '', { fontPt: 12, align: 'right' });
  }

  // Dòng 14: 2 tiêu đề QR (12pt đậm, căn giữa, CHỈ có viền trên).
  lines.box(rect('B', 14, 'D', 14), { t: true });
  lines.box(rect('F', 14, 'H', 14), { t: true });
  html += cell(rect('B', 14, 'D', 14), 'QR CAN THUOC NHUOM', { fontPt: 12, bold: true, align: 'center' });
  html += cell(rect('F', 14, 'H', 14), 'QR CAN CHAT TRO', { fontPt: 12, bold: true, align: 'center' });

  // Dòng 16-22: 2 khung QR.
  const dyeBox = rect('B', 16, 'D', 22);
  const chemBox = rect('F', 16, 'H', 22);
  lines.box(dyeBox, boxAll);
  lines.box(chemBox, boxAll);

  // Dòng 24: chuỗi khu vực kho (16pt thường, không viền, cho tràn sang phải).
  html += cell(rect('B', 24, 'B', 24), routing.b24Route, { fontPt: 16, align: 'left', overflow: true });

  /* ----- 3 ảnh QR: đúng công thức tính cạnh + căn giữa của VBA ----- */
  // qr_dye / qr_chem: side = Min(rộng, cao của vùng B16:D22) * 0.8
  const qrSide = Math.min(dyeBox.width, dyeBox.height) * 0.8;
  const img = (src: string, box: Rect, side: number, alt: string) => {
    if (!src) return '';
    const left = box.left + (box.width - side) / 2;
    const top = box.top + (box.height - side) / 2;
    return `<img src="${src}" alt="${alt}" style="position:absolute;left:${left.toFixed(3)}mm;top:${top.toFixed(3)}mm;width:${side.toFixed(3)}mm;height:${side.toFixed(3)}mm" />`;
  };
  html += img(dyeQrDataUrl, dyeBox, qrSide, 'QR DYE');
  html += img(chemQrDataUrl, chemBox, qrSide, 'QR CHEM');
  // qr_process / qr_extra / qr_fb: side = chiều cao G1:H1 * 0.95
  const modeBox = rect('G', 1, 'H', 1);
  html += img(modeQrDataUrl, modeBox, modeBox.height * 0.95, 'QR MODE');

  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Tem ${esc(data.batchId || '')}</title>
<style>
  * { box-sizing: border-box; }
  /* Máy in tem là máy in NHIỆT: chỉ có đen/trắng, mọi sắc xám đều bị dither thành lưới
     chấm thưa -> nhìn mờ. Chặn trình duyệt "tối ưu" màu khi in. */
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; padding: 6mm; color: #000; display: flex; flex-direction: column; align-items: center;
         font-family: Calibri, Carlito, 'Segoe UI', sans-serif; }
  /* .slip = ĐÚNG khổ giấy trong DEVMODE của sheet; .sheet = vùng in $B$1:$H$24 sau khi ép
     vừa 1 trang, căn giữa theo chiều ngang (printOptions horizontalCentered="1") và dính
     mép trên (sheet KHÔNG bật căn giữa dọc). */
  .slip { position: relative; width: ${PAPER_W_MM}mm; height: ${PAPER_H_MM}mm; zoom: 2.6; }
  .sheet { position: absolute; left: ${OFFSET_X_MM.toFixed(3)}mm; top: 0;
           width: ${mm(CONTENT_W_PT).toFixed(3)}mm; height: ${mm(CONTENT_H_PT).toFixed(3)}mm; }
  .footnote { margin-top: 3mm; font-size: 2.3mm; color: #666; max-width: 190mm; }
  @page {
    size: ${PAPER_W_MM}mm ${PAPER_H_MM}mm;
    margin: 0;
  }
  @media print {
    body { padding: 0; display: block; }
    /* KHÔNG dùng transform: scale() ở đây: scale nhân vào cả ĐỘ DÀY nét, viền không còn
       tròn dot và in ra bị đứt quãng (lỗi thật 2026-07-31). Muốn bù co trang thì sửa
       hằng số BROWSER_FIT trong dispatchSlipPrint.ts, vì nó nhân vào TOẠ ĐỘ chứ không
       nhân vào độ dày viền. */
    .slip { zoom: 1; }
    .footnote { display: none; }
  }
</style>
</head>
<body>
  <div class="slip"><div class="sheet">${lines.toString()}${html}</div></div>

  <p class="footnote">
    Lô: ${esc(data.batchId || '')} — In qua trình duyệt (không qua TSPL/Local Agent). Bố cục, cỡ chữ và nội dung QR port 1:1 từ sheet DF_WEIGHING_SLIP + Mod_printslip của "3.DF028 formulas - PRINTER LANDSCAPE - jit qr sending - 15l special.xlsm"; khổ giấy ${PAPER_W_MM}×${PAPER_H_MM}mm, ép vừa 1 trang ở ${Math.round(FIT_SCALE * 100)}%. Khu vực kho/QR chế độ tự tính lại tại đây, có thể lệch nếu cấu hình routing thay đổi giữa lúc in và lúc xác nhận chính thức.
  </p>

  <script>
    window.onload = function () {
      // window.print() CHẶN tới khi hộp thoại in đóng lại trên Chrome/Edge, nên gọi
      // window.close() ngay dòng kế tiếp là chạy SAU khi người dùng bấm In/Hủy.
      window.print();
      window.close();
    };
  <\/script>
</body>
</html>`;
}

/**
 * Ghi tem vào một cửa sổ ĐÃ MỞ SẴN. Cửa sổ phải được `window.open()` ĐỒNG BỘ ngay trong trình
 * xử lý sự kiện click — Chrome/Edge chặn popup nếu gọi sau một tác vụ bất đồng bộ (mất "user
 * gesture"), khiến bấm nút in không hiện hộp thoại nào cả.
 */
export async function writeDispatchSlipToWindow(win: Window, data: DispatchSlipData): Promise<void> {
  const html = await buildDispatchSlipHtml(data);
  win.document.write(html);
  win.document.close();
}
