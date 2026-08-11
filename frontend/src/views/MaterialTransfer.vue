<template>
  <div class="transfer-station-container">
    <!-- Top info banner -->
    <div class="station-banner">
      <div class="banner-left">
        <span class="station-badge">MATERIAL TRANSFER</span>
        <h2>{{ currentWorkstation ? currentWorkstation.name : $t('materialTransfer.notRegisteredStation') }}</h2>
        <p class="text-muted font-sm">{{ $t('materialTransfer.stationCodeLabel') }} <code>{{ currentWorkstation?.code }}</code> | {{ $t('materialTransfer.stationLocationLabel') }} {{ currentWorkstation?.location }}</p>
      </div>
      <div class="banner-right">
        <button @click="fetchTransports" class="btn btn-secondary btn-sm">
          {{ $t('materialTransfer.refreshBtn') }}
        </button>
      </div>
    </div>

    <!-- Just-scanned confirmation: shows destination, matches the operator's one job — "carry this to X" -->
    <div v-if="lastReceived" class="card-sec confirm-banner mb-4">
      <div class="confirm-icon">✅</div>
      <div class="confirm-body">
        <h3>{{ $t('materialTransfer.receivedTitle') }}</h3>
        <div class="confirm-details">
          <span>{{ $t('materialTransfer.receivedBatchLabel') }} <strong>{{ lastReceived.batch?.legacy_batch_id }}</strong></span>
          <span>{{ $t('materialTransfer.receivedMachineLabel') }} <strong class="machine-tag">{{ lastReceived.batch?.machine?.code || 'N/A' }}</strong></span>
          <span v-if="lastReceived.batch?.tank">{{ $t('materialTransfer.receivedTankLabel') }} <strong>{{ lastReceived.batch.tank.code }}</strong></span>
        </div>
      </div>
      <button class="btn btn-secondary btn-sm" @click="lastReceived = null">{{ $t('common.close') }}</button>
    </div>

    <!-- Scanner wait view -->
    <div class="scanning-wait-screen card-sec text-center mb-4">
      <div class="scanner-anim-icon">🚚</div>
      <h3>{{ $t('materialTransfer.scanPrompt') }}</h3>
      <p class="text-muted">{{ $t('materialTransfer.scanHint') }}</p>

      <!-- Manual entry fallback: scanner hardware unavailable/broken -->
      <div class="manual-entry-widget mt-5">
        <h4>{{ $t('materialTransfer.manualEntryTitle') }}</h4>
        <div class="manual-input-row">
          <input
            v-model="manualQuery"
            @keyup.enter="searchManual"
            type="text"
            class="form-input manual-input"
            :placeholder="$t('materialTransfer.manualInputPlaceholder')"
          />
          <button @click="searchManual" class="btn btn-secondary" :disabled="!manualQuery.trim() || searching">
            {{ searching ? $t('materialTransfer.searchingBtn') : $t('materialTransfer.searchBtn') }}
          </button>
        </div>

        <div v-if="manualError" class="manual-error mt-2">{{ manualError }}</div>

        <div v-if="manualResults.length" class="manual-results mt-3">
          <p class="text-muted font-sm">{{ $t('materialTransfer.manualResultsHint', { count: manualResults.length }) }}</p>
          <button
            v-for="l in manualResults"
            :key="l.id"
            class="manual-result-item"
            @click="selectManualResult(l)"
          >
            <strong>{{ l.batch?.legacy_batch_id }}</strong> — {{ (l.weight / 1000).toFixed(2) }} kg
          </button>
        </div>
      </div>
    </div>

    <!-- Active Transports Monitor -->
    <div class="section card-sec">
      <div class="section-header">
        <h3>{{ $t('materialTransfer.sectionTitle') }}</h3>
      </div>
      <div class="table-container mt-3">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('materialTransfer.colBatchId') }}</th>
              <th>{{ $t('materialTransfer.colColorMachine') }}</th>
              <th>{{ $t('common.status') }}</th>
              <th>{{ $t('materialTransfer.colTransitTime') }}</th>
              <th>{{ $t('materialTransfer.colLabelInfo') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transports" :key="t.id">
              <td class="bold-text highlight-code">{{ t.batch?.legacy_batch_id }}</td>
              <td>
                <div>{{ $t('materialTransfer.rowColorLabel') }} {{ t.batch?.color }}</div>
                <span class="machine-tag mt-1 d-inline-block">{{ $t('materialTransfer.rowMachineLabel') }} {{ t.batch?.machine?.code || 'N/A' }}</span>
              </td>
              <td>
                <span :class="['status-badge', t.status.toLowerCase()]">
                  {{ getStatusLabel(t.status) }}
                </span>
              </td>
              <td>
                <div class="sla-time-box">
                  <div>{{ $t('materialTransfer.slaStartedLabel') }} {{ formatTime(t.started_at) }}</div>
                  <div v-if="t.arrived_at" class="text-success">{{ $t('materialTransfer.slaArrivedLabel') }} {{ formatTime(t.arrived_at) }}</div>
                  <div v-if="t.status === 'IN_TRANSIT'" :class="['countdown', getSlaStatusClass(t)]">
                    {{ $t('materialTransfer.slaElapsed', { minutes: getElapsedMinutes(t.started_at), sla: t.sla_minutes }) }}
                  </div>
                </div>
              </td>
              <td>
                <div>{{ $t('materialTransfer.rowWeightLabel') }} {{ (t.material_label?.weight/1000).toFixed(2) }} kg</div>
                <div class="font-sm text-muted">{{ $t('materialTransfer.rowQrLabel') }} <code>DF:MATERIAL_LABEL:{{ t.material_label_id }}</code></div>
              </td>
            </tr>
            <tr v-if="transports.length === 0">
              <td colspan="5" class="text-center text-muted pad-empty-row">
                {{ $t('materialTransfer.emptyMsg') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { currentWorkstation } from '../services/workstation';
import { scannerService } from '../services/scanner';

// Đặt tên `translate` thay vì `t` vì `t` đã là biến lặp của v-for trong template (một dòng
// material_transport) — trùng tên sẽ gây nhầm lẫn khi đọc.
const { t: translate } = useI18n({ useScope: 'global' });

const transports = ref<any[]>([]);
const lastReceived = ref<any>(null);

const manualQuery = ref('');
const manualResults = ref<any[]>([]);
const manualError = ref('');
const searching = ref(false);

onMounted(() => {
  fetchTransports();
  scannerService.onScan(handleBarcodeScan);
});

onUnmounted(() => {
  scannerService.offScan(handleBarcodeScan);
});

const fetchTransports = async () => {
  try {
    const res = await axios.get('/api/material-transports');
    transports.value = res.data.data || res.data || [];
  } catch (err) {
    console.error('Failed to fetch transports:', err);
  }
};

const handleBarcodeScan = async (token: string) => {
  if (!currentWorkstation.value) return;
  if (!token.startsWith('DF:MATERIAL_LABEL:')) {
    manualError.value = translate('materialTransfer.errorInvalidQr');
    return;
  }

  manualError.value = '';
  try {
    const res = await axios.post('/api/scanner/scan', {
      qr_token: token,
      workstation_code: currentWorkstation.value.code
    });

    if (res.data?.status === 'SUCCESS') {
      scannerService.playBeep(2000, 150);
      lastReceived.value = res.data.data;
      manualResults.value = [];
      manualQuery.value = '';
      fetchTransports();
    }
  } catch (err: any) {
    scannerService.playBeep(600, 400); // Error sound
    manualError.value = err.response?.data?.message || translate('materialTransfer.errorScanFailed');
  }
};

const searchManual = async () => {
  const query = manualQuery.value.trim();
  if (!query) return;
  searching.value = true;
  manualError.value = '';
  manualResults.value = [];
  try {
    const res = await axios.get('/api/material-labels/search', { params: { q: query } });
    const rows = res.data?.data || [];
    if (rows.length === 0) {
      manualError.value = translate('materialTransfer.errorNoLabelFound', { query });
    } else if (rows.length === 1) {
      scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${rows[0].id}`);
    } else {
      manualResults.value = rows;
    }
  } catch (err: any) {
    manualError.value = translate('materialTransfer.errorSearchFailed');
  } finally {
    searching.value = false;
  }
};

const selectManualResult = (l: any) => {
  scannerService.submitManualEntry(`DF:MATERIAL_LABEL:${l.id}`);
};

const getStatusLabel = (status: string) => {
  const mapping: Record<string, string> = {
    'READY_FOR_TRANSFER': 'materialTransfer.statusReadyForTransfer',
    'IN_TRANSIT': 'materialTransfer.statusInTransit',
    'ARRIVED_AT_TANK': 'materialTransfer.statusArrivedAtTank',
    'ACCEPTED': 'materialTransfer.statusAccepted',
    'REJECTED': 'materialTransfer.statusRejected'
  };
  return mapping[status] ? translate(mapping[status]) : status;
};

const formatTime = (timeStr: string) => {
  if (!timeStr) return '-';
  const d = new Date(timeStr);
  return d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false });
};

const getElapsedMinutes = (startedAt: string) => {
  if (!startedAt) return 0;
  const elapsedMs = Date.now() - new Date(startedAt).getTime();
  return Math.floor(elapsedMs / 60000);
};

const getSlaStatusClass = (t: any) => {
  const elapsed = getElapsedMinutes(t.started_at);
  if (elapsed >= t.sla_minutes) return 'text-danger bold-text';
  return 'text-muted';
};
</script>

<style scoped>
.transfer-station-container {
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
}

.scanning-wait-screen {
  padding: 60px 40px;
}

.scanner-anim-icon {
  font-size: 4.5rem;
  margin-bottom: 20px;
  animation: pulseScan 2s infinite ease-in-out;
}

@keyframes pulseScan {
  0% { transform: scale(1); opacity: 0.7; }
  50% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); opacity: 0.7; }
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

.confirm-banner {
  display: flex;
  align-items: center;
  gap: var(--space-lg);
  border-left: 4px solid var(--status-green);
}
.confirm-icon {
  font-size: 2rem;
}
.confirm-body {
  flex: 1;
}
.confirm-body h3 {
  margin: 0 0 6px 0;
  color: var(--text-title);
  font-size: 1rem;
}
.confirm-details {
  display: flex;
  gap: var(--space-xl);
  color: var(--text-body);
  font-size: 0.9rem;
}
.machine-tag {
  background-color: var(--status-blue-bg);
  color: var(--status-blue);
  padding: 2px 10px;
  border-radius: var(--radius-full);
  font-size: 0.85rem;
}

.sla-time-box {
  line-height: 1.5;
  font-size: 12px;
}
</style>
