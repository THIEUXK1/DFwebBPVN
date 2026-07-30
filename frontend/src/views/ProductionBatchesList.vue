<template>
  <div class="batches-view-container">
    <!-- Page Header -->
    <div class="page-header-row">
      <div class="header-titles">
        <h2>📋 Danh sách Lô sản xuất</h2>
        <p class="text-muted">Giám sát trạng thái sản xuất, cấp liệu, điều phối và liên kết kế hoạch từ hệ thống MES.</p>
      </div>
      <div class="header-actions">
        <router-link to="/production-batches" class="btn btn-secondary">
          <SvgIcon name="search" size="18" />
          Sang trạm Quét đơn
        </router-link>
        <!-- MES Mock tool trigger button -->
        <button
          v-if="authStore.isAdmin || authStore.isOperator"
          @click="openCreateDrawer"
          class="btn btn-primary"
        >
          <SvgIcon name="plus" size="18" />
          Tạo lô từ MES (Giả lập)
        </button>
      </div>
    </div>

    <!-- KPI Cards Grid -->
    <div class="kpi-grid mb-4">
      <div class="kpi-card">
        <div class="kpi-header">
          <span class="kpi-title">Tổng lô hôm nay</span>
          <span class="kpi-icon">📊</span>
        </div>
        <div class="kpi-value">{{ kpis.total }}</div>
        <div class="kpi-subtext">Cập nhật thời gian thực</div>
      </div>
      <div class="kpi-card kpi-blue">
        <div class="kpi-header">
          <span class="kpi-title">Đang chuẩn bị</span>
          <span class="kpi-icon">⚙️</span>
        </div>
        <div class="kpi-value">{{ kpis.preparing }}</div>
        <div class="kpi-subtext">Mới tạo &amp; Chờ cân</div>
      </div>
      <div class="kpi-card kpi-yellow">
        <div class="kpi-header">
          <span class="kpi-title">Đang cân đo</span>
          <span class="kpi-icon">⚖️</span>
        </div>
        <div class="kpi-value">{{ kpis.weighing }}</div>
        <div class="kpi-subtext">Đang cân tại trạm cân</div>
      </div>
      <div class="kpi-card kpi-green">
        <div class="kpi-header">
          <span class="kpi-title">Đã cân (Chờ cấp máy)</span>
          <span class="kpi-icon">✅</span>
        </div>
        <div class="kpi-value">{{ kpis.weighed }}</div>
        <div class="kpi-subtext">Sẵn sàng nạp van</div>
      </div>
      <div class="kpi-card kpi-purple">
        <div class="kpi-header">
          <span class="kpi-title">Hoàn thành / Đã cấp</span>
          <span class="kpi-icon">⚡</span>
        </div>
        <div class="kpi-value">{{ kpis.completed }}</div>
        <div class="kpi-subtext">Đã cấp máy &amp; Đóng mẻ</div>
      </div>
    </div>

    <!-- Filter Bar -->
    <section class="section filter-section mb-4">
      <div class="filter-bar">
        <div class="filter-group flex-2">
          <label>Tìm kiếm lô sản xuất:</label>
          <div class="search-input-wrapper">
            <SvgIcon name="search" size="16" class="search-icon" />
            <input
              v-model="searchQuery"
              @input="onFilterChange"
              type="text"
              placeholder="Nhập mã lô, mã màu, mã hàng..."
              class="form-control pad-left-search"
            />
          </div>
        </div>

        <div class="filter-group">
          <label>Trạng thái:</label>
          <select v-model="statusFilter" @change="onFilterChange" class="form-select">
            <option value="">Tất cả trạng thái</option>
            <option value="NEW">Mới tạo (NEW)</option>
            <option value="APPROVED">Đã duyệt, chờ in tem (APPROVED)</option>
            <option value="READY_TO_WEIGH">Chờ cân (READY_TO_WEIGH)</option>
            <option value="WEIGHING">Đang cân (WEIGHING)</option>
            <option value="WEIGHED">Đã cân xong (WEIGHED)</option>
            <option value="SENT">Đã gửi máy (SENT)</option>
            <option value="DONE">Hoàn thành (DONE)</option>
            <option value="CANCELLED">Đã hủy (CANCELLED)</option>
          </select>
        </div>

        <div class="filter-group">
          <label>Máy chỉ định:</label>
          <select v-model="machineFilter" @change="onFilterChange" class="form-select">
            <option value="">Tất cả máy nhuộm</option>
            <option v-for="m in machines" :key="m.id" :value="m.id">
              {{ m.code }} ({{ m.name }})
            </option>
          </select>
        </div>

        <div class="filter-group actions-group">
          <label>&nbsp;</label>
          <button @click="clearFilters" class="btn btn-secondary w-full-mobile">
            Xóa bộ lọc
          </button>
        </div>
      </div>
    </section>

    <!-- Main Data Table Card -->
    <section class="section card-sec">
      <div class="table-container-fixed">
        <table class="data-table">
          <thead>
            <tr>
              <th>Mã Lô (Batch ID)</th>
              <th>Mã Màu (Color)</th>
              <th>Mã Hàng (Product)</th>
              <th>Máy Nhuộm</th>
              <th>Thùng Trộn</th>
              <th>Mức nước</th>
              <th>Tiến Trình</th>
              <th>Trạng Thái</th>
              <th>Ngày Cập Nhật</th>
              <th>Thao Tác</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton Loading state -->
            <tr v-if="loading" v-for="i in 5" :key="'skel-' + i">
              <td v-for="j in 10" :key="'cell-' + j">
                <div class="skeleton" style="height: 20px; width: 80%;"></div>
              </td>
            </tr>

            <!-- Actual Table Rows -->
            <tr
              v-else
              v-for="batch in batches"
              :key="batch.id"
              @click="openDetailDrawer(batch)"
              class="clickable-row"
            >
              <td class="bold-text highlight-code">{{ batch.legacy_batch_id }}</td>
              <td>{{ batch.color }}</td>
              <td>{{ batch.product_code }}</td>
              <td>
                <span class="machine-tag">{{ batch.machine?.code || 'N/A' }}</span>
              </td>
              <td @click.stop>
                <select
                  v-if="editingTankBatchId === batch.id"
                  :value="batch.tank_id || ''"
                  class="form-select table-row-select"
                  @change="updateTank(batch, ($event.target as HTMLSelectElement).value)"
                  @blur="editingTankBatchId = null"
                >
                  <option value="">-- Không chọn --</option>
                  <option v-for="t in tanks.filter(t => t.machine_id === batch.machine_id)" :key="t.id" :value="t.id">
                    {{ t.code }}
                  </option>
                </select>
                <button
                  v-else-if="batch.status === 'NEW'"
                  class="tank-pick-btn"
                  @click="openTankEdit(batch)"
                  title="Bấm để chọn nhanh Thùng trộn"
                >
                  <span class="tank-tag" v-if="batch.tank">{{ batch.tank?.code }}</span>
                  <span class="text-muted" v-else>+ Chọn thùng</span>
                </button>
                <span v-else class="tank-tag" title="Đơn đã duyệt — không thể đổi Thùng trộn nữa">
                  {{ batch.tank?.code || 'N/A' }}
                </span>
              </td>
              <td>{{ batch.level_code || 'N/A' }}</td>
              <td>
                <div class="progress-bar-wrapper" :title="'Độ hoàn tất: ' + getProgressPercent(batch.status) + '%'">
                  <div class="progress-bar-fill" :style="{ width: getProgressPercent(batch.status) + '%' }"></div>
                </div>
              </td>
              <td>
                <span :class="['badge', getStatusBadgeClass(batch.status)]">
                  {{ batch.status }}
                </span>
              </td>
              <td class="date-cell">{{ formatDate(batch.updated_at || batch.created_at) }}</td>
              <td @click.stop class="actions-cell">
                <button
                  @click="openDetailDrawer(batch)"
                  class="btn btn-secondary btn-sm"
                  title="Xem chi tiết Thuốc nhuộm (DYE) và Hóa chất (CHEM) của lô này"
                >
                  👁️ DYE/CHEM
                </button>
                <button
                  v-if="batch.status === 'NEW'"
                  @click="approveBatch(batch)"
                  class="btn btn-primary btn-sm"
                  :disabled="approvingId === batch.id || !batch.tank"
                  :title="!batch.tank ? 'Phải chọn Thùng trộn trước khi duyệt' : ''"
                >
                  {{ approvingId === batch.id ? 'Đang duyệt...' : (!batch.tank ? '⚠️ Chưa có Thùng' : '✅ Duyệt đơn') }}
                </button>
                <select
                  :value="batch.status"
                  @change="updateBatchStatus(batch.id, ($event.target as HTMLSelectElement).value)"
                  class="form-select table-row-select"
                >
                  <option value="NEW">NEW</option>
                  <option value="APPROVED">APPROVED</option>
                  <option value="READY_TO_WEIGH">READY_TO_WEIGH</option>
                  <option value="WEIGHING">WEIGHING</option>
                  <option value="WEIGHED">WEIGHED</option>
                  <option value="SENT">SENT</option>
                  <option value="DONE">DONE</option>
                  <option value="CANCELLED">CANCELLED</option>
                </select>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!loading && batches.length === 0">
              <td colspan="10" class="text-center text-muted pad-empty-row">
                <div class="empty-state-icon">🔍</div>
                <p>Không tìm thấy lô sản xuất nào khớp với điều kiện lọc.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="pagination-footer" v-if="totalPages > 1 && !loading">
        <button
          @click="changePage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="btn btn-secondary btn-sm"
        >
          ◀ Trước
        </button>
        <span class="page-info">Trang {{ currentPage }} / {{ totalPages }}</span>
        <button
          @click="changePage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="btn btn-secondary btn-sm"
        >
          Sau ▶
        </button>
      </div>
    </section>

    <!-- Create Mock Batch Drawer (Sliding Panel from Right) -->
    <div class="drawer-overlay" v-if="createDrawerOpen" @click.self="closeCreateDrawer">
      <div class="right-drawer">
        <div class="drawer-header">
          <div class="drawer-header-title">
            <h3>🚀 Giả lập phát hành Lô sản xuất</h3>
            <span class="sim-badge">Công cụ mô phỏng MES</span>
          </div>
          <button @click="closeCreateDrawer" class="drawer-close-btn">&times;</button>
        </div>

        <div class="drawer-body">
          <p class="drawer-desc mb-4">Nhập thông tin giả lập đẩy đơn lệnh nhuộm từ MES xuống trạm điều phối nhà máy.</p>

          <form @submit.prevent="createMockBatch" class="drawer-form">
            <div class="form-group">
              <label for="mock-batch-id">Mã Lô sản xuất (Batch ID) <span class="required">*</span></label>
              <input
                id="mock-batch-id"
                v-model="mockForm.legacy_batch_id"
                type="text"
                required
                placeholder="Ví dụ: L2-260715-08"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="mock-color">Mã công thức màu (Color Code) <span class="required">*</span></label>
              <input
                id="mock-color"
                v-model="mockForm.color"
                type="text"
                required
                placeholder="Ví dụ: A+110293"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="mock-product">Mã sản phẩm (Product Code) <span class="required">*</span></label>
              <input
                id="mock-product"
                v-model="mockForm.product_code"
                type="text"
                required
                placeholder="Ví dụ: T7400"
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label for="mock-machine">Máy nhuộm chỉ định <span class="required">*</span></label>
              <select id="mock-machine" v-model="mockForm.machine_id" required class="form-select">
                <option value="">-- Chọn máy nhuộm --</option>
                <option v-for="m in machines" :key="m.id" :value="m.id">
                  {{ m.code }} ({{ m.name }})
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="mock-tank">Thùng trộn nhuộm (Tùy chọn)</label>
              <select id="mock-tank" v-model="mockForm.tank_id" class="form-select">
                <option value="">-- Mặc định --</option>
                <option v-for="t in tanks" :key="t.id" :value="t.id">
                  Thùng {{ t.code }}
                </option>
              </select>
            </div>

            <div class="form-group">
              <label for="mock-level">Mức nước cố định (Water Level)</label>
              <select id="mock-level" v-model="mockForm.level_code" class="form-select">
                <option value="">-- Chọn mức nước --</option>
                <option v-for="lv in waterLevels" :key="lv" :value="lv">{{ lv }}</option>
              </select>
            </div>

            <!-- Error/Success feedbacks -->
            <p v-if="successMsg" class="text-success mt-2">✅ {{ successMsg }}</p>
            <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>

            <div class="drawer-actions mt-4">
              <button type="button" @click="closeCreateDrawer" class="btn btn-secondary flex-1">Hủy</button>
              <button type="submit" class="btn btn-primary flex-2" :disabled="creatingBatch">
                {{ creatingBatch ? 'Đang tạo...' : 'Phát hành Lệnh' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Batch Detail Modal (hiển thị giữa màn hình, rộng hơn để bảng DYE/CHEM dễ đọc) -->
    <div class="drawer-overlay center-modal-overlay" v-if="detailDrawerOpen" @click.self="closeDetailDrawer">
      <div class="right-drawer detail-modal">
        <div class="drawer-header">
          <h3>🔍 Chi tiết Lô sản xuất</h3>
          <button @click="closeDetailDrawer" class="drawer-close-btn">&times;</button>
        </div>

        <div class="drawer-body" v-if="selectedBatch">
          <div class="detail-hero mb-4">
            <span :class="['badge', getStatusBadgeClass(selectedBatch.status)]" class="hero-badge">
              {{ selectedBatch.status }}
            </span>
            <h4>Lô: {{ selectedBatch.legacy_batch_id }}</h4>
          </div>

          <div class="detail-info-list">
            <div class="detail-item">
              <span class="detail-label">Mã màu công thức (Color)</span>
              <span class="detail-value">{{ selectedBatch.color }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Mã hàng sản phẩm (Product)</span>
              <span class="detail-value">{{ selectedBatch.product_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Máy nhuộm chỉ định</span>
              <span class="detail-value">{{ selectedBatch.machine ? selectedBatch.machine.code + ' - ' + selectedBatch.machine.name : 'N/A' }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Thùng trộn nhuộm</span>
              <select
                v-if="selectedBatch.status === 'NEW'"
                :value="selectedBatch.tank_id || ''"
                class="form-select detail-tank-select"
                @change="updateTank(selectedBatch, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">-- Chưa chọn --</option>
                <option v-for="t in tanks.filter(t => t.machine_id === selectedBatch.machine_id)" :key="t.id" :value="t.id">
                  Thùng {{ t.code }}
                </option>
              </select>
              <span v-else class="detail-value" title="Đơn đã duyệt — không thể đổi Thùng trộn nữa">
                {{ selectedBatch.tank ? 'Thùng ' + selectedBatch.tank.code : 'Chưa chọn' }}
              </span>
            </div>
            <div class="detail-item" v-if="selectedBatch.level_code">
              <span class="detail-label">Mức nước chỉ định</span>
              <span class="detail-value">{{ selectedBatch.level_code }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Ngày khởi tạo</span>
              <span class="detail-value">{{ formatDate(selectedBatch.created_at) }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Ngày cập nhật gần nhất</span>
              <span class="detail-value">{{ formatDate(selectedBatch.updated_at) }}</span>
            </div>
          </div>

          <!-- Bảng tách dòng RACK/MÃ/KHỐI LƯỢNG — giống layout scaleform.frm (VBA gốc) -->
          <div class="rack-tables-row mt-4" v-if="selectedDyeLines.length || selectedChemLines.length">
            <div class="rack-table-col">
              <label class="rack-table-title">🧵 Thuốc nhuộm (DYE)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ THUỐC NHUỘM</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedDyeLines" :key="'sel-dye-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="rack-table-col">
              <label class="rack-table-title">🧪 Hóa chất (CHEM)</label>
              <table class="data-table rack-table">
                <thead><tr><th>RACK</th><th>MÃ HÓA CHẤT</th><th>KHỐI LƯỢNG</th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in selectedChemLines" :key="'sel-chem-' + idx" class="rack-row-filled">
                    <td class="rack-cell-num">{{ line.rack }}</td>
                    <td class="rack-cell-code">{{ line.code }}</td>
                    <td class="rack-cell-weight">{{ line.weight }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-else class="text-muted font-sm mt-4">Lô này không có dữ liệu quét DYE/CHEM (được tạo bằng tay hoặc từ MES giả lập).</p>

          <p v-if="selectedBatch.status === 'NEW' && !selectedBatch.tank" class="text-error mt-2">
            ⚠️ Phải chọn Thùng trộn trước khi duyệt đơn này.
          </p>
          <p v-if="approveErrorMsg" class="text-error mt-2">❌ {{ approveErrorMsg }}</p>

          <div class="drawer-actions mt-4">
            <button
              v-if="selectedBatch.status === 'NEW'"
              @click="approveBatch(selectedBatch)"
              class="btn btn-primary flex-2"
              :disabled="approvingId === selectedBatch.id || !selectedBatch.tank"
              :title="!selectedBatch.tank ? 'Phải chọn Thùng trộn trước khi duyệt' : ''"
            >
              {{ approvingId === selectedBatch.id ? 'Đang duyệt...' : '✅ Duyệt đơn → Tạo hàng chờ in tem' }}
            </button>
            <button @click="closeDetailDrawer" class="btn btn-secondary flex-1">Đóng cửa sổ</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, reactive } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';
import SvgIcon from '../components/SvgIcon.vue';
import { currentWorkstation } from '../services/workstation';
import { parseRackLines } from '../utils/rackParser';
import echo from '../services/echo';

const authStore = useAuthStore();

// Table state
const batches = ref<any[]>([]);
const loading = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);

// Filters state
const searchQuery = ref('');
const statusFilter = ref('');
const machineFilter = ref('');

// Metadata lists
const machines = ref<any[]>([]);
const tanks = ref<any[]>([]);

// Chọn nhanh Thùng trộn ngay tại bảng (yêu cầu 2026-07-22: bấm vào ô Thùng Trộn hiện
// dropdown chọn luôn, không cần mở form riêng — cần vì duyệt đơn giờ bắt buộc có thùng).
const editingTankBatchId = ref<string | null>(null);

const openTankEdit = (batch: any) => {
  editingTankBatchId.value = batch.id;
};

const updateTank = async (batch: any, tankIdRaw: string) => {
  const tankId = tankIdRaw === '' ? null : Number(tankIdRaw);
  editingTankBatchId.value = null;
  try {
    const res = await axios.put(`/api/production-batches/${batch.id}/tank`, { tank_id: tankId });
    batch.tank_id = res.data.data.tank_id;
    batch.tank = res.data.data.tank;
    if (selectedBatch.value && selectedBatch.value.id === batch.id) {
      selectedBatch.value.tank_id = res.data.data.tank_id;
      selectedBatch.value.tank = res.data.data.tank;
    }
  } catch (error: any) {
    alert(error.response?.data?.message || 'Không thể cập nhật Thùng trộn.');
  }
};

// Drawers state
const createDrawerOpen = ref(false);
const detailDrawerOpen = ref(false);
const selectedBatch = ref<any | null>(null);
const selectedDyeLines = computed(() => parseRackLines(selectedBatch.value?.raw_qr_dye));
const selectedChemLines = computed(() => parseRackLines(selectedBatch.value?.raw_qr_chemical));

// Mức nước cố định (Water Level) - 4 giá trị chuẩn hệ thống
const waterLevels = [50, 100, 250, 450];

// Mock Form state
const mockForm = reactive({
  legacy_batch_id: '',
  color: '',
  product_code: '',
  machine_id: '',
  tank_id: '',
  level_code: ''
});
const creatingBatch = ref(false);
const successMsg = ref('');
const errorMsg = ref('');
const approvingId = ref<string | null>(null);
const approveErrorMsg = ref('');

// KPI stats
const kpis = computed(() => {
  const list = batches.value;
  return {
    total: list.length,
    preparing: list.filter(b => b.status === 'NEW' || b.status === 'APPROVED' || b.status === 'READY_TO_WEIGH').length,
    weighing: list.filter(b => b.status === 'WEIGHING').length,
    weighed: list.filter(b => b.status === 'WEIGHED').length,
    completed: list.filter(b => b.status === 'SENT' || b.status === 'DONE').length
  };
});

let batchPollInterval: any = null;

onMounted(() => {
  fetchBatches();
  fetchMetadata();

  // Realtime qua Reverb — lô mới tạo/duyệt/đổi trạng thái ở /production-batches hay trạm
  // cân đều làm bảng này tự cập nhật ngay, giữ nguyên filter/trang đang xem (fetchBatches
  // tự đọc lại searchQuery/statusFilter/machineFilter/currentPage hiện tại). Xem
  // ProductionBatchUpdated::broadcastOn (backend) — cùng kênh với /production-batches.
  echo.channel('production-batches').listen('.updated', () => fetchBatches(true));

  // Polling làm lưới an toàn nếu WebSocket rớt kết nối tạm thời.
  batchPollInterval = setInterval(() => fetchBatches(true), 15000);
});

onUnmounted(() => {
  if (batchPollInterval) clearInterval(batchPollInterval);
  echo.leaveChannel('production-batches');
});

// silent = true cho các lần refresh nền (WebSocket "production-batches" updated, polling
// 15s) — không set loading nên bảng không bị thay bằng skeleton/nháy khi người dùng không
// hề thao tác gì. Chỉ hiện skeleton khi tải lần đầu hoặc người dùng chủ động đổi filter/trang.
const fetchBatches = async (silent = false) => {
  if (!silent) loading.value = true;
  try {
    const response = await axios.get('/api/production-batches', {
      params: {
        page: currentPage.value,
        status: statusFilter.value || undefined,
        search: searchQuery.value || undefined,
        machine_id: machineFilter.value || undefined
      }
    });
    batches.value = response.data.data;
    totalPages.value = response.data.last_page;
  } catch (error) {
    console.error('Error fetching batches:', error);
  } finally {
    if (!silent) loading.value = false;
  }
};

const fetchMetadata = async () => {
  try {
    const [machinesRes, tanksRes] = await Promise.all([
      axios.get('/api/machines'),
      axios.get('/api/tanks')
    ]);
    machines.value = machinesRes.data.data || [];
    tanks.value = tanksRes.data.data || [];
  } catch (error) {
    console.error('Error fetching metadata:', error);
  }
};

const onFilterChange = () => {
  currentPage.value = 1;
  fetchBatches();
};

const clearFilters = () => {
  searchQuery.value = '';
  statusFilter.value = '';
  machineFilter.value = '';
  onFilterChange();
};

const changePage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
    fetchBatches();
  }
};

const updateBatchStatus = async (id: string, newStatus: string) => {
  try {
    await axios.put(`/api/production-batches/${id}/status`, {
      status: newStatus
    });
    fetchBatches();
  } catch (error) {
    console.error('Error updating batch status:', error);
    alert('Không thể cập nhật trạng thái lô.');
  }
};

// Duyệt đơn -> tạo hàng chờ điều phối cho QR_LABEL_PRINTING (ApproveProductionOrderService).
// Khác updateBatchStatus (đổi status tự do, không quy tắc gì): endpoint này có transaction,
// idempotent. Quy tắc "MINIMUM LEVEL 250L" đã bị bỏ theo yêu cầu người dùng 2026-07-28.
const approveBatch = async (batch: any) => {
  approveErrorMsg.value = '';
  approvingId.value = batch.id;
  try {
    await axios.post(`/api/production-batches/${batch.id}/approve`, {
      workstation_id: currentWorkstation.value?.code
    });
    batch.status = 'APPROVED';
    if (selectedBatch.value && selectedBatch.value.id === batch.id) {
      selectedBatch.value.status = 'APPROVED';
    }
    fetchBatches();
  } catch (error: any) {
    const msg = error.response?.data?.message || 'Không thể duyệt đơn.';
    approveErrorMsg.value = msg;
    alert(msg);
  } finally {
    approvingId.value = null;
  }
};

const createMockBatch = async () => {
  successMsg.value = '';
  errorMsg.value = '';

  if (!mockForm.legacy_batch_id || !mockForm.color || !mockForm.product_code || !mockForm.machine_id) {
    errorMsg.value = 'Vui lòng điền đầy đủ các thông tin bắt buộc!';
    return;
  }

  creatingBatch.value = true;
  try {
    await axios.post('/api/production-batches', {
      legacy_batch_id: mockForm.legacy_batch_id,
      color: mockForm.color,
      product_code: mockForm.product_code,
      machine_id: mockForm.machine_id,
      tank_id: mockForm.tank_id || null,
      level_code: mockForm.level_code || null,
      status: 'NEW'
    });

    successMsg.value = 'Đẩy đơn sản xuất từ MES thành công! Trạng thái: NEW — vào chi tiết lô để bấm "Duyệt đơn".';
    // Reset form fields
    mockForm.legacy_batch_id = '';
    mockForm.color = '';
    mockForm.product_code = '';
    mockForm.machine_id = '';
    mockForm.tank_id = '';
    mockForm.level_code = '';

    fetchBatches();
    setTimeout(() => {
      closeCreateDrawer();
    }, 1500);
  } catch (error: any) {
    errorMsg.value = error.response?.data?.message || 'Có lỗi xảy ra khi kết nối MES.';
  } finally {
    creatingBatch.value = false;
  }
};

// Khóa scroll của trang nền khi có drawer/modal đang mở — tránh cuộn chuột trên
// overlay kéo lẫn sang view phía sau; thanh kéo lúc này chỉ còn ở trong .drawer-body.
const lockBodyScroll = () => { document.body.style.overflow = 'hidden'; };
const unlockBodyScroll = () => {
  if (!createDrawerOpen.value && !detailDrawerOpen.value) document.body.style.overflow = '';
};

// Drawer controls
const openCreateDrawer = () => {
  successMsg.value = '';
  errorMsg.value = '';
  createDrawerOpen.value = true;
  lockBodyScroll();
};
const closeCreateDrawer = () => {
  createDrawerOpen.value = false;
  unlockBodyScroll();
};

const openDetailDrawer = (batch: any) => {
  selectedBatch.value = batch;
  detailDrawerOpen.value = true;
  lockBodyScroll();
};
const closeDetailDrawer = () => {
  detailDrawerOpen.value = false;
  selectedBatch.value = null;
  unlockBodyScroll();
};

// Progress percent calculation
const getProgressPercent = (status: string) => {
  const mapping: Record<string, number> = {
    'NEW': 10,
    'APPROVED': 25,
    'READY_TO_WEIGH': 40,
    'WEIGHING': 60,
    'WEIGHED': 80,
    'SENT': 92,
    'DONE': 100
  };
  return mapping[status] || 0;
};

const getStatusBadgeClass = (status: string) => {
  const mapping: Record<string, string> = {
    'NEW': 'badge-grey',
    'APPROVED': 'badge-blue',
    'READY_TO_WEIGH': 'badge-blue',
    'WEIGHING': 'badge-yellow',
    'WEIGHED': 'badge-green',
    'SENT': 'badge-orange',
    'DONE': 'badge-purple',
    'CANCELLED': 'badge-grey'
  };
  return mapping[status] || 'badge-grey';
};

const formatDate = (dateStr: string) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('vi-VN', { hour12: false });
};
</script>

<style scoped>
.batches-view-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.page-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-lg);
}

.header-titles h2 {
  font-size: 1.6rem;
  color: var(--text-title);
  margin-bottom: 4px;
}

.header-actions {
  display: flex;
  gap: var(--space-md);
}

/* Bảng RACK/MÃ/KHỐI LƯỢNG tách dòng — layout kiểu scaleform.frm (VBA gốc), dùng trong modal Chi tiết */
.rack-tables-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-lg);
  margin-top: var(--space-lg);
}

