<template>
  <!-- `pageWrapper` là HẰNG SỐ quyết định một lần lúc khởi tạo (xem script). -->
  <component :is="pageWrapper">
  <div class="classic-container" ref="rootRef" :style="rootH ? { minHeight: rootH + 'px' } : undefined">
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>

    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> {{ $t('chemicalCallClassic.loadingLabel') }}
    </div>

    <div v-else class="classic-columns">
      <div v-for="(col, colIdx) in machineColumns" :key="colIdx" class="classic-column">
        <div v-for="machineCode in col" :key="machineCode" class="classic-machine">
          <div class="classic-machine-label">{{ machineCode }}</div>

          <div
            v-for="c in groupedChannels[machineCode]"
            :key="c.channel_id"
            class="classic-row"
          >
            <div class="classic-slot-num">{{ c.channel_number }}</div>

            <button
              class="classic-call-btn"
              :disabled="actionLoading === c.channel_id"
              :title="$t('chemicalCallClassic.callButtonTitle', { code: c.chemical_code, machine: machineCode })"
              @click="callChemical(c)"
            >
              {{ c.chemical_code }}
            </button>

            <div class="classic-status" :class="isChannelRed(c) ? 'status-order' : 'status-done'">
              {{ actionLoading === c.channel_id ? '...' : (isChannelRed(c) ? 'ORDER' : 'DONE') }}
            </div>

            <button
              class="classic-ok-btn"
              :disabled="actionLoading === c.channel_id"
              :title="$t('chemicalCallClassic.okButtonTitle')"
              @click="markDone(c)"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </div>

    <FullscreenButton />
    <NavToggleButton />
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import AppLayout from '../components/AppLayout.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import NavToggleButton from '../components/NavToggleButton.vue';
import { isFullscreen } from '../services/layout';
import { useAuthStore } from '../stores/auth';

// Trang công khai (requiresAuth:false) — App.vue không bọc AppLayout, trang tự bọc lấy khi
// người xem đã đăng nhập để vẫn có menu điều hướng (hiện sẵn, ẩn/hiện bằng nút 3 gạch — xem
// NavToggleButton).
const isLoggedIn = useAuthStore().isAuthenticated;
const pageWrapper = isLoggedIn ? AppLayout : 'div';
const previousIsFullscreen = isFullscreen.value;

// Bảng gọi hóa chất "cổ điển" — dựng lại ĐÚNG giao diện UserForm CHEM_ORDER của
// "1.báo phát AC XƯỞNG -193.xlsm" (2 cột máy, mỗi thùng 1 hàng: số thùng + nút gọi
// (caption = mã hóa chất) + ô trạng thái đỏ "ORDER"/xanh "DONE" + nút "OK" riêng —
// KHÔNG gộp thành 1 nút toggle như /chemical-call). Bấm nút gọi = HandleButton
// btn_vdXXX_chemN (đặt ORDER); bấm OK = HandleButton btn_vdXXX_okN (đặt DONE).
// Dùng chung đúng API/dữ liệu với /chemical-call — chỉ khác cách trình bày và tách
// hành động gọi/xác nhận thành 2 nút để giống bản gốc thao tác viên đã quen.
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
  current_request: RequestInfo | null;
}

const channelsList = ref<ChemicalChannel[]>([]);
const loading = ref(true);
const actionLoading = ref<number | null>(null);
const errorMsg = ref('');
let pollInterval: any = null;

// Đo khoảng trống dọc còn lại thật (không dùng `100vh`) — trang nằm trong
// `.content-container` của AppLayout (đã có thanh trên + padding riêng) nên `100vh` luôn
// dài hơn phần còn lại thật và sinh cuộn thừa. Gán vào `min-height` (không phải `height`)
// để khi nội dung cao hơn khung thì trang vẫn giãn ra và cuộn bình thường thay vì bị cắt.
// Cùng cách làm với ChemicalCallPendingClassic.vue / WeighingStationLarge.vue.
const rootRef = ref<HTMLElement | null>(null);
const rootH = ref<number | null>(null);

