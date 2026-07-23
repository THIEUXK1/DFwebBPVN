<template>
  <div class="bpdb-admin-container">
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">ADMIN — BPDB / JIT</span>
        <h2>Giám sát tích hợp Color Service</h2>
        <p class="text-muted font-sm">Chỉ đọc — không sửa BPDB/Windows Service/SCCM/JIT/DLV.</p>
      </div>
      <div class="banner-right status-board font-sm">
        <div class="status-indicator">
          🔌 Kết nối BPDB:
          <strong :class="bpdbConnected ? 'text-success' : 'text-error'">
            {{ bpdbConnected ? 'ONLINE' : 'MẤT KẾT NỐI' }}
          </strong>
        </div>
        <button class="btn btn-secondary btn-sm" @click="fetchActiveTab" :disabled="loading">
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

    <!-- Tabs -->
    <div class="tab-bar mt-3">
      <button v-for="tab in tabs" :key="tab.key" class="tab-btn" :class="{ active: activeTab === tab.key }" @click="switchTab(tab.key)">
        {{ tab.label }}
      </button>
    </div>

    <!-- TAB: Tổng quan -->
    <div v-if="activeTab === 'overview'">
      <div class="summary-grid" v-if="overview">
        <div class="summary-card">
          <div class="summary-label">Tổng số liên kết</div>
          <div class="summary-value">{{ overview.summary.total_links }}</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Tỷ lệ hoàn thành (40)</div>
          <div class="summary-value text-success">{{ overview.summary.completion_rate ?? '—' }}%</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Tỷ lệ hủy (99)</div>
          <div class="summary-value text-error">{{ overview.summary.cancellation_rate ?? '—' }}%</div>
        </div>
        <div class="summary-card">
          <div class="summary-label">Thời gian xử lý TB (BPDB)</div>
          <div class="summary-value">{{ formatDuration(overview.summary.avg_processing_seconds) }}</div>
        </div>
      </div>

      <div class="section card-sec mt-3" v-if="overview">
        <h3>Phân bố theo trạng thái Adapter</h3>
        <div class="status-dist">
          <span v-for="(cnt, key) in overview.summary.by_adapter_status" :key="key" class="status-chip">
            {{ key }}: <strong>{{ cnt }}</strong>
          </span>
          <span v-if="!Object.keys(overview.summary.by_adapter_status || {}).length" class="text-muted">Chưa có dữ liệu</span>
        </div>
      </div>

      <div class="section card-sec mt-3" v-if="overview?.not_available">
        <h3 class="text-muted">Chưa có dữ liệu để hiển thị</h3>
        <ul class="font-xs text-muted">
          <li v-for="(msg, key) in overview.not_available" :key="key">{{ msg }}</li>
        </ul>
      </div>
    </div>

    <!-- TAB: Lệnh BPDB -->
    <div v-if="activeTab === 'taskLinks'">
      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>Toàn bộ liên kết BPDB gần đây</h3>
          <select v-model="statusFilter" @change="fetchTaskLinks" class="form-select font-xs">
            <option value="">Tất cả trạng thái</option>
            <option value="AWAITING_SCAN">AWAITING_SCAN</option>
            <option value="AMBIGUOUS">AMBIGUOUS</option>
            <option value="BPDB_ACCEPTED">BPDB_ACCEPTED</option>
            <option value="BPDB_ASSIGNED">BPDB_ASSIGNED</option>
            <option value="BPDB_PROCESSING">BPDB_PROCESSING</option>
            <option value="BPDB_COMPLETED">BPDB_COMPLETED</option>
            <option value="CANCELLED">CANCELLED</option>
          </select>
        </div>
        <table class="data-table">
          <thead><tr><th>Màu-Mã hàng</th><th>Máy/Tank</th><th>JIT</th><th>TaskTitle BPDB</th><th>Status BPDB</th><th>Adapter Status</th><th>Đồng bộ cuối</th></tr></thead>
          <tbody>
            <tr v-for="row in taskLinks" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td>{{ row.jit_queue_code || '—' }}</td>
              <td class="mono-text-sm">{{ row.bpdb_task_title || '—' }}</td>
              <td>{{ rawTaskStatusLabel(row.bpdb_task_status) }}</td>
              <td><span class="status-badge">{{ row.adapter_status }}</span></td>
              <td>{{ formatTime(row.last_synced_at) }}</td>
            </tr>
            <tr v-if="!taskLinks.length"><td colspan="7" class="text-muted text-center">Không có dữ liệu</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB: Máy VD -->
    <div v-if="activeTab === 'machines'">
      <div class="summary-grid" v-if="machineSummary">
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
      </div>
    </div>

    <!-- TAB: Nhu cầu bơm hóa chất -->
    <div v-if="activeTab === 'chemicalDemand'">
      <div class="connection-disclaimer font-xs">
        ℹ️ Trạng thái BPDB phản ánh trạng thái task, không đồng nghĩa chắc chắn với trạng thái bơm vật lý. Chỉ hiển thị "đã cấp hóa chất" khi có thêm bằng chứng xác nhận (lượng thực tế, tín hiệu hoàn thành từ hệ thống phía dưới) — hiện chưa có, nên giao diện chỉ dùng nhãn trung tính theo trạng thái task.
      </div>

      <div class="summary-grid mt-2" v-if="demandSummary">
        <div class="summary-card"><div class="summary-label">Máy đang chờ hệ thống xử lý</div><div class="summary-value" style="color:#ca8a04">{{ demandSummary.machinesWaiting }}</div></div>
        <div class="summary-card"><div class="summary-label">Máy đang có task xử lý</div><div class="summary-value" style="color:#2563eb">{{ demandSummary.machinesProcessing }}</div></div>
        <div class="summary-card"><div class="summary-label">Task chờ hệ thống xử lý</div><div class="summary-value">{{ demandSummary.tasksWaiting }}</div></div>
        <div class="summary-card"><div class="summary-label">Task đang xử lý</div><div class="summary-value">{{ demandSummary.tasksProcessing }}</div></div>
        <div class="summary-card"><div class="summary-label">Kết thúc hôm nay</div><div class="summary-value text-success">{{ demandSummary.completedToday }}</div></div>
        <div class="summary-card"><div class="summary-label">Lỗi/hủy hôm nay</div><div class="summary-value text-error">{{ demandSummary.errorOrCancelledToday }}</div></div>
        <div class="summary-card"><div class="summary-label">Chờ/xử lý lâu nhất</div><div class="summary-value">{{ formatDuration(demandSummary.longestWaitingSeconds) }}</div></div>
        <div class="summary-card"><div class="summary-label">JIT queue tải cao nhất</div><div class="summary-value">{{ demandSummary.topJitQueue || '—' }}<span v-if="demandSummary.topJitQueue" class="font-xs text-muted"> ({{ demandSummary.topJitQueueLoad }})</span></div></div>
      </div>
      <p v-if="demandSummary?.stuckCount || demandSummary?.orphanedCount" class="text-error font-xs mt-1">
        ⚠️ {{ demandSummary.stuckCount }} task bị kẹt (vượt ngưỡng theo trạng thái) · {{ demandSummary.orphanedCount }} task tồn đọng cũ (quá {{ orphanThresholdLabel }}) — xem 2 nhóm bên dưới, dữ liệu vẫn được giữ nguyên, không bị xóa.
      </p>
      <p class="text-muted font-xs mt-1">
        Cập nhật gần nhất: <strong>{{ formatTime(lastSyncedAt) }}</strong> · Nguồn: <strong>BPDB — Chỉ đọc</strong>
        <span v-if="dataStale" class="text-error"> · Dữ liệu có thể đã cũ</span>
      </p>

      <div class="section card-sec mt-2">
        <h3>Đang hoạt động ({{ demandActiveRecent.length }})</h3>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Tank</th><th>JIT</th><th>Trạng thái</th><th>Task</th><th>Thời gian chờ/xử lý</th><th>Số hóa chất</th></tr></thead>
          <tbody>
            <tr v-for="t in demandActiveRecent" :key="t.taskId" class="demand-row" :class="'demand-' + t.displayStatus.toLowerCase()" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatDuration(t.waitingSeconds) }}</td>
              <td>{{ t.chemicalCount }}</td>
            </tr>
            <tr v-if="!demandActiveRecent.length"><td colspan="7" class="text-muted text-center">Không có task nào đang hoạt động bình thường</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3" v-if="demandStuck.length">
        <h3 class="text-error">⚠️ Bị kẹt ({{ demandStuck.length }})</h3>
        <p class="font-xs text-muted">Vượt ngưỡng bình thường theo trạng thái (cấu hình tại Admin, không hard-code) — cần kiểm tra, không nhất thiết là rác.</p>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Tank</th><th>JIT</th><th>Trạng thái</th><th>Task</th><th>Thời gian chờ/xử lý</th><th>Cảnh báo</th></tr></thead>
          <tbody>
            <tr v-for="t in demandStuck" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td class="text-error">{{ formatDuration(t.waitingSeconds) }}</td>
              <td class="font-xs text-error">{{ t.stuckWarning?.code }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3 class="text-muted">Tồn đọng cũ ({{ demandOrphaned.length }})</h3>
          <label class="font-xs" style="display:flex;align-items:center;gap:0.3rem;cursor:pointer;">
            <input type="checkbox" v-model="hideOrphaned" /> Ẩn task tồn đọng
          </label>
        </div>
        <p class="font-xs text-muted">Quá {{ orphanThresholdLabel }} kể từ lúc tạo — nhiều khả năng là rác/orphan trong BPDB, không phải nhu cầu thật đang chờ. Vẫn giữ nguyên dữ liệu, chỉ ẩn khỏi danh sách chính theo mặc định.</p>
        <table class="data-table" v-if="!hideOrphaned && demandOrphaned.length">
          <thead><tr><th>Máy</th><th>Tank</th><th>JIT</th><th>Trạng thái</th><th>Task</th><th>Tuổi task</th></tr></thead>
          <tbody>
            <tr v-for="t in demandOrphaned" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatDuration(t.ageSeconds) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else-if="hideOrphaned && demandOrphaned.length" class="text-muted font-sm">{{ demandOrphaned.length }} task đang bị ẩn — bỏ chọn "Ẩn task tồn đọng" để xem.</p>
        <p v-else class="text-muted font-sm">Không có task tồn đọng cũ.</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>Task đã kết thúc gần đây ({{ demandCompleted.length }})</h3>
        <p class="font-xs text-muted">"Kết thúc" = BPDB ghi nhận <code>TaskStatus=40</code> — chưa chắc đồng nghĩa đã cấp hóa chất xong vật lý (xem cảnh báo đầu trang).</p>
        <table class="data-table" v-if="demandCompleted.length">
          <thead><tr><th>Máy</th><th>Tank</th><th>JIT</th><th>Task</th><th>Kết thúc lúc</th><th>Số hóa chất</th></tr></thead>
          <tbody>
            <tr v-for="t in demandCompleted" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td>{{ t.jitQueue || '—' }}</td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td>{{ formatTime(t.finishTime) }}</td>
              <td>{{ t.chemicalCount }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">Chưa có task nào kết thúc trong cửa sổ thời gian gần đây.</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>Có lỗi/hủy ({{ demandErrors.length }})</h3>
        <table class="data-table" v-if="demandErrors.length">
          <thead><tr><th>Máy</th><th>Tank</th><th>Trạng thái</th><th>Task</th><th>Lỗi</th><th>Tạo lúc</th></tr></thead>
          <tbody>
            <tr v-for="t in demandErrors" :key="t.taskId" class="demand-row" @click="openTaskDetail(t.taskId)">
              <td>{{ t.machineCode }}</td>
              <td>{{ t.tank || '—' }}</td>
              <td><span class="op-status-badge" :class="'status-' + t.displayStatus.toLowerCase()">{{ demandStatusLabel(t.displayStatus) }}</span></td>
              <td class="mono-text-sm">{{ t.taskId.slice(0, 8) }}…</td>
              <td class="font-xs text-error">{{ t.errorMessage || '—' }}</td>
              <td>{{ formatTime(t.createdAt) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">Không có task lỗi/hủy nào gần đây.</p>
      </div>
    </div>

    <!-- TAB: Thống kê hoạt động nguyên liệu -->
    <div v-if="activeTab === 'materialActivity'">
      <div class="connection-disclaimer font-xs">
        ℹ️ Đây là thống kê <strong>SỐ LƯỢNG TASK hoàn thành</strong> theo máy/nhóm nguyên liệu — KHÔNG PHẢI khối lượng tiêu hao. Đã xác minh (2026-07-20): <code>SUP_TaskDetails.GramsDosed/DaDosare</code> (lượng thực tế/yêu cầu) hầu như luôn = 0 kể từ 11/2025, chỉ có số liệu thật trong khoảng 03-10/2025. Tỷ lệ có dữ liệu khối lượng cho khoảng thời gian đang chọn hiển thị ở thẻ cuối bên dưới — tự tính lại mỗi lần đổi bộ lọc, không cố định.
      </div>

      <div class="filter-row mt-2" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <label class="font-xs">Từ ngày <input type="date" v-model="maFrom" class="form-select font-xs" /></label>
        <label class="font-xs">Đến ngày <input type="date" v-model="maTo" class="form-select font-xs" /></label>
        <select v-model="maMachineFilter" class="form-select font-xs">
          <option value="">Tất cả máy</option>
          <option v-for="m in maByMachine" :key="m.machineCode" :value="m.machineCode">{{ m.machineCode }}</option>
        </select>
        <button class="btn btn-secondary btn-sm" @click="fetchMaterialActivity" :disabled="maLoading">
          {{ maLoading ? 'Đang tải...' : 'Áp dụng' }}
        </button>
      </div>

      <div class="summary-grid mt-2" v-if="maSummary">
        <div class="summary-card"><div class="summary-label">Task hoàn thành</div><div class="summary-value">{{ maSummary.completedTaskCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Số máy có hoạt động</div><div class="summary-value">{{ maSummary.machineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Số nhóm/mã nguyên liệu</div><div class="summary-value">{{ maSummary.distinctMaterialCodeCount }}</div></div>
        <div class="summary-card">
          <div class="summary-label">Có dữ liệu khối lượng</div>
          <div class="summary-value" :class="maSummary.weightDataCoveragePercent < 5 ? 'text-error' : 'text-success'">{{ maSummary.weightDataCoveragePercent }}%</div>
        </div>
      </div>
      <p v-if="maSummary && maSummary.weightDataCoveragePercent < 5" class="text-error font-xs mt-1">
        ⚠️ SUP_TaskDetails.GramsDosed (BPDB) gần như không có dữ liệu khối lượng ({{ maSummary.weightDataRowsAvailable }}/{{ maSummary.weightDataRowsTotal }} dòng) — số liệu "Task hoàn thành" ở trên chỉ đếm số lượng, không phải khối lượng.
      </p>

      <div class="connection-disclaimer font-xs mt-2" v-if="maSummary?.supStorico">
        ℹ️ <strong>Cập nhật 2026-07-21:</strong> tìm được nguồn khối lượng thật khác — <code>BPVN2025.SUP_Storico</code> (đã trace chéo xác nhận là chi tiết thật của cùng task ở trên). Thẻ dưới đây dùng nguồn này, KHÁC với "Task hoàn thành" phía trên.
      </div>
      <div class="summary-grid mt-2" v-if="maSummary?.supStorico">
        <div class="summary-card"><div class="summary-label">Dòng định lượng (SUP_Storico)</div><div class="summary-value">{{ maSummary.supStorico.dosingLineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Lượng yêu cầu (g)</div><div class="summary-value">{{ maSummary.supStorico.sumRequestedGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card"><div class="summary-label">Lượng thực tế (g)</div><div class="summary-value text-success">{{ maSummary.supStorico.sumActualGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card">
          <div class="summary-label">Chênh lệch (g)</div>
          <div class="summary-value" :class="maSummary.supStorico.sumDeviationGrams < 0 ? 'text-error' : ''">{{ maSummary.supStorico.sumDeviationGrams.toLocaleString('vi-VN') }}</div>
        </div>
      </div>
      <p v-if="maSummary?.unmappedMachineTaskWarning" class="text-error font-xs mt-1">
        ⚠️ Có task với mã máy không khớp danh mục DyeMachines (VD%) trong khoảng thời gian này — không bị loại âm thầm, xem "Số máy có hoạt động" ({{ maSummary.machineCount }}) so với số máy khớp được ({{ maSummary.mappedMachineCount }}).
      </p>

      <div class="section card-sec mt-3">
        <h3>Theo máy ({{ maByMachine.length }})</h3>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Task hoàn thành</th><th>Số nhóm nguyên liệu</th><th>Hoạt động gần nhất</th><th>Lượng thực tế (g, SUP_Storico)</th></tr></thead>
          <tbody>
            <tr v-for="m in maByMachine" :key="m.machineCode">
              <td>{{ m.machineCode }}</td>
              <td>
                <div class="ma-bar-cell">
                  <div class="ma-bar" :style="{ width: maBarWidth(m.completedTaskCount, maMaxMachineCount) }"></div>
                  <span>{{ m.completedTaskCount }}</span>
                </div>
              </td>
              <td>{{ m.distinctMaterialCodeCount }}</td>
              <td>{{ formatTime(m.lastFinishTime) }}</td>
              <td>{{ m.supStorico ? m.supStorico.sumActualGrams.toLocaleString('vi-VN') : '—' }}</td>
            </tr>
            <tr v-if="!maByMachine.length"><td colspan="5" class="text-muted text-center">Không có dữ liệu trong khoảng thời gian này</td></tr>
          </tbody>
        </table>
        <p v-if="maByMachineData?.unmappedTaskCount" class="font-xs text-muted mt-1">{{ maByMachineData.unmappedTaskCount }} task không khớp máy VD nào trong danh mục — không tính vào bảng trên.</p>
      </div>

      <div class="section card-sec mt-3">
        <h3>Theo nhóm/mã nguyên liệu ({{ maByMaterial.length }})</h3>
        <p class="font-xs text-muted">Từ 11/2025, phần lớn mã là mã NHÓM (vd "NYLON DYES", "CATION DYES"), không phải mã hóa chất cụ thể — xem báo cáo xác minh.</p>
        <table class="data-table">
          <thead><tr><th>Mã/nhóm</th><th>Tên mẫu</th><th>Task hoàn thành</th><th>Số máy dùng</th></tr></thead>
          <tbody>
            <tr v-for="mt in maByMaterial" :key="mt.materialCode">
              <td>{{ mt.materialCode }}</td>
              <td>
                {{ mt.sampleName }}
                <span v-if="mt.multipleNamesWarning" class="text-error font-xs" title="Mã này từng gắn nhiều tên khác nhau trong khoảng thời gian này"> ⚠️ nhiều tên</span>
              </td>
              <td>{{ mt.completedTaskCount }}</td>
              <td>{{ mt.machineCount }}</td>
            </tr>
            <tr v-if="!maByMaterial.length"><td colspan="4" class="text-muted text-center">Không có dữ liệu trong khoảng thời gian này</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3" v-if="maTimeseries.length">
        <h3>Theo ngày</h3>
        <table class="data-table">
          <thead><tr><th>Ngày</th><th>Task hoàn thành</th></tr></thead>
          <tbody>
            <tr v-for="p in maTimeseries" :key="p.periodStart">
              <td>{{ p.periodStart }}</td>
              <td>
                <div class="ma-bar-cell">
                  <div class="ma-bar" :style="{ width: maBarWidth(p.completedTaskCount, maMaxDayCount) }"></div>
                  <span>{{ p.completedTaskCount }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>Chi tiết task ({{ maDetailTotal }})</h3>
          <a class="btn btn-secondary btn-sm" :href="maExportUrl" target="_blank" rel="noopener">⬇️ Xuất Excel</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Task</th><th>Kết thúc</th><th>Nguyên liệu</th></tr></thead>
          <tbody>
            <tr v-for="t in maDetail" :key="t.taskId">
              <td>{{ t.machineCode || '—' }}</td>
              <td class="mono-text-sm">{{ t.taskTitle }}</td>
              <td>{{ formatTime(t.finishTime) }}</td>
              <td class="font-xs">
                <span v-for="(l, i) in t.materialLines" :key="i">{{ l.materialCode }}<span v-if="i < t.materialLines.length - 1">, </span></span>
              </td>
            </tr>
            <tr v-if="!maDetail.length"><td colspan="4" class="text-muted text-center">Không có dữ liệu</td></tr>
          </tbody>
        </table>
        <div class="flex-header mt-1" v-if="maDetailTotal > maPerPage">
          <button class="btn btn-secondary btn-sm" :disabled="maPage <= 1" @click="changeMaPage(maPage - 1)">← Trước</button>
          <span class="font-xs">Trang {{ maPage }} / {{ Math.max(1, Math.ceil(maDetailTotal / maPerPage)) }}</span>
          <button class="btn btn-secondary btn-sm" :disabled="maPage * maPerPage >= maDetailTotal" @click="changeMaPage(maPage + 1)">Sau →</button>
        </div>
      </div>
    </div>

    <!-- TAB: Cấp máy (Mức A — BPVN2025.SUP_Storico) -->
    <div v-if="activeTab === 'machineFeeding'">
      <div class="connection-disclaimer font-xs">
        ℹ️ <strong>Mức A — có bằng chứng đầy đủ.</strong> Nguồn: <code>BPVN2025.SUP_Storico</code> (đã xác minh 2026-07-21, chi tiết thật của cùng task trong SUP_Tasks). Có Máy/Tank/Lô/mã sản phẩm/lượng yêu cầu/lượng thực tế (khác nhau thật)/thời gian bắt đầu-kết thúc riêng từng dòng định lượng. Chỉ đọc — không có nút xác nhận/hủy/retry.
      </div>

      <div class="filter-row mt-2" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
        <label class="font-xs">Từ ngày <input type="date" v-model="mfFrom" class="form-select font-xs" /></label>
        <label class="font-xs">Đến ngày <input type="date" v-model="mfTo" class="form-select font-xs" /></label>
        <select v-model="mfMachineFilter" class="form-select font-xs">
          <option value="">Tất cả máy</option>
          <option v-for="m in mfByMachine" :key="m.machineCode" :value="m.machineCode">{{ m.machineCode }}</option>
        </select>
        <button class="btn btn-secondary btn-sm" @click="fetchMachineFeeding" :disabled="mfLoading">
          {{ mfLoading ? 'Đang tải...' : 'Áp dụng' }}
        </button>
      </div>

      <div class="summary-grid mt-2" v-if="mfSummary">
        <div class="summary-card"><div class="summary-label">Dòng định lượng</div><div class="summary-value">{{ mfSummary.dosingLineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Số máy</div><div class="summary-value">{{ mfSummary.machineCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Số lô</div><div class="summary-value">{{ mfSummary.lotCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Số mã sản phẩm</div><div class="summary-value">{{ mfSummary.distinctProductCount }}</div></div>
        <div class="summary-card"><div class="summary-label">Lượng yêu cầu (g)</div><div class="summary-value">{{ mfSummary.sumRequestedGrams.toLocaleString('vi-VN') }}</div></div>
        <div class="summary-card"><div class="summary-label">Lượng thực tế (g)</div><div class="summary-value text-success">{{ mfSummary.sumActualGrams.toLocaleString('vi-VN') }}</div></div>
      </div>

      <div class="section card-sec mt-3">
        <h3>Theo máy ({{ mfByMachine.length }})</h3>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Dòng định lượng</th><th>Số lô</th><th>Lượng yêu cầu (g)</th><th>Lượng thực tế (g)</th><th>Gần nhất</th></tr></thead>
          <tbody>
            <tr v-for="m in mfByMachine" :key="m.machineCode">
              <td>{{ m.machineCode }}</td>
              <td>{{ m.dosingLineCount }}</td>
              <td>{{ m.lotCount }}</td>
              <td>{{ m.sumRequestedGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ m.sumActualGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ formatTime(m.lastFinishTime) }}</td>
            </tr>
            <tr v-if="!mfByMachine.length"><td colspan="6" class="text-muted text-center">Không có dữ liệu trong khoảng thời gian này</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <h3>Theo mã sản phẩm ({{ mfByMaterial.length }})</h3>
        <table class="data-table">
          <thead><tr><th>Mã sản phẩm</th><th>Dòng định lượng</th><th>Số máy dùng</th><th>Lượng yêu cầu (g)</th><th>Lượng thực tế (g)</th></tr></thead>
          <tbody>
            <tr v-for="mt in mfByMaterial" :key="mt.materialCode">
              <td>{{ mt.materialCode }}</td>
              <td>{{ mt.dosingLineCount }}</td>
              <td>{{ mt.machineCount }}</td>
              <td>{{ mt.sumRequestedGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ mt.sumActualGrams.toLocaleString('vi-VN') }}</td>
            </tr>
            <tr v-if="!mfByMaterial.length"><td colspan="5" class="text-muted text-center">Không có dữ liệu trong khoảng thời gian này</td></tr>
          </tbody>
        </table>
      </div>

      <div class="section card-sec mt-3">
        <div class="flex-header">
          <h3>Chi tiết dòng định lượng ({{ mfDetailTotal }})</h3>
          <a class="btn btn-secondary btn-sm" :href="mfExportUrl" target="_blank" rel="noopener">⬇️ Xuất Excel</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Tank</th><th>Lô</th><th>Mã SP</th><th>Yêu cầu (g)</th><th>Thực tế (g)</th><th>Bắt đầu</th><th>Kết thúc</th></tr></thead>
          <tbody>
            <tr v-for="(r, i) in mfDetail" :key="i">
              <td>{{ r.machineCode }}</td>
              <td>{{ r.tankLetterCode || r.tank }}</td>
              <td class="mono-text-sm">{{ r.lot }}</td>
              <td>{{ r.materialCode }}</td>
              <td>{{ r.requestedGrams.toLocaleString('vi-VN') }}</td>
              <td :class="r.actualGrams < r.requestedGrams ? 'text-error' : ''">{{ r.actualGrams.toLocaleString('vi-VN') }}</td>
              <td>{{ formatTime(r.startTime) }}</td>
              <td>{{ formatTime(r.finishTime) }}</td>
            </tr>
            <tr v-if="!mfDetail.length"><td colspan="8" class="text-muted text-center">Không có dữ liệu</td></tr>
          </tbody>
        </table>
        <div class="flex-header mt-1" v-if="mfDetailTotal > mfPerPage">
          <button class="btn btn-secondary btn-sm" :disabled="mfPage <= 1" @click="changeMfPage(mfPage - 1)">← Trước</button>
          <span class="font-xs">Trang {{ mfPage }} / {{ Math.max(1, Math.ceil(mfDetailTotal / mfPerPage)) }}</span>
          <button class="btn btn-secondary btn-sm" :disabled="mfPage * mfPerPage >= mfDetailTotal" @click="changeMfPage(mfPage + 1)">Sau →</button>
        </div>
      </div>
    </div>

    <!-- TAB: Theo dõi xử lý JIT ("Vận chuyển" — Mức B, tái dùng dữ liệu tab Máy VD) -->
    <div v-if="activeTab === 'transport'">
      <div class="connection-disclaimer font-xs">
        ℹ️ <strong>Mức B — chỉ có dữ liệu chung.</strong> BPDB không tách được bước "đang vận chuyển" riêng khỏi trạng thái task chung (đã rà soát BPVN2025/INTEGDB/INTERFACE_SCC/StandardDB 2026-07-21 — các bảng TRS_*/DLV_* chi tiết từng bước đều rỗng, không được dùng). Bảng dưới đây chỉ hiển thị thông tin có bằng chứng thật: task đang xử lý, máy đích, tank, JIT queue, thời gian — KHÔNG gọi là "đang vận chuyển".
      </div>
      <p class="text-muted font-xs mt-1">Dữ liệu giống hệt tab "Máy VD" (đọc lại, không gọi thêm API) — chỉ trình bày lại theo góc nhìn "task nào đang ở đâu".</p>

      <table class="data-table mt-2">
        <thead><tr><th>Task</th><th>Máy đích</th><th>Tank</th><th>JIT queue</th><th>Trạng thái có bằng chứng</th><th>Bắt đầu</th><th>Cập nhật cuối</th></tr></thead>
        <tbody>
          <tr v-for="m in transportRows" :key="m.machineCode">
            <td class="mono-text-sm">{{ m.currentTask.taskTitle }}</td>
            <td>{{ m.machineCode }}</td>
            <td>{{ m.currentTask.tank || '—' }}</td>
            <td>{{ m.currentTask.jitQueue || '—' }}</td>
            <td><span class="op-status-badge" :class="'status-' + m.operationalStatus.toLowerCase()">{{ m.operationalStatus }}</span></td>
            <td>{{ formatTime(m.currentTask.workStartTime) }}</td>
            <td>{{ formatTime(m.lastActivityAt) }}</td>
          </tr>
          <tr v-if="!transportRows.length"><td colspan="7" class="text-muted text-center">Không có task nào đang hoạt động</td></tr>
        </tbody>
      </table>
    </div>

    <!-- TAB: Định tuyến JIT -->
    <div v-if="activeTab === 'jit'">
      <div class="section card-sec mt-3">
        <h3>Quy tắc định tuyến JIT (port từ VBA)</h3>
        <table class="data-table">
          <thead><tr><th>Máy</th><th>Tank</th><th>Mức nước</th><th>JIT Queue</th><th>B24 Route</th><th>QR Mode</th><th>Ưu tiên</th></tr></thead>
          <tbody>
            <tr v-for="rule in jitRules.slice(0, 80)" :key="rule.id">
              <td>{{ rule.machine_code }}</td>
              <td>{{ rule.tank_code }}</td>
              <td>{{ rule.water_level ?? 'bất kỳ' }}</td>
              <td>{{ rule.jit_queue_code || '—' }}</td>
              <td class="font-xs">{{ rule.b24_route }}</td>
              <td>{{ rule.qr_mode }}</td>
              <td>{{ rule.priority }}</td>
            </tr>
          </tbody>
        </table>
        <p class="text-muted font-xs mt-1">Hiển thị {{ Math.min(80, jitRules.length) }}/{{ jitRules.length }} quy tắc — nguồn: {{ jitRules[0]?.source_reference || 'VBA' }}</p>
      </div>
    </div>

    <!-- TAB: Lỗi đồng bộ -->
    <div v-if="activeTab === 'errors'">
      <div class="section card-sec mt-3" v-if="overview">
        <h3>⚠️ Đơn bị kẹt (chưa tiến triển &gt; {{ overview.stuck_threshold_hours }}h)</h3>
        <table class="data-table" v-if="overview.stuck_links.length">
          <thead><tr><th>Màu-Mã hàng</th><th>Máy/Tank</th><th>JIT</th><th>Adapter Status</th><th>Tạo lúc</th></tr></thead>
          <tbody>
            <tr v-for="row in overview.stuck_links" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td>{{ row.jit_queue_code || '—' }}</td>
              <td><span class="status-badge">{{ row.adapter_status }}</span></td>
              <td>{{ formatTime(row.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">Không có đơn nào bị kẹt.</p>
      </div>

      <div class="section card-sec mt-3" v-if="overview">
        <h3>🔀 Khớp mơ hồ — cần xác nhận thủ công</h3>
        <table class="data-table" v-if="overview.ambiguous_links.length">
          <thead><tr><th>Màu-Mã hàng</th><th>Máy/Tank</th><th>Lỗi/Ghi chú</th><th>Tạo lúc</th></tr></thead>
          <tbody>
            <tr v-for="row in overview.ambiguous_links" :key="row.id">
              <td>{{ row.color }}-{{ row.product_code }}</td>
              <td>{{ row.machine_code }} / {{ row.tank_code }}</td>
              <td class="font-xs">{{ row.bpdb_error_message }}</td>
              <td>{{ formatTime(row.created_at) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted font-sm">Không có trường hợp mơ hồ nào.</p>
      </div>
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

    <!-- Chemical task detail modal -->
    <div v-if="selectedTask" class="modal-overlay" @click.self="selectedTask = null">
      <div class="detail-drawer">
        <div class="flex-header">
          <h3>Chi tiết task {{ selectedTask.machineCode }}</h3>
          <button class="btn btn-secondary btn-sm" @click="selectedTask = null">Đóng</button>
        </div>
        <p class="font-xs text-muted mono-text-sm">{{ selectedTask.taskTitle }}</p>
        <p class="font-xs">
          Trạng thái: <strong class="op-status-badge" :class="'status-' + selectedTask.displayStatus.toLowerCase()">{{ demandStatusLabel(selectedTask.displayStatus) }}</strong>
          · Tank {{ selectedTask.tank || '—' }}
        </p>
        <p class="font-xs text-muted">
          Tạo lúc: {{ formatTime(selectedTask.createdAt) }} · Bắt đầu: {{ formatTime(selectedTask.workStartTime) }} · Kết thúc: {{ formatTime(selectedTask.finishTime) }}
        </p>
        <p v-if="selectedTask.errorMessage" class="text-error font-xs">⚠️ {{ selectedTask.errorMessage }}</p>

        <h4 class="font-sm mt-2">Hóa chất ({{ selectedTask.chemicalLines?.length || 0 }})</h4>
        <p class="font-xs" :class="selectedTask.chemicalLinesSource === 'SUP_Storico' ? 'text-success' : 'text-muted'">
          <span v-if="selectedTask.chemicalLinesSource === 'SUP_Storico'">✅ Số liệu thật (BPVN2025.SUP_Storico) — lượng yêu cầu/thực tế đáng tin cậy.</span>
          <span v-else>⚠️ Không tìm thấy dữ liệu ở BPVN2025.SUP_Storico cho khung giờ task này — hiển thị từ BPDB.SUP_TaskDetails (thường = 0 kể từ 11/2025).</span>
        </p>
        <table class="data-table">
          <thead><tr><th>#</th><th>Mã</th><th>Tên</th><th>Yêu cầu</th><th>Thực tế</th><th>Đơn vị</th><th>Trạng thái (suy diễn)</th></tr></thead>
          <tbody>
            <tr v-for="l in selectedTask.chemicalLines" :key="l.orderNum">
              <td>{{ l.orderNum }}</td>
              <td>{{ l.chemicalCode }}</td>
              <td>{{ l.chemicalName || '—' }}</td>
              <td>{{ l.requestedAmount }}</td>
              <td>{{ l.dosedAmount }}</td>
              <td>{{ l.unit }}</td>
              <td class="font-xs">{{ l.lineStatusDerived }}</td>
            </tr>
            <tr v-if="!selectedTask.chemicalLines?.length"><td colspan="7" class="text-muted text-center">Không có dữ liệu hóa chất</td></tr>
          </tbody>
        </table>
        <p class="font-xs text-muted mt-1">Cột "Trạng thái (suy diễn)" so sánh Yêu cầu/Thực tế — SUP_TaskDetails không có cột trạng thái/lỗi riêng theo dòng trong BPDB.</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const tabs = [
  { key: 'overview', label: 'Tổng quan' },
  { key: 'machines', label: 'Máy VD' },
  { key: 'chemicalDemand', label: 'Nhu cầu bơm hóa chất' },
  { key: 'materialActivity', label: 'Thống kê hoạt động nguyên liệu' },
  { key: 'machineFeeding', label: 'Cấp máy' },
  { key: 'transport', label: 'Theo dõi xử lý JIT' },
  { key: 'jit', label: 'Định tuyến JIT' },
  { key: 'taskLinks', label: 'Lệnh BPDB' },
  { key: 'errors', label: 'Lỗi đồng bộ' },
];
const activeTab = ref('overview');

// Nhãn TRUNG TÍNH theo yêu cầu — không dùng "Đang bơm"/"Đã bơm xong" vì BPDB không phân
// biệt được cân/hòa tan/bơm vật lý và chưa có bằng chứng xác nhận đã cấp hóa chất xong
// (xem cảnh báo đầu tab). Phải khớp displayStatus trả về từ BpdbChemicalDemandService.
const DEMAND_STATUS_LABELS: Record<string, string> = {
  AWAITING_PROCESSING: 'Chờ hệ thống xử lý',
  TRANSITIONING: 'Đang chuyển trạng thái',
  PROCESSING: 'Đang được hệ thống xử lý',
  ENDED: 'Task đã kết thúc',
  CANCELLED: 'Đã hủy/xóa',
  ERROR: 'Lỗi',
  UNKNOWN: 'Không rõ',
};
const demandStatusLabel = (status: string) => DEMAND_STATUS_LABELS[status] || status;

// Nhãn trung tính cho SUP_Tasks.TaskStatus thô (10/20/30/40/99) — dùng ở những chỗ còn
// hiển thị mã gốc thay vì displayStatus đã suy diễn (yêu cầu 2026-07-21, "CHỐT NGUỒN DỮ
// LIỆU"). Khớp App\Services\ColorService\BpdbTaskStatusLabels ở backend.
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

const overview = ref<any>(null);
const taskLinks = ref<any[]>([]);
const jitRules = ref<any[]>([]);
const machines = ref<any[]>([]);
const machineSummary = ref<any>(null);
const selectedMachine = ref<any>(null);
const demandActive = ref<any[]>([]);
const demandCompleted = ref<any[]>([]);
const demandErrors = ref<any[]>([]);
const demandSummary = ref<any>(null);
const selectedTask = ref<any>(null);
const hideOrphaned = ref(true); // mặc định ẩn task tồn đọng, không xóa khỏi dữ liệu
const statusFilter = ref('');
const machineStatusFilter = ref('');

// Tab "Thống kê hoạt động nguyên liệu" — mặc định 30 ngày gần nhất, khớp resolveRange() phía
// backend khi không truyền from/to.
const toDateInput = (d: Date) => d.toISOString().slice(0, 10);
const maTo = ref(toDateInput(new Date()));
const maFrom = ref(toDateInput(new Date(Date.now() - 30 * 24 * 3600 * 1000)));
const maMachineFilter = ref('');
const maSummary = ref<any>(null);
const maByMachine = ref<any[]>([]);
const maByMachineData = ref<any>(null);
const maByMaterial = ref<any[]>([]);
const maTimeseries = ref<any[]>([]);
const maDetail = ref<any[]>([]);
const maDetailTotal = ref(0);
const maPage = ref(1);
const maPerPage = 20;
const maLoading = ref(false);

const maMaxMachineCount = computed(() => Math.max(1, ...maByMachine.value.map((m: any) => m.completedTaskCount)));
const maMaxDayCount = computed(() => Math.max(1, ...maTimeseries.value.map((p: any) => p.completedTaskCount)));
const maBarWidth = (value: number, max: number) => `${Math.max(4, Math.round((value / max) * 100))}%`;
const maExportUrl = computed(() => {
  const params = new URLSearchParams({ from: maFrom.value, to: maTo.value });
  if (maMachineFilter.value) params.set('machine_code', maMachineFilter.value);
  return `/api/admin/bpdb/material-activity/detail/export?${params.toString()}`;
});

// Tab "Cấp máy" (Mức A — BPVN2025.SUP_Storico), cùng mặc định 30 ngày như "Thống kê hoạt
// động nguyên liệu".
const mfFrom = ref(toDateInput(new Date(Date.now() - 30 * 24 * 3600 * 1000)));
const mfTo = ref(toDateInput(new Date()));
const mfMachineFilter = ref('');
const mfSummary = ref<any>(null);
const mfByMachine = ref<any[]>([]);
const mfByMaterial = ref<any[]>([]);
const mfDetail = ref<any[]>([]);
const mfDetailTotal = ref(0);
const mfPage = ref(1);
const mfPerPage = 20;
const mfLoading = ref(false);
const mfExportUrl = computed(() => {
  const params = new URLSearchParams({ from: mfFrom.value, to: mfTo.value });
  if (mfMachineFilter.value) params.set('machine_code', mfMachineFilter.value);
  return `/api/admin/bpdb/machine-feeding/detail/export?${params.toString()}`;
});

const mfRangeParams = () => {
  const params: Record<string, string> = { from: mfFrom.value, to: mfTo.value };
  if (mfMachineFilter.value) params.machine_code = mfMachineFilter.value;
  return params;
};

const fetchMachineFeeding = async () => {
  mfLoading.value = true;
  try {
    const params = mfRangeParams();
    const [summaryRes, byMachineRes, byMaterialRes] = await Promise.all([
      axios.get('/api/admin/bpdb/machine-feeding/summary', { params }),
      axios.get('/api/admin/bpdb/machine-feeding/by-machine', { params: { from: params.from, to: params.to } }),
      axios.get('/api/admin/bpdb/machine-feeding/by-material', { params }),
    ]);
    mfSummary.value = summaryRes.data;
    mfByMachine.value = byMachineRes.data.machines;
    mfByMaterial.value = byMaterialRes.data.materials;
    mfPage.value = 1;
    await fetchMachineFeedingDetail();
  } finally {
    mfLoading.value = false;
  }
};

const fetchMachineFeedingDetail = async () => {
  const res = await axios.get('/api/admin/bpdb/machine-feeding/detail', {
    params: { ...mfRangeParams(), page: mfPage.value, per_page: mfPerPage },
  });
  mfDetail.value = res.data.rows;
  mfDetailTotal.value = res.data.total;
};

const changeMfPage = async (page: number) => {
  mfPage.value = page;
  await fetchMachineFeedingDetail();
};

// Tab "Theo dõi xử lý JIT" ("Vận chuyển", Mức B) — tái dùng nguyên `machines` đã fetch cho
// tab "Máy VD", KHÔNG gọi thêm API riêng (đúng nguyên tắc chỉ hiển thị dữ liệu có bằng
// chứng, không suy diễn thêm gì ngoài trạng thái task đã biết).
const transportRows = computed(() => machines.value.filter((m: any) => m.currentTask));

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

// Tách 3 nhóm theo activityBucket (BpdbChemicalDemandService) — không lọc bằng ngưỡng lặp
// lại ở frontend, chỉ đọc cờ đã tính sẵn ở backend (nguồn ngưỡng duy nhất, cấu hình qua
// app.feature_flags).
const demandActiveRecent = computed(() => demandActive.value.filter(t => t.activityBucket === 'ACTIVE_RECENT'));
const demandStuck = computed(() => demandActive.value.filter(t => t.activityBucket === 'STUCK'));
const demandOrphaned = computed(() => demandActive.value.filter(t => t.activityBucket === 'ORPHANED_OLD'));
const orphanThresholdLabel = computed(() => {
  const hours = demandSummary.value?.orphanThresholdHours;
  return hours ? `${hours}h` : '24h';
});

const formatTime = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

const formatDuration = (seconds: number | null) => {
  if (seconds === null || seconds === undefined) return '—';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m} phút ${s}s`;
};

const fetchOverview = async () => {
  const res = await axios.get('/api/admin/bpdb/overview');
  overview.value = res.data;
  bpdbConnected.value = res.data.bpdb_connection?.connected ?? false;
};

const fetchTaskLinks = async () => {
  const res = await axios.get('/api/admin/bpdb/task-links', {
    params: statusFilter.value ? { adapter_status: statusFilter.value } : {},
  });
  taskLinks.value = res.data.data;
};

const fetchJitRules = async () => {
  const res = await axios.get('/api/admin/bpdb/jit-routing-rules');
  jitRules.value = res.data.data;
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

const fetchChemicalDemand = async () => {
  const res = await axios.get('/api/admin/bpdb/chemical-demand');
  demandActive.value = res.data.active;
  demandCompleted.value = res.data.completedRecent;
  demandErrors.value = res.data.errorOrCancelled;
  bpdbConnected.value = res.data.bpdbConnected;
  applyEnvelope(res.data);
};

const fetchChemicalDemandSummary = async () => {
  const res = await axios.get('/api/admin/bpdb/chemical-demand/summary');
  demandSummary.value = res.data;
  bpdbConnected.value = res.data.bpdbConnected;
};

const openTaskDetail = async (taskId: string) => {
  const res = await axios.get(`/api/admin/bpdb/chemical-demand/${taskId}`);
  selectedTask.value = res.data.data;
};

const maRangeParams = () => {
  const params: Record<string, string> = { from: maFrom.value, to: maTo.value };
  if (maMachineFilter.value) params.machine_code = maMachineFilter.value;
  return params;
};

const fetchMaterialActivity = async () => {
  maLoading.value = true;
  try {
    const params = maRangeParams();
    const [summaryRes, byMachineRes, byMaterialRes, timeseriesRes] = await Promise.all([
      axios.get('/api/admin/bpdb/material-activity/summary', { params }),
      axios.get('/api/admin/bpdb/material-activity/by-machine', { params: { from: params.from, to: params.to } }),
      axios.get('/api/admin/bpdb/material-activity/by-material', { params }),
      axios.get('/api/admin/bpdb/material-activity/timeseries', { params: { ...params, granularity: 'day' } }),
    ]);
    maSummary.value = summaryRes.data;
    maByMachineData.value = byMachineRes.data;
    maByMachine.value = byMachineRes.data.machines;
    maByMaterial.value = byMaterialRes.data.materials;
    maTimeseries.value = timeseriesRes.data.points;
    maPage.value = 1;
    await fetchMaterialActivityDetail();
  } finally {
    maLoading.value = false;
  }
};

const fetchMaterialActivityDetail = async () => {
  const res = await axios.get('/api/admin/bpdb/material-activity/detail', {
    params: { ...maRangeParams(), page: maPage.value, per_page: maPerPage },
  });
  maDetail.value = res.data.rows;
  maDetailTotal.value = res.data.total;
};

const changeMaPage = async (page: number) => {
  maPage.value = page;
  await fetchMaterialActivityDetail();
};

const fetchActiveTab = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    if (activeTab.value === 'overview') await fetchOverview();
    else if (activeTab.value === 'taskLinks') await fetchTaskLinks();
    else if (activeTab.value === 'machines') await Promise.all([fetchMachines(), fetchMachineSummary()]);
    else if (activeTab.value === 'chemicalDemand') await Promise.all([fetchChemicalDemand(), fetchChemicalDemandSummary()]);
    else if (activeTab.value === 'materialActivity') await fetchMaterialActivity();
    else if (activeTab.value === 'machineFeeding') await fetchMachineFeeding();
    else if (activeTab.value === 'transport') await fetchMachines();
    else if (activeTab.value === 'jit') await fetchJitRules();
    else if (activeTab.value === 'errors') await fetchOverview();
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || 'Không tải được dữ liệu Admin BPDB.';
  } finally {
    loading.value = false;
  }
};

// Chỉ tải dữ liệu của tab đang mở, KHÔNG tải trước cả 6 tab ngay khi vào trang (2026-07-20
// — sửa sau khi phát hiện `php artisan serve` xử lý tuần tự từng request một, tải trước 9
// API cùng lúc lúc mount làm trang vào chậm hẳn, đặc biệt khi có nhiều tab trình duyệt cùng
// mở trang này). Chuyển tab mới gọi API của tab đó (lazy-load qua switchTab()).
const switchTab = (key: string) => {
  activeTab.value = key;
  fetchActiveTab();
};

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(async () => {
  await fetchActiveTab();
  // Cập nhật gần thời gian thực cho tab Máy VD / Nhu cầu bơm hóa chất — 5s theo phương án
  // MVP (mục 8), backend đã cache 4s nên nhiều trình duyệt cùng mở không dội query trực
  // tiếp vào BPDB.
  pollTimer = setInterval(() => {
    if (activeTab.value === 'machines') {
      fetchMachines();
      fetchMachineSummary();
    } else if (activeTab.value === 'chemicalDemand') {
      fetchChemicalDemand();
      fetchChemicalDemandSummary();
    }
  }, 5000);
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<style scoped>
.bpdb-admin-container { padding: 1rem; }

.tab-bar {
  display: flex;
  gap: 0.25rem;
  border-bottom: 1px solid var(--border-color, #e2e8f0);
}

.tab-btn {
  background: none;
  border: none;
  padding: 0.5rem 1rem;
  font-size: 0.85rem;
  cursor: pointer;
  color: var(--text-muted, #6b7280);
  border-bottom: 2px solid transparent;
}

.tab-btn.active {
  color: #4f46e5;
  border-bottom-color: #4f46e5;
  font-weight: 600;
}

.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 0.75rem;
  margin-top: 1rem;
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

.status-dist {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.status-chip {
  background: var(--bg-main, #f3f4f6);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 6px;
  padding: 0.25rem 0.6rem;
  font-size: 0.8rem;
}

.status-badge {
  background: rgba(99, 102, 241, 0.12);
  color: #4f46e5;
  border-radius: 4px;
  padding: 0.1rem 0.4rem;
  font-size: 0.75rem;
  font-weight: 600;
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

.ma-bar-cell { display: flex; align-items: center; gap: 0.5rem; min-width: 120px; }
.ma-bar { height: 0.55rem; border-radius: 3px; background: #4f46e5; opacity: 0.75; min-width: 3px; }
.ma-bar-cell span { font-variant-numeric: tabular-nums; font-size: 0.78rem; }

.demand-row { cursor: pointer; }
.demand-row:hover { background: var(--bg-main, #f3f4f6); }
.demand-row.demand-processing td:first-child { border-left: 3px solid #2563eb; }
.demand-row.demand-transitioning td:first-child { border-left: 3px solid #9333ea; }
.demand-row.demand-awaiting_processing td:first-child { border-left: 3px solid #ca8a04; }

.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center; z-index: 50;
}
.detail-drawer {
  background: var(--bg-card, #fff); border-radius: 10px; padding: 1.2rem;
  width: 90%; max-width: 720px; max-height: 85vh; overflow-y: auto;
}
</style>
