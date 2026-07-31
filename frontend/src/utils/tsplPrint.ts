import QRCode from 'qrcode';

// Dựng lại lệnh TSPL (do chính backend sinh ra: SIZE/TEXT/QRCODE) thành trang HTML định
// vị tuyệt đối theo mm rồi gọi window.print() — dùng cho các trạm in qua hộp thoại in của
// trình duyệt thay vì gửi TSPL thô xuống máy in qua Local Agent (yêu cầu 2026-07-30).
// Cùng quy ước quy đổi với LabelPreview.vue: máy in tem TSC 203dpi = 8 dot/mm, tọa độ
// TEXT/QRCODE tính bằng dot còn SIZE tính bằng mm.
const DOTS_PER_MM = 8;
const FONT_DOT_HEIGHT: Record<string, number> = { '1': 8, '2': 12, '3': 16, '4': 24, '5': 32, '6': 48 };

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
      dataUrl = await QRCode.toDataURL(content, { width: 240, margin: 0 });
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
  body { font-family: Arial, sans-serif; margin: 0; padding: 6mm; color: #000; display: flex; justify-content: center; }
  /* Tem thật khá bé trên màn hình to — phóng to riêng cho màn hình (zoom, không phải
     transform, để layout giãn ra đúng), lúc in luôn trả về kích thước gốc 1:1. */
  .slip { position: relative; width: ${widthMm}mm; height: ${heightMm}mm; border: 1.2mm solid #000; zoom: 2.6; }
  .t { position: absolute; white-space: nowrap; line-height: 1; }
  .q { position: absolute; }
  /* Không khai báo @page thì trình duyệt in theo khổ giấy mặc định của driver máy in
     (thường A4/Letter) — .slip vẫn đúng kích thước thật nhưng chỉ chiếm 1 góc nhỏ giữa
     tờ giấy to hơn nhiều, nhìn như "tem bé xíu" (bug thật 2026-07-30, phát hiện qua ảnh
     tem in ra ở /print-station dùng chung cơ chế này). Khai báo khổ trang = đúng khổ tem
     để Chrome/Edge tự yêu cầu đổi khổ giấy khi in. */
  @page { size: ${widthMm}mm ${heightMm}mm; margin: 0; }
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
