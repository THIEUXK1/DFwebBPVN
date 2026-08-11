<template>
  <div class="feeding-monitor-container">
    <!-- Top banner -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">{{ currentWorkstation?.type }}</span>
        <h2>{{ currentWorkstation ? currentWorkstation.name : $t('feedingMonitor.notRegistered') }}</h2>
        <p class="text-muted font-sm">{{ $t('feedingMonitor.stationCodeLabel') }} <code>{{ currentWorkstation?.code }}</code> | {{ $t('feedingMonitor.locationLabel') }} {{ currentWorkstation?.location }}</p>
      </div>
      <div class="banner-right">
        <button @click="reloadData" class="btn btn-secondary btn-sm">
          🔄 {{ $t('feedingMonitor.refreshData') }}
        </button>
      </div>
    </div>

    <!-- VIEW 1: TANK RECEIVING (XÁC NHẬN TỚI THÙNG QUA QUÉT KÉP) -->
    <div v-if="currentWorkstation?.type === 'TANK_RECEIVING'" class="tank-receiving-view">
      <div class="card-sec text-center padding-xl">
        <div class="scanner-anim-icon">📥</div>
        <h3>{{ $t('feedingMonitor.dualScanTitle') }}</h3>
        <p class="text-muted mb-4">{{ $t('feedingMonitor.dualScanSubtitle') }}</p>

        <div class="dual-scan-inputs-grid max-w-500">
          <div class="form-group text-left">
            <label>{{ $t('feedingMonitor.step1ScanLabel') }}</label>
            <div class="scan-status-indicator" :class="{ scanned: tankCode }">
              <span class="indicator-icon">{{ tankCode ? '✅' : '⏳' }}</span>
              <span class="indicator-text">{{ tankCode ? $t('feedingMonitor.scannedCode', { code: tankCode }) : $t('feedingMonitor.waitingTankScan') }}</span>
            </div>
          </div>

          <div class="form-group text-left mt-3">
            <label>{{ $t('feedingMonitor.step2ScanLabel') }}</label>
            <div class="scan-status-indicator" :class="{ scanned: labelCode }">
              <span class="indicator-icon">{{ labelCode ? '✅' : '⏳' }}</span>
              <span class="indicator-text">{{ labelCode ? $t('feedingMonitor.scannedCode', { code: labelCode }) : $t('feedingMonitor.waitingLabelScan') }}</span>
            </div>
          </div>

          <div v-if="dualScanError" class="manual-error mt-3">{{ dualScanError }}</div>
          <div v-if="dualScanSuccess" class="confirm-success mt-3">{{ dualScanSuccess }}</div>

          <!-- Actions -->
          <div class="flex-row gap-3 mt-4">
            <button @click="resetDualScan" class="btn btn-secondary flex-1">{{ $t('feedingMonitor.resetButton') }}</button>
            <button
              @click="submitDualScanVerify"
              class="btn btn-primary flex-2"
              :disabled="!tankCode || !labelCode"
            >
              📥 {{ $t('feedingMonitor.confirmToTank') }}
            </button>
          </div>
        </div>

        <!-- Manual entry fallback: scanner hardware unavailable/broken -->
        <div class="manual-entry-widget mt-5">
          <h4>⌨️ {{ $t('feedingMonitor.manualEntryTitle') }}</h4>
          <div class="manual-input-row">
            <select v-model="mockMachineId" class="form-select" @change="selectMachineManually">
              <option value="">{{ $t('feedingMonitor.manualStep1Placeholder') }}</option>
              <option v-for="m in machines" :key="m.id" :value="m.id">{{ $t('feedingMonitor.machinePrefix') }} {{ m.code }}</option>
            </select>
          </div>
          <div class="manual-input-row mt-2">
            <input
              v-model="labelSearchQuery"
              @keyup.enter="searchLabelManually"
              type="text"
              class="form-input manual-input"
              :placeholder="$t('feedingMonitor.manualStep2Placeholder')"
            />
            <button @click="searchLabelManually" class="btn btn-secondary" :disabled="!labelSearchQuery.trim() || labelSearching">
              {{ labelSearching ? $t('feedingMonitor.searchingText') : $t('feedingMonitor.searchButton') }}
            </button>
          </div>
          <div v-if="labelSearchError" class="manual-error mt-2">{{ labelSearchError }}</div>
          <div v-if="labelSearchResults.length" class="manual-results mt-3">
            <p class="text-muted font-sm">{{ $t('feedingMonitor.chooseLabel') }}</p>
            <button v-for="l in labelSearchResults" :key="l.id" class="manual-result-item" @click="selectLabelManually(l)">
              <strong>{{ l.batch?.legacy_batch_id }}</strong> — {{ (l.weight / 1000).toFixed(2) }} kg
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- VIEW 2: MACHINE FEEDING (CẤP VÀO MÁY NHUỘM) -->
    <div v-else-if="currentWorkstation?.type === 'MACHINE_FEEDING'" class="machine-feeding-view">
      <div class="two-col-grid">
        <!-- Col 1: Selected Batch & Readiness interlocks -->
        <div class="section card-sec flex-1">
          <div class="section-header">
            <h3>⚡ {{ $t('feedingMonitor.selectBatchTitle') }}</h3>
            <select v-model="selectedBatchId" @change="handleBatchChange" class="form-select batch-selector">
              <option value="">{{ $t('feedingMonitor.selectBatchPlaceholder') }}</option>
              <option v-for="b in readyBatches" :key="b.id" :value="b.id">
                {{ $t('feedingMonitor.batchOption', { batchId: b.legacy_batch_id, color: b.color, productCode: b.product_code }) }}
              </option>
            </select>
          </div>

          <div v-if="selectedBatch" class="batch-details-box mt-3">
            <h4>{{ $t('feedingMonitor.batchDetailsTitle') }}</h4>
            <div class="details-grid">
              <div><span class="label">{{ $t('feedingMonitor.labelBatchCode') }}</span> <strong class="value text-glow-blue">{{ selectedBatch.legacy_batch_id }}</strong></div>
              <div><span class="label">{{ $t('feedingMonitor.machinePrefix') }}</span> <span class="machine-tag">{{ selectedBatch.machine?.code || 'N/A' }}</span></div>
              <div><span class="label">{{ $t('feedingMonitor.labelColor') }}</span> <span class="value">{{ selectedBatch.color }}</span></div>
              <div><span class="label">{{ $t('feedingMonitor.labelBatchStatus') }}</span> <span class="value">{{ selectedBatch.status }}</span></div>
            </div>
            
            <div class="readiness-checklist mt-4">
              <h5>📋 {{ $t('feedingMonitor.readinessTitle') }}</h5>
              <div class="check-item" :class="{ ok: readiness.batch_weighed }">
                <span class="icon">{{ readiness.batch_weighed ? '✅' : '❌' }}</span>
                <span class="text">{{ $t('feedingMonitor.checkWeighed') }}</span>
              </div>
              <div class="check-item" :class="{ ok: readiness.transport_accepted }">
                <span class="icon">{{ readiness.transport_accepted ? '✅' : '❌' }}</span>
                <span class="text">{{ $t('feedingMonitor.checkArrived') }}</span>
              </div>
            </div>

            <div class="mt-4" v-if="!readiness.ready_to_feed">
              <div class="alert-box alert-danger">
                ⚠️ {{ $t('feedingMonitor.notReadyWarning') }}
              </div>
            </div>
            <div class="mt-3" v-else>
              <button @click="startFeedingProcess" class="btn btn-primary w-100" v-if="!activeOp">
                ⚡ {{ $t('feedingMonitor.startFeedButton') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Col 2: Step-by-step feeding process -->
        <div class="section card-sec flex-1" v-if="activeOp">
          <div class="section-header">
            <h3>🛠️ {{ $t('feedingMonitor.feedProcessTitle') }}</h3>
          </div>

          <div class="steps-flow mt-3">
            <!-- Step 1: Water verification -->
            <div class="step-card" :class="{ done: activeOp.water_verified }">
              <div class="step-header">
                <span class="step-number">1</span>
                <h5>{{ $t('feedingMonitor.step1WaterTitle') }}</h5>
                <span class="step-status">{{ activeOp.water_verified ? $t('feedingMonitor.statusChecked') : $t('feedingMonitor.statusWaiting') }}</span>
              </div>
              <p class="text-muted">{{ $t('feedingMonitor.step1WaterDesc') }}</p>
              <button 
                @click="verifyWater" 
                class="btn btn-small btn-success" 
                v-if="!activeOp.water_verified"
              >
                💧 {{ $t('feedingMonitor.verifyWaterButton') }}
              </button>
            </div>

            <!-- Step 2: Complete / Open Valve -->
            <div class="step-card mt-3" :class="{ locked: !activeOp.water_verified && !activeOp.override_approved }">
              <div class="step-header">
                <span class="step-number">2</span>
                <h5>{{ $t('feedingMonitor.step2ValveTitle') }}</h5>
                <span class="step-status">{{ activeOp.completed_at ? $t('feedingMonitor.statusFed') : $t('feedingMonitor.statusReady') }}</span>
              </div>
              <p class="text-muted font-bold text-success" v-if="activeOp.override_approved">
                ⚠️ {{ $t('feedingMonitor.overrideApprovedMsg', { reason: activeOp.override_reason }) }}
              </p>

              <!-- Final Valve Trigger -->
              <div class="valve-trigger-box mt-3" v-if="!activeOp.completed_at">
                <button 
                  @click="completeFeeding" 
                  class="valve-btn text-glow-green"
                  :disabled="!activeOp.water_verified && !activeOp.override_approved"
                >
                  ⚡ {{ $t('feedingMonitor.openValveButton') }}
                </button>

                <!-- Supervisor Override option if locked -->
                <div v-if="!activeOp.water_verified && !activeOp.override_approved" class="override-panel mt-3">
                  <label class="text-warning font-bold">🔒 {{ $t('feedingMonitor.lockedOverrideLabel') }}</label>
                  <input
                    type="text"
                    v-model="overrideReason"
                    class="form-input mt-2"
                    :placeholder="$t('feedingMonitor.overrideReasonPlaceholder')"
                  />
                  <button 
                    @click="approveOverride" 
                    class="btn btn-warning mt-2 w-100"
                    :disabled="!overrideReason"
                  >
                    🔑 {{ $t('feedingMonitor.approveOverrideButton') }}
                  </button>
                </div>
              </div>
              
              <div class="success-valve-message mt-3" v-else>
                <h4>🎉 {{ $t('feedingMonitor.feedCompleteTitle') }}</h4>
                <p class="text-muted">{{ $t('feedingMonitor.feedCompleteDesc') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- VIEW 3: OTHERS -->
    <div v-else class="card-sec text-center padding-xl">
      <h3>🖥️ {{ $t('feedingMonitor.unauthorizedTitle') }}</h3>
      <p class="text-muted">{{ $t('feedingMonitor.unauthorizedDesc') }}</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { currentWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';

const { t } = useI18n({ useScope: 'global' });

// State
const readyBatches = ref<any[]>([]);
const selectedBatchId = ref('');
const selectedBatch = ref<any | null>(null);
const activeOp = ref<any | null>(null);

const readiness = ref({
  batch_id: '',
  batch_status: '',
  transport_status: '',
  batch_weighed: false,
  transport_accepted: false,
  ready_to_feed: false
});

// Dual scan state for TANK_RECEIVING
const tankCode = ref<string>('');
const labelCode = ref<string>('');
const mockMachineId = ref<string>('');
const machines = ref<any[]>([]);
const dualScanError = ref('');
const dualScanSuccess = ref('');

const labelSearchQuery = ref('');
const labelSearchResults = ref<any[]>([]);
const labelSearchError = ref('');
const labelSearching = ref(false);

// Machine feeding state
const overrideReason = ref('');

onMounted(async () => {
  fetchReadyBatches();
  fetchMachines();
  
  // Register barcode scanner
  scannerService.onScan(handleBarcodeScan);
});

onUnmounted(() => {
  scannerService.offScan(handleBarcodeScan);
});

const reloadData = () => {
  fetchReadyBatches();
  if (selectedBatch.value) {
    checkReadiness();
    fetchActiveOperation();
  }
};

const fetchReadyBatches = async () => {
  try {
    // Only batches that have actually arrived at the tank are eligible for feeding — showing
    // batches still weighing/in transit would let an operator pick something not ready yet.
    const res = await axios.get('/api/production-batches', { params: { status: 'ARRIVED_AT_TANK' } });
    readyBatches.value = res.data.data || res.data || [];
  } catch (error) {
    console.error('Error fetching ready batches:', error);
  }
};

const fetchMachines = async () => {
  try {
    const res = await axios.get('/api/dashboard/overview');
    machines.value = res.data.data?.machines || [];
  } catch (error) {
    console.error(error);
  }
};

const handleBarcodeScan = (token: string) => {
  if (currentWorkstation.value?.type !== 'TANK_RECEIVING') return;
  dualScanError.value = '';

  if (token.startsWith('DF:MACHINE:') || token.startsWith('DF:TANK:')) {
    tankCode.value = token;
    scannerService.playBeep(1800, 100);
  } else if (token.startsWith('DF:MATERIAL_LABEL:')) {
    labelCode.value = token;
    scannerService.playBeep(1800, 100);
  } else {
    dualScanError.value = t('feedingMonitor.scanFormatError');
  }
};

const resetDualScan = () => {
  tankCode.value = '';
  labelCode.value = '';
  dualScanError.value = '';
  dualScanSuccess.value = '';
  mockMachineId.value = '';
  labelSearchQuery.value = '';
  labelSearchResults.value = [];
};

const submitDualScanVerify = async () => {
  if (!tankCode.value || !labelCode.value || !currentWorkstation.value) return;

  dualScanError.value = '';
  dualScanSuccess.value = '';
  try {
    const res = await axios.post('/api/scanner/verify-tank', {
      machine_or_tank_qr: tankCode.value,
      material_label_qr: labelCode.value,
      workstation_code: currentWorkstation.value.code
    });

    if (res.data?.status === 'SUCCESS') {
      scannerService.playBeep(2000, 200);
      const successMessage = res.data.message || t('feedingMonitor.dualScanSuccessDefault');
      resetDualScan();
      dualScanSuccess.value = successMessage;
      fetchReadyBatches();
    }
  } catch (err: any) {
    scannerService.playBeep(600, 450); // error sound
    dualScanError.value = err.response?.data?.message || t('feedingMonitor.dualScanErrorDefault');
  }
};

// Manual fallback for Step 1: pick the physical machine from a short known list.
const selectMachineManually = () => {
  if (!mockMachineId.value) return;
  scannerService.submitManualEntry(`DF:MACHINE:${mockMachineId.value}`);
};

// Manual fallback for Step 2: search the real material label by the batch's human-readable code.
const searchLabelManually = async () => {
  const query = labelSearchQuery.value.trim();
  if (!query) return;
  labelSearching.value = true;
  labelSearchError.value = '';
  labelSearchResults.value = [];
  try {
    const res = await axios.get('/api/material-labels/search', { params: { q: query } });
    const rows = res.data?.data || [];
    if (rows.length === 0) {
      labelSearchError.value = t('feedingMonitor.labelNotFound', { query });
    } else if (rows.length === 1) {
      scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${rows[0].id}`);
    } else {
      labelSearchResults.value = rows;
    }
  } catch (err) {
    labelSearchError.value = t('feedingMonitor.labelSearchFailed');
  } finally {
    labelSearching.value = false;
  }
};

const selectLabelManually = (l: any) => {
  scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${l.id}`);
  labelSearchResults.value = [];
  labelSearchQuery.value = '';
};

const handleBatchChange = async () => {
  activeOp.value = null;
  if (!selectedBatchId.value) {
    selectedBatch.value = null;
    return;
  }
  
  const batch = readyBatches.value.find(b => b.id === selectedBatchId.value);
  if (batch) {
    selectedBatch.value = batch;
    await checkReadiness();
    await fetchActiveOperation();
  }
};

const checkReadiness = async () => {
  if (!selectedBatch.value) return;
  try {
    const res = await axios.get(`/api/feed-operations/readiness/${selectedBatch.value.id}`);
    readiness.value = res.data.data;
  } catch (error) {
    console.error('Error checking readiness:', error);
  }
};

const fetchActiveOperation = async () => {
  if (!selectedBatch.value) return;
  try {
    const res = await axios.get('/api/feed-operations', {
      params: { batch_id: selectedBatch.value.id }
    });
    if (res.data.data && res.data.data.length > 0) {
      activeOp.value = res.data.data[0];
    }
  } catch (error) {
    console.error('Error fetching active op:', error);
  }
};

const startFeedingProcess = async () => {
  if (!selectedBatch.value) return;
  try {
    const res = await axios.post('/api/feed-operations', {
      batch_id: selectedBatch.value.id
    });
    activeOp.value = res.data.data;
    alert(t('feedingMonitor.feedStartedAlert'));
  } catch (error) {
    console.error('Error starting feed process:', error);
  }
};

const verifyWater = async () => {
  if (!activeOp.value) return;
  try {
    const res = await axios.post(`/api/feed-operations/${activeOp.value.id}/verify-water`);
    activeOp.value = res.data.data;
    alert(t('feedingMonitor.waterVerifiedAlert'));
  } catch (error) {
    console.error('Error verifying water:', error);
  }
};

const completeFeeding = async () => {
  if (!activeOp.value) return;
  try {
    const res = await axios.post(`/api/feed-operations/${activeOp.value.id}/complete`);
    activeOp.value = res.data.data;
    alert(t('feedingMonitor.valveOpenedAlert'));
    fetchReadyBatches();
  } catch (error) {
    console.error('Error completing feed:', error);
  }
};

const approveOverride = async () => {
  if (!activeOp.value || !overrideReason.value) return;
  try {
    const res = await axios.post(`/api/feed-operations/${activeOp.value.id}/override`, {
      override_reason: overrideReason.value
    });
    activeOp.value = res.data.data;
    overrideReason.value = '';
    alert(t('feedingMonitor.overrideApprovedAlert'));
  } catch (error) {
    console.error('Error approving override:', error);
  }
};
</script>

<style scoped>
.feeding-monitor-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-2xl);
}

.station-banner {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  border-radius: var(--radius-lg);
  padding: var(--space-xl) var(--space-2xl);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.banner-left h2 {
  font-size: 1.4rem;
  color: var(--text-title);
  margin: 4px 0;
}

.station-badge {
  font-size: 10px;
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  color: var(--status-blue);
  font-weight: 700;
  padding: 1px 6px;
  border-radius: var(--radius-sm);
  width: fit-content;
  text-transform: uppercase;
}

.padding-xl {
  padding: 40px;
}

.scanner-anim-icon {
  font-size: 4rem;
  margin-bottom: 20px;
}

.max-w-500 {
  max-w: 500px;
  margin: 0 auto;
}

.scan-status-indicator {
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  padding: 12px 16px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  margin-top: 6px;
}

.scan-status-indicator.scanned {
  border-color: var(--status-green);
  background-color: rgba(46, 204, 113, 0.05);
}

.manual-entry-widget {
  max-width: 500px;
  margin: 0 auto;
  border-top: 1px solid var(--border-divider);
  padding-top: 24px;
  text-align: left;
}

.manual-input-row {
  display: flex;
  gap: 12px;
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

.details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  background-color: var(--bg-main);
  padding: 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.details-grid .label {
  font-size: 11px;
  color: var(--text-muted);
}

.details-grid .value {
  font-weight: 600;
  color: var(--text-title);
}

.readiness-checklist {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.check-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--text-muted);
}

.check-item.ok {
  color: var(--text-title);
  font-weight: 600;
}

.steps-flow {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.step-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  padding: 16px;
  position: relative;
}

.step-card.done {
  border-color: var(--status-green);
  background-color: rgba(46, 204, 113, 0.02);
}

.step-card.locked {
  opacity: 0.6;
}

.step-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
}

.step-number {
  background-color: var(--bg-main);
  color: var(--text-muted);
  width: 24px;
  height: 24px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
}

.step-card.done .step-number {
  background-color: var(--status-green);
  color: #fff;
}

.step-header h5 {
  margin: 0;
  flex: 1;
}

.step-status {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
}

.valve-btn {
  width: 100%;
  padding: 16px;
  font-size: 1.1rem;
  background-color: var(--status-green);
  border: 1px solid var(--status-green);
  color: #fff;
  border-radius: var(--radius-md);
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
}

.valve-btn:disabled {
  background-color: var(--border-divider);
  border-color: var(--border-divider);
  color: var(--text-muted);
  cursor: not-allowed;
}
</style>
