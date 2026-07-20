<template>
  <div class="chemical-call-container">
    <!-- Remote Access Mode Banner -->
    <div v-if="isImpersonating" class="remote-banner mb-4" :class="remoteModeClass">
      <div class="banner-content">
        <span class="banner-icon">🌐</span>
        <div class="banner-text">
          <strong>CHẾ ĐỘ GIÁM SÁT TỪ XA: </strong>
          <span v-if="remoteMode === 'VIEW_ONLY'">CHỈ XEM (VIEW_ONLY) - Các nút thao tác nghiệp vụ đã bị vô hiệu hóa.</span>
          <span v-else>ĐIỀU KHIỂN TỪ XA (REMOTE_OPERATE) - Cho phép vận hành từ xa. Mọi thao tác sẽ được ghi Audit Log kiểm toán.</span>
        </div>
      </div>
      <div class="banner-actions">
        <select v-model="remoteMode" class="form-select font-xs select-mode">
          <option value="VIEW_ONLY">🔒 Chế độ Chỉ xem</option>
          <option value="REMOTE_OPERATE">⚡ Chế độ Điều khiển</option>
        </select>
      </div>
    </div>

    <!-- Top banner status indicators (VBA Operating Style, No Scale References) -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">CHEMICAL CALL OPERATING SYSTEM</span>
        <h2>{{ currentWorkstation ? currentWorkstation.name : 'Hệ thống Gọi Hóa Chất Xưởng Nhuộm' }}</h2>
        <p class="text-muted font-sm">
          Mã trạm: <code>{{ currentWorkstation?.code || 'WS-CHEMICAL-01' }}</code> | 
          Vị trí: {{ currentWorkstation?.location || 'Khu gọi hóa chất' }}
        </p>
      </div>
      <div class="banner-right status-board font-sm">
        <div class="status-indicator">🖥️ Workstation: <strong class="text-success">{{ currentWorkstation?.code || 'WS-CHEMICAL-01' }}</strong></div>
        <div class="status-indicator">🎛️ Valve System: <strong class="text-success">ONLINE</strong></div>
        <div class="status-indicator">🤖 Local Agent: <strong class="text-success">ONLINE</strong></div>
        <div class="status-indicator">⚡ Realtime MES: <strong class="text-success">CONNECTED</strong></div>
        <div class="status-indicator">⏱️ Cập nhật cuối: <strong class="text-primary">{{ lastUpdateTime }}</strong></div>
      </div>
    </div>

    <!-- Error/Success Alerts -->
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>
    <div v-if="successMsg" class="alert-box alert-success">✅ {{ successMsg }}</div>

    <!-- Factory Operating Grid (Equivalent to VBA CHEM_ORDER) -->
    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> Đang tải thông tin van đường ống xưởng nhuộm...
    </div>

    <div v-else class="machine-grid">
      <div 
        v-for="(channels, machineCode) in groupedChannels" 
        :key="machineCode" 
        class="card machine-card"
      >
        <div class="machine-card-header">
          <span class="machine-name-title">🖥️ Máy {{ machineCode }}</span>
          <span class="machine-status-dot dot-green"></span>
        </div>
        
        <div class="machine-card-body">
          <div 
            v-for="c in channels" 
            :key="c.channel_id" 
            class="channel-row"
            :class="getChannelRowClass(c)"
          >
            <div class="channel-number-col">
              <strong class="font-mono text-dark">Kênh {{ c.channel_number }}</strong>
            </div>
            
            <div class="chemical-name-col">
              <span class="chem-formula font-semibold" title="Tên hóa chất / công thức từ Database">{{ c.chemical_code }}</span>
            </div>
            
            <div class="status-badge-col">
              <span :class="['badge', getStatusBadgeClass(c)]">
                {{ getStatusLabel(c) }}
              </span>
            </div>
            
            <div class="action-btn-col">
              <!-- IDLE state: show Call / ORDER button -->
              <button 
                v-if="!c.current_request" 
                @click="callChemical(c)" 
                class="btn btn-primary btn-sm w-full py-1"
                :disabled="actionLoading === c.channel_id || (isImpersonating && remoteMode === 'VIEW_ONLY')"
              >
                📣 Gọi
              </button>
              
              <!-- ORDER state: show Cancel / Hủy button & waiting text -->
              <div v-else-if="c.current_request.status === 'ORDERED' || c.current_request.status === 'ACKNOWLEDGED'" class="d-flex align-center gap-1 justify-between w-full">
                <span class="waiting-txt font-xs text-danger blink">Đang phát...</span>
                <button 
                  @click="cancelRequest(c.current_request.id, c.channel_id)" 
                  class="btn btn-danger btn-xs py-1 px-2"
                  title="Hủy yêu cầu"
                  :disabled="actionLoading === c.channel_id || (isImpersonating && remoteMode === 'VIEW_ONLY')"
                >
                  ❌ Hủy
                </button>
              </div>
              
              <!-- DONE state: show OK button to acknowledge and reset -->
              <button 
                v-else-if="c.current_request.status === 'DONE'"
                @click="resetRequest(c.current_request.id, c.channel_id)" 
                class="btn btn-success btn-sm w-full py-1 font-semibold"
                :disabled="actionLoading === c.channel_id || (isImpersonating && remoteMode === 'VIEW_ONLY')"
              >
                🟢 OK
              </button>
            </div>
            
            <div class="time-col font-xs text-muted text-right">
              {{ c.current_request ? formatTime(c.current_request.requested_at) : '-' }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Collapsible Log Panel (Bottom of page, audit compliant) -->
    <div class="logs-panel card">
      <div class="logs-header" @click="showLogs = !showLogs">
        <h4>📋 Nhật ký trạng thái van phát xưởng nhuộm</h4>
        <span class="toggle-icon text-muted font-sm">{{ showLogs ? '▼ Thu gọn' : '▲ Mở rộng' }}</span>
      </div>
      <div v-if="showLogs" class="logs-body mt-3">
        <div class="table-responsive">
          <table class="table table-dark">
            <thead>
              <tr>
                <th>Thời gian</th>
                <th>Máy</th>
                <th>Kênh</th>
                <th>Hóa chất</th>
                <th>Trạng thái cũ</th>
                <th>Trạng thái mới</th>
                <th>Người thao tác</th>
                <th>Workstation</th>
                <th>Chi tiết</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(log, idx) in logs" :key="idx" :class="log.type">
                <td class="font-mono">{{ log.time }}</td>
                <td><strong>{{ log.machine_code || '-' }}</strong></td>
                <td>Kênh {{ log.channel_number || '-' }}</td>
                <td class="font-mono text-info">{{ log.chemical_code || '-' }}</td>
                <td><span class="badge" :class="getStatusClass(log.before_status)">{{ log.before_status || '-' }}</span></td>
                <td><span class="badge" :class="getStatusClass(log.after_status)">{{ log.after_status || '-' }}</span></td>
                <td><code>{{ log.actor_username || 'Hệ thống' }}</code></td>
                <td><code>{{ log.workstation_code || '-' }}</code></td>
                <td>{{ log.message }}</td>
              </tr>
              <tr v-if="logs.length === 0">
                <td colspan="9" class="text-center text-muted py-4">Chưa có nhật ký hoạt động nào được ghi nhận.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Simulator Control (Visible only in Development/Impersonation Mode) -->
    <div v-if="isDevelopment" class="admin-sim-panel card mt-4 border-dashed">
      <div class="d-flex justify-between align-center border-b pb-2">
        <h4>🧪 Bảng điều khiển giả lập Phòng điều phối (Control Room Simulator)</h4>
        <span class="badge badge-yellow">DEVELOPER MODE</span>
      </div>
      <p class="text-muted font-xs mt-1">Sử dụng bảng này để phê duyệt Tiếp nhận hoặc báo cáo Hoàn thành van phát thay cho hệ thống PLC tự động.</p>

      <div class="active-requests-list mt-3">
        <div v-if="activeRequests.length === 0" class="text-center py-4 text-muted font-sm">
          Không có yêu cầu van phát nào đang hoạt động.
        </div>
        <div 
          v-else 
          v-for="r in activeRequests" 
          :key="r.channel_id" 
          class="active-req-row"
        >
          <div class="req-info">
            <strong>Máy {{ r.machine_code }} - Kênh {{ r.channel_number }}</strong>
            <span class="font-xs block text-muted">{{ r.chemical_code }}</span>
          </div>
          <div class="req-status-badge">
            <span :class="['badge', getStatusBadgeClass(r)]">{{ getStatusLabel(r) }}</span>
          </div>
          <div class="req-actions">
            <button 
              v-if="r.current_request.status === 'ORDERED'"
              @click="acknowledgeRequest(r.current_request.id, r.channel_id)"
              class="btn btn-warning btn-xs mr-2"
              :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
            >
              Tiếp Nhận (Acknowledge)
            </button>
            <button 
              v-if="r.current_request.status === 'ACKNOWLEDGED' || r.current_request.status === 'ORDERED'"
              @click="completeRequest(r.current_request.id, r.channel_id)"
              class="btn btn-success btn-xs"
              :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
            >
              Hoàn Thành (Complete)
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);
const remoteMode = ref<'VIEW_ONLY' | 'REMOTE_OPERATE'>('VIEW_ONLY');
const showLogs = ref(true);

