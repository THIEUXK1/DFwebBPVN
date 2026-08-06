/**
 * Dựng phiếu in của nút `print` (`scaleform.btnPrint_Click`) trong workbook
 * "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm" — in QUA TRÌNH DUYỆT.
 *
 * ===== NGUỒN SỰ THẬT =====
 * Bố cục + chuỗi QR port 1:1 từ chính workbook ở gốc repo (không phải bản trong `Danny/`, bản
 * đó cũ hơn và thiếu nút CLEAR WEIGHT):
 *   - `scaleform.btnPrint_Click`   → bảng A1:C19 + ảnh QR ở cột E
 *   - `mdQRCodegen.GenerateQRCode` → ảnh QR (VBA gọi api.qrserver.com; ở đây sinh nội bộ)
 *   - `xl/printerSettings/printerSettings1.bin` (DEVMODE) → máy in `TSC TE200 on vn-ld047`,
 *     khổ giấy tuỳ biến 63.5 × 38.5 mm (paperSize=256, dmPaperWidth=635, dmPaperLength=385).
 *
 * VBA in bằng `ws.PrintOut ActivePrinter:="vn-ld047\TSC TE200"` trên một sheet TẠM vừa tạo bằng
 * `Sheets.Add`, nên khổ giấy của phiếu KHÔNG được lưu ở đâu trong file — sheet mới nhận khổ mặc
 * định của driver máy in lúc chạy. 63.5 × 38.5 mm dưới đây là giá trị DUY NHẤT ghi trong
 * workbook; màn hình cho đổi lại khổ giấy vì không có cách nào suy ra khổ thật của sheet tạm.
 *
 * QR: CLAUDE.md mục 5 cấm gọi api.qrserver.com nên sinh nội bộ bằng `qrcode` — cùng cách làm với
 * `dispatchSlipPrint.ts`. VBA gọi URL **không kèm tham số nào ngoài `size`**:
 *     https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<urlencode UTF-8>
 * nên qrserver dùng mặc định của nó: mức sửa lỗi **L**, vùng lặng **0** — đó là lý do ở dưới đặt
 * `errorCorrectionLevel: 'L'` + `margin: 0`, hai tham số này khớp chắc chắn.
 *
 * ĐIỀU KHÔNG KHỚP (đã đo, đừng sửa comment này thành lời hứa suông): thư viện `qrcode` tự tối ưu
 * chế độ mã hoá, tách chuỗi thành đoạn alphanumeric + byte, nên ra bảng mã NHỎ HƠN một mã ép byte
 * thuần (payload 74 ký tự: version 3 = 29x29 ô thay vì version 4 = 33x33). Không kiểm chứng được
 * qrserver chọn chế độ nào vì không được phép gọi API đó. Hệ quả: ảnh QR có thể KHÁC ảnh của bản
 * VBA về số ô, nhưng **nội dung giải mã ra y hệt** — mọi máy quét đều đọc được cả hai chế độ (ISO
 * 18004), và ít ô hơn nghĩa là mỗi ô to hơn trên tem 63.5mm, tức DỄ quét hơn ở máy in nhiệt
 * 203dpi. Ép byte để "giống ảnh" sẽ làm mã to ra và khó quét hơn, nên cố ý không ép.
 */
import QRCode from 'qrcode';

export interface QrPrinterRow {
  rack: string;
  code: string;
  weight: string;
}

export interface QrPrinterSlipData {
  color: string;
  code: string;
  machine: string;
  level: string;
  /** 9 dòng thuốc nhuộm (txt_RACK/txt_DYE/txt_WEIGHT 1..9). */
  dye: QrPrinterRow[];
  /** 9 dòng hóa chất (txt_chem_rack/txt_chem_code/txt_chem_weight 1..9). */
  chem: QrPrinterRow[];
  /** Khổ giấy (mm) — mặc định lấy từ DEVMODE của workbook. */
  paperWmm: number;
  paperHmm: number;
}

export const DEFAULT_PAPER_W_MM = 63.5;
export const DEFAULT_PAPER_H_MM = 38.5;
export const PRINTER_NAME = 'vn-ld047\\TSC TE200';

