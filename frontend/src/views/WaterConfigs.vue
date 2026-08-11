<template>
  <div class="dashboard-container">
    <!-- Main Content -->
    <main class="main-content">
      <section class="section">
        <div class="section-header">
          <h3>💧 {{ $t('waterConfigs.title') }}</h3>
          <p class="section-desc">{{ $t('waterConfigs.description') }}</p>
        </div>

        <!-- Matrix/List of configs -->
        <div class="table-container">
          <table class="stats-table">
            <thead>
              <tr>
                <th>{{ $t('waterConfigs.colMachineLine') }}</th>
                <th>{{ $t('waterConfigs.colProcess') }}</th>
                <th class="number-cell">{{ $t('waterConfigs.colCoefficient') }}</th>
                <th class="number-cell">{{ $t('waterConfigs.colLiquorRatio') }}</th>
                <th class="action-cell">{{ $t('waterConfigs.colActions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cfg in configs" :key="cfg.id" class="table-row-hover">
                <td class="bold-text highlight-line">{{ cfg.machine_line }}</td>
                <td>
                  <span class="badge-process">{{ cfg.process_code }}</span>
                </td>
                <td class="number-cell bold-text text-glow">{{ cfg.ratio_coefficient }}</td>
                <td class="number-cell bold-text text-glow">1:{{ cfg.liquor_ratio }}</td>
                <td class="action-cell">
                  <button 
                    :id="'edit-cfg-' + cfg.machine_line + '-' + cfg.process_code"
                    @click="openEditModal(cfg)" 
                    class="edit-action-btn"
                  >
                    ✏️ {{ $t('waterConfigs.configureButton') }}
                  </button>
                </td>
              </tr>
              <tr v-if="configs.length === 0">
                <td colspan="5" class="empty-cell">{{ $t('waterConfigs.emptyRow') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <!-- Edit Modal -->
    <div class="modal-backdrop" v-if="showModal">
      <div class="modal-card">
        <div class="modal-header">
          <h4>💧 {{ $t('waterConfigs.editModalTitle', { line: editingConfig?.machine_line, process: editingConfig?.process_code }) }}</h4>
          <button @click="closeModal" class="close-modal-btn">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="edit-ratio-coeff">{{ $t('waterConfigs.labelRatioCoefficient') }}</label>
            <input 
              id="edit-ratio-coeff"
              v-model.number="editForm.ratio_coefficient" 
              type="number" 
              step="0.0001"
              class="form-input"
            />
          </div>
          <div class="form-group">
            <label for="edit-liquor-ratio">{{ $t('waterConfigs.labelLiquorRatio') }}</label>
            <input 
              id="edit-liquor-ratio"
              v-model.number="editForm.liquor_ratio" 
              type="number" 
              step="0.1"
              class="form-input"
            />
          </div>
        </div>
        <div class="modal-footer">
          <button id="cancel-cfg-modal-btn" @click="closeModal" class="cancel-btn">{{ $t('common.cancel') }}</button>
          <button id="save-cfg-modal-btn" @click="saveConfig" class="submit-btn" :disabled="saving">
            {{ saving ? $t('waterConfigs.saving') : $t('waterConfigs.saveConfig') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const authStore = useAuthStore();

// State
const configs = ref<any[]>([]);
const showModal = ref(false);
const editingConfig = ref<any>(null);
const editForm = ref({ ratio_coefficient: 0.0, liquor_ratio: 0.0 });
const saving = ref(false);

const fetchConfigs = async () => {
  try {
    const token = authStore.token;
    const response = await axios.get('/api/water-configs', {
      headers: { Authorization: `Bearer ${token}` }
    });
    configs.value = response.data;
  } catch (error) {
    console.error('Fetch water configs error:', error);
  }
};

const openEditModal = (cfg: any) => {
  editingConfig.value = cfg;
  editForm.value = {
    ratio_coefficient: cfg.ratio_coefficient,
    liquor_ratio: cfg.liquor_ratio
  };
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  editingConfig.value = null;
};

const saveConfig = async () => {
  if (!editingConfig.value) return;
  saving.value = true;
  try {
    const token = authStore.token;
    await axios.put(`/api/water-configs/${editingConfig.value.id}`, editForm.value, {
      headers: { Authorization: `Bearer ${token}` }
    });
    fetchConfigs();
    closeModal();
  } catch (error) {
    console.error('Save water config error:', error);
  } finally {
    saving.value = false;
  }
};

onMounted(() => {
  fetchConfigs();
});
</script>

<style scoped>
.nav-tabs {
  display: flex;
  gap: 1rem;
  margin: 0 1.5rem;
}
.nav-item {
  color: var(--text-subtitle);
  text-decoration: none;
  font-weight: 500;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  transition: all 0.2s ease;
}
.nav-item:hover {
  color: var(--text-title);
  background: var(--bg-tag);
}
.nav-item.active {
  color: var(--text-title);
  background: var(--primary);
  box-shadow: 0 0 12px var(--primary-shadow);
}
.highlight-line {
  color: hsl(200, 100%, 65%);
}
.badge-process {
  background: hsla(250, 100%, 65%, 0.2);
  color: hsl(250, 100%, 75%);
  border: 1px solid hsla(250, 100%, 65%, 0.4);
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
}
.text-glow {
  text-shadow: 0 0 8px rgba(255, 255, 255, 0.1);
}
.table-row-hover:hover {
  background: hsla(224, 20%, 22%, 0.5);
}
.edit-action-btn {
  background: transparent;
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 4px 10px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.edit-action-btn:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

/* Modal Backing */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
}
.modal-card {
  background: hsl(224, 25%, 16%);
  border: 1px solid var(--border-card);
  border-radius: 12px;
  width: 450px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.5);
  display: flex;
  flex-direction: column;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.2rem;
  border-bottom: 1px solid var(--border-divider);
}
.close-modal-btn {
  background: transparent;
  border: none;
  color: var(--text-subtitle);
  font-size: 1.2rem;
  cursor: pointer;
}
.close-modal-btn:hover {
  color: var(--text-title);
}
.modal-body {
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--border-divider);
  background: hsla(224, 25%, 12%, 0.5);
  border-bottom-left-radius: 12px;
  border-bottom-right-radius: 12px;
}
.cancel-btn {
  background: transparent;
  border: 1px solid var(--border-card);
  color: var(--text-subtitle);
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}
.cancel-btn:hover {
  color: var(--text-title);
  background: var(--bg-tag);
}
</style>