const isDevelopment = computed(() => {
  return import.meta.env.DEV || isImpersonating.value;
});

const remoteModeClass = computed(() => {
  return remoteMode.value === 'VIEW_ONLY' ? 'mode-view-only' : 'mode-remote-operate';
});

function getRequestConfig() {
  const config: { headers: Record<string, string> } = { headers: {} };
  if (isImpersonating.value && remoteMode.value === 'REMOTE_OPERATE') {
    config.headers['X-Remote-Operation'] = 'true';
    config.headers['X-Target-Workstation'] = String(targetWsId.value || '');
    config.headers['X-Remote-Reason'] = 'Admin remote control action';
  }
  return config;
}

interface RequestInfo {
  id: string;
  status: string;
  requested_at: string;
}

interface ChemicalChannel {
  channel_id: number;
  channel_number: number;
  machine_code: string;
  chemical_code: string;
  is_active: boolean;
  current_request: RequestInfo | null;
}

const currentWorkstation = ref<any>(null);
const channelsList = ref<ChemicalChannel[]>([]);
const loading = ref(true);
const actionLoading = ref<number | null>(null);
const errorMsg = ref('');
const successMsg = ref('');
const logs = ref<any[]>([]);
const lastUpdateTime = ref<string>('-');

let pollInterval: any = null;