.rack-table-title {
  display: block;
  font-size: 0.8rem;
  color: var(--text-muted);
  margin-bottom: 6px;
}

.rack-table th {
  font-size: 11px;
}

.rack-row-filled td {
  background-color: var(--status-green-bg);
}

.rack-cell-num {
  width: 60px;
  font-weight: 700;
  color: var(--text-muted);
}

.rack-cell-code {
  font-weight: 700;
  font-family: monospace;
}

.rack-cell-weight {
  font-weight: 700;
  font-family: monospace;
  color: var(--status-blue);
}

@media (max-width: 768px) {
  .rack-tables-row {
    grid-template-columns: 1fr;
  }
}

/* KPI cards styling */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-lg);
}

.kpi-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-lg) var(--space-xl);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: relative;
  overflow: hidden;
}

.kpi-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background-color: var(--status-grey);
}

.kpi-blue::before { background-color: var(--status-blue); }
.kpi-yellow::before { background-color: var(--status-yellow); }
.kpi-green::before { background-color: var(--status-green); }
.kpi-purple::before { background-color: var(--status-purple); }

.kpi-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kpi-title {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.kpi-icon {
  font-size: 1.2rem;
}

.kpi-value {
  font-family: 'Outfit', sans-serif;
  font-size: 1.8rem;
  font-weight: 700;
  color: var(--text-title);
  line-height: 1.2;
}

.kpi-subtext {
  font-size: 11px;
  color: var(--text-disabled);
}

/* Filter bar styles */
.filter-bar {
  display: flex;
  align-items: flex-end;
  gap: var(--space-lg);
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 150px;
}

.filter-group label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: var(--space-lg);
  color: var(--text-disabled);
}

