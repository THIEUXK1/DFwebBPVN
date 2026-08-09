<template>
  <!-- Bản web của sheet "sent" trong workbook DF002 (Mod_load_sentlog_sheet). Bản gốc
       không để bảng này trên UserForm mà đổ ra một sheet riêng — nên tách thành view
       riêng ở đây cũng đúng tinh thần bản gốc. -->
  <div class="vba-scroll">
    <div class="vba-form sent-log">
      <div class="sent-log-head">
        <div class="sent-log-title">
          SENT LOG — đơn đã tích &amp; đã bấm OK ({{ filtered.length }}<span v-if="filtered.length !== rows.length">/{{ rows.length }}</span>)
        </div>
        <div class="sent-log-tools">
          <button class="vba-btn" @click="fetchSentLog">LÀM MỚI</button>
          <router-link to="/print-order-entry" class="vba-btn vba-link">← IN TEM NHẬP ĐƠN</router-link>
        </div>
      </div>

      <!-- Bộ lọc chạy hoàn toàn phía trình duyệt trên tập /machine-dispatches/history đã trả về
           (endpoint giới hạn 100 dòng gần nhất) — không gọi lại API khi đổi lọc. -->
      <div class="filter-bar">
        <label>
          Cửa sổ
          <select v-model.number="windowHours" class="vba-select">
            <option :value="24">24 giờ</option>
            <option :value="48">48 giờ</option>
            <option :value="168">7 ngày</option>
            <option :value="720">30 ngày</option>
            <option :value="0">Tất cả</option>
          </select>
        </label>

        <label>
          Máy
          <select v-model="machineFilter" class="vba-select">
            <option value="">-- Tất cả máy --</option>
            <option v-for="m in machineOptions" :key="m" :value="m">{{ m }}</option>
          </select>
        </label>

        <label>
          Tank
          <select v-model="tankFilter" class="vba-select">
            <option value="">-- Tất cả tank --</option>
            <option v-for="t in tankOptions" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>

        <label>
          CHECK
          <select v-model="checkFilter" class="vba-select">
            <option value="">-- Tất cả --</option>
            <option value="YES">Đã tích ✓</option>
            <option value="NO">Chưa tích —</option>
          </select>
        </label>

        <label>
          Trạng thái in
          <select v-model="printFilter" class="vba-select">
            <option value="">-- Tất cả --</option>
            <option value="PRINTED">Đã in xong</option>
            <option value="PENDING">Đang chờ in</option>
            <option value="FAILED">In lỗi</option>
            <option value="CANCELLED">Đã hủy</option>
            <option value="NONE">Chưa có lệnh in</option>
          </select>
        </label>

        <label>
          Trạm gửi
          <select v-model="stationFilter" class="vba-select">
            <option value="">-- Tất cả trạm --</option>
            <option v-for="s in stationOptions" :key="s" :value="s">{{ s }}</option>
          </select>
        </label>

        <input v-model="search" class="vba-input" placeholder="Lọc lô / màu / mã hàng / máy..." />

        <button class="vba-btn" :disabled="!hasFilter" @click="resetFilters">XÓA LỌC</button>
      </div>

      <table class="sent-tbl">
        <thead>
          <tr>
            <th style="width: 36pt">#</th>
            <th style="width: 78pt">LÔ</th>
            <th style="width: 120pt">COLOR</th>
            <th style="width: 72pt">CODE</th>
            <th style="width: 54pt">MACHINE</th>
            <th style="width: 36pt">TANK</th>
            <th style="width: 36pt">LV</th>
            <th style="width: 48pt">CHECK</th>
            <th style="width: 90pt">IN</th>
            <th style="width: 72pt">TRẠM GỬI</th>
            <th style="width: 120pt">TIME3</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in pageRows" :key="d.id">
            <td>{{ pageStart + i + 1 }}</td>
            <td>{{ d.batch?.legacy_batch_id || '' }}</td>
            <td>{{ d.batch?.color || '' }}</td>
            <td>{{ d.batch?.product_code || '' }}</td>
            <td>{{ d.batch?.machine?.code || '' }}</td>
            <td>{{ d.batch?.tank?.code || '' }}</td>
            <td>{{ d.batch?.level_code || '' }}</td>
            <td :class="d.scale_checked ? 'chk-yes' : 'chk-no'">{{ d.scale_checked ? '✓' : '—' }}</td>
            <td>
              <span class="print-badge" :class="'pb-' + printStatusOf(d)">{{ PRINT_LABEL[printStatusOf(d)] }}</span>
              <span v-if="printCountOf(d) > 1" class="print-count">×{{ printCountOf(d) }}</span>
            </td>
            <td>{{ d.originating_station_code || '' }}</td>
            <td>{{ fmt(confirmTimeOf(d)) }}</td>
          </tr>
          <tr v-if="pageRows.length === 0">
            <td colspan="11" class="sent-empty">
              {{ loading ? 'Đang tải...' : 'Không có đơn nào khớp điều kiện.' }}
            </td>
          </tr>
        </tbody>
      </table>

      <div class="pager">
        <div class="pager-info">
          <template v-if="filtered.length">
            Hiển thị {{ pageStart + 1 }}–{{ pageStart + pageRows.length }} / {{ filtered.length }} đơn
          </template>
          <template v-else>Không có dòng nào</template>
        </div>
        <div class="pager-ctrl">
          <label>
            Mỗi trang
            <select v-model.number="pageSize" class="vba-select">
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="0">Tất cả</option>
            </select>
          </label>
          <button class="vba-btn" :disabled="page <= 1" @click="page = 1">«</button>
          <button class="vba-btn" :disabled="page <= 1" @click="page--">‹</button>
          <button
            v-for="p in pageWindow"
            :key="p"
            class="vba-btn pg-num"
            :class="{ 'pg-active': p === page }"
            @click="page = p"
          >{{ p }}</button>
          <button class="vba-btn" :disabled="page >= totalPages" @click="page++">›</button>
          <button class="vba-btn" :disabled="page >= totalPages" @click="page = totalPages">»</button>
          <span class="pager-total">Trang {{ page }}/{{ totalPages }}</span>
        </div>
      </div>

      <p class="sent-note">
        Bản gốc đọc <code>tbl_sentlog WHERE TIME3 &gt;= DateAdd('h',-48,Now()) ORDER BY TIME3 ASC</code>
        (hàm tên <code>LoadSent_Last24h</code> nhưng SQL thật là 48 giờ). Cột TIME3 ở đây lấy mốc
        print job đầu tiên của đơn — web không ghi cột thời điểm xác nhận riêng.
        Bảng này sắp xếp <strong>mới nhất lên đầu</strong> (ngược bản gốc) cho tiện tra cứu.
        Cột IN lấy trạng thái của lệnh in <strong>mới nhất</strong>; <code>×n</code> là số lần đã in (kể cả in lại).
      </p>
    </div>

    <FullscreenButton variant="vba" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';
