<template>
  <div class="kiosk-menu-container">
    <div class="menu-header">
      <h1 class="client-title">💻 {{ clientName }}</h1>
      <div class="client-meta">
        <span class="badge badge-location">📍 {{ location || $t('kioskMenu.defaultLocation') }}</span>
        <span class="badge badge-status" :class="statusClass">🟢 {{ clientStatus }}</span>
      </div>
      <p class="subtitle text-muted">{{ $t('kioskMenu.subtitle') }}</p>
    </div>

    <div class="menu-grid">
      <div
        v-for="cap in businessCapabilities"
        :key="cap.code"
        class="menu-card"
        @click="selectProgram(cap.code)"
      >
        <div class="card-icon">{{ getIconForCapability(cap.code) }}</div>
        <h3>{{ cap.name }}</h3>
        <p class="description">{{ getDescriptionForCapability(cap.code) }}</p>
        <div class="card-action">{{ $t('kioskMenu.cardActionOpen') }}</div>
      </div>
    </div>

    <div class="devices-footer">
      <h4>{{ $t('kioskMenu.devicesFooterTitle') }}</h4>
      <div class="devices-list">
        <div
          v-for="dev in devices"
          :key="dev.id"
          class="device-badge"
          :class="dev.enabled ? 'dev-online' : 'dev-offline'"
        >
          <span class="dev-icon">{{ getIconForDeviceType(dev.device_type) }}</span>
          <span class="dev-name">{{ dev.code }}</span>
          <span class="dev-role">({{ dev.role === 'PRIMARY_PRINTER' ? $t('kioskMenu.rolePrimaryPrinter') : dev.role === 'PRIMARY_SCALE' ? $t('kioskMenu.rolePrimaryScale') : dev.role }})</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();
const { t } = useI18n({ useScope: 'global' });

const client = computed(() => authStore.kioskClient);
const clientName = computed(() => client.value?.name || t('kioskMenu.defaultClientName'));
const location = computed(() => client.value?.location);
const clientStatus = computed(() => client.value?.status || 'ONLINE');
const statusClass = computed(() => client.value?.status === 'ACTIVE' ? 'badge-active' : 'badge-inactive');

const businessCapabilities = computed(() => {
  if (!client.value?.capabilities) return [];
  return client.value.capabilities.filter((c: any) => c.category === 'BUSINESS' && c.enabled);
});

const devices = computed(() => client.value?.devices || []);

function selectProgram(cap: string) {
  // Store capability selection as default if desired or just navigate
  const routes: Record<string, string> = {
    'PRODUCTION_ORDER': '/order-scan',
    'SMALL_SCALE': '/weighing-station',
    'LARGE_SCALE': '/weighing-station',
    'QR_LABEL_PRINTING': '/print-station',
    'CHEMICAL_CALL': '/feeding-monitor',
  };
  const routePath = routes[cap] || '/';
  router.push(routePath);
}

function getIconForCapability(cap: string): string {
  const icons: Record<string, string> = {
    'CHEMICAL_CALL': '🧪',
    'PRODUCTION_ORDER': '📋',
    'QR_LABEL_PRINTING': '🏷️',
    'SMALL_SCALE': '⚖️',
    'LARGE_SCALE': '🏗️',
  };
  return icons[cap] || '⚙️';
}

function getDescriptionForCapability(cap: string): string {
  const desc: Record<string, string> = {
    'CHEMICAL_CALL': t('kioskMenu.capDescChemicalCall'),
    'PRODUCTION_ORDER': t('kioskMenu.capDescProductionOrder'),
    'QR_LABEL_PRINTING': t('kioskMenu.capDescQrLabelPrinting'),
    'SMALL_SCALE': t('kioskMenu.capDescSmallScale'),
    'LARGE_SCALE': t('kioskMenu.capDescLargeScale'),
  };
  return desc[cap] || t('kioskMenu.capDescDefault');
}

function getIconForDeviceType(type: string): string {
  const icons: Record<string, string> = {
    'PRINTER': '🖨️',
    'SCALE': '⚖️',
    'SCANNER': '🔍',
    'LOCAL_AGENT': '🔌',
  };
  return icons[type] || '💻';
}
</script>

<style scoped>
.kiosk-menu-container {
  max-width: 1200px;
  margin: 40px auto;
  padding: var(--space-xl);
  color: #f1f5f9;
}

.menu-header {
  text-align: center;
  margin-bottom: var(--space-xl);
}

.client-title {
  font-size: 2.5rem;
  color: #f8fafc;
  margin-bottom: var(--space-xs);
  text-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
}

.client-meta {
  display: flex;
  justify-content: center;
  gap: 15px;
  margin-bottom: var(--space-md);
}

.badge {
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 0.9rem;
  font-weight: 600;
}

.badge-location {
  background-color: rgba(59, 130, 246, 0.15);
  border: 1px solid rgba(59, 130, 246, 0.3);
  color: #60a5fa;
}

.badge-status {
  background-color: rgba(34, 197, 94, 0.15);
  border: 1px solid rgba(34, 197, 94, 0.3);
  color: #4ade80;
}

.badge-inactive {
  background-color: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #f87171;
}

.subtitle {
  font-size: 1.1rem;
}

.menu-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: var(--space-xl);
  margin-bottom: 50px;
}

.menu-card {
  background: rgba(30, 41, 59, 0.5);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  text-align: center;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;
  box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

.menu-card:hover {
  transform: translateY(-5px);
  background-color: rgba(30, 41, 59, 0.8);
  box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.2), 0 8px 10px -6px rgb(0 0 0 / 0.2);
  border-color: rgba(59, 130, 246, 0.3);
}

.card-icon {
  font-size: 4rem;
  margin-bottom: var(--space-md);
}

.menu-card h3 {
  font-size: 1.5rem;
  margin-bottom: var(--space-xs);
  color: #f8fafc;
}

.description {
  color: #94a3b8;
  font-size: 0.95rem;
  margin-bottom: var(--space-lg);
  line-height: 1.5;
}

.card-action {
  font-weight: 600;
  color: #3b82f6;
  transition: color 0.2s;
}

.menu-card:hover .card-action {
  color: #60a5fa;
}

.devices-footer {
  background: rgba(15, 23, 42, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.05);
  border-radius: var(--radius-lg);
  padding: var(--space-lg) var(--space-xl);
}

.devices-footer h4 {
  margin-bottom: var(--space-md);
  color: #94a3b8;
  font-size: 1.1rem;
}

.devices-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.device-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.dev-online {
  background-color: rgba(30, 41, 59, 0.4);
  color: #e2e8f0;
}

.dev-offline {
  background-color: rgba(239, 68, 68, 0.05);
  border-color: rgba(239, 68, 68, 0.2);
  color: #f87171;
}

.dev-icon {
  font-size: 1.1rem;
}

.dev-name {
  font-weight: 600;
}

.dev-role {
  color: #64748b;
  font-size: 0.85rem;
}
</style>
