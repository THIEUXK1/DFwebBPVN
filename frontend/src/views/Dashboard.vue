<template>
  <div class="realtime-control-center">
    <!-- Realtime Connection Status Banner -->
    <div class="connection-status-banner" :class="'banner-' + connectionStatus.toLowerCase()">
      <span class="status-indicator-dot"></span>
      <span class="status-text">Trạng thái hệ thống: {{ connectionStatusText }}</span>
      <span class="status-subtext" v-if="connectionStatus === 'FALLBACK'"> (Đang dùng chế độ dự phòng Polling 10s)</span>
    </div>

    <!-- Dashboard Main Navigation Tabs -->
    <div class="tabs-nav-bar mb-4">
      <button 
        v-for="tab in tabs" 
        :key="tab.id" 
        @click="activeTab = tab.id" 
        :class="['tab-nav-btn', { 'active': activeTab === tab.id }]"
      >
        <span class="tab-icon">{{ tab.icon }}</span>
        <span class="tab-label">{{ tab.name }}</span>
      </button>
    </div>

    <!-- Content Area Based on Active Tab -->
    <main class="dashboard-tab-content">
      
      <!-- TAB 1: OVERALL OVERVIEW -->
      <div v-if="activeTab === 'overview'" class="tab-panel">
        <div class="panel-header mb-4">
          <h3>📊 Điều độ sản xuất &amp; Giám sát máy nhuộm</h3>
          <p class="text-muted">Trạng thái hoạt động thời gian thực của máy nhuộm VD01 - VD18.</p>
        </div>

        <div class="machines-grid">
          <div 
            v-for="m in overviewData" 
            :key="m.machine_id" 
            class="machine-card" 
            :class="['card-state-' + m.status.toLowerCase(), { 'has-alert': m.alerts.length > 0 }]"
            @click="viewMachineDetails(m)"
          >
            <!-- Card Header -->
            <div class="m-card-header">
              <span class="m-code">{{ m.machine_code }}</span>
              <span :class="['m-connection-dot', m.status === 'IDLE' ? 'conn-idle' : 'conn-running']"></span>
            </div>

            <!-- Card Body -->
            <div class="m-card-body">
              <div v-if="m.current_batch" class="m-batch-info">
                <div class="m-batch-id" @click.stop="openBatchTimeline(m.current_batch.id)">
                  Lô: <strong class="code-link">{{ m.current_batch.legacy_batch_id }}</strong>
                </div>
                <div class="m-meta-text">Màu: {{ m.current_batch.color }}</div>
                <div class="m-meta-text">Hàng: {{ m.current_batch.product_code }}</div>
                <div class="m-meta-text" v-if="m.current_batch.tank_code">Thùng: {{ m.current_batch.tank_code }}</div>
                
                <!-- Progress bar -->
                <div class="progress-bar-container mt-2">
                  <div class="progress-bar-fill" :style="{ width: getProgressPercent(m.current_batch.status) + '%' }"></div>
                </div>
                <div class="progress-label">{{ m.current_batch.status }} ({{ getProgressPercent(m.current_batch.status) }}%)</div>
              </div>
              <div v-else class="m-idle-state">
                <span class="idle-icon">🌀</span>
                <span class="idle-text">MÁY TRỐNG (IDLE)</span>
              </div>
            </div>

            <!-- Card Footer (Alerts count) -->
            <div class="m-card-footer" v-if="m.alerts.length > 0">
              <span class="m-alert-badge">⚠️ {{ m.alerts.length }} Cảnh báo</span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: WEIGHING ROOM -->
      <div v-if="activeTab === 'weighing'" class="tab-panel">
        <div class="panel-header mb-4">
          <h3>⚖️ Giám sát Trạm Cân &amp; Chuẩn bị Nguyên liệu</h3>
          <p class="text-muted">Hàng chờ cân nguyên liệu mẻ và thuốc nhuộm dán nhãn Lot hôm nay.</p>
        </div>

        <div class="table-responsive">
          <table class="data-table">
            <thead>
              <tr>
                <th>Mã Lô (Batch ID)</th>
                <th>Màu (Color)</th>
                <th>Sản phẩm (Product)</th>
                <th>Máy nhuộm</th>
                <th>Thùng trộn</th>
                <th>Trạng thái cân</th>
                <th>Thao tác cân hoàn thành</th>
                <th>Vận chuyển</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in weighingData" :key="w.batch_id">
                <td class="bold-text highlight-code" @click="openBatchTimeline(w.batch_id)">
                  <span class="code-link">{{ w.legacy_batch_id }}</span>
                </td>
                <td>{{ w.color }}</td>
                <td>{{ w.product_code }}</td>
                <td><span class="machine-tag">{{ w.machine_code }}</span></td>
                <td><span class="tank-tag">{{ w.tank_code }}</span></td>
                <td>
                  <span :class="['badge', getStatusBadgeClass(w.status)]">{{ w.status }}</span>
                </td>
                <td>
                  <div class="weighed-indicators">
                    <span class="indicator-number">{{ w.weighed_count }} hóa chất</span>
                    <span class="indicator-time" v-if="w.last_weighed_at">Cân xong: {{ formatTime(w.last_weighed_at) }}</span>
                  </div>
                </td>
                <td>
                  <span :class="['badge', getTransportBadgeClass(w.transport_status)]">
                    {{ w.transport_status }}
                  </span>
                </td>
              </tr>
              <tr v-if="weighingData.length === 0">
                <td colspan="8" class="text-center text-muted">Không có nhiệm vụ cân nào hôm nay.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- TAB 3: DYEING MACHINE DETAIL -->
      <div v-if="activeTab === 'dyeing'" class="tab-panel">
        <div class="panel-header mb-4">
          <h3>🌀 Giám sát cấp máy nhuộm tự động</h3>
          <p class="text-muted">Theo dõi khóa liên động nạp van nước, A11/DLG, hóa chất và thuốc nhuộm tại các máy nhuộm.</p>
        </div>

        <div class="machine-status-list">
          <div v-for="item in machinesData" :key="item.machine.id" class="machine-status-row card">
            <div class="m-row-header">
              <span class="m-row-code">{{ item.machine.code }}</span>
              <span class="m-row-name">{{ item.machine.name }}</span>
            </div>

            <div class="m-row-body" v-if="item.active_batch">
              <div class="m-row-info">
                <div>Mẻ chạy: <strong class="code-link" @click="openBatchTimeline(item.active_batch.id)">{{ item.active_batch.legacy_batch_id }}</strong></div>
                <div class="m-row-subtext">Trạng thái mẻ: {{ item.active_batch.status }}</div>
              </div>

              <!-- Interlock Checklist -->
              <div class="interlock-checklist">
                <div class="checklist-item" :class="{ 'check-success': item.feed_operation?.water_verified }">
                  <span class="check-icon">{{ item.feed_operation?.water_verified ? '✓' : '✗' }}</span>
                  <span class="check-label">Đủ nước nạp</span>
                </div>
                <div class="checklist-item" :class="{ 'check-success': item.feed_operation?.materials_verified }">
                  <span class="check-icon">{{ item.feed_operation?.materials_verified ? '✓' : '✗' }}</span>
                  <span class="check-label">Xác thực nguyên liệu</span>
                </div>
                <div class="checklist-item" :class="{ 'check-success': item.transport?.status === 'ARRIVED_AT_TANK' }">
                  <span class="check-icon">{{ item.transport?.status === 'ARRIVED_AT_TANK' ? '✓' : '✗' }}</span>
                  <span class="check-label">Thùng 1A/2B đã tới trạm</span>
                </div>
                <div class="checklist-item" :class="{ 'check-success': item.feed_operation?.completed_at }">
                  <span class="check-icon">{{ item.feed_operation?.completed_at ? '✓' : '✗' }}</span>
                  <span class="check-label">Van cấp đã mở</span>
                </div>
              </div>
            </div>
            <div v-else class="m-row-empty text-muted">
              Máy đang trống (IDLE). Không có hoạt động nạp cấp liệu.
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 4: ALERTS PANEL -->
      <div v-if="activeTab === 'alerts'" class="tab-panel">
        <div class="panel-header mb-4">
          <h3>⚠️ Trung tâm Xử lý Cảnh báo tự động</h3>
          <p class="text-muted">Quản lý và giải quyết các sự cố vận hành trễ SLA hoặc sai lệch dung sai cân đo.</p>
        </div>

        <div class="alerts-container">
          <div 
            v-for="alert in alertsData" 
            :key="alert.id" 
            class="alert-action-card" 
            :class="'alert-sev-' + alert.severity.toLowerCase()"
          >
            <div class="alert-head">
              <span class="alert-badge-sev">{{ alert.severity }}</span>
              <span class="alert-time">{{ formatTime(alert.created_at) }}</span>
            </div>
            
            <div class="alert-body mt-2">
              <p class="alert-message">{{ alert.message }}</p>
              <div class="alert-meta-row mt-2" v-if="alert.batch || alert.machine">
                <span class="meta-tag" v-if="alert.batch">Lô: {{ alert.batch.legacy_batch_id }}</span>
                <span class="meta-tag" v-if="alert.machine">Máy: {{ alert.machine.code }}</span>
              </div>
            </div>

            <div class="alert-actions-footer mt-4">
              <span class="assignee-info" v-if="alert.status === 'ACKNOWLEDGED'">
                👤 Phụ trách: <strong>{{ alert.assignee?.display_name }}</strong>
              </span>
              <div class="action-buttons">
                <button 
                  v-if="alert.status === 'OPEN'" 
                  @click="openAlertModal(alert, 'ACKNOWLEDGE')" 
                  class="btn btn-secondary btn-sm"
                >
                  Nhận xử lý
                </button>
                <button 
                  @click="openAlertModal(alert, 'RESOLVE')" 
                  class="btn btn-primary btn-sm"
                >
                  Đóng cảnh báo (Resolve)
                </button>
              </div>
            </div>
          </div>

          <div v-if="alertsData.length === 0" class="text-center text-muted pad-empty-row card">
            <div class="empty-state-icon">✅</div>
            <p>Tuyệt vời! Không có cảnh báo sự cố nào đang mở.</p>
          </div>
        </div>
      </div>

      <!-- TAB 5: MANAGEMENT KPIS -->
      <div v-if="activeTab === 'management'" class="tab-panel">
        <div class="panel-header mb-4">
          <h3>📈 Thống kê &amp; KPIs Quản lý Ca</h3>
          <p class="text-muted">Biểu đồ chỉ số vận hành nhà máy gần thời gian thực.</p>
        </div>

        <div class="kpi-detail-grid">
          <div class="card kpi-detail-card">
            <h4>Mẻ hoàn thành (hôm nay)</h4>
            <div class="kpi-detail-value text-success">{{ managementKpis.completed_today }}</div>
            <p class="text-muted">Mẻ nhuộm đã nạp máy hoàn thành.</p>
          </div>
          <div class="card kpi-detail-card">
            <h4>Mẻ đang chạy hiện hành</h4>
            <div class="kpi-detail-value text-warning">{{ managementKpis.active_batches }}</div>
            <p class="text-muted">Mẻ đang cân, vận chuyển hoặc chờ nạp.</p>
          </div>
          <div class="card kpi-detail-card">
            <h4>Nhiệm vụ cân trễ hạn</h4>
            <div class="kpi-detail-value text-error">{{ managementKpis.overdue_weighing_count }}</div>
            <p class="text-muted">Trọng số cân kéo dài vượt ngưỡng.</p>
          </div>
          <div class="card kpi-detail-card">
            <h4>Độ trễ vận chuyển trung bình</h4>
            <div class="kpi-detail-value text-info">{{ managementKpis.average_transport_minutes }} phút</div>
            <p class="text-muted">Thời gian trễ trung bình so với SLA.</p>
          </div>
        </div>
      </div>

    </main>

    <!-- MOCK printing & DB stats simulator section (Admin/Developer only — WS-011: Dashboard/Giám
         sát phải "chỉ giám sát", nên công cụ giả lập không được lộ ra cho tài khoản trạm MONITORING) -->
    <div v-if="authStore.isAdmin" class="collapsible-mock-section mt-4">
      <button @click="showMockPanel = !showMockPanel" class="mock-panel-toggle">
        🛠️ Công cụ kiểm thử &amp; Giả lập (Admin / Developer)
        <span>{{ showMockPanel ? '▲' : '▼' }}</span>
      </button>

      <div class="mock-panel-body card" v-show="showMockPanel">
        <div class="two-col-grid">
          <!-- Live Workstations readings -->
          <section class="section-sub">
            <h4>📶 Nhịp tim Trạm Cân &amp; Telemetry Cân thô</h4>
            <div class="ws-telemetry-row mt-2" v-for="ws in workstations" :key="ws.id">
              <div class="ws-header">
                <strong>{{ ws.name }}</strong>
                <span :class="['ws-dot', ws.active ? 'dot-active' : 'dot-offline']"></span>
              </div>
              <div class="ws-weight-box">
                <span class="ws-weight">{{ ws.weight.toFixed(2) }} kg</span>
                <span class="ws-time">Cập nhật: {{ ws.lastUpdated }}</span>
              </div>
            </div>
          </section>

          <!-- Printing trigger simulator -->
          <section class="section-sub">
            <h4>🚀 Phát lệnh in Tem Nhãn QR giả lập (TSPL)</h4>
            <div class="control-form mt-2">
              <div class="form-group">
                <label>Trạm cân đích</label>
                <select v-model="targetWorkstation" class="form-select">
                  <option value="WS-01">WS-01 (Cân Dyes)</option>
                  <option value="WS-02">WS-02 (Cân Chems)</option>
                </select>
              </div>
              <div class="form-group mt-2">
                <label>Nội dung mã lệnh TSPL</label>
                <textarea v-model="tsplPayload" class="form-control" style="height: 80px; font-family: monospace;"></textarea>
              </div>
              <button @click="triggerMockPrint" class="btn btn-primary mt-2" :disabled="printing">
                {{ printing ? 'Đang gửi...' : 'Gửi lệnh in' }}
              </button>
              <p v-if="printMessage" :class="printSuccess ? 'text-success' : 'text-error'" class="mt-2">{{ printMessage }}</p>
            </div>
          </section>
        </div>
      </div>
    </div>

    <!-- Batch Timeline Modal Dialog -->
    <div class="modal-backdrop" v-if="timelineOpen" @click.self="closeBatchTimeline">
      <div class="timeline-modal">
        <div class="modal-header">
          <h3>📋 Trình tự thời gian mẻ nhuộm (Batch Timeline)</h3>
          <button @click="closeBatchTimeline" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" v-if="activeTimelineData">
          <div class="timeline-hero mb-4">
            <h4>Lô sản xuất: {{ activeTimelineData.batch.legacy_batch_id }}</h4>
            <div class="text-muted">Màu: {{ activeTimelineData.batch.color }} | Vải: {{ activeTimelineData.batch.product_code }}</div>
          </div>

          <div class="timeline-steps">
            <div 
              v-for="(step, idx) in activeTimelineData.timeline" 
              :key="'step-' + idx" 
              class="timeline-step-item"
              :class="'step-status-' + step.status.toLowerCase()"
            >
              <div class="step-dot"></div>
              <div class="step-content">
                <div class="step-header">
                  <strong class="step-title">{{ step.milestone }}</strong>
                  <span class="step-time">{{ formatTime(step.time) }}</span>
                </div>
                <div class="step-meta">Tác nhân: {{ step.actor }}</div>
                <p class="step-notes">{{ step.notes }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeBatchTimeline" class="btn btn-secondary">Đóng</button>
        </div>
      </div>
    </div>

    <!-- Alert Action Modal -->
    <div class="modal-backdrop" v-if="alertModalOpen" @click.self="closeAlertModal">
      <div class="action-modal">
        <div class="modal-header">
          <h3>{{ alertModalAction === 'ACKNOWLEDGE' ? 'Nhận xử lý Sự cố' : 'Đóng Cảnh báo (Resolve)' }}</h3>
          <button @click="closeAlertModal" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" v-if="selectedAlertForAction">
          <p class="mb-4">Bạn đang xử lý cảnh báo: <strong>{{ selectedAlertForAction.message }}</strong></p>
          <div class="form-group">
            <label>{{ alertModalAction === 'ACKNOWLEDGE' ? 'Ghi chú tiếp nhận' : 'Biện pháp khắc phục thực tế *' }}</label>
            <textarea v-model="alertActionNotes" required class="form-control" style="height: 100px;"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="closeAlertModal" class="btn btn-secondary">Hủy</button>
          <button @click="submitAlertAction" class="btn btn-primary">Xác nhận</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';
import SvgIcon from '../components/SvgIcon.vue';
import realtimeService from '../services/realtime';

const authStore = useAuthStore();

// Tab configuration
const tabs = [
  { id: 'overview', name: 'Điều độ tổng thể', icon: '📊' },
  { id: 'weighing', name: 'Phòng cân', icon: '⚖️' },
  { id: 'dyeing', name: 'Máy nhuộm', icon: '🌀' },
  { id: 'alerts', name: 'Cảnh báo', icon: '⚠️' },
  { id: 'management', name: 'KPIs Quản lý', icon: '📈' }
];
const activeTab = ref('overview');
const showMockPanel = ref(false);

// Connection Status banner
const connectionStatus = ref('OFFLINE');
const connectionStatusText = ref('Đang khởi tạo kết nối...');

// Telemetry State
const workstations = ref([
  { id: 'WS-01', name: 'Trạm cân 1 (Thuốc nhuộm)', weight: 0.0, active: false, lastUpdated: 'Không có dữ liệu' },
  { id: 'WS-02', name: 'Trạm cân 2 (Hóa chất)', weight: 0.0, active: false, lastUpdated: 'Không có dữ liệu' }
]);

// Tab Snapshots
const overviewData = ref<any[]>([]);
const weighingData = ref<any[]>([]);
const machinesData = ref<any[]>([]);
const alertsData = ref<any[]>([]);
const managementKpis = ref<any>({
  completed_today: 0,
  active_batches: 0,
  machines_running: 0,
  machines_waiting: 0,
  overdue_weighing_count: 0,
  average_transport_minutes: 0
});

// Printing mock form state
const targetWorkstation = ref('WS-01');
const tsplPayload = ref(
  "SIZE 80 mm, 50 mm\r\n" +
  "GAP 3 mm, 0 mm\r\n" +
  "CLS\r\n" +
  "QRCODE 50,50,L,6,A,0,\"BATCH-A+110293\"\r\n" +
  "TEXT 50,220,\"3\",0,1,1,\"LOT: A+110293\"\r\n" +
  "PRINT 1\r\n"
);
const printing = ref(false);
const printMessage = ref('');
const printSuccess = ref(true);

// Modals State
const timelineOpen = ref(false);
const activeTimelineData = ref<any | null>(null);
const alertModalOpen = ref(false);
const alertModalAction = ref<'ACKNOWLEDGE' | 'RESOLVE'>('ACKNOWLEDGE');
const selectedAlertForAction = ref<any | null>(null);
const alertActionNotes = ref('');

// Setup Realtime Connection
const initRealtimeConnection = () => {
  const token = authStore.token;
  if (!token) return;

  // 1. Hook Connection Status
  realtimeService.connect(token);
  
  // Track reactive connection status
  const checkStatusInterval = setInterval(() => {
    connectionStatus.value = realtimeService.status.value;
    const mapping: Record<string, string> = {
      'ONLINE': 'Đã kết nối trực tiếp (Realtime Online)',
      'RECONNECTING': 'Mất kết nối. Đang thử kết nối lại...',
      'FALLBACK': 'Chế độ dự phòng hoạt động (Polling 10s)',
      'OFFLINE': 'Không có kết nối mạng'
    };
    connectionStatusText.value = mapping[realtimeService.status.value] || 'Chờ kết nối';
  }, 1000);

  // 2. Subscribe to transactional events
  realtimeService.subscribe('*', (event: any) => {
    // Whenever any transactional event arrives, fetch corresponding snapshots to keep view fresh
    fetchOverviewSnapshot();
    fetchWeighingSnapshot();
    fetchMachinesSnapshot();
    fetchAlertsSnapshot();
    fetchManagementKpiSnapshot();
  });

  // 3. Subscribe to live scale telemetry heartbeat (updates live weights without DB queries)
  realtimeService.subscribe('scale_heartbeat', (data: any) => {
    workstations.value.forEach(ws => {
      const live = data[ws.id];
      if (live) {
        ws.active = live.active;
        ws.weight = live.weight;
        ws.lastUpdated = live.active ? new Date().toLocaleTimeString() : 'Heartbeat offline';
      }
    });
  });

  // 4. Hook fallback polling sync
  realtimeService.subscribe('fallback_sync', (data: any) => {
    // If running in polling fallback mode, sync state
    if (data.overview) {
      overviewData.value = data.overview;
    }
  });

  // Clean up
  return () => {
    clearInterval(checkStatusInterval);
    realtimeService.disconnect();
  };
};

let disposeRealtime: any = null;

onMounted(() => {
  // Pull initial snapshots
  fetchOverviewSnapshot();
  fetchWeighingSnapshot();
  fetchMachinesSnapshot();
  fetchAlertsSnapshot();
  fetchManagementKpiSnapshot();

  // Establish SSE stream connection
  disposeRealtime = initRealtimeConnection();
});

onUnmounted(() => {
  if (disposeRealtime) {
    disposeRealtime();
  }
});

// Snapshot API Pulls
const fetchOverviewSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/overview');
    overviewData.value = res.data.data.overview;
  } catch (err) {
    console.error('Failed to load overview:', err);
  }
};

const fetchWeighingSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/weighing');
    weighingData.value = res.data.data;
  } catch (err) {
    console.error('Failed to load weighing queue:', err);
  }
};

const fetchMachinesSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/machines');
    machinesData.value = res.data.data;
  } catch (err) {
    console.error('Failed to load machine list:', err);
  }
};

const fetchAlertsSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/alerts');
    alertsData.value = res.data.data;
  } catch (err) {
    console.error('Failed to load alerts:', err);
  }
};

const fetchManagementKpiSnapshot = async () => {
  try {
    const res = await axios.get('/api/dashboard/management');
    managementKpis.value = res.data.data;
  } catch (err) {
    console.error('Failed to load management KPIs:', err);
  }
};

// Alert Handlers
const openAlertModal = (alert: any, action: 'ACKNOWLEDGE' | 'RESOLVE') => {
  selectedAlertForAction.value = alert;
  alertModalAction.value = action;
  alertActionNotes.value = '';
  alertModalOpen.value = true;
};

const closeAlertModal = () => {
  alertModalOpen.value = false;
  selectedAlertForAction.value = null;
};

const submitAlertAction = async () => {
  if (!selectedAlertForAction.value) return;
  
  if (alertModalAction.value === 'RESOLVE' && !alertActionNotes.value.trim()) {
    alert('Vui lòng nhập biện pháp khắc phục thực tế!');
    return;
  }

  try {
    await axios.post(`/api/alerts/${selectedAlertForAction.value.id}/handle`, {
      action: alertModalAction.value,
      notes: alertActionNotes.value
    });
    
    closeAlertModal();
    // Refresh alerts snapshot
    fetchAlertsSnapshot();
    fetchManagementKpiSnapshot();
  } catch (err) {
    console.error('Failed to handle alert:', err);
  }
};

