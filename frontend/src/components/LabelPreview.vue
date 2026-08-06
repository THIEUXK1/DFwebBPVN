<template>
  <div class="label-preview-root">
    <div v-if="mode === 'no-layout'" class="no-layout-notice">
      ℹ️ Loại tem này (đơn sản xuất / QR_LABEL_PRINTING) <strong>chưa có mẫu bố cục/kích thước</strong>
      định nghĩa trong hệ thống web — tem in ra dùng đúng mẫu đã cấu hình sẵn trên chính máy in vật lý
      (template TSPL cục bộ). Web chỉ gửi <strong>nội dung QR thô</strong>, nên chỉ xem trước được nội
      dung từng mã QR bên dưới — <strong>không xem được đúng bố cục/kích thước tem thật</strong>.
      <div class="qr-payload-list mt-3">
        <div v-for="(item, idx) in qrPayloads" :key="idx" class="qr-payload-item">
          <!-- Phải chú kiểu cho `el`: hàm mũi tên viết thẳng trong template không được suy kiểu
               từ đâu cả. `unknown` chứ không phải `any` — setCanvasRef vẫn tự lọc bằng
               instanceof nên không mất an toàn kiểu. -->
          <canvas :ref="(el: unknown) => setCanvasRef(el, idx)" class="qr-canvas"></canvas>
          <div class="qr-payload-text">
            <span class="badge badge-blue font-xs">{{ item.type }}</span>
            <code class="font-xs">{{ item.payload }}</code>
          </div>
        </div>
      </div>
    </div>

    <!-- Phiếu cân (từ 06/08/2026) là mảnh HTML dựng 1:1 theo sheet Excel mà VBA in ra, không phải
         TSPL — xem `utils/weighSlip.ts`. Nhúng trong iframe chứ không dán thẳng vào trang: mảnh đó
         mang theo CSS của riêng nó (`.df-slip`, `@page`), dán thẳng là rò ra toàn ứng dụng. -->
    <div v-else-if="mode === 'html'" class="html-preview">
      <p class="text-muted font-xs mb-2">
        Phiếu cân dựng theo đúng bố cục sheet của form VBA
        (<strong>{{ sizeMm.width }}mm × {{ sizeMm.height }}mm</strong>, Calibri 12, kẻ ô A1:E19).
        Bản in thật được co lại cho vừa 1 trang giống <em>FitToPages</em> của Excel.
      </p>
      <div class="canvas-wrap">
        <iframe class="html-frame" :srcdoc="htmlDoc" title="Xem trước phiếu cân"></iframe>
      </div>
    </div>

    <div v-else-if="mode === 'tspl'" class="tspl-preview">
      <p class="text-muted font-xs mb-2">
        Xem trước gần đúng từ lệnh TSPL thật sẽ gửi xuống máy in — kích thước theo đúng tỉ lệ khai báo
        (<strong>{{ sizeMm.width }}mm × {{ sizeMm.height }}mm</strong>). Font chữ/khoảng cách là mô phỏng
        tương đối, không phải ảnh in chính xác từng pixel.
      </p>
      <div class="canvas-wrap">
        <canvas ref="tsplCanvas" :width="canvasPx.width" :height="canvasPx.height" class="label-canvas"></canvas>
      </div>
    </div>

    <div v-else class="text-muted font-xs">Không có dữ liệu tem để xem trước.</div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import QRCode from 'qrcode';
import { wrapSlipDocument } from '../utils/slipPrint';

const props = defineProps<{ labelPayload: string | null | undefined }>();

// DPI chuẩn máy in tem TSC (203dpi ≈ 8 dots/mm) — tọa độ TEXT/QRCODE trong TSPL tính
// bằng dot, còn SIZE/GAP tính bằng mm. Quy đổi cả 2 về cùng đơn vị px màn hình.
const DOTS_PER_MM = 8;
const SCREEN_PX_PER_MM = 4; // độ phân giải hiển thị trên màn hình, không phải máy in thật

type QrItem = { type: string; payload: string };

const mode = computed<'no-layout' | 'html' | 'tspl' | 'empty'>(() => {
  const raw = (props.labelPayload || '').trim();
  if (!raw) return 'empty';
  if (raw.startsWith('[')) return 'no-layout'; // JSON QR_LABEL_PRINTING — chưa có layout
  if (raw.startsWith('<div class="df-slip-page"')) return 'html'; // phiếu cân 1:1 theo sheet VBA
  if (/^SIZE\s/im.test(raw)) return 'tspl';
  return 'empty';
});

/** Trang hoàn chỉnh cho iframe xem trước. `false` = KHÔNG nhúng script tự in. */
const htmlDoc = computed(() => (mode.value === 'html' ? wrapSlipDocument(props.labelPayload || '', false) : ''));

