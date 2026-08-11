<template>
  <div class="chemical-call-container">
    <!-- Remote Access Mode Banner -->
    <div v-if="isImpersonating" class="remote-banner mb-4" :class="remoteModeClass">
      <div class="banner-content">
        <span class="banner-icon">🌐</span>
        <div class="banner-text">
          <strong>{{ $t('chemicalCall.remoteBannerLabel') }}</strong>
          <span v-if="remoteMode === 'VIEW_ONLY'">{{ $t('chemicalCall.remoteViewOnlyDesc') }}</span>
          <span v-else>{{ $t('chemicalCall.remoteOperateDesc') }}</span>
        </div>
      </div>
      <div class="banner-actions">
        <select v-model="remoteMode" class="form-select font-xs select-mode">
          <option value="VIEW_ONLY">{{ $t('chemicalCall.remoteModeViewOnlyOption') }}</option>
          <option value="REMOTE_OPERATE">{{ $t('chemicalCall.remoteModeOperateOption') }}</option>
        </select>
      </div>
    </div>

    <!-- Error/Success Alerts -->
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>
    <div v-if="successMsg" class="alert-box alert-success">✅ {{ successMsg }}</div>

    <!-- Quản lý danh mục: thêm máy mới / thêm kênh mới -->
    <div class="admin-actions-row">
      <button @click="openAddMachine" class="btn btn-secondary btn-sm">{{ $t('chemicalCall.addMachineButton') }}</button>
      <button @click="openAddChannel" class="btn btn-secondary btn-sm">{{ $t('chemicalCall.addChannelButton') }}</button>
    </div>

    <!-- Factory Operating Grid (Equivalent to VBA CHEM_ORDER) -->
    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> {{ $t('chemicalCall.loadingChannels') }}
    </div>

    <div v-else class="machine-grid" :class="{ 'grid-4col': isFullscreen }">
      <div
        v-for="machineCode in sortedMachineCodes"
        :key="machineCode"
        class="card machine-card"
      >
        <div class="machine-card-header">
          <span class="machine-name-title">{{ $t('chemicalCall.machineTitle', { code: machineCode }) }}</span>
          <span class="machine-status-dot dot-green"></span>
        </div>

        <div class="machine-card-body">
          <div
            v-for="c in groupedChannels[machineCode]"
            :key="c.channel_id"
            class="channel-row"
            :class="[
              getChannelRowClass(c),
              actionLoading === c.channel_id ? 'row-processing' : '',
              actionLoading !== null && actionLoading !== c.channel_id ? 'row-locked' : ''
            ]"
          >
            <div class="channel-number-col">
              <span v-if="isChannelRed(c)" class="alert-dot" aria-hidden="true"></span>
              <span class="channel-number-pill">{{ $t('chemicalCall.channelPill', { number: c.channel_number }) }}</span>
            </div>

            <div class="chemical-name-col">
              <span class="chem-formula" :title="$t('chemicalCall.chemFormulaTitle')">{{ c.chemical_code }}</span>
            </div>

            <div class="time-col font-xs text-muted text-right">
              {{ c.current_request ? formatTime(c.current_request.requested_at) : '-' }}
            </div>

            <div class="action-btn-col">
              <!-- Nút toggle duy nhất: xanh (OK) <-> đỏ (chưa OK). Bấm khi đang xanh = Gọi
                   hóa chất (chuyển đỏ); bấm khi đang đỏ = báo Xong (chuyển lại xanh). -->
              <button
                @click="toggleChannel(c)"
                class="btn btn-sm w-full py-1 font-semibold toggle-btn"
                :class="isChannelRed(c) ? 'btn-danger' : 'btn-success'"
                :disabled="actionLoading !== null || (isImpersonating && remoteMode === 'VIEW_ONLY')"
              >
                <span v-if="actionLoading === c.channel_id">{{ $t('chemicalCall.processingLabel') }}</span>
                <span v-else>{{ isChannelRed(c) ? $t('chemicalCall.toggleDoneLabel') : $t('chemicalCall.toggleCallLabel') }}</span>
              </button>
              <button
                @click="openEditChannel(c)"
                class="btn btn-sm btn-secondary edit-channel-btn"
                :title="$t('chemicalCall.editChannelTitle')"
                :disabled="actionLoading !== null || (isImpersonating && remoteMode === 'VIEW_ONLY')"
              >
                ✏️
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Thêm máy mới -->
    <div v-if="showAddMachine" class="modal-overlay" @click.self="showAddMachine = false">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('chemicalCall.addMachineModalTitle') }}</h3>
          <button @click="showAddMachine = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.machineCodeLabel') }}</label>
            <input v-model="newMachine.code" type="text" class="form-control" :placeholder="$t('chemicalCall.machineCodePlaceholder')" />
          </div>
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.machineNameLabel') }}</label>
            <input v-model="newMachine.name" type="text" class="form-control" :placeholder="$t('chemicalCall.machineNamePlaceholder')" />
          </div>
          <p v-if="addMachineError" class="text-error font-sm">❌ {{ addMachineError }}</p>
          <div class="modal-actions">
            <button @click="showAddMachine = false" class="btn btn-secondary">{{ $t('common.cancel') }}</button>
            <button @click="submitAddMachine" class="btn btn-primary" :disabled="!newMachine.code || !newMachine.name || addingMachine">
              {{ addingMachine ? $t('chemicalCall.savingLabel') : $t('chemicalCall.saveMachineButton') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Thêm kênh mới -->
    <div v-if="showAddChannel" class="modal-overlay" @click.self="showAddChannel = false">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('chemicalCall.addChannelModalTitle') }}</h3>
          <button @click="showAddChannel = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.machineLabel') }}</label>
            <select v-model="newChannel.machine_id" class="form-select">
              <option :value="null">{{ $t('chemicalCall.selectMachinePlaceholder') }}</option>
              <option v-for="m in machinesList" :key="m.id" :value="m.id">{{ m.code }} ({{ m.name }})</option>
            </select>
          </div>
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.channelNumberLabel') }}</label>
            <input v-model.number="newChannel.channel_number" type="number" min="1" class="form-control" :placeholder="$t('chemicalCall.channelNumberPlaceholder')" />
          </div>
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.chemicalCodeLabel') }}</label>
            <input v-model="newChannel.chemical_code" type="text" class="form-control" :placeholder="$t('chemicalCall.chemicalCodePlaceholder')" />
          </div>
          <p v-if="addChannelError" class="text-error font-sm">❌ {{ addChannelError }}</p>
          <div class="modal-actions">
            <button @click="showAddChannel = false" class="btn btn-secondary">{{ $t('common.cancel') }}</button>
            <button @click="submitAddChannel" class="btn btn-primary" :disabled="!newChannel.machine_id || !newChannel.channel_number || !newChannel.chemical_code || addingChannel">
              {{ addingChannel ? $t('chemicalCall.savingLabel') : $t('chemicalCall.saveChannelButton') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Sửa kênh -->
    <div v-if="showEditChannel" class="modal-overlay" @click.self="showEditChannel = false">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('chemicalCall.editChannelModalTitle', { machineCode: editChannel.machine_code, number: editChannel.original_channel_number }) }}</h3>
          <button @click="showEditChannel = false" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.channelNumberLabel') }}</label>
            <input v-model.number="editChannel.channel_number" type="number" min="1" class="form-control" />
          </div>
          <div class="form-group mb-3">
            <label>{{ $t('chemicalCall.chemicalCodeLabel') }}</label>
            <input v-model="editChannel.chemical_code" type="text" class="form-control" :placeholder="$t('chemicalCall.chemicalCodePlaceholder')" />
          </div>
          <p v-if="editChannelError" class="text-error font-sm">❌ {{ editChannelError }}</p>
          <div class="modal-actions">
            <button @click="showEditChannel = false" class="btn btn-secondary">{{ $t('common.cancel') }}</button>
            <button @click="submitEditChannel" class="btn btn-primary" :disabled="!editChannel.channel_number || !editChannel.chemical_code || editingChannel">
              {{ editingChannel ? $t('chemicalCall.savingLabel') : $t('chemicalCall.saveChangesButton') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Collapsible Log Panel (Bottom of page, audit compliant) -->
    <div class="logs-panel card">
      <div class="logs-header" @click="showLogs = !showLogs">
        <h4>{{ $t('chemicalCall.logsPanelTitle') }}</h4>
        <span class="toggle-icon text-muted font-sm">{{ showLogs ? $t('chemicalCall.logsCollapseLabel') : $t('chemicalCall.logsExpandLabel') }}</span>
      </div>
      <div v-if="showLogs" class="logs-body mt-3">
        <div class="table-responsive">
          <table class="table table-dark logs-table">
            <thead>
              <tr>
                <th>{{ $t('chemicalCall.tableColTime') }}</th>
                <th>{{ $t('chemicalCall.tableColMachine') }}</th>
                <th>{{ $t('chemicalCall.tableColChannel') }}</th>
                <th>{{ $t('chemicalCall.tableColChemical') }}</th>
                <th>{{ $t('chemicalCall.tableColTransition') }}</th>
                <th>{{ $t('chemicalCall.tableColActor') }}</th>
                <th>{{ $t('chemicalCall.tableColWorkstation') }}</th>
                <th>{{ $t('chemicalCall.tableColDetail') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(log, idx) in logs" :key="idx" :class="log.type">
                <td class="font-mono">{{ log.time }}</td>
                <td><strong>{{ log.machine_code || '-' }}</strong></td>
                <td>{{ $t('chemicalCall.channelPill', { number: log.channel_number || '-' }) }}</td>
                <td class="font-mono text-info">{{ log.chemical_code || '-' }}</td>
                <td>
                  <span class="status-transition">
                    <span class="badge" :class="getSimpleStatus(log.before_status).cls">{{ getSimpleStatus(log.before_status).label }}</span>
                    <span class="transition-arrow">→</span>
                    <span class="badge" :class="getSimpleStatus(log.after_status).cls">{{ getSimpleStatus(log.after_status).label }}</span>
                  </span>
                </td>
                <td><code>{{ log.actor_username || $t('chemicalCall.systemActor') }}</code></td>
                <td><code>{{ log.workstation_code || '-' }}</code></td>
                <td>{{ log.message }}</td>
              </tr>
              <tr v-if="logs.length === 0">
                <td colspan="8" class="text-center text-muted py-4">{{ $t('chemicalCall.noLogsMessage') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <FullscreenButton />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import echo from '../services/echo';
import { isFullscreen } from '../services/layout';
import FullscreenButton from '../components/FullscreenButton.vue';

const { t } = useI18n({ useScope: 'global' });
const route = useRoute();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);
const remoteMode = ref<'VIEW_ONLY' | 'REMOTE_OPERATE'>('VIEW_ONLY');
const showLogs = ref(true);

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

const channelsList = ref<ChemicalChannel[]>([]);
const loading = ref(true);
const actionLoading = ref<number | null>(null);
const errorMsg = ref('');
const successMsg = ref('');
const logs = ref<any[]>([]);

// Thêm máy / thêm kênh — danh mục dùng chung với Lô sản xuất (bảng machines).
const machinesList = ref<any[]>([]);
const showAddMachine = ref(false);
const newMachine = ref({ code: '', name: '' });
const addingMachine = ref(false);
const addMachineError = ref('');

const showAddChannel = ref(false);
const newChannel = ref<{ machine_id: number | null; channel_number: number | null; chemical_code: string }>({
  machine_id: null,
  channel_number: null,
  chemical_code: ''
});
const addingChannel = ref(false);
const addChannelError = ref('');

const showEditChannel = ref(false);
const editChannel = ref<{ channel_id: number | null; machine_code: string; original_channel_number: number | null; channel_number: number | null; chemical_code: string }>({
  channel_id: null,
  machine_code: '',
  original_channel_number: null,
  channel_number: null,
  chemical_code: ''
});
const editingChannel = ref(false);
const editChannelError = ref('');

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

// Số thứ tự máy trích từ mã "VDxxx" — dùng để sắp tăng dần đúng số (không phải so sánh
// chuỗi, vì "VD010" < "VD9" theo chuỗi dù 10 > 9). Máy không khớp định dạng VD rơi
// xuống cuối, xếp theo tên để vẫn có thứ tự ổn định.
function machineSortNum(code: string): number {
  const m = /^VD(\d+)$/.exec((code || '').toUpperCase().trim());
  return m ? parseInt(m[1], 10) : Number.MAX_SAFE_INTEGER;
}

// Danh sách máy theo thứ tự tăng dần (VD001 -> VD018...) để hiển thị thẻ máy trên màn
// hình theo đúng thứ tự vật lý, thay vì thứ tự ngẫu nhiên theo dữ liệu trả về từ API.
const sortedMachineCodes = computed(() => {
  return Object.keys(groupedChannels.value).sort((a, b) => {
    const diff = machineSortNum(a) - machineSortNum(b);
    return diff !== 0 ? diff : a.localeCompare(b);
  });
});

// Fetch channels from Backend API
async function fetchChannels() {
  try {
    const res = await axios.get('/api/chemical-channels');
    channelsList.value = res.data;
  } catch (err: any) {
    console.error('Failed to fetch channels:', err);
    errorMsg.value = t('chemicalCall.errorFetchChannels');
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

// Danh sách máy dùng cho dropdown "Thêm kênh" (bảng machines dùng chung toàn hệ thống).
async function fetchMachinesList() {
  try {
    const res = await axios.get('/api/machines');
    machinesList.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to fetch machines list:', err);
  }
}

function openAddMachine() {
  newMachine.value = { code: '', name: '' };
  addMachineError.value = '';
  showAddMachine.value = true;
}

async function submitAddMachine() {
  addMachineError.value = '';
  addingMachine.value = true;
  try {
    await axios.post('/api/machines', newMachine.value);
    showAddMachine.value = false;
    await fetchMachinesList();
    successMsg.value = t('chemicalCall.successAddMachine', { code: newMachine.value.code });
  } catch (err: any) {
    addMachineError.value = err.response?.data?.message || t('chemicalCall.errorAddMachine');
  } finally {
    addingMachine.value = false;
  }
}

function openAddChannel() {
  newChannel.value = { machine_id: null, channel_number: null, chemical_code: '' };
  addChannelError.value = '';
  showAddChannel.value = true;
  if (machinesList.value.length === 0) {
    fetchMachinesList();
  }
}

async function submitAddChannel() {
  addChannelError.value = '';
  addingChannel.value = true;
  try {
    await axios.post('/api/chemical-channels', newChannel.value);
    showAddChannel.value = false;
    await fetchChannels();
    successMsg.value = t('chemicalCall.successAddChannel', { number: newChannel.value.channel_number });
  } catch (err: any) {
    addChannelError.value = err.response?.data?.message || t('chemicalCall.errorAddChannel');
  } finally {
    addingChannel.value = false;
  }
}

function openEditChannel(channel: ChemicalChannel) {
  editChannel.value = {
    channel_id: channel.channel_id,
    machine_code: channel.machine_code,
    original_channel_number: channel.channel_number,
    channel_number: channel.channel_number,
    chemical_code: channel.chemical_code
  };
  editChannelError.value = '';
  showEditChannel.value = true;
}

async function submitEditChannel() {
  editChannelError.value = '';
  editingChannel.value = true;
  try {
    await axios.patch(`/api/chemical-channels/${editChannel.value.channel_id}`, {
      channel_number: editChannel.value.channel_number,
      chemical_code: editChannel.value.chemical_code
    }, getRequestConfig());
    showEditChannel.value = false;
    await fetchChannels();
    successMsg.value = t('chemicalCall.successEditChannel', { number: editChannel.value.channel_number, machineCode: editChannel.value.machine_code });
  } catch (err: any) {
    editChannelError.value = err.response?.data?.message || t('chemicalCall.errorEditChannel');
  } finally {
    editingChannel.value = false;
  }
}

// Kênh đang "ĐỎ" (chưa OK) khi có yêu cầu đang chờ phát/đã tiếp nhận nhưng chưa xong.
function isChannelRed(channel: ChemicalChannel): boolean {
  return !!channel.current_request && (channel.current_request.status === 'ORDERED' || channel.current_request.status === 'ACKNOWLEDGED');
}

// Toggle 1 nút duy nhất thay cho quy trình nhiều bước Gọi/Tiếp nhận/Hoàn thành/OK:
// Xanh -> bấm = Gọi hóa chất (chuyển Đỏ). Đỏ -> bấm = báo Xong (Hoàn thành + đóng yêu
// cầu luôn trong 1 lần bấm, chuyển thẳng lại Xanh, không cần bấm OK riêng nữa).
//
// Cập nhật LẠC QUAN: đổi màu/nhãn nút NGAY khi bấm, không đợi PATCH/POST xong. Đồng bộ
// lại id/thời gian thật qua fetchChannels() chạy nền; rollback nếu API lỗi.
async function toggleChannel(channel: ChemicalChannel) {
  errorMsg.value = '';
  successMsg.value = '';
  actionLoading.value = channel.channel_id;

  const previousRequest = channel.current_request;
  const wasRed = isChannelRed(channel);
  channel.current_request = wasRed
    ? null
    : { id: '__optimistic__', status: 'ORDERED', requested_at: new Date().toISOString() };

  try {
    if (wasRed) {
      const requestId = previousRequest!.id;
      await axios.patch(`/api/chemical-call-requests/${requestId}/complete`, {}, getRequestConfig());
      await axios.patch(`/api/chemical-call-requests/${requestId}/reset`, {}, getRequestConfig());
      successMsg.value = t('chemicalCall.successToggleDone', { machineCode: channel.machine_code, number: channel.channel_number });
    } else {
      // Nếu còn sót request DONE cũ chưa đóng (VD do lỗi mạng lần trước), đóng nốt
      // trước khi gọi mới — tránh vi phạm ràng buộc unique request đang active.
      if (previousRequest?.id) {
        await axios.patch(`/api/chemical-call-requests/${previousRequest.id}/reset`, {}, getRequestConfig());
      }
      const idempotencyKey = `cc-${channel.channel_id}-${Date.now()}`;
      await axios.post('/api/chemical-call-requests', {
        channel_id: channel.channel_id,
        idempotency_key: idempotencyKey
      }, getRequestConfig());
      successMsg.value = t('chemicalCall.successToggleCall', { machineCode: channel.machine_code, number: channel.channel_number });
    }
    // Đợi fetchChannels() lấy trạng thái THẬT từ server rồi mới mở khoá thao tác —
    // tránh trường hợp API mutation trả về xong nhưng dữ liệu trên lưới vẫn là dữ
    // liệu lạc quan cũ, người dùng bấm tiếp trước khi trạng thái thật sự cập nhật.
    await Promise.all([fetchChannels(), fetchRecentEvents()]);
  } catch (err: any) {
    channel.current_request = previousRequest;
    errorMsg.value = err.response?.data?.message || t('chemicalCall.errorToggleChannel');
  } finally {
    actionLoading.value = null;
  }
}

function getChannelRowClass(channel: ChemicalChannel) {
  return isChannelRed(channel) ? 'row-ordered' : 'row-done';
}

// Rút gọn toàn bộ trạng thái nội bộ (CREATED/ORDERED/ACKNOWLEDGED/DONE/RESET/CANCELLED)
// về đúng 2 khái niệm người vận hành thấy trên lưới máy: OK (xanh) / CHƯA OK (đỏ) —
// riêng CANCELLED giữ nhãn riêng vì đó là "hủy yêu cầu", không phải trạng thái van.
function getSimpleStatus(status: string): { label: string; cls: string } {
  if (status === 'CREATED' || status === 'ORDERED' || status === 'ACKNOWLEDGED') {
    return { label: t('chemicalCall.statusNotOk'), cls: 'badge-danger' };
  }
  if (status === 'DONE' || status === 'RESET') {
    return { label: t('chemicalCall.statusOk'), cls: 'badge-success' };
  }
  if (status === 'CANCELLED') {
    return { label: t('chemicalCall.statusCancelled'), cls: 'badge-neutral' };
  }
  return { label: status || '-', cls: 'badge-neutral' };
}

function formatTime(timeStr: string | null) {
  if (!timeStr) return '-';
  try {
    const d = new Date(timeStr);
    return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
  } catch (e) {
    return timeStr;
  }
}

onMounted(async () => {
  // Chạy song song thay vì tuần tự — trang hiện trước, không phải chờ hết API này tới API
  // kia mới render. Danh sách máy (machinesList) không cần cho lần tải đầu, chỉ dùng khi mở
  // popup "Thêm kênh" nên được tải lười (xem openAddChannel) thay vì chặn ở đây.
  await Promise.all([fetchChannels(), fetchRecentEvents()]);

  // Realtime qua Reverb (WebSocket) — /chemical-call và /chemical-call/monitor cùng nghe
  // kênh public "chemical-channels": đổi trạng thái ở trang này thấy NGAY ở trang kia,
  // không phải đợi tới lượt polling. Xem ChemicalChannelUpdated::broadcastOn (backend).
  echo.channel('chemical-channels').listen('.updated', () => {
    fetchChannels();
    fetchRecentEvents();
  });

  // Vẫn giữ polling làm lưới an toàn (vd WebSocket rớt kết nối tạm thời) — chu kỳ dài hơn
  // vì cập nhật chính đã qua Reverb tức thì.
  // Tab bị ẩn thì BỎ QUA lượt poll: backend chạy `php artisan serve` (một tiến trình, xử lý
  // tuần tự — xem session-log mục 38/1113), nên mọi request thừa của tab nền đều xếp hàng
  // trước request thật của người đang thao tác ở tab khác. Khi quay lại tab thì nạp ngay.
  pollInterval = setInterval(() => {
    if (document.hidden) return;
    fetchChannels();
    fetchRecentEvents();
  }, 10000);
  document.addEventListener('visibilitychange', onVisible);
});

function onVisible() {
  if (document.hidden) return;
  fetchChannels();
  fetchRecentEvents();
}

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  document.removeEventListener('visibilitychange', onVisible);
  echo.leaveChannel('chemical-channels');
});
</script>

<style scoped>
.chemical-call-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
}

.machine-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: var(--space-lg);
}

@media (min-width: 1200px) {
  .machine-grid {
    grid-template-columns: repeat(3, 1fr);
  }

  /* Đang ở chế độ Toàn màn hình (sidebar+topbar ẩn) — nhiều chỗ trống hơn, dùng 4 cột */
  .machine-grid.grid-4col {
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
  font-size: 1.1rem;
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
  grid-template-columns: auto auto 1fr minmax(0, auto);
  align-items: center;
  gap: var(--space-lg);
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-divider);
  transition: background-color 0.2s ease;
}

.channel-row:last-child {
  border-bottom: none;
}

.channel-number-col,
.chemical-name-col {
  white-space: nowrap;
}

.channel-number-col {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.alert-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background-color: #ef4444;
  box-shadow: 0 0 0 rgba(239, 68, 68, 0.6);
  animation: alert-dot-pulse 1.2s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes alert-dot-pulse {
  0% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .alert-dot {
    animation: none;
  }
}

.channel-number-pill {
  display: inline-block;
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-title);
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card-hover);
  padding: 4px 12px;
  border-radius: var(--radius-full);
}

.time-col {
  font-size: 0.95rem;
}

/* Status colors equivalent to legacy VBA */
.row-idle {
  background-color: transparent;
}

.row-ordered {
  background-color: rgba(239, 68, 68, 0.08);
  border-left: 4px solid #ef4444;
  animation: row-alert-pulse 1.2s ease-in-out infinite;
}

.row-done {
  background-color: rgba(52, 211, 153, 0.08);
  border-left: 4px solid transparent;
}

/* Hàng đang xử lý (vừa bấm) — tối lại để báo hiệu đang thay đổi trạng thái,
   thay thế màu đỏ/xanh nhấp nháy trong lúc chờ API phản hồi. */
.row-processing {
  animation: none;
  background-color: rgba(15, 23, 42, 0.35);
  filter: grayscale(0.4);
}

/* Các hàng còn lại — mờ nhẹ + khoá thao tác khi đang có 1 hàng khác xử lý,
   để người dùng hiểu phải đợi đổi xong mới được bấm hàng khác. */
.row-locked {
  opacity: 0.45;
  pointer-events: none;
  filter: grayscale(0.3);
  transition: opacity 0.2s ease, filter 0.2s ease;
}

@keyframes row-alert-pulse {
  0%, 100% {
    background-color: rgba(239, 68, 68, 0.08);
  }
  50% {
    background-color: rgba(239, 68, 68, 0.28);
  }
}

@media (prefers-reduced-motion: reduce) {
  .row-ordered {
    animation: none;
    background-color: rgba(239, 68, 68, 0.2);
  }
}

.chem-formula {
  display: inline-block;
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--status-blue);
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  padding: 4px 12px;
  border-radius: var(--radius-md);
}

.badge {
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: var(--radius-full);
  font-weight: 700;
  display: inline-block;
  text-align: center;
  white-space: nowrap;
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
  max-height: 320px;
  overflow-y: auto;
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
}

/* Bảng này cố ý tối ở CẢ hai theme (không dùng biến --bg-card) nên phải tự chỉnh sang tông
   navy cho khớp dark theme mới — để nguyên #111827 sẽ thành mảng đen lạc tông giữa nền xanh. */
.table-dark {
  background-color: hsl(217, 40%, 13%);
  color: #f3f4f6;
  margin-bottom: 0;
  width: 100%;
  border-collapse: collapse;
}

.table th, .table td {
  padding: 10px 14px;
  border-bottom: 1px solid hsl(217, 30%, 24%);
  font-size: 0.85rem;
  text-align: left;
}

.table th {
  background-color: hsl(217, 30%, 24%);
  color: #9ca3af;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  position: sticky;
  top: 0;
}

.table tbody tr:hover {
  background-color: rgba(255, 255, 255, 0.03);
}

.status-transition {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.transition-arrow {
  color: #6b7280;
  font-weight: 700;
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

.dot-green {
  background-color: var(--status-green);
}

/* Thêm máy / thêm kênh */
.admin-actions-row {
  display: flex;
  gap: var(--space-md);
}

.action-btn-col {
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
}

.toggle-btn {
  min-height: 34px;
  min-width: 0;
  flex: 1 1 auto;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding-left: 8px;
  padding-right: 8px;
}

.edit-channel-btn {
  flex-shrink: 0;
  min-height: 34px;
  padding: 0 10px;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.ws-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-xl);
}

.modal-header {
  padding: var(--space-xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
  color: var(--text-title);
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
}

.modal-body {
  padding: var(--space-xl);
}

.modal-body .form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.modal-body .form-group label {
  font-weight: 600;
  color: var(--text-title);
  font-size: 0.9rem;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}
</style>
