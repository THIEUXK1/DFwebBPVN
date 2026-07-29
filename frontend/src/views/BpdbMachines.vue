<template>
  <div class="bpdb-machines-container">
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">ADMIN — BPDB / JIT</span>
        <h2>Máy VD — Trạng thái vận hành</h2>
        <p class="text-muted font-sm">Chỉ đọc — không sửa BPDB/Windows Service/SCCM/JIT/DLV.</p>
      </div>
      <div class="banner-right status-board font-sm">
        <div class="status-indicator">
          🔌 Kết nối BPDB:
          <strong :class="bpdbConnected ? 'text-success' : 'text-error'">
            {{ bpdbConnected ? 'ONLINE' : 'MẤT KẾT NỐI' }}
          </strong>
        </div>
        <button class="btn btn-secondary btn-sm" @click="fetchAll" :disabled="loading">
          {{ loading ? 'Đang tải...' : '🔄 Làm mới' }}
        </button>
      </div>
    </div>

    <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>

    <div v-if="!bpdbConnected" class="stale-banner error-banner mt-2">
      ⚠️ BPDB mất kết nối — đang hiển thị dữ liệu cache gần nhất (lúc {{ formatTime(lastSyncedAt) }}). Không phải mọi máy đều "Offline", chỉ là chưa đọc được trạng thái mới.
    </div>
    <div v-else-if="dataStale" class="stale-banner mt-2">
      ⏱️ Dữ liệu có thể đã cũ — lần đồng bộ gần nhất lúc {{ formatTime(lastSyncedAt) }} ({{ dataAgeSeconds }}s trước).
    </div>

    <div class="summary-grid mt-3" v-if="machineSummary">
      <div class="summary-card"><div class="summary-label">Tổng số máy VD</div><div class="summary-value">{{ machineSummary.total }}</div></div>
      <div class="summary-card"><div class="summary-label">Đang xử lý</div><div class="summary-value" style="color:#2563eb">{{ machineSummary.processing }}</div></div>
      <div class="summary-card"><div class="summary-label">Đang chờ</div><div class="summary-value" style="color:#ca8a04">{{ machineSummary.waiting }}</div></div>
      <div class="summary-card"><div class="summary-label">Đang chuyển trạng thái</div><div class="summary-value" style="color:#9333ea">{{ machineSummary.transitioning }}</div></div>
      <div class="summary-card"><div class="summary-label">Nhàn rỗi</div><div class="summary-value text-muted">{{ machineSummary.idle }}</div></div>
      <div class="summary-card"><div class="summary-label">Lỗi/Hủy</div><div class="summary-value text-error">{{ machineSummary.error + machineSummary.cancelledRecent }}</div></div>
      <div class="summary-card"><div class="summary-label">Task bị kẹt</div><div class="summary-value" :class="stuckMachineCount ? 'text-error' : ''">{{ stuckMachineCount }}</div></div>
    </div>
    <p class="text-muted font-xs mt-1">
      Cập nhật gần nhất: <strong>{{ formatTime(lastSyncedAt) }}</strong> · Nguồn: <strong>BPDB — Chỉ đọc</strong>
      <span v-if="dataStale" class="text-error"> · Dữ liệu có thể đã cũ</span>
    </p>
    <div class="connection-disclaimer font-xs">
      ℹ️ BPDB hiện không cung cấp trạng thái kết nối trực tiếp theo từng máy VD. Trạng thái hiển thị là trạng thái nghiệp vụ suy ra từ lệnh sản xuất.
    </div>

    <div class="filter-row mt-2">
      <select v-model="machineStatusFilter" class="form-select font-xs">
        <option value="">Tất cả trạng thái</option>
        <option value="PROCESSING">PROCESSING</option>
        <option value="WAITING">WAITING</option>
        <option value="TRANSITIONING">TRANSITIONING</option>
        <option value="COMPLETED_RECENTLY">COMPLETED_RECENTLY</option>
        <option value="CANCELLED">CANCELLED</option>
        <option value="ERROR">ERROR</option>
        <option value="IDLE">IDLE</option>
      </select>
    </div>

    <div class="machine-grid mt-2">
      <div v-for="m in filteredMachines" :key="m.machineCode" class="machine-card" :class="'status-' + m.operationalStatus.toLowerCase()" @click="openMachineDetail(m.machineCode)">
        <div class="machine-card-top">
          <strong>{{ m.displayName }}</strong>
          <span class="op-status-badge" :class="'status-' + m.operationalStatus.toLowerCase()">{{ m.operationalStatus }}</span>
        </div>
        <div class="machine-card-body font-xs" v-if="m.currentTask">
          <div>Tank {{ m.currentTask.tank || '—' }} · JIT {{ m.currentTask.jitQueue || '—' }}</div>
          <div class="mono-text-sm">{{ m.currentTask.taskTitle }}</div>
          <div v-if="m.currentTask.workStartTime">Bắt đầu: {{ formatTime(m.currentTask.workStartTime) }}</div>
        </div>
        <div class="machine-card-body font-xs text-muted" v-else>Không có lệnh đang hoạt động</div>
        <div class="machine-card-footer font-xs">
          <span>Kết nối: <strong class="text-muted">NOT_AVAILABLE</strong></span>
          <span v-if="m.activeTaskCount > 1" class="text-error"> · {{ m.activeTaskCount }} task đồng thời ⚠️</span>
        </div>
        <p v-if="m.stuckWarning" class="text-error font-xs mt-1">⚠️ {{ m.stuckWarning.code }}<span v-if="m.stuckWarning.minutes"> ({{ m.stuckWarning.minutes }}p &gt; ngưỡng {{ m.stuckWarning.threshold }}p)</span></p>
      </div>
      <p v-if="!filteredMachines.length" class="text-muted font-sm">Không có máy nào khớp bộ lọc.</p>
    </div>

    <!-- Machine detail drawer -->
    <div v-if="selectedMachine" class="modal-overlay" @click.self="selectedMachine = null">
      <div class="detail-drawer">
        <div class="flex-header">
          <h3>{{ selectedMachine.machineCode }}</h3>
          <button class="btn btn-secondary btn-sm" @click="selectedMachine = null">Đóng</button>
        </div>
        <p class="font-xs text-muted">Trạng thái: <strong :class="'op-status-badge status-' + (selectedMachine.status?.operationalStatus || '').toLowerCase()">{{ selectedMachine.status?.operationalStatus }}</strong> · Kết nối: NOT_AVAILABLE</p>

        <h4 class="font-sm mt-2">Các tổ hợp Tank/Mức nước</h4>
        <table class="data-table">
          <thead><tr><th>MachineName</th><th>Tank</th><th>Dung tích</th></tr></thead>
          <tbody>
            <tr v-for="v in selectedMachine.variants" :key="v.machine_id">
              <td>{{ v.machine_name }}</td>
              <td>{{ v.tank }}</td>
              <td>{{ v.max_storage_content }}</td>
            </tr>
          </tbody>
        </table>

        <h4 class="font-sm mt-2">Task gần đây ({{ selectedMachine.recentTasks?.length || 0 }})</h4>
        <table class="data-table">
          <thead><tr><th>TaskTitle</th><th>Status</th><th>Bắt đầu</th><th>Kết thúc</th><th>Lỗi</th></tr></thead>
          <tbody>
            <tr v-for="t in selectedMachine.recentTasks" :key="t.Id">
              <td class="mono-text-sm">{{ t.TaskTitle }}</td>
              <td>{{ rawTaskStatusLabel(t.TaskStatus) }}</td>
              <td>{{ formatTime(t.WorkStartTime) }}</td>
              <td>{{ formatTime(t.FinishTime) }}</td>
              <td class="text-error font-xs">{{ t.ErrorMsg || '' }}</td>
            </tr>
          </tbody>
        </table>

        <h4 class="font-sm mt-2">Lịch sử hoàn thành gần đây</h4>
        <table class="data-table">
          <thead><tr><th>TaskTitle</th><th>Hóa chất</th><th>Khối lượng</th><th>Kết thúc</th></tr></thead>
          <tbody>
            <tr v-for="h in selectedMachine.recentHistory" :key="h.ID">
              <td class="mono-text-sm">{{ h.TaskTitle }}</td>
              <td>{{ h.DyeName || h.DyeCode }}</td>
              <td>{{ h.GramsDosed }}g</td>
              <td>{{ formatTime(h.FinishTime) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// Nhãn trung tính cho SUP_Tasks.TaskStatus thô (10/20/30/40/99) — khớp
// App\Services\ColorService\BpdbTaskStatusLabels ở backend (yêu cầu 2026-07-21, "CHỐT
// NGUỒN DỮ LIỆU").
const RAW_TASK_STATUS_LABELS: Record<string, string> = {
  '10': 'Chờ hệ thống xử lý',
  '20': 'Đang chuyển trạng thái',
  '30': 'Đang được hệ thống xử lý',
  '40': 'Task đã kết thúc',
  '99': 'Task bị hủy/xóa',
};
const rawTaskStatusLabel = (status: string | number | null | undefined) => {
  if (status === null || status === undefined || status === '') return '—';
  return RAW_TASK_STATUS_LABELS[String(status)] || `Không xác định (${status})`;
};

const machines = ref<any[]>([]);
const machineSummary = ref<any>(null);
const machineStatusFilter = ref('');
const selectedMachine = ref<any>(null);

const loading = ref(false);
const errorMsg = ref('');
const bpdbConnected = ref(true);
const lastSyncedAt = ref<string | null>(null);
const dataAgeSeconds = ref(0);
const dataStale = ref(false);

const stuckMachineCount = computed(() => machines.value.filter(m => m.stuckWarning).length);
const filteredMachines = computed(() =>
  machineStatusFilter.value ? machines.value.filter(m => m.operationalStatus === machineStatusFilter.value) : machines.value
);

const formatTime = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

const applyEnvelope = (data: any) => {
  lastSyncedAt.value = data.lastSyncedAt ?? null;
  dataAgeSeconds.value = Math.round(data.dataAgeSeconds ?? 0);
  dataStale.value = !!data.stale;
};

const fetchMachines = async () => {
  const res = await axios.get('/api/admin/bpdb/machines/status');
  machines.value = res.data.data;
  bpdbConnected.value = res.data.bpdbConnected;
  applyEnvelope(res.data);
};

const fetchMachineSummary = async () => {
  const res = await axios.get('/api/admin/bpdb/machines/status-summary');
  machineSummary.value = res.data;
  bpdbConnected.value = res.data.bpdbConnected;
  applyEnvelope(res.data);
};

const openMachineDetail = async (machineCode: string) => {
  const res = await axios.get(`/api/admin/bpdb/machines/${machineCode}/status`);
  selectedMachine.value = res.data.data;
};

const fetchAll = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    await Promise.all([fetchMachines(), fetchMachineSummary()]);
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Không tải được dữ liệu Máy VD.';
  } finally {
    loading.value = false;
  }
};

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
  await fetchAll();
  // Cập nhật gần thời gian thực — 5s theo phương án MVP (mục 8), backend đã cache 4s nên
  // nhiều trình duyệt cùng mở không dội query trực tiếp vào BPDB.
  pollTimer = setInterval(() => {
    fetchMachines();
    fetchMachineSummary();
  }, 5000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.bpdb-machines-container { padding: 1rem; }

.station-banner {
  background-color: var(--bg-sidebar, #f8fafc);
  border: 1px solid var(--border-divider, #e2e8f0);
  border-radius: var(--radius-lg, 10px);
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.banner-left h2 {
  font-size: 1.4rem;
  color: var(--text-title, #111827);
  margin: 4px 0;
}

.station-badge {
  font-size: 10px;
  background-color: var(--status-blue-bg, rgba(37,99,235,0.1));
  border: 1px solid var(--status-blue-border, rgba(37,99,235,0.3));
  color: var(--status-blue, #2563eb);
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--radius-sm, 4px);
  width: fit-content;
  text-transform: uppercase;
}

.banner-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
}

.summary-card {
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 8px;
  padding: 0.75rem;
  text-align: center;
}

.summary-label {
  font-size: 0.72rem;
  color: var(--text-muted, #6b7280);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.summary-value {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 0.25rem;
}

.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin-top: 0.5rem;
}

.data-table th, .data-table td {
  border-bottom: 1px solid var(--border-color, #e2e8f0);
  padding: 0.4rem 0.5rem;
  text-align: left;
}

.mono-text-sm { font-family: monospace; font-size: 0.75rem; }
.font-xs { font-size: 0.72rem; }

.stale-banner {
  background: rgba(202, 138, 4, 0.12);
  border: 1px solid rgba(202, 138, 4, 0.3);
  color: #92400e;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.82rem;
}
.stale-banner.error-banner {
  background: rgba(220, 38, 38, 0.1);
  border-color: rgba(220, 38, 38, 0.3);
  color: #991b1b;
}

.connection-disclaimer {
  background: var(--bg-main, #f3f4f6);
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  color: var(--text-muted, #6b7280);
  margin-top: 0.35rem;
}
.flex-header { display: flex; justify-content: space-between; align-items: center; }

.machine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 0.6rem;
}

.machine-card {
  border: 1px solid var(--border-color, #e2e8f0);
  border-left: 4px solid #9ca3af;
  border-radius: 8px;
  padding: 0.6rem 0.7rem;
  background: var(--bg-card, #fff);
  cursor: pointer;
  transition: box-shadow 0.15s;
}
.machine-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }

.machine-card.status-processing { border-left-color: #2563eb; }
.machine-card.status-waiting { border-left-color: #ca8a04; }
.machine-card.status-transitioning { border-left-color: #9333ea; }
.machine-card.status-completed_recently { border-left-color: #16a34a; }
.machine-card.status-cancelled, .machine-card.status-error { border-left-color: #dc2626; }
.machine-card.status-idle { border-left-color: #9ca3af; }
.machine-card.status-unknown { border-left-color: #4b5563; }

.machine-card-top { display: flex; justify-content: space-between; align-items: center; }

.op-status-badge {
  font-size: 0.68rem;
  font-weight: 700;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  background: rgba(156,163,175,0.15);
  color: #6b7280;
}
.op-status-badge.status-processing { background: rgba(37,99,235,0.12); color: #2563eb; }
.op-status-badge.status-waiting, .op-status-badge.status-awaiting_processing { background: rgba(202,138,4,0.12); color: #ca8a04; }
.op-status-badge.status-transitioning { background: rgba(147,51,234,0.12); color: #9333ea; }
.op-status-badge.status-completed_recently, .op-status-badge.status-ended { background: rgba(22,163,74,0.12); color: #16a34a; }
.op-status-badge.status-cancelled, .op-status-badge.status-error { background: rgba(220,38,38,0.12); color: #dc2626; }

.machine-card-body { margin-top: 0.4rem; }
.machine-card-footer { margin-top: 0.4rem; color: var(--text-muted, #6b7280); }

.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 50;
}
.detail-drawer {
  background: var(--bg-card, #fff); border-radius: 10px; padding: 1.2rem;
  width: 90%; max-width: 720px; max-height: 85vh; overflow-y: auto;
}
</style>