.pad-left-search {
  padding-left: calc(var(--space-lg) * 2 + 10px);
}

/* Data Table and custom columns styling */
.table-container-fixed {
  width: 100%;
  overflow-x: auto;
  border-radius: var(--radius-lg);
}

.clickable-row {
  cursor: pointer;
}

.bold-text {
  font-weight: 700;
}

.highlight-code {
  color: var(--primary-hover);
  font-family: monospace;
}

.machine-tag, .tank-tag {
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card);
  padding: 3px var(--space-sm);
  border-radius: var(--radius-sm);
  font-size: 12px;
  font-weight: 600;
}

.tank-pick-btn {
  background: none;
  border: 1px dashed var(--border-card);
  border-radius: var(--radius-sm);
  padding: 3px var(--space-sm);
  cursor: pointer;
  font-size: 12px;
  color: var(--text-muted);
}

.tank-pick-btn:hover {
  border-color: var(--primary);
  color: var(--primary-hover);
  background-color: var(--bg-card-hover);
}

.detail-tank-select {
  width: auto;
  min-width: 140px;
}

.progress-bar-wrapper {
  width: 100px;
  height: 6px;
  background-color: var(--border-card);
  border-radius: var(--radius-full);
  overflow: hidden;
}

.progress-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
  border-radius: var(--radius-full);
  transition: width 0.3s ease;
}

