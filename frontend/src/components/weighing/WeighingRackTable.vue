<template>
  <div class="rack-table-wrap">
    <table class="data-table rack-table">
      <thead>
        <tr>
          <th class="col-rack">RACK</th>
          <th>DYE CODE</th>
          <th class="col-weight">WEIGHT</th>
          <th class="col-process">PROCESS</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(item, idx) in items"
          :key="item.id"
          :class="['rack-row', activeIndex === idx ? 'active' : item.status === 'COMPLETED' ? 'done' : isLocked(idx) ? 'locked' : 'pending']"
          @click="handleSelect(idx)"
        >
          <td class="col-rack">
            <input
              type="text"
              inputmode="numeric"
              class="form-control rack-input"
              :placeholder="'#' + item.sequence_no"
              v-model="item.rack_code"
              @click.stop="handleSelect(idx)"
            />
          </td>
          <td>
            <div class="item-name">{{ item.material?.name || item.material_code }}</div>
            <div class="item-code">Mã: <code>{{ item.material_code }}</code></div>
          </td>
          <td class="col-weight">
            <div class="target-w">Mục tiêu: {{ item.planned_weight.toFixed(2) }} g</div>
            <div v-if="item.status === 'COMPLETED'" class="actual-w text-success">
              Thực tế: <strong>{{ item.actual_weight.toFixed(2) }} g</strong>
            </div>
          </td>
          <td class="col-process">
            <span v-if="item.status === 'COMPLETED'" :class="item.override_approved ? 'text-danger' : 'text-success'">
              {{ item.override_approved ? '⚠️ Override' : '✅ Đạt' }}
            </span>
            <span v-else-if="activeIndex === idx" class="text-glow-blue">⚖️ Đang cân</span>
            <span v-else-if="isLocked(idx)" class="text-muted" title="Phải cân xong dòng phía trên mới tới lượt dòng này">🔒 Khóa</span>
            <span v-else class="text-muted">⏳ Chờ</span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
// Bảng 9 dòng RACK / DYE CODE / WEIGHT / PROCESS — port đúng layout scaleform.frm (VBA
// gốc: txt_RACK1-9 / txt_DYE1-9 / txt_WEIGHT1-9 / txt_PROCESS1-9), thay cho danh sách dọc
// theo material trước đây. rack_code sửa được ngay trên từng dòng (giống bàn phím số ảo
// NumClick/NumDel gõ vào ô rack đang chọn trong VBA — ở đây dùng input thường với
// inputmode="numeric" để bật bàn phím số cảm ứng, theo đúng tiền lệ đã chọn ở
// PrintStation.vue thay vì dựng lại 1 bàn phím ảo riêng).
const props = defineProps<{ items: any[]; activeIndex: number }>();
const emit = defineEmits<{ (e: 'select', idx: number): void }>();

// Cân lần lượt từ trên xuống (đúng thứ tự RACK/sequence_no trong scaleform VBA gốc) — dòng
// nào phía trên còn CHƯA cân xong thì các dòng phía dưới bị khóa, không cho nhảy cóc.
function isLocked(idx: number): boolean {
  const item = props.items[idx];
  if (item.status === 'COMPLETED') return false;
  return props.items.slice(0, idx).some((i) => i.status !== 'COMPLETED');
}

function handleSelect(idx: number) {
  const item = props.items[idx];
  // Đã cân xong = chốt vĩnh viễn, không cho chọn lại để sửa bì/cân lại nữa (phản hồi
  // 2026-07-30: "bì phải chốt từ lần đầu, cân 1 được 1 cái rồi thì không sửa được bì
  // nữa") — muốn cân lại thật sự phải qua nghiệp vụ riêng (hủy/override), không phải chỉ
  // bấm chọn lại dòng.
  if (item.status === 'COMPLETED') return;
  if (isLocked(idx)) return;
  emit('select', idx);
}
</script>

<style scoped>
.rack-table-wrap {
  margin-top: 12px;
  overflow-x: auto;
}

.rack-row {
  cursor: pointer;
  transition: background-color 0.15s ease;
}

.rack-row.active td {
  background-color: var(--bg-card-hover);
  box-shadow: inset 3px 0 0 var(--status-blue);
}

.rack-row.done {
  cursor: default;
}

.rack-row.done td {
  opacity: 0.8;
}

.rack-row.locked {
  cursor: not-allowed;
}

.rack-row.locked td {
  opacity: 0.5;
}

.col-rack {
  width: 90px;
}

.col-weight {
  width: 180px;
  text-align: right;
}

.col-process {
  width: 140px;
  text-align: center;
  font-size: 13px;
  font-weight: 600;
}

.rack-input {
  width: 70px;
  text-align: center;
  font-weight: 700;
  padding: 6px 4px;
}

.item-name {
  font-weight: 600;
  color: var(--text-title);
  font-size: 13px;
}

.item-code {
  font-size: 11px;
}

.target-w {
  font-size: 11px;
  color: var(--text-muted);
}

.actual-w {
  font-size: 13px;
}
</style>
