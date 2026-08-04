<template>
  <div class="classic-container" ref="rootRef" :style="rootH ? { height: rootH + 'px' } : undefined">
    <div v-if="errorMsg" class="alert-box alert-error">⚠️ {{ errorMsg }}</div>

    <div v-if="loading" class="card text-center padding-xl text-muted">
      <span class="spinner">⏳</span> Đang tải danh sách đang chờ xử lý...
    </div>

    <div v-else class="classic-queue">
      <div v-for="slotIndex in 4" :key="slotIndex" class="classic-slot">
        <div class="classic-slot-row1">
          <div class="classic-mac-box" :class="{ 'mac-filled': slotChannel(slotIndex) }">
            {{ slotChannel(slotIndex)?.machine_code || '' }}
          </div>
          <button
            class="classic-slot-ok"
            :disabled="!slotChannel(slotIndex) || actionLoading === slotChannel(slotIndex)?.channel_id"
            title="Xác nhận đã cấp xong"
            @click="onOkClick(slotIndex)"
          >
            OK
          </button>
        </div>

        <div class="classic-chem-box">{{ slotChannel(slotIndex)?.chemical_code || '' }}</div>

        <div class="classic-qr-box">
          <ChemicalCallQrImage
            v-if="slotChannel(slotIndex)?.qr_image_url"
            :src="slotChannel(slotIndex)!.qr_image_url!"
            :size="140"
          />
          <ChemicalCallQrThumb
            v-else-if="slotChannel(slotIndex)?.formula_qr_text"
            :text="slotChannel(slotIndex)!.formula_qr_text!"
            :size="140"
          />
        </div>
      </div>

      <div v-if="pendingChannels.length > 4" class="classic-overflow-hint">
        +{{ pendingChannels.length - 4 }} khác đang chờ — xem đầy đủ ở "Đang chờ xử lý"
      </div>
    </div>

    <FullscreenButton />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import ChemicalCallQrThumb from '../components/ChemicalCallQrThumb.vue';
import ChemicalCallQrImage from '../components/ChemicalCallQrImage.vue';
import FullscreenButton from '../components/FullscreenButton.vue';

// Dựng lại ĐÚNG giao diện UserForm CHEM_ORDER của "6.báo phát AC- 151.xlsm" — hàng đợi
// dọc CỐ ĐỊNH 4 ô (txt_mac1..4/btnok1..4/txt_chem1..4/imgQR1..4, xem
// scratchpad pending_dump.txt): mỗi ô là 1 yêu cầu đang chờ, xếp dọc từ trên xuống,
// ô trống thì mac-box trắng/chữ đen (ApplyColor gốc), có dữ liệu thì đỏ/chữ trắng.
// Bấm OK = BtnOK_Click gốc: đóng yêu cầu rồi ô về trống ngay lập tức.
// VBA gốc chỉ hiện tối đa 4 (không cuộn/không hiện phần dư) — thêm dòng gợi ý "+N khác"
// bên dưới để không âm thầm giấu yêu cầu khi hàng đợi thực tế dài hơn 4.
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

// Đo chiều cao còn lại thật (không dùng `100vh`) — trang nằm trong `.content-container`
// của AppLayout (đã có thanh trên + padding riêng), `100vh` sẽ luôn dài hơn phần còn lại
// thật và bắt cuộn mới thấy hết 4 ô. Đo từ mép trên thật của chính phần tử này thì đúng
// trong mọi trường hợp — cùng cách làm với WeighingStationLarge.vue (xem hàm fitRoot ở đó).
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

function isChannelRed(channel: ChemicalChannel): boolean {
  return !!channel.current_request && (channel.current_request.status === 'ORDERED' || channel.current_request.status === 'ACKNOWLEDGED');
}

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

function slotChannel(slotIndex: number): ChemicalChannel | null {
  return pendingChannels.value[slotIndex - 1] || null;
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

// Tương đương BtnOK_Click gốc: rỗng thì bỏ qua (If machine = "" Or chem = "" Then Exit Sub).
function onOkClick(slotIndex: number) {
  const channel = slotChannel(slotIndex);
  if (!channel) return;
  markDone(channel);
}

async function markDone(channel: ChemicalChannel) {
  if (!channel.current_request) return;
  errorMsg.value = '';
  actionLoading.value = channel.channel_id;
  const previousRequest = channel.current_request;
  const requestId = previousRequest.id;
  channel.current_request = null;

  try {
    await axios.patch(`/api/chemical-call-requests/${requestId}/complete`);
    await axios.patch(`/api/chemical-call-requests/${requestId}/reset`);
    await fetchChannels();
  } catch (err: any) {
    channel.current_request = previousRequest;
    errorMsg.value = err.response?.data?.message || 'Không thể xác nhận xong cho thùng này.';
  } finally {
    actionLoading.value = null;
  }
}

onMounted(async () => {
  await fetchChannels();

  echo.channel('chemical-channels').listen('.updated', fetchChannels);

  pollInterval = setInterval(() => {
    if (document.hidden) return;
    fetchChannels();
  }, 10000);

  fitRoot();
  // Đo lại một nhịp nữa sau khi trình duyệt vẽ xong: lúc onMounted chạy, AppLayout có thể
  // chưa dựng xong thanh trên nên `getBoundingClientRect().top` còn là số tạm.
  requestAnimationFrame(fitRoot);
  window.addEventListener('resize', refit);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  echo.leaveChannel('chemical-channels');
  window.removeEventListener('resize', refit);
});
</script>

<style scoped>
/*
 * Màu/kích thước lấy từ dump layout UserForm CHEM_ORDER của "6.báo phát AC- 151.xlsm"
 * (scratchpad pending_dump.txt) — cùng tinh thần giao diện Windows cổ điển như
 * /chemical-call/classic, không dùng biến theme sáng/tối của app.
 */
.classic-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 12px;
  background-color: #f0f0f0;
  font-family: Tahoma, 'MS Sans Serif', sans-serif;
  color: #000;
  /* Chiều cao gán inline bằng px qua `rootH` (xem fitRoot trong <script>) — co giãn đúng
     bằng khoảng trống dọc thật còn lại, không dùng 100vh (luôn dài hơn thật). */
  min-height: 0;
}

