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

    <!-- Cảnh báo ngoài dung sai — chỉ BÁO, không chặn lưu: port đúng VBA btnSave_Click, thao
         tác viên lưu được mọi lần cân, hệ thống gắn nhãn ĐẠT/KHÔNG ĐẠT (yêu cầu 2026-07-30).
         Không còn luồng override (checkbox + lý do + PIN Giám sát). -->
    <div class="reject-warn-box mt-3" v-if="toleranceStatus !== 'in-range' && toleranceStatus !== 'zero'">
      <strong>⚠️ Ngoài dung sai — dòng này sẽ được lưu với nhãn KHÔNG ĐẠT.</strong>
    </div>

    <!-- Confirm button — chỉ còn chặn khi số cân chưa ổn định (StableFilter), đúng VBA:
         CheckRange/lưu chỉ chạy trên số đã ổn định 2 lần đọc liên tiếp. -->
    <div class="action-buttons-group mt-4">
      <button
        @click="$emit('confirm')"
        class="weigh-confirm-btn"
        :disabled="viewOnly || !isStable"
        :title="!isStable ? 'Số cân chưa ổn định — chờ 2 lần đọc liên tiếp giống nhau (PB-2)' : ''"
      >
        ⚖️ Xác nhận lưu số cân (Save Weight)
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
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
  viewOnly: boolean;
}>();

defineEmits<{
  (e: 'confirm'): void;
  (e: 'start-weighing'): void;
  (e: 'retare'): void;
}>();
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

.reject-warn-box {
  background-color: rgba(231, 76, 60, 0.05);
  border: 1px dashed var(--status-red);
  color: var(--status-red);
  padding: 12px;
  border-radius: var(--radius-md);
  font-size: 0.9rem;
}
</style>