/** VBA `Trim$` chỉ cắt dấu cách, không cắt CR/LF. */
const trimSpaces = (s: string) => String(s ?? '').replace(/^ +| +$/g, '');

const esc = (v: string) =>
  String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** `Format(Now, "HH:nn")` — 24 giờ, 2 chữ số. */
function nowHHmm(): string {
  const n = new Date();
  const p2 = (v: number) => String(v).padStart(2, '0');
  return `${p2(n.getHours())}:${p2(n.getMinutes())}`;
}

/* ===================================================================================
 * 1. CHUỖI QR
 * =================================================================================== */

/**
 * Payload QR của nút `print`:
 *
 *     #COLOR-CODE-MACHINE-LV-DYE[-rack-dye-weight]…-CHEM[-rack-chemcode-weight]…
 *
 * Duyệt đủ 9 dòng mỗi khối; **điều kiện nhận một dòng là ô RACK khác rỗng** (không phải ô mã),
 * khối lượng rỗng thì ghi "0".
 *
 * Đây chính là định dạng mà `txt_color_AfterUpdate` của cùng workbook đọc lại được — quét tem
 * này vào ô COLOR sẽ dựng lại nguyên 9+9 dòng. Đổi định dạng ở đây là làm hỏng vòng quét đó.
 */
export function buildSlipQrData(d: QrPrinterSlipData): string {
  let qr = `#${d.color ?? ''}-${d.code ?? ''}-${d.machine ?? ''}-${d.level ?? ''}`;

  qr += '-DYE';
  for (const r of d.dye) {
    if (trimSpaces(r.rack) === '') continue;
    qr += '-' + trimSpaces(r.rack);
    qr += '-' + trimSpaces(r.code);
    qr += '-' + (trimSpaces(r.weight) === '' ? '0' : trimSpaces(r.weight));
  }

  qr += '-CHEM';
  for (const r of d.chem) {
    if (trimSpaces(r.rack) === '') continue;
    qr += '-' + trimSpaces(r.rack);
    qr += '-' + trimSpaces(r.code);
    qr += '-' + (trimSpaces(r.weight) === '' ? '0' : trimSpaces(r.weight));
  }

  return qr;
}

/**
 * Chuỗi `rawqrdye` / `rawqrchem` mà `btnSend_Click` ghi xuống `tbl_tosend`:
 * các bộ ba `rack-code-weight` nối bằng "-", **chỉ lấy dòng có ô MÃ khác rỗng** (khác điều kiện
 * của QR ở trên vốn xét ô RACK), và cắt dấu "-" thừa ở cuối.
 */
export function buildRawQr(rows: QrPrinterRow[]): string {
  let s = '';
  for (const r of rows) {
    if (trimSpaces(r.code) === '') continue;
    s += `${r.rack ?? ''}-${r.code ?? ''}-${r.weight ?? ''}-`;
  }
  return s.endsWith('-') ? s.slice(0, -1) : s;
}

