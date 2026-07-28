<template>
  <div class="chemical-pending-container">
    <!-- Error alert -->
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>

    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> Đang tải danh sách đang chờ xử lý...
    </div>

    <!-- Gom tất cả thùng "chưa OK" từ mọi máy vào 1 danh sách, xếp theo thời gian gọi lâu
         nhất trước — để người trực không phải rà từng máy ở /chemical-call/monitor mới
         biết có bao nhiêu yêu cầu đang chờ. -->
    <div v-else class="card pending-panel" :class="{ 'has-pending': pendingChannels.length > 0 }">
      <div class="pending-panel-header">
        <span v-if="pendingChannels.length > 0" class="alert-dot" aria-hidden="true"></span>
        <span>{{ pendingChannels.length > 0 ? `🔴 Đang chờ xử lý (${pendingChannels.length})` : '✅ Không có yêu cầu nào đang chờ' }}</span>
      </div>

      <TransitionGroup v-if="pendingChannels.length > 0" name="card" tag="div" class="pending-list">
        <div v-for="c in pendingChannels" :key="c.channel_id" class="pending-card">
          <div class="pending-card-qr">
            <ChemicalCallQrImage
              v-if="c.qr_image_url"
              :src="c.qr_image_url"
              :size="180"
            />
            <ChemicalCallQrThumb
              v-else-if="c.formula_qr_text"
              :text="c.formula_qr_text"
              :size="180"
            />
            <span v-else class="no-qr-hint font-xs text-muted">Chưa có QR</span>
          </div>

          <div class="pending-card-body">
            <span class="channel-number-pill">{{ c.machine_code }} — Thùng {{ c.channel_number }}</span>
            <span class="chem-formula">{{ c.chemical_code }}</span>
            <span class="font-xs text-muted">Gọi lúc {{ c.current_request ? formatTime(c.current_request.requested_at) : '-' }}</span>
            <button
              @click="toggleChannel(c, $event)"
              class="btn btn-sm py-1 font-semibold toggle-btn btn-danger"
              :disabled="actionLoading === c.channel_id"
            >
              🔴 Bấm khi Xong
            </button>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import ChemicalCallQrThumb from '../components/ChemicalCallQrThumb.vue';
import ChemicalCallQrImage from '../components/ChemicalCallQrImage.vue';

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
  qr_image_url: string | null;
  formula_qr_text: string | null;
  current_request: RequestInfo | null;
}

const channelsList = ref<ChemicalChannel[]>([]);
const loading = ref(true);
const errorMsg = ref('');
const actionLoading = ref<number | null>(null);

let pollInterval: any = null;

function isChannelRed(channel: ChemicalChannel): boolean {
  return !!channel.current_request && (channel.current_request.status === 'ORDERED' || channel.current_request.status === 'ACKNOWLEDGED');
}

// Xếp theo thời gian gọi lâu nhất trước — thùng chờ lâu nhất là thùng cần xử lý gấp nhất.
const pendingChannels = computed(() => {
  return channelsList.value
    .filter(isChannelRed)
    .slice()
    .sort((a, b) => {
      const ta = a.current_request?.requested_at ? new Date(a.current_request.requested_at).getTime() : 0;
      const tb = b.current_request?.requested_at ? new Date(b.current_request.requested_at).getTime() : 0;
      return ta - tb;
    });
});

async function fetchChannels() {
  try {
    const res = await axios.get('/api/chemical-channels');
    channelsList.value = res.data;
  } catch (err) {
    console.error('Failed to fetch channels:', err);
    errorMsg.value = 'Không thể kết nối đến máy chủ API để lấy thông tin van đường ống.';
  } finally {
    loading.value = false;
  }
}

async function refreshAll() {
  await fetchChannels();
}

// Toggle 1 nút duy nhất: Đỏ -> bấm = báo Xong (Hoàn thành + đóng yêu cầu luôn trong 1 lần
// bấm, chuyển thẳng lại Xanh). Giống hệt logic ở /chemical-call và /chemical-call/monitor.
//
// Cập nhật LẠC QUAN (optimistic): xóa current_request khỏi state cục bộ NGAY khi bấm,
// không đợi 2 lượt PATCH (complete rồi reset) xong mới cập nhật giao diện — thẻ biến mất
// khỏi lưới ngay lập tức thay vì phải chờ round-trip mạng. Nếu API lỗi thì phục hồi lại
// current_request cũ (rollback) và báo lỗi.
async function toggleChannel(channel: ChemicalChannel, event?: MouseEvent) {
  (event?.currentTarget as HTMLElement | undefined)?.blur();

  errorMsg.value = '';
  actionLoading.value = channel.channel_id;

  const previousRequest = channel.current_request;
  channel.current_request = null;

  try {
    const requestId = previousRequest!.id;
    await axios.patch(`/api/chemical-call-requests/${requestId}/complete`);
    await axios.patch(`/api/chemical-call-requests/${requestId}/reset`);
    fetchChannels();
  } catch (err: any) {
    channel.current_request = previousRequest;
    errorMsg.value = err.response?.data?.message || 'Không thể đổi trạng thái kênh.';
  } finally {
    actionLoading.value = null;
  }
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
  await refreshAll();

  // Realtime qua Reverb — nghe chung kênh "chemical-channels" với /chemical-call và
  // /chemical-call/monitor, đổi trạng thái ở trang nào cũng thấy NGAY ở đây.
  echo.channel('chemical-channels').listen('.updated', refreshAll);

  // Polling giữ làm lưới an toàn (WebSocket rớt kết nối tạm thời), chu kỳ dài hơn.
  pollInterval = setInterval(refreshAll, 10000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  echo.leaveChannel('chemical-channels');
});
</script>

<style scoped>
.chemical-pending-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
}

.pending-panel {
  padding: var(--space-md) var(--space-lg);
  transition: border-color 0.2s ease;
}

.pending-panel.has-pending {
  border: 1px solid rgba(239, 68, 68, 0.35);
}

.pending-panel-header {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--text-title);
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

.pending-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-lg);
  margin-top: var(--space-md);
}

@media (max-width: 768px) {
  .pending-list {
    grid-template-columns: 1fr;
  }
}

.pending-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: var(--space-lg) var(--space-md);
  border-radius: var(--radius-md);
  background-color: rgba(239, 68, 68, 0.08);
  border-left: 4px solid #ef4444;
  text-align: center;
}

.pending-card-qr {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 180px;
}

.no-qr-hint {
  font-style: italic;
}

.pending-card-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.channel-number-pill {
  display: inline-block;
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--text-title);
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card-hover);
  padding: 4px 12px;
  border-radius: var(--radius-full);
}

.chem-formula {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--status-blue);
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  padding: 4px 10px;
  border-radius: var(--radius-md);
}

.toggle-btn {
  min-height: 38px;
  width: 100%;
  padding-left: var(--space-sm);
  padding-right: var(--space-sm);
  font-size: 0.9rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Thẻ vào/ra mượt (vd bấm "Xong" hoặc có yêu cầu mới xuất hiện) thay vì đổi đột ngột;
   .card-move làm các thẻ còn lại trượt êm vào chỗ trống khi 1 thẻ biến mất. */
.card-move,
.card-enter-active,
.card-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.card-enter-from,
.card-leave-to {
  opacity: 0;
  transform: scale(0.92);
}

.card-leave-active {
  z-index: -1;
}

@media (prefers-reduced-motion: reduce) {
  .card-move,
  .card-enter-active,
  .card-leave-active {
    transition: none;
  }
}
</style>
