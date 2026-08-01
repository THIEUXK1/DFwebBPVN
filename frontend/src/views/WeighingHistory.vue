<template>
  <div class="wh-page">
    <div class="wh-bar">
      <div class="wh-field">
        <label>Từ ngày</label>
        <input type="date" v-model="filters.from" />
      </div>
      <div class="wh-field">
        <label>Đến ngày</label>
        <input type="date" v-model="filters.to" />
      </div>
      <div class="wh-field wh-grow">
        <label>Tìm kiếm</label>
        <input
          type="text"
          v-model="filters.q"
          placeholder="Màu / mã hàng / mã lô / máy…"
          @keyup.enter="reload"
        />
      </div>
      <button class="wh-btn" @click="reload" :disabled="loading">Lọc</button>
      <button class="wh-btn ghost" @click="resetFilters" :disabled="loading">Xoá lọc</button>
    </div>

    <p v-if="loading" class="wh-msg">Đang tải…</p>
    <p v-else-if="errorMsg" class="wh-msg err">{{ errorMsg }}</p>
    <p v-else-if="rounds.length === 0" class="wh-msg">
      Không có vòng cân nào khớp điều kiện lọc.
    </p>

    <table v-else class="wh-table">
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
        <template v-for="job in rounds" :key="job.id">
          <tr class="wh-row" :class="{ open: expanded === job.id }" @click="toggle(job.id)">
            <td class="c-time">{{ formatTime(job.completed_at) }}</td>
            <td class="strong">{{ job.batch?.color || '—' }}</td>
            <td>{{ job.batch?.product_code || '—' }}</td>
            <td>{{ job.batch?.machine?.code || '—' }}</td>
            <td>{{ job.batch?.level_code || '—' }}</td>
            <td class="c-num">{{ job.total_items }}</td>
            <td class="c-num ok">{{ job.accepted_count }}</td>
            <td class="c-num" :class="{ bad: job.rejected_count > 0 }">{{ job.rejected_count }}</td>
            <td class="c-act">{{ expanded === job.id ? '▲' : '▼' }}</td>
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

    <div v-if="meta.last_page > 1" class="wh-pager">
      <button class="wh-btn ghost" :disabled="meta.current_page <= 1 || loading" @click="go(meta.current_page - 1)">
        ← Trước
      </button>
      <span>Trang {{ meta.current_page }} / {{ meta.last_page }} — {{ meta.total }} vòng cân</span>
      <button class="wh-btn ghost" :disabled="meta.current_page >= meta.last_page || loading" @click="go(meta.current_page + 1)">
        Sau →
      </button>
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

import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const rounds = ref<any[]>([]);
const loading = ref(false);
const errorMsg = ref('');
const expanded = ref<string | null>(null);

const filters = reactive({ from: '', to: '', q: '' });
const meta = reactive({ current_page: 1, last_page: 1, total: 0 });

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

async function load(page = 1) {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await axios.get('/api/weighing-jobs/history', {
      params: {
        page,
        per_page: 20,
        from: filters.from || undefined,
        to: filters.to || undefined,
        q: filters.q || undefined,
      },
    });
    const p = res.data?.data;
    rounds.value = p?.data || [];
    meta.current_page = p?.current_page || 1;
    meta.last_page = p?.last_page || 1;
    meta.total = p?.total || 0;
  } catch (err: any) {
    errorMsg.value = err.response?.data?.message || 'Không tải được lịch sử cân.';
    rounds.value = [];
  } finally {
    loading.value = false;
  }
}

function reload() {
  expanded.value = null;
  load(1);
}

function go(page: number) {
  expanded.value = null;
  load(page);
}

function resetFilters() {
  filters.from = '';
  filters.to = '';
  filters.q = '';
  reload();
}

onMounted(() => load(1));
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
  width: 28px;
  text-align: center;
  opacity: 0.6;
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
