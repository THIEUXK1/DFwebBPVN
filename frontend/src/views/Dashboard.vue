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
          <p class="text-muted" v-if="authStore.isAdmin">Trạng thái vận hành thời gian thực máy VD — nguồn BPDB (chỉ đọc).</p>
          <p class="text-muted" v-else>Trạng thái hoạt động thời gian thực của máy nhuộm VD01 - VD18.</p>
        </div>

        <div class="overview-status-layout">
          <!-- Trạng thái máy thật lấy trực tiếp từ BPDB (giống /bpdb-machines) — chỉ Admin
               thấy vì endpoint /admin/bpdb/machines/status yêu cầu role:ADMIN ở backend (xác
               nhận 2026-07-29). Chỉ hiển thị icon + trạng thái, không kèm chi tiết task/tank
               (chi tiết đầy đủ đã có sẵn ở /bpdb-machines). -->
          <div v-if="authStore.isAdmin" class="machines-grid-simple">
            <div
              v-for="m in bpdbMachines"
              :key="m.machineCode"
              class="machine-status-tile"
              :class="'bpdb-status-' + m.operationalStatus.toLowerCase()"
            >
              <span class="tile-icon">{{ bpdbStatusIcon(m.operationalStatus) }}</span>
              <span class="tile-code">{{ m.displayName }}</span>
              <span class="tile-status">{{ m.operationalStatus }}</span>
            </div>
            <p v-if="!bpdbMachines.length" class="text-muted font-sm">Không có dữ liệu máy VD.</p>
          </div>

          <!-- Fallback: trạng thái nội bộ (app.machines) cho tài khoản không phải Admin — vẫn
               giữ nguyên hành vi cũ vì họ không gọi được endpoint BPDB. -->
          <div v-else class="machines-grid-simple">
            <div
              v-for="m in overviewData"
              :key="m.machine_id"
              class="machine-status-tile"
              :class="'card-state-' + m.status.toLowerCase()"
            >
              <span class="tile-icon">{{ appStatusIcon(m.status) }}</span>
              <span class="tile-code">{{ m.machine_code }}</span>
              <span class="tile-status">{{ m.status }}</span>
            </div>
          </div>

          <!-- Bảng chú thích trạng thái, bên phải lưới máy (yêu cầu 2026-07-29) -->
          <aside class="status-legend">
            <h4 class="legend-title">Chú thích trạng thái</h4>
            <ul class="legend-list">
              <li v-for="item in (authStore.isAdmin ? bpdbStatusLegend : appStatusLegend)" :key="item.status" class="legend-item">
                <span class="legend-icon">{{ item.icon }}</span>
                <div class="legend-text">
                  <div class="legend-head">
                    <strong>{{ item.label }}</strong>
                    <span class="legend-code">{{ item.status }}</span>
                  </div>
                  <p class="legend-desc">{{ item.desc }}</p>
                </div>
              </li>
            </ul>
          </aside>
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
import echo from '../services/echo';

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

// BPDB real machine status (Admin only — TAB 1 overview, chỉ icon + trạng thái) — cùng
// nguồn API với BpdbMachines.vue (/bpdb-machines) nhưng nhúng gọn vào Dashboard theo yêu
// cầu 2026-07-29 (không cần chi tiết task/tank, chỉ cần biết máy "đang là gì").
const bpdbMachines = ref<any[]>([]);

const fetchBpdbMachines = async () => {
  try {
    const res = await axios.get('/api/admin/bpdb/machines/status');
    bpdbMachines.value = res.data.data;
  } catch (err) {
    console.error('Failed to load BPDB machine status:', err);
  }
};

let bpdbPollTimer: ReturnType<typeof setInterval> | null = null;

// Icon theo trạng thái BPDB (operationalStatus) và trạng thái nội bộ (app.machines) — chỉ
// mang tính minh họa nhanh trên Dashboard, không thay thế nhãn chữ đứng cạnh.
const bpdbStatusIcon = (status: string) => {
  const mapping: Record<string, string> = {
    PROCESSING: '⚙️',
    WAITING: '⏳',
    TRANSITIONING: '🔄',
    COMPLETED_RECENTLY: '✅',
    CANCELLED: '🚫',
    ERROR: '❌',
    IDLE: '💤',
  };
  return mapping[status] || '❔';
};

const appStatusIcon = (status: string) => {
  const mapping: Record<string, string> = {
    IDLE: '💤',
    NEW: '🆕',
    READY_TO_WEIGH: '⚖️',
    WEIGHING: '⚖️',
    WEIGHED: '✅',
    SENT: '🚀',
    DONE: '🏁',
  };
  return mapping[status] || '❔';
};

