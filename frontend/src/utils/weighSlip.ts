/**
 * Dựng NỘI DUNG phiếu cân NGAY TẠI TRÌNH DUYỆT.
 *
 * Đây là bản port của `WeighingJobController::buildSlipHtml` (PHP). CỐ Ý nhân bản logic: khi mất
 * mạng, mẻ cân nằm lại hàng đợi chờ đẩy lên (xem `services/saveQueue.ts`) nhưng thợ VẪN PHẢI IN
 * được phiếu ngay để dán theo hàng — mà lúc đó không hỏi server được câu nào.
 *
 * Server vẫn là nguồn sự thật: lưu thành công thì phiếu in ra lấy từ response của server, bản này
 * chỉ dùng cho đúng đường mất mạng. **Đổi bố cục phiếu thì phải sửa CẢ HAI chỗ** — có script đối
 * chiếu hai bản: `frontend/scripts/check-weigh-slip.mjs`.
 *
 * ============================ NGUỒN BỐ CỤC (06/08/2026) ============================
 * Yêu cầu người dùng: "tem in ra giống form VBA 100%". Bố cục dưới đây KHÔNG phải thiết kế riêng
 * cho web nữa mà là bản dựng lại nguyên văn `scaleform.btnPrint_Click` trong workbook đang chạy
 * ngoài xưởng `4.semiauto-small scale - delta-stable-final_DF026-027.xlsm` (giải nén
 * `xl/vbaProject.bin`, module `scaleform`). VBA không in TSPL: nó `Sheets.Add` một sheet mới, đổ
 * dữ liệu vào ô rồi `ws.PrintOut ActivePrinter:="TSC TTP-224 Pro"`. Vì thế phiếu ở đây là HTML
 * dựng lại đúng cái sheet đó, KHÔNG phải chuỗi TSPL — TSPL không có viền ô, không có Calibri,
 * không có in đậm theo dòng.
 *
 * Nguyên văn phần dựng sheet của VBA (rút gọn phần lặp):
 *
 *     ws.Cells(r,1) = "DF_WEIGHING_SLIP"        : r = r + 2      ' r=1, nhảy qua dòng 2
 *     ws.Cells(r,1) = "COLOR:"   : ws.Cells(r,2) = txt_COLOR     ' r=3
 *     ws.Cells(r,1) = "CODE:"    : ws.Cells(r,2) = txt_CODE      ' r=4
 *     ws.Cells(r,1) = "MACHINE:" : ws.Cells(r,2) = txt_MACHINE   ' r=5
 *     ws.Cells(r,1) = "LEVEL:"   : ws.Cells(r,2) = txt_LV        ' r=6, r = r + 2
 *     ws.Cells(r,1..5) = RACK / DYE CODE / WEIGHT / PROCESS / STATUS  ' r=8
 *     For i = 1 To 9 ... rack / dye / weight / process / GetProcessStatus  ' r=9..17
 *     r = r + 1 : ws.Cells(r,1) = "Print time:" : ws.Cells(r,2) = Format(Now,"dd/mm/yyyy HH:nn:ss")
 *     With ws.Range("A1:E" & r): .Font.name="Calibri": .Font.size=12: .Columns.AutoFit
 *                                .Borders.LineStyle = xlContinuous
 *     ws.Range("A1:E1").Font.Bold = True
 *     ws.Range("A7:E7").Font.Bold = True
 *     PageSetup: xlPortrait, FitToPagesWide/Tall = 1, 4 lề = 0.2cm
 *
 * Ba chi tiết dễ tưởng là lỗi của bản web nhưng chính là bản gốc, ĐỪNG "sửa" nếu không có yêu cầu:
 *   1. Dòng in đậm là dòng 1 và dòng **7** (dòng 7 trống trơn) — dòng tiêu đề bảng ở dòng 8
 *      KHÔNG in đậm. VBA tính nhầm chỉ số nhưng phiếu thật ngoài xưởng đang ra như thế.
 *   2. Cột WEIGHT là số cân MỤC TIÊU (`txt_weight` do QR nạp vào), cột PROCESS là số cân THỰC TẾ
 *      (`txt_process` — ô mà `Mod_UI_processcolor.CheckRange` tô màu theo dung sai). Đọc tên cột
 *      theo nghĩa thông thường là hiểu ngược.
 *   3. Luôn in đủ 9 dòng dữ liệu kể cả khi đơn ít vật tư hơn (`For i = 1 To 9`).
 *
 * Bố cục này dùng CHUNG cho cả `/weighing-station-v2` (cân nhỏ) lẫn `/weighing-station-large`
 * (cân to) vì `btnPrint_Click` của workbook cân to
 * `5.Semiauto- lockmove SEND OVER6 - delta-stable-final-221.xlsm` **giống hệt** workbook cân nhỏ
 * — đã đối chiếu nguyên văn cả hai module `scaleform`, kể cả khổ giấy trong
 * `printerSettings1.bin` (cùng 53.3 x 101.6mm ở thời điểm đối chiếu). Không có gì để tách riêng.
 *
 * Chỗ DUY NHẤT cố ý không chép y nguyên (đã báo người dùng): dòng CÂN TAY vẫn in "MANUAL" thay vì
 * "REJECTED". Cân tay là luồng chỉ có ở web (VBA không có), nên không có tờ phiếu VBA nào để mà
 * lệch — trong khi in "REJECTED" lên cả mẻ cân tay đạt là đẩy một tín hiệu sai vào tay thợ. Mọi
 * trường hợp KHÁC đều đi đúng `GetProcessStatus`, xem `statusInRaPhieu()` bên dưới.
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
  /** ACCEPTED | REJECTED | PENDING | MANUAL — xem processStatus() bên dưới. */
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
 * Đây cũng đúng cách lưới 9 dòng trên màn hình hiển thị (`VbaRackGrid.formatNum`), nên tờ giấy và
 * màn hình không lệch nhau con số nào.
 *
 * Không dùng `toLocaleString` vì kết quả đổi theo ngôn ngữ máy trạm; phiếu in phải giống nhau ở
 * mọi máy và giống hệt bản server dựng.
 */