import FullscreenButton from '../components/FullscreenButton.vue';

const windowHours = ref(48);
const machineFilter = ref('');
const tankFilter = ref('');
const checkFilter = ref('');
const printFilter = ref('');
const stationFilter = ref('');
const search = ref('');
const loading = ref(false);
const rows = ref<any[]>([]);

const pageSize = ref(50);
const page = ref(1);

// Web không ghi mốc riêng cho lúc xác nhận (VBA có cột TIME3): confirm chỉ đổi queue_state
// và tạo print job. Mốc print job ĐẦU TIÊN chính là lúc bấm OK — các job sau là in lại.
const jobsOf = (d: any): any[] => d.print_jobs ?? d.printJobs ?? [];
const confirmTimeOf = (d: any): string => jobsOf(d)[0]?.created_at ?? d.created_at;
const printCountOf = (d: any): number => jobsOf(d).length;

const PRINT_LABEL: Record<string, string> = {
  PRINTED: 'Đã in',
  PENDING: 'Chờ in',
  FAILED: 'Lỗi',
  CANCELLED: 'Đã hủy',
  NONE: 'Chưa in',
};

// Lấy trạng thái của lệnh in MỚI NHẤT (mảng do API trả về đã sắp xếp cũ → mới): in lại sau khi
// lỗi thì đơn phải hiện là "Đã in", chứ không kẹt ở trạng thái lỗi của lần in hỏng trước đó.
// QUEUED gom chung vào PENDING — cả hai đều là "Agent chưa in xong".
const printStatusOf = (d: any): string => {
  const jobs = jobsOf(d);
  if (jobs.length === 0) return 'NONE';
  const s = String(jobs[jobs.length - 1]?.status ?? '').toUpperCase();
  if (s === 'QUEUED') return 'PENDING';
  return PRINT_LABEL[s] ? s : 'PENDING';
};