.classic-queue {
  display: flex;
  flex-direction: column;
  gap: clamp(6px, 1.4vh, 22px);
  width: 100%;
  max-width: clamp(220px, 24vw, 420px);
  margin: 0 auto;
  flex: 1;
  min-height: 0;
}

.classic-slot {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 6px;
  border: 1px solid #a0a0a0;
  /* Mỗi ô chiếm phần bằng nhau trong chiều cao còn lại — co lại khi màn hình thấp,
     giãn ra khi màn hình cao, thay vì giữ cỡ cố định rồi tràn phải cuộn. */
  flex: 1;
  min-height: 0;
}

.classic-slot-row1 {
  display: grid;
  grid-template-columns: 1fr clamp(42px, 5vw, 68px);
  gap: 4px;
  flex-shrink: 0;
}

/* Ô tên máy — ĐÚNG logic ApplyColor gốc: trống = trắng/chữ đen, có dữ liệu =
   đỏ RGB(255,0,0)/chữ trắng. min-height co theo CẢ bề rộng lẫn bề cao (min(vw,vh)) để
   nhường chỗ cho khung QR khi màn hình thấp. */
.classic-mac-box {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: clamp(24px, min(4vw, 5vh), 54px);
  font-weight: 700;
  font-size: clamp(14px, 1.6vw, 24px);
  background-color: #fff;
  color: #000;
  border-style: solid;
  border-width: 2px;
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
}

.classic-mac-box.mac-filled {
  background-color: rgb(255, 0, 0);
  color: #fff;
}

.classic-slot-ok {
  min-height: clamp(24px, min(4vw, 5vh), 54px);
  font-family: Tahoma, 'MS Sans Serif', sans-serif;
  font-size: clamp(12px, 1.3vw, 20px);
  background-color: #f0f0f0;
  border-style: solid;
  border-width: 2px;
  border-top-color: #fff;
  border-left-color: #fff;
  border-right-color: #a0a0a0;
  border-bottom-color: #a0a0a0;
  color: #000;
  cursor: pointer;
}

.classic-slot-ok:disabled {
  color: #a0a0a0;
  cursor: not-allowed;
}

.classic-slot-ok:not(:disabled):active {
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
}

/* Ô công thức — luôn trắng/chữ đen, VBA gốc không đổi màu ô này. */
.classic-chem-box {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: clamp(18px, min(2.8vw, 3.2vh), 38px);
  font-weight: 700;
  font-size: clamp(12px, 1.3vw, 18px);
  background-color: #fff;
  color: #000;
  border-style: solid;
  border-width: 2px;
  border-top-color: #a0a0a0;
  border-left-color: #a0a0a0;
  border-right-color: #fff;
  border-bottom-color: #fff;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  padding: 0 4px;
  flex-shrink: 0;
}

/* Khung QR — chiếm hết phần cao còn lại trong ô sau khi trừ 2 hàng trên (flex: 1), thay
   vì cỡ cố định — đây là phần lớn nhất nên co giãn nó là chính để đáp ứng "theo chiều dọc". */
.classic-qr-box {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  background-color: #f0f0f0;
  border: 1px solid #a0a0a0;
}

/* ChemicalCallQrImage/ChemicalCallQrThumb tự co theo BỀ RỘNG (`width: var(--qr-size);
   height: auto`) — trong khung dọc này thứ đang bị ép lại là CHIỀU CAO, nên phải lật
   ngược ràng buộc: cao hết khung (100%), rộng tự theo tỉ lệ vuông (aspect-ratio: 1/1 giữ
   nguyên ở component gốc), rồi vẫn chặn max-width để không tràn ngang khi khung hẹp. */
.classic-qr-box :deep(.chem-call-qr-thumb) {
  width: auto;
  height: 100%;
  max-width: 100%;
  max-height: 100%;
}

.classic-overflow-hint {
  text-align: center;
  font-size: clamp(11px, 1.1vw, 15px);
  font-style: italic;
  color: #555;
  flex-shrink: 0;
}
</style>
