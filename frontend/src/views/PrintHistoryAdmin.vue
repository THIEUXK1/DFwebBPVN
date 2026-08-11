<template>
  <div class="print-history-admin">
    <div class="page-header mb-4">
      <h2>{{ $t('printHistoryAdmin.pageTitle') }}</h2>
      <p class="text-muted font-sm">{{ $t('printHistoryAdmin.pageDesc') }}</p>
    </div>

    <div class="card-sec filter-panel mb-4">
      <div class="filter-row">
        <div class="form-group">
          <label>{{ $t('printHistoryAdmin.stationLabel') }}</label>
          <select v-model="filters.station_code" class="form-select">
            <option value="">{{ $t('printHistoryAdmin.allStationsOption') }}</option>
            <option v-for="ws in workstationsList" :key="ws.id" :value="ws.code">{{ ws.code }} - {{ ws.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>{{ $t('printHistoryAdmin.printStatusLabel') }}</label>
          <select v-model="filters.print_status" class="form-select">
            <option value="">{{ $t('printHistoryAdmin.allOption') }}</option>
            <option value="PENDING">{{ $t('printHistoryAdmin.statusPending') }}</option>
            <option value="PRINTED">{{ $t('printHistoryAdmin.statusPrinted') }}</option>
            <option value="FAILED">{{ $t('printHistoryAdmin.statusFailed') }}</option>
            <option value="CANCELLED">{{ $t('printHistoryAdmin.statusCancelled') }}</option>
          </select>
        </div>
        <div class="form-group">
          <label>{{ $t('printHistoryAdmin.fromDateLabel') }}</label>
          <input v-model="filters.from" type="date" class="form-control" />
        </div>
        <div class="form-group">
          <label>{{ $t('printHistoryAdmin.toDateLabel') }}</label>
          <input v-model="filters.to" type="date" class="form-control" />
        </div>
        <div class="form-group flex-2">
          <label>{{ $t('printHistoryAdmin.searchLabel') }}</label>
          <input v-model="filters.q" type="text" class="form-control" :placeholder="$t('printHistoryAdmin.searchPlaceholder')" @keyup.enter="fetchHistory" />
        </div>
        <button class="btn btn-primary" @click="fetchHistory" :disabled="loading">{{ loading ? $t('common.loading') : $t('common.filter') }}</button>
        <button class="btn btn-secondary" @click="resetFilters">{{ $t('printHistoryAdmin.resetFiltersButton') }}</button>
      </div>
    </div>

    <PrintJobHistoryTable :rows="history" @refresh="fetchHistory" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import PrintJobHistoryTable from '../components/PrintJobHistoryTable.vue';
import { workstationsList, fetchWorkstations } from '../services/workstation';

const history = ref<any[]>([]);
const loading = ref(false);
const filters = reactive({ station_code: '', print_status: '', from: '', to: '', q: '' });

async function fetchHistory() {
  loading.value = true;
  try {
    const params: Record<string, string> = {};
    Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v as string; });
    const res = await axios.get('/api/machine-dispatches/history', { params });
    history.value = res.data.data || [];
  } catch (err) {
    console.error('Error fetching admin print history:', err);
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.station_code = '';
  filters.print_status = '';
  filters.from = '';
  filters.to = '';
  filters.q = '';
  fetchHistory();
}

onMounted(() => {
  fetchWorkstations();
  fetchHistory();
});
</script>

<style scoped>
.page-header h2 { margin: 0 0 4px 0; color: var(--text-title); }
.filter-panel { padding: var(--space-lg) var(--space-xl); }
.filter-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md);
  align-items: end;
}
.filter-row .form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 160px;
}
.filter-row .flex-2 { flex: 2; min-width: 220px; }
.filter-row label { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }
</style>
