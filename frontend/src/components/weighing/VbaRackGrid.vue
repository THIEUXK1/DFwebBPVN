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
         luôn hiện, ô thừa để trống), thao tác viên quen nhìn khung cố định không nhảy.
         Dòng trống cũng chọn được: VBA cho NEXT chạy hết 9 dòng bất kể QR có mấy dòng, và ô
         PROCESS ở đó vẫn hiện số cân sống để dùng cân như cân thường. -->
    <div
      v-for="(_row, idx) in MAX_RACK_LINES"
      :key="idx"
      class="vba-row"
      :class="{ 'is-current': idx === currentIndex, 'is-empty': !items[idx] }"
      @click="$emit('select', idx)"
    >
      <div class="cell-no">{{ idx + 1 }}</div>

      <!-- Dòng KHÔNG có vật tư cũng gõ được RACK khi đang cân tay (chưa quét đơn) — cân tay lưu
           theo đúng mã rack thợ gõ, không có gì khác để nhận ra dòng nào là dòng nào. Còn dòng
           trống nằm DƯỚI một đơn đã quét thì vẫn khoá: SAVE của mẻ theo đơn không gửi chúng đi,
           cho gõ vào đó chỉ tạo cảm giác đã ghi được cái gì đó. -->
      <div class="cell cell-rack">
        <input
          v-if="items[idx] || allowManualRack"
          type="text"
          inputmode="numeric"
          class="vba-input"
          :value="items[idx] ? (items[idx].rack_code || '') : (manualRacks?.[idx] || '')"
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

import { MAX_RACK_LINES } from '../../utils/qrDyeParser';
import { processTone, processBackground } from '../../utils/processColor';

const props = defineProps<{
  items: any[];
  /** Ô đang cân (0-based). -1 = chưa bắt đầu (chưa bấm NEXT lần nào). */
  currentIndex: number;
  /** Giá trị delta đã chốt cho từng ô, theo index. */
  capturedWeights: Record<number, number>;
  /** Số cân sống của ô đang cân — chỉ hiện ở đúng dòng currentIndex. */
  liveWeight: number;
  readonlyRows?: boolean;
  /** Mã RACK gõ tay cho dòng không có vật tư (cân tay), theo index. */
  manualRacks?: Record<number, string>;
  /** Cho gõ RACK ở dòng trống — bật khi đang cân tay (chưa quét đơn nào). */
  allowManualRack?: boolean;
}>();

defineEmits<{
  (e: 'select', idx: number): void;
  (e: 'update-rack', idx: number, value: string): void;
}>();

function formatNum(v: any): string {
  if (v === null || v === undefined || v === '') return '';
  return Number(v).toFixed(2);
}

/**
 * Giá trị hiện ở ô PROCESS: ô đang cân lấy số sống, ô khác lấy giá trị đã chốt.
 *
 * Hai nhánh đầu KHÔNG đòi phải có vật tư ở dòng đó: dòng nằm dưới phần QR mang tới vẫn cân được
 * (xem onNext) nên phải nhìn thấy số, nếu không thì bấm NEXT xuống đó chỉ thấy ô trắng trơn.
 */
function valueFor(idx: number): number | null {
  if (idx === props.currentIndex) return props.liveWeight;
  if (props.capturedWeights[idx] !== undefined) return props.capturedWeights[idx];
  if (!props.items[idx]) return null;
  // Đã cân xong từ trước (khôi phục job đang dở) thì hiện luôn kết quả đã lưu.
  const actual = props.items[idx].actual_weight;
  return actual !== null && actual !== undefined ? Number(actual) : null;
}

function processText(idx: number): string {
  const v = valueFor(idx);
  return v === null ? '' : v.toFixed(2);
}

/**
 * Nền ô PROCESS theo dung sai. Quy tắc (port của `Mod_UI_processcolor.CheckRange`) nằm ở
 * `utils/processColor` vì ô số DELTA cỡ lớn trên băng trên tô theo ĐÚNG quy tắc đó — hai chỗ nằm
 * cạnh nhau nên không được phép lệch. Ô này giữ nền sáng + chữ đen kể cả khi giao diện đang tối.
 */
function processStyle(idx: number): Record<string, string> {
  const v = valueFor(idx);
  if (v === null) return {};

  // Dòng không có vật tư = không có mục tiêu -> tone 'none' -> nền trắng.
  const tone = processTone(v, props.items[idx]?.planned_weight);

  return { backgroundColor: processBackground(tone), color: '#000000' };
}
</script>

<style scoped>
.vba-grid {
  border: 1px solid #b9c0cc;
  border-radius: 10px;
  overflow: hidden;
  background: #fff;
  /* Cột dọc để 9 dòng CHIA ĐỀU phần cao còn lại của màn hình thay vì đóng cứng 53px/dòng
     (07/08/2026). Trước đây bảng cao đúng 509px bất kể màn hình: màn cao thì thừa một mảng trống
     dưới đáy, màn thấp (hoặc phóng to 150%+) thì tràn ra ngoài và phải cuộn.
     `min-height: 0` là BẮT BUỘC: mặc định flex item không co xuống dưới kích thước nội dung, nên
     thiếu dòng này thì trên màn thấp bảng vẫn tràn y như cũ. */
  display: flex;
  flex-direction: column;
  min-height: 0;
  /* Bảng luôn nền sáng như form VBA gốc, không đổi theo theme tối — thợ đã quen bảng trắng
     chữ đen, đổi sang nền tối làm mất cảm nhận màu vàng/xanh/đỏ vốn là tín hiệu chính. */
  color: #0d1520;
  box-shadow: 0 1px 3px rgba(15, 30, 55, 0.1);
}

