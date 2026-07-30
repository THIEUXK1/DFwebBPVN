<template>
  <div class="tolerance-details-box mt-4" v-if="activeIngredient">
    <h4>Vật tư đang cân:</h4>
    <div class="target-meta">
      <div class="meta-row">
        <span class="label">Tên hóa chất:</span>
        <span class="value">{{ activeIngredient.material?.name || activeIngredient.material_code }}</span>
      </div>
      <div class="meta-row">
        <span class="label">Mục tiêu:</span>
        <span class="value bold-text text-glow">{{ activeIngredient.planned_weight.toFixed(2) }} g</span>
      </div>
      <div class="meta-row">
        <span class="label">Dung sai cho phép:</span>
        <span class="value text-success font-code">
          {{ minAllowed.toFixed(2) }} g - {{ maxAllowed.toFixed(2) }} g
        </span>
      </div>
      <div class="meta-row" v-if="deviationPercent !== 0">
        <span class="label">Sai số thực tế:</span>
        <span :class="['value', toleranceStatus === 'in-range' ? 'text-success' : 'text-danger']">
          {{ deviationVal.toFixed(2) }} g ({{ deviationPercent.toFixed(2) }}%)
        </span>
      </div>
    </div>

    <!-- Trừ bì (tare) — CHỦ ĐỘNG bấm nút xác nhận thay vì tự khóa bì vào lần đọc ổn định
         đầu tiên (yêu cầu 2026-07-30): đặt cốc/khay/thau rỗng lên cân, chờ số đứng ổn định,
         rồi bấm "Bắt đầu cân" để chốt đây là bì — tránh khóa nhầm khi cân chưa kịp đứng
         đúng ý thao tác viên. -->
    <div class="tare-info-box mt-3">
      <div v-if="tareBaseline === null" class="tare-pending-box">
        <span class="text-muted font-xs">
          {{ isStable ? `Cân gộp hiện tại: ${grossWeight.toFixed(2)} g — đã đứng ổn định.` : '⏳ Đặt cốc/khay/thau rỗng lên cân, chờ số đứng ổn định...' }}
        </span>
        <button
          @click="$emit('start-weighing')"
          class="btn btn-secondary btn-sm mt-2"
          :disabled="!isStable || viewOnly"
        >
          ▶️ Bắt đầu cân (chốt bì {{ grossWeight.toFixed(2) }} g)
        </button>
      </div>
      <div v-else class="tare-confirmed-box">
        <span class="font-xs text-muted">
          Bì: <strong>{{ tareBaseline.toFixed(2) }} g</strong> ·
          Cân gộp: <strong>{{ grossWeight.toFixed(2) }} g</strong> ·
          Thực (net): <strong class="text-success">{{ liveWeight.toFixed(2) }} g</strong>
        </span>
        <!-- Lỡ chốt sai bì (đặt lệch, rung lúc bấm, nhầm cốc...) — cân lại từ đầu cho ĐÚNG
             vật tư đang đứng dở này, không cần chuyển sang vật tư khác rồi quay lại. -->
        <button
          @click="$emit('retare')"
          class="btn btn-secondary btn-sm mt-2"
          :disabled="viewOnly"
          title="Bỏ bì hiện tại, đặt lại cốc/khay lên cân và bấm Bắt đầu cân lại từ đầu"
        >
          🔄 Cân lại bì
        </button>
      </div>
    </div>

    <!-- Exception override triggers — dùng toleranceStatus thay vì "liveWeight > 0" vì net
         giờ có thể ÂM (cân cộng dồn bị hao hụt so với bì); so sánh > 0 sẽ vô tình ẩn mất
         khung override đúng lúc cần nó nhất (phản hồi 2026-07-30). -->
    <div class="override-box mt-3" v-if="toleranceStatus !== 'in-range' && toleranceStatus !== 'zero'">
      <div class="override-checkbox">
        <input type="checkbox" v-model="overrideApprovedModel" id="chk-override" :disabled="viewOnly" />
        <label for="chk-override"><strong>⚠️ Yêu cầu Override Dung sai (Cần QA/QC phê duyệt)</strong></label>
      </div>
      <textarea
        v-if="overrideApprovedModel"
        v-model="overrideReasonModel"
        placeholder="Nhập lý do override dung sai..."
        class="form-input mt-2"
        rows="2"
      ></textarea>
    </div>

    <!-- Confirm button -->
    <div class="action-buttons-group mt-4">
      <button
        @click="$emit('confirm')"
        class="weigh-confirm-btn"
        :disabled="(toleranceStatus !== 'in-range' && !overrideApprovedModel) || viewOnly || !isStable"
        :title="!isStable ? 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau (PB-2)' : ''"
      >
        ⚖️ Xác nhận lưu số cân (Save Weight)
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  activeIngredient: any | null;
  liveWeight: number;
  isStable: boolean;
  toleranceStatus: string;
  minAllowed: number;
  maxAllowed: number;
  deviationVal: number;
  deviationPercent: number;
  tareBaseline: number | null;
  grossWeight: number;
  overrideApproved: boolean;
  overrideReason: string;
  viewOnly: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:overrideApproved', value: boolean): void;
  (e: 'update:overrideReason', value: string): void;
  (e: 'confirm'): void;
  (e: 'start-weighing'): void;
  (e: 'retare'): void;
}>();

const overrideApprovedModel = computed({
  get: () => props.overrideApproved,
  set: (v: boolean) => emit('update:overrideApproved', v),
});

const overrideReasonModel = computed({
  get: () => props.overrideReason,
  set: (v: string) => emit('update:overrideReason', v),
});
</script>

<style scoped>
.tolerance-details-box {
  background-color: var(--bg-main);
  padding: 16px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.target-meta {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 10px;
}

.meta-row {
  display: flex;
  justify-content: space-between;
}

.tare-info-box {
  padding: 8px 10px;
  background-color: var(--bg-sidebar);
  border: 1px dashed var(--border-divider);
  border-radius: var(--radius-md);
  text-align: center;
}

.tare-pending-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.tare-confirmed-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}

.weigh-confirm-btn {
  width: 100%;
  padding: 14px;
  font-size: 1.1rem;
  background-color: var(--status-green);
  border: 1px solid var(--status-green);
  color: #fff;
  border-radius: var(--radius-md);
  font-weight: 700;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  transition: all 0.2s;
}

.weigh-confirm-btn:hover:not(:disabled) {
  opacity: 0.9;
  transform: translateY(-1px);
}

.weigh-confirm-btn:disabled {
  background-color: var(--border-divider);
  border-color: var(--border-divider);
  color: var(--text-muted);
  cursor: not-allowed;
}

.override-box {
  background-color: rgba(231, 76, 60, 0.05);
  border: 1px dashed var(--status-red);
  padding: 12px;
  border-radius: var(--radius-md);
}

.override-checkbox {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
</style>