// Batch Timeline handlers
const openBatchTimeline = async (batchId: string) => {
  try {
    const res = await axios.get(`/api/batches/${batchId}/timeline`);
    activeTimelineData.value = res.data.data;
    timelineOpen.value = true;
  } catch (err) {
    console.error('Failed to load timeline:', err);
  }
};

const closeBatchTimeline = () => {
  timelineOpen.value = false;
  activeTimelineData.value = null;
};

// Mock Printing simulation
const triggerMockPrint = async () => {
  printing.value = true;
  printMessage.value = '';
  try {
    const response = await axios.post('/api/print-jobs', {
      workstation_id: targetWorkstation.value,
      label_payload: tsplPayload.value,
      printer_connection_type: 'USB',
      printer_address: 'PRINTER_DYES_1'
    });

    if (response.data.status === 'SUCCESS') {
      printSuccess.value = true;
      printMessage.value = `Gửi lệnh in thành công! Job ID: ${response.data.data.id}.`;
      // Trigger local agent heartbeat refresh in UI
      fetchOverviewSnapshot();
    } else {
      printSuccess.value = false;
      printMessage.value = 'Lỗi in thất bại.';
    }
  } catch (err: any) {
    printSuccess.value = false;
    printMessage.value = err.response?.data?.message || 'Có lỗi xảy ra khi kết nối máy chủ.';
  } finally {
    printing.value = false;
  }
};