function fitRoot() {
  const el = rootRef.value;
  if (!el) return;
  const top = el.getBoundingClientRect().top;
  const parent = el.parentElement;
  const padBottom = parent ? parseFloat(getComputedStyle(parent).paddingBottom) || 0 : 0;
  rootH.value = Math.max(320, Math.floor(window.innerHeight - top - padBottom));
}

const refit = () => requestAnimationFrame(fitRoot);

function machineSortNum(code: string): number {
  const m = /^VD(\d+)$/.exec((code || '').toUpperCase().trim());
  return m ? parseInt(m[1], 10) : Number.MAX_SAFE_INTEGER;
}

const groupedChannels = computed(() => {
  const groups: Record<string, ChemicalChannel[]> = {};
  channelsList.value.forEach(c => {
    if (!groups[c.machine_code]) groups[c.machine_code] = [];
    groups[c.machine_code].push(c);
  });
  Object.keys(groups).forEach(machine => {
    groups[machine].sort((a, b) => a.channel_number - b.channel_number);
  });
  return groups;
});

const sortedMachineCodes = computed(() => {
  return Object.keys(groupedChannels.value).sort((a, b) => {
    const diff = machineSortNum(a) - machineSortNum(b);
    return diff !== 0 ? diff : a.localeCompare(b);
  });
});

// 2 cột trái/phải giống bố cục gốc (VD006-009 trái, VD010-013 phải) — chia đều theo
// SỐ LƯỢNG máy thay vì hard-code danh sách máy, để còn đúng khi thêm/bớt máy sau này.
const machineColumns = computed(() => {
  const codes = sortedMachineCodes.value;
  const half = Math.ceil(codes.length / 2);
  return [codes.slice(0, half), codes.slice(half)];
});

function isChannelRed(channel: ChemicalChannel): boolean {
  return !!channel.current_request && (channel.current_request.status === 'ORDERED' || channel.current_request.status === 'ACKNOWLEDGED');
}

// Route này là requiresAuth:false (xem router/index.ts) nên KHÔNG được gọi các endpoint
// /api/chemical-* thường — chúng nằm sau KioskAuthenticationMiddleware, người chưa đăng
// nhập sẽ nhận 401 và interceptor ở main.ts đá thẳng về trang đăng nhập. Nhóm
// /api/public/... là đúng 4 endpoint đã mở riêng cho 2 màn hình cổ điển này.
const API = '/api/public';

async function fetchChannels() {
  try {
    const res = await axios.get(`${API}/chemical-channels`);
    channelsList.value = res.data;
  } catch (err) {
    console.error('Failed to fetch channels:', err);
    errorMsg.value = 'Không thể kết nối đến máy chủ API để lấy thông tin van đường ống.';
  } finally {
    loading.value = false;
  }
}

// Nút gọi (caption = mã hóa chất) — tương đương btn_vdXXX_chemN: đặt ORDER (đỏ).
async function callChemical(channel: ChemicalChannel) {
  errorMsg.value = '';
  actionLoading.value = channel.channel_id;
  const previousRequest = channel.current_request;
  channel.current_request = { id: '__optimistic__', status: 'ORDERED', requested_at: new Date().toISOString() };

  try {
    if (previousRequest?.id) {
      await axios.patch(`${API}/chemical-call-requests/${previousRequest.id}/reset`);
    }
    const idempotencyKey = `cc-classic-${channel.channel_id}-${Date.now()}`;
    await axios.post(`${API}/chemical-call-requests`, {
      channel_id: channel.channel_id,
      idempotency_key: idempotencyKey
    });
    await fetchChannels();
  } catch (err: any) {
    channel.current_request = previousRequest;
    errorMsg.value = err.response?.data?.message || 'Không thể gọi hóa chất cho thùng này.';
  } finally {
    actionLoading.value = null;
  }
}

