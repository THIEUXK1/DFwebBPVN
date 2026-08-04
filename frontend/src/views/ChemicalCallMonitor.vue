<template>
  <div class="chemical-monitor-container">
    <!-- Error alert -->
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>

    <!-- Machine Grid (read-only) -->
    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> Đang tải thông tin van đường ống xưởng nhuộm...
    </div>

    <div v-else class="machine-grid">
      <div
        v-for="machineCode in sortedMachineCodes"
        :key="machineCode"
        class="card machine-card"
      >
        <div class="machine-card-header">
          <span class="machine-name-title">🖥️ Máy {{ machineCode }}</span>
        </div>

        <div class="machine-card-body">
          <div
            v-for="c in groupedChannels[machineCode]"
            :key="c.channel_id"
            class="channel-row"
            :class="[
              isChannelRed(c) ? 'row-ordered' : 'row-done',
              actionLoading === c.channel_id ? 'row-processing' : '',
              actionLoading !== null && actionLoading !== c.channel_id ? 'row-locked' : ''
            ]"
          >
            <div class="channel-number-col">
              <span v-if="isChannelRed(c)" class="alert-dot" aria-hidden="true"></span>
              <span class="channel-number-pill">Thùng {{ c.channel_number }}</span>
            </div>

            <div class="chemical-name-col">
              <span class="chem-formula">{{ c.chemical_code }}</span>
            </div>

            <div class="time-col font-xs text-muted text-right">
              {{ c.current_request ? formatTime(c.current_request.requested_at) : '-' }}
            </div>

            <div class="action-btn-col">
              <!-- QR "Báo phát AC" — ưu tiên ẢNH THẬT (chụp/xuất từ tem in ở xưởng, xem
                   MachineChemicalChannel::qrImageUrl) thay vì dựng lại text rồi sinh QR:
                   đã phát hiện 2 lần dữ liệu đọc từ ảnh bảng giấy sai (quantity, unit_weight),
                   ảnh thật loại bỏ rủi ro này. Text sinh QR chỉ dự phòng cho thùng chưa có
                   ảnh thật tương ứng. Chỉ hiện khi thùng đang "chưa OK". -->
              <ChemicalCallQrImage
                v-if="c.qr_image_url && isChannelRed(c)"
                :src="c.qr_image_url"
              />
              <ChemicalCallQrThumb
                v-else-if="c.formula_qr_text && isChannelRed(c)"
                :text="c.formula_qr_text"
              />

              <!-- Nút toggle: xanh (OK) <-> đỏ (chưa OK), giống trạm thao tác /chemical-call -->
              <button
                @click="toggleChannel(c, $event)"
                class="btn btn-sm py-1 font-semibold toggle-btn"
                :class="isChannelRed(c) ? 'btn-danger' : 'btn-success'"
                :disabled="actionLoading !== null"
              >
                <span v-if="actionLoading === c.channel_id">⏳ Đang xử lý...</span>
                <span v-else>{{ isChannelRed(c) ? '🔴 Bấm khi Xong' : '🟢 OK — Bấm để Gọi' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- z-index 10: dưới ảnh QR phóng to khi rê chuột (.chem-call-qr-thumb:hover = 20) -->
    <FullscreenButton :z-index="10" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import ChemicalCallQrThumb from '../components/ChemicalCallQrThumb.vue';
import ChemicalCallQrImage from '../components/ChemicalCallQrImage.vue';
import FullscreenButton from '../components/FullscreenButton.vue';

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

// Số thứ tự máy trích từ mã "VDxxx" — dùng để sắp tăng dần đúng số (không phải so sánh
// chuỗi, vì "VD010" < "VD9" theo chuỗi dù 10 > 9). Máy không khớp định dạng VD rơi
// xuống cuối, xếp theo tên để vẫn có thứ tự ổn định.
function machineSortNum(code: string): number {
  const m = /^VD(\d+)$/.exec((code || '').toUpperCase().trim());
  return m ? parseInt(m[1], 10) : Number.MAX_SAFE_INTEGER;
}

const groupedChannels = computed(() => {
  const groups: Record<string, ChemicalChannel[]> = {};
  channelsList.value.forEach(c => {
    if (!groups[c.machine_code]) {
      groups[c.machine_code] = [];
    }
    groups[c.machine_code].push(c);
  });

  Object.keys(groups).forEach(machine => {
    groups[machine].sort((a, b) => a.channel_number - b.channel_number);
  });

  return groups;
});

// Danh sách máy theo thứ tự tăng dần (VD001 -> VD018...) để hiển thị thẻ máy trên màn
// hình theo đúng thứ tự vật lý, thay vì thứ tự ngẫu nhiên theo dữ liệu trả về từ API.
const sortedMachineCodes = computed(() => {
  return Object.keys(groupedChannels.value).sort((a, b) => {
    const diff = machineSortNum(a) - machineSortNum(b);
    return diff !== 0 ? diff : a.localeCompare(b);
  });
});

function isChannelRed(channel: ChemicalChannel): boolean {
  return !!channel.current_request && (channel.current_request.status === 'ORDERED' || channel.current_request.status === 'ACKNOWLEDGED');
}

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

// Toggle 1 nút duy nhất: Xanh -> bấm = Gọi hóa chất (chuyển Đỏ). Đỏ -> bấm = báo Xong
// (Hoàn thành + đóng yêu cầu luôn trong 1 lần bấm, chuyển thẳng lại Xanh). Giống hệt
// logic ở trạm thao tác /chemical-call (xem ChemicalCall.vue::toggleChannel).
//
// Cập nhật LẠC QUAN: đổi màu/nhãn nút NGAY khi bấm, không đợi PATCH/POST xong mới cập
// nhật (trước đây phải chờ 1-2 lượt network mới thấy đổi màu). id/thời gian thật của
// request mới sẽ được đồng bộ lại ngay sau đó qua fetchChannels() chạy nền. Nếu API lỗi
// thì rollback về trạng thái cũ và báo lỗi.
async function toggleChannel(channel: ChemicalChannel, event?: MouseEvent) {
  // Bỏ focus khỏi nút trước khi disable nó — nếu không, trình duyệt tự cuộn
  // ngang khối .machine-card (overflow: hidden) để giữ nút đang focus trong
  // tầm nhìn, làm lưới 3 cột trông như bị xô lệch dù grid-template-columns
  // không hề đổi.
  (event?.currentTarget as HTMLElement | undefined)?.blur();

  errorMsg.value = '';
  actionLoading.value = channel.channel_id;

  const previousRequest = channel.current_request;
  const wasRed = isChannelRed(channel);
  channel.current_request = wasRed
    ? null
    : { id: '__optimistic__', status: 'ORDERED', requested_at: new Date().toISOString() };

  try {
    if (wasRed) {
      const requestId = previousRequest!.id;
      await axios.patch(`/api/chemical-call-requests/${requestId}/complete`);
      await axios.patch(`/api/chemical-call-requests/${requestId}/reset`);
    } else {
      if (previousRequest?.id) {
        await axios.patch(`/api/chemical-call-requests/${previousRequest.id}/reset`);
      }
      const idempotencyKey = `cc-${channel.channel_id}-${Date.now()}`;
      await axios.post('/api/chemical-call-requests', {
        channel_id: channel.channel_id,
        idempotency_key: idempotencyKey
      });
    }
    // Đợi fetchChannels() lấy trạng thái THẬT từ server rồi mới mở khoá thao tác —
    // tránh trường hợp API mutation trả về xong nhưng dữ liệu trên lưới vẫn là dữ
    // liệu lạc quan cũ, người dùng bấm tiếp trước khi trạng thái thật sự cập nhật.
    await fetchChannels();
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

  // Realtime qua Reverb — nghe chung kênh "chemical-channels" với /chemical-call, đổi
  // trạng thái (hoặc cấu hình Báo phát AC) ở trang kia thấy NGAY ở đây, không đợi polling.
  echo.channel('chemical-channels').listen('.updated', refreshAll);

  // Polling giữ làm lưới an toàn (WebSocket rớt kết nối tạm thời), chu kỳ dài hơn.
  // Tab ẩn thì bỏ qua lượt poll — backend xử lý tuần tự (`php artisan serve`, xem session-log
  // mục 38/1113), request thừa của tab nền chen trước thao tác thật của người dùng.
  pollInterval = setInterval(() => {
    if (document.hidden) return;
    refreshAll();
  }, 10000);
  document.addEventListener('visibilitychange', onVisible);
});

function onVisible() {
  if (!document.hidden) refreshAll();
}

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  document.removeEventListener('visibilitychange', onVisible);
  echo.leaveChannel('chemical-channels');
});
</script>