function numberFormat2(value: number): string {
  const n = Number.isFinite(value) ? value : 0;
  return n.toFixed(2);
}

/** Escape HTML — phải trùng từng ký tự với `htmlspecialchars($s, ENT_QUOTES)` bên PHP. */
function escapeHtml(value: unknown): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function haiChuSo(n: number): string {
  return String(n).padStart(2, '0');
}

/** Đúng định dạng `Carbon::now()->format('d/m/Y H:i:s')` bên PHP và `Format(Now, "dd/mm/yyyy HH:nn:ss")` bên VBA. */
export function nowSlipTimestamp(d = new Date()): string {
  return (
    `${haiChuSo(d.getDate())}/${haiChuSo(d.getMonth() + 1)}/${d.getFullYear()} ` +
    `${haiChuSo(d.getHours())}:${haiChuSo(d.getMinutes())}:${haiChuSo(d.getSeconds())}`
  );
}

/**
 * Khổ giấy phiếu, mm — cuộn tem **60 x 40mm (6cm x 4cm), nằm ngang**, theo yêu cầu người dùng
 * 07/08/2026 cho trạm `/weighing-station-v2`.
 *
 * Trước đó là 53.3 x 101.6mm dọc, lấy từ DEVMODE lưu trong workbook
 * (`xl/printerSettings/printerSettings1.bin`: TSC TTP-244 Pro, dmPaperWidth 533, dmPaperLength
 * 1016). Cuộn tem ngoài xưởng đã đổi nên số ở đây không còn bám theo workbook nữa.
 *
 * `margin` = 0.2cm, đúng 4 lề mà `btnPrint_Click` đặt trong PageSetup.
 *
 * Đây là CHỖ DUY NHẤT phải sửa nếu đổi cuộn tem, và phải sửa cả bản PHP cho khớp.
 *
 * PHẢI khai đúng CHIỀU của con tem đang nạp (rộng/cao không được đảo cho tiện): đường in không
 * còn xoay được nữa. Xoay bắt buộc đi qua `transform`, mà bất cứ `transform`/`zoom`/canvas nào
 * cũng buộc Chrome gửi xuống driver một tấm ẢNH thay vì chữ, và máy in nhiệt 1 bit dither ảnh đó
 * ra lưới chấm — xem ghi chú đầu `utils/slipPrint.ts`.
 */
