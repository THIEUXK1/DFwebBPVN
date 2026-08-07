<template>
  <!-- Lịch sử GỬI / IN của màn /qr-printer.

       KHÔNG có trong workbook "QR PRINTER-send to access- NEW 9ROWS BIG QR.xlsm" — phần bổ sung
       cho bản web (yêu cầu 07/08/2026). Vì vậy trang này KHÔNG dựng theo toạ độ pt của UserForm
       như QrPrinterForm.vue, chỉ mượn hệ màu/khung Windows cổ điển cho đồng bộ khi mở cạnh nhau.

       HAI TAB ĐỌC HAI NGUỒN KHÁC NHAU, cố ý:

       · ĐÃ GỬI  → `app.production_batches` (/api/public/production-batches). Đây là NGUỒN SỰ THẬT
         của việc gửi đơn, nên có sẵn cả lịch sử trước ngày làm màn hình này, và gồm cả lô gửi từ
         màn khác (/production-batches/grid, /print-order-entry) — thao tác viên tra "máy VD07 đã
         có lô chưa" thì cần thấy hết, không chỉ lô gửi từ đúng cái form này. 9 dòng chi tiết đọc
         ngược từ `raw_qr_dye`/`raw_qr_chemical` đã lưu lúc gửi.

       · ĐÃ IN   → `app.audit_logs` (/api/public/qr-printer/logs, xem QrPrinterLogController). Bấm
         `print` ở form chỉ mở cửa sổ dựng phiếu, KHÔNG sinh `print_jobs` như trạm in — trước bản
         này không có chỗ nào lưu vết, nên tab này chỉ có dữ liệu TỪ 07/08/2026 trở đi. -->

  <component :is="pageWrapper">
  <div class="vba-scroll">
    <div class="vba-form">
      <div class="head">
        <div class="tabs">
          <button class="vba-btn tab" :class="{ 'is-active': tab === 'sent' }" @click="switchTab('sent')">
            ĐÃ GỬI (SEND)
          </button>
          <button class="vba-btn tab" :class="{ 'is-active': tab === 'print' }" @click="switchTab('print')">
            ĐÃ IN (PRINT)
          </button>
        </div>

        <div class="tools">
          <label>
            Khoảng:
            <select v-model.number="days" class="vba-select" @change="fetchLogs">
              <option :value="1">Hôm nay</option>
              <option :value="7">7 ngày</option>
              <option :value="30">30 ngày</option>
              <option :value="90">90 ngày</option>
            </select>
          </label>
          <input v-model="search" class="vba-input" placeholder="Lọc COLOR / CODE / máy / mã thuốc..." />
          <button class="vba-btn" :disabled="loading" @click="fetchLogs">{{ loading ? 'Đang tải...' : 'LÀM MỚI' }}</button>
          <a class="vba-btn" href="/qr-printer">← VỀ FORM QR PRINTER</a>
        </div>
      </div>

      <div class="count">
        {{ tab === 'sent' ? 'Lô đã gửi xuống hàng chờ' : 'Các lần bấm print ở màn /qr-printer' }}
        — {{ visible.length }} bản ghi
        <span v-if="error" class="err">· {{ error }}</span>
      </div>

      <table class="log-tbl">
        <thead>
          <tr>
            <th style="width: 30px"></th>
            <th style="width: 36px">#</th>
            <th style="width: 130px">THỜI GIAN</th>
            <th style="width: 120px">COLOR</th>
            <th style="width: 90px">CODE</th>
            <th style="width: 60px">MACHINE</th>
            <th style="width: 46px">TANK</th>
            <th style="width: 40px">LV</th>
            <th style="width: 44px">DYE</th>
            <th style="width: 50px">CHEM</th>
            <th style="width: 130px">{{ tab === 'sent' ? 'TRẠNG THÁI' : 'MÁY TRẠM' }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(r, i) in visible" :key="r.id">
            <tr class="log-row" :class="{ 'is-open': openId === r.id }" @click="toggle(r.id)">
              <td class="expander">{{ openId === r.id ? '▾' : '▸' }}</td>
              <td>{{ i + 1 }}</td>
              <td>{{ fmt(r.logged_at) }}</td>
              <td class="strong">{{ r.color }}</td>
              <td>{{ r.code }}</td>
              <td>{{ r.machine }}</td>
              <td>{{ r.tank }}</td>
              <td>{{ r.lv }}</td>
              <td>{{ r.dye?.length || 0 }}</td>
              <td>{{ r.chem?.length || 0 }}</td>
              <td class="dim">{{ r.trailing || '' }}</td>
            </tr>
            <tr v-if="openId === r.id" class="detail-row">
              <td colspan="11">
                <div class="detail">
                  <div class="detail-block">
                    <div class="detail-title">THUỐC NHUỘM ({{ r.dye?.length || 0 }} dòng)</div>
                    <table class="mini-tbl">
                      <tr><th>RACK</th><th>DYE CODE</th><th>WEIGHT</th></tr>
                      <tr v-for="(d, k) in r.dye || []" :key="'d' + k">
                        <td>{{ d.rack }}</td><td>{{ d.code }}</td><td class="num">{{ d.weight }}</td>
                      </tr>
                      <tr v-if="!r.dye?.length"><td colspan="3" class="dim">(không có)</td></tr>
                    </table>
                  </div>
                  <div class="detail-block">
                    <div class="detail-title">HÓA CHẤT ({{ r.chem?.length || 0 }} dòng)</div>
                    <table class="mini-tbl">
                      <tr><th>RACK</th><th>chem CODE</th><th>WEIGHT</th></tr>
                      <tr v-for="(c, k) in r.chem || []" :key="'c' + k">
                        <td>{{ c.rack }}</td><td>{{ c.code }}</td><td class="num">{{ c.weight }}</td>
                      </tr>
                      <tr v-if="!r.chem?.length"><td colspan="3" class="dim">(không có)</td></tr>
                    </table>
                  </div>
                  <div class="detail-block meta">
                    <div class="detail-title">GHI CHÚ</div>
                    <div v-if="r.batch_id">Mã lô: <code>{{ r.batch_id }}</code></div>
                    <div v-if="r.note">{{ r.note }}</div>
                    <div class="dim">{{ tab === 'sent' ? 'Lô ' + r.id : 'Bản ghi audit #' + r.id }}</div>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="!visible.length">
            <td colspan="11" class="empty">
              {{ loading ? 'Đang tải...' : 'Chưa có bản ghi nào trong khoảng thời gian đã chọn.' }}
            </td>
          </tr>
        </tbody>
      </table>

      <p class="note">
        <strong>ĐÃ GỬI</strong> đọc thẳng từ bảng lô sản xuất nên có sẵn lịch sử cũ, và gồm cả lô
        gửi từ màn hình khác (<a href="/production-batches/grid">/production-batches/grid</a>,
        <a href="/print-order-entry">/print-order-entry</a>) chứ không riêng form
        <a href="/qr-printer">/qr-printer</a>. 9 dòng chi tiết đọc ngược từ chuỗi QR đã lưu lúc gửi.
        <br />
        <strong>ĐÃ IN</strong> là các lần bấm nút <code>print</code> ở form — trước 07/08/2026 hệ
        thống không lưu vết việc này, nên tab đó chỉ có dữ liệu từ ngày đó trở đi.
        <br />
        Mỗi lần tải tối đa 100 lô / 200 bản ghi in gần nhất; ô lọc hoạt động trên phần đã tải về.
      </p>
    </div>

    <FullscreenButton variant="vba" />
    <NavToggleButton variant="vba" />
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import AppLayout from '../components/AppLayout.vue';
import FullscreenButton from '../components/FullscreenButton.vue';
import NavToggleButton from '../components/NavToggleButton.vue';
import { useAuthStore } from '../stores/auth';

// Cùng cách làm với QrPrinterForm.vue: trang công khai nên App.vue không bọc AppLayout, tự bọc
// lấy khi người xem đã đăng nhập để vẫn có menu điều hướng.
const isLoggedIn = useAuthStore().isAuthenticated;
const pageWrapper = isLoggedIn ? AppLayout : 'div';

const route = useRoute();
const router = useRouter();

type Tab = 'sent' | 'print';
const tab = ref<Tab>(route.query.tab === 'print' ? 'print' : 'sent');

const days = ref(7);
const search = ref('');
const loading = ref(false);
const error = ref('');
const rows = ref<any[]>([]);
const openId = ref<string | number | null>(null);

function switchTab(t: Tab): void {
  if (tab.value === t) return;
  tab.value = t;
  openId.value = null;
  // Giữ tab trên URL để mở lại/F5 không nhảy về tab mặc định.
  router.replace({ query: { ...route.query, tab: t } });
  fetchLogs();
}

function toggle(id: string | number): void {
  openId.value = openId.value === id ? null : id;
}

const fmt = (v: string) => (v ? new Date(v).toLocaleString('vi-VN', { hour12: false }) : '');

const visible = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return rows.value;
  return rows.value.filter(r => {
    const inRows = [...(r.dye || []), ...(r.chem || [])]
      .some((x: any) => String(x.code ?? '').toLowerCase().includes(q));
    return inRows || [r.color, r.code, r.machine, r.tank, r.lv, r.batch_id]
      .some((v: any) => String(v ?? '').toLowerCase().includes(q));
  });
});