// Group channels by Machine Code
const groupedChannels = computed(() => {
  const groups: Record<string, ChemicalChannel[]> = {};
  channelsList.value.forEach(c => {
    if (!groups[c.machine_code]) {
      groups[c.machine_code] = [];
    }
    groups[c.machine_code].push(c);
  });
  
  // Sort channels by channel_number
  Object.keys(groups).forEach(machine => {
    groups[machine].sort((a, b) => a.channel_number - b.channel_number);
  });

  return groups;
});

// List of channels that have active requests (ORDERED, ACKNOWLEDGED, DONE)
const activeRequests = computed(() => {
  return channelsList.value.filter(c => c.current_request !== null);
});

// Fetch channels from Backend API
async function fetchChannels() {
  try {
    const res = await axios.get('/api/chemical-channels');
    channelsList.value = res.data;
    lastUpdateTime.value = new Date().toLocaleTimeString('vi-VN');
  } catch (err: any) {
    console.error('Failed to fetch channels:', err);
    errorMsg.value = 'Không thể kết nối đến máy chủ API để lấy thông tin van đường ống.';
  } finally {
    loading.value = false;
  }
}

// Fetch recent event logs from database events
async function fetchRecentEvents() {
  try {
    const res = await axios.get('/api/chemical-call-events');
    logs.value = res.data;
  } catch (err) {
    console.error('Failed to fetch chemical call events:', err);
  }
}

