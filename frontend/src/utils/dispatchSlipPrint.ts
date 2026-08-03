/**
 * Dựng tem "DF_WEIGHING_SLIP" 70x100mm dạng HTML để in QUA TRÌNH DUYỆT (không qua TSPL/Local
 * Agent) — tách ra từ PrintStation.vue để màn hình Nhập đơn (PrintOrderEntry -> VbaPrintForm)
 * in ra ĐÚNG cùng một tem, không phải một bố cục tự vẽ riêng (yêu cầu 2026-08-03).
 *
 * Bố cục LẤY ĐÚNG toạ độ đo thật từ sheet "DF_WEIGHING_SLIP" gốc (file "Copy of Copy of DF002
 * no formulas..." không khóa VBA, 2026-07-21) — quy đổi dot TSPL (backend
 * QrPayloadService::buildTsplLabel70x100) sang mm (chia 8, vì 203dpi = 8 dot/mm) để khớp 1:1
 * với tem in thật. Đổi bố cục ở đây thì phải đối chiếu lại với backend.
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

import { parseRackLines } from './rackParser';

// Port ĐÚNG y hệt WarehouseRoutingService::calculateRouting (backend) — đã đối chiếu
// xác nhận khớp 100% với code thật Mod_printslip.bas (D1 zone) ngày 2026-07-22. Chỉ
// dùng để XEM TRƯỚC ở màn hình "In qua trình duyệt" (dispatch chưa confirm nên chưa
// có RoutingDecision thật) — kết quả này KHÔNG được lưu, không thay thế tính toán thật
// lúc bấm "In qua trình duyệt" (ConfirmDispatchService/WarehouseRoutingService).
function numBetween(v: number, min: number, max: number): boolean {
  return v >= min && v <= max;
}

/** Port "VD" & Format(Val(Mid(s,3)),"000") — chuẩn hóa mã máy về 3 chữ số (VD4 -> VD004). */
function normalizeVdCode(code: string): string {
  const c = (code || '').toUpperCase().trim();
  if (c.startsWith('VD')) {
    const num = parseInt(c.slice(2), 10) || 0;
    return 'VD' + String(num).padStart(3, '0');
  }
  return c;
}

/**
 * Port Mod_printslip.bas khối "PROCESS CHECK" (dòng 88-115) — phân loại họ thuốc nhuộm in
 * vào QR mode PROCESS. Trước đây chỗ này hard-code "Nylon Dyes" nên mọi tem PROCESS đều
 * báo sai họ thuốc với lô Cation/Disperse. Đối chiếu backend QrPayloadService::
 * computeDyesProcess — hai bên phải cho cùng kết quả.
 *
 * Quy tắc gốc:
 *  - mã dye kết thúc bằng "C"                                 -> Cation Dyes (thắng tuyệt đối)
 *  - mã dye kết thúc bằng "D" hoặc bắt đầu Y13/R23/B33         -> đánh dấu isDisperse
 *  - mã hóa chất có chứa "0574" hoặc "0507"                    -> đánh dấu hasChemKey
 *  - không phải Cation, mà isDisperse && hasChemKey            -> Disperse Dyes
 *  - còn lại                                                   -> Nylon Dyes
 */
function computeDyesProcess(dyeCodes: string[], chemCodes: string[]): string {
  let dyesProcess = 'Nylon Dyes';
  let isDisperse = false;

  for (const raw of dyeCodes) {
    const c = (raw || '').toUpperCase().trim();
    if (c === '') continue;
    if (c.endsWith('C')) dyesProcess = 'Cation Dyes';
    if (c.endsWith('D') || c.startsWith('Y13') || c.startsWith('R23') || c.startsWith('B33')) {
      isDisperse = true;
    }
  }

  const hasChemKey = chemCodes.some(raw => {
    const c = (raw || '').toUpperCase().trim();
    return c.includes('0574') || c.includes('0507');
  });

  if (dyesProcess !== 'Cation Dyes' && isDisperse && hasChemKey) {
    dyesProcess = 'Disperse Dyes';
  }

  return dyesProcess;
}

