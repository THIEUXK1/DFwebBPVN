<template>
  <div class="reports-container">
    <div class="panel-header mb-4">
      <h3>📊 {{ $t('reports.title') }}</h3>
      <p class="text-muted">{{ $t('reports.subtitle') }}</p>
    </div>

    <!-- Tabs -->
    <div class="tabs-nav-bar mb-4">
      <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" :class="['tab-nav-btn', { active: activeTab === tab.id }]">
        <span class="tab-icon">{{ tab.icon }}</span>
        <span class="tab-label">{{ tab.name }}</span>
      </button>
    </div>

    <!-- Shared date range + tab-specific filters -->
    <div class="card filter-bar mb-4">
      <div class="form-group">
        <label>{{ $t('reports.fromDate') }}</label>
        <input type="date" v-model="filters.from" class="form-input" />
      </div>
      <div class="form-group">
        <label>{{ $t('reports.toDate') }}</label>
        <input type="date" v-model="filters.to" class="form-input" />
      </div>

      <div class="form-group" v-if="activeTab === 'consumption' || activeTab === 'tolerance'">
        <label>{{ $t('reports.materialType') }}</label>
        <select v-model="filters.material_type" class="form-select">
          <option :value="null">{{ $t('reports.materialTypeAll') }}</option>
          <option value="DYE">{{ $t('reports.materialTypeDye') }}</option>
          <option value="CHEMICAL">{{ $t('reports.materialTypeChemical') }}</option>
        </select>
      </div>

      <div class="form-group" v-if="activeTab === 'output'">
        <label>{{ $t('reports.groupBy') }}</label>
        <select v-model="filters.group_by" class="form-select">
          <option value="day">{{ $t('reports.groupByDay') }}</option>
          <option value="month">{{ $t('reports.groupByMonth') }}</option>
          <option value="shift">{{ $t('reports.groupByShift') }}</option>
        </select>
      </div>

      <div class="filter-actions">
        <button class="btn btn-primary" @click="loadActiveTab" :disabled="loading">🔄 {{ $t('reports.applyButton') }}</button>
        <button class="btn btn-secondary" @click="exportFile('xlsx')" :disabled="loading || exporting">⬇️ {{ $t('reports.exportExcel') }}</button>
        <button class="btn btn-secondary" @click="exportFile('pdf')" :disabled="loading || exporting">⬇️ {{ $t('reports.exportPdf') }}</button>
      </div>
    </div>

    <div v-if="errorMessage" class="card error-card mb-4">{{ errorMessage }}</div>

    <!-- TAB 1: Dye/Chemical Consumption -->
    <div v-if="activeTab === 'consumption'" class="tab-panel">
      <div class="kpi-detail-grid mb-4">
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.consumptionPlannedTotal') }}</span>
          <div class="kpi-detail-value text-info">{{ fmt(consumption?.totals?.planned_total) }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.consumptionActualTotal') }}</span>
          <div class="kpi-detail-value text-info">{{ fmt(consumption?.totals?.actual_total) }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.consumptionVariancePct') }}</span>
          <div class="kpi-detail-value" :class="varianceClass(consumption?.totals?.variance_pct)">
            {{ consumption?.totals?.variance_pct ?? '—' }}%
          </div>
        </div>
      </div>

      <div class="card chart-card mb-4">
        <h4 class="chart-title">{{ $t('reports.consumptionChartTitle') }}</h4>
        <SimpleBarChart
          :categories="(consumption?.rows || []).map((r: any) => r.material_code)"
          :series="[
            { name: $t('reports.seriesPlanned'), color: '#3987e5', values: (consumption?.rows || []).map((r: any) => r.planned_total) },
            { name: $t('reports.seriesActual'), color: '#e66767', values: (consumption?.rows || []).map((r: any) => r.actual_total) },
          ]"
        />
      </div>

      <div class="card">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('reports.colMaterialCode') }}</th><th>{{ $t('reports.colName') }}</th><th>{{ $t('reports.colType') }}</th><th>{{ $t('reports.colWeighCount') }}</th>
                <th>{{ $t('reports.colPlanned') }}</th><th>{{ $t('reports.colActual') }}</th><th>{{ $t('reports.colVarianceG') }}</th><th>{{ $t('reports.colVariancePct') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in consumption?.rows" :key="row.material_code">
                <td>{{ row.material_code }}</td>
                <td>{{ row.material_name }}</td>
                <td><span class="badge badge-blue">{{ row.material_type }}</span></td>
                <td>{{ row.weigh_count }}</td>
                <td>{{ fmt(row.planned_total) }}</td>
                <td>{{ fmt(row.actual_total) }}</td>
                <td :class="varianceClass(row.variance_pct)">{{ fmt(row.variance) }}</td>
                <td :class="varianceClass(row.variance_pct)">{{ row.variance_pct ?? '—' }}%</td>
              </tr>
              <tr v-if="!consumption?.rows?.length"><td colspan="8" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 2: Dung sai & Không đạt — từ 2026-07-30 không còn luồng override có phê duyệt
         (port y hệt VBA), mọi lần cân đều lưu được và chỉ gắn nhãn ĐẠT/KHÔNG ĐẠT. -->
    <div v-if="activeTab === 'tolerance'" class="tab-panel">
      <div class="kpi-detail-grid mb-4">
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.toleranceTotalWeighed') }}</span>
          <div class="kpi-detail-value text-info">{{ tolerance?.summary?.total_weighed ?? 0 }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.toleranceTotalReject') }}</span>
          <div class="kpi-detail-value text-warning">{{ tolerance?.summary?.total_reject ?? 0 }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.toleranceRejectRate') }}</span>
          <div class="kpi-detail-value text-warning">{{ tolerance?.summary?.reject_rate_pct ?? 0 }}%</div>
        </div>
      </div>

      <div class="card chart-card mb-4">
        <h4 class="chart-title">{{ $t('reports.toleranceChartTitle') }}</h4>
        <SimpleBarChart
          :categories="(tolerance?.by_material || []).map((r: any) => r.material_code)"
          :series="[
            { name: $t('reports.seriesTotalWeighed'), color: '#3987e5', values: (tolerance?.by_material || []).map((r: any) => r.total_weighed) },
            { name: $t('reports.seriesReject'), color: '#e66767', values: (tolerance?.by_material || []).map((r: any) => r.reject_count) },
          ]"
        />
      </div>

      <div class="card mb-4">
        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr><th>{{ $t('reports.colMaterialCode') }}</th><th>{{ $t('reports.colName') }}</th><th>{{ $t('reports.colWeighCount') }}</th><th>{{ $t('reports.colRejectCount') }}</th><th>{{ $t('reports.colRejectRatePct') }}</th><th>{{ $t('reports.colAvgDeviationPct') }}</th><th>{{ $t('reports.colMaxDeviationPct') }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in tolerance?.by_material" :key="row.material_code">
                <td>{{ row.material_code }}</td>
                <td>{{ row.material_name }}</td>
                <td>{{ row.total_weighed }}</td>
                <td>{{ row.reject_count }}</td>
                <td><span :class="['badge', row.reject_rate_pct > 10 ? 'badge-red' : 'badge-green']">{{ row.reject_rate_pct }}%</span></td>
                <td>{{ row.avg_deviation_pct ?? '—' }}%</td>
                <td>{{ row.max_deviation_pct ?? '—' }}%</td>
              </tr>
              <tr v-if="!tolerance?.by_material?.length"><td colspan="7" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h4 class="chart-title">{{ $t('reports.byMachineTitle') }}</h4>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>{{ $t('reports.colMachine') }}</th><th>{{ $t('reports.colWeighCount') }}</th><th>{{ $t('reports.colRejectCount') }}</th><th>{{ $t('reports.colRejectRatePct') }}</th></tr></thead>
            <tbody>
              <tr v-for="row in tolerance?.by_machine" :key="row.machine_code">
                <td>{{ row.machine_code }}</td>
                <td>{{ row.total_weighed }}</td>
                <td>{{ row.reject_count }}</td>
                <td>{{ row.reject_rate_pct }}%</td>
              </tr>
              <tr v-if="!tolerance?.by_machine?.length"><td colspan="4" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 3: Machine Output -->
    <div v-if="activeTab === 'output'" class="tab-panel">
      <div class="kpi-detail-grid mb-4">
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.outputTotalBatches') }}</span>
          <div class="kpi-detail-value text-info">{{ output?.summary?.total_batches ?? 0 }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.outputTotalClothWeight') }}</span>
          <div class="kpi-detail-value text-info">{{ fmt(output?.summary?.total_cloth_weight) }}</div>
        </div>
      </div>

      <div class="card chart-card mb-4">
        <h4 class="chart-title">{{ $t('reports.outputChartTitle', { period: groupByLabel }) }}</h4>
        <SimpleBarChart
          :categories="outputCategories"
          :series="outputSeries"
          :height="320"
        />
      </div>

      <div class="card">
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>{{ $t('reports.colMachineDye') }}</th><th>{{ $t('reports.colPeriod') }}</th><th>{{ $t('reports.colBatchCount') }}</th><th>{{ $t('reports.colTotalClothWeight') }}</th></tr></thead>
            <tbody>
              <tr v-for="(row, i) in output?.rows" :key="i">
                <td>{{ row.machine_code }}</td>
                <td>{{ row.period }}</td>
                <td>{{ row.batch_count }}</td>
                <td>{{ fmt(row.total_cloth_weight) }}</td>
              </tr>
              <tr v-if="!output?.rows?.length"><td colspan="4" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TAB 4: Troubleshooting Pareto -->
    <div v-if="activeTab === 'pareto'" class="tab-panel">
      <div class="kpi-detail-grid mb-4">
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.paretoTotalCases') }}</span>
          <div class="kpi-detail-value text-info">{{ pareto?.summary?.total_cases ?? 0 }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.paretoResolvedCases') }}</span>
          <div class="kpi-detail-value text-success">{{ pareto?.summary?.resolved_cases ?? 0 }}</div>
        </div>
        <div class="card kpi-detail-card">
          <span class="text-muted">{{ $t('reports.paretoResolutionRate') }}</span>
          <div class="kpi-detail-value text-success">{{ pareto?.summary?.resolution_rate_pct ?? 0 }}%</div>
        </div>
      </div>

      <div class="card chart-card mb-4">
        <h4 class="chart-title">{{ $t('reports.paretoChartTitle') }}</h4>
        <ParetoChart
          :labels="(pareto?.pareto_causes || []).map((c: any) => c.cause_name)"
          :counts="(pareto?.pareto_causes || []).map((c: any) => c.case_count)"
          :pct="(pareto?.pareto_causes || []).map((c: any) => c.pct)"
          :cumulative-pct="(pareto?.pareto_causes || []).map((c: any) => c.cumulative_pct)"
        />
      </div>

      <div class="card mb-4">
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>{{ $t('reports.colCause') }}</th><th>{{ $t('reports.colCaseCount') }}</th><th>{{ $t('reports.colPct') }}</th><th>{{ $t('reports.colCumulativePct') }}</th></tr></thead>
            <tbody>
              <tr v-for="c in pareto?.pareto_causes" :key="c.cause_id">
                <td>{{ c.cause_name }}</td>
                <td>{{ c.case_count }}</td>
                <td>{{ c.pct }}%</td>
                <td>{{ c.cumulative_pct }}%</td>
              </tr>
              <tr v-if="!pareto?.pareto_causes?.length"><td colspan="4" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <h4 class="chart-title">{{ $t('reports.byProblemTitle') }}</h4>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>{{ $t('reports.colProblem') }}</th><th>{{ $t('reports.colOccurrenceCount') }}</th></tr></thead>
            <tbody>
              <tr v-for="p in pareto?.by_problem" :key="p.problem_id">
                <td>{{ p.problem_name }}</td>
                <td>{{ p.occurrence_count }}</td>
              </tr>
              <tr v-if="!pareto?.by_problem?.length"><td colspan="2" class="text-muted text-center">{{ $t('reports.noDataDot') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import SimpleBarChart from '../components/charts/SimpleBarChart.vue';
import ParetoChart from '../components/charts/ParetoChart.vue';

const { t } = useI18n({ useScope: 'global' });

const tabs = computed(() => [
  { id: 'consumption', name: t('reports.tabConsumption'), icon: '🧪' },
  { id: 'tolerance', name: t('reports.tabTolerance'), icon: '⚖️' },
  { id: 'output', name: t('reports.tabOutput'), icon: '🏭' },
  { id: 'pareto', name: t('reports.tabPareto'), icon: '📈' },
]);

const activeTab = ref('consumption');
const loading = ref(false);
const exporting = ref(false);
const errorMessage = ref('');

function defaultDate(daysAgo: number) {
  const d = new Date();
  d.setDate(d.getDate() - daysAgo);
  return d.toISOString().slice(0, 10);
}

const filters = reactive({
  from: defaultDate(30),
  to: defaultDate(0),
  material_type: null as string | null,
  group_by: 'day',
});

const consumption = ref<any>(null);
const tolerance = ref<any>(null);
const output = ref<any>(null);
const pareto = ref<any>(null);

const endpointMap: Record<string, string> = {
  consumption: '/api/reports/dye-consumption',
  tolerance: '/api/reports/tolerance-stats',
  output: '/api/reports/machine-output',
  pareto: '/api/reports/troubleshooting-pareto',
};

function buildParams() {
  const params: Record<string, string> = { from: filters.from, to: filters.to };
  if (activeTab.value === 'consumption' || activeTab.value === 'tolerance') {
    if (filters.material_type) params.material_type = filters.material_type;
  }
  if (activeTab.value === 'output') {
    params.group_by = filters.group_by;
  }
  return params;
}

async function loadActiveTab() {
  loading.value = true;
  errorMessage.value = '';
  try {
    const res = await axios.get(endpointMap[activeTab.value], { params: buildParams() });
    const data = res.data?.data;
    if (activeTab.value === 'consumption') consumption.value = data;
    if (activeTab.value === 'tolerance') tolerance.value = data;
    if (activeTab.value === 'output') output.value = data;
    if (activeTab.value === 'pareto') pareto.value = data;
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || t('reports.loadErrorDefault');
  } finally {
    loading.value = false;
  }
}

async function exportFile(format: 'xlsx' | 'pdf') {
  exporting.value = true;
  errorMessage.value = '';
  try {
    const res = await axios.get(endpointMap[activeTab.value], {
      params: { ...buildParams(), format },
      responseType: 'blob',
    });
    const blob = new Blob([res.data]);
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bao-cao-${activeTab.value}-${filters.from}-${filters.to}.${format}`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
  } catch (err: any) {
    errorMessage.value = t('reports.exportError');
  } finally {
    exporting.value = false;
  }
}

watch(activeTab, () => loadActiveTab());
onMounted(() => loadActiveTab());

function fmt(v: number | null | undefined) {
  if (v === null || v === undefined) return '—';
  return v.toLocaleString('vi-VN', { maximumFractionDigits: 2 });
}
function varianceClass(pct: number | null | undefined) {
  if (pct === null || pct === undefined) return '';
  return Math.abs(pct) > 2 ? 'text-error' : 'text-success';
}

const groupByLabel = computed(() => ({
  day: t('reports.groupByLabelDay'),
  month: t('reports.groupByLabelMonth'),
  shift: t('reports.groupByLabelShift'),
}[filters.group_by] || t('reports.groupByLabelDay')));

// Pivot the (machine, period) rows into a per-period category with one series per machine,
// so multiple machines stay readable on a single chart instead of one bar per row.
const outputCategories = computed(() => {
  const periods = new Set<string>((output.value?.rows || []).map((r: any) => r.period));
  return Array.from(periods).sort();
});
const outputSeries = computed(() => {
  const rows = output.value?.rows || [];
  const machines = Array.from(new Set(rows.map((r: any) => r.machine_code)));
  const palette = ['#3987e5', '#d95926', '#199e70', '#9085e9', '#c98500', '#e66767', '#d55181', '#008300'];
  return machines.map((code, i) => ({
    name: code as string,
    color: palette[i % palette.length],
    values: outputCategories.value.map(period => {
      const row = rows.find((r: any) => r.machine_code === code && r.period === period);
      return row ? row.total_cloth_weight : 0;
    }),
  }));
});
</script>

<style scoped>
.reports-container {
  display: flex;
  flex-direction: column;
}

.tabs-nav-bar {
  display: flex;
  gap: var(--space-sm);
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  padding: 6px;
  border-radius: var(--radius-lg);
  overflow-x: auto;
}

.tab-nav-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-sm);
  height: 38px;
  padding: 0 var(--space-lg);
  border-radius: var(--radius-md);
  font-weight: 600;
  color: var(--text-muted);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}
.tab-nav-btn:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}
.tab-nav-btn.active {
  background-color: var(--primary);
  color: var(--text-white);
  box-shadow: var(--shadow-sm);
}

.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-lg);
  align-items: flex-end;
  padding: var(--space-lg) var(--space-xl) !important;
}
.filter-bar .form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 160px;
}
.filter-bar label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 600;
}
.filter-actions {
  display: flex;
  gap: var(--space-sm);
  margin-left: auto;
}

.error-card {
  color: var(--status-red);
  border-color: var(--status-red-border);
  background-color: var(--status-red-bg);
  padding: var(--space-lg) !important;
}

.chart-card {
  padding: var(--space-xl) !important;
}
.chart-title {
  margin: 0 0 var(--space-lg) 0;
  font-size: 1rem;
  color: var(--text-title);
}

.kpi-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-xl);
}
.kpi-detail-card {
  text-align: center;
  padding: var(--space-2xl) var(--space-xl) !important;
}
.kpi-detail-value {
  font-family: 'Outfit', sans-serif;
  font-size: 2.2rem;
  font-weight: 800;
  margin: var(--space-sm) 0;
  color: var(--text-title);
}

.text-center { text-align: center; }
</style>