// Helper status formatters
const getProgressPercent = (status: string) => {
  const mapping: Record<string, number> = {
    'NEW': 15,
    'READY_TO_WEIGH': 35,
    'WEIGHING': 55,
    'WEIGHED': 75,
    'SENT': 90,
    'DONE': 100
  };
  return mapping[status] || 0;
};

const getStatusBadgeClass = (status: string) => {
  const mapping: Record<string, string> = {
    'NEW': 'badge-grey',
    'READY_TO_WEIGH': 'badge-blue',
    'WEIGHING': 'badge-yellow',
    'WEIGHED': 'badge-green',
    'SENT': 'badge-orange',
    'DONE': 'badge-purple'
  };
  return mapping[status] || 'badge-grey';
};

const getTransportBadgeClass = (status: string) => {
  const mapping: Record<string, string> = {
    'READY_FOR_TRANSFER': 'badge-grey',
    'IN_TRANSIT': 'badge-yellow',
    'ARRIVED_AT_TANK': 'badge-green',
    'ACCEPTED': 'badge-blue',
    'REJECTED': 'badge-red'
  };
  return mapping[status] || 'badge-grey';
};

const formatTime = (dateStr: string) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN');
};

const viewMachineDetails = (m: any) => {
  if (m.current_batch) {
    openBatchTimeline(m.current_batch.id);
  }
};
</script>