/* ===================================================================================
 * 2. HÌNH HỌC SHEET TẠM — SỐ ĐO THẬT, KHÔNG SUY ĐOÁN
 * ===================================================================================
 *
 * Các hằng số dưới đây ĐO TRỰC TIẾP bằng cách cho Excel chạy lại đúng chuỗi thao tác của
 * `btnPrint_Click` trên một workbook trắng rồi đọc lại hình học (script
 * `scratchpad/measure_slip.ps1`, dữ liệu mẫu "xấu nhất": rack 2 chữ số, mã BK8817D, khối
 * lượng 1234.75):
 *
 *   Chiều cao MỌI dòng 1..19 .............. 24.75 pt   (Calibri 19pt)
 *   Bề rộng cột D..J (mặc định) ........... 48 pt
 *   qrTop  = Cells(4,5).Top ............... 74.25 pt  = 3 × 24.75
 *   qrWidth = Range("E1:J1").Width ........ 288 pt    = 6 × 48
 *   qrLeft = Cells(1,5).Left .............. (A+B+C) + 48
 *
 * ---- Công thức AutoFit (rút ra bằng thực nghiệm, KHÔNG lấy từ tài liệu) ----
 * Cho Excel autofit từng chuỗi một ở Calibri 19pt (`scratchpad/measure_text.ps1`,
 * `measure_num.ps1`) rồi đối chiếu với `canvas.measureText` cùng font:
 *
 *   bề rộng cột (px) = bề rộng chữ đo bằng canvas (px) + 10.4  [+ 7 nếu là SỐ có phần thập phân]
 *
 * Số liệu chốt công thức:
 *   'W' 33px, 'WW' 55px, 'WWWW' 100px, 'W'×8 191px  → mỗi W ≈ 22.6px, phần đệm ≈ 10.4px
 *   Cột A 'DF_QR_SLIP' : canvas 125.00 + 10.4 = 135.4  ↔ Excel 135  (lệch 0.4px)
 *   Cột B 'DYE CODE'   : canvas 103.55 + 10.4 = 113.95 ↔ Excel 114  (lệch 0.05px)
 *   Cột C '1234.75'    : canvas  85.60 + 10.4 + 7 = 103 ↔ Excel 103 (khớp)
 *
 * Phần +7px CHỈ áp cho số có dấu thập phân — đã đo cặp đôi text/số cùng nội dung:
 *   1234.75 → 96px (text) / 103px (số)   98.5 → 56/63   7.125 → 69/76   (đều +7)
 *   0, 1, 12, 450, 10000000 → +0
 */

/** Bề rộng cột mặc định của Excel (8.43 ký tự Calibri 11 = 64px = 48pt). */
const DEFAULT_COL_PT = 48;

/** Chiều cao dòng Excel tự đặt cho Calibri 19pt — số ĐO ĐƯỢC, không phải ước lượng. */
const ROW_H_PT = 24.75;

/** Phần đệm cố định Excel cộng vào bề rộng chữ khi AutoFit (px) — xem khối ghi chú trên. */
const AUTOFIT_PAD_PX = 10.4;

/** Chỗ Excel chừa thêm cho SỐ có phần thập phân ở định dạng General (px). */
const AUTOFIT_DECIMAL_PX = 7;

const PX_PER_PT = 4 / 3;

/**
 * Đo bề rộng chuỗi ở đúng font ô (Calibri 19pt) bằng canvas rồi áp công thức AutoFit của Excel.
 * Carlito là font metric-compatible với Calibri nên máy không có Calibri vẫn ra đúng bề rộng.
 * Không đo được (không có canvas) thì trả null để bảng tự co như trước — thà lệch bề rộng còn
 * hơn không in được.
 */
function autoFitColumnsPt(rows: SlipCell[][], fontPt: number): number[] | null {
  try {
    const ctx = document.createElement('canvas').getContext('2d');
    if (!ctx) return null;
    ctx.font = `${fontPt * PX_PER_PT}px Calibri, Carlito, sans-serif`;
    const widths: number[] = [];
    for (let c = 0; c < 3; c++) {
      let maxPx = 0;
      for (const row of rows) {
        const cell = row[c];
        const extra = cell.kind === 'number' && cell.text.includes('.') ? AUTOFIT_DECIMAL_PX : 0;
        const w = ctx.measureText(cell.text).width + extra;
        if (w > maxPx) maxPx = w;
      }
      widths.push((maxPx + AUTOFIT_PAD_PX) / PX_PER_PT);
    }
    return widths;
  } catch {
    return null;
  }
}

/* ===== Định dạng General của Excel =====
 * `ws.Cells(r,c).Value = <chuỗi lấy từ TextBox>` ghi vào ô định dạng General: chuỗi nào ĐỌC
 * ĐƯỢC thành số thì Excel ĐỔI THÀNH SỐ (mất số 0 ở đầu, bỏ số 0 thừa sau dấu phẩy) và CANH
 * PHẢI; còn lại giữ nguyên chữ và canh trái. Đã xác nhận bằng số đo: B6 "450", A9 "1",
 * C9 "1234.75" đều trả về kiểu số, mọi ô đều HorizontalAlignment = xlGeneral.
 */
