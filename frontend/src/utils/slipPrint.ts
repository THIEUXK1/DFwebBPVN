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
  /* Ảnh phiếu đã tô sẵn ở ĐÚNG lưới dot của máy in (xem SCRIPT_TU_IN): mỗi pixel = 1 dot, chỉ có
     đen tuyệt đối hoặc trắng tuyệt đối. Bắt buộc nội suy LÁNG GIỀNG GẦN NHẤT — để trình duyệt nội
     suy trơn (mặc định) là nó pha lại sắc xám ở mọi cạnh nét, đúng thứ vừa khử xong. */
  canvas.df-slip-bitmap { image-rendering: pixelated; image-rendering: crisp-edges; display: block; }
</style>
</head>
<body>
${slipHtml}
${autoPrint ? SCRIPT_TU_IN : ''}
</body>
</html>`;
}

/**
 * TÔ PHIẾU THÀNH ẢNH 1-BIT ĐÚNG LƯỚI DOT CỦA MÁY IN, rồi mới in ảnh đó.
 *
 * ============================ VÌ SAO KHÔNG CÒN LÀ HTML CO GIÃN ============================
 * Bản trước để nguyên bảng HTML rồi `zoom` cho vừa tem. Chữ vì thế do TRÌNH DUYỆT vẽ, mà trình
 * duyệt luôn khử răng cưa: mỗi nét chữ có viền xám hai bên. Máy in tem là máy in NHIỆT **1 bit** —
 * không in được xám, driver phải dither sắc xám thành lưới chấm thưa. Ở cỡ chữ ~11-15 dot thì
 * phần viền xám chiếm gần nửa nét, nên cái ra giấy là chấm lấm tấm quanh mỗi chữ: đúng cảm giác
 * "MỜ" mà mắt thấy. Không có thuộc tính CSS nào tắt được khử răng cưa trên Chrome/Windows
 * (`-webkit-font-smoothing` chỉ có tác dụng trên macOS — nó nằm trong `wrapSlipDocument` từ trước
 * và thực tế KHÔNG làm gì cả trên máy trạm).
 *
 * Cách duy nhất ra nét tuyệt đối là tự quyết định từng dot:
 *   1. Dựng canvas đúng **số dot thật** của vùng in (60x40mm, lề 2mm, 8 dot/mm -> 448 x 288 dot).
 *   2. Vẽ lưới ô + chữ vào canvas ở đúng độ phân giải đó (không co giãn về sau).
 *   3. **Nhị phân hoá**: quét từng pixel, tối hơn ngưỡng -> ĐEN đặc, còn lại -> TRẮNG đặc. Sau
 *      bước này trong ảnh không còn một sắc xám nào để mà dither.
 *   4. In canvas ở đúng kích thước vật lý của nó (số dot / 8 mm) với `image-rendering: pixelated`,
 *      tức 1 pixel ảnh = 1 dot máy in, không nội suy.
 *
 * Đánh đổi đã biết: chữ KHÔNG to lên: nét sạch hơn chứ ngân sách dot không đổi. Muốn to hơn nữa
 * thì phải bớt dòng — 288 dot chia 19 dòng chỉ được 15 dot/dòng. Xem bảng ngân sách trong
 * `session-log.md`.
 *
 * Vẫn giữ nguyên tinh thần `FitToPagesWide/Tall = 1` của VBA: chọn cỡ chữ LỚN NHẤT mà cả bảng còn
 * nằm gọn trong tem, và XOAY 90° nếu xoay cho cỡ chữ lớn hơn (tem dọc 53.3x101.6 của cuộn cũ thì
 * xoay có lợi, tem ngang 60x40 hiện tại thì không) — nên đổi cuộn tem chỉ cần sửa `SLIP_PAGE_MM`
 * và hằng tương ứng bên PHP, không phải sờ vào đây.
 *
 * Tô ở ĐÂY chứ không sửa payload là có chủ đích: `print_jobs.label_payload` phải khớp TỪNG KÝ TỰ
 * giữa `utils/weighSlip.ts` và `WeighingJobController::buildSlipHtml` (script đối soát
 * `frontend/scripts/check-weigh-slip.mjs`), và bản xem trước ở Lịch sử in vẫn cần bảng HTML đọc
 * được. Payload vẫn là bảng HTML; canvas chỉ là cách VẼ nó ra giấy.
 */
const SCRIPT_TU_IN = `<script>
(function () {
  // TSC 203dpi. Dùng 8 dot/mm đúng quy ước của TSPL (và của utils/tsplPrint.ts) — lệch 0.1% so
  // với 203dpi thật (8.0 vs 7.992 dot/mm), không đủ để dịch nổi một dot trên cả bề rộng tem.
  var DOT_MM = 8;

  // Ngưỡng nhị phân hoá, thang 0-255. Đặt CAO hơn mức giữa (128) là cố ý: nét chữ nhỏ do trình
  // duyệt vẽ có lõi đen và hai bên xám nhạt dần; lấy ngưỡng giữa thì nét bị gọt còn 1 dot và chữ
  // ra mảnh/gãy. 176 giữ lại phần xám đậm thành đen, nét dày lên ~1 dot mỗi bên — trên máy in
  // nhiệt dày mà liền vẫn dễ đọc hơn mảnh mà đứt.
  var NGUONG = 176;

  var FONT = 'Calibri, Carlito, Arial, sans-serif';

  function docSo(el, ten) {
    var v = parseFloat(el.getAttribute(ten));
    return isFinite(v) ? v : 0;
  }

  /**
   * Đọc bảng HTML của payload thành mảng 2 chiều.
   *
   * CỐ Ý bỏ qua tr.b (cờ in đậm theo dòng của weighSlip.ts): ở đây MỌI dòng đều in đậm, xem
   * chonCoChu(). Giữ lại cờ đó thì dòng thường ra nét mảnh và gãy sau khi nhị phân hoá.
   *
   * (Không dùng dấu nháy ngược trong khối này: cả đoạn script nằm trong một template literal,
   * một dấu nháy ngược lạc vào là cắt đứt chuỗi ngay tại đó.)
   */
  function docBang(table) {
    var trs = table.querySelectorAll('tr'), hang = [], i, j;
    for (i = 0; i < trs.length; i++) {
      var tds = trs[i].querySelectorAll('td'), o = [];
      for (j = 0; j < tds.length; j++) o.push((tds[j].textContent || '').replace(/\\s+$/, ''));
      hang.push({ o: o });
    }
    return hang;
  }

  /**
   * Cỡ chữ LỚN NHẤT (px = dot) mà bảng còn nằm gọn trong rongDot x caoDot.
   *
   * Dò từ to xuống nhỏ chứ không giải công thức: bề rộng cột là bề rộng THẬT của chữ Calibri ở
   * từng cỡ, mà bề rộng đó không tỉ lệ tuyến tính với cỡ chữ (hinting làm tròn theo pixel).
   */
  function chonCoChu(ctx, hang, soCot, rongDot, caoDot) {
    // Chiều cao dòng lấy TRỌN phần cao còn lại chia đều cho số dòng — không buộc nó vào cỡ chữ
    // theo một tỉ lệ cố định. Buộc theo tỉ lệ thì chiều cao dòng nhảy nấc và phần dư bị bỏ phí:
    // trên tem 60x40 (288 dot / 19 dòng) cách cũ chốt ở 14 dot/dòng và bỏ không 21 dot — gần một
    // dòng rưỡi, đủ để chữ nhỏ hơn một cỡ.
    var caoHang = Math.floor((caoDot - 1) / hang.length);
    if (caoHang < 9) return null;   // dưới 9 dot/dòng thì chữ không còn hình dạng để mà đọc
    // Chừa 1 dot cho viền trên + 2 dot thở trên/dưới phần chữ.
    for (var px = Math.min(40, caoHang - 3); px >= 6; px--) {
      // In ĐẬM toàn bộ: ở cỡ dưới 16 dot, nét chữ thường mảnh hơn 1.5 dot nên sau khi nhị phân
      // hoá bị đứt quãng. Đây là chỗ CỐ Ý lệch bản VBA (VBA chỉ đậm dòng 1 và 7) — cùng lý do mà
      // utils/tsplPrint.ts cũng để font-weight 700 cho mọi tem.
      ctx.font = 'bold ' + px + 'px ' + FONT;
      var dem = Math.max(3, Math.round(px * 0.34));   // lề trái/phải trong ô
      var rongCot = [], tong = 1, c, r;
      for (c = 0; c < soCot; c++) {
        var w = 0;
        for (r = 0; r < hang.length; r++) {
          var t = hang[r].o[c];
          if (t) w = Math.max(w, ctx.measureText(t).width);
        }
        rongCot[c] = Math.ceil(w) + 2 * dem + 1;
        tong += rongCot[c];
      }
      if (tong <= rongDot) return { px: px, caoHang: caoHang, rongCot: rongCot, rong: tong, dem: dem };
    }
    return null;
  }

  /** Vẽ lưới ô + chữ. Mọi toạ độ là SỐ NGUYÊN dot: lệch nửa dot là nét ra xám. */
  function ve(ctx, hang, dung) {
    var soCot = dung.rongCot.length, w = dung.rong, h = dung.caoHang * hang.length + 1, i, r, c;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, w, h);
    ctx.fillStyle = '#000';

    // Viền ô: dày ĐÚNG 1 dot. fillRect chứ không strokeRect — stroke vẽ giữa đường nên nét 1px
    // rơi vào nửa dot ở hai bên và ra xám, đúng cái đang cần tránh.
    for (r = 0; r <= hang.length; r++) ctx.fillRect(0, r * dung.caoHang, w, 1);
    var x = 0;
    ctx.fillRect(0, 0, 1, h);
    for (c = 0; c < soCot; c++) { x += dung.rongCot[c]; ctx.fillRect(x, 0, 1, h); }

    ctx.font = 'bold ' + dung.px + 'px ' + FONT;
    ctx.textBaseline = 'middle';
    ctx.textAlign = 'left';
    for (r = 0; r < hang.length; r++) {
      x = 1;
      for (c = 0; c < soCot; c++) {
        var t = hang[r].o[c];
        if (t) ctx.fillText(t, x + dung.dem, Math.round(r * dung.caoHang + dung.caoHang / 2) + 1);
        x += dung.rongCot[c];
      }
    }
    return { w: w, h: h };
  }

  /** Bước quyết định độ nét: bỏ hẳn dải xám, chỉ còn đen đặc và trắng đặc. */
  function nhiPhanHoa(ctx, w, h) {
    var d = ctx.getImageData(0, 0, w, h), p = d.data, i;
    for (i = 0; i < p.length; i += 4) {
      var v = (p[i] * 299 + p[i + 1] * 587 + p[i + 2] * 114) / 1000;
      var den = v < NGUONG ? 0 : 255;
      p[i] = p[i + 1] = p[i + 2] = den;
      p[i + 3] = 255;
    }
    ctx.putImageData(d, 0, 0);
  }

  function toPhieu() {
    var page = document.querySelector('.df-slip-page');
    var table = document.querySelector('.df-slip');
    if (!page || !table) return false;

    var lem = docSo(page, 'data-m');
    var rongDot = Math.floor((docSo(page, 'data-w') - 2 * lem) * DOT_MM);
    var caoDot = Math.floor((docSo(page, 'data-h') - 2 * lem) * DOT_MM);
    if (!(rongDot > 0 && caoDot > 0)) return false;

    var hang = docBang(table), soCot = 0, i;
    if (!hang.length) return false;
    for (i = 0; i < hang.length; i++) soCot = Math.max(soCot, hang[i].o.length);

    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d');
    if (!ctx) return false;

    var thang = chonCoChu(ctx, hang, soCot, rongDot, caoDot);
    var quay = chonCoChu(ctx, hang, soCot, caoDot, rongDot);
    var xoay = !!quay && (!thang || quay.px > thang.px);
    var dung = xoay ? quay : thang;
    if (!dung) return false;   // không cỡ nào vừa -> giữ bảng HTML, thà nhỏ còn hơn mất chữ

    canvas.width = dung.rong;
    canvas.height = dung.caoHang * hang.length + 1;
    var kt = ve(ctx, hang, dung);
    nhiPhanHoa(ctx, kt.w, kt.h);

    // Kích thước vật lý = đúng số dot chia 8 -> 1 pixel ảnh đè lên đúng 1 dot máy in.
    canvas.className = 'df-slip-bitmap';
    canvas.style.width = (kt.w / DOT_MM) + 'mm';
    canvas.style.height = (kt.h / DOT_MM) + 'mm';
    if (xoay) {
      // Xoay 90° là hoán vị pixel thuần tuý, không nội suy -> không mất nét.
      canvas.style.transformOrigin = 'top left';
      canvas.style.transform = 'translateX(' + (kt.h / DOT_MM) + 'mm) rotate(90deg)';
      page.style.width = (kt.h / DOT_MM) + 'mm';
      page.style.height = (kt.w / DOT_MM) + 'mm';
      page.style.overflow = 'hidden';
    }

    table.style.display = 'none';
    page.appendChild(canvas);
    return true;
  }

  function chay() {
    try { toPhieu(); } catch (e) { console.error('Khong to duoc phieu, in bang bang HTML:', e); }
    document.body.classList.add('ready');
    // window.print() CHẶN tới khi hộp thoại in đóng trên Chrome/Edge (xác nhận 30/07/2026), nên
    // gọi window.close() ngay sau là đủ, không cần chờ sự kiện afterprint.
    window.print();
    window.close();
  }

  // Phải chờ font nạp xong mới đo được bề rộng chữ: đo lúc font dự phòng còn đang dùng thì cột
  // rộng sai và cỡ chữ chọn ra cũng sai.
  window.onload = function () {
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(chay, chay);
    else chay();
  };
})();
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