export const SLIP_PAGE_MM = { width: 60, height: 40, margin: 2 };

/** Số dòng dữ liệu luôn in ra — `For i = 1 To 9` của VBA. */
const SLIP_DATA_ROWS = 9;

/**
 * CSS của phiếu. Dựng lại định dạng mà `btnPrint_Click` áp cho vùng A1:E19:
 *   - `.Font.name = "Calibri"`, `.Font.size = 12`  -> font-family/font-size
 *   - `.Borders.LineStyle = xlContinuous`          -> viền MỌI ô (border-collapse)
 *   - `.Columns.AutoFit`                           -> white-space:nowrap + bảng tự co theo nội dung
 * Chiều cao dòng 5.3mm = 15pt, đúng chiều cao dòng mặc định của Excel (VBA không đụng tới nó).
 *
 * Viền để 0.2mm (~1.6 dot ở 203dpi) chứ không phải 1px: viền mảnh hơn 1 dot bị máy in nhiệt
 * rasterize ra nét đứt quãng — lỗi thật đã gặp 31/07/2026 ở /print-station.
 *
 * Con số này chỉ có tác dụng cho bản XEM TRƯỚC: lúc in thật, `utils/slipPrint.ts` ghi đè thành
 * 0.25mm = đúng 2 dot chẵn. Phiếu không còn bị co nữa nên cỡ viền không đổi theo hệ số co như
 * trước, mà chỉ cần tròn dot.
 */
const SLIP_CSS =
  `@page { size: ${SLIP_PAGE_MM.width}mm ${SLIP_PAGE_MM.height}mm; margin: ${SLIP_PAGE_MM.margin}mm; }\n` +
  'html, body { margin: 0; padding: 0; background: #fff; }\n' +
  '.df-slip-page { font-family: Calibri, Carlito, "Segoe UI", sans-serif; color: #000; }\n' +
  '.df-slip { border-collapse: collapse; font-size: 12pt; }\n' +
  '.df-slip td { border: 0.2mm solid #000; padding: 0 0.8mm; white-space: nowrap;' +
  ' height: 5.3mm; line-height: 5.3mm; }\n' +
  '.df-slip tr.b td { font-weight: 700; }\n';

/**
 * Port của `Mod_print_tsc224.GetProcessStatus` — hàm dựng cột STATUS trên phiếu:
 *
 *     Select Case tb.BackColor
 *         Case RGB(120, 250, 20): "ACCEPTED"
 *         Case Else:              "REJECTED"
 *
 * Nghĩa là **chỉ ô nền XANH mới ACCEPTED**, còn lại — vàng (thiếu), đỏ (vượt), và cả ô TRẮNG của
 * dòng chưa đụng tới — đều ra "REJECTED". Vì `For i = 1 To 9` chạy hết 9 dòng nên tờ phiếu VBA
 * luôn có REJECTED ở những dòng trống; đó là bản gốc, không phải lỗi của bản web.
 *
 * (Bản hàm này trong workbook cân NHỎ so nhầm với `RGB(60,200,100)` — màu mà `CheckRange` không
 * bao giờ gán — nên ở đó mọi dòng đều ra REJECTED kể cả dòng cân đạt. Đó là lỗi legacy đã chốt
 * KHÔNG migrate, xem `.claude/test-architecture.md`; ta dùng bản ĐÃ SỬA của workbook cân to.)
 *
 * MANUAL (cân tay) là ngoại lệ web-only — xem ghi chú đầu file.
 */