interface SlipCell {
  text: string;
  /** `number` canh phải và được chừa thêm chỗ nếu có phần thập phân; `time` chỉ canh phải. */
  kind: 'text' | 'number' | 'time';
}

function generalCell(raw: string): SlipCell {
  const s = String(raw ?? '').trim();
  if (s === '') return { text: '', kind: 'text' };
  const n = Number(s);
  // Excel hiển thị số theo định dạng General: mất số 0 ở đầu ("0574" → 574), bỏ số 0 thừa sau
  // dấu phẩy ("1.500" → 1.5) — `String(Number(s))` cho ra đúng kết quả đó.
  if (Number.isFinite(n)) return { text: String(n), kind: 'number' };
  return { text: s, kind: 'text' };
}

/** Ô chữ thuần (nhãn "COLOR:", tiêu đề cột…) — luôn canh trái. */
const textCell = (s: string): SlipCell => ({ text: s, kind: 'text' });

/**
 * `Format(Now, "HH:nn")` cho ra "10:44"; Excel NHẬN RA đây là giờ, lưu thành số serial
 * (đã đo: 0.4576388…) và tự gán định dạng h:mm → hiển thị "10:44" nhưng CANH PHẢI như số.
 * Không cộng thêm 7px vì đây không phải số General (và trong thực tế ô này chưa bao giờ là ô
 * rộng nhất của cột B).
 */
const timeCell = (s: string): SlipCell => ({ text: s, kind: 'time' });

async function qrDataUrl(text: string): Promise<string> {
  try {
    // Workbook B đưa thẳng `qrData` vào `URLEncode_UTF8` — KHÔNG có `CleanString` như workbook A
    // (đối chiếu `scratchpad/vba/mdQRCodegen.bas` dòng 12 vs `vbaB2/mdQRCodegen.bas`). Giữ
    // `trimSpaces` ở đây chỉ để phòng hờ: chuỗi luôn mở đầu bằng "#" và kết thúc bằng "-CHEM"
    // hoặc một khối lượng đã Trim, nên nó không bao giờ cắt được ký tự nào.
    return await QRCode.toDataURL(trimSpaces(text), {
      errorCorrectionLevel: 'L',
      margin: 0,
      width: 960,
    });
  } catch (err) {
    console.error('Không dựng được ảnh QR cho phiếu QR PRINTER:', err);
    return '';
  }
}

/* ===================================================================================
 * 3. DỰNG TRANG IN
 * =================================================================================== */

/**
 * Sheet tạm của VBA (Calibri 19pt, `Borders.LineStyle = xlContinuous` trên CẢ vùng A1:C19 nên
 * mọi ô — kể cả 3 dòng trống 2/7/18 — đều có khung):
 *
 *   dòng  1  A="DF_QR_SLIP"
 *   dòng  2  (trống)
 *   dòng  3  A="COLOR:"    B=mã màu
 *   dòng  4  A="CODE:"     B=mã hàng
 *   dòng  5  A="MACHINE:"  B=máy
 *   dòng  6  A="LEVEL:"    B=mực nước
 *   dòng  7  (trống)
 *   dòng  8  A="RACK"      B="DYE CODE"  C="WEIGHT"
 *   dòng 9-17 9 dòng thuốc nhuộm; cột C chỉ ghi khi RACK khác rỗng (khối lượng rỗng → 0)
 *   dòng 18  (trống)
 *   dòng 19  A="Print time:"  B=HH:mm
 *
 * Ảnh QR đặt tại `Cells(1,5).Left` / `Cells(4,5).Top` — mép trái cột E (sau 3 cột autofit A-C
 * cộng 1 cột D bề rộng mặc định) và mép trên dòng 4 — cạnh bằng `Range("E1:J1").Width` = 288pt.
 *
 * `FitToPagesWide/Tall = 1` tái lập bằng đoạn script trong trang in: đo nội dung so với vùng in
 * rồi đặt `zoom` (làm tròn XUỐNG phần trăm nguyên như Excel). Dùng `zoom` chứ không phải
 * `transform: scale()` vì Excel khi ép vừa trang cũng thu nhỏ CẢ nét kẻ theo tỉ lệ.
 */
