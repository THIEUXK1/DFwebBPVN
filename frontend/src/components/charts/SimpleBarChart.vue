<template>
  <div class="viz-root">
    <div v-if="series.length > 1" class="viz-legend">
      <span v-for="s in series" :key="s.name" class="legend-item">
        <span class="legend-swatch" :style="{ backgroundColor: s.color }"></span>
        {{ s.name }}
      </span>
    </div>

    <svg v-if="categories.length" :viewBox="`0 0 ${width} ${height}`" class="viz-svg" preserveAspectRatio="xMidYMid meet">
      <!-- Gridlines -->
      <g v-for="(tick, i) in yTicks" :key="'grid-' + i">
        <line :x1="padLeft" :x2="width - padRight" :y1="yScale(tick)" :y2="yScale(tick)" class="viz-gridline" />
        <text :x="padLeft - 8" :y="yScale(tick) + 4" class="viz-axis-label" text-anchor="end">{{ formatTick(tick) }}</text>
      </g>

      <!-- Baseline -->
      <line :x1="padLeft" :x2="width - padRight" :y1="yScale(0)" :y2="yScale(0)" class="viz-baseline" />

      <!-- Bars -->
      <g v-for="(cat, ci) in categories" :key="'cat-' + ci">
        <g v-for="(s, si) in series" :key="'s-' + si">
          <rect
            :x="barX(ci, si)"
            :y="yScale(Math.max(s.values[ci], 0))"
            :width="barWidth"
            :height="Math.max(Math.abs(yScale(s.values[ci]) - yScale(0)), 0)"
            :fill="s.color"
            rx="3"
          >
            <title>{{ cat }} · {{ s.name }}: {{ formatValue(s.values[ci]) }}</title>
          </rect>
          <text
            v-if="showDirectLabels"
            :x="barX(ci, si) + barWidth / 2"
            :y="yScale(Math.max(s.values[ci], 0)) - 6"
            class="viz-direct-label"
            text-anchor="middle"
          >{{ formatValue(s.values[ci]) }}</text>
        </g>
        <text
          :x="groupCenter(ci)"
          :y="height - padBottom + 18"
          class="viz-axis-label"
          text-anchor="middle"
        >{{ truncateLabel(cat) }}</text>
      </g>
    </svg>

    <div v-else class="viz-empty">Không có dữ liệu để hiển thị.</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
  categories: string[];
  series: { name: string; color: string; values: number[] }[];
  height?: number;
  formatValue?: (v: number) => string;
  showDirectLabels?: boolean;
}>(), {
  height: 280,
  showDirectLabels: true,
});

const width = 720;
const padLeft = 56;
const padRight = 16;
const padTop = 20;
const padBottom = 40;

const height = computed(() => props.height);

const maxValue = computed(() => {
  const all = props.series.flatMap(s => s.values);
  const m = Math.max(0, ...all);
  return m === 0 ? 1 : m;
});

const yTicks = computed(() => {
  const step = maxValue.value / 4;
  return [0, step, step * 2, step * 3, step * 4];
});

function yScale(v: number) {
  const usable = height.value - padTop - padBottom;
  return height.value - padBottom - (v / maxValue.value) * usable;
}

const groupWidth = computed(() => (width - padLeft - padRight) / Math.max(props.categories.length, 1));
const barWidth = computed(() => {
  const n = Math.max(props.series.length, 1);
  return Math.min(40, (groupWidth.value * 0.7) / n);
});

function groupCenter(ci: number) {
  return padLeft + groupWidth.value * ci + groupWidth.value / 2;
}
function barX(ci: number, si: number) {
  const n = props.series.length;
  const totalBarsWidth = barWidth.value * n;
  const startX = groupCenter(ci) - totalBarsWidth / 2;
  return startX + si * barWidth.value;
}

function formatTick(v: number) {
  return props.formatValue ? props.formatValue(v) : Math.round(v).toLocaleString('vi-VN');
}
function formatValue(v: number) {
  return props.formatValue ? props.formatValue(v) : v.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
}
function truncateLabel(s: string) {
  return s.length > 12 ? s.slice(0, 11) + '…' : s;
}
</script>

<style scoped>
.viz-root {
  --viz-text-primary: var(--text-title);
  --viz-text-muted: var(--text-muted);
  --viz-gridline: var(--border-divider);
  color-scheme: dark;
}
.viz-legend {
  display: flex;
  gap: var(--space-lg);
  margin-bottom: var(--space-md);
  font-size: 0.8rem;
  color: var(--viz-text-muted);
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
}
.legend-swatch {
  width: 10px;
  height: 10px;
  border-radius: 2px;
  display: inline-block;
}
.viz-svg {
  width: 100%;
  height: auto;
}
.viz-gridline {
  stroke: var(--viz-gridline);
  stroke-width: 1;
}
.viz-baseline {
  stroke: var(--viz-text-muted);
  stroke-width: 1;
}
.viz-axis-label {
  fill: var(--viz-text-muted);
  font-size: 11px;
}
.viz-direct-label {
  fill: var(--viz-text-primary);
  font-size: 10px;
  font-weight: 600;
}
.viz-empty {
  padding: var(--space-2xl);
  text-align: center;
  color: var(--viz-text-muted);
}
</style>