/** Port Format(Now,"yyyymmddhhmm") / Format(Now,"hhmm") — chỉ 2 kiểu Mod_printslip.bas dùng. */
function nowStamp(pattern: 'yyyymmddhhmm' | 'hhmm'): string {
  const n = new Date();
  const p2 = (v: number) => String(v).padStart(2, '0');
  const hhmm = p2(n.getHours()) + p2(n.getMinutes());
  if (pattern === 'hhmm') return hhmm;
  return `${n.getFullYear()}${p2(n.getMonth() + 1)}${p2(n.getDate())}${hhmm}`;
}

export function calculateRoutingPreview(machineCode: string, tankCode: string, levelCode: string) {
  const machine = (machineCode || '').toUpperCase().trim();
  const tank = (tankCode || '').toUpperCase().trim();
  const m = /^VD(\d+)$/.exec(machine);
  const machineNum = m ? parseInt(m[1], 10) : 0;

  let b24: string | null = null;
  if (numBetween(machineNum, 6, 13) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT CAO, MAY E13, MAY A11';
  } else if ((machineNum === 17 || machineNum === 18) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT CAO, MAY E12, MAY A11';
  } else if ((machineNum === 17 || machineNum === 18) && (tank === '3C' || tank === '4D')) {
    b24 = levelCode == '50' ? 'PHA TAY, HOA CHAT DLG' : 'THUNG SAT CAO, MAY E12, MAY DLG';
  } else if ((numBetween(machineNum, 1, 5) || numBetween(machineNum, 14, 16)) && (tank === '1A' || tank === '2B')) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY A11';
  } else if (numBetween(machineNum, 1, 16) && (tank === '3C' || tank === '4D')) {
    b24 = 'THUNG SAT THAP, MAY JIT, MAY DLG';
  }

  let d1 = '';
  if (numBetween(machineNum, 6, 13) && (tank === '1A' || tank === '2B')) {
    d1 = 'E13';
  } else if ((machineNum === 17 || machineNum === 18) && ['1A', '2B', '3C', '4D'].includes(tank)) {
    d1 = 'E12';
  } else if (numBetween(machineNum, 1, 5) && (tank === '1A' || tank === '2B')) {
    d1 = 'JIT3';
  } else if (numBetween(machineNum, 1, 9) && (tank === '3C' || tank === '4D')) {
    d1 = 'JIT2';
  } else if (numBetween(machineNum, 14, 16) && (tank === '1A' || tank === '2B')) {
    d1 = 'JIT4';
  } else if (numBetween(machineNum, 10, 16) && (tank === '3C' || tank === '4D')) {
    d1 = 'JIT1';
  }

  let mode = 'FB';
  if (b24 !== null) {
    if (b24.includes('MAY JIT')) mode = 'PROCESS';
    else if (b24.includes('THUNG SAT CAO')) mode = 'EXTRA';
  }
  if (mode === 'FB') {
    b24 = 'PHU BAN-LAY LIEU COPOWER';
  }

  return { d1Zone: d1, b24Route: b24 || '', mode };
}

/**
 * Dựng toàn bộ HTML một trang in chứa đúng 1 tem (kèm sẵn script tự gọi window.print()).
 * Dispatch CHƯA confirm nên chưa có RoutingDecision LƯU THẬT trong DB — nhưng khu vực D1/chuỗi
 * B24 tự tính lại ngay tại đây bằng calculateRoutingPreview() (đã đối chiếu khớp 100% với
 * backend + code VBA gốc), nên tem vẫn hiển thị ĐÚNG giá trị thật.
 */
