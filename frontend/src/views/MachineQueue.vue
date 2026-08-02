<template>
  <div class="dashboard-container">
    <main class="main-content">
      <div class="section card-sec">
        <div class="section-header">
          <div>
            <h3>⚡ Hàng chờ Điều phối & Phát lệnh Gửi máy</h3>
            <p class="section-desc">Danh sách mẻ thuốc nhuộm/hóa chất sẵn sàng nạp van tự động. Yêu cầu nhận lệnh (Claim Lock) để phát lệnh.</p>
          </div>
          <button @click="fetchQueue" class="refresh-btn">🔄 Làm mới hàng chờ</button>
        </div>

        <div class="queue-cards-grid">
          <div 
            v-for="item in queue" 
            :key="item.id" 
            :class="['queue-card', isLockedByMe(item) ? 'my-lock' : item.locked_by ? 'other-lock' : 'unlocked']"
          >
            <!-- Card Header -->
            <div class="q-header">
              <span class="q-machine">{{ item.batch?.machine?.code || 'VD-X' }}</span>
              <span class="q-source">{{ item.source_table || 'WAITING' }}</span>
            </div>

            <!-- Card Body -->
            <div class="q-body">
              <div class="q-row">
                <span class="q-label">Mã Lô:</span>
                <span class="q-value bold-text text-glow-blue">{{ item.batch?.legacy_batch_id || 'N/A' }}</span>
              </div>
              <div class="q-row">
                <span class="q-label">Mã Màu:</span>
                <span class="q-value">{{ item.batch?.color || 'N/A' }}</span>
              </div>
              <div class="q-row">
                <span class="q-label">Mã Hàng:</span>
                <span class="q-value">{{ item.batch?.product_code || 'N/A' }}</span>
              </div>
              <div class="q-row" v-if="item.batch?.tank">
                <span class="q-label">Thùng trộn:</span>
                <span class="q-value tank-tag">Thùng {{ item.batch?.tank?.code }}</span>
              </div>
            </div>

            <!-- Lock Info / Timer -->
            <div class="lock-status-bar" v-if="item.locked_by">
              <div class="lock-info" v-if="isLockedByMe(item)">
                <span class="lock-icon">🔒</span>
                <span>Bạn đang giữ lệnh (Còn <strong>{{ getTimerStr(item) }}</strong>)</span>
              </div>
              <div class="lock-info other" v-else>
                <span class="lock-icon">🔒</span>
                <span>Được nhận bởi <strong>{{ item.locked_by_user?.display_name || 'Vận hành viên' }}</strong> 
                  <span v-if="isLockExpired(item)" class="expired-label">(Hết hạn)</span>
                  <span v-else>(Còn {{ getTimerStr(item) }})</span>
                </span>
              </div>
            </div>
            <div class="lock-status-bar unlocked-bar" v-else>
              <span>🔓 Lệnh đang trống - Sẵn sàng tiếp nhận</span>
            </div>

            <!-- Card Actions -->
            <div class="q-actions">
              <!-- Case 1: Unlocked -->
              <button 
                v-if="!item.locked_by" 
                @click="claimLock(item.id)" 
                class="action-btn claim-btn"
              >
                📥 Nhận lệnh (Claim Lock)
              </button>

              <!-- Case 2: Locked by current user -->
              <template v-else-if="isLockedByMe(item)">
                <button 
                  @click="releaseLock(item.id)" 
                  class="action-btn release-btn"
                >
                  🔓 Trả lại lệnh
                </button>
                <button 
                  @click="sendToMachine(item)" 
                  class="action-btn send-btn"
                >
                  ⚡ Gửi máy nhuộm (Send)
                </button>
              </template>

              <!-- Case 3: Locked by another user -->
              <template v-else>
                <button 
                  v-if="isLockExpired(item)" 
                  @click="claimLock(item.id)" 
                  class="action-btn override-btn"
                >
                  ⚔️ Chiếm quyền nhận lệnh (Override)
                </button>
                <button 
                  v-else-if="isAdminOrShiftLeader" 
                  @click="releaseLock(item.id)" 
                  class="action-btn force-release-btn"
                >
                  🔓 Giải phóng bắt buộc (Force)
                </button>
                <button 
                  v-else 
                  disabled 
                  class="action-btn disabled-btn"
                >
                  🔒 Lệnh đang bị khóa
                </button>
              </template>
            </div>
          </div>

          <div v-if="queue.length === 0" class="empty-queue">
            🎉 Hàng chờ điều phối trống. Tất cả các mẻ đã được gửi máy nhuộm thành công!
          </div>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import axios from 'axios';

