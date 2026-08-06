/**
 * In PHIẾU CÂN (payload HTML do `utils/weighSlip.ts` / `WeighingJobController::buildSlipHtml` dựng)
 * qua hộp thoại in của trình duyệt.
 *
 * Vì sao không dùng chung `utils/tsplPrint.ts`: từ 06/08/2026 phiếu cân được dựng lại 1:1 theo
 * `scaleform.btnPrint_Click` của workbook `4.semiauto-small scale ... DF026-027.xlsm`, mà VBA in
 * phiếu bằng cách đổ dữ liệu ra một sheet Excel rồi `PrintOut` — có viền ô, có Calibri, có in đậm
 * theo dòng. TSPL không diễn tả được cả ba thứ đó. `tsplPrint.ts` VẪN DÙNG cho tem vật tư
 * (MaterialLabel) và các tem khác — hai đường in cố ý khác nhau.
 *
 * `payload` là MẢNH HTML tự mang CSS của nó (`<style>` nằm ngay trong mảnh), không phải cả trang:
 * nhờ vậy `print_jobs.label_payload` lưu được nguyên nội dung phiếu mà không kèm đoạn script tự
 * in — bản xem trước ở Lịch sử in (`LabelPreview.vue`) nhúng đúng mảnh đó vào iframe và KHÔNG bị
 * bật hộp thoại in.
 */

/**
 * Bọc mảnh phiếu thành một trang HTML hoàn chỉnh.
 *
 * @param autoPrint Nhúng đoạn script tự co giãn + gọi `window.print()`. Chỉ bật cho cửa sổ in;
 *   bản xem trước phải để `false`.
 */
export function wrapSlipDocument(slipHtml: string, autoPrint: boolean): string {
  return `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Phieu can</title>
<style>
  /* Máy in tem là máy in NHIỆT chỉ có đen/trắng: mọi sắc xám đều bị dither thành lưới chấm thưa
     nên nhìn ra mờ. Ép màu đúng như khai báo và tắt khử răng cưa để chữ/viền ra đen ĐẶC. */
  html, body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    -webkit-font-smoothing: none;
    text-rendering: optimizeSpeed;
    margin: 0;
    background: #fff;
  }
  /* Trên màn hình tem thật khá bé — phóng to cho dễ soát, lúc in luôn trả về 1:1. Dùng zoom chứ
     không phải transform để layout giãn ra thật. Chỉ áp SAU khi đã đo xong (class .ready), vì
     zoom làm sai số đo offsetWidth của bảng. */
  @media screen {
    body { padding: 6mm; display: flex; justify-content: center; }
    body.ready .df-slip-page { zoom: 2.4; }
    .df-slip-page { outline: 0.2mm dashed #bbb; outline-offset: 2mm; }
  }
  @media print {
    body { padding: 0; }
    .df-slip-page { outline: none; }
  }
</style>
</head>
<body>
${slipHtml}
${autoPrint ? SCRIPT_TU_IN : ''}
</body>
</html>`;
}

/**
 * XOAY 90° SANG PHẢI rồi co phiếu cho vừa ĐÚNG MỘT TRANG — bản dựng lại của
 * `FitToPagesWide = 1` + `FitToPagesTall = 1` trong PageSetup của VBA, cộng phần xoay.
 *
 * Vì sao phải xoay (yêu cầu 06/08/2026, kèm số đo): bảng phiếu 5 cột tự nhiên rộng **127.5mm**,
 * cao **106.1mm** — nằm NGANG. Con tem thì 53.3 × 101.6mm, tức DỌC. Ép thẳng vào là phiếu co còn
 * 39%, chữ 12pt xuống 4.6pt. Xoay 90° rồi mới co thì hệ số lên 46% (dùng hết chiều dài 101.6mm
 * cho bề rộng bảng) và chữ ra 5.6pt.
 *
 * Xoay ở ĐÂY chứ không sửa payload là có chủ đích: `print_jobs.label_payload` phải khớp TỪNG KÝ
 * TỰ giữa `utils/weighSlip.ts` và `WeighingJobController::buildSlipHtml` (script đối soát
 * `frontend/scripts/check-weigh-slip.mjs`). Đụng vào payload là phải sửa cả PHP và giữ đồng bộ
 * hai bản mãi mãi; xoay ở đường in thì chỉ một chỗ.
 *
 * Phải đo tại trình duyệt chứ không tính sẵn được lúc dựng chuỗi: bề rộng bảng phụ thuộc bề rộng
 * thật của từng chữ Calibri sau khi `.Columns.AutoFit`, mà bên PHP không có cách nào biết.
 *
 * `Math.min(1, ...)` vì Excel chỉ THU NHỎ để vừa trang, không bao giờ phóng quá 100%.
 */