/**
 * Đọc ngược chuỗi `raw_qr_dye` / `raw_qr_chemical` mà form đã lưu lúc bấm SEND (xem `buildRawQr`
 * trong utils/qrPrinterSlip.ts): các bộ ba `rack-code-weight` nối nhau bằng dấu "-".
 *
 * Cùng điểm yếu với bản VBA gốc: mã thuốc có chứa dấu "-" sẽ tách sai. Giữ nguyên cách tách đó
 * thay vì "thông minh hơn" — dữ liệu đã lưu theo đúng quy ước này, đoán khác đi chỉ lệch thêm.
 */
function parseRawQr(raw: string | null | undefined): Array<{ rack: string; code: string; weight: string }> {
  const parts = String(raw ?? '').split('-');
  const out = [];
  for (let i = 0; i < parts.length; i += 3) {
    if (!parts[i + 1]) continue;
    out.push({ rack: parts[i] ?? '', code: parts[i + 1] ?? '', weight: parts[i + 2] ?? '' });
  }
  return out;
}

/** Lô đã gửi — nguồn sự thật là bảng lô sản xuất, không phải nhật ký bấm nút. */
async function fetchSent(): Promise<void> {
  const res = await axios.get('/api/public/production-batches', { params: { per_page: 100 } });
  // Endpoint này trả nguyên đối tượng phân trang của Laravel (data nằm trong .data).
  const list = res.data.data ?? [];
  const cutoff = Date.now() - days.value * 86_400_000;
  rows.value = list
    .filter((b: any) => new Date(b.created_at).getTime() >= cutoff)
    .map((b: any) => ({
      id: b.id,
      logged_at: b.created_at,
      color: b.color,
      code: b.product_code,
      machine: b.machine?.code ?? '',
      tank: b.tank?.code ?? '',
      lv: b.level_code ?? '',
      batch_id: b.legacy_batch_id,
      dye: parseRawQr(b.raw_qr_dye),
      chem: parseRawQr(b.raw_qr_chemical),
      // Trạng thái lô + trạng thái hàng chờ gửi máy — cho biết lô đã được xác nhận gửi chưa.
      trailing: [b.status, b.dispatches?.[0]?.queue_state].filter(Boolean).join(' / '),
    }));
}

