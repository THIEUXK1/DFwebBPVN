<template>
  <div class="vba-grid">
    <!-- Hàng tiêu đề — đúng Label11-14 của scaleform.frm -->
    <div class="vba-row vba-head">
      <div class="cell-no"></div>
      <div class="cell cell-rack">RACK</div>
      <div class="cell cell-dye">DYE CODE</div>
      <div class="cell cell-weight">WEIGHT</div>
      <div class="cell cell-process">PROCESS</div>
    </div>

    <!-- LUÔN vẽ đủ 9 dòng kể cả khi công thức ít vật tư hơn — đúng form gốc (txt_RACK1..9
         luôn hiện, ô thừa để trống), thao tác viên quen nhìn khung cố định không nhảy. -->
    <div
      v-for="(row, idx) in NINE_ROWS"
      :key="idx"
      class="vba-row"
      :class="{ 'is-current': idx === currentIndex, 'is-empty': !items[idx] }"
      @click="items[idx] && $emit('select', idx)"
    >
      <div class="cell-no">{{ idx + 1 }}</div>

      <div class="cell cell-rack">
        <input
          v-if="items[idx]"
          type="text"
          inputmode="numeric"
          class="vba-input"
          :value="items[idx].rack_code || ''"
          :disabled="readonlyRows"
          @click.stop
          @input="$emit('update-rack', idx, ($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="cell cell-dye">{{ items[idx]?.material_code || '' }}</div>

      <div class="cell cell-weight">{{ formatNum(items[idx]?.planned_weight) }}</div>

      <!-- Ô PROCESS: vừa là nơi hiện số cân thực tế, vừa là đèn báo dung sai. Trong VBA màu
           nền ô này CHÍNH LÀ trạng thái nghiệp vụ (btnSave_Click đọc ngược BackColor để ghi
           ACCEPTED/REJECTED). Ở đây chỉ dùng để hiển thị — nhãn ĐẠT/KHÔNG ĐẠT khi lưu vẫn do
           backend suy ra từ planned_weight/tolerance đã snapshot trên item. -->
      <div class="cell cell-process" :style="processStyle(idx)">
        {{ processText(idx) }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Lưới 9 dòng RACK / DYE CODE / WEIGHT / PROCESS — dựng theo đúng toạ độ scaleform.frm
// (trích bằng Excel COM từ workbook "4.semiauto-small scale ... DF026-027.xlsm"):
//   RACK 48pt | DYE CODE 330pt | WEIGHT 312pt | PROCESS 360pt, dòng cao 39.75pt bước 48pt,
//   dải số thứ tự bên trái rộng 12pt.
// Tỉ lệ cột giữ nguyên theo bản gốc, quy sang % để co giãn theo bề rộng màn hình thật.

const NINE_ROWS = 9;

const props = defineProps<{
  items: any[];
  /** Ô đang cân (0-based). -1 = chưa bắt đầu (chưa bấm NEXT lần nào). */
  currentIndex: number;
  /** Giá trị delta đã chốt cho từng ô, theo index. */
  capturedWeights: Record<number, number>;
  /** Số cân sống của ô đang cân — chỉ hiện ở đúng dòng currentIndex. */
  liveWeight: number;
  readonlyRows?: boolean;
}>();

defineEmits<{
  (e: 'select', idx: number): void;
  (e: 'update-rack', idx: number, value: string): void;
}>();

function formatNum(v: any): string {
  if (v === null || v === undefined || v === '') return '';
  return Number(v).toFixed(2);
}

/** Giá trị hiện ở ô PROCESS: ô đang cân lấy số sống, ô khác lấy giá trị đã chốt. */
function valueFor(idx: number): number | null {
  if (!props.items[idx]) return null;
  if (idx === props.currentIndex) return props.liveWeight;
  if (props.capturedWeights[idx] !== undefined) return props.capturedWeights[idx];
  // Đã cân xong từ trước (khôi phục job đang dở) thì hiện luôn kết quả đã lưu.
  const actual = props.items[idx].actual_weight;
  return actual !== null && actual !== undefined ? Number(actual) : null;
}

function processText(idx: number): string {
  const v = valueFor(idx);
  return v === null ? '' : v.toFixed(2);
}

/**
 * Port nguyên văn Mod_UI_processcolor.CheckRange:
 *   ratio = delta / target
 *   ratio < 0.99            -> RGB(250,230,5)  vàng  (chưa đủ)
 *   0.99 <= ratio <= 1.01   -> RGB(120,250,20) xanh  (đạt, đúng ±1%)
 *   ratio > 1.01            -> RGB(255,20,0)   đỏ    (vượt)
 *   target rỗng/<=0         -> trắng
 * Dùng ĐÚNG mã màu gốc (yêu cầu người dùng) nên hard-code hex, không dùng biến theme —
 * ô này giữ nền sáng + chữ đen kể cả khi giao diện đang ở chế độ tối.
 *
 * Delta ÂM (lấy vật tư ra tụt xuống dưới mốc bì) rơi vào nhánh `ratio < 0.99` -> vàng, đúng
 * nghĩa "chưa đủ". Không cần nhánh riêng, và quan trọng là KHÔNG có đường nào để số âm ăn nền
 * xanh — đây chính là thứ bản dùng Abs() trước đây làm sai được.
 */
function processStyle(idx: number): Record<string, string> {
  const item = props.items[idx];
  if (!item) return {};
  const v = valueFor(idx);
  if (v === null) return {};

  const target = Number(item.planned_weight);
  if (!target || target <= 0) return { backgroundColor: '#FFFFFF', color: '#000000' };

  const ratio = v / target;
  let bg = '#FAE605';               // RGB(250,230,5)
  if (ratio >= 0.99 && ratio <= 1.01) bg = '#78FA14';  // RGB(120,250,20)
  else if (ratio > 1.01) bg = '#FF1400';               // RGB(255,20,0)

  return { backgroundColor: bg, color: '#000000' };
}
</script>

<style scoped>
.vba-grid {
  border: 1px solid #000;
  background: #fff;
  /* Bảng luôn nền sáng như form VBA gốc, không đổi theo theme tối — thợ đã quen bảng trắng
     chữ đen, đổi sang nền tối làm mất cảm nhận màu vàng/xanh/đỏ vốn là tín hiệu chính. */
  color: #000;
}

.vba-row {
  display: grid;
  /* Tỉ lệ đúng bản gốc: 12 | 48 | 330 | 312 | 360 (pt) */
  grid-template-columns: 16px 4.4% 30.6% 28.9% 33.4%;
  height: 53px; /* 39.75pt */
  border-bottom: 1px solid #000;
}

.vba-row:last-child {
  border-bottom: none;
}

.vba-head {
  height: 24px;
  background: #d4d0c8; /* xám hệ thống kiểu form Windows cổ điển */
  font-weight: 700;
  font-size: 13px;
}

.vba-head .cell {
  display: flex;
  align-items: center;
  padding: 0 6px;
}

.cell-no {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 700;
  color: #555;
  background: #d4d0c8;
  border-right: 1px solid #000;
}

.cell {
  display: flex;
  align-items: center;
  padding: 0 8px;
  border-right: 1px solid #000;
  font-size: 18px;
  font-weight: 700;
  overflow: hidden;
  white-space: nowrap;
}

.cell:last-child {
  border-right: none;
}

.cell-weight,
.cell-process {
  justify-content: flex-end;
  font-family: 'Courier New', monospace;
}

.vba-head .cell-weight,
.vba-head .cell-process {
  justify-content: flex-start;
  font-family: inherit;
}

.cell-process {
  font-size: 22px;
}

/* Dòng đang cân — viền xanh đậm bên trái để thấy ngay đang ở ô nào giữa 9 dòng. */
.vba-row.is-current {
  box-shadow: inset 4px 0 0 #0a5cff;
}

.vba-row.is-empty {
  background: #f0f0f0;
}

.vba-input {
  width: 100%;
  height: 38px;
  border: 1px solid #7a7a7a;
  font-size: 18px;
  font-weight: 700;
  text-align: center;
  font-family: inherit;
  background: #fff;
  color: #000;
}

.vba-input:disabled {
  background: #ebebeb;
}
</style>