<style scoped>
.chemical-monitor-container {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
  padding: var(--space-md);
}

.machine-grid {
  display: grid;
  /* Luôn cố định 3 cột (yêu cầu 2026-07-25) — trang này không hiện AppLayout/nút Toàn
     màn hình nên bỏ hẳn phụ thuộc vào isFullscreen dùng chung toàn app. */
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-lg);
}

.machine-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-md);
  overflow: hidden;
  overflow-anchor: none;
  display: flex;
  flex-direction: column;
  padding: 0;
}

.machine-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-sm);
  padding: var(--space-sm) var(--space-md);
  background-color: var(--bg-sidebar);
  border-bottom: 1px solid var(--border-divider);
}

.machine-name-title {
  font-weight: 700;
  font-size: 1.1rem;
  color: var(--text-title);
}

.machine-card-body {
  padding: var(--space-xs) 0;
}

.channel-row {
  display: grid;
  /* minmax(0, max-content) thay vì "auto": cho phép cột co lại (ellipsis) khi
     card không đủ chỗ, thay vì để nội dung tràn ra ngoài rồi bị overflow:hidden
     cắt vô hình — chính cái tràn vô hình đó là nguyên nhân khiến trình duyệt
     tự cuộn ngang khi bấm nút (disable/đổi chữ nút), làm lưới trông như xô lệch
     cột dù grid-template-columns của .machine-grid không hề đổi. */
  grid-template-columns: minmax(0, max-content) minmax(0, max-content) minmax(0, 1fr) minmax(0, max-content);
  align-items: center;
  gap: var(--space-md);
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-divider);
  transition: background-color 0.2s ease;
}