<style scoped>
.realtime-control-center {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

/* Connection Status banner */
.connection-status-banner {
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  padding: 10px var(--space-xl);
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.status-indicator-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--status-grey);
}

.banner-online {
  background-color: var(--status-green-bg);
  border: 1px solid var(--status-green-border);
  color: var(--status-green);
}
.banner-online .status-indicator-dot {
  background-color: var(--status-green);
  box-shadow: 0 0 8px var(--status-green);
  animation: blink 2s infinite;
}

.banner-reconnecting, .banner-fallback {
  background-color: var(--status-yellow-bg);
  border: 1px solid var(--status-yellow-border);
  color: var(--status-yellow);
}
.banner-reconnecting .status-indicator-dot, .banner-fallback .status-indicator-dot {
  background-color: var(--status-yellow);
  box-shadow: 0 0 8px var(--status-yellow);
  animation: blink 1s infinite;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

/* Tab Navigation styles */
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

.dashboard-tab-content {
  flex: 1;
}

/* Tab 1: Overall overview machines grid */
.machines-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-xl);
}

.machine-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  position: relative;
  overflow: hidden;
}

.machine-card:hover {
  transform: translateY(-2px);
  border-color: var(--border-card-hover);
  box-shadow: var(--shadow-md);
}

.machine-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background-color: var(--status-grey);
}