const uniqSorted = (vals: any[]) =>
  [...new Set(vals.map(v => String(v ?? '').trim()).filter(Boolean))].sort((a, b) =>
    a.localeCompare(b, 'vi', { numeric: true })
  );

const machineOptions = computed(() => uniqSorted(rows.value.map(d => d.batch?.machine?.code)));
const tankOptions = computed(() => uniqSorted(rows.value.map(d => d.batch?.tank?.code)));
const stationOptions = computed(() => uniqSorted(rows.value.map(d => d.originating_station_code)));

const hasFilter = computed(
  () =>
    windowHours.value !== 48 ||
    !!machineFilter.value ||
    !!tankFilter.value ||
    !!checkFilter.value ||
    !!printFilter.value ||
    !!stationFilter.value ||
    search.value.trim() !== ''
);

const resetFilters = () => {
  windowHours.value = 48;
  machineFilter.value = '';
  tankFilter.value = '';
  checkFilter.value = '';
  printFilter.value = '';
  stationFilter.value = '';
  search.value = '';
};

// MỚI NHẤT LÊN ĐẦU (yêu cầu 2026-08-06) — LỆCH CÓ CHỦ Ý so với bản gốc, vốn là
// "ORDER BY TIME3 ASC" (cũ nhất trước). Đây là màn tra cứu chứ không phải hàng đợi thao tác:
// thứ người ta cần xem gần như luôn là mấy lô vừa gửi xong, để cũ nhất trước thì mỗi lần mở
// phải cuộn xuống tận đáy — mà danh sách còn tự nạp lại mỗi 15 giây nên vị trí cuộn cũng
// không giữ được.
const filtered = computed(() => {
  const cutoff = windowHours.value ? Date.now() - windowHours.value * 3_600_000 : -Infinity;
  const q = search.value.trim().toLowerCase();
  return rows.value
    .filter(d => new Date(confirmTimeOf(d)).getTime() >= cutoff)
    .filter(d => !machineFilter.value || (d.batch?.machine?.code ?? '') === machineFilter.value)
    .filter(d => !tankFilter.value || (d.batch?.tank?.code ?? '') === tankFilter.value)
    .filter(d => !checkFilter.value || (checkFilter.value === 'YES' ? !!d.scale_checked : !d.scale_checked))
    .filter(d => !printFilter.value || printStatusOf(d) === printFilter.value)
    .filter(d => !stationFilter.value || (d.originating_station_code ?? '') === stationFilter.value)
    .filter(d => {
      if (!q) return true;
      const b = d.batch || {};
      return [b.legacy_batch_id, b.color, b.product_code, b.machine?.code, b.tank?.code]
        .some((v: any) => String(v ?? '').toLowerCase().includes(q));
    })
    .sort((a, b) => new Date(confirmTimeOf(b)).getTime() - new Date(confirmTimeOf(a)).getTime());
});

const totalPages = computed(() =>
  pageSize.value === 0 ? 1 : Math.max(1, Math.ceil(filtered.value.length / pageSize.value))
);
const pageStart = computed(() => (pageSize.value === 0 ? 0 : (page.value - 1) * pageSize.value));
const pageRows = computed(() =>
  pageSize.value === 0 ? filtered.value : filtered.value.slice(pageStart.value, pageStart.value + pageSize.value)
);

// Tối đa 7 nút số quanh trang hiện tại — danh sách tự nạp lại mỗi 15 giây nên thanh phân trang
// phải giữ nguyên chiều rộng, không co giãn theo số trang.
const pageWindow = computed(() => {
  const total = totalPages.value;
  const span = Math.min(7, total);
  let start = Math.max(1, page.value - Math.floor(span / 2));
  start = Math.min(start, total - span + 1);
  return Array.from({ length: span }, (_, i) => start + i);
});