// Chú thích trạng thái hiển thị cạnh lưới máy — BPDB: khớp logic suy luận operationalStatus
// ở BpdbMachineMonitoringService::reduceMachineStatus (backend); nội bộ: khớp state machine
// Batch trong .claude/rules/architecture-workflow.md.
const bpdbStatusLegend = [
  { status: 'PROCESSING', icon: '⚙️', label: 'Đang xử lý', desc: 'Máy đang chạy task, BPDB đang xử lý.' },
  { status: 'WAITING', icon: '⏳', label: 'Đang chờ', desc: 'Task đã tạo nhưng chưa bắt đầu chạy.' },
  { status: 'TRANSITIONING', icon: '🔄', label: 'Chuyển trạng thái', desc: 'Task đang ở bước chuyển tiếp giữa các giai đoạn xử lý.' },
  { status: 'COMPLETED_RECENTLY', icon: '✅', label: 'Vừa hoàn thành', desc: 'Task vừa kết thúc gần đây, máy sắp trống.' },
  { status: 'CANCELLED', icon: '🚫', label: 'Đã hủy', desc: 'Task gần nhất bị hủy/xóa.' },
  { status: 'ERROR', icon: '❌', label: 'Lỗi', desc: 'Task hiện tại phát sinh lỗi.' },
  { status: 'IDLE', icon: '💤', label: 'Nhàn rỗi', desc: 'Không có task nào — máy đang trống.' },
];

const appStatusLegend = [
  { status: 'IDLE', icon: '💤', label: 'Nhàn rỗi', desc: 'Máy chưa có lệnh sản xuất nào.' },
  { status: 'NEW', icon: '🆕', label: 'Lệnh mới', desc: 'Lệnh sản xuất vừa tạo, chưa cân.' },
  { status: 'READY_TO_WEIGH', icon: '⚖️', label: 'Sẵn sàng cân', desc: 'Lệnh đủ điều kiện để bắt đầu cân nguyên liệu.' },
  { status: 'WEIGHING', icon: '⚖️', label: 'Đang cân', desc: 'Đang trong quá trình cân nguyên liệu.' },
  { status: 'WEIGHED', icon: '✅', label: 'Đã cân xong', desc: 'Đã cân xong, chờ vận chuyển/nạp máy.' },
  { status: 'SENT', icon: '🚀', label: 'Đã gửi lệnh máy', desc: 'Đã gửi lệnh nạp vào máy nhuộm.' },
  { status: 'DONE', icon: '🏁', label: 'Hoàn tất', desc: 'Mẻ đã hoàn tất toàn bộ quy trình.' },
];

// Modals State
const timelineOpen = ref(false);
const activeTimelineData = ref<any | null>(null);
const alertModalOpen = ref(false);
const alertModalAction = ref<'ACKNOWLEDGE' | 'RESOLVE'>('ACKNOWLEDGE');
const selectedAlertForAction = ref<any | null>(null);
const alertActionNotes = ref('');

// Setup Realtime Connection — qua Reverb (kênh public "dashboard-events"), thay cho SSE
// cũ (/api/realtime/stream) đã bị gỡ 2026-07-25 vì giữ 1 kết nối HTTP sống mãi khiến
// php artisan serve trên Windows (không có concurrency thật) bị treo chỉ với 1 tab mở.
let liveWeightPoller: any = null;

const fetchLiveWeights = async () => {
  for (const ws of workstations.value) {
    try {
      const res = await axios.get(`/api/devices/readings/${ws.id}`);
      const live = res.data;
      ws.active = !!live?.active;
      ws.weight = live?.weight ?? 0;
      ws.lastUpdated = ws.active ? new Date().toLocaleTimeString('vi-VN', { hour12: false }) : 'Không có dữ liệu';
    } catch {
      // Bỏ qua lỗi 1 lần đọc — sẽ tự thử lại ở vòng poll tiếp theo.
    }
  }
};

const initRealtimeConnection = () => {
  // 1. Theo dõi trạng thái kết nối WebSocket (Reverb) qua pusher-js connector.
  const pusherConnection = (echo.connector as any)?.pusher?.connection;
  const mapping: Record<string, string> = {
    connected: 'Đã kết nối trực tiếp (Realtime Online)',
    connecting: 'Đang kết nối...',
    unavailable: 'Mất kết nối. Đang thử kết nối lại...',
    disconnected: 'Không có kết nối mạng',
    failed: 'Không có kết nối mạng',
  };
  const applyStatus = (state: string) => {
    connectionStatus.value = state === 'connected' ? 'ONLINE' : (state === 'connecting' ? 'RECONNECTING' : 'OFFLINE');
    connectionStatusText.value = mapping[state] || 'Chờ kết nối';
  };
  if (pusherConnection) {
    applyStatus(pusherConnection.state);
    pusherConnection.bind('state_change', (states: any) => applyStatus(states.current));
  }

  // 2. Lắng nghe mọi sự kiện nghiệp vụ (batch/dispatch/alert/...) qua kênh public — làm mới
  // snapshot ngay khi có thay đổi thay vì đợi polling.
  echo.channel('dashboard-events').listen('.event', () => {
    fetchOverviewSnapshot();
    fetchWeighingSnapshot();
    fetchMachinesSnapshot();
    fetchAlertsSnapshot();
    fetchManagementKpiSnapshot();
  });

  // 3. Cân điện tử đọc trọng lượng live qua REST polling nhẹ (giống WeighingStation.vue),
  // không cần kênh riêng vì tần suất đổi rất nhanh (mỗi giây) không hợp broadcast từng sự kiện.
  fetchLiveWeights();
  liveWeightPoller = setInterval(fetchLiveWeights, 2000);

  return () => {
    echo.leaveChannel('dashboard-events');
    if (pusherConnection) {
      pusherConnection.unbind('state_change');
    }
    if (liveWeightPoller) {
      clearInterval(liveWeightPoller);
      liveWeightPoller = null;
    }
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

  if (authStore.isAdmin) {
    fetchBpdbMachines();
    // 5s theo đúng cadence của BpdbMachines.vue — backend đã cache ~4s nên nhiều tab admin
    // mở cùng lúc không dội query trực tiếp vào BPDB.
    bpdbPollTimer = setInterval(fetchBpdbMachines, 5000);
  }

  disposeRealtime = initRealtimeConnection();
});