const SCRIPT_TU_IN = `<script>
window.onload = function () {
  var page = document.querySelector('.df-slip-page');
  var table = document.querySelector('.df-slip');
  if (page && table) {
    var MM_TO_PX = 96 / 25.4;
    var lem = parseFloat(page.getAttribute('data-m')) || 0;
    var rongMm = parseFloat(page.getAttribute('data-w')) || 0;
    var caoMm = parseFloat(page.getAttribute('data-h')) || 0;
    var rongPx = (rongMm - 2 * lem) * MM_TO_PX;
    var caoPx = (caoMm - 2 * lem) * MM_TO_PX;
    // Đã xoay 90 độ nên BỀ RỘNG tem chặn CHIỀU CAO bảng và ngược lại — đảo hai vế so với bản
    // không xoay, quên đảo là phiếu vẫn tràn ra ngoài tem.
    var w0 = table.offsetWidth, h0 = table.offsetHeight;
    var k = Math.min(1, rongPx / h0, caoPx / w0);
    if (isFinite(k) && k > 0) {
      table.style.transformOrigin = 'top left';
      // Thứ tự đọc từ PHẢI sang TRÁI: co k -> xoay 90 độ theo chiều kim đồng hồ -> kéo lại vào
      // trong khung. Xoay quanh góc trên-trái đẩy toàn bộ bảng sang bên trái trục (x' = -y), nên
      // phải dịch phải đúng bằng chiều cao bảng sau khi co.
      table.style.transform = 'translateX(' + (h0 * k) + 'px) rotate(90deg) scale(' + k + ')';
      // Hộp bố cục của bảng KHÔNG co/xoay theo transform, nên phải ép lại kích thước của khung
      // trang — nếu không, phần thừa vẫn tính là nội dung và trình duyệt đẩy sang trang 2.
      page.style.width = h0 * k + 'px';
      page.style.height = w0 * k + 'px';
      page.style.overflow = 'hidden';

      // Nét viền 0.2mm sau khi co k=0.46 chỉ còn 0.74 dot ở 203dpi -> máy in nhiệt rasterize ra
      // nét ĐỨT QUÃNG (lỗi thật đã gặp 31/07/2026 ở /print-station). Excel không bị vậy vì GDI
      // không bao giờ vẽ nét mảnh hơn 1 pixel. Bù lại đúng bằng cách đó: ép viền trước khi co sao
      // cho sau khi co vẫn >= 1 dot (0.125mm).
      var toiThieu = 0.125 / k;
      if (toiThieu > 0.2) {
        var o = document.querySelectorAll('.df-slip td');
        for (var i = 0; i < o.length; i++) o[i].style.borderWidth = toiThieu + 'mm';
      }
    }
  }
  document.body.classList.add('ready');
  // window.print() CHẶN tới khi hộp thoại in đóng trên Chrome/Edge (xác nhận 30/07/2026), nên
  // gọi window.close() ngay sau là đủ, không cần chờ sự kiện afterprint.
  window.print();
  window.close();
};
<\/script>`;

/**
 * Lọc lấy đúng một `Window` thật, trả `null` cho mọi thứ khác.
 *
 * Sinh ra từ một lỗi thật (06/08/2026): nút PRINT của `/weighing-station-v2` viết
 * `@click="printSlip"` KHÔNG NGOẶC, nên Vue truyền `PointerEvent` vào tham số `preOpened`. Sự
 * kiện đó truthy, `preOpened ?? window.open(...)` vì thế không bao giờ mở cửa sổ, rồi
 * `win.document.write(...)` ném TypeError ngay trong handler — người dùng bấm PRINT thấy **không
 * có phản ứng gì**. Cửa sổ in chưa kịp mở nên cũng chẳng có gì để nhìn mà đoán.
 *
 * Chỉ sửa template là hết lỗi lần này, nhưng nút nào gọi kiểu đó cũng dính lại y hệt; kiểm ở đây
 * thì cả lớp lỗi biến mất và tệ nhất cũng chỉ là mở thừa một cửa sổ.
 */
export function cuaSoThat(x: unknown): Window | null {
  return x && typeof x === 'object' && typeof (x as Window).document === 'object' ? (x as Window) : null;
}

/**
 * @param win Cửa sổ đã mở SẴN bằng window.open() đồng bộ ngay lúc bấm nút — bắt buộc, vì
 *   Chrome/Edge chặn popup mở sau await (mất "user gesture" gắn với cú click).
 */
export async function printSlipHtml(slipHtml: string, win: Window): Promise<void> {
  win.document.open();
  win.document.write(wrapSlipDocument(slipHtml, true));
  win.document.close();
}