// Call Chemical POST Request
async function callChemical(channel: ChemicalChannel) {
  errorMsg.value = '';
  successMsg.value = '';
  actionLoading.value = channel.channel_id;

  const idempotencyKey = `cc-${channel.channel_id}-${Date.now()}`;

  try {
    const res = await axios.post('/api/chemical-call-requests', {
      channel_id: channel.channel_id,
      idempotency_key: idempotencyKey
    }, getRequestConfig());

    if (res.data) {
      successMsg.value = `Gửi yêu cầu phát hóa chất thành công cho máy ${channel.machine_code}!`;
      await fetchChannels();
      await fetchRecentEvents();
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Gửi yêu cầu gọi hóa chất thất bại.';
  } finally {
    actionLoading.value = null;
  }
}

// Cancel Request PATCH
async function cancelRequest(requestId: string, channelId: number) {
  errorMsg.value = '';
  successMsg.value = '';
  actionLoading.value = channelId;

  try {
    await axios.patch(`/api/chemical-call-requests/${requestId}/cancel`, {
      reason: 'Hủy yêu cầu từ màn hình điều khiển máy'
    }, getRequestConfig());
    successMsg.value = 'Hủy yêu cầu phát hóa chất thành công.';
    await fetchChannels();
    await fetchRecentEvents();
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Hủy yêu cầu thất bại.';
  } finally {
    actionLoading.value = null;
  }
}

// OK Button Request reset (DONE -> RESET)
async function resetRequest(requestId: string, channelId: number) {
  errorMsg.value = '';
  successMsg.value = '';
  actionLoading.value = channelId;

  try {
    await axios.patch(`/api/chemical-call-requests/${requestId}/reset`, {}, getRequestConfig());
    successMsg.value = 'Xác nhận OK (Reset van phát) thành công.';
    await fetchChannels();
    await fetchRecentEvents();
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Xác nhận OK thất bại.';
  } finally {
    actionLoading.value = null;
  }
}

// Acknowledge Request PATCH (Simulator Room)
async function acknowledgeRequest(requestId: string, channelId: number) {
  errorMsg.value = '';
  successMsg.value = '';
  try {
    await axios.patch(`/api/chemical-call-requests/${requestId}/acknowledge`, {}, getRequestConfig());
    successMsg.value = 'Giả lập Tiếp nhận yêu cầu thành công.';
    await fetchChannels();
    await fetchRecentEvents();
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Tiếp nhận yêu cầu thất bại.';
  }
}

// Complete Request PATCH (Simulator Room)
async function completeRequest(requestId: string, channelId: number) {
  errorMsg.value = '';
  successMsg.value = '';
  try {
    await axios.patch(`/api/chemical-call-requests/${requestId}/complete`, {}, getRequestConfig());
    successMsg.value = 'Giả lập Hoàn thành cấp hóa chất thành công.';
    await fetchChannels();
    await fetchRecentEvents();
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Hoàn thành cấp hóa chất thất bại.';
  }
}

// Helpers for Classes and Labels
function getStatusLabel(channel: ChemicalChannel) {
  if (!channel.current_request) return 'IDLE';
  const status = channel.current_request.status;
  if (status === 'ORDERED' || status === 'ACKNOWLEDGED') return 'ORDER';
  if (status === 'DONE') return 'DONE';
  return 'IDLE';
}

function getStatusBadgeClass(channel: ChemicalChannel) {
  if (!channel.current_request) return 'badge-neutral';
  const status = channel.current_request.status;
  if (status === 'ORDERED' || status === 'ACKNOWLEDGED') return 'badge-danger';
  if (status === 'DONE') return 'badge-success';
  return 'badge-neutral';
}

function getChannelRowClass(channel: ChemicalChannel) {
  if (!channel.current_request) return 'row-idle';
  const status = channel.current_request.status;
  if (status === 'ORDERED' || status === 'ACKNOWLEDGED') return 'row-ordered';
  if (status === 'DONE') return 'row-done';
  return 'row-idle';
}

function getStatusClass(status: string) {
  if (status === 'ORDERED' || status === 'ACKNOWLEDGED') return 'badge-danger';
  if (status === 'DONE') return 'badge-success';
  return 'badge-neutral';
}

function formatTime(timeStr: string | null) {
  if (!timeStr) return '-';
  try {
    const d = new Date(timeStr);
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  } catch (e) {
    return timeStr;
  }
}

onMounted(async () => {
  // Resolve Workstation
  if (isImpersonating.value && targetWsId.value) {
    try {
      const res = await axios.get('/api/workstations');
      const wsList = res.data.data || res.data;
      const target = wsList.find((w: any) => String(w.id) === String(targetWsId.value));
      if (target) {
        currentWorkstation.value = target;
      }
    } catch (e) {
      console.error('Failed to load impersonated workstation', e);
    }
  } else {
    const wsConfigStr = localStorage.getItem('df_workstation_config');
    if (wsConfigStr) {
      try {
        currentWorkstation.value = JSON.parse(wsConfigStr);
      } catch (e) {
        console.error('Failed to parse workstation config', e);
      }
    }
  }

  await fetchChannels();
  await fetchRecentEvents();

  // Start polling channel statuses every 3 seconds for real-time responsiveness
  pollInterval = setInterval(() => {
    fetchChannels();
    fetchRecentEvents();
  }, 3000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
});
</script>

<style scoped>
.chemical-call-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
}

.station-banner {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: var(--space-md) var(--space-lg);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.station-badge {
  background-color: var(--status-blue-bg);
  color: var(--status-blue);
  border: 1px solid var(--status-blue-border);
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  font-size: var(--font-xs);
  font-weight: 700;
  letter-spacing: 0.5px;
}

.status-board {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: right;
}

.status-indicator {
  color: var(--text-muted);
}

.machine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--space-md);
}

