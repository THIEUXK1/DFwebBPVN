<template>
  <div class="kiosk-landing-container">
    <div class="loading-card" v-if="loading">
      <div class="spinner"></div>
      <h3>Đang kết nối thiết bị...</h3>
      <p class="text-muted">Đang thiết lập phiên làm việc an toàn (kiosk session)</p>
    </div>

    <div class="error-card" v-else-if="error">
      <div class="error-icon">❌</div>
      <h2>Kết nối thất bại</h2>
      <p class="error-message">{{ error }}</p>
      <div class="actions">
        <router-link to="/login" class="btn btn-secondary">Về trang đăng nhập</router-link>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(true);
const error = ref('');

async function initializeKiosk() {
  const clientCode = route.params.clientCode as string;
  const kioskToken = route.params.kioskToken as string;

  if (!clientCode || !kioskToken) {
    error.value = 'Mã trạm hoặc mã token không hợp lệ trong URL.';
    loading.value = false;
    return;
  }

  try {
    const res = await axios.post('/api/kiosk/session', {
      client_code: clientCode,
      kiosk_token: kioskToken,
      browser_fingerprint: navigator.userAgent
    });

    if (res.data && res.data.status === 'SUCCESS') {
      const { session_token, client } = res.data;
      
      // Store session details
      authStore.setKioskSession(session_token, client);

      // Trigger global event so app layout knows
      window.dispatchEvent(new Event('workstation-registered'));

      // Resolve business capabilities count
      const businessCaps = client.capabilities.filter((c: any) => c.category === 'BUSINESS');

      setTimeout(() => {
        if (businessCaps.length === 1) {
          const cap = businessCaps[0].code;
          router.push(getRouteForCapability(cap));
        } else if (client.default_capability) {
          router.push(getRouteForCapability(client.default_capability));
        } else {
          router.push('/operate/menu');
        }
      }, 800);
    } else {
      error.value = res.data.message || 'Không thể xác thực trạm vận hành.';
      loading.value = false;
    }
  } catch (err: any) {
    error.value = err.response?.data?.message || 'Lỗi kết nối đến máy chủ. Vui lòng kiểm tra mạng.';
    loading.value = false;
  }
}

function getRouteForCapability(cap: string): string {
  const routes: Record<string, string> = {
    // '/order-scan' là trạm "ORDER DESK" khác (quét QR xem/xác nhận đã nhận đơn).
    // Route đúng khớp VBA C3 (tạo+duyệt đơn) là '/production-batches'. Sửa 2026-07-18.
    'PRODUCTION_ORDER': '/production-batches',
    'SMALL_SCALE': '/weighing-station',
    'LARGE_SCALE': '/weighing-station',
    'QR_LABEL_PRINTING': '/print-station',
    'CHEMICAL_CALL': '/chemical-call',
  };
  return routes[cap] || '/operate/menu';
}

onMounted(() => {
  initializeKiosk();
});
</script>

<style scoped>
.kiosk-landing-container {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 80vh;
  padding: 20px;
  background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
}

.loading-card, .error-card {
  background: rgba(30, 41, 59, 0.7);
  backdrop-filter: blur(16px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  padding: 40px;
  width: 100%;
  max-width: 500px;
  text-align: center;
  box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.3), 0 8px 10px -6px rgb(0 0 0 / 0.3);
  color: #f1f5f9;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 4px solid rgba(255, 255, 255, 0.1);
  border-left-color: #3b82f6;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin: 0 auto 20px;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-icon {
  font-size: 48px;
  margin-bottom: 20px;
}

.error-message {
  color: #f87171;
  margin: 15px 0 25px;
  font-size: 1.1rem;
}

.btn-secondary {
  background-color: #475569;
  color: #fff;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  transition: background-color 0.2s;
}

.btn-secondary:hover {
  background-color: #334155;
}
</style>
