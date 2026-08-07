<template>
  <div class="wh-page">
    <div class="wh-bar">
      <div class="wh-field">
        <label>Từ ngày</label>
        <input type="date" v-model="filters.from" @change="reload" />
      </div>
      <div class="wh-field">
        <label>Đến ngày</label>
        <input type="date" v-model="filters.to" @change="reload" />
      </div>
      <div class="wh-field wh-grow">
        <!-- Ô này KHÔNG gọi server: lọc ngay trên cửa sổ dữ liệu đã tải, hiện kết quả theo từng
             ký tự gõ. Muốn với ra ngoài cửa sổ đó thì có nút riêng bên cạnh. -->
        <label>Tìm nhanh (không cần chờ)</label>
        <input type="text" v-model="search" placeholder="Màu / mã hàng / mã lô / máy / LV…" />
      </div>
      <!-- Đường thoát khi thứ cần tìm nằm NGOÀI cửa sổ đã tải. Luôn hiện khi có chữ trong ô tìm,
           không đợi tới lúc "không thấy gì": lọc ra 2 dòng cũng không có nghĩa là chỉ có 2. -->
      <button v-if="search.trim()" class="wh-btn" @click="searchOnServer" :disabled="loading">
        🔎 Tìm trên toàn bộ lịch sử
      </button>
      <button class="wh-btn ghost" @click="resetFilters" :disabled="loading">Xoá lọc</button>
      <button class="wh-btn ghost" @click="reload" :disabled="loading" title="Tải lại dữ liệu mới nhất">
        ⟳ Làm mới
      </button>
    </div>

    <p v-if="loading" class="wh-msg">Đang tải…</p>
    <p v-else-if="errorMsg" class="wh-msg err">{{ errorMsg }}</p>

    <template v-else>
      <!-- Cửa sổ bị cắt: phải nói ra. Người dùng gõ tìm mà không thấy sẽ tưởng là không có dữ
           liệu, trong khi thực tế nó nằm ngoài số dòng vừa tải. -->
      <p v-if="truncated" class="wh-msg warn">
        ⚠ Chỉ đang xem <strong>{{ allRounds.length }} vòng cân gần nhất</strong> — còn nữa ở phía
        trước. Thu hẹp khoảng ngày, hoặc bấm “Tìm trên toàn bộ lịch sử”.
      </p>
      <p v-else-if="serverSearch" class="wh-msg warn">
        🔎 Kết quả tìm <strong>trên toàn bộ lịch sử</strong> cho “{{ serverSearch }}” —
        <button class="wh-link" @click="resetFilters">quay lại danh sách gần nhất</button>
      </p>

      <p v-if="filtered.length === 0" class="wh-msg">
        <template v-if="search.trim()">
          Không có vòng cân nào khớp “{{ search }}” trong {{ allRounds.length }} vòng đã tải —
          thử nút <strong>🔎 Tìm trên toàn bộ lịch sử</strong> ở trên.
        </template>
        <template v-else>Không có vòng cân nào khớp điều kiện lọc.</template>
      </p>
    </template>

    <table v-if="!loading && !errorMsg && filtered.length > 0" class="wh-table">
      <thead>
        <tr>
          <th class="c-time">Thời điểm cân</th>
          <th>Màu</th>
          <th>Mã hàng</th>
          <th>Máy</th>
          <th>LV</th>
          <th class="c-num">Số dòng</th>
          <th class="c-num">Đạt</th>
          <th class="c-num">Không đạt</th>
          <th class="c-act"></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="job in paged" :key="job.id">
          <tr class="wh-row" :class="{ open: expanded === job.id }" @click="toggle(job.id)">
            <td class="c-time">{{ formatTime(job.completed_at) }}</td>
            <td class="strong">{{ job.batch?.color || '—' }}</td>
            <td>{{ job.batch?.product_code || '—' }}</td>
            <td>{{ job.batch?.machine?.code || '—' }}</td>
            <td>{{ job.batch?.level_code || '—' }}</td>
            <td class="c-num">{{ job.total_items }}</td>
            <td class="c-num ok">{{ job.accepted_count }}</td>
            <td class="c-num" :class="{ bad: job.rejected_count > 0 }">{{ job.rejected_count }}</td>
            <td class="c-act">
              <!-- @click.stop: bấm IN LẠI không được kéo theo gập/mở dòng chi tiết. -->
              <button
                class="wh-print"
                :disabled="reprintingId === job.id"
                title="In lại phiếu cân của vòng này"
                @click.stop="reprint(job)"
              >
                {{ reprintingId === job.id ? '…' : '🖨' }}
              </button>
              <span class="chev">{{ expanded === job.id ? '▲' : '▼' }}</span>
            </td>
          </tr>

          <tr v-if="expanded === job.id" :key="job.id + '-d'" class="wh-detail-row">
            <td colspan="9">
              <table class="wh-detail">
                <thead>
                  <tr>
                    <th class="c-num">#</th>
                    <th>RACK</th>
                    <th>DYE CODE</th>
                    <th class="c-num">WEIGHT (mục tiêu)</th>
                    <th class="c-num">PROCESS (thực cân)</th>
                    <th class="c-num">Lệch</th>
                    <th>Kết quả</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in job.items" :key="item.id">
                    <td class="c-num">{{ item.sequence_no }}</td>
                    <td>{{ item.rack_code || '—' }}</td>
                    <td class="strong">{{ item.material_code }}</td>
                    <td class="c-num">{{ fmt(item.planned_weight) }}</td>
                    <!-- Chưa cân thì để TRỐNG, không hiện 0.00 — 0.00 là một kết quả cân hợp lệ
                         (đĩa rỗng), không được lẫn với "không hề cân". -->
                    <td class="c-num strong">{{ item.actual_weight === null ? '—' : fmt(item.actual_weight) }}</td>
                    <td class="c-num" :class="deviationClass(item)">{{ deviation(item) }}</td>
                    <td>
                      <span class="tag" :class="item.process_status === 'ACCEPTED' ? 'ok' : 'bad'">
                        {{ item.process_status === 'ACCEPTED' ? 'ĐẠT' : 'KHÔNG ĐẠT' }}
                      </span>
                      <span v-if="item.actual_weight === null" class="note">chưa cân</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- Phân trang thuần JS trên dữ liệu đã tải — đổi trang không gọi server, không có trạng thái
         chờ nào cả. -->
    <div v-if="!loading && !errorMsg && filtered.length > 0" class="wh-pager">
      <button class="wh-btn ghost" :disabled="page <= 1" @click="page -= 1">← Trước</button>
      <span>
        Trang {{ page }} / {{ lastPage }} — {{ filtered.length }} vòng cân<template v-if="search"> khớp</template>
      </span>
      <button class="wh-btn ghost" :disabled="page >= lastPage" @click="page += 1">Sau →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