@media (min-width: 1200px) {
  .machine-grid {
    grid-template-columns: repeat(4, 1fr);
  }
}

.machine-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  padding: 0;
}

.machine-card-header {
  padding: var(--space-sm) var(--space-md);
  background-color: var(--bg-sidebar);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.machine-name-title {
  font-weight: 700;
  color: var(--text-title);
}

.machine-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.machine-card-body {
  padding: var(--space-xs) 0;
}

.channel-row {
  display: grid;
  grid-template-columns: 1.1fr 1.6fr 1.1fr 1.8fr 1fr;
  align-items: center;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-divider);
  transition: background-color 0.2s ease;
}

.channel-row:last-child {
  border-bottom: none;
}

/* Status colors equivalent to legacy VBA */
.row-idle {
  background-color: transparent;
}

.row-ordered {
  background-color: rgba(239, 68, 68, 0.08);
}

.row-done {
  background-color: rgba(52, 211, 153, 0.08);
}

.chem-formula {
  font-family: 'JetBrains Mono', monospace;
  font-size: var(--font-xs);
  color: var(--text-body);
}

.badge {
  font-size: 10px;
  padding: 2px 4px;
  border-radius: var(--radius-sm);
  font-weight: 700;
  display: inline-block;
  text-align: center;
}

.badge-neutral {
  background-color: #374151;
  color: #d1d5db;
}

.badge-danger {
  background-color: var(--status-red);
  color: #fff;
}

.badge-success {
  background-color: var(--status-green);
  color: #fff;
}

.badge-yellow {
  background-color: var(--status-yellow);
  color: #000;
}

.blink {
  animation: blink-animation 1s steps(5, start) infinite;
}

@keyframes blink-animation {
  to {
    visibility: hidden;
  }
}

/* Collapsible Logs table */
.logs-panel {
  padding: var(--space-md);
  margin-top: var(--space-md);
}

.logs-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  user-select: none;
}

.logs-body {
  max-height: 250px;
  overflow-y: auto;
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-sm);
}

.table-dark {
  background-color: #111827;
  color: #f3f4f6;
  margin-bottom: 0;
  width: 100%;
}

.table th, .table td {
  padding: 8px 12px;
  border-bottom: 1px solid #1f2937;
  font-size: 0.8rem;
  text-align: left;
}

.table th {
  background-color: #1f2937;
  color: #9ca3af;
  position: sticky;
  top: 0;
}

/* Remote monitoring banner styles */
.remote-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-md) var(--space-lg);
  border-radius: var(--radius-md);
  border-width: 1px;
  border-style: solid;
  font-size: var(--font-sm);
  transition: all 0.3s ease;
}

.mode-view-only {
  background-color: var(--status-blue-bg);
  border-color: var(--status-blue-border);
  color: var(--status-blue);
}

.mode-remote-operate {
  background-color: var(--status-yellow-bg);
  border-color: var(--status-yellow-border);
  color: var(--status-yellow);
}

.banner-content {
  display: flex;
  align-items: center;
  gap: var(--space-md);
}

.banner-icon {
  font-size: var(--font-lg);
}

.select-mode {
  width: 180px;
  background-color: var(--bg-card);
  border-color: var(--border-card);
  color: var(--text-body);
}

/* Simulator panel */
.admin-sim-panel {
  padding: var(--space-md);
  background-color: rgba(245, 158, 11, 0.01);
}

.active-requests-list {
  background-color: #1f2937;
  border: 1px solid #374151;
  border-radius: var(--radius-sm);
  max-height: 150px;
  overflow-y: auto;
}

.active-req-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 12px;
  border-bottom: 1px solid #374151;
}

.active-req-row:last-child {
  border-bottom: none;
}

.req-actions {
  display: flex;
}

.border-dashed {
  border-style: dashed !important;
}

.dot-green {
  background-color: var(--status-green);
}
</style>