// Nút OK — tương đương btn_vdXXX_okN: đặt DONE (xanh), đóng hẳn yêu cầu.
async function markDone(channel: ChemicalChannel) {
  if (!channel.current_request) return;
  errorMsg.value = '';
  actionLoading.value = channel.channel_id;
  const previousRequest = channel.current_request;
  const requestId = previousRequest.id;
  channel.current_request = null;

  try {
    await axios.patch(`${API}/chemical-call-requests/${requestId}/complete`);
    await axios.patch(`${API}/chemical-call-requests/${requestId}/reset`);
    await fetchChannels();
  } catch (err: any) {
    channel.current_request = previousRequest;
    errorMsg.value = err.response?.data?.message || 'Không thể xác nhận xong cho thùng này.';
  } finally {
    actionLoading.value = null;
  }
}

onMounted(async () => {
  // Người đã đăng nhập: hiện sẵn menu điều hướng (yêu cầu 2026-08-05) — trước đây trang tự
  // ẩn sidebar+topbar nên phải bấm nút 3 gạch mới thấy menu. Đặt lại false tường minh vì cờ
  // này dùng chung toàn app, có thể còn đang bật do trang trước đó để lại.
  if (isLoggedIn) isFullscreen.value = false;
  await fetchChannels();

  echo.channel('chemical-channels').listen('.updated', fetchChannels);

  pollInterval = setInterval(() => {
    if (document.hidden) return;
    fetchChannels();
  }, 10000);

  fitRoot();
  // Đo lại một nhịp sau khi trình duyệt vẽ xong: lúc onMounted chạy, AppLayout có thể
  // chưa dựng xong thanh trên nên `getBoundingClientRect().top` còn là số tạm.
  requestAnimationFrame(fitRoot);
  window.addEventListener('resize', refit);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  echo.leaveChannel('chemical-channels');
  window.removeEventListener('resize', refit);
  isFullscreen.value = previousIsFullscreen;
});
</script>

<style scoped>
/*
 * Bảng màu/kích thước lấy TRỰC TIẾP từ dump layout UserForm CHEM_ORDER (COM
 * Designer.Controls: Left/Top/Width/Height/BackColor tính bằng point, xem
 * scratchpad layout_dump.txt) — cố tình KHÔNG dùng biến theme sáng/tối của app
 * (--bg-card, --border-card...) vì form gốc là giao diện Windows cổ điển xám 3D
 * cố định, không đổi theo theme. Đây là yêu cầu "giống hệt form VBA".
 */
.classic-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  /* Căn giữa cả 2 chiều: `align-items` lo trái/phải, `justify-content` lo trên/dưới trong
     khoảng trống dọc thật đo được (xem fitRoot trong <script>). */
  justify-content: center;
  gap: 12px;
  padding: 12px;
  background-color: #f0f0f0;
  font-family: Tahoma, 'MS Sans Serif', sans-serif;
  color: #000;
}

/* Căn giữa và co giãn theo bề rộng màn hình: giữ tỉ lệ layout gốc (2 cột) nhưng
   không khóa cứng theo px như UserForm 522x462 gốc — max-width co theo viewport
   để dùng tốt trên cả tablet nhỏ lẫn màn hình rộng nhà xưởng. */
.classic-columns {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: clamp(16px, 3vw, 48px);
  width: 100%;
  max-width: clamp(640px, 80vw, 1400px);
  margin: 0 auto;
}

@media (max-width: 700px) {
  .classic-columns {
    grid-template-columns: 1fr;
    max-width: 480px;
  }
}

.classic-machine {
  margin-bottom: 10px;
}

/* Nhãn tên máy — BackColor gốc = system Highlight (chỉ số 13, dump layout_dump.txt),
   trên Windows 10/11 mặc định là xanh #0078D7; ForeColor gốc lại là BtnText (đen,
   không đổi sang HighlightText trắng) — giữ đúng datum dù tương phản thấp, vì đây là
   đúng những gì Designer.Controls trả về từ file .xlsm gốc. */