watch([windowHours, machineFilter, tankFilter, checkFilter, printFilter, stationFilter, search, pageSize], () => {
  page.value = 1;
});

// Poll 15 giây có thể làm danh sách ngắn lại (đơn rơi khỏi cửa sổ thời gian) khi đang đứng ở
// trang cuối — kẹp lại để không hiện trang trống.
watch(totalPages, tp => {
  if (page.value > tp) page.value = tp;
});

const fmt = (d: string) => (d ? new Date(d).toLocaleString('vi-VN', { hour12: false }) : '');

const fetchSentLog = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/machine-dispatches/history');
    rows.value = Array.isArray(res.data) ? res.data : (res.data.data ?? []);
  } catch (e) {
    console.error('Error fetching sent log:', e);
  } finally {
    loading.value = false;
  }
};

let pollInterval: any = null;

onMounted(() => {
  fetchSentLog();
  echo.channel('production-batches').listen('.updated', fetchSentLog);
  pollInterval = setInterval(fetchSentLog, 15000);
});

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval);
  echo.leaveChannel('production-batches');
});
</script>

<style scoped>
.vba-scroll {
  overflow: auto;
  padding: 8px;
  background-color: #808080;
  min-height: 100%;
}

.vba-form {
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 0 0 0 1px #404040;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
  padding: 8px;
}

.sent-log-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.sent-log-title { font-weight: bold; font-size: 9pt; }

.sent-log-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  background-color: #e4e4e4;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  padding: 6px 8px;
  margin-bottom: 6px;
}

.filter-bar label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  white-space: nowrap;
}

.vba-select, .vba-input {
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #ffffff;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  padding: 2px 4px;
  outline: none;
}

.vba-input { width: 180px; }

.vba-btn {
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  padding: 3px 10px;
  cursor: pointer;
  text-decoration: none;
}

.vba-btn:active:not(:disabled) { border-color: #808080 #ffffff #ffffff #808080; }
.vba-btn:disabled { color: #808080; cursor: default; }
.vba-link { display: inline-block; }

.sent-tbl {
  width: 100%;
  border-collapse: collapse;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #ffffff;
}

.sent-tbl th {
  background-color: #f0f0f0;
  border: 1px solid #808080;
  padding: 2px 4px;
  text-align: left;
  white-space: nowrap;
  position: sticky;
  top: 0;
}

.sent-tbl td {
  border: 1px solid #c0c0c0;
  padding: 2px 4px;
  white-space: nowrap;
}

.sent-tbl tbody tr:nth-child(even) td { background-color: #f7f7f7; }

.chk-yes { color: #008000; font-weight: bold; text-align: center; }
.chk-no { color: #a00000; text-align: center; }

.print-badge {
  display: inline-block;
  border: 1px solid #808080;
  padding: 0 4px;
  font-size: 7.5pt;
}

.pb-PRINTED { background-color: #d6f0d6; color: #005000; }
.pb-PENDING { background-color: #fff2cc; color: #7a5500; }
.pb-FAILED { background-color: #f8d0d0; color: #a00000; font-weight: bold; }
.pb-CANCELLED { background-color: #e0e0e0; color: #505050; }
.pb-NONE { background-color: #ffffff; color: #707070; }

.print-count { margin-left: 4px; color: #404040; }

.sent-empty { text-align: center; color: #606060; padding: 16px 0; }

.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-top: 6px;
}

.pager-info { color: #303030; }

.pager-ctrl {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.pager-ctrl label {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-right: 4px;
}

.pg-num { padding: 3px 7px; }
.pg-active { font-weight: bold; border-color: #808080 #ffffff #ffffff #808080; background-color: #dcdcdc; }

.pager-total { margin-left: 6px; color: #303030; }

.sent-note {
  margin-top: 8px;
  color: #404040;
  font-size: 7.5pt;
  line-height: 1.5;
}

.sent-note code {
  background-color: #ffffff;
  border: 1px solid #c0c0c0;
  padding: 0 3px;
}
</style>