.table-row-select {
  height: 32px;
  padding: 0 var(--space-sm);
  font-size: 12px;
  width: auto;
}

.pad-empty-row {
  padding: var(--space-4xl) 0 !important;
}

.empty-state-icon {
  font-size: 3rem;
  margin-bottom: var(--space-sm);
  opacity: 0.5;
}

.pagination-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: var(--space-lg);
  margin-top: var(--space-2xl);
  padding-top: var(--space-lg);
  border-top: 1px solid var(--border-divider);
}

.page-info {
  font-size: 13px;
  color: var(--text-muted);
}

/* Right Drawer Panel (MES Mock tool) */
.drawer-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: flex;
  justify-content: flex-end;
}

.right-drawer {
  width: 100%;
  max-width: 460px;
  height: 100%;
  background-color: var(--bg-card);
  border-left: 1px solid var(--border-card);
  display: flex;
  flex-direction: column;
  box-shadow: var(--shadow-lg);
  animation: slideLeft 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideLeft {
  from { transform: translateX(100%); }
  to { transform: translateX(0); }
}

/* Modal căn giữa màn hình cho Chi tiết Lô sản xuất — rộng hơn drawer trượt cạnh để
   2 bảng DYE/CHEM có đủ chỗ hiển thị song song, dễ đọc hơn thay vì bị bóp hẹp 460px. */
.center-modal-overlay {
  align-items: center;
  justify-content: center;
  padding: var(--space-2xl);
}

.detail-modal {
  max-width: 900px;
  width: 100%;
  height: auto;
  max-height: 90vh;
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  animation: popCenter 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes popCenter {
  from { transform: scale(0.96); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

@media (max-width: 768px) {
  .center-modal-overlay {
    padding: 0;
  }
  .detail-modal {
    max-width: 100%;
    height: 100%;
    max-height: 100%;
    border-radius: 0;
  }
}

.drawer-header {
  height: 70px;
  padding: 0 var(--space-2xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
  background-color: var(--bg-sidebar);
}

.drawer-header-title {
  display: flex;
  flex-direction: column;
}

.sim-badge {
  font-size: 9px;
  background-color: var(--status-red-bg);
  border: 1px solid var(--status-red-border);
  color: var(--status-red);
  font-weight: 700;
  padding: 1px 4px;
  border-radius: var(--radius-sm);
  width: fit-content;
  text-transform: uppercase;
}

.drawer-close-btn {
  font-size: 2rem;
  color: var(--text-muted);
  cursor: pointer;
  line-height: 1;
}

.drawer-close-btn:hover {
  color: var(--text-title);
}

.drawer-body {
  flex: 1;
  padding: var(--space-2xl);
  overflow-y: auto;
  overscroll-behavior: contain;
}

.drawer-desc {
  font-size: 0.85rem;
  color: var(--text-muted);
}

.drawer-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 600;
  color: var(--text-title);
  font-size: 0.9rem;
}

.required {
  color: var(--status-red);
}

.drawer-actions {
  display: flex;
  gap: var(--space-lg);
}

/* Detail Hero Area */
.detail-hero {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.hero-badge {
  font-size: 12px;
}

.detail-info-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  background-color: var(--bg-main);
  padding: var(--space-xl);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: var(--space-sm);
}

.detail-item:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.detail-label {
  font-size: 12px;
  color: var(--text-muted);
}

.detail-value {
  font-weight: 600;
  color: var(--text-title);
}

/* Layout adjustments on narrow displays */
@media (max-width: 768px) {
  .page-header-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .filter-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .w-full-mobile {
    width: 100% !important;
  }
}
</style>
