<template>
  <div>
    <div class="led-display-container">
      <div class="led-label">LIVE SCALE INDICATOR</div>
      <div :class="['led-numbers', toleranceStatus]">
        {{ liveWeight.toFixed(2) }} <span class="unit">g</span>
      </div>
      <div class="led-status-text" :class="toleranceStatus">
        {{ statusMessage }}
      </div>
      <div class="stable-indicator" :class="isStable ? 'is-stable' : 'is-unstable'">
        {{ isStable ? '✅ SỐ CÂN ỔN ĐỊNH' : '⏳ ĐANG CHỜ SỐ CÂN ỔN ĐỊNH...' }}
      </div>
    </div>

    <!-- Scale weight simulator slider (Always available for testing and safety backup) -->
    <div class="scale-simulator-box mt-4">
      <div class="header-sim">
        <span>🎛️ Giả lập số cân điện tử (Simulator)</span>
        <input type="checkbox" v-model="useSimValueModel" /> Bật giả lập
      </div>
      <div class="sim-controls" v-if="useSimValueModel">
        <input
          type="range"
          v-model.number="simulatedWeightModel"
          :min="0"
          :max="targetWeight * 1.5"
          step="0.1"
          class="sim-slider"
        />
        <input
          type="number"
          v-model.number="simulatedWeightModel"
          step="0.1"
          class="form-input sim-num-input"
        /> g
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  liveWeight: number;
  isStable: boolean;
  toleranceStatus: string;
  statusMessage: string;
  targetWeight: number;
  useSimValue: boolean;
  simulatedWeight: number;
}>();

const emit = defineEmits<{
  (e: 'update:useSimValue', value: boolean): void;
  (e: 'update:simulatedWeight', value: number): void;
}>();

const useSimValueModel = computed({
  get: () => props.useSimValue,
  set: (v: boolean) => emit('update:useSimValue', v),
});

const simulatedWeightModel = computed({
  get: () => props.simulatedWeight,
  set: (v: number) => emit('update:simulatedWeight', v),
});
</script>

<style scoped>
.led-display-container {
  background-color: #0b0f19;
  border: 2px solid #1a2236;
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  text-align: center;
  box-shadow: inset 0 0 20px rgba(0,0,0,0.8);
}

.led-label {
  font-family: 'Outfit', sans-serif;
  font-size: 10px;
  color: #3e527a;
  letter-spacing: 2px;
  font-weight: 700;
  margin-bottom: 8px;
}

.led-numbers {
  font-family: 'Courier New', monospace;
  font-size: 3.5rem;
  font-weight: 700;
  color: var(--text-muted);
  margin-bottom: 8px;
}

/* "zero" (cân rỗng, chưa đặt vật tư) phải khác màu với "insufficient" (đã có vật tư
   nhưng chưa đủ) — trước đây "zero" không có rule riêng nên rơi vào màu vàng mặc định
   của .led-numbers, trông giống hệt "insufficient" dù ý nghĩa hoàn toàn khác nhau. */
.led-numbers.zero {
  color: var(--text-muted);
}

.led-numbers.in-range {
  color: var(--status-green);
  text-shadow: 0 0 10px rgba(46, 204, 113, 0.5);
}

/* 3 mức đúng VBA CheckRange: vàng "chưa đủ" (insufficient) / xanh "đạt" (in-range) /
   đỏ "vượt" (over-range). */
.led-numbers.insufficient {
  color: var(--status-yellow);
  text-shadow: 0 0 10px rgba(241, 196, 15, 0.5);
}

.led-numbers.over-range {
  color: var(--status-red);
  text-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
}

.led-status-text {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-muted);
}

.led-status-text.zero { color: var(--text-muted); }
.led-status-text.in-range { color: var(--status-green); }
.led-status-text.insufficient { color: var(--status-yellow); }
.led-status-text.over-range { color: var(--status-red); }

.stable-indicator {
  margin-top: 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.stable-indicator.is-stable { color: var(--status-green); }
.stable-indicator.is-unstable { color: var(--status-yellow); }

.scale-simulator-box {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: 12px;
}

.header-sim {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-muted);
  align-items: center;
}

.sim-controls {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
}

.sim-slider {
  flex: 1;
}

.sim-num-input {
  width: 90px;
  padding: 4px 8px;
  font-size: 13px;
}
</style>
