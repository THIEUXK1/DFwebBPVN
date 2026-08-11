<template>
  <div class="table-container-fixed">
    <p v-if="actionError" class="text-error mb-2">❌ {{ actionError }}</p>
    <table class="data-table">
      <thead>
        <tr>
          <th></th>
          <th>{{ $t('printJobHistoryTable.colBatchCode') }}</th>
          <th>{{ $t('printJobHistoryTable.colColor') }}</th>
          <th>{{ $t('printJobHistoryTable.colProductCode') }}</th>
          <th>{{ $t('printJobHistoryTable.colMachine') }}</th>
          <th>{{ $t('printJobHistoryTable.colTank') }}</th>
          <th>{{ $t('printJobHistoryTable.colOriginStation') }}</th>
          <th>{{ $t('printJobHistoryTable.colTransferTime') }}</th>
          <th>{{ $t('printJobHistoryTable.colLatestPrintStatus') }}</th>
          <th>{{ $t('printJobHistoryTable.colActions') }}</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="d in rows" :key="d.id">
          <tr class="row-printed tier-a-row" @click="toggleExpand(d.id)">
            <td class="expand-cell">{{ expanded.has(d.id) ? '▾' : '▸' }}</td>
            <td class="highlight-code">{{ d.batch?.legacy_batch_id }}</td>
            <td>{{ d.batch?.color }}</td>
            <td>{{ d.batch?.product_code }}</td>
            <td><span class="machine-tag">{{ d.batch?.machine?.code || 'N/A' }}</span></td>
            <td>{{ d.batch?.tank?.code || '-' }}</td>
            <td>{{ d.originating_station_code || '—' }}</td>
            <td>{{ formatTime(d.created_at) }}</td>
            <td>
              <span :class="['badge', printStatusBadgeClass(latestJob(d)?.status)]">
                {{ printStatusLabel(latestJob(d)?.status) }}
              </span>
              <span v-if="d.print_jobs?.length > 1" class="text-muted font-xs ml-1">{{ $t('printJobHistoryTable.printCountSuffix', { count: d.print_jobs.length }) }}</span>
            </td>
            <td class="actions-cell" @click.stop>
              <button
                v-if="latestJob(d) && latestJob(d)?.status !== 'PENDING'"
                class="btn btn-secondary btn-sm"
                :disabled="busyId === d.id"
                @click="requestReprint(d)"
              >{{ $t('printJobHistoryTable.reprintButton') }}</button>
            </td>
          </tr>

          <!-- Tier B: từng PrintJob (lần in đầu + các lần in lại) -->
          <tr v-if="expanded.has(d.id)" class="tier-b-wrap">
            <td :colspan="10">
              <table class="data-table nested-table">
                <thead>
                  <tr>
                    <th>{{ $t('printJobHistoryTable.nestedColCreatedAt') }}</th>
                    <th>{{ $t('printJobHistoryTable.nestedColPrinter') }}</th>
                    <th>{{ $t('printJobHistoryTable.nestedColStatus') }}</th>
                    <th>{{ $t('printJobHistoryTable.nestedColError') }}</th>
                    <th>{{ $t('printJobHistoryTable.nestedColAttempts') }}</th>
                    <th>{{ $t('printJobHistoryTable.nestedColActions') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(pj, idx) in d.print_jobs" :key="pj.id">
                    <td>{{ formatTime(pj.created_at) }} <span v-if="idx > 0" class="badge badge-yellow font-xs">{{ $t('printJobHistoryTable.reprintBadge') }}</span></td>
                    <td>{{ pj.printer_address || '—' }}</td>
                    <td><span :class="['badge', printStatusBadgeClass(pj.status)]">{{ printStatusLabel(pj.status) }}</span></td>
                    <td class="text-error font-xs">{{ latestError(pj) || '—' }}</td>
                    <td>
                      <div v-if="pj.attempts?.length" class="attempts-list">
                        <div v-for="a in pj.attempts" :key="a.id" class="font-xs">
                          {{ $t('printJobHistoryTable.attemptLabel', { no: a.attempt_no }) }} {{ a.status === 'PRINTED' ? '✅' : '❌' }} {{ formatTime(a.finished_at) }}
                        </div>
                      </div>
                      <span v-else class="text-muted font-xs">{{ $t('printJobHistoryTable.noAgentResponse') }}</span>
                    </td>
                    <td class="actions-cell">
                      <button class="btn btn-secondary btn-sm" @click.stop="openPreview(pj)">{{ $t('printJobHistoryTable.viewLabelButton') }}</button>
                      <button
                        v-if="pj.status === 'PENDING'"
                        class="btn btn-secondary btn-sm"
                        :disabled="busyId === pj.id"
                        @click.stop="cancelJob(pj)"
                      >{{ $t('printJobHistoryTable.cancelPrintButton') }}</button>
                      <button
                        v-else-if="idx === d.print_jobs.length - 1"
                        class="btn btn-secondary btn-sm"
                        :disabled="busyId === d.id"
                        @click.stop="requestReprint(d)"
                      >{{ $t('printJobHistoryTable.reprintButton') }}</button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
    <p v-if="!rows.length" class="text-muted text-center mt-3">{{ $t('printJobHistoryTable.noRows') }}</p>

    <!-- Modal nhập lý do — thay window.prompt() (bị chặn/không hiện gì trong 1 số webview
         nhúng, vd VSCode Simple Browser) bằng UI thật trong trang, luôn hoạt động. -->
    <div v-if="reasonModal.visible" class="modal-overlay" @click.self="closeReasonModal">
      <div class="reason-modal-card">
        <h4>{{ reasonModal.title }}</h4>
        <textarea
          v-model="reasonModal.value"
          class="form-input"
          rows="3"
          :placeholder="$t('printJobHistoryTable.reasonModalPlaceholder')"
          autofocus
        ></textarea>
        <div class="reason-modal-actions">
          <button class="btn btn-secondary btn-sm" @click="closeReasonModal">{{ $t('common.cancel') }}</button>
          <button
            class="btn btn-primary btn-sm"
            :disabled="!reasonModal.value.trim()"
            @click="confirmReasonModal"
          >{{ $t('common.confirm') }}</button>
        </div>
      </div>
    </div>

    <!-- Xem trước tem (yêu cầu 2026-07-18: kiểm tra nội dung/kích thước trước khi có máy in thật) -->
    <div v-if="previewJob" class="modal-overlay" @click.self="previewJob = null">
      <div class="preview-modal-card">
        <div class="preview-modal-header">
          <h4>{{ $t('printJobHistoryTable.previewModalTitle', { time: formatTime(previewJob.created_at) }) }}</h4>
          <button class="close-btn" @click="previewJob = null">&times;</button>
        </div>
        <LabelPreview :label-payload="previewJob.label_payload" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import LabelPreview from './LabelPreview.vue';

const { t } = useI18n({ useScope: 'global' });

const previewJob = ref<any | null>(null);
function openPreview(pj: any) {
  previewJob.value = pj;
}

// defaultPrinter: máy in đã resolve của trạm ĐANG đứng (PrintStation.vue truyền
// resolvedPrinter) — dùng làm máy in cho lệnh IN LẠI. Admin xem lịch sử toàn hệ thống
// không có 1 trạm cụ thể nào đang đứng nên không truyền — backend tự dùng default cột
// DB (không chặn thao tác, chỉ có thể không đúng máy in vật lý nào).
// stationCode: mã trạm đang đứng (PrintStation.vue truyền currentWorkstation.code) —
// BẮT BUỘC gửi kèm khi in lại, nếu không backend lưu job dưới workstation_id mặc định
// ('QR_LABEL_PRINTING_1') mà KHÔNG Agent thật nào đứng nghe, khiến job treo PENDING mãi
// mãi không ai xử lý (bug phát hiện 2026-07-18: "ấn In lại không có phản ứng gì" — 2
// nguyên nhân cộng dồn, đây là nguyên nhân thứ 2 sau lỗi window.prompt() bị chặn).
// Admin xem lịch sử toàn hệ thống không có trạm cụ thể nào đang đứng, không truyền —
// backend/component tự lấy workstation_id của lần in gần nhất làm mặc định hợp lý.
const props = defineProps<{ rows: any[]; defaultPrinter?: string | null; stationCode?: string | null }>();
const emit = defineEmits<{ refresh: [] }>();

const expanded = ref<Set<string>>(new Set());
const busyId = ref<string | null>(null);

function toggleExpand(id: string) {
  if (expanded.value.has(id)) expanded.value.delete(id);
  else expanded.value.add(id);
}

function latestJob(d: any) {
  return d.print_jobs?.length ? d.print_jobs[d.print_jobs.length - 1] : null;
}

function latestError(pj: any) {
  if (pj.attempts?.length) {
    const lastFailed = [...pj.attempts].reverse().find((a: any) => a.status === 'FAILED');
    if (lastFailed) return lastFailed.error_detail;
  }
  return null;
}

function printStatusLabel(status: string | undefined) {
  const map: Record<string, string> = {
    PENDING: t('printJobHistoryTable.statusPending'),
    PRINTED: t('printJobHistoryTable.statusPrinted'),
    FAILED: t('printJobHistoryTable.statusFailed'),
    CANCELLED: t('printJobHistoryTable.statusCancelled'),
  };
  return map[status || ''] || status || t('printJobHistoryTable.statusUnknown');
}

function printStatusBadgeClass(status: string | undefined) {
  const map: Record<string, string> = {
    PENDING: 'badge-yellow', PRINTED: 'badge-green', FAILED: 'badge-red', CANCELLED: 'badge-gray',
  };
  return map[status || ''] || 'badge-gray';
}

function formatTime(v: string | undefined) {
  if (!v) return '—';
  return new Date(v).toLocaleString('vi-VN', { hour12: false });
}

// Modal nhập lý do — thay window.prompt()/alert() (bị chặn hoặc không hiện gì trong 1 số
// webview nhúng, vd VSCode Simple Browser, khiến bấm nút "như không có phản ứng gì").
const reasonModal = ref<{ visible: boolean; title: string; value: string; onConfirm: ((reason: string) => void) | null }>({
  visible: false,
  title: '',
  value: '',
  onConfirm: null,
});
const actionError = ref('');

function openReasonModal(title: string, onConfirm: (reason: string) => void) {
  actionError.value = '';
  reasonModal.value = { visible: true, title, value: '', onConfirm };
}

function closeReasonModal() {
  reasonModal.value.visible = false;
  reasonModal.value.onConfirm = null;
}

function confirmReasonModal() {
  const reason = reasonModal.value.value.trim();
  if (!reason || !reasonModal.value.onConfirm) return;
  const cb = reasonModal.value.onConfirm;
  closeReasonModal();
  cb(reason);
}

function cancelJob(pj: any) {
  openReasonModal(t('printJobHistoryTable.cancelReasonTitle'), async (reason) => {
    busyId.value = pj.id;
    try {
      await axios.post(`/api/print-jobs/${pj.id}/cancel`, { reason });
      emit('refresh');
    } catch (err: any) {
      actionError.value = err.response?.data?.message || t('printJobHistoryTable.cancelPrintFailed');
    } finally {
      busyId.value = null;
    }
  });
}

function requestReprint(d: any) {
  openReasonModal(t('printJobHistoryTable.reprintReasonTitle'), async (reason) => {
    busyId.value = d.id;
    try {
      const fallbackStation = latestJob(d)?.workstation_id;
      await axios.post(`/api/machine-dispatches/${d.id}/reprint`, {
        reason,
        printer_address: props.defaultPrinter || undefined,
        printer_type: props.defaultPrinter ? 'USB' : undefined,
        workstation_id: props.stationCode || fallbackStation || undefined,
      });
      emit('refresh');
    } catch (err: any) {
      actionError.value = err.response?.data?.message || t('printJobHistoryTable.reprintFailed');
    } finally {
      busyId.value = null;
    }
  });
}
</script>

<style scoped>
.table-container-fixed { width: 100%; overflow-x: auto; border-radius: var(--radius-lg); }
.highlight-code { color: var(--primary-hover); font-family: monospace; font-weight: 700; }
.machine-tag {
  background-color: var(--status-blue-bg); color: var(--status-blue);
  padding: 2px 10px; border-radius: var(--radius-full); font-weight: 700; font-size: 0.85rem;
}
.tier-a-row { cursor: pointer; }
.expand-cell { width: 24px; text-align: center; color: var(--text-muted); }
.nested-table { width: 100%; background-color: var(--bg-sidebar); }
.tier-b-wrap td { padding: 0 0 0 24px; background-color: var(--bg-sidebar); }
.attempts-list { display: flex; flex-direction: column; gap: 2px; }
.font-xs { font-size: 0.75rem; }
.badge-gray { background-color: var(--bg-card-hover); color: var(--text-muted); }
.text-error { color: var(--status-red); font-size: 0.85rem; }

.modal-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background-color: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.reason-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  padding: var(--space-xl);
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-xl);
}
.reason-modal-card h4 {
  margin: 0 0 var(--space-md) 0;
  color: var(--text-title);
}
.reason-modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: var(--space-md);
}

.actions-cell { display: flex; gap: 6px; flex-wrap: wrap; }

.preview-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 560px;
  max-height: 85vh;
  overflow-y: auto;
  box-shadow: var(--shadow-xl);
}
.preview-modal-header {
  padding: var(--space-lg) var(--space-xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.preview-modal-header h4 { margin: 0; color: var(--text-title); font-size: 1rem; }
.close-btn { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; }
</style>
