import QRCode from 'qrcode';

// Dựng lại lệnh TSPL (do chính backend sinh ra: SIZE/TEXT/QRCODE) thành trang HTML định
// vị tuyệt đối theo mm rồi gọi window.print() — dùng cho các trạm in qua hộp thoại in của
// trình duyệt thay vì gửi TSPL thô xuống máy in qua Local Agent (yêu cầu 2026-07-30).
// Cùng quy ước quy đổi với LabelPreview.vue: máy in tem TSC 203dpi = 8 dot/mm, tọa độ
// TEXT/QRCODE tính bằng dot còn SIZE tính bằng mm.
const DOTS_PER_MM = 8;
/**
 * CHIỀU CAO ô chữ của các font dựng sẵn trong máy in TSC (dot).
 *
 * Bản trước ghi 8/12/16/24/32 — đó là chiều RỘNG của các font đó (font 1 = 8x12, 2 = 12x20,
 * 3 = 16x24, 4 = 24x32, 5 = 32x48), nên mọi chữ in qua trình duyệt ra nhỏ hơn ý định của lệnh
 * TSPL khoảng 1.5 lần. Đây là một nửa nguyên nhân tem cân "chữ nhỏ, vỡ chữ, không đọc được"
 * (05/08/2026) — nửa còn lại là bản thân bố cục tem dùng font 1 cho cả bảng, xem `weighSlip.ts`.
 */
const FONT_DOT_HEIGHT: Record<string, number> = {
  '1': 12, '2': 20, '3': 24, '4': 32, '5': 48, '6': 19, '7': 27, '8': 25,
};

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c] as string));
}

/**
 * @param win Cửa sổ đã mở SẴN bằng window.open() đồng bộ ngay lúc bấm nút — bắt buộc, vì
 *   Chrome/Edge chặn popup mở sau await (mất "user gesture" gắn với cú click).
 */
export async function printTsplViaBrowser(tspl: string, win: Window): Promise<void> {
  const sizeMatch = tspl.match(/SIZE\s+([\d.]+)\s*mm\s*,\s*([\d.]+)\s*mm/i);
  const widthMm = sizeMatch ? parseFloat(sizeMatch[1]) : 80;
  const heightMm = sizeMatch ? parseFloat(sizeMatch[2]) : 50;

  const dotToMm = (dot: number) => dot / DOTS_PER_MM;

  let bodyHtml = '';

  const textRe = /TEXT\s+(-?\d+),(-?\d+),"([^"]*)",(-?\d+),(\d+),(\d+),"([^"]*)"/gi;
  let m: RegExpExecArray | null;
  while ((m = textRe.exec(tspl)) !== null) {
    const [, x, y, font, , xmul, , content] = m;
    const fontMm = dotToMm((FONT_DOT_HEIGHT[font] || 16) * (parseInt(xmul, 10) || 1));
    bodyHtml += `<div class="t" style="left:${dotToMm(parseInt(x, 10))}mm;top:${dotToMm(parseInt(y, 10))}mm;font-size:${fontMm}mm;">${escapeHtml(content)}</div>`;
  }

  const qrRe = /QRCODE\s+(-?\d+),(-?\d+),[HQML],(\d+),[AN],(-?\d+),"([^"]*)"/gi;
  while ((m = qrRe.exec(tspl)) !== null) {
    const [, x, y, cellWidth, , content] = m;
    const sizeMm = dotToMm(parseInt(cellWidth, 10) * 20);
    let dataUrl = '';
    try {
      // 240 -> 960 (2026-07-31): nguồn dư độ phân giải để lúc in luôn là THU NHỎ. Nguồn nhỏ
      // bị phóng to khi in làm cạnh module QR nhoè xám, máy in nhiệt dither ra lấm tấm/mờ.
      dataUrl = await QRCode.toDataURL(content, { width: 960, margin: 0 });
    } catch (err) {
      console.error('QR render failed for browser print', err);
    }
    if (dataUrl) {
      bodyHtml += `<img class="q" src="${dataUrl}" alt="QR" style="left:${dotToMm(parseInt(x, 10))}mm;top:${dotToMm(parseInt(y, 10))}mm;width:${sizeMm}mm;height:${sizeMm}mm;" />`;
    }
  }

  const html = `<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8" />
<title>Tem ${widthMm}x${heightMm}mm</title>
<style>
  * { box-sizing: border-box; }
  /* print-color-adjust:exact — không cho trình duyệt làm nhạt màu khi in; máy in tem là máy
     in NHIỆT chỉ có đen/trắng, mọi sắc xám đều bị dither thành lưới chấm thưa -> nhìn mờ.
     Tắt khử răng cưa (anti-aliasing) để ép chữ/viền ra màu đen ĐẶC tuyệt đối 100%. */
  html, body {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    -webkit-font-smoothing: none;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeSpeed;
  }
  body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #000; display: flex; justify-content: center; }
  /* Tem thật khá bé trên màn hình to — phóng to riêng cho màn hình (zoom, không phải
     transform, để layout giãn ra đúng), lúc in luôn trả về kích thước gốc 1:1.
     border 0.25mm = ĐÚNG 2 dot ở 203dpi (tròn dot -> nét đen đặc, không dither/nhoè). */
  .slip { position: relative; width: ${widthMm}mm; height: ${heightMm}mm; border: 0.25mm solid #000; zoom: 2.6; }
  .t { position: absolute; white-space: nowrap; line-height: 1; font-weight: 700; letter-spacing: -0.1px; }
  .q { position: absolute; image-rendering: pixelated; }
  /* Không khai báo @page thì trình duyệt in theo khổ giấy mặc định của driver máy in
     (thường A4/Letter) — .slip vẫn đúng kích thước thật nhưng chỉ chiếm 1 góc nhỏ giữa
     tờ giấy to hơn nhiều, nhìn như "tem bé xíu" (bug thật 2026-07-30, phát hiện qua ảnh
     tem in ra ở /print-station dùng chung cơ chế này). Khai báo khổ trang = đúng khổ tem
     để Chrome/Edge tự yêu cầu đổi khổ giấy khi in. */
  @page { size: ${widthMm}mm ${heightMm}mm; margin: 0; }
  /* KHÔNG scale khi in: scale nhân vào cả độ dày nét, làm nét lẻ dot (0.25mm=2dot -> 1.9dot)
     và rasterize ra đường ĐỨT QUÃNG — lỗi thật ghi nhận 2026-07-31 ở /print-station. */
  @media print { body { padding: 0; } .slip { zoom: 1; } }
</style>
</head>
<body>
  <div class="slip">${bodyHtml}</div>
  <script>
    window.onload = function () {
      // window.onafterprint không đóng được cửa sổ trong thực tế (xác nhận 2026-07-30) —
      // window.print() CHẶN (blocking) tới khi hộp thoại in đóng trên Chrome/Edge, nên
      // gọi window.close() ngay sau là đủ, không cần chờ sự kiện afterprint.
      window.print();
      window.close();
    };
  <\/script>
</body>
</html>`;

  win.document.open();
  win.document.write(html);
  win.document.close();
}