export async function buildDispatchSlipHtml(data: DispatchSlipData): Promise<string> {
  const dyeLines = parseRackLines(data.rawQrDye);
  const chemLines = parseRackLines(data.rawQrChem);

  const routing = calculateRoutingPreview(data.machineCode, data.tankCode, data.levelCode);

  const dyeQrText = `#${data.color}-${data.productCode}-${data.machineCode}-${data.levelCode}-${data.rawQrDye}`;

  // qrChem — port đúng backend QrPayloadService::buildChemPayload: KHÔNG nhét thẳng
  // raw_qr_chemical thô, mã thùng chỉ lấy ký tự đầu, và mỗi trường cách nhau 1 dòng
  // trống (yêu cầu 2026-07-22, lệch có chủ đích so với VBA gốc — khớp tem thật đang
  // dùng). "#" cuối nối TRỰC TIẾP vào dòng khối lượng cuối, không có dòng trống trước nó.
  const chemRndPreview = 1 + Math.floor(Math.random() * 9);
  const chemParts = [
    normalizeVdCode(data.machineCode),
    data.tankCode.toUpperCase().trim().charAt(0),
    `#${data.color}-${data.productCode}`,
    String(chemRndPreview),
    data.levelCode,
  ];
  chemLines.forEach(r => {
    chemParts.push(r.code);
    chemParts.push(String(r.weight).replace(',', '.'));
  });
  const chemQrText = chemParts.join('\n\n') + '#';

  // QR chế độ (PROCESS/EXTRA/FB) — port đúng định dạng qrProcess/qrExtra/qrFB trong
  // Mod_printslip.bas gốc, đặt ở G1:H1. Không có totalD (tổng khối lượng dye) sẵn ở màn
  // hình xem trước nên tính lại từ chính bảng dye đang hiển thị.
  let modeQrText = '';
  if (routing.mode === 'PROCESS') {
    // Yêu cầu 2026-07-22: dòng trống xen giữa 3 phần (khác VBA gốc chỉ 1 vbCrLf) — đối
    // chiếu tem thật đang dùng, chỉ áp dụng cho mode PROCESS (xem QrPayloadService::
    // buildProcessPayload backend, cùng lý do).
    const tankUpper = data.tankCode.toUpperCase();
    const newLevel = tankUpper === '1A' ? '450' : tankUpper === '2B' ? '250' : data.levelCode;
    const dyesProcess = computeDyesProcess(dyeLines.map(r => r.code), chemLines.map(r => r.code));
    modeQrText = `${data.color}-${data.productCode} ${nowStamp('yyyymmddhhmm')}\n\n${data.machineCode}-${data.tankCode}-${newLevel}\n\n${dyesProcess}`;
  } else if (routing.mode === 'EXTRA') {
    const totalD = dyeLines.reduce((sum, r) => sum + (parseFloat(r.weight) || 0), 0);
    const rnd = 1 + Math.floor(Math.random() * 9);
    // VBA: vF3QR chuẩn hóa "VD" + 3 chữ số, và Format(totalD,"0.###") — tối đa 3 số lẻ,
    // cắt số 0 thừa. Để nguyên số JS sẽ lòi đuôi dấu phẩy động (4.250000000000001).
    const totalDText = String(parseFloat(totalD.toFixed(3)));
    modeQrText = `${normalizeVdCode(data.machineCode)}\n${data.tankCode.toUpperCase().trim().charAt(0)}\n${data.color} ${data.productCode}\n${rnd}\n${data.levelCode}\n1\n${totalDText}`;
  } else {
    modeQrText = `${data.color}-${data.productCode} ${nowStamp('hhmm')}`;
  }

  let dyeQrDataUrl = '';
  let chemQrDataUrl = '';
  let modeQrDataUrl = '';
  // width 240/200 -> 960/800 (2026-07-31, tem in ra mờ): QR in ở ~20mm trên máy 203dpi tức
  // ~160 dot, nhưng trình duyệt render trang in ở DPI cao hơn màn hình nhiều; ảnh nguồn 240px
  // phải phóng lên khi in -> cạnh module QR nhoè xám -> máy in nhiệt dither thành lấm tấm.
  // Cho nguồn dư độ phân giải để lúc in luôn là THU NHỎ (nội suy mượt, nét đen giữ đặc).
  try {
    dyeQrDataUrl = await QRCode.toDataURL(dyeQrText, { width: 960, margin: 0 });
    chemQrDataUrl = await QRCode.toDataURL(chemQrText, { width: 960, margin: 0 });
    modeQrDataUrl = await QRCode.toDataURL(modeQrText, { width: 800, margin: 0 });
  } catch (err) {
    console.error('Failed to render QR for browser print:', err);
  }

  // Toạ độ — LẤY NGUYÊN các mốc dot (203dpi, 8dot/mm) dùng trong
  // QrPayloadService::buildTsplLabel70x100 (backend, đã đối chiếu ảnh tem in thật
  // 2026-07-21), quy đổi sang mm chỉ tại lúc vẽ (chia 8) qua boxDot()/mmD() — KHÔNG
  // còn tự tính lại mốc riêng, tránh lệch giữa preview và tem in thật. Đổi 2026-07-22:
  // dùng ĐỦ chiều rộng 0-560 dot (trước đó có lề dư 5.25mm/5.375mm trái/phải không có
  // trên tem thật), ô Màu+Mã hàng gộp 1 khung không đường kẻ giữa, QR to hơn.
  const DOT = 8;
  // FIT/MARGIN — thu bản vẽ NGAY TỪ LÚC TÍNH TOẠ ĐỘ, thay cho `transform: scale()` (đã gỡ ở
  // mục 55 vì làm đứt nét) và thay cho việc để trình duyệt tự co trang.
  // Người dùng xác nhận 2026-07-31: "trước đó căn chưa chuẩn nhưng tem nào cũng NÉT, giờ in
  // chuẩn vị trí rồi thì bị mờ" -> đúng thủ phạm là @page size = ĐÚNG khổ giấy (mục 42):
  // vùng in được của máy in luôn NHỎ HƠN khổ giấy, nên Chrome phải co cả trang cho vừa
  // (fit to printable area) -> mọi nét bị nhân với hệ số lẻ -> lẻ dot -> răng cưa/mờ.
  // Cách xử lý: tự thu nội dung xuống dưới vùng in được + chừa lề, để trình duyệt KHÔNG
  // phải co thêm lần nữa. Khác biệt then chốt so với transform: ở đây chỉ TOẠ ĐỘ bị nhân
  // hệ số, còn ĐỘ DÀY nét và CỠ CHỮ vẫn khai báo bằng mm nguyên (0.375mm = đúng 3 dot) nên
  // nét vẫn tròn dot, in ra liền mạch.
  const FIT = 0.955;      // 70mm -> 66.85mm, 100mm -> 95.5mm
  const MARGIN_MM = 1.6;  // lề chừa quanh tem (mm)
  const mmD = (dot: number) => (dot / DOT) * FIT;

  function box(x: number, y: number, w: number, h: number, innerHtml: string, noBorder = false, extraClass = ''): string {
    return `<div class="box${noBorder ? ' noborder' : ''}${extraClass ? ' ' + extraClass : ''}" style="left:${x}mm;top:${y}mm;width:${w}mm;height:${h}mm;">${innerHtml}</div>`;
  }
  function boxDot(x1: number, y1: number, x2: number, y2: number, innerHtml: string, noBorder = false, extraClass = ''): string {
    return box(mmD(x1), mmD(y1), mmD(x2 - x1), mmD(y2 - y1), innerHtml, noBorder, extraClass);
  }

  const tableTop = 200, rowHDot = 41, tableBottom = tableTop + rowHDot * 9; // 569
  const rowH = mmD(rowHDot);
  const titleTop = tableBottom, qrTop = 605, qrBottom = 763, routeY = 772;
  const dyeColsDot: [number, number][] = [[0, 110], [110, 206], [206, 278]];
  const chemColsDot: [number, number][] = [[293, 391], [391, 498], [498, 560]];

  let tableCellsHtml = '';
  for (let i = 0; i < 9; i++) {
    const y = tableTop + i * rowHDot;
    const dr = dyeLines[i];
    const cr = chemLines[i];
    if (dr) {
      tableCellsHtml += boxDot(dyeColsDot[0][0], y, dyeColsDot[0][1], y + rowHDot, `<span class="cellval">${dr.rack}</span>`, true);
      tableCellsHtml += boxDot(dyeColsDot[1][0], y, dyeColsDot[1][1], y + rowHDot, `<span class="cellval">${dr.code}</span>`, true);
      tableCellsHtml += boxDot(dyeColsDot[2][0], y, dyeColsDot[2][1], y + rowHDot, `<span class="cellval cellval-right">${dr.weight}</span>`, true);
    }
    if (cr) {
      tableCellsHtml += boxDot(chemColsDot[0][0], y, chemColsDot[0][1], y + rowHDot, `<span class="cellval">${cr.rack}</span>`, true);
      tableCellsHtml += boxDot(chemColsDot[1][0], y, chemColsDot[1][1], y + rowHDot, `<span class="cellval">${cr.code}</span>`, true);
      tableCellsHtml += boxDot(chemColsDot[2][0], y, chemColsDot[2][1], y + rowHDot, `<span class="cellval cellval-right">${cr.weight}</span>`, true);
    }
  }
  // Khung kẻ toàn bảng (kể cả ô rỗng) — vẽ riêng lưới 6 cột x 9 dòng để luôn thấy đủ khung.
  let tableGridHtml = '';
  for (let i = 0; i < 9; i++) {
    const y = mmD(tableTop + i * rowHDot);
    [...dyeColsDot, ...chemColsDot].forEach(([x1, x2]) => {
      tableGridHtml += `<div class="gridcell" style="left:${mmD(x1)}mm;top:${y}mm;width:${mmD(x2 - x1)}mm;height:${rowH}mm;"></div>`;
    });
  }

  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Tem ${data.batchId || ''}</title>
<style>
  * { box-sizing: border-box; }
  /* print-color-adjust:exact — chặn trình duyệt "tối ưu" màu khi in (làm nét đen bị nhạt
     đi thành xám). Máy in tem là máy in NHIỆT: chỉ có đen/trắng, mọi sắc xám đều bị dithering
     thành lưới chấm thưa -> nhìn mờ. Toàn bộ tem vì thế phải là #000 đặc, không xám. */
  html, body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #000; display: flex; flex-direction: column; align-items: center; }
  /* Tem thật chỉ 70x100mm nên trên màn hình to sẽ trông rất bé — phóng to riêng cho
     màn hình (zoom, không phải transform, để layout giãn ra đúng) để xem cho rõ, còn
     lúc in thật (@media print bên dưới) luôn trả về đúng kích thước gốc 1:1. */
  /* Viền cũ 1.2mm (ngoài)/0.3mm (ô)/0.2mm (lưới bảng) quá dày, lại càng dày hơn ở MỌI
     đường phân cách giữa 2 ô liền kề — mỗi ô tự vẽ border riêng (position:absolute, tọa
     độ x2 của ô này = x1 của ô sau) nên 2 border 0.3mm cạnh nhau cộng lại nhìn như một
     đường ~0.6mm, có cảm giác "đè" vào chữ do padding chỉ 0.4-0.8mm không đủ giãn cách
     (người dùng phản ánh 2026-07-30, có ảnh chụp tem in ra thật). Giảm đều độ dày +
     tăng padding cho thoáng hơn, không đổi bố cục/tọa độ.
     2026-07-31 (tem in ra MỜ): độ dày viền phải là bội số của 1 dot = 0.125mm ở máy in tem
     203dpi. Bản trước 0.15mm/0.2mm = 1.2/1.6 dot -> không tròn dot, trình duyệt khử răng cưa
     thành nét XÁM, máy in nhiệt (chỉ có đen/trắng) lại dither nét xám thành lưới chấm thưa
     -> nhìn mờ/đứt nét. Nâng về 0.25mm (2 dot) và 0.5mm (4 dot) cho nét đen đặc. Vẫn mỏng
     hơn hẳn bản gốc 0.3/1.2mm nên KHÔNG quay lại lỗi "viền to đè chữ" của mục 44 (padding
     giữ nguyên 0.5/0.9mm).
     2026-07-31 lần 2 (ảnh tem thật: CHỮ đen đặc sắc nét, chỉ ĐƯỜNG KẺ bị răng cưa/lượn sóng):
     2 dot vẫn quá mảnh để sống sót qua chuỗi render trang in -> driver máy in nhiệt. Driver
     TSC nhận ảnh raster ở DPI cao rồi hạ về 203dpi bằng dither: nét mảnh chỉ cần lệch nửa dot
     là bị chuyển thành chuỗi chấm so le (đúng hình răng cưa trong ảnh), trong khi chữ có nét
     dày + font hinting nên vẫn ra đen đặc. Nâng đường kẻ lên 0.375mm = ĐÚNG 3 dot: đủ dày để
     phần lõi luôn đen tuyệt đối kể cả khi 2 cạnh bị khử răng cưa. Vẫn mỏng hơn bản gốc 1.2mm
     và padding giữ nguyên nên không tái diễn lỗi "viền đè chữ" của mục 44.
  */
  /* Kích thước .slip suy ra từ chính mmD() (đã nhân FIT) + margin chừa lề — KHÔNG hard-code
     70x100mm nữa, để bản vẽ luôn nhỏ hơn vùng in được và trình duyệt không phải co trang. */
  .slip { position: relative; width: ${mmD(560)}mm; height: ${mmD(800)}mm; margin: ${MARGIN_MM}mm; border: 0.5mm solid #000; zoom: 2.6; }
  /* padding ngang 0.9 -> 0.6 -> 0.45mm: bản vẽ đã thu 4.5% nên ô hẹp lại, padding rộng làm
     mã dài 6 ký tự (Y1019A/R2064G) và mã máy chạm mép ô. Padding dọc giữ 0.5mm (hàng vẫn dư).
     Canh giữa cả 2 chiều + KHÔNG cho chữ tràn ra ngoài ô (yêu cầu 2026-07-31: "chữ đừng
     dài quá đè vào vạch kẻ, căn giữa trên dưới trái phải"). Trước đây overflow:visible nên
     nhãn dài (DF_WEIGHING_SLIP) tràn hẳn sang ô bên cạnh, chữ lại luôn dính mép trên-trái.
     flex-direction:column (không phải row) vì ô Màu+Mã hàng chứa 2 span xếp chồng — để row
     thì 2 span nằm cạnh nhau, vỡ bố cục. padding ngang 0.6 -> 0.45mm lấy thêm chỗ cho mã máy
     5 ký tự (VD003 ở cỡ 3.2mm rộng ~9.8mm, ô chỉ hở 9.75mm nếu giữ padding cũ). */
  .box { position: absolute; border: 0.375mm solid #000; overflow: hidden; padding: 0.5mm 0.45mm; white-space: nowrap;
         display: flex; flex-direction: column; align-items: center; justify-content: center; }
  .box.noborder { border: none; }
  .gridcell { position: absolute; border: 0.375mm solid #000; }
  /* Cỡ chữ nhỏ tăng đồng loạt (2026-07-31, ảnh tem thật: chữ/số trong bảng nhoè không đọc
     được, trong khi chữ to JIT3/LEP70158 vẫn sắc). Ở máy in nhiệt 203dpi, chữ 2.2mm chỉ cao
     ~17 dot và nét đứng của Arial mảnh hơn 1 dot -> driver dither ra lấm tấm, mất nét. Nét
     chữ chỉ ổn định khi dày >= 2 dot, tức cỡ chữ phải >= ~2.6mm ở font-weight 700.
     Chiều cao hàng bảng là 41 dot = 5.125mm, trừ padding 0.5mm x2 còn 4.1mm nên 2.6mm vẫn
     dư chỗ — KHÔNG phải nới lại bố cục/toạ độ đã khớp tem thật. */
  /* Arial Narrow riêng cho nhãn này: "DF_WEIGHING_SLIP" (16 ký tự) ở Arial Bold 2.6mm rộng
     ~25.6mm, trong khi ô chỉ hở ~22.9mm -> luôn tràn đè vạch kẻ. Font hẹp co bề NGANG ~18%
     (còn ~21mm, vừa ô) nhưng GIỮ NGUYÊN chiều cao chữ 2.6mm, nên nét đứng vẫn dày >= 2 dot
     ở máy in nhiệt 203dpi — không tái diễn lỗi chữ nhoè của mục "cỡ chữ nhỏ" phía trên.
     Thu nhỏ font là cách sai ở đây: 2.3mm mới vừa ô nhưng nét mảnh dưới 2 dot sẽ bị dither. */
  .label-sm { font-size: 2.6mm; font-weight: 700; white-space: nowrap;
              font-family: 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif; }
  .big { font-size: 3.2mm; font-weight: 700; line-height: 1; white-space: nowrap; }
  .big.code-line { display: block; margin-top: 1.2mm; }
  .zone { font-size: 5.5mm; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; display: block; width: 100%; }
  .med { font-size: 2.9mm; font-weight: 700; white-space: nowrap; }
  /* Ô trong bảng: giữ nguyên canh ngang như tem thật (RACK/mã căn trái, khối lượng căn phải)
     — chỉ hưởng canh giữa theo CHIỀU DỌC từ .box. align-self:stretch để span chiếm đủ bề
     ngang ô (trong flex column, mặc định span co lại bằng nội dung nên text-align mất tác dụng). */
  .cellval { font-size: 2.6mm; font-weight: 700; align-self: stretch; text-align: left; }
  .cellval-right { text-align: right; }
  .title { font-size: 2.6mm; font-weight: 700; }
  .qr-block { position: absolute; text-align: center; }
  /* KHÔNG dùng image-rendering:pixelated cho QR: ảnh QR đang bị THU NHỎ (nguồn px > kích
     thước in), pixelated lúc downscale sẽ vứt pixel không đều làm méo module QR -> máy quét
     dễ đọc sai. Cách xử lý đúng cho độ nét là tăng độ phân giải NGUỒN khi sinh QR (xem
     QRCode.toDataURL bên trên) rồi để trình duyệt nội suy. */
  .qr-block img { width: 100%; height: 100%; object-fit: contain; }
  /* QR chế độ: 12.5mm trong ô cao 13.37mm (trừ 2 viền còn 12.62mm) chỉ hở 0.06mm mỗi bên —
     in ra dính sát vạch kẻ. Co còn 11.6mm để hở đều ~0.5mm trên/dưới, canh giữa do .box lo. */
  .qr-block-inline { display: flex; align-items: center; justify-content: center; }
  .qr-block-inline img { width: 11.6mm; height: 11.6mm; }
  /* QR góc trên bên phải (chế độ PROCESS/EXTRA/FB). Bản 2026-07-30 phải neo trên (flex-start)
     + padding trên dày 0.9mm để đẩy QR 12.5mm rời khỏi viền; nay QR đã co còn 11.6mm và .box
     tự canh giữa 2 chiều nên padding trả về đều 4 cạnh, khe hở trên/dưới bằng nhau. */
  .box.mode-qr-cell { padding: 0.3mm; }
  .placeholder { color: #999; font-style: italic; }
  .footnote { margin-top: 3mm; font-size: 2.3mm; color: #666; }
  /* KHÔNG có @page thì trình duyệt in theo khổ giấy mặc định đang chọn sẵn trong driver
     máy in (thường A4/Letter) — .slip vẫn đúng 70x100mm THẬT (đo đúng, không bị co giãn)
     nhưng chỉ chiếm 1 góc nhỏ giữa tờ giấy to, nhìn như "tem bé xíu" dù kích thước tuyệt
     đối đã đúng. Khai báo khổ trang = đúng khổ tem để Chrome/Edge tự yêu cầu đổi khổ giấy
     khi in (bug thật 2026-07-30, ảnh chụp tem in ra chỉ chiếm góc trên tờ giấy lớn).
     margin:0 để không cộng thêm lề trắng ngoài viền .slip — lề thật do chính .slip tự chừa
     (MARGIN_MM), như vậy bản vẽ luôn nhỏ hơn vùng in được và trình duyệt không co trang. */
  @page {
    size: 70mm 100mm;
    margin: 0;
  }
  @media print {
    body { padding: 0; display: block; }
    /* KHÔNG dùng transform: scale() ở đây. Bản 2026-07-31 từng thêm scale(0.95) để chừa lề
       (tránh mất viền trên/trái) nhưng gây lỗi NẶNG hơn: người dùng báo "đường thẳng in ra
       bị đứt đứt". Lý do: scale nhân vào cả ĐỘ DÀY nét — viền 0.25mm (đúng 2 dot ở 203dpi)
       thành 0.2375mm = 1.9 dot, không tròn dot; khi rasterize, mỗi đoạn dọc theo đường bị
       làm tròn khi 1 dot khi 2 dot -> nét đứt quãng. Giữ tỉ lệ 1:1 để mọi nét khai báo theo
       mm luôn tròn dot và in ra liền mạch. Việc chừa lề mép giấy phải xử lý bằng cách khác
       (dịch/chỉnh máy in), KHÔNG bằng scale. */
    .slip { zoom: 1; }
    .footnote { display: none; }
  }
</style>
</head>
<body>
  <div class="slip">
    ${boxDot(0, 0, 206, 112, '<span class="label-sm">DF_WEIGHING_SLIP</span>')}
    ${boxDot(206, 0, 391, 112, `<span class="zone${routing.d1Zone ? '' : ' placeholder'}">${routing.d1Zone || '—'}</span>`)}
    ${boxDot(391, 0, 560, 112, `<div class="qr-block-inline">${modeQrDataUrl ? `<img src="${modeQrDataUrl}" alt="QR mode" />` : ''}</div>`, false, 'mode-qr-cell')}

    ${boxDot(0, 114, 278, 200, `<span class="big">${data.color}</span><span class="big code-line">${data.productCode}</span>`)}
    ${boxDot(293, 114, 391, 200, `<span class="big">${data.machineCode}</span>`)}
    ${boxDot(391, 114, 498, 200, `<span class="big">${data.tankCode || '-'}</span>`)}
    ${boxDot(498, 114, 560, 200, `<span class="med">${data.levelCode || '-'}</span>`)}

    ${tableGridHtml}
    ${tableCellsHtml}

    <div style="position:absolute; left:${mmD(0)}mm; top:${mmD(titleTop)}mm; width:${mmD(278)}mm;" class="title">QR CAN THUOC NHUOM</div>
    <div style="position:absolute; left:${mmD(293)}mm; top:${mmD(titleTop)}mm; width:${mmD(560 - 293)}mm;" class="title">QR CAN CHAT TRO</div>

    <div class="qr-block" style="left:${mmD(0)}mm; top:${mmD(qrTop)}mm; width:${mmD(278)}mm; height:${mmD(qrBottom - qrTop)}mm;">
      ${dyeQrDataUrl ? `<img src="${dyeQrDataUrl}" alt="QR DYE" />` : ''}
    </div>
    <div class="qr-block" style="left:${mmD(293)}mm; top:${mmD(qrTop)}mm; width:${mmD(560 - 293)}mm; height:${mmD(qrBottom - qrTop)}mm;">
      ${chemQrDataUrl ? `<img src="${chemQrDataUrl}" alt="QR CHEM" />` : ''}
    </div>

    <div style="position:absolute; left:${mmD(0)}mm; top:${mmD(routeY)}mm; width:${mmD(560)}mm;" class="med">
      ${routing.b24Route}
    </div>
  </div>

  <p class="footnote">
    Lô: ${data.batchId || ''} — In qua trình duyệt (không qua TSPL/Local Agent), bố cục đo đúng từ sheet DF_WEIGHING_SLIP gốc. Khu vực kho/QR chế độ tự tính lại tại đây (khớp backend), có thể lệch nếu cấu hình routing (feature flag) thay đổi giữa lúc in và lúc xác nhận chính thức.
  </p>

  <script>
    window.onload = function () {
      // In xong (bấm In hoặc Hủy trong hộp thoại) thì tự đóng luôn cửa sổ này. Dùng
      // window.onafterprint không ăn thua trong thực tế (người dùng xác nhận cửa sổ vẫn
      // còn "about:blank" sau khi in, 2026-07-30) — chuyển sang cách chắc chắn hơn:
      // window.print() CHẶN (blocking) tới khi hộp thoại in đóng lại trên Chrome/Edge
      // (2 trình duyệt Windows thực tế đang dùng), nên gọi window.close() ngay dòng kế
      // tiếp là chạy SAU khi người dùng đã bấm In/Hủy, không cần chờ sự kiện afterprint.
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