const authStore = useAuthStore();

const queue = ref<any[]>([]);
let timerInterval: any = null;

// Determine if user has managerial role to override locks
const isAdminOrShiftLeader = ref(false);

const fetchQueue = async () => {
  try {
    const token = authStore.token;
    const response = await axios.get('/api/machine-dispatches', {
      headers: { Authorization: `Bearer ${token}` }
    });
    // Add client-side timestamp properties to easily track countdown
    queue.value = response.data.map((item: any) => {
      return {
        ...item,
        client_fetch_time: Date.now()
      };
    });
  } catch (error) {
    console.error('Error fetching dispatch queue:', error);
  }
};

const isLockedByMe = (item: any) => {
  return item.locked_by === authStore.user?.id;
};

// Expiration checking (5 minutes = 300 seconds)
const getLockAgeSeconds = (item: any) => {
  if (!item.locked_at) return 0;
  
  // Calculate difference between current time and locked_at
  const lockTime = new Date(item.locked_at).getTime();
  // LƯU Ý: tính bằng ĐỒNG HỒ MÁY TRẠM đối chiếu với mốc giờ của server. Ở đây từng có một biến
  // `elapsed` dựa trên `item.client_fetch_time` để bù lệch đồng hồ, nhưng chưa bao giờ được dùng
  // vào kết quả trả về — đã gỡ. Máy trạm lệch giờ thì mốc hết hạn khoá 5 phút sẽ lệch theo.
  const totalElapsed = Math.floor((Date.now() - lockTime) / 1000);

  return totalElapsed > 0 ? totalElapsed : 0;
};

const isLockExpired = (item: any) => {
  return getLockAgeSeconds(item) >= 300;
};

const getTimerStr = (item: any) => {
  const age = getLockAgeSeconds(item);
  const remaining = 300 - age;
  if (remaining <= 0) return '0:00';
  
  const mins = Math.floor(remaining / 60);
  const secs = remaining % 60;
  return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
};

const claimLock = async (id: string) => {
  try {
    const token = authStore.token;
    await axios.post(`/api/machine-dispatches/${id}/claim`, {}, {
      headers: { Authorization: `Bearer ${token}` }
    });
    fetchQueue();
  } catch (error: any) {
    alert(error.response?.data?.message || 'Không thể nhận khóa lệnh.');
  }
};

const releaseLock = async (id: string) => {
  let reason = '';
  // If it's a force release, ask for reason
  const item = queue.value.find(q => q.id === id);
  if (item && !isLockedByMe(item)) {
    reason = prompt('Nhập lý do giải phóng khóa bắt buộc:', 'Quá ca hoặc người dùng quên tắt trạm.') || '';
    if (!reason) return;
  }

  try {
    const token = authStore.token;
    await axios.post(`/api/machine-dispatches/${id}/release`, {
      reason: reason || undefined
    }, {
      headers: { Authorization: `Bearer ${token}` }
    });
    fetchQueue();
  } catch (error: any) {
    alert(error.response?.data?.message || 'Không thể giải phóng khóa.');
  }
};

const sendToMachine = async (item: any) => {
  const confirmSend = confirm(`Bạn có chắc chắn muốn phát lệnh gửi mẻ ${item.batch?.legacy_batch_id} sang máy nhuộm ${item.batch?.machine?.code}?`);
  if (!confirmSend) return;

  try {
    const token = authStore.token;
    const response = await axios.post(`/api/machine-dispatches/${item.id}/send`, {}, {
      headers: { Authorization: `Bearer ${token}` }
    });
    alert(response.data.message);
    fetchQueue();
  } catch (error: any) {
    alert(error.response?.data?.message || 'Lỗi phát lệnh gửi máy.');
  }
};

