<template>
  <div class="kiosk-setup-container">
    <div class="setup-card">
      <div class="setup-header">
        <h2>🖥️ {{ $t('workstationKioskSetup.title') }}</h2>
        <p class="text-muted">{{ $t('workstationKioskSetup.description') }}</p>
      </div>

      <div v-if="errorMsg" class="card error-card mb-4">{{ errorMsg }}</div>
      <div v-if="successMsg" class="card success-card mb-4">{{ successMsg }}</div>

      <form @submit.prevent="handleHandshake">
        <div class="form-group mb-3">
          <label>{{ $t('workstationKioskSetup.labelToken') }}</label>
          <input 
            v-model="token" 
            type="text" 
            class="form-input text-center font-mono text-xl" 
            required 
            placeholder="WS-XXXX-XXXX-XXXX"
          />
        </div>

        <div class="row">
          <div class="col form-group mb-3">
            <label>{{ $t('workstationKioskSetup.labelHostname') }}</label>
            <input
              v-model="hostname"
              type="text"
              class="form-input"
              :placeholder="$t('workstationKioskSetup.placeholderHostname')"
            />
          </div>
          <div class="col form-group mb-3">
            <label>{{ $t('workstationKioskSetup.labelMacAddress') }}</label>
            <input
              v-model="macAddress"
              type="text"
              class="form-input"
              :placeholder="$t('workstationKioskSetup.placeholderMacAddress')"
            />
          </div>
        </div>

        <div class="agent-status-panel mb-4">
          <div class="status-indicator">
            <span class="dot" :class="agentConnected ? 'dot-green' : 'dot-grey'"></span>
            <span class="font-sm font-semibold">
              {{ $t('workstationKioskSetup.agentStatusLabel', { status: agentConnected ? $t('workstationKioskSetup.agentConnected') : $t('workstationKioskSetup.agentDisconnected') }) }}
            </span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3 text-lg" :disabled="loading">
          {{ loading ? $t('workstationKioskSetup.activatingLabel') : $t('workstationKioskSetup.activateButton') }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n({ useScope: 'global' });
const router = useRouter();
const token = ref('');
const hostname = ref('');
const macAddress = ref('');
const agentConnected = ref(false);
const loading = ref(false);
const errorMsg = ref('');
const successMsg = ref('');

// Auto-fill fields if we can contact local agent
async function checkLocalAgent() {
  try {
    // Local Agent runs on localhost:5000 or similar.
    // For UAT simulation, we mock the hostname and MAC.
    const res = await axios.get('http://localhost:5000/api/info', { timeout: 1000 });
    if (res.data) {
      hostname.value = res.data.hostname || '';
      macAddress.value = res.data.mac_address || '';
      agentConnected.value = true;
    }
  } catch (e) {
    // Agent not running, fill default client browser metadata
    hostname.value = window.location.hostname || 'ClientPC';
    macAddress.value = 'E4-B9-7A-83-20-CD'; // Fallback / mock MAC address
    agentConnected.value = false;
  }
}

async function handleHandshake() {
  loading.value = false;
  errorMsg.value = '';
  successMsg.value = '';
  loading.value = true;

  try {
    const res = await axios.post('/api/workstations/handshake', {
      token: token.value,
      hostname: hostname.value,
      mac_address: macAddress.value
    });

    if (res.data && res.data.status === 'SUCCESS') {
      const config = res.data.workstation;
      localStorage.setItem('df_workstation_token', token.value);
      localStorage.setItem('df_workstation_config', JSON.stringify(config));
      successMsg.value = t('workstationKioskSetup.registerSuccess', { name: config.name });
      
      // Dispatch custom event to notify AppLayout and router
      window.dispatchEvent(new Event('workstation-registered'));

      setTimeout(() => {
        router.push(config.default_route || '/');
      }, 1500);
    }
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || t('workstationKioskSetup.registerFailed');
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  checkLocalAgent();
  
  // If we already have token, check if user wants to re-register
  const existingToken = localStorage.getItem('df_workstation_token');
  if (existingToken) {
    token.value = existingToken;
  }
});
</script>

<style scoped>
.kiosk-setup-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
  padding: var(--space-xl);
}

.setup-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-xl);
  padding: 40px;
  width: 100%;
  max-width: 600px;
}

.setup-header {
  text-align: center;
  margin-bottom: var(--space-xl);
}

.setup-header h2 {
  color: var(--text-title);
  margin-bottom: var(--space-xs);
}

.agent-status-panel {
  padding: var(--space-md);
  background-color: var(--bg-sidebar);
  border-radius: var(--radius-md);
  border: 1px solid var(--border-divider);
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 10px;
}

.dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.dot-green {
  background-color: var(--status-green);
  box-shadow: 0 0 8px var(--status-green);
}
.dot-grey {
  background-color: var(--text-muted);
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

.font-mono {
  font-family: 'JetBrains Mono', monospace;
}
</style>