/* Card States color themes */
.card-state-idle::before { background-color: var(--status-grey); }
.card-state-new::before, .card-state-ready_to_weigh::before { background-color: var(--status-blue); }
.card-state-weighing::before { background-color: var(--status-yellow); }
.card-state-weighed::before { background-color: var(--status-green); }
.card-state-sent::before { background-color: var(--status-orange); }

.has-alert {
  border-color: var(--status-red) !important;
  box-shadow: 0 0 12px rgba(244, 63, 94, 0.15) !important;
}

.m-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.m-code {
  font-family: 'Outfit', sans-serif;
  font-weight: 700;
  font-size: 1.2rem;
  color: var(--text-title);
}

.m-connection-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.conn-idle { background-color: var(--status-grey); }
.conn-running { background-color: var(--status-green); box-shadow: 0 0 6px var(--status-green); }

.m-idle-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-xl) 0;
  gap: 6px;
  color: var(--text-disabled);
}

.idle-icon {
  font-size: 2.2rem;
  opacity: 0.25;
}

.idle-text {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.05em;
}

.code-link {
  color: var(--primary-hover);
  text-decoration: underline;
  cursor: pointer;
}

.m-meta-text {
  font-size: 12px;
  color: var(--text-muted);
}

.progress-bar-container {
  width: 100%;
  height: 5px;
  background-color: var(--border-divider);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.progress-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--text-disabled);
  margin-top: 2px;
}