.classic-machine-label {
  padding: clamp(3px, 0.4vw, 8px) clamp(6px, 0.8vw, 14px);
  font-weight: 700;
  font-size: clamp(13px, 1.3vw, 20px);
  background-color: #0078d7;
  color: #000;
}

.classic-row {
  display: grid;
  grid-template-columns: clamp(16px, 1.6vw, 26px) 1fr clamp(52px, 6vw, 84px) clamp(44px, 5vw, 72px);
  align-items: stretch;
  gap: clamp(4px, 0.6vw, 10px);
  padding: 2px 0;
}

.classic-slot-num {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: clamp(11px, 1.1vw, 16px);
  background-color: #b4b4b4;
  color: #000;
}

/* Nút gọi — caption chính là mã công thức (VD "VN62+0554"), giống hệt btn_vdXXX_chemN
   gốc. Bevel nổi 3D chuẩn nút Windows classic (viền sáng trên/trái, tối dưới/phải). */
.classic-call-btn {
  min-height: clamp(40px, 4.2vw, 64px);
  padding: clamp(2px, 0.4vw, 8px) clamp(6px, 0.8vw, 14px);
  font-family: Tahoma, 'MS Sans Serif', sans-serif;
  font-weight: 400;
  font-size: clamp(12px, 1.3vw, 20px);
  background-color: #b4b4b4;
  border-style: solid;
  border-width: 2px;
  border-top-color: #fff;
  border-left-color: #fff;
  border-right-color: #a0a0a0;
  border-bottom-color: #a0a0a0;
  color: #000;
  cursor: pointer;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  box-shadow: 2px 3px 6px rgba(0, 0, 0, 0.45);
  transition: box-shadow 0.08s ease, transform 0.08s ease;
}

.classic-call-btn:disabled {
  color: #6d6d6d;
  cursor: not-allowed;
  box-shadow: none;
}

.classic-call-btn:not(:disabled):hover {
  box-shadow: 3px 4px 8px rgba(0, 0, 0, 0.55);
}

.classic-call-btn:not(:disabled):active {
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
  box-shadow: 1px 1px 2px rgba(0, 0, 0, 0.35);
  transform: translate(1px, 1px);
}

/* Ô trạng thái — textbox chìm (sunken, viền ngược nút nổi), ĐÚNG màu/chữ gốc:
   RGB(255,0,0) "ORDER" / RGB(0,200,0) "DONE" (Module2.loadstatus), chữ đen như
   ForeColor mặc định của textbox (VBA không đổi ForeColor, chỉ đổi BackColor+Text). */
.classic-status {
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: clamp(11px, 1.1vw, 16px);
  border-style: solid;
  border-width: 2px;
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
  color: #000;
}

.status-order {
  background-color: rgb(255, 0, 0);
}

.status-done {
  background-color: rgb(0, 200, 0);
}

.classic-ok-btn {
  min-height: clamp(40px, 4.2vw, 64px);
  font-family: Tahoma, 'MS Sans Serif', sans-serif;
  font-size: clamp(12px, 1.3vw, 20px);
  background-color: #b4b4b4;
  border-style: solid;
  border-width: 2px;
  border-top-color: #fff;
  border-left-color: #fff;
  border-right-color: #a0a0a0;
  border-bottom-color: #a0a0a0;
  color: #000;
  cursor: pointer;
  box-shadow: 2px 3px 6px rgba(0, 0, 0, 0.45);
  transition: box-shadow 0.08s ease, transform 0.08s ease;
}

.classic-ok-btn:disabled {
  color: #6d6d6d;
  cursor: not-allowed;
  box-shadow: none;
}

.classic-ok-btn:not(:disabled):hover {
  box-shadow: 3px 4px 8px rgba(0, 0, 0, 0.55);
}

.classic-ok-btn:not(:disabled):active {
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
  box-shadow: 1px 1px 2px rgba(0, 0, 0, 0.35);
  transform: translate(1px, 1px);
}
</style>