const qrPayloads = computed<QrItem[]>(() => {
  if (mode.value !== 'no-layout') return [];
  try {
    const parsed = JSON.parse(props.labelPayload || '[]');
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
});

const qrCanvases = ref<Record<number, HTMLCanvasElement>>({});
function setCanvasRef(el: unknown, idx: number) {
  if (el instanceof HTMLCanvasElement) {
    qrCanvases.value[idx] = el;
  }
}

async function renderQrList() {
  await nextTick();
  for (const [idxStr, canvas] of Object.entries(qrCanvases.value)) {
    const idx = Number(idxStr);
    const item = qrPayloads.value[idx];
    if (item && canvas) {
      try {
        await QRCode.toCanvas(canvas, item.payload, { width: 120, margin: 1 });
      } catch (err) {
        console.error('QR render failed for payload', item, err);
      }
    }
  }
}

// ---- TSPL parsing (chỉ đủ cho các lệnh do chính hệ thống sinh ra — SIZE/GAP/TEXT/QRCODE) ----
const sizeMm = computed(() => {
  const raw = props.labelPayload || '';
  if (mode.value === 'html') {
    // Khổ giấy của phiếu cân nằm ở data-w/data-h ngay trên thẻ bọc (xem `utils/weighSlip.ts`).
    const w = raw.match(/data-w="([\d.]+)"/);
    const h = raw.match(/data-h="([\d.]+)"/);
    return { width: w ? parseFloat(w[1]) : 0, height: h ? parseFloat(h[1]) : 0 };
  }
  const m = raw.match(/SIZE\s+([\d.]+)\s*mm\s*,\s*([\d.]+)\s*mm/i);
  return { width: m ? parseFloat(m[1]) : 40, height: m ? parseFloat(m[2]) : 30 };
});

const canvasPx = computed(() => ({
  width: Math.round(sizeMm.value.width * SCREEN_PX_PER_MM),
  height: Math.round(sizeMm.value.height * SCREEN_PX_PER_MM),
}));

// CHIỀU CAO ô chữ font dựng sẵn của máy in TSC (dot) — phải trùng bảng trong `utils/tsplPrint.ts`,
// nếu không thì bản xem trước trên màn hình khác bản in ra giấy. Bản trước ghi nhầm chiều RỘNG.
const FONT_DOT_HEIGHT: Record<string, number> = {
  '1': 12, '2': 20, '3': 24, '4': 32, '5': 48, '6': 19, '7': 27, '8': 25,
};

function dotsToPx(dots: number): number {
  return (dots / DOTS_PER_MM) * SCREEN_PX_PER_MM;
}

const tsplCanvas = ref<HTMLCanvasElement | null>(null);

async function renderTspl() {
  await nextTick();
  const canvas = tsplCanvas.value;
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = '#ccc';
  ctx.strokeRect(0, 0, canvas.width, canvas.height);

  const raw = props.labelPayload || '';

  // TEXT x,y,"font",rotation,xmul,ymul,"content"
  const textRe = /TEXT\s+(-?\d+),(-?\d+),"([^"]*)",(-?\d+),(\d+),(\d+),"([^"]*)"/gi;
  let m: RegExpExecArray | null;
  ctx.fillStyle = '#000000';
  ctx.textBaseline = 'top';
  while ((m = textRe.exec(raw)) !== null) {
    const [, x, y, font, , xmul, , content] = m;
    const dotH = FONT_DOT_HEIGHT[font] || 16;
    const pxH = dotsToPx(dotH * (parseInt(xmul, 10) || 1));
    ctx.font = `${Math.max(pxH, 8)}px monospace`;
    ctx.fillText(content, dotsToPx(parseInt(x, 10)), dotsToPx(parseInt(y, 10)));
  }

  // QRCODE x,y,ecc,cellwidth,mode,rotation,"content"
  const qrRe = /QRCODE\s+(-?\d+),(-?\d+),[HQML],(\d+),[AN],(-?\d+),"([^"]*)"/gi;
  while ((m = qrRe.exec(raw)) !== null) {
    const [, x, y, cellWidth, , content] = m;
    const sizePx = Math.max(dotsToPx(parseInt(cellWidth, 10) * 20), 40); // xấp xỉ, không phải công thức TSC chính xác
    try {
      const dataUrl = await QRCode.toDataURL(content, { width: sizePx, margin: 0 });
      const img = new Image();
      await new Promise<void>((resolve) => {
        img.onload = () => resolve();
        img.src = dataUrl;
      });
      ctx.drawImage(img, dotsToPx(parseInt(x, 10)), dotsToPx(parseInt(y, 10)), sizePx, sizePx);
    } catch (err) {
      console.error('QR render failed in TSPL preview', err);
    }
  }
}

onMounted(() => {
  if (mode.value === 'no-layout') renderQrList();
  if (mode.value === 'tspl') renderTspl();
});

watch(() => props.labelPayload, () => {
  if (mode.value === 'no-layout') renderQrList();
  if (mode.value === 'tspl') renderTspl();
});
</script>

<style scoped>
.label-preview-root { padding: 12px; }
.no-layout-notice {
  font-size: 0.85rem;
  color: var(--text-muted);
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  border-radius: var(--radius-md);
  padding: 12px;
}
.qr-payload-list { display: flex; flex-direction: column; gap: 10px; }
.qr-payload-item {
  display: flex;
  align-items: center;
  gap: 12px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 8px;
}
.qr-canvas { flex-shrink: 0; background: #fff; border-radius: 4px; }
.qr-payload-text { display: flex; flex-direction: column; gap: 4px; word-break: break-all; }
.canvas-wrap { display: flex; justify-content: center; padding: 12px; background: #333; border-radius: var(--radius-md); }
.label-canvas { background: #fff; box-shadow: 0 0 8px rgba(0,0,0,0.3); }
.html-frame { width: 100%; height: 420px; border: 0; background: #fff; border-radius: 4px; }
</style>