export async function buildSlipHtml(d: QrPrinterSlipData): Promise<string> {
  const FONT_PT = 19;
  const ROW_PT = ROW_H_PT;
  const QR_PT = DEFAULT_COL_PT * 6; // Range("E1:J1").Width = 288pt
  const MARGIN_MM = 2; // PageSetup: 0.2cm cả 4 lề

  const empty = (): SlipCell[] => [textCell(''), textCell(''), textCell('')];

  const rows: SlipCell[][] = [
    [textCell('DF_QR_SLIP'), textCell(''), textCell('')],
    empty(),
    [textCell('COLOR:'), generalCell(d.color ?? ''), textCell('')],
    [textCell('CODE:'), generalCell(d.code ?? ''), textCell('')],
    [textCell('MACHINE:'), generalCell(d.machine ?? ''), textCell('')],
    [textCell('LEVEL:'), generalCell(d.level ?? ''), textCell('')],
    empty(),
    [textCell('RACK'), textCell('DYE CODE'), textCell('WEIGHT')],
  ];

  for (let i = 0; i < 9; i++) {
    const r = d.dye[i] ?? { rack: '', code: '', weight: '' };
    // `If Trim(rack) <> "" Then (weight = "" -> ghi SỐ 0) Else ghi rỗng`
    let weight = '';
    if (trimSpaces(r.rack) !== '') {
      weight = trimSpaces(r.weight) === '' ? '0' : r.weight;
    }
    rows.push([generalCell(r.rack ?? ''), generalCell(r.code ?? ''), generalCell(weight)]);
  }

  rows.push(empty());
  rows.push([textCell('Print time:'), timeCell(nowHHmm()), textCell('')]);

  // AutoFit chỉ chạy trên 3 cột A:C của vùng A1:C19 — đúng phạm vi `rng.Columns.AutoFit`.
  const colsPt = autoFitColumnsPt(rows, FONT_PT);
  const colgroup = colsPt
    ? `<colgroup>${colsPt.map(w => `<col style="width:${w.toFixed(3)}pt" />`).join('')}</colgroup>`
    : '';
  const tableWidthPt = colsPt ? colsPt.reduce((a, b) => a + b, 0) : null;

  const table = rows
    .map(
      cells =>
        `<tr>${cells
          .map(
            c =>
              `<td style="border:1px solid #000;height:${ROW_PT}pt;text-align:${c.kind === 'text' ? 'left' : 'right'}">${esc(c.text)}</td>`
          )
          .join('')}</tr>`
    )
    .join('');

  const qr = await qrDataUrl(buildSlipQrData(d));
  // Ảnh nằm ở cột E: chừa đúng 1 cột D bề rộng mặc định giữa bảng và ảnh; lệch xuống 3 dòng
  // vì `qrTop = Cells(4, 5).Top`.
  const qrHtml = qr
    ? `<div style="width:${DEFAULT_COL_PT}pt;flex:none"></div>
       <img src="${qr}" alt="QR" style="flex:none;margin-top:${ROW_PT * 3}pt;width:${QR_PT}pt;height:${QR_PT}pt" />`
    : '';

  const areaW = d.paperWmm - MARGIN_MM * 2;
  const areaH = d.paperHmm - MARGIN_MM * 2;

  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>DF_QR_SLIP ${esc(d.color ?? '')}</title>
<style>
  * { box-sizing: border-box; }
  /* Máy in tem là máy in NHIỆT: chỉ đen/trắng, mọi sắc xám bị dither thành lưới chấm thưa nên
     nhìn mờ. Chặn trình duyệt "tối ưu" màu khi in. */
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; padding: 6mm; color: #000; display: flex; flex-direction: column;
         align-items: center; font-family: Calibri, Carlito, 'Segoe UI', sans-serif; }
  /* .paper = đúng khổ giấy trong DEVMODE; .area = vùng in được sau khi trừ lề PageSetup. */
  .paper { position: relative; width: ${d.paperWmm}mm; height: ${d.paperHmm}mm; background: #fff; zoom: 3; }
  .area { position: absolute; left: ${MARGIN_MM}mm; top: ${MARGIN_MM}mm;
          width: ${areaW}mm; height: ${areaH}mm; overflow: hidden; }
  .sheet { display: flex; align-items: flex-start; width: max-content; font-size: ${FONT_PT}pt; }
  /* table-layout fixed để bề rộng cột LẤY ĐÚNG số AutoFit đã tính, không cho trình duyệt tự
     co giãn lại theo nội dung (Excel đã chốt bề rộng rồi mới in). */
  table { border-collapse: collapse; table-layout: fixed;${tableWidthPt !== null ? ` width:${tableWidthPt.toFixed(3)}pt;` : ''} }
  /* Lề trong ô của Excel là 2px mỗi bên (đã tính vào công thức AutoFit ở trên). */
  td { white-space: nowrap; vertical-align: bottom; padding: 0 1.5pt; overflow: hidden; }
  .footnote { margin-top: 3mm; font-size: 2.3mm; color: #666; max-width: 190mm; }
  @page { size: ${d.paperWmm}mm ${d.paperHmm}mm; margin: 0; }
  @media print {
    body { padding: 0; display: block; }
    .paper { zoom: 1; }
    .footnote { display: none; }
  }
</style>
</head>
<body>
  <div class="paper"><div class="area"><div class="sheet" id="sheet"><table>${colgroup}${table}</table>${qrHtml}</div></div></div>

  <p class="footnote">
    DF_QR_SLIP — port 1:1 từ scaleform.btnPrint_Click của "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm".
    Khổ giấy ${d.paperWmm}×${d.paperHmm}mm, lề 0.2cm, ép vừa 1 trang. Máy in gốc: ${esc(PRINTER_NAME)}.
  </p>

  <script>
    window.onload = function () {
      // FitToPagesWide/Tall = 1: đo nội dung SAU khi ảnh QR và font đã sẵn sàng rồi ép vừa vùng
      // in. So TỈ LỆ hai getBoundingClientRect (nội dung vs vùng in) chứ không quy ra px tuyệt
      // đối: cả hai cùng nằm dưới mức zoom xem trước của .paper nên tỉ lệ tự khử phần zoom đó.
      var sheet = document.getElementById('sheet');
      var area = sheet.parentElement;
      var cr = sheet.getBoundingClientRect();
      var ar = area.getBoundingClientRect();
      if (cr.width > 0 && cr.height > 0) {
        // Excel cho phép phóng to tới 400% khi ép vừa trang, và làm tròn XUỐNG phần trăm nguyên.
        var s = Math.min(ar.width / cr.width, ar.height / cr.height, 4);
        sheet.style.zoom = String(Math.floor(s * 100) / 100);
      }
      // window.print() CHẶN tới khi hộp thoại in đóng trên Chrome/Edge, nên window.close() ngay
      // dòng kế tiếp chạy SAU khi người dùng bấm In/Hủy.
      window.print();
      window.close();
    };
  <\/script>
</body>
</html>`;
}

/**
 * Ghi phiếu vào một cửa sổ ĐÃ MỞ SẴN — phiếu hiện ra ở cửa sổ riêng nổi bên ngoài trang, người
 * vận hành nhìn thấy rồi bấm in (yêu cầu 06/08: KHÔNG in ngầm trong trang bằng iframe).
 *
 * Cửa sổ phải được `window.open()` ĐỒNG BỘ ngay trong trình xử lý sự kiện click — Chrome/Edge
 * chặn pop-up nếu gọi sau một tác vụ bất đồng bộ (mất "user gesture"), khiến bấm nút in không
 * hiện hộp thoại nào cả.
 */
export async function writeSlipToWindow(win: Window, data: QrPrinterSlipData): Promise<void> {
  const html = await buildSlipHtml(data);
  win.document.write(html);
  win.document.close();
}