function statusInRaPhieu(status: string): string {
  if (status === 'MANUAL') return 'MANUAL';

  return status === 'ACCEPTED' ? 'ACCEPTED' : 'REJECTED';
}

/** Một hàng 5 ô của sheet. `bold` = VBA có `.Font.Bold = True` cho cả hàng đó. */
function slipRow(cells: string[], bold = false): string {
  const tds = cells.map((c) => `<td>${escapeHtml(c)}</td>`).join('');
  return `<tr${bold ? ' class="b"' : ''}>${tds}</tr>`;
}

export function buildSlipHtml(header: SlipHeader, items: SlipItem[]): string {
  const color = String(header.color ?? '');
  const productCode = String(header.product_code ?? '');
  const machineCode = String(header.machine_code ?? 'N/A');
  const levelCode = String(header.level_code ?? '');
  const printedAt = String(header.printed_at ?? nowSlipTimestamp());

  const rows: string[] = [];

  // r1..r8 — phần đầu phiếu, đúng thứ tự điền ô của btnPrint_Click.
  rows.push(slipRow(['DF_WEIGHING_SLIP', '', '', '', ''], true));
  rows.push(slipRow(['', '', '', '', '']));
  rows.push(slipRow(['COLOR:', color, '', '', '']));
  rows.push(slipRow(['CODE:', productCode, '', '', '']));
  rows.push(slipRow(['MACHINE:', machineCode, '', '', '']));
  rows.push(slipRow(['LEVEL:', levelCode, '', '', '']));
  // Dòng 7 trống nhưng IN ĐẬM — đúng `ws.Range("A7:E7").Font.Bold = True` của VBA, xem ghi chú
  // đầu file: chính vì dòng này mà dòng tiêu đề bảng bên dưới lại KHÔNG đậm.
  rows.push(slipRow(['', '', '', '', ''], true));
  rows.push(slipRow(['RACK', 'DYE CODE', 'WEIGHT', 'PROCESS', 'STATUS']));

  // r9..r17 — luôn đủ 9 dòng. Đơn nhiều vật tư hơn 9 không tồn tại (lưới cân chặn ở 9) nhưng vẫn
  // in hết thay vì cắt bớt: mất một dòng trên phiếu là mất dấu vết của một lần cân.
  const soDong = Math.max(SLIP_DATA_ROWS, items.length);
  for (let i = 0; i < soDong; i++) {
    const item = items[i];
    if (!item) {
      // Dòng trống VẪN có STATUS = REJECTED: ô txt_process của nó nền trắng, mà GetProcessStatus
      // trả REJECTED cho mọi nền không phải xanh. Đây là bản gốc, xem statusInRaPhieu().
      rows.push(slipRow(['', '', '', '', 'REJECTED']));
      continue;
    }

    const planned = item.planned_weight;
    const actual = item.actual_weight;

    rows.push(
      slipRow([
        String(item.rack_code ?? ''),
        String(item.material_code ?? ''),
        planned === null || planned === undefined || planned === '' ? '' : numberFormat2(Number(planned)),
        actual === null || actual === undefined || actual === '' ? '' : numberFormat2(Number(actual)),
        statusInRaPhieu(String(item.process_status ?? '')),
      ])
    );
  }

  // r18 trống, r19 mốc giờ in — VBA để "Print time:" ở CUỐI phiếu.
  rows.push(slipRow(['', '', '', '', '']));
  rows.push(slipRow(['Print time:', printedAt, '', '', '']));

  return (
    `<div class="df-slip-page" data-w="${SLIP_PAGE_MM.width}" data-h="${SLIP_PAGE_MM.height}"` +
    ` data-m="${SLIP_PAGE_MM.margin}">\n` +
    `<style>\n${SLIP_CSS}</style>\n` +
    '<table class="df-slip">\n' +
    rows.join('\n') +
    '\n</table>\n</div>'
  );
}
