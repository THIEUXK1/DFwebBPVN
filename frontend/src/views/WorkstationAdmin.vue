<template>
  <div class="ws-admin-container">
    <div class="panel-header mb-4 d-flex justify-between align-center">
      <div>
        <h3>{{ $t('workstationAdmin.pageTitle') }}</h3>
        <p class="text-muted">{{ $t('workstationAdmin.pageSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" @click="openRegisterModal">
        {{ $t('workstationAdmin.registerNewBtn') }}
      </button>
    </div>

    <div v-if="errorMessage" class="card error-card mb-4">{{ errorMessage }}</div>
    <div v-if="successMessage" class="card success-card mb-4">{{ successMessage }}</div>

    <div v-if="loading" class="card text-center padding-xl text-muted">{{ $t('workstationAdmin.loadingList') }}</div>

    <div v-else class="ws-grid">
      <div v-for="ws in workstations" :key="ws.id" class="card ws-card">
        <div class="ws-card-header mb-2 d-flex justify-between align-center">
          <span class="ws-code-badge font-semibold font-mono">{{ ws.code }}</span>
          <span class="status-badge d-flex align-center gap-1">
            <span class="dot" :class="isOnline(ws) ? 'dot-green' : 'dot-grey'"></span>
            <span class="badge" :class="isOnline(ws) ? 'badge-green' : 'badge-grey'">
              {{ isOnline(ws) ? 'ONLINE' : 'OFFLINE' }}
            </span>
          </span>
        </div>
        <h4 class="ws-name mb-3">{{ ws.name }}</h4>
        
        <div class="ws-info-grid font-sm mb-3">
          <div class="info-item">
            <strong>{{ $t('workstationAdmin.capabilitiesLabel') }}</strong>
            <div class="flex-wrap gap-1 mt-1 d-flex">
              <span v-for="c in getBusinessCaps(ws)" :key="c.code" class="badge badge-blue font-xs">{{ c.name }}</span>
              <span v-for="c in getDeviceCaps(ws)" :key="c.code" class="badge badge-purple font-xs">{{ c.name }}</span>
            </div>
          </div>
          <div class="info-item mt-2"><strong>{{ $t('workstationAdmin.locationLabel') }}</strong> {{ ws.location || $t('workstationAdmin.notAssigned') }}</div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.scaleLabel') }}</strong> ⚖️ {{ getDefaultDeviceCode(ws, 'SCALE') || $t('workstationAdmin.notConfigured') }}</div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.printerLabel') }}</strong> 🖨️ {{ getDefaultDeviceCode(ws, 'PRINTER') || $t('workstationAdmin.notConfigured') }}</div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.agentLabel') }}</strong> 🔌 <code>{{ ws.agent_version || 'N/A' }}</code></div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.heartbeatLabel') }}</strong> <span class="font-xs">{{ formatTime(ws.last_seen_at) }}</span></div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.kioskModeLabel') }}</strong> <span class="badge font-xs" :class="ws.kiosk_mode ? 'badge-green' : 'badge-grey'">{{ ws.kiosk_mode ? $t('workstationAdmin.kioskOn') : $t('workstationAdmin.kioskOff') }}</span></div>
          <div class="info-item"><strong>{{ $t('workstationAdmin.defaultLabel') }}</strong> <code>{{ ws.default_capability || $t('workstationAdmin.notChosen') }}</code></div>
          <div class="info-item"><strong>{{ $t('common.status') }}:</strong> <span :class="ws.suspended ? 'text-danger font-semibold' : 'text-success'">{{ ws.suspended ? $t('workstationAdmin.suspendedState') : $t('workstationAdmin.activeState') }}</span></div>
        </div>

        <div class="ws-card-footer mt-auto pt-2 border-t">
          <div class="btn-action-group">
            <button class="btn btn-primary btn-sm btn-action flex-1" @click="operateClient(ws)" :disabled="ws.suspended">
              {{ $t('workstationAdmin.viewKioskBtn') }}
            </button>
            <button class="btn btn-secondary btn-sm btn-action" @click="openEditModal(ws)">
              {{ $t('workstationAdmin.configBtn') }}
            </button>
            <button class="btn btn-warning btn-sm btn-action" @click="toggleSuspend(ws)">
              {{ ws.suspended ? $t('workstationAdmin.resumeBtn') : $t('workstationAdmin.suspendBtn') }}
            </button>
            <button class="btn btn-secondary btn-sm btn-action" @click="openProvisionModal(ws)">
              {{ $t('workstationAdmin.createUserBtn') }}
            </button>
            <button class="btn btn-success btn-sm btn-action" @click="openKioskLinkModal(ws)">
              {{ $t('workstationAdmin.kioskLinkBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="editingWorkstation" class="modal-overlay" @click.self="closeEditModal">
      <div class="ws-modal-card modal-lg">
        <div class="modal-header">
          <h3>{{ $t('workstationAdmin.editModalTitle', { code: editingWorkstation.code }) }}</h3>
          <button @click="closeEditModal" class="close-btn">&times;</button>
        </div>
        
        <!-- Tabs -->
        <div class="modal-tabs">
          <button class="tab-btn" :class="{ active: activeTab === 'general' }" @click="activeTab = 'general'">{{ $t('workstationAdmin.tabGeneral') }}</button>
          <button class="tab-btn" :class="{ active: activeTab === 'capabilities' }" @click="activeTab = 'capabilities'">{{ $t('workstationAdmin.tabCapabilities') }}</button>
          <button class="tab-btn" :class="{ active: activeTab === 'devices' }" @click="activeTab = 'devices'">{{ $t('workstationAdmin.tabDevices') }}</button>
        </div>

        <div class="modal-body">
          <div v-if="modalError" class="card error-card mb-3">{{ modalError }}</div>
          <div v-if="modalSuccess" class="card success-card mb-3">{{ modalSuccess }}</div>

          <!-- General Tab -->
          <div v-if="activeTab === 'general'">
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldStationName') }}</label>
              <input v-model="editForm.name" type="text" class="form-input" required />
            </div>

            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldLocation') }}</label>
              <input v-model="editForm.location" type="text" class="form-input" />
            </div>

            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldDefaultCapability') }}</label>
              <select v-model="editForm.default_capability" class="form-select" @change="autoSetRoute">
                <option value="">{{ $t('workstationAdmin.optionNoneMenu') }}</option>
                <option value="PRODUCTION_ORDER">PRODUCTION_ORDER — {{ $t('workstationAdmin.capProductionOrder') }}</option>
                <option value="QR_LABEL_PRINTING">QR_LABEL_PRINTING — {{ $t('workstationAdmin.capQrLabelPrinting') }}</option>
                <option value="SMALL_SCALE">SMALL_SCALE — {{ $t('workstationAdmin.capSmallScale') }}</option>
                <option value="LARGE_SCALE">LARGE_SCALE — {{ $t('workstationAdmin.capLargeScale') }}</option>
                <option value="CHEMICAL_CALL">CHEMICAL_CALL — {{ $t('workstationAdmin.capChemicalCall') }}</option>
              </select>
            </div>

            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldDefaultRoute') }}</label>
              <input v-model="editForm.default_route" type="text" class="form-input" :placeholder="$t('workstationAdmin.placeholderRoute')" />
            </div>
          </div>

          <!-- Capabilities Tab -->
          <div v-if="activeTab === 'capabilities'">
            <div class="form-group mb-4">
              <label class="d-block font-semibold mb-2">{{ $t('workstationAdmin.businessCapsLabel') }}</label>
              <div class="checkbox-group grid-2">
                <div v-for="cap in allCapabilities.filter(c => c.category === 'BUSINESS')" :key="cap.code" class="d-flex align-center mb-2">
                  <input v-model="editForm.capabilities" type="checkbox" :id="'cap_'+cap.code" :value="cap.code" class="form-checkbox mr-2" />
                  <label :for="'cap_'+cap.code" class="mb-0 cursor-pointer">
                    <strong>{{ cap.name }}</strong>
                    <span class="text-muted d-block font-xs">{{ cap.code }}</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label class="d-block font-semibold mb-2">{{ $t('workstationAdmin.deviceCapsLabel') }}</label>
              <div class="checkbox-group grid-2">
                <div v-for="cap in allCapabilities.filter(c => c.category === 'DEVICE')" :key="cap.code" class="d-flex align-center mb-2">
                  <input v-model="editForm.capabilities" type="checkbox" :id="'cap_'+cap.code" :value="cap.code" class="form-checkbox mr-2" />
                  <label :for="'cap_'+cap.code" class="mb-0 cursor-pointer">
                    <strong>{{ cap.name }}</strong>
                    <span class="text-muted d-block font-xs">{{ cap.code }}</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          <!-- Devices Tab -->
          <div v-if="activeTab === 'devices'">
            <p class="text-muted font-sm mb-3">{{ $t('workstationAdmin.devicesTabDesc') }}</p>
            
            <div v-for="(mapping, idx) in editForm.devices" :key="idx" class="device-mapping-row mb-3 d-flex gap-2 align-center">
              <div class="form-group flex-1">
                <label v-if="idx === 0">{{ $t('workstationAdmin.colDevice') }}</label>
                <select v-model="mapping.device_id" class="form-select font-mono">
                  <option value="">{{ $t('workstationAdmin.optionChooseDevice') }}</option>
                  <option v-for="d in allDevices" :key="d.id" :value="d.id">
                    [{{ d.device_type }}] {{ d.code }} - {{ d.status }}
                  </option>
                </select>
              </div>

              <div class="form-group" style="width: 150px;">
                <label v-if="idx === 0">{{ $t('workstationAdmin.colRole') }}</label>
                <select v-model="mapping.device_role" class="form-select">
                  <option value="PRIMARY_SCALE">{{ $t('workstationAdmin.rolePrimaryScale') }}</option>
                  <option value="SECONDARY_SCALE">{{ $t('workstationAdmin.roleSecondaryScale') }}</option>
                  <option value="PRIMARY_PRINTER">{{ $t('workstationAdmin.rolePrimaryPrinter') }}</option>
                  <option value="BACKUP_PRINTER">{{ $t('workstationAdmin.roleBackupPrinter') }}</option>
                  <option value="SCANNER">{{ $t('workstationAdmin.roleScanner') }}</option>
                  <option value="LOCAL_AGENT">{{ $t('workstationAdmin.roleLocalAgent') }}</option>
                </select>
              </div>

              <div class="form-group text-center" style="width: 80px;">
                <label v-if="idx === 0" class="d-block">{{ $t('workstationAdmin.colDefault') }}</label>
                <input v-model="mapping.is_default" type="checkbox" class="form-checkbox" />
              </div>

              <div class="form-group text-center" style="width: 80px;">
                <label v-if="idx === 0" class="d-block">{{ $t('workstationAdmin.colPriority') }}</label>
                <input v-model="mapping.priority" type="number" class="form-input text-center" style="padding: 4px;" />
              </div>

              <button class="btn btn-danger btn-sm align-self-end mb-1" @click="removeDeviceRow(idx)" style="padding: 6px 10px;">
                🗑️
              </button>
            </div>

            <button class="btn btn-secondary btn-sm mt-2" @click="addDeviceRow">
              {{ $t('workstationAdmin.addDeviceBtn') }}
            </button>
          </div>
        </div>

        <div class="modal-footer d-flex justify-end gap-2">
          <button class="btn btn-secondary" @click="closeEditModal" :disabled="submitting">{{ $t('common.close') }}</button>
          <button class="btn btn-primary" @click="submitEditConfig" :disabled="submitting">
            {{ submitting ? $t('workstationAdmin.savingLabel') : $t('workstationAdmin.saveConfigBtn') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Kiosk Link Generation Modal -->
    <div v-if="kioskLinkWorkstation" class="modal-overlay" @click.self="closeKioskLinkModal">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('workstationAdmin.kioskModalTitle', { code: kioskLinkWorkstation.code }) }}</h3>
          <button @click="closeKioskLinkModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <p class="font-sm text-muted mb-3">
            {{ $t('workstationAdmin.kioskDesc') }}
          </p>

          <div v-if="kioskError" class="card error-card mb-3">{{ kioskError }}</div>
          
          <div v-if="generatedKioskUrl" class="card success-card mb-3">
            <h5 class="mb-2">{{ $t('workstationAdmin.securityNoticeTitle') }}</h5>
            <p class="font-xs mb-3 text-dark">
              {{ $t('workstationAdmin.kioskTokenWarning') }}
            </p>
            <div class="kiosk-url-box d-flex gap-2">
              <!-- Bỏ ref="kioskUrlInput": nút copy dùng navigator.clipboard đọc thẳng
                   generatedKioskUrl, không đụng tới thẻ input này. -->
              <input type="text" class="form-input font-mono font-xs flex-1" readonly :value="generatedKioskUrl" />
              <button class="btn btn-primary btn-sm" @click="copyKioskUrl">{{ $t('workstationAdmin.copyBtn') }}</button>
            </div>
            <p v-if="copied" class="text-success font-xs mt-1 font-semibold">{{ $t('workstationAdmin.copiedMsg') }}</p>
          </div>

          <div class="kiosk-token-meta font-sm mb-3">
            <div><strong>{{ $t('workstationAdmin.tokenStatusLabel') }}</strong> {{ kioskLinkWorkstation.kiosk_token_active ? $t('workstationAdmin.tokenActive') : $t('workstationAdmin.tokenRevoked') }}</div>
            <div v-if="kioskLinkWorkstation.kiosk_token_expires_at">
              <strong>{{ $t('workstationAdmin.tokenExpiryLabel') }}</strong> {{ formatDate(kioskLinkWorkstation.kiosk_token_expires_at) }}
            </div>
          </div>

          <div class="kiosk-actions d-flex gap-2">
            <button class="btn btn-primary flex-1 py-2" @click="generateKioskLink" :disabled="kioskSubmitting">
              ⚡ {{ kioskLinkWorkstation.kiosk_token_active ? $t('workstationAdmin.rotateTokenBtn') : $t('workstationAdmin.createKioskLinkBtn') }}
            </button>
            <button 
              v-if="kioskLinkWorkstation.kiosk_token_active" 
              class="btn btn-danger py-2" 
              @click="revokeKioskLink" 
              :disabled="kioskSubmitting"
            >
               {{ $t('workstationAdmin.revokeBtn') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Provision User Modal -->
    <div v-if="activeWorkstation" class="modal-overlay" @click.self="closeModal">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('workstationAdmin.provisionModalTitle') }}</h3>
          <button @click="closeModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div v-if="formError" class="card error-card mb-3">{{ formError }}</div>
          <div v-if="formSuccess" class="card success-card mb-3">{{ formSuccess }}</div>

          <form @submit.prevent="submitProvision">
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldUsername') }}</label>
              <input v-model="form.username" type="text" class="form-input" required :placeholder="$t('workstationAdmin.placeholderUsername')" />
            </div>
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldDisplayName') }}</label>
              <input v-model="form.display_name" type="text" class="form-input" required :placeholder="$t('workstationAdmin.placeholderDisplayName')" />
            </div>
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldPassword') }}</label>
              <input v-model="form.password" type="password" class="form-input" required :placeholder="$t('workstationAdmin.placeholderPassword')" />
            </div>
            <div class="form-group mb-4">
              <label>{{ $t('workstationAdmin.fieldMainRole') }}</label>
              <select v-model="form.role" class="form-select">
                <option value="OPERATOR">OPERATOR — {{ $t('workstationAdmin.roleOperator') }}</option>
                <option value="SUPERVISOR">SUPERVISOR — {{ $t('workstationAdmin.roleSupervisor') }}</option>
                <option value="TECHNOLOGIST">TECHNOLOGIST — {{ $t('workstationAdmin.roleTechnologist') }}</option>
              </select>
            </div>
            <button type="submit" class="btn btn-primary w-full py-2" :disabled="submitting">
              {{ submitting ? $t('workstationAdmin.creatingLabel') : $t('workstationAdmin.createAccountBtn') }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Register Kiosk Client Modal -->
    <div v-if="showRegisterModal" class="modal-overlay" @click.self="closeRegisterModal">
      <div class="ws-modal-card">
        <div class="modal-header">
          <h3>{{ $t('workstationAdmin.registerModalTitle') }}</h3>
          <button @click="closeRegisterModal" class="close-btn">&times;</button>
        </div>
        <div class="modal-body">
          <div v-if="regError" class="card error-card mb-3">{{ regError }}</div>

          <form @submit.prevent="submitRegister">
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldStationCode') }}</label>
              <input v-model="regForm.code" type="text" class="form-input font-mono" required :placeholder="$t('workstationAdmin.placeholderStationCode')" />
            </div>
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldStationDisplayName') }}</label>
              <input v-model="regForm.name" type="text" class="form-input" required :placeholder="$t('workstationAdmin.placeholderStationDisplayName')" />
            </div>
            <div class="form-group mb-3">
              <label>{{ $t('workstationAdmin.fieldMainCapability') }}</label>
              <select v-model="regForm.default_capability" class="form-select">
                <option value="PRODUCTION_ORDER">PRODUCTION_ORDER — {{ $t('workstationAdmin.capProductionOrder') }}</option>
                <option value="QR_LABEL_PRINTING">QR_LABEL_PRINTING — {{ $t('workstationAdmin.capQrLabelPrinting') }}</option>
                <option value="SMALL_SCALE">SMALL_SCALE — {{ $t('workstationAdmin.capSmallScale') }}</option>
                <option value="LARGE_SCALE">LARGE_SCALE — {{ $t('workstationAdmin.capLargeScale') }}</option>
                <option value="CHEMICAL_CALL">CHEMICAL_CALL — {{ $t('workstationAdmin.capChemicalCall') }}</option>
              </select>
            </div>
            <div class="form-group mb-4">
              <label>{{ $t('workstationAdmin.fieldWorkshopArea') }}</label>
              <input v-model="regForm.location" type="text" class="form-input" :placeholder="$t('workstationAdmin.placeholderWorkshopArea')" />
            </div>
            <button type="submit" class="btn btn-primary w-full py-2" :disabled="submitting">
              {{ submitting ? $t('workstationAdmin.registeringLabel') : $t('workstationAdmin.finishRegisterBtn') }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n({ useScope: 'global' });
const workstations = ref<any[]>([]);
const allDevices = ref<any[]>([]);
const loading = ref(false);
const submitting = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const activeWorkstation = ref<any>(null);
const editingWorkstation = ref<any>(null);
const showRegisterModal = ref(false);
const activeTab = ref('general');
const modalError = ref('');
const modalSuccess = ref('');

// Kiosk Link modal state
const kioskLinkWorkstation = ref<any>(null);
const kioskSubmitting = ref(false);
const generatedKioskUrl = ref('');
const kioskError = ref('');
const copied = ref(false);

const allCapabilitiesRaw = [
  { code: 'CHEMICAL_CALL', nameKey: 'capChemicalCall', category: 'BUSINESS' },
  { code: 'PRODUCTION_ORDER', nameKey: 'capProductionOrder', category: 'BUSINESS' },
  { code: 'QR_LABEL_PRINTING', nameKey: 'capQrLabelPrinting', category: 'BUSINESS' },
  { code: 'SMALL_SCALE', nameKey: 'capSmallScale', category: 'BUSINESS' },
  { code: 'LARGE_SCALE', nameKey: 'capLargeScale', category: 'BUSINESS' },
  { code: 'PRINT', nameKey: 'capPrint', category: 'DEVICE' },
  { code: 'WEIGH', nameKey: 'capWeigh', category: 'DEVICE' },
  { code: 'SCAN_QR', nameKey: 'capScanQr', category: 'DEVICE' },
  { code: 'LOCAL_AGENT', nameKey: 'capLocalAgent', category: 'DEVICE' },
  { code: 'REMOTE_VIEW', nameKey: 'capRemoteView', category: 'DEVICE' },
];
// computed để đổi tên capability theo ngôn ngữ đang chọn.
const allCapabilities = computed(() => allCapabilitiesRaw.map(c => ({
  code: c.code,
  category: c.category,
  name: t(`workstationAdmin.${c.nameKey}`),
})));

const form = reactive({
  username: '',
  display_name: '',
  password: '',
  role: 'OPERATOR',
});
const formError = ref('');
const formSuccess = ref('');

const regForm = reactive({
  code: '',
  name: '',
  default_capability: 'SMALL_SCALE',
  location: t('workstationAdmin.defaultLocation'),
  default_route: '',
});
const regError = ref('');

const editForm = reactive({
  name: '',
  default_capability: '',
  location: '',
  default_route: '',
  capabilities: [] as string[],
  devices: [] as Array<{ device_id: string; device_role: string; is_default: boolean; priority: number }>,
});

async function loadWorkstations() {
  loading.value = true;
  try {
    const res = await axios.get('/api/admin/workstations');
    workstations.value = res.data.data;
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || t('workstationAdmin.errListLoadFailed');
  } finally {
    loading.value = false;
  }
}

async function loadDevices() {
  try {
    const res = await axios.get('/api/admin/devices');
    allDevices.value = res.data.data || [];
  } catch (err) {
    console.error('Failed to load devices:', err);
  }
}

function getBusinessCaps(ws: any) {
  return ws.capabilities?.filter((c: any) => c.category === 'BUSINESS') || [];
}

function getDeviceCaps(ws: any) {
  return ws.capabilities?.filter((c: any) => c.category === 'DEVICE') || [];
}

function getDefaultDeviceCode(ws: any, type: string): string {
  const dev = ws.devices?.find((d: any) => d.device_type === type && d.pivot?.is_default);
  if (dev) return dev.code;
  const anyDev = ws.devices?.find((d: any) => d.device_type === type);
  return anyDev ? anyDev.code : '';
}

function isOnline(ws: any) {
  if (!ws.last_seen_at) return false;
  const diff = Date.now() - new Date(ws.last_seen_at).getTime();
  return diff < 60000; // Online if seen in last 1 minute
}

function formatTime(dStr: string) {
  if (!dStr) return t('workstationAdmin.notConnected');
  return new Date(dStr).toLocaleTimeString('vi-VN', { hour12: false });
}

function formatDate(dStr: string) {
  if (!dStr) return '-';
  return new Date(dStr).toLocaleString('vi-VN', { hour12: false });
}

function operateClient(ws: any) {
  // Simulates opening in view-only or redirecting to kiosk flow if token is available
  // Here we redirect to their default screen
  if (ws.default_route) {
    window.open(ws.default_route, '_blank');
  }
}

async function toggleSuspend(ws: any) {
  errorMessage.value = '';
  successMessage.value = '';
  const action = ws.suspended ? 'resume' : 'suspend';
  try {
    const res = await axios.post(`/api/admin/workstations/${ws.id}/${action}`);
    successMessage.value = res.data.message;
    await loadWorkstations();
  } catch (err: any) {
    errorMessage.value = err.response?.data?.message || t('workstationAdmin.errActionFailed');
  }
}

// Provision User Account Modal
function openProvisionModal(ws: any) {
  activeWorkstation.value = ws;
  form.username = '';
  form.display_name = '';
  form.password = '';
  form.role = 'OPERATOR';
  formError.value = '';
  formSuccess.value = '';
}
function closeModal() {
  activeWorkstation.value = null;
}
async function submitProvision() {
  if (!activeWorkstation.value) return;
  formError.value = '';
  formSuccess.value = '';
  submitting.value = true;
  try {
    const res = await axios.post(`/api/admin/workstations/${activeWorkstation.value.id}/users`, form);
    formSuccess.value = res.data.message;
    await loadWorkstations();
    form.username = '';
    form.display_name = '';
    form.password = '';
  } catch (err: any) {
    formError.value = err.response?.data?.message || t('workstationAdmin.errCreateAccount');
  } finally {
    submitting.value = false;
  }
}

// Kiosk Link modal
function openKioskLinkModal(ws: any) {
  kioskLinkWorkstation.value = ws;
  generatedKioskUrl.value = '';
  kioskError.value = '';
  copied.value = false;
}
function closeKioskLinkModal() {
  kioskLinkWorkstation.value = null;
}
async function generateKioskLink() {
  if (!kioskLinkWorkstation.value) return;
  kioskError.value = '';
  kioskSubmitting.value = true;
  generatedKioskUrl.value = '';
  try {
    const res = await axios.post(`/api/admin/workstations/${kioskLinkWorkstation.value.id}/generate-kiosk-token`);
    if (res.data && res.data.status === 'SUCCESS') {
      generatedKioskUrl.value = res.data.kiosk_url;
      // Reload ws details to refresh status/expiration display
      await loadWorkstations();
      // Keep reference updated
      kioskLinkWorkstation.value = workstations.value.find(w => w.id === kioskLinkWorkstation.value.id);
    }
  } catch (err: any) {
    kioskError.value = err.response?.data?.message || t('workstationAdmin.errGenerateToken');
  } finally {
    kioskSubmitting.value = false;
  }
}
async function revokeKioskLink() {
  if (!kioskLinkWorkstation.value) return;
  kioskError.value = '';
  kioskSubmitting.value = true;
  try {
    const res = await axios.post(`/api/admin/workstations/${kioskLinkWorkstation.value.id}/revoke-kiosk-token`);
    if (res.data && res.data.status === 'SUCCESS') {
      generatedKioskUrl.value = '';
      await loadWorkstations();
      kioskLinkWorkstation.value = workstations.value.find(w => w.id === kioskLinkWorkstation.value.id);
    }
  } catch (err: any) {
    kioskError.value = err.response?.data?.message || t('workstationAdmin.errRevokeToken');
  } finally {
    kioskSubmitting.value = false;
  }
}
function copyKioskUrl() {
  if (!generatedKioskUrl.value) return;
  navigator.clipboard.writeText(generatedKioskUrl.value).then(() => {
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
  });
}

// Edit configurations modal
function openEditModal(ws: any) {
  editingWorkstation.value = ws;
  activeTab.value = 'general';
  modalError.value = '';
  modalSuccess.value = '';

  editForm.name = ws.name || '';
  editForm.default_capability = ws.default_capability || '';
  editForm.location = ws.location || '';
  editForm.default_route = ws.default_route || '';
  editForm.capabilities = ws.capabilities?.map((c: any) => c.code) || [];
  
  editForm.devices = ws.devices?.map((d: any) => ({
    device_id: d.id,
    device_role: d.pivot?.device_role || 'PRIMARY_SCALE',
    is_default: d.pivot?.is_default || false,
    priority: d.pivot?.priority || 1,
  })) || [];
}
function closeEditModal() {
  editingWorkstation.value = null;
}
function addDeviceRow() {
  editForm.devices.push({
    device_id: '',
    device_role: 'PRIMARY_SCALE',
    is_default: false,
    priority: 1
  });
}
function removeDeviceRow(idx: number) {
  editForm.devices.splice(idx, 1);
}
function autoSetRoute() {
  const routes: Record<string, string> = {
    'PRODUCTION_ORDER': '/order-scan',
    'SMALL_SCALE': '/weighing-station',
    'LARGE_SCALE': '/weighing-station',
    'QR_LABEL_PRINTING': '/print-station',
    'CHEMICAL_CALL': '/feeding-monitor',
  };
  editForm.default_route = routes[editForm.default_capability] || '/';
}
async function submitEditConfig() {
  if (!editingWorkstation.value) return;
  modalError.value = '';
  modalSuccess.value = '';
  submitting.value = true;

  // Filter out incomplete device mappings
  const devicesPayload = editForm.devices.filter(d => d.device_id !== '');

  try {
    const res = await axios.put(`/api/admin/workstations/${editingWorkstation.value.id}/config`, {
      name: editForm.name,
      default_capability: editForm.default_capability,
      location: editForm.location,
      default_route: editForm.default_route,
      capabilities: editForm.capabilities,
      devices: devicesPayload
    });

    modalSuccess.value = res.data.message;
    await loadWorkstations();
  } catch (err: any) {
    modalError.value = err.response?.data?.message || t('workstationAdmin.errUpdateConfig');
  } finally {
    submitting.value = false;
  }
}

// Register Client modal
function openRegisterModal() {
  showRegisterModal.value = true;
  regError.value = '';
  regForm.code = '';
  regForm.name = '';
  regForm.default_capability = 'SMALL_SCALE';
  regForm.location = t('workstationAdmin.defaultLocation');
  regForm.default_route = '/weighing-station';
}
function closeRegisterModal() {
  showRegisterModal.value = false;
}
async function submitRegister() {
  regError.value = '';
  submitting.value = true;
  try {
    await axios.post('/api/admin/workstations/register', regForm);
    await loadWorkstations();
    closeRegisterModal();
  } catch (err: any) {
    regError.value = err.response?.data?.message || t('workstationAdmin.errRegister');
  } finally {
    submitting.value = false;
  }
}

let refreshInterval: any = null;

onMounted(() => {
  loadWorkstations();
  loadDevices();
  refreshInterval = setInterval(loadWorkstations, 5000);
});

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval);
});
</script>

<style scoped>
.d-flex { display: flex; }
.d-block { display: block; }
.justify-between { justify-content: space-between; }
.justify-end { justify-content: flex-end; }
.align-center { align-items: center; }
.align-self-end { align-self: flex-end; }
.gap-1 { gap: 4px; }
.gap-2 { gap: 8px; }
.mr-2 { margin-right: 8px; }
.mr-1 { margin-right: 4px; }
.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.mt-auto { margin-top: auto; }
.flex-1 { flex: 1; }
.flex-wrap { flex-wrap: wrap; }
.font-semibold { font-weight: 600; }
.cursor-pointer { cursor: pointer; }

.ws-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: var(--space-xl);
}

.ws-card {
  min-height: 320px;
  display: flex;
  flex-direction: column;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: var(--space-md);
  transition: all 0.2s ease;
}
.ws-card:hover {
  border-color: var(--primary);
  transform: translateY(-2px);
}

.ws-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: var(--space-sm);
}

