<template>
  <!-- Dựng lại UserForm `printform` / `wait_printform` của workbook DF002 theo đúng tọa độ
       designer (hai form đó giống hệt nhau về bố cục, chỉ khác nguồn đọc dữ liệu — nên
       dùng chung một component). Form gốc 306 × 390pt. -->
  <div class="vba-modal-backdrop" @click.self="$emit('close')">
    <div class="vba-form vba-modal" :style="{ width: '306pt', height: '390pt' }">
      <button class="vba-btn vba-btn-print" :style="box(24, 18, 126, 36)" @click="doPrint" :disabled="printing">PRINT</button>
      <button class="vba-btn" :style="box(156, 18, 66, 36)" @click="$emit('close')">CLEAR</button>
      <button class="vba-btn" :style="box(222, 18, 60, 36)" @click="$emit('close')">CLOSE</button>

      <label class="vba-label" :style="box(24, 60, 72, 18)">dye stuff information</label>
      <label class="vba-label" :style="box(156, 60, 72, 18)">auxiliary information</label>
      <textarea :value="data.rawQrDye" readonly class="vba-text vba-raw" :style="box(24, 76.8, 126, 44.4)" />
      <textarea :value="data.rawQrChem" readonly class="vba-text vba-raw" :style="box(156, 76.8, 126, 44.4)" />

      <label class="vba-label vba-label-bold" :style="box(24, 138, 126, 18)">DF_WEIGHING_SLIP</label>

      <label class="vba-label" :style="box(24, 162, 36, 18)">mã màu</label>
      <label class="vba-label" :style="box(90.05, 162, 36, 18)">mã đai</label>
      <label class="vba-label" :style="box(156, 162, 36, 18)">mã máy</label>
      <label class="vba-label" :style="box(210, 162, 36, 18)">mã thùng</label>
      <label class="vba-label" :style="box(258, 162, 24, 18)">lv</label>

      <input :value="data.color" readonly class="vba-text" :style="box(24, 180, 54, 18)" />
      <input :value="data.code" readonly class="vba-text" :style="box(90.05, 180, 54, 18)" />
      <input :value="data.machine" readonly class="vba-text" :style="box(156, 180, 42, 18)" />
      <input :value="data.tank" readonly class="vba-text" :style="box(210, 180, 36, 18)" />
      <input :value="data.level" readonly class="vba-text" :style="box(258, 180, 24, 18)" />

      <!-- Bảng DYE (trái) và CHEM (phải), mỗi bảng 9 dòng rack/code/weight -->
      <template v-for="i in 9" :key="'row-' + i">
        <input :value="dyeLines[i - 1]?.rack || ''" readonly class="vba-text" :style="box(24, 204 + (i - 1) * 18, 30, 18)" />
        <input :value="dyeLines[i - 1]?.code || ''" readonly class="vba-text" :style="box(54, 204 + (i - 1) * 18, 54, 18)" />
        <input :value="dyeLines[i - 1]?.weight || ''" readonly class="vba-text" :style="box(108, 204 + (i - 1) * 18, 42, 18)" />
        <input :value="chemLines[i - 1]?.rack || ''" readonly class="vba-text" :style="box(156, 204 + (i - 1) * 18, 30, 18)" />
        <input :value="chemLines[i - 1]?.code || ''" readonly class="vba-text" :style="box(186, 204 + (i - 1) * 18, 54, 18)" />
        <input :value="chemLines[i - 1]?.weight || ''" readonly class="vba-text" :style="box(240, 204 + (i - 1) * 18, 42, 18)" />
      </template>
    </div>

  </div>
</template>

<script lang="ts">
// Khối script thường (không phải <script setup>) vì <script setup> không cho phép export.
export interface VbaPrintFormData {
  color: string;
  code: string;
  machine: string;
  tank: string;
  level: string;
  rawQrDye: string;
  rawQrChem: string;
  /** Mã lô — chỉ để ghi lên tiêu đề cửa sổ in và dòng ghi chú của tem. */
  batchId?: string;
}
</script>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { parseRackLines } from '../utils/rackParser';
import { writeDispatchSlipToWindow } from '../utils/dispatchSlipPrint';

const props = defineProps<{ data: VbaPrintFormData }>();
defineEmits<{ (e: 'close'): void }>();

const box = (l: number, t: number, w: number, h: number) => ({
  left: l + 'pt', top: t + 'pt', width: w + 'pt', height: h + 'pt',
});

// printform.ParseQR — cắt chuỗi thô thành từng bộ 3 (rack, code, weight), tối đa 9 dòng.
const dyeLines = computed(() => parseRackLines(props.data.rawQrDye).slice(0, 9));
const chemLines = computed(() => parseRackLines(props.data.rawQrChem).slice(0, 9));

// Nút PRINT — in ĐÚNG tem 70x100mm của Trạm in (utils/dispatchSlipPrint.ts), không phải một
// phiếu text riêng như bản trước (yêu cầu 2026-08-03: "in ở đây ra tem giống ở print-station").
// Cửa sổ in phải mở ĐỒNG BỘ ngay trong handler click, trước await dựng QR — nếu mở sau,
// Chrome/Edge coi như mất "user gesture" và chặn popup.
const printing = ref(false);

const doPrint = async () => {
  if (printing.value) return;
  const win = window.open('', '_blank', 'width=780,height=980');
  if (!win) {
    alert('Trình duyệt đã chặn cửa sổ mới — cho phép popup cho trang này rồi thử lại.');
    return;
  }
  printing.value = true;
  try {
    await writeDispatchSlipToWindow(win, {
      color: props.data.color,
      productCode: props.data.code,
      machineCode: props.data.machine,
      tankCode: props.data.tank,
      levelCode: props.data.level,
      rawQrDye: props.data.rawQrDye,
      rawQrChem: props.data.rawQrChem,
      batchId: props.data.batchId,
    });
  } finally {
    printing.value = false;
  }
};
</script>

<style scoped>
.vba-modal-backdrop {
  position: fixed;
  inset: 0;
  background-color: rgba(0, 0, 0, 0.35);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.vba-form {
  position: relative;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

.vba-modal { box-shadow: 4px 4px 12px rgba(0, 0, 0, 0.5); }

.vba-form > * {
  position: absolute;
  box-sizing: border-box;
  font: inherit;
  margin: 0;
}

.vba-text {
  background-color: #ffffff;
  color: #000000;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 0 2px;
  outline: none;
  overflow: hidden;
}

.vba-raw {
  font-size: 6.5pt;
  resize: none;
  overflow: auto;
}

.vba-btn {
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}

.vba-btn:active { border-color: #808080 #ffffff #ffffff #808080; }
.vba-btn-print { font-weight: bold; }

.vba-label { background-color: transparent; display: flex; align-items: center; }
.vba-label-bold { font-weight: bold; }

.vba-btn:disabled { color: #808080; text-shadow: 1px 1px 0 #ffffff; cursor: not-allowed; }
</style>
