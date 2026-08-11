<template>
  <div class="order-scan-container">
    <!-- Remote Access Mode Banner -->
    <div v-if="isImpersonating" class="remote-banner mb-4" :class="remoteModeClass">
      <div class="banner-content">
        <span class="banner-icon">🌐</span>
        <div class="banner-text">
          <strong>{{ $t('orderScan.remoteBannerPrefix') }}</strong>
          <span v-if="remoteMode === 'VIEW_ONLY'">{{ $t('orderScan.remoteViewOnly') }}</span>
          <span v-else>{{ $t('orderScan.remoteOperate') }}</span>
        </div>
      </div>
      <div class="banner-actions">
        <select v-model="remoteMode" class="form-select font-xs select-mode">
          <option value="VIEW_ONLY">{{ $t('orderScan.remoteModeViewOnlyOption') }}</option>
          <option value="REMOTE_OPERATE">{{ $t('orderScan.remoteModeOperateOption') }}</option>
        </select>
      </div>
    </div>

    <!-- Station Banner -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">ORDER DESK</span>
        <h2>{{ currentWorkstation ? currentWorkstation.name : $t('orderScan.noWorkstationRegistered') }}</h2>
        <p class="text-muted font-sm">{{ $t('orderScan.workstationCodeLabel') }} <code>{{ currentWorkstation?.code }}</code> | {{ $t('orderScan.workstationLocationLabel') }} {{ currentWorkstation?.location }}</p>
      </div>
    </div>

    <!-- Wait / Scan screen -->
    <div v-if="!previewBatch" class="scanning-wait-screen card-sec text-center">
      <div class="scanner-anim-icon">🔳</div>
      <h3>{{ $t('orderScan.scanTitle') }}</h3>
      <p class="text-muted">{{ $t('orderScan.scanDesc') }}</p>

      <!-- Manual entry fallback: scanner hardware unavailable/broken -->
      <div class="manual-entry-widget mt-5">
        <h4>{{ $t('orderScan.manualEntryTitle') }}</h4>
        <div class="manual-input-row">
          <input
            v-model="manualQuery"
            @keyup.enter="searchManual"
            type="text"
            class="form-input manual-input"
            :placeholder="$t('orderScan.manualInputPlaceholder')"
          />
          <button
            @click="searchManual"
            class="btn btn-secondary"
            :disabled="!manualQuery.trim() || searching || (isImpersonating && remoteMode === 'VIEW_ONLY')"
          >
            {{ searching ? $t('orderScan.searching') : $t('orderScan.searchBtn') }}
          </button>
        </div>

        <div v-if="manualError" class="manual-error mt-2">{{ manualError }}</div>

        <div v-if="manualResults.length > 1" class="manual-results mt-3">
          <p class="text-muted font-sm">{{ $t('orderScan.manualResultsFound', { count: manualResults.length }) }}</p>
          <button
            v-for="b in manualResults"
            :key="b.id"
            class="manual-result-item"
            @click="!isImpersonating || remoteMode !== 'VIEW_ONLY' ? selectManualResult(b) : null"
            :disabled="isImpersonating && remoteMode === 'VIEW_ONLY'"
          >
            <strong>{{ b.legacy_batch_id }}</strong>{{ $t('orderScan.resultColorPrefix') }}{{ b.color }}{{ $t('orderScan.resultMachinePrefix') }}{{ b.machine?.code || 'N/A' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Preview + confirm screen -->
    <div v-else class="section card-sec order-preview">
      <div class="meta-badge-row mb-3">
        <span class="badge" :class="alreadyAcknowledged ? 'badge-green' : 'badge-yellow'">
          {{ alreadyAcknowledged ? $t('orderScan.ackBadgeDone') : $t('orderScan.ackBadgePending') }}
        </span>
      </div>

      <h3>{{ $t('orderScan.batchLabel') }} <span class="text-glow-blue">{{ previewBatch.legacy_batch_id }}</span></h3>

      <div class="details-grid mt-4">
        <div><span class="label">{{ $t('orderScan.detailColor') }}</span> <span class="val">{{ previewBatch.color }}</span></div>
        <div><span class="label">{{ $t('orderScan.detailProduct') }}</span> <span class="val">{{ previewBatch.product_code }}</span></div>
        <div><span class="label">{{ $t('orderScan.detailMachine') }}</span> <span class="machine-tag">{{ previewBatch.machine?.code || 'N/A' }}</span></div>
        <div><span class="label">{{ $t('orderScan.detailTank') }}</span> <span class="val">{{ previewBatch.tank?.code || $t('orderScan.tankUnassigned') }}</span></div>
        <div><span class="label">{{ $t('orderScan.detailWaterLevel') }}</span> <span class="val">{{ previewBatch.level_code || $t('orderScan.waterLevelDefault') }}</span></div>
        <div><span class="label">{{ $t('orderScan.detailStatus') }}</span> <span class="val">{{ previewBatch.status }}</span></div>
      </div>

      <div v-if="confirmError" class="manual-error mt-3">{{ confirmError }}</div>
      <div v-if="confirmSuccess" class="confirm-success mt-3">{{ confirmSuccess }}</div>

      <div class="flex-row gap-3 mt-5">
        <button class="btn btn-secondary flex-1" @click="resetScan">{{ $t('orderScan.rescanBtn') }}</button>
        <button
          v-if="!alreadyAcknowledged"
          class="btn btn-primary flex-2"
          @click="confirmReceipt"
          :disabled="confirming || (isImpersonating && remoteMode === 'VIEW_ONLY')"
        >
          {{ confirming ? $t('orderScan.confirming') : $t('orderScan.confirmReceiptBtn') }}
        </button>
      </div>

      <!-- Chuyển nhanh sang trạm chuyên biệt cho lô này — không gộp cân/in tem/gọi hóa
           chất vào màn hình này (giữ đúng nguyên tắc "1 máy = 1 nhiệm vụ" của WS-001),
           chỉ đưa thẳng qua đúng màn hình với lô đã chọn sẵn, khỏi phải quét lại. -->
      <div v-if="alreadyAcknowledged" class="next-step-row mt-4">
        <p class="text-muted font-sm mb-2">{{ $t('orderScan.nextStepLabel') }}</p>
        <div class="flex-row gap-3">
          <button class="btn btn-secondary flex-1" @click="goToWeighing">{{ $t('orderScan.goToWeighingBtn') }}</button>
          <button class="btn btn-secondary flex-1" @click="goToPrint">{{ $t('orderScan.goToPrintBtn') }}</button>
          <button class="btn btn-secondary flex-1" @click="goToChemicalCall">{{ $t('orderScan.goToChemicalCallBtn') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import scannerService from '../services/scanner';
import { currentWorkstation } from '../services/workstation';

const { t } = useI18n({ useScope: 'global' });
const route = useRoute();
const router = useRouter();
const isImpersonating = computed(() => route.query.impersonate === 'true');
const targetWsId = computed(() => route.query.target_ws);
const remoteMode = ref<'VIEW_ONLY' | 'REMOTE_OPERATE'>('VIEW_ONLY');

const remoteModeClass = computed(() => {
  return remoteMode.value === 'VIEW_ONLY' ? 'mode-view-only' : 'mode-remote-operate';
});

function getRequestConfig() {
  const config: { headers: Record<string, string> } = { headers: {} };
  if (isImpersonating.value && remoteMode.value === 'REMOTE_OPERATE') {
    config.headers['X-Remote-Operation'] = 'true';
    config.headers['X-Target-Workstation'] = String(targetWsId.value || '');
    config.headers['X-Remote-Reason'] = 'Admin remote order desk confirmation';
  }
  return config;
}

const previewBatch = ref<any>(null);
const alreadyAcknowledged = ref(false);

const manualQuery = ref('');
const manualResults = ref<any[]>([]);
const manualError = ref('');
const searching = ref(false);

const confirming = ref(false);
const confirmError = ref('');
const confirmSuccess = ref('');

let resetTimer: ReturnType<typeof setTimeout> | null = null;

async function loadOrder(batchId: string) {
  manualError.value = '';
  try {
    const res = await axios.post('/api/scanner/scan', {
      qr_token: `DF:ORDER:${batchId}`,
      workstation_code: currentWorkstation.value?.code,
    }, getRequestConfig());
    if (res.data?.status === 'SUCCESS') {
      previewBatch.value = res.data.data.batch;
      alreadyAcknowledged.value = res.data.data.already_acknowledged;
      manualResults.value = [];
      manualQuery.value = '';
    }
  } catch (err: any) {
    manualError.value = err.response?.data?.message || t('orderScan.errorBatchNotFound');
  }
}

function handleScan(token: string) {
  if (!token.startsWith('DF:ORDER:')) {
    manualError.value = t('orderScan.errorInvalidQr');
    return;
  }
  const batchId = token.split(':')[2];
  loadOrder(batchId);
}

async function searchManual() {
  const query = manualQuery.value.trim();
  if (!query) return;
  searching.value = true;
  manualError.value = '';
  manualResults.value = [];
  try {
    const res = await axios.get('/api/production-batches', {
      params: { search: query, status: 'NEW' },
      ...getRequestConfig()
    });
    const rows = res.data?.data || [];
    if (rows.length === 0) {
      manualError.value = t('orderScan.errorNoMatch', { query });
    } else if (rows.length === 1) {
      scannerService.submitManualEntry(`DF:ORDER:${rows[0].id}`);
    } else {
      manualResults.value = rows;
    }
  } catch (err: any) {
    manualError.value = t('orderScan.errorSearchFailed');
  } finally {
    searching.value = false;
  }
}

function selectManualResult(batch: any) {
  scannerService.submitManualEntry(`DF:ORDER:${batch.id}`);
}

async function confirmReceipt() {
  if (!previewBatch.value || !currentWorkstation.value) return;
  confirming.value = true;
  confirmError.value = '';
  confirmSuccess.value = '';
  try {
    const res = await axios.post('/api/scanner/acknowledge-order', {
      batch_id: previewBatch.value.id,
      workstation_code: currentWorkstation.value.code,
    }, getRequestConfig());
    confirmSuccess.value = res.data.message;
    alreadyAcknowledged.value = true;
    scannerService.playBeep(2200, 200);

    // Kiosk auto-reset: ready for the next order without any manual navigation.
    resetTimer = setTimeout(resetScan, 3000);
  } catch (err: any) {
    confirmError.value = err.response?.data?.message || t('orderScan.errorConfirmFailed');
  } finally {
    confirming.value = false;
  }
}

// Chuyển nhanh sang trạm chuyên biệt cho lô đang xem, mang theo mã lô để trạm đích
// tự nạp lại (giống hệt hành vi quét mã QR thật ở trạm đó), khỏi phải quét lại từ đầu.
function goToWeighing() {
  if (!previewBatch.value) return;
  router.push({ path: '/weighing-station', query: { batch_id: previewBatch.value.id } });
}

function goToPrint() {
  if (!previewBatch.value) return;
  router.push({ path: '/print-station', query: { legacy_batch_id: previewBatch.value.legacy_batch_id } });
}

function goToChemicalCall() {
  router.push({ path: '/chemical-call' });
}

function resetScan() {
  previewBatch.value = null;
  alreadyAcknowledged.value = false;
  manualQuery.value = '';
  manualResults.value = [];
  manualError.value = '';
  confirmError.value = '';
  confirmSuccess.value = '';
  if (resetTimer) {
    clearTimeout(resetTimer);
    resetTimer = null;
  }
}

onMounted(async () => {
  // Resolve Workstation if impersonating
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
  }

  scannerService.onScan(handleScan);
});

onUnmounted(() => {
  scannerService.offScan(handleScan);
  if (resetTimer) clearTimeout(resetTimer);
});
</script>

<style scoped>
.order-scan-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.station-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-lg) var(--space-xl);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
}
.station-badge {
  display: inline-block;
  background-color: var(--primary-bg);
  color: var(--primary-hover);
  font-weight: 700;
  font-size: 0.75rem;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  margin-bottom: 6px;
}

.scanning-wait-screen {
  padding: var(--space-4xl) var(--space-xl);
}
.scanner-anim-icon {
  font-size: 4rem;
  margin-bottom: var(--space-lg);
}

.manual-entry-widget {
  max-width: 480px;
  margin: 0 auto;
  padding: var(--space-xl);
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  text-align: left;
}
.manual-input-row {
  display: flex;
  gap: var(--space-sm);
}
.manual-input {
  flex: 1;
}
.manual-error {
  color: var(--status-red);
  font-size: 0.85rem;
}
.confirm-success {
  color: var(--status-green);
  font-weight: 600;
}
.manual-results {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.manual-result-item {
  text-align: left;
  padding: 10px 14px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-md);
  color: var(--text-body);
  cursor: pointer;
}
.manual-result-item:hover {
  border-color: var(--primary);
  background-color: var(--bg-card-hover);
}

.order-preview {
  max-width: 640px;
  margin: 0 auto;
  width: 100%;
}
.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-md) var(--space-xl);
}
.details-grid .label {
  color: var(--text-muted);
  font-size: 0.85rem;
}
.details-grid .val {
  color: var(--text-body);
  font-weight: 600;
}
.machine-tag {
  background-color: var(--status-blue-bg);
  color: var(--status-blue);
  padding: 2px 10px;
  border-radius: var(--radius-full);
  font-weight: 700;
  font-size: 0.85rem;
}
.text-glow-blue {
  color: var(--primary-hover);
}

.next-step-row {
  border-top: 1px solid var(--border-divider);
  padding-top: var(--space-lg);
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
</style>