.m-alert-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--status-red);
  background-color: var(--status-red-bg);
  padding: 2px 6px;
  border-radius: var(--radius-sm);
  display: inline-block;
}

/* Tab 3: interlocks rows list */
.machine-status-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.machine-status-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-lg) var(--space-2xl);
  gap: var(--space-xl);
}

.m-row-header {
  display: flex;
  flex-direction: column;
  min-width: 150px;
}

.m-row-code {
  font-family: 'Outfit', sans-serif;
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-title);
}

.m-row-name {
  font-size: 12px;
  color: var(--text-muted);
}

.m-row-body {
  flex: 1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-xl);
}

.interlock-checklist {
  display: flex;
  gap: var(--space-xl);
}

.checklist-item {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background-color: var(--status-red-bg);
  border: 1px solid var(--status-red-border);
  color: var(--status-red);
  padding: 4px 10px;
  border-radius: var(--radius-full);
  font-size: 11px;
  font-weight: 700;
}

.checklist-item.check-success {
  background-color: var(--status-green-bg);
  border-color: var(--status-green-border);
  color: var(--status-green);
}

/* Tab 4: alert cards styling */
.alerts-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

.alert-action-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  position: relative;
  overflow: hidden;
}

.alert-action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 5px;
  height: 100%;
  background-color: var(--status-yellow);
}

.alert-sev-critical::before {
  background-color: var(--status-red);
}