// Lịch sử cân của /weighing-station-v2.
//
// Mỗi dòng là MỘT VÒNG CÂN (một WeighingJob đã COMPLETED), KHÔNG phải một lô. Từ 2026-08-01
// quét lại mã sau khi đã SAVE sẽ mở một vòng cân mới thay vì tái dùng vòng cũ, nên một lô có
// thể xuất hiện nhiều lần ở đây — đó là chủ ý: các lần cân lại chính là thứ cần nhìn thấy nhất
// khi đối soát, gom theo lô sẽ giấu mất chúng.

import { ref, reactive, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { inPhieuTrongTrang } from '../utils/slipPrint';

/**
 * Tải MỘT cửa sổ dữ liệu rồi tìm kiếm/phân trang hoàn toàn tại trình duyệt (2026-08-02).
 *
 * Trước đây mỗi lần bấm Lọc hay đổi trang là một vòng HTTP. Đo trên máy dev (DB nằm ở CS-SERVER):
 * riêng phần DB của endpoint này là ~193ms cho 5 truy vấn, cộng mở kết nối + khởi động Laravel +
 * đường truyền là gần nửa giây cho mỗi thao tác — trong khi toàn bộ dữ liệu chỉ có 16KB. Tải một
 * lần rồi lọc bằng JS thì gõ tới đâu thấy tới đó, không có ô "Đang tải…" nào chen vào.
 *
 * Giới hạn thật thà: cửa sổ có trần (HISTORY_MAX_ROWS phía server). Vượt trần thì màn hình nói rõ
 * và có nút tìm thẳng trên server — tuyệt đối không im lặng cắt bớt rồi để người dùng tưởng là
 * không có dữ liệu.
 */
const PER_PAGE = 20;
const WINDOW_LIMIT = 200;

const allRounds = ref<any[]>([]);
const truncated = ref(false);
/** Khác rỗng = danh sách đang hiện là kết quả tìm TRÊN SERVER, không phải cửa sổ gần nhất. */
const serverSearch = ref('');

const loading = ref(false);
const errorMsg = ref('');
const expanded = ref<string | null>(null);
const reprintingId = ref<string | null>(null);

const filters = reactive({ from: '', to: '' });
/** Ô tìm nhanh — chỉ lọc trong `allRounds`, không bao giờ chạm mạng. */
const search = ref('');
const page = ref(1);

/**
 * Gộp các trường thao tác viên hay gõ vào một chuỗi thường để so khớp.
 *
 * Tính MỘT LẦN lúc dữ liệu về, KHÔNG tính trong computed: gán thêm thuộc tính vào object đang nằm
 * trong ref là ghi vào dữ liệu phản ứng ngay giữa lượt đọc của computed — Vue sẽ coi nguồn vừa
 * đổi và tính lại vòng vòng.
 */
function ganChuoiTim(job: any) {
  job.__hay = [
    job.batch?.color,
    job.batch?.product_code,
    job.batch?.legacy_batch_id,
    job.batch?.machine?.code,
    job.batch?.level_code,
  ].filter(Boolean).join(' ').toLowerCase();
  return job;
}

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return allRounds.value;
  // Nhiều từ = phải khớp TẤT CẢ, để gõ "đỏ VD10" lọc được đúng cả máy lẫn màu.
  const tokens = q.split(/\s+/);
  return allRounds.value.filter((job) => tokens.every((t) => (job.__hay || '').includes(t)));
});

