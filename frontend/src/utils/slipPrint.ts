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
  /* Trên màn hình tem thật khá bé — phóng to cho dễ soát. Chỉ áp SAU khi đã đo xong (class
     .ready), vì zoom làm sai số đo offsetWidth của bảng.
     PHẢI nằm trong @media screen: zoom lúc IN là buộc Chrome rasterize thành ảnh, xem ghi chú
     ở khối @media print bên dưới. */
  @media screen {
    body { padding: 6mm; display: flex; justify-content: center; }
    body.ready .df-slip-page { zoom: 2.4; }
    .df-slip-page { outline: 0.2mm dashed #bbb; outline-offset: 2mm; }
  }
  @media print {
    body { padding: 0; }
    .df-slip-page { outline: none; }
  }
  /* KHÔNG thêm zoom/transform/filter/opacity nào vào .df-slip hay tổ tiên của nó trong @media
     print. Bất cứ thứ nào trong số đó buộc Chrome rasterize vùng đó thành ẢNH rồi mới gửi xuống
     driver, và driver hạ ảnh về 203dpi bằng cách dither -> viền và chữ ra lưới chấm (ảnh chụp tem
     thật 07/08/2026). Để nguyên là chữ thì driver tự vẽ glyph ở 203dpi, nét liền. Xem SCRIPT_TU_IN. */
</style>
</head>
<body>
${slipHtml}
${autoPrint ? SCRIPT_TU_IN : ''}
</body>
</html>`;
}

/**
 * Co phiếu cho vừa MỘT TRANG bằng cách CHỌN CỠ CHỮ, rồi in CHỮ THẬT.
 *
 * ================= VÌ SAO KHÔNG ĐƯỢC DÙNG ẢNH, `zoom`, HAY `transform` =================
 * Ảnh in ra ngày 07/08/2026 (ảnh chụp tem thật) cho thấy CẢ viền ô lẫn nét chữ đều vỡ thành lưới
 * chấm đều nhau. Đó là driver máy in **dither một tấm ảnh xám**, không phải khử răng cưa.
 *
 * Đường đi sinh ra nó: bất cứ thứ gì Chrome phải rasterize (canvas, phần tử có `zoom`, phần tử có
 * `transform`) đều được gửi xuống driver dưới dạng ẢNH BITMAP ở độ phân giải của Chrome, rồi driver
 * đổi kích thước ảnh đó về 203dpi của máy in. Qua hai lần đổi kích thước, đen đặc thành xám, mà máy
 * in nhiệt 1 bit không in được xám nên nó dither ra lưới chấm. **Tô ảnh 1-bit cũng không cứu được**
 * — đã thử (bản canvas 07/08/2026, xem session-log mục 124/128): ảnh vào pipeline đó là hỏng, dù
 * trước khi vào nó sạch tới đâu.
 *
 * Bản VBA in nét vì Excel gửi xuống driver **CHỮ**, và driver tự vẽ glyph thẳng ở 203dpi — không
 * có tấm ảnh nào để mà dither. Nên ở đây phải làm y hệt: để nguyên bảng HTML là chữ thật, chỉ
 * **chọn cỡ chữ** sao cho bảng vừa trang, và tuyệt đối không đụng vào `zoom`/`transform`/canvas.
 *
 * Đánh đổi đã biết và chấp nhận: KHÔNG XOAY được nữa (xoay bắt buộc phải qua `transform`, tức quay
 * lại đường ảnh). Muốn phiếu nằm đúng chiều thì phải khai báo `SLIP_PAGE_MM` ĐÚNG chiều con tem
 * đang nạp — xem `utils/weighSlip.ts`.
 *
 * Phải đo tại trình duyệt chứ không tính sẵn được lúc dựng chuỗi: bề rộng cột phụ thuộc bề rộng
 * thật của từng chữ Calibri sau khi `.Columns.AutoFit`, mà bên PHP không có cách nào biết.
 */
const SCRIPT_TU_IN = `<script>
(function () {
  // 1 CSS px = 1/96 inch, ở cả màn hình lẫn lúc in — nên đo bằng px trên màn hình rồi dùng thẳng
  // cho trang in là hợp lệ, không phải quy đổi gì.
  var PX_MM = 96 / 25.4;

  // Cỡ chữ nhỏ nhất còn chấp nhận, tính bằng dot của máy in 203dpi (8 dot/mm). Dưới 9 dot thì chữ
  // không còn hình dạng để mà đọc; thà để tràn và báo lỗi còn hơn in ra một tờ không ai đọc được.
  var TOI_THIEU_DOT = 9;

  function docSo(el, ten) {
    var v = parseFloat(el.getAttribute(ten));
    return isFinite(v) ? v : 0;
  }

  function toPhieu() {
    var page = document.querySelector('.df-slip-page');
    var table = document.querySelector('.df-slip');
    if (!page || !table) return;

    var lem = docSo(page, 'data-m');
    var rongPx = (docSo(page, 'data-w') - 2 * lem) * PX_MM;
    var caoPx = (docSo(page, 'data-h') - 2 * lem) * PX_MM;
    if (!(rongPx > 0 && caoPx > 0)) return;

    var o = table.querySelectorAll('td'), i;

    // BỎ CHIỀU CAO DÒNG CỐ ĐỊNH của payload (5.3mm — chiều cao dòng mặc định của Excel). 19 dòng
    // x 5.3mm = 100mm, cao gấp gần ba lần con tem, nên chính nó mới là thứ ép cỡ chữ xuống chứ
    // không phải bề rộng. Cho dòng co về đúng hộp chữ thì cỡ chữ chọn được lớn hơn hẳn.
    for (i = 0; i < o.length; i++) {
      o[i].style.height = 'auto';
      o[i].style.lineHeight = '1.12';
      o[i].style.padding = '0 0.6mm';
      // 0.25mm = ĐÚNG 2 dot ở 203dpi. Viền mảnh hơn 1 dot bị driver làm thành nét đứt quãng; số
      // tròn dot thì ra nét đen liền. KHÔNG để 1px (= 2.1 dot, lẻ dot).
      o[i].style.borderWidth = '0.25mm';
    }

    // Dò cỡ chữ từ to xuống: bề rộng chữ không tỉ lệ tuyến tính với cỡ chữ (hinting làm tròn theo
    // pixel) nên không giải được bằng công thức, phải thử và ĐO.
    var toiThieuPx = TOI_THIEU_DOT / 8 * PX_MM;
    var chon = 0;
    for (var px = 20; px >= toiThieuPx; px -= 0.5) {
      table.style.fontSize = px + 'px';
      if (table.offsetWidth <= rongPx && table.offsetHeight <= caoPx) { chon = px; break; }
    }

    if (!chon) {
      // Không cỡ nào vừa. Giữ cỡ nhỏ nhất và NÓI RA — im lặng in một tờ bị xén mất cột là kiểu
      // hỏng tệ nhất: thợ dán nhầm rồi mới phát hiện.
      table.style.fontSize = toiThieuPx + 'px';
      console.error('Phieu khong vua kho tem: can ' + Math.ceil(table.offsetWidth / PX_MM) + 'x'
        + Math.ceil(table.offsetHeight / PX_MM) + 'mm, tem chi co '
        + Math.floor(rongPx / PX_MM) + 'x' + Math.floor(caoPx / PX_MM) + 'mm.');
    }
  }

  function chay() {
    try { toPhieu(); } catch (e) { console.error('Khong chon duoc co chu, in nguyen ban:', e); }
    document.body.classList.add('ready');
    // window.print() CHẶN tới khi hộp thoại in đóng trên Chrome/Edge (xác nhận 30/07/2026), nên
    // gọi window.close() ngay sau là đủ, không cần chờ sự kiện afterprint.
    window.print();
    // Trong iframe thì close() vô tác dụng (chỉ cửa sổ do script mở mới tự đóng được) và Chrome
    // ghi một cảnh báo ra console — chỉ gọi khi đúng là cửa sổ riêng.
    if (!window.frameElement) window.close();
  }

  // Phải chờ font nạp xong mới đo được bề rộng chữ: đo lúc font dự phòng còn đang dùng thì cỡ chữ
  // chọn ra sai.
  window.onload = function () {
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(chay, chay);
    else chay();
  };
})();
<\/script>`;

/**
 * In phiếu bằng một IFRAME ẨN ngay trong trang — KHÔNG mở cửa sổ mới.
 *
 * Vì sao bỏ `window.open` (07/08/2026): Chrome/Edge **thoát chế độ toàn màn hình (F11)** của cửa sổ
 * hiện tại ngay khi trang mở một cửa sổ trình duyệt mới — cửa sổ mới phải hiện ra được thì cửa sổ
 * đang phủ kín màn hình buộc phải thu về dạng thường. Máy trạm cân chạy F11 suốt ca, nên mỗi lần
 * bấm SAVE là thợ mất toàn màn hình và phải bấm F11 lại. Iframe không tạo cửa sổ nào nên không
 * đụng tới trạng thái đó. (Cùng lý do đã bỏ `confirm`/`alert` sang `HopThoaiVba`.)
 *
 * Được lợi kèm theo: hết ràng buộc "user activation". Iframe tạo được sau `await`, nên chỗ gọi
 * không phải mở sẵn cửa sổ rồi truyền vào nữa, và cũng không còn cảnh bị chặn popup.
 *
 * `window.print()` bên trong iframe chỉ in NỘI DUNG IFRAME, không in trang cha.
 */
let khungIn: HTMLIFrameElement | null = null;

export function inPhieuTrongTrang(slipHtml: string): void {
  // Dùng lại MỘT khung duy nhất thay vì tạo rồi xoá mỗi lần in: xoá đúng lúc thì phải rình xem hộp
  // thoại in đã đóng chưa, mà không có sự kiện nào báo tin cậy được. Giữ lại một khung ẩn thì lần
  // in sau chỉ ghi đè nội dung.
  if (!khungIn || !khungIn.isConnected) {
    khungIn = document.createElement('iframe');
    khungIn.setAttribute('aria-hidden', 'true');
    khungIn.title = 'Phieu can';
    // Đẩy ra ngoài màn hình chứ KHÔNG dùng `display:none`: phần tử display:none không được bố cục,
    // mà đoạn script tự in phải đo `offsetWidth` của bảng mới chọn được cỡ chữ. Khung để rộng hơn
    // con tem nhiều để bảng không bị ngắt dòng oan trong lúc thử các cỡ chữ lớn.
    khungIn.style.cssText = 'position:fixed;left:-10000px;top:0;width:200mm;height:300mm;border:0;';
    document.body.appendChild(khungIn);
  }

  const doc = khungIn.contentDocument;
  if (!doc) return;
  doc.open();
  doc.write(wrapSlipDocument(slipHtml, true));
  doc.close();
}

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