.alert-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.alert-badge-sev {
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 0.08em;
  background-color: var(--status-yellow-bg);
  border: 1px solid var(--status-yellow-border);
  color: var(--status-yellow);
  padding: 1px 6px;
  border-radius: var(--radius-sm);
}

.alert-sev-critical .alert-badge-sev {
  background-color: var(--status-red-bg);
  border-color: var(--status-red-border);
  color: var(--status-red);
}

.alert-time {
  font-size: 11px;
  color: var(--text-disabled);
}

.alert-message {
  font-weight: 600;
  color: var(--text-title);
}

.meta-tag {
  font-size: 11px;
  font-weight: 700;
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-divider);
  padding: 2px 8px;
  border-radius: var(--radius-sm);
  margin-right: 6px;
}

.alert-actions-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border-divider);
  padding-top: var(--space-lg);
}

.action-buttons {
  display: flex;
  gap: var(--space-sm);
}

/* Tab 5: management KPIs styling */
.kpi-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: var(--space-xl);
}

.kpi-detail-card {
  text-align: center;
  padding: var(--space-3xl) var(--space-xl) !important;
}

.kpi-detail-value {
  font-family: 'Outfit', sans-serif;
  font-size: 2.8rem;
  font-weight: 800;
  margin: var(--space-sm) 0;
}

/* Collapsible Mock section styling */
.collapsible-mock-section {
  border-top: 1px solid var(--border-divider);
  padding-top: var(--space-xl);
}

.mock-panel-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-card);
  padding: var(--space-md) var(--space-xl);
  border-radius: var(--radius-md);
  font-weight: 600;
  cursor: pointer;
}

.ws-telemetry-row {
  background-color: var(--bg-main);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-md) var(--space-lg);
  margin-bottom: var(--space-sm);
}

.ws-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.ws-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.dot-active { background-color: var(--status-green); box-shadow: 0 0 6px var(--status-green); }
.dot-offline { background-color: var(--status-grey); }

.ws-weight-box {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 4px;
}

.ws-weight {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text-title);
  font-family: monospace;
}

.ws-time {
  font-size: 10px;
  color: var(--text-disabled);
}

/* Modals Backdrop & structure */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.75);
  backdrop-filter: blur(4px);
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.timeline-modal, .action-modal {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-xl);
  width: 90%;
  max-width: 580px;
  max-height: 85vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
}

.modal-header {
  height: 60px;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
}

.modal-close-btn {
  font-size: 2rem;
  color: var(--text-muted);
  cursor: pointer;
}

.modal-body {
  flex: 1;
  padding: var(--space-2xl);
  overflow-y: auto;
}

.modal-footer {
  height: 60px;
  padding: 0 var(--space-2xl);
  border-top: 1px solid var(--border-divider);
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: var(--space-md);
  background-color: var(--bg-sidebar);
}

/* Timeline specific nodes list styling */
.timeline-steps {
  position: relative;
  padding-left: 20px;
}

.timeline-steps::before {
  content: '';
  position: absolute;
  top: 8px;
  left: 4px;
  bottom: 8px;
  width: 2px;
  background-color: var(--border-divider);
}

.timeline-step-item {
  position: relative;
  margin-bottom: var(--space-2xl);
}

.timeline-step-item:last-child {
  margin-bottom: 0;
}

.step-dot {
  position: absolute;
  left: -20px;
  top: 5px;
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background-color: var(--border-card);
  border: 2px solid var(--bg-card);
  z-index: 2;
}

.step-status-completed .step-dot {
  background-color: var(--status-green);
  border-color: var(--bg-card);
  box-shadow: 0 0 6px var(--status-green);
}

.step-status-warning .step-dot {
  background-color: var(--status-yellow);
  border-color: var(--bg-card);
  box-shadow: 0 0 6px var(--status-yellow);
}

.step-content {
  background-color: var(--bg-main);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  padding: var(--space-md) var(--space-lg);
}

.step-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.step-title {
  color: var(--text-title);
}

.step-time {
  font-size: 11px;
  color: var(--text-disabled);
}

.step-meta {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 2px;
}

.step-notes {
  margin-top: 6px;
  font-size: 12px;
  color: var(--text-body);
}

@media (max-width: 768px) {
  .tabs-nav-bar {
    width: 100%;
  }
  .machine-status-row {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-md);
  }
  .m-row-body {
    width: 100%;
    flex-direction: column;
    align-items: flex-start;
  }
  .interlock-checklist {
    flex-wrap: wrap;
    gap: var(--space-sm);
  }
}
</style>
