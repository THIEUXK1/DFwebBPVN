<template>
  <div class="audit-container">
    <div class="panel-header mb-4">
      <h3>🕵️ Audit Log Explorer</h3>
      <p class="text-muted">Tra cứu nhật ký thay đổi bất biến (phê duyệt công thức, override dung sai/cấp máy, reprint tem, force unlock...).</p>
    </div>

    <div class="card filter-bar mb-4">
      <div class="form-group">
        <label>Từ ngày</label>
        <input type="date" v-model="filters.from" class="form-input" />
      </div>
      <div class="form-group">
        <label>Đến ngày</label>
        <input type="date" v-model="filters.to" class="form-input" />
      </div>
      <div class="form-group">
        <label>Hành động (Action)</label>
        <select v-model="filters.action" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="a in filterOptions.actions" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
      <div class="form-group">
        <label>Đối tượng (Entity)</label>
        <select v-model="filters.entity_type" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="e in filterOptions.entity_types" :key="e" :value="e">{{ e }}</option>
        </select>
      </div>
      <div class="filter-actions">
        <button class="btn btn-primary" @click="load(1)" :disabled="loading">🔍 Tìm kiếm</button>
      </div>
    </div>

    <div v-if="errorMessage" class="card error-card mb-4">{{ errorMessage }}</div>

    <div class="card">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Thời gian</th>
              <th>Người thực hiện</th>
              <th>Hành động</th>
              <th>Đối tượng</th>
              <th>Mã đối tượng</th>
              <th>IP</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in logs" :key="row.id">
              <tr class="log-row" @click="toggleExpand(row.id)">
                <td>{{ formatDate(row.created_at) }}</td>
                <td>{{ row.user_display_name || row.username || '—' }}</td>
                <td><span class="badge badge-purple">{{ row.action }}</span></td>
                <td>{{ row.entity_type }}</td>
                <td class="mono">{{ truncate(row.entity_id) }}</td>
                <td>{{ row.client_ip || '—' }}</td>
                <td>{{ expanded === row.id ? '▲' : '▼' }}</td>
              </tr>
              <tr v-if="expanded === row.id" class="detail-row">
                <td colspan="7">
                  <div class="detail-grid">
                    <div>
                      <h5>Trước khi thay đổi (before_data)</h5>
                      <pre>{{ prettyJson(row.before_data) }}</pre>
                    </div>
                    <div>
                      <h5>Sau khi thay đổi (after_data)</h5>
                      <pre>{{ prettyJson(row.after_data) }}</pre>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!loading && logs.length === 0">
              <td colspan="7" class="text-muted text-center">Không tìm thấy nhật ký phù hợp.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="pagination-footer" v-if="pagination.last_page > 1">
        <button class="page-btn" :disabled="pagination.current_page === 1" @click="load(pagination.current_page - 1)">◀ Trước</button>
        <span class="page-info">Trang {{ pagination.current_page }} / {{ pagination.last_page }} ({{ pagination.total }} bản ghi)</span>
        <button class="page-btn" :disabled="pagination.current_page === pagination.last_page" @click="load(pagination.current_page + 1)">Sau ▶</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

function defaultDate(daysAgo: number) {
  const d = new Date();
  d.setDate(d.getDate() - daysAgo);
  return d.toISOString().slice(0, 10);
}

const filters = reactive({
  from: defaultDate(30),
  to: defaultDate(0),
  action: '',
  entity_type: '',
});

const filterOptions = ref<{ actions: string[]; entity_types: string[] }>({ actions: [], entity_types: [] });
const logs = ref<any[]>([]);
const pagination = reactive({ current_page: 1, last_page: 1, total: 0 });
const loading = ref(false);
const errorMessage = ref('');
const expanded = ref<number | null>(null);

function toggleExpand(id: number) {
  expanded.value = expanded.value === id ? null : id;
}

async function loadFilterOptions() {
  try {
    const res = await axios.get('/api/audit-logs/filters');
    filterOptions.value = res.data.data;
  } catch {
    // Non-critical: dropdowns simply stay empty if this fails.
  }
}

async function load(page = 1) {
  loading.value = true;
  errorMessage.value = '';
  try {
    const params: Record<string, string | number> = { page, from: filters.from, to: filters.to };
    if (filters.action) params.action = filters.action;
    if (filters.entity_type) params.entity_type = filters.entity_type;

    const res = await axios.get('/api/audit-logs', { params });
    const data = res.data.data;
    logs.value = data.data;
    pagination.current_page = data.current_page;
    pagination.last_page = data.last_page;
    pagination.total = data.total;
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || 'Không thể tải nhật ký Audit Log.';
  } finally {
    loading.value = false;
  }
}

function formatDate(v: string) {
  if (!v) return '—';
  return new Date(v).toLocaleString('vi-VN');
}
function truncate(v: string | null) {
  if (!v) return '—';
  return v.length > 12 ? v.slice(0, 10) + '…' : v;
}
function prettyJson(v: any) {
  if (!v) return '(không có)';
  try {
    const obj = typeof v === 'string' ? JSON.parse(v) : v;
    return JSON.stringify(obj, null, 2);
  } catch {
    return String(v);
  }
}

onMounted(() => {
  loadFilterOptions();
  load(1);
});
</script>

<style scoped>
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
  min-width: 180px;
}
.filter-bar label {
  font-size: 0.8rem;
  color: var(--text-muted);
  font-weight: 600;
}
.filter-actions {
  margin-left: auto;
}
.error-card {
  color: var(--status-red);
  border-color: var(--status-red-border);
  background-color: var(--status-red-bg);
  padding: var(--space-lg) !important;
}
.log-row {
  cursor: pointer;
}
.mono {
  font-family: 'JetBrains Mono', 'Courier New', monospace;
  font-size: 0.8rem;
}
.detail-row td {
  background-color: var(--bg-sidebar) !important;
  padding: var(--space-lg) !important;
}
.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-xl);
}
.detail-grid h5 {
  margin: 0 0 var(--space-sm) 0;
  color: var(--text-muted);
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.detail-grid pre {
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-md);
  font-size: 0.8rem;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-word;
  color: var(--text-body);
}
.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-lg);
  padding: var(--space-lg);
  border-top: 1px solid var(--border-divider);
}
.page-btn {
  padding: 8px 16px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
  background-color: var(--bg-card);
  color: var(--text-body);
  cursor: pointer;
}
.page-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.page-info {
  color: var(--text-muted);
  font-size: 0.85rem;
}
.text-center { text-align: center; }
</style>