const lastPage = computed(() => Math.max(1, Math.ceil(filtered.value.length / PER_PAGE)));
const paged = computed(() => filtered.value.slice((page.value - 1) * PER_PAGE, page.value * PER_PAGE));

// Lọc lại làm số trang co lại — đang đứng ở trang 5 mà chỉ còn 2 trang thì bảng trống trơn.
watch(filtered, () => {
  if (page.value > lastPage.value) page.value = 1;
});

function fmt(v: any): string {
  if (v === null || v === undefined || v === '') return '—';
  return Number(v).toFixed(2);
}

/** Lệch = thực cân − mục tiêu, có dấu để thấy ngay thiếu hay thừa. */
function deviation(item: any): string {
  if (item.actual_weight === null || item.actual_weight === undefined) return '—';
  const d = Number(item.actual_weight) - Number(item.planned_weight);
  return (d > 0 ? '+' : '') + d.toFixed(2);
}

function deviationClass(item: any): string {
  if (item.actual_weight === null || item.actual_weight === undefined) return '';
  return item.process_status === 'ACCEPTED' ? 'ok' : 'bad';
}

function formatTime(v: string | null): string {
  if (!v) return '—';
  const d = new Date(v);
  return d.toLocaleString('vi-VN', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function toggle(id: string) {
  expanded.value = expanded.value === id ? null : id;
}

/** `q` chỉ được truyền khi người dùng CHỦ ĐỘNG tìm trên toàn bộ lịch sử — xem searchOnServer. */
async function load(q = '') {
  loading.value = true;
  errorMsg.value = '';
  expanded.value = null;
  try {
    const res = await axios.get('/api/weighing-jobs/history', {
      params: {
        limit: WINDOW_LIMIT,
        // Tìm toàn cục thì bỏ qua khoảng ngày, nếu không lại bị chính bộ lọc ngày chặn mất cái
        // bản ghi cũ mà người dùng đang cố tìm.
        from: q ? undefined : (filters.from || undefined),
        to: q ? undefined : (filters.to || undefined),
        q: q || undefined,
      },
    });
    const d = res.data?.data;
    allRounds.value = (d?.rounds || []).map(ganChuoiTim);
    truncated.value = !!d?.truncated;
    serverSearch.value = q;
    page.value = 1;
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Không tải được lịch sử cân.';
    allRounds.value = [];
    truncated.value = false;
  } finally {
    loading.value = false;
  }
}

function reload() {
  load();
}

function searchOnServer() {
  const q = search.value.trim();
  if (q) load(q);
}

function resetFilters() {
  filters.from = '';
  filters.to = '';
  search.value = '';
  load();
}

/**
 * In lại phiếu cân của một vòng đã xong.
 *
 * Phải đi qua server chứ KHÔNG dựng phiếu bằng JS tại chỗ, dù dữ liệu đã có sẵn trên màn hình:
 * CLAUDE.md mục 5 bắt buộc mọi lượt in lại phải để lại Audit Log bất biến (ai in, vòng nào, lúc
 * nào). Dựng ở client thì không có gì để mà ghi.
 *
 * IN QUA IFRAME (`inPhieuTrongTrang`), ĐÚNG NHƯ NÚT PRINT CỦA `/weighing-station-v2` — không mở
 * cửa sổ mới nữa (yêu cầu người dùng 07/08/2026: "tem in lại phải bằng kích cỡ ở trạm cân, là 1
 * thì càng tốt", lấy trạm cân làm chuẩn).
 *
 * Nội dung phiếu vốn đã giống hệt nhau: hai màn gọi CÙNG endpoint `/print-slip`, cùng
 * `WeighingJobController::buildSlipHtml`, cùng khổ 60x40mm. Thứ làm tem ra giấy khác cỡ là VẬT
 * CHỨA lúc in: `SCRIPT_TU_IN` (xem `utils/slipPrint.ts`) chọn cỡ chữ bằng cách ĐO
 * `table.offsetWidth/offsetHeight` ngay trong tài liệu đang chứa phiếu. Cửa sổ popup 780x980px và
 * iframe 200x300mm là hai khung đo khác nhau, nên hai bên chốt ra hai cỡ chữ khác nhau dù chuỗi
 * HTML vào giống từng ký tự. Dùng chung một đường in là hết lệch, và hết cả một lớp lỗi: không còn
 * bị chặn popup, không còn văng khỏi F11.
 */
async function reprint(job: any) {
  if (reprintingId.value) return;
  reprintingId.value = job.id;
  try {
    // KHÔNG gửi workstation_code: phiếu in lại phải mang mã trạm ĐÃ CÂN ra nó, không phải máy
    // văn phòng đang mở màn hình này. Server tự lấy từ chính vòng cân (xem printSlip).
    const res = await axios.post(`/api/weighing-jobs/${job.id}/print-slip`, {});
    inPhieuTrongTrang(res.data?.data?.label_payload || '');
  } catch (err: any) {
    alert(err.response?.data?.message || 'Không in lại được phiếu cân.');
  } finally {
    reprintingId.value = null;
  }
}

onMounted(() => load());
</script>

<style scoped>
.wh-page {
  padding: 16px;
}

.wh-bar {
  display: flex;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.wh-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.wh-grow {
  flex: 1;
  min-width: 220px;
}

.wh-field label {
  font-size: 12px;
  font-weight: 600;
  opacity: 0.75;
}

.wh-field input {
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 6px;
  background: var(--input-bg, #fff);
  color: inherit;
  font-size: 14px;
}

.wh-btn {
  height: 36px;
  padding: 0 16px;
  border: none;
  border-radius: 6px;
  background: #0a5cff;
  color: #fff;
  font-weight: 700;
  cursor: pointer;
}

.wh-btn.ghost {
  background: transparent;
  border: 1px solid var(--border-color, #c9c9c9);
  color: inherit;
}

.wh-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.wh-msg {
  padding: 24px;
  text-align: center;
  opacity: 0.75;
}

.wh-msg.err {
  color: #d40000;
  font-weight: 600;
}

.wh-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
}

.wh-table th,
.wh-table td {
  padding: 9px 10px;
  border-bottom: 1px solid var(--border-color, #e2e2e2);
  text-align: left;
}

.wh-table thead th {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  opacity: 0.7;
}

.wh-row {
  cursor: pointer;
}

.wh-row:hover,
.wh-row.open {
  background: rgba(10, 92, 255, 0.07);
}

.c-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.c-time {
  white-space: nowrap;
}

.c-act {
  width: 72px;
  text-align: right;
  white-space: nowrap;
}

.chev {
  margin-left: 8px;
  opacity: 0.6;
}

.wh-print {
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 5px;
  background: transparent;
  color: inherit;
  font-size: 14px;
  line-height: 1;
  padding: 5px 7px;
  cursor: pointer;
}

.wh-print:hover {
  background: rgba(10, 92, 255, 0.14);
  border-color: #0a5cff;
}

.wh-print:disabled {
  opacity: 0.5;
  cursor: wait;
}

/* Nút trông như một đường dẫn — dùng cho các hành động phụ nằm lẫn trong câu chữ. */
.wh-link {
  border: none;
  background: none;
  padding: 0;
  color: #0a5cff;
  font: inherit;
  font-weight: 700;
  text-decoration: underline;
  cursor: pointer;
}

.wh-msg.warn {
  padding: 10px 12px;
  margin-bottom: 12px;
  text-align: left;
  border-radius: 6px;
  background: rgba(250, 173, 20, 0.14);
  border: 1px solid rgba(250, 173, 20, 0.45);
  opacity: 1;
}

.strong {
  font-weight: 700;
}

.ok {
  color: #14803c;
}

.bad {
  color: #d40000;
  font-weight: 700;
}

.wh-detail-row td {
  padding: 0 0 12px 0;
  background: rgba(0, 0, 0, 0.025);
}

.wh-detail {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.wh-detail th,
.wh-detail td {
  padding: 6px 10px;
  border-bottom: 1px solid var(--border-color, #e8e8e8);
  text-align: left;
}

.wh-detail thead th {
  font-size: 11px;
  text-transform: uppercase;
  opacity: 0.65;
}

.tag {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
}

.tag.ok {
  background: #d6f5e2;
  color: #14803c;
}

.tag.bad {
  background: #ffdcdc;
  color: #d40000;
}

.note {
  margin-left: 6px;
  font-size: 11px;
  opacity: 0.7;
}

@media (prefers-color-scheme: dark) {
  .wh-detail-row td {
    background: rgba(255, 255, 255, 0.04);
  }
  .tag.ok {
    background: rgba(20, 128, 60, 0.25);
    color: #7ee2a8;
  }
  .tag.bad {
    background: rgba(212, 0, 0, 0.25);
    color: #ff9d9d;
  }
  .ok {
    color: #7ee2a8;
  }
  .bad {
    color: #ff9d9d;
  }
}
</style>
