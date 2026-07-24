<template>
  <div class="viz-root">
    <div class="viz-legend">
      <span class="legend-item">
        <span class="legend-swatch" :style="{ backgroundColor: barColor }"></span>
        Tỉ lệ theo nguyên nhân (%)
      </span>
      <span class="legend-item">
        <span class="legend-line" :style="{ backgroundColor: lineColor }"></span>
        Tỉ lệ tích lũy (%)
      </span>
      <span class="legend-item">
        <span class="legend-dash"></span>
        Ngưỡng 80%
      </span>
    </div>

    <svg v-if="labels.length" :viewBox="`0 0 ${width} ${height}`" class="viz-svg" preserveAspectRatio="xMidYMid meet">
      <!-- 0-100% gridlines, single shared axis -->
      <g v-for="tick in [0, 25, 50, 75, 100]" :key="'grid-' + tick">
        <line :x1="padLeft" :x2="width - padRight" :y1="yScale(tick)" :y2="yScale(tick)" class="viz-gridline" />
        <text :x="padLeft - 8" :y="yScale(tick) + 4" class="viz-axis-label" text-anchor="end">{{ tick }}%</text>
      </g>

      <!-- 80% Pareto threshold reference -->
      <line :x1="padLeft" :x2="width - padRight" :y1="yScale(80)" :y2="yScale(80)" class="viz-threshold" />

      <!-- Bars: pct of total per cause -->
      <g v-for="(label, i) in labels" :key="'bar-' + i">
        <rect
          :x="barX(i)"
          :y="yScale(pct[i])"
          :width="barWidth"
          :height="yScale(0) - yScale(pct[i])"
          :fill="barColor"
          rx="3"
        >
          <title>{{ label }}: {{ counts[i] }} ca ({{ pct[i] }}%)</title>
        </rect>
        <text :x="barX(i) + barWidth / 2" :y="yScale(pct[i]) - 6" class="viz-direct-label" text-anchor="middle">{{ counts[i] }}</text>
        <text :x="groupCenter(i)" :y="height - padBottom + 18" class="viz-axis-label" text-anchor="middle">{{ truncateLabel(label) }}</text>
      </g>

      <!-- Cumulative % line, same 0-100 axis as the bars -->
      <polyline :points="linePoints" fill="none" :stroke="lineColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
      <g v-for="(label, i) in labels" :key="'pt-' + i">
        <circle :cx="groupCenter(i)" :cy="yScale(cumulativePct[i])" r="3.5" :fill="lineColor">
          <title>{{ label }} · Tích lũy: {{ cumulativePct[i] }}%</title>
        </circle>
      </g>
    </svg>

    <div v-else class="viz-empty">Không có dữ liệu sự cố trong khoảng thời gian đã chọn.</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
  labels: string[];
  counts: number[];
  pct: number[];
  cumulativePct: number[];
  height?: number;
}>();

const barColor = '#3987e5'; // dataviz categorical slot 1 (blue, dark-mode step)
const lineColor = '#9085e9'; // dataviz categorical slot 7 (violet, dark-mode step)

const width = 720;
const padLeft = 48;
const padRight = 16;
const padTop = 20;
const padBottom = 44;
const height = computed(() => props.height ?? 280);

function yScale(v: number) {
  const usable = height.value - padTop - padBottom;
  return height.value - padBottom - (v / 100) * usable;
}

const groupWidth = computed(() => (width - padLeft - padRight) / Math.max(props.labels.length, 1));
const barWidth = computed(() => Math.min(48, groupWidth.value * 0.5));

function groupCenter(i: number) {
  return padLeft + groupWidth.value * i + groupWidth.value / 2;
}
function barX(i: number) {
  return groupCenter(i) - barWidth.value / 2;
}
function truncateLabel(s: string) {
  return s.length > 14 ? s.slice(0, 13) + '…' : s;
}

const linePoints = computed(() =>
  props.labels.map((_, i) => `${groupCenter(i)},${yScale(props.cumulativePct[i])}`).join(' ')
);
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
  flex-wrap: wrap;
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
.legend-line {
  width: 14px;
  height: 2px;
  display: inline-block;
}
.legend-dash {
  width: 14px;
  height: 0;
  border-top: 1px dashed var(--text-disabled);
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
.viz-threshold {
  stroke: var(--text-disabled);
  stroke-width: 1;
  stroke-dasharray: 4 3;
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