.ws-code-badge {
  font-family: 'JetBrains Mono', monospace;
  font-weight: 700;
  color: var(--primary-hover);
  background-color: var(--primary-bg);
  padding: 2px 10px;
  border-radius: var(--radius-full);
  font-size: 0.8rem;
}

.ws-name {
  margin: 0 0 4px 0;
  color: var(--text-title);
}

.font-mono {
  font-family: 'JetBrains Mono', monospace;
}

.text-dark {
  color: var(--text-body);
}

.error-card {
  color: var(--status-red);
  border-color: var(--status-red-border);
  background-color: var(--status-red-bg);
  padding: var(--space-md) !important;
}
.success-card {
  color: var(--status-green);
  border-color: var(--status-green-border);
  background-color: var(--status-green-bg);
  padding: var(--space-md) !important;
}

/* Modals */
.modal-lg {
  max-width: 800px !important;
}
.ws-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 500px;
  box-shadow: var(--shadow-xl);
  overflow: hidden;
}
.modal-header {
  padding: var(--space-lg) var(--space-xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h3 {
  margin: 0;
  color: var(--text-title);
}
.close-btn {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: var(--text-muted);
}
.modal-tabs {
  display: flex;
  background-color: var(--bg-sidebar);
  border-bottom: 1px solid var(--border-divider);
}
.tab-btn {
  padding: var(--space-md) var(--space-xl);
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  cursor: pointer;
  font-weight: 600;
  color: var(--text-muted);
}
.tab-btn.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}
.modal-body {
  padding: var(--space-xl);
  max-height: 60vh;
  overflow-y: auto;
}
.modal-footer {
  padding: var(--space-md) var(--space-xl);
  border-top: 1px solid var(--border-divider);
  background-color: var(--bg-sidebar);
}

.kiosk-url-box {
  background-color: var(--bg-sidebar);
  padding: var(--space-md);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.device-mapping-row {
  background: var(--bg-sidebar);
  padding: var(--space-md);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}
</style>