onUnmounted(() => {
  if (disposeRealtime) {
    disposeRealtime();
  }
  if (bpdbPollTimer) {
    clearInterval(bpdbPollTimer);
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
  return d.toLocaleString('vi-VN', { hour12: false });
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

/* Bố cục Tab 1: lưới trạng thái máy bên trái + bảng chú thích bên phải (yêu cầu
   2026-07-29). wrap để tự xuống dòng trên màn hình hẹp/tablet dọc thay vì bị bóp méo. */
.overview-status-layout {
  display: flex;
  gap: var(--space-xl);
  align-items: flex-start;
  flex-wrap: wrap;
}

.overview-status-layout .machines-grid-simple {
  flex: 3 1 480px;
}

.status-legend {
  flex: 1 1 240px;
  max-width: 320px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
}

.legend-title {
  font-size: 0.85rem;
  color: var(--text-title);
  margin-bottom: var(--space-md);
}

.legend-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.legend-item {
  display: flex;
  gap: var(--space-sm);
  align-items: flex-start;
}

.legend-icon { font-size: 1.1rem; line-height: 1.3; }

.legend-head {
  display: flex;
  align-items: baseline;
  gap: 6px;
  flex-wrap: wrap;
}

.legend-head strong { font-size: 0.82rem; color: var(--text-title); }

.legend-code {
  font-size: 0.62rem;
  font-family: monospace;
  color: var(--text-disabled);
}

.legend-desc {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin-top: 2px;
}

/* Trạng thái máy đơn giản (Tab 1) — chỉ icon + mã máy + nhãn trạng thái, dùng chung cho
   cả lưới BPDB (Admin) và lưới nội bộ (non-admin), theo yêu cầu 2026-07-29. */
.machines-grid-simple {
  display: grid;
  /* auto-fit (thay vì auto-fill) để ô tự giãn lấp đầy hàng khi ít máy, không để trống
     cột thừa; clamp() cho min-width co giãn mượt theo bề rộng màn hình/tablet nhà xưởng
     thay vì nhảy bậc theo breakpoint cố định (yêu cầu "linh động" 2026-07-29). */
  grid-template-columns: repeat(auto-fit, minmax(clamp(110px, 12vw, 160px), 1fr));
  gap: clamp(var(--space-sm), 1.5vw, var(--space-md));
}

.machine-status-tile {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-top: 3px solid var(--status-grey);
  border-radius: var(--radius-lg);
  padding: clamp(0.6rem, 1.4vw, var(--space-lg)) var(--space-sm);
  text-align: center;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.machine-status-tile:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.tile-icon { font-size: clamp(1.4rem, 2.4vw, 1.9rem); line-height: 1; }
.tile-code { font-weight: 700; color: var(--text-title); font-size: clamp(0.8rem, 1.1vw, 0.95rem); }
.tile-status { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; color: var(--text-muted); }

.machine-status-tile.bpdb-status-processing { border-top-color: #2563eb; }
.machine-status-tile.bpdb-status-waiting { border-top-color: #ca8a04; }
.machine-status-tile.bpdb-status-transitioning { border-top-color: #9333ea; }
.machine-status-tile.bpdb-status-completed_recently { border-top-color: #16a34a; }
.machine-status-tile.bpdb-status-cancelled, .machine-status-tile.bpdb-status-error { border-top-color: #dc2626; }
.machine-status-tile.bpdb-status-idle { border-top-color: #9ca3af; }

.machine-status-tile.card-state-idle { border-top-color: var(--status-grey); }
.machine-status-tile.card-state-new, .machine-status-tile.card-state-ready_to_weigh { border-top-color: var(--status-blue); }
.machine-status-tile.card-state-weighing { border-top-color: var(--status-yellow); }
.machine-status-tile.card-state-weighed { border-top-color: var(--status-green); }
.machine-status-tile.card-state-sent { border-top-color: var(--status-orange); }

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
