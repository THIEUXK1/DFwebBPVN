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
 * Co phiếu cho vừa ĐÚNG MỘT TRANG — bản dựng lại của `FitToPagesWide = 1` +
 * `FitToPagesTall = 1` trong PageSetup của VBA — và XOAY 90° SANG PHẢI **nếu xoay ra chữ to hơn**.
 *
 * Bảng phiếu 5 cột nằm NGANG (tự nhiên rộng ~127.5mm, cao ~106.1mm trước khi bỏ chiều cao dòng cố
 * định), nên hướng nào có lợi phụ thuộc con tem đang nạp:
 *   - Tem DỌC (53.3 × 101.6mm, cuộn cũ): ép thẳng thì phiếu co còn 39%, chữ 12pt xuống 4.6pt;
 *     xoay rồi mới co thì lên 46% và chữ ra 5.6pt — xoay có lợi (đo thật 06/08/2026).
 *   - Tem NGANG (60 × 40mm, cuộn hiện tại): bảng đã cùng hướng với tem, xoay chỉ làm chữ nhỏ đi.
 * Vì thế KHÔNG hardcode hướng: đo cả hai rồi lấy hệ số co lớn hơn. Đổi cuộn tem chỉ cần sửa
 * `SLIP_PAGE_MM` (và hằng tương ứng bên PHP), không phải sờ vào đây.
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
    var o = document.querySelectorAll('.df-slip td'), i;

    // (1) BỎ CHIỀU CAO DÒNG CỐ ĐỊNH. Payload đặt 5.3mm/dòng (chiều cao dòng mặc định của Excel),
    // 19 dòng thành 106mm — mà bề rộng tem chỉ 49.3mm, nên chính CHIỀU CAO bảng mới là thứ ép hệ
    // số co xuống, không phải bề rộng. Cho dòng co về đúng hộp chữ thì bảng thấp lại, hệ số co
    // tăng, tức CHỮ IN RA TO HƠN dù cỡ chữ khai báo không đổi.
    for (i = 0; i < o.length; i++) { o[i].style.height = 'auto'; o[i].style.lineHeight = '1.08'; }

    // Khi xoay 90 độ thì BỀ RỘNG tem chặn CHIỀU CAO bảng và ngược lại — đảo hai vế so với bản
    // không xoay, quên đảo là phiếu vẫn tràn ra ngoài tem.
    var rongBang = table.offsetWidth, caoBang = table.offsetHeight;
    var kThang = Math.min(rongPx / rongBang, caoPx / caoBang);
    var kXoay = Math.min(rongPx / caoBang, caoPx / rongBang);
    var xoay = kXoay > kThang;

    var k = Math.min(1, xoay ? kXoay : kThang);
    if (isFinite(k) && k > 0) {
      // (2) THU NHỎ BẰNG zoom, KHÔNG PHẢI transform: scale(). Chrome rasterize lớp có transform
      // ở độ phân giải MÀN HÌNH rồi mới phóng lên độ phân giải máy in -> chữ nhỏ ra rỗ/vỡ đúng
      // như đang thấy. zoom thì bố cục được tính lại và chữ vẽ thẳng ở cỡ đã co, nên nét sạch.
      // transform CHỈ còn lo phần xoay.
      //
      // Nhưng zoom BỐ TRÍ LẠI chứ không co ảnh: chữ ở cỡ đã thu nhỏ có bề rộng không tỉ lệ tuyệt
      // đối với cỡ gốc (làm tròn theo pixel/hinting), nên kích thước thật SAU zoom lệch vài phần
      // trăm so với "kích thước gốc nhân k". Đo một lần rồi tin là bảng tràn ra ngoài tem (đã gặp:
      // 51.33mm trên tem rộng 49.30mm). Phải zoom -> ĐO LẠI -> chỉnh, tới khi khít.
      var r = null;
      for (var lan = 0; lan < 6; lan++) {
        table.style.zoom = k;
        r = table.getBoundingClientRect();
        if (!r.width || !r.height) break;
        // Sau khi xoay: chiều cao bảng thành bề rộng in, bề rộng bảng thành chiều cao in.
        var heSo = xoay
          ? Math.min(rongPx / r.height, caoPx / r.width)
          : Math.min(rongPx / r.width, caoPx / r.height);
        // heSo >= 1 nghĩa là ĐÃ NẰM GỌN trong tem; chỉ được dừng ở trạng thái đó, không bao giờ
        // dừng khi còn thừa ra (dù chỉ 0.1%) — thừa là bị xén mất một cột.
        if (heSo >= 1 && (heSo < 1.01 || k >= 1)) break;
        k = Math.min(1, k * heSo * 0.999);   // 0.999: chừa vụn làm tròn, tránh dao động qua lại
      }

      if (xoay) {
        table.style.transformOrigin = 'top left';
        // Toạ độ trong transform tính theo hệ CỦA CHÍNH phần tử, mà hệ đó đã bị zoom co lại — nên
        // muốn dịch đúng r.height khi vẽ ra thì phải ghi r.height chia k. Xoay quanh góc trên-trái
        // đẩy bảng sang trái trục (x' = -y) nên phải dịch phải đúng bằng chiều cao bảng.
        table.style.transform = 'translateX(' + (r.height / k) + 'px) rotate(90deg)';
        // Hộp bố cục của bảng KHÔNG xoay theo transform, nên phải ép lại kích thước của khung
        // trang — nếu không, phần thừa vẫn tính là nội dung và trình duyệt đẩy sang trang 2.
        page.style.width = r.height + 'px';
        page.style.height = r.width + 'px';
        page.style.overflow = 'hidden';
      }

      // (3) Nét viền 0.2mm sau khi co chỉ còn ~0.7 dot ở 203dpi -> máy in nhiệt rasterize ra nét
      // ĐỨT QUÃNG (lỗi thật đã gặp 31/07/2026 ở /print-station). Excel không bị vậy vì GDI không
      // bao giờ vẽ nét mảnh hơn 1 pixel. Bù lại đúng bằng cách đó: ép viền trước khi co sao cho
      // sau khi co vẫn >= 1 dot (0.125mm).
      var toiThieu = 0.125 / k;
      if (toiThieu > 0.2) {
        for (i = 0; i < o.length; i++) o[i].style.borderWidth = toiThieu + 'mm';
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