onMounted(() => {
  fetchQueue();
  
  // Set role status
  const roles = authStore.user?.roles || [];
  isAdminOrShiftLeader.value = roles.includes('ADMIN') || roles.includes('SHIFT_LEADER');
  
  // Start reactive timer update every second
  timerInterval = setInterval(() => {
    // Force Vue reactivity update by reassigning queue array
    queue.value = [...queue.value];
  }, 1000);
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});
</script>

<style scoped>
.refresh-btn {
  background: var(--bg-tag);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  padding: 8px 16px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
}
.refresh-btn:hover {
  background: var(--primary);
  color: #fff;
}

.queue-cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.queue-card {
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 250px;
  position: relative;
  transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.queue-card.my-lock {
  border-color: var(--primary);
  box-shadow: 0 0 16px rgba(108, 92, 231, 0.2);
}

.queue-card.other-lock {
  border-color: rgba(239, 68, 68, 0.4);
  opacity: 0.85;
}

.q-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border-divider);
  padding-bottom: 8px;
  margin-bottom: 12px;
}

.q-machine {
  background: var(--primary);
  color: #fff;
  padding: 4px 10px;
  border-radius: 20px;
  font-weight: 700;
  font-size: 0.9rem;
  box-shadow: 0 0 10px var(--primary-shadow);
}

.q-source {
  font-size: 0.75rem;
  color: var(--text-subtitle);
}

.q-body {
  flex-grow: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 12px;
}

.q-row {
  display: flex;
  justify-content: space-between;
}

.q-label {
  color: var(--text-subtitle);
  font-size: 0.85rem;
}

.q-value {
  color: var(--text-body);
  font-size: 0.9rem;
  font-weight: 500;
}

.lock-status-bar {
  background: rgba(255, 255, 255, 0.05);
  border-radius: 6px;
  padding: 8px;
  font-size: 0.8rem;
  text-align: center;
  margin-bottom: 12px;
  color: var(--text-subtitle);
}

.my-lock .lock-status-bar {
  background: rgba(108, 92, 231, 0.15);
  color: var(--primary-glow);
}

.other-lock .lock-status-bar {
  background: rgba(239, 68, 68, 0.1);
  color: #f87171;
}

.expired-label {
  color: #fca5a5;
  font-weight: 600;
}

.q-actions {
  display: flex;
  gap: 8px;
}

.action-btn {
  flex: 1;
  padding: 8px 12px;
  border-radius: 6px;
  border: none;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s ease;
  text-align: center;
}

.claim-btn {
  background: var(--bg-tag);
  color: var(--text-body);
  border: 1px solid var(--border-card);
}
.claim-btn:hover {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 0 12px var(--primary-shadow);
}

.release-btn {
  background: rgba(156, 163, 175, 0.1);
  color: #d1d5db;
  border: 1px solid rgba(156, 163, 175, 0.2);
}
.release-btn:hover {
  background: rgba(156, 163, 175, 0.25);
}

.send-btn {
  background: #10b981;
  color: #fff;
  box-shadow: 0 0 10px rgba(16, 185, 129, 0.3);
}
.send-btn:hover {
  background: #059669;
  box-shadow: 0 0 15px rgba(16, 185, 129, 0.5);
}

.override-btn {
  background: #f59e0b;
  color: #fff;
  box-shadow: 0 0 10px rgba(245, 158, 11, 0.3);
}
.override-btn:hover {
  background: #d97706;
}

.force-release-btn {
  background: rgba(239, 68, 68, 0.15);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
.force-release-btn:hover {
  background: rgba(239, 68, 68, 0.3);
}

.disabled-btn {
  background: rgba(255, 255, 255, 0.05);
  color: rgba(255, 255, 255, 0.2);
  cursor: not-allowed;
}

.empty-queue {
  grid-column: 1 / -1;
  text-align: center;
  padding: 3rem;
  background: var(--bg-card);
  border: 1px dashed var(--border-card);
  border-radius: 12px;
  color: var(--text-subtitle);
}
</style>