/** Các lần bấm print — chỉ có trong audit log, không có nguồn nào khác. */
async function fetchPrints(): Promise<void> {
  const res = await axios.get('/api/public/qr-printer/logs', {
    params: { kind: 'PRINT', days: days.value },
  });
  rows.value = (res.data.data ?? []).map((r: any) => ({ ...r, trailing: r.client_ip }));
}

async function fetchLogs(): Promise<void> {
  loading.value = true;
  error.value = '';
  try {
    await (tab.value === 'sent' ? fetchSent() : fetchPrints());
  } catch (e: any) {
    error.value = e.response?.data?.message || 'Không tải được nhật ký.';
    rows.value = [];
  } finally {
    loading.value = false;
  }
}

// Nạp lại định kỳ để tab lịch sử mở sẵn cạnh form tự cập nhật sau mỗi lần gửi/in ở tab kia —
// 2 tab là 2 tiến trình riêng, không có sự kiện nào bắn qua lại giữa chúng.
let poll: any = null;

onMounted(() => {
  fetchLogs();
  poll = setInterval(fetchLogs, 20000);
});

onUnmounted(() => {
  if (poll) clearInterval(poll);
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

.head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 6px;
}

.tabs { display: flex; gap: 4px; }

.tab {
  font-size: 10pt;
  font-weight: bold;
  padding: 6px 16px;
}

/* Tab đang mở: đảo viền cho giống nút đang được nhấn giữ của MSForms. */
.tab.is-active {
  background-color: #ffffff;
  border-color: #808080 #ffffff #ffffff #808080;
}

.tools {
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

.vba-input { width: 220px; }

.vba-btn {
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #f0f0f0;
  color: #000000;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  padding: 3px 10px;
  cursor: pointer;
  text-decoration: none;
  white-space: nowrap;
}

.vba-btn:active:not(:disabled) { border-color: #808080 #ffffff #ffffff #808080; }
.vba-btn:disabled { color: #808080; cursor: default; }

.count { margin: 4px 0 6px; font-weight: bold; }
.count .err { color: #a00000; }

.log-tbl {
  width: 100%;
  border-collapse: collapse;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #ffffff;
}

.log-tbl th {
  background-color: #f0f0f0;
  border: 1px solid #808080;
  padding: 3px 4px;
  text-align: left;
  white-space: nowrap;
}

.log-tbl td {
  border: 1px solid #c8c8c8;
  padding: 3px 4px;
  white-space: nowrap;
}

.log-row { cursor: pointer; }
.log-row:hover { background-color: #d8e6f8; }
.log-row.is-open { background-color: #cfe3ff; }

.expander { text-align: center; color: #404040; }
.strong { font-weight: bold; }
.dim { color: #606060; }
.num { text-align: right; }

.empty { text-align: center; color: #606060; padding: 12px; }

.detail-row td { background-color: #fbfbe8; }

.detail {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  padding: 4px 2px;
}

.detail-block { min-width: 220px; }
.detail-title { font-weight: bold; margin-bottom: 3px; }
.detail-block.meta { line-height: 1.6; }

.mini-tbl {
  border-collapse: collapse;
  background-color: #ffffff;
}

.mini-tbl th, .mini-tbl td {
  border: 1px solid #c8c8c8;
  padding: 2px 8px;
  text-align: left;
}

.mini-tbl th { background-color: #f0f0f0; }

.note {
  margin: 8px 2px 0;
  color: #303030;
  line-height: 1.5;
}
</style>