.channel-row:last-child {
  border-bottom: none;
}

.channel-number-col,
.chemical-name-col {
  white-space: nowrap;
}

.channel-number-col {
  display: inline-flex;
  align-items: center;
  gap: 8px;
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

.channel-number-pill {
  display: inline-block;
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-title);
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-card-hover);
  padding: 4px 10px;
  border-radius: var(--radius-full);
}

.time-col {
  font-size: 0.95rem;
}

.row-ordered {
  background-color: rgba(239, 68, 68, 0.08);
  border-left: 4px solid #ef4444;
  animation: row-alert-pulse 1.2s ease-in-out infinite;
}

.row-done {
  background-color: rgba(52, 211, 153, 0.08);
  border-left: 4px solid transparent;
}

/* Hàng đang xử lý (vừa bấm) — tối lại để báo hiệu đang thay đổi trạng thái,
   thay thế màu đỏ/xanh nhấp nháy trong lúc chờ API phản hồi. */
.row-processing {
  animation: none;
  background-color: rgba(15, 23, 42, 0.35);
  filter: grayscale(0.4);
}

/* Các hàng còn lại — mờ nhẹ + khoá thao tác khi đang có 1 hàng khác xử lý,
   để người dùng hiểu phải đợi đổi xong mới được bấm hàng khác. */
.row-locked {
  opacity: 0.45;
  pointer-events: none;
  filter: grayscale(0.3);
  transition: opacity 0.2s ease, filter 0.2s ease;
}

@keyframes row-alert-pulse {
  0%, 100% {
    background-color: rgba(239, 68, 68, 0.08);
  }
  50% {
    background-color: rgba(239, 68, 68, 0.28);
  }
}

@media (prefers-reduced-motion: reduce) {
  .row-ordered {
    animation: none;
    background-color: rgba(239, 68, 68, 0.2);
  }
}

.chem-formula {
  display: inline-block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  font-family: 'JetBrains Mono', monospace;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--status-blue);
  background-color: var(--status-blue-bg);
  border: 1px solid var(--status-blue-border);
  padding: 4px 8px;
  border-radius: var(--radius-md);
}

.action-btn-col {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  min-width: 0;
}

.toggle-btn {
  min-height: 34px;
  max-width: 100%;
  padding-left: var(--space-sm);
  padding-right: var(--space-sm);
  font-size: 0.85rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