.vba-row {
  display: grid;
  /* Tỉ lệ đúng bản gốc: 12 | 48 | 330 | 312 | 360 (pt) */
  grid-template-columns: 34px 4.4% 30.6% 28.9% 33.4%;
  /* 9 dòng chia đều chỗ còn lại. `flex-basis: 0` (không phải auto) để chia theo phần bằng nhau
     chứ không theo nội dung — dòng có ô nhập RACK cao hơn dòng trống là lệch ngay.
     44px là sàn: dưới mức đó thì đeo găng bấm không trúng dòng nữa, thà để trang cuộn. */
  flex: 1 1 0;
  min-height: 44px;
  border-bottom: 1px solid #e3e7ee;
  transition: background 0.12s ease;
}

.vba-row:last-child {
  border-bottom: none;
}

.vba-head {
  /* Hàng tiêu đề KHÔNG co giãn — nó chỉ là nhãn, mọi px thừa phải về cho 9 dòng dữ liệu.
     Phải ghi cả `flex` lẫn `min-height` vì phần tử này mang luôn class .vba-row ở trên. */
  flex: 0 0 30px;
  min-height: 30px;
  background: #eef1f6;
  border-bottom: 1px solid #c9d1dd;
  font-weight: 800;
  font-size: 13px;
  letter-spacing: 0.7px;
  color: #56617a;
}

.vba-head .cell {
  display: flex;
  align-items: center;
  padding: 0 10px;
  font-size: inherit; /* nhãn giữ 13px, không đi theo chiều cao dòng như ô dữ liệu */
}

.cell-no {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: clamp(12px, calc(1.6vh / var(--df-zoom, 1)), 18px);
  font-weight: 800;
  color: #8a94a8;
  background: #f5f7fa;
  border-right: 1px solid #e3e7ee;
}

/* Cỡ chữ đi theo chiều cao màn hình, không đóng cứng: dòng đã cao lên theo màn thì chữ 18px cố
   định trông lọt thỏm giữa ô.
   PHẢI chia cho `--df-zoom`: `zoom` ở .ws2-root nhân mọi chiều dài bên trong, kể cả 1vh — không
   chia thì bấm A+ hai lần là chữ vọt lên gấp rưỡi trong khi ô chỉ to bằng đúng hệ số phóng.
   clamp() giữ hai đầu: không bao giờ nhỏ hơn 15px (đứng cách 1-2m còn đọc được) và không lớn hơn
   22px (quá đó thì mã DYE dài bị cắt cụt vì cột có bề rộng cố định). */
.cell {
  display: flex;
  align-items: center;
  padding: 0 10px;
  border-right: 1px solid #e3e7ee;
  font-size: clamp(15px, calc(2vh / var(--df-zoom, 1)), 22px);
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
  font-variant-numeric: tabular-nums;
  font-family: ui-monospace, 'Cascadia Mono', 'Consolas', 'Courier New', monospace;
}

.vba-head .cell-weight,
.vba-head .cell-process {
  justify-content: flex-start;
  font-family: inherit;
}

.cell-weight {
  color: #56617a;
}

/* Số cân thực tế — to hơn các cột khác vì đây là con số thợ đọc để biết đã đủ hay chưa. */
.cell-process {
  font-size: clamp(19px, calc(2.6vh / var(--df-zoom, 1)), 30px);
  font-weight: 800;
}

.vba-row:not(.is-empty):hover {
  background: #f7f9fc;
  cursor: pointer;
}

/* Dòng đang cân — dải xanh bên trái + nền nhạt để thấy ngay đang ở ô nào giữa 9 dòng, kể cả khi
   liếc nhanh từ xa. Nền nhạt thôi: ô PROCESS mới là chỗ mang tín hiệu màu, không được lấn át. */
.vba-row.is-current,
.vba-row.is-current:hover {
  background: #e8f0ff;
  box-shadow: inset 5px 0 0 #1f6bff;
}

.vba-row.is-current .cell-no {
  background: #1f6bff;
  color: #fff;
}

.vba-row.is-empty {
  background: #f7f8fa;
  color: #aab2c0;
}

.vba-input {
  width: 100%;
  /* Theo chiều cao dòng thay vì 38px cố định — dòng co giãn rồi thì ô nhập cũng phải theo, nếu
     không thì trên màn cao nó thành một khe hẹp lọt giữa dòng. */
  height: 72%;
  min-height: 30px;
  max-height: 52px;
  border: 1px solid #c2cad8;
  border-radius: 6px;
  font-size: clamp(15px, calc(2vh / var(--df-zoom, 1)), 22px);
  font-weight: 700;
  text-align: center;
  font-family: inherit;
  background: #fff;
  color: #0d1520;
  outline: none;
}

.vba-input:focus {
  border-color: #1f6bff;
  box-shadow: 0 0 0 3px rgba(31, 107, 255, 0.18);
}

.vba-input:disabled {
  background: #eef0f4;
}
</style>
