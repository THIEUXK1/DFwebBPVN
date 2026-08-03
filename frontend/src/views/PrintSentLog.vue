<template>
  <!-- Bản web của sheet "sent" trong workbook DF002 (Mod_load_sentlog_sheet). Bản gốc
       không để bảng này trên UserForm mà đổ ra một sheet riêng — nên tách thành view
       riêng ở đây cũng đúng tinh thần bản gốc. -->
  <div class="vba-scroll">
    <div class="vba-form sent-log">
      <div class="sent-log-head">
        <div class="sent-log-title">SENT LOG — đơn đã tích &amp; đã bấm OK ({{ sentLog.length }})</div>
        <div class="sent-log-tools">
          <label>
            Cửa sổ:
            <select v-model.number="windowHours" class="vba-select">
              <option :value="24">24 giờ</option>
              <option :value="48">48 giờ</option>
              <option :value="168">7 ngày</option>
            </select>
          </label>
          <input v-model="search" class="vba-input" placeholder="Lọc màu / mã hàng / máy..." />
          <button class="vba-btn" @click="fetchSentLog">LÀM MỚI</button>
          <router-link to="/print-order-entry" class="vba-btn vba-link">← IN TEM NHẬP ĐƠN</router-link>
        </div>
      </div>

      <table class="sent-tbl">
        <thead>
          <tr>
            <th style="width: 36pt">#</th>
            <th style="width: 120pt">COLOR</th>
            <th style="width: 72pt">CODE</th>
            <th style="width: 54pt">MACHINE</th>
            <th style="width: 36pt">TANK</th>
            <th style="width: 36pt">LV</th>
            <th style="width: 60pt">CHECK</th>
            <th style="width: 120pt">TIME3</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in sentLog" :key="d.id">
            <td>{{ i + 1 }}</td>
            <td>{{ d.batch?.color || '' }}</td>
            <td>{{ d.batch?.product_code || '' }}</td>
            <td>{{ d.batch?.machine?.code || '' }}</td>
            <td>{{ d.batch?.tank?.code || '' }}</td>
            <td>{{ d.batch?.level_code || '' }}</td>
            <td :class="d.scale_checked ? 'chk-yes' : 'chk-no'">{{ d.scale_checked ? '✓' : '—' }}</td>
            <td>{{ fmt(confirmTimeOf(d)) }}</td>
          </tr>
          <tr v-if="sentLog.length === 0">
            <td colspan="8" class="sent-empty">
              {{ loading ? 'Đang tải...' : 'Không có đơn nào khớp điều kiện.' }}
            </td>
          </tr>
        </tbody>
      </table>

      <p class="sent-note">
        Bản gốc đọc <code>tbl_sentlog WHERE TIME3 &gt;= DateAdd('h',-48,Now()) ORDER BY TIME3 ASC</code>
        (hàm tên <code>LoadSent_Last24h</code> nhưng SQL thật là 48 giờ). Cột TIME3 ở đây lấy mốc
        print job đầu tiên của đơn — web không ghi cột thời điểm xác nhận riêng.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import echo from '../services/echo';

const windowHours = ref(48);
const search = ref('');
const loading = ref(false);
const rows = ref<any[]>([]);

// Web không ghi mốc riêng cho lúc xác nhận (VBA có cột TIME3): confirm chỉ đổi queue_state
// và tạo print job. Mốc print job ĐẦU TIÊN chính là lúc bấm OK — các job sau là in lại.
const confirmTimeOf = (d: any): string =>
  d.print_jobs?.[0]?.created_at ?? d.printJobs?.[0]?.created_at ?? d.created_at;

const fmt = (d: string) => (d ? new Date(d).toLocaleString('vi-VN', { hour12: false }) : '');

// ORDER BY TIME3 ASC — cũ nhất trước, đúng bản gốc.
const sentLog = computed(() => {
  const cutoff = Date.now() - windowHours.value * 3_600_000;
  const q = search.value.trim().toLowerCase();
  return rows.value
    .filter(d => new Date(confirmTimeOf(d)).getTime() >= cutoff)
    .filter(d => {
      if (!q) return true;
      const b = d.batch || {};
      return [b.color, b.product_code, b.machine?.code, b.tank?.code]
        .some((v: any) => String(v ?? '').toLowerCase().includes(q));
    })
    .sort((a, b) => new Date(confirmTimeOf(a)).getTime() - new Date(confirmTimeOf(b)).getTime());
});

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

.vba-btn:active { border-color: #808080 #ffffff #ffffff #808080; }
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

.sent-empty { text-align: center; color: #606060; padding: 16px 0; }

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
