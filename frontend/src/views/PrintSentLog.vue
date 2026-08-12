<template>
  <!-- Bản web của sheet "sent" trong workbook DF002 (Mod_load_sentlog_sheet). Bản gốc
       không để bảng này trên UserForm mà đổ ra một sheet riêng — nên tách thành view
       riêng ở đây cũng đúng tinh thần bản gốc. -->
  <div class="vba-scroll">
    <div class="vba-form sent-log">
      <div class="sent-log-head">
        <div class="sent-log-title">
          {{ $t('printSentLog.titleLabel') }} ({{ filtered.length }}<span v-if="filtered.length !== rows.length">/{{ rows.length }}</span>)
        </div>
        <div class="sent-log-tools">
          <button class="vba-btn" @click="fetchSentLog">{{ $t('printSentLog.refreshBtn') }}</button>
          <router-link to="/print-order-entry" class="vba-btn vba-link">{{ $t('printSentLog.backToPrintOrderLink') }}</router-link>
        </div>
      </div>

      <!-- Khối lọc thu gọn được (giống WeighingHistory.vue) — chạy hoàn toàn phía trình duyệt
           trên tập /machine-dispatches/history đã trả về, không gọi lại API khi đổi lọc. -->
      <div class="filters">
        <div class="filters-head">
          <button
            type="button"
            class="vba-btn filters-toggle"
            @click="filtersCollapsed = !filtersCollapsed"
            :aria-expanded="!filtersCollapsed"
            :title="$t('printSentLog.filtersToggleTitle')"
          >
            <span class="filters-toggle-icon">{{ filtersCollapsed ? '▸' : '▾' }}</span>
            {{ $t('printSentLog.filtersToggleLabel') }}
          </button>
          <!-- Chỉ hiện khi đã thu gọn: nhắc còn đang lọc gì, tránh tưởng bảng ngắn là hết dữ liệu. -->
          <span v-if="filtersCollapsed && activeFilterSummary" class="filters-summary">
            {{ activeFilterSummary }}
          </span>
        </div>

        <div v-show="!filtersCollapsed" class="filter-bar">
          <label>
            {{ $t('printSentLog.windowLabel') }}
            <select v-model.number="windowHours" class="vba-select">
              <option :value="24">{{ $t('printSentLog.window24h') }}</option>
              <option :value="48">{{ $t('printSentLog.window48h') }}</option>
              <option :value="168">{{ $t('printSentLog.window7d') }}</option>
              <option :value="720">{{ $t('printSentLog.window30d') }}</option>
              <option :value="0">{{ $t('common.all') }}</option>
            </select>
          </label>

          <label>
            {{ $t('printSentLog.machineLabel') }}
            <select v-model="machineFilter" class="vba-select">
              <option value="">{{ $t('printSentLog.machineAllOption') }}</option>
              <option v-for="m in machineOptions" :key="m" :value="m">{{ m }}</option>
            </select>
          </label>

          <label>
            {{ $t('printSentLog.tankLabel') }}
            <select v-model="tankFilter" class="vba-select">
              <option value="">{{ $t('printSentLog.tankAllOption') }}</option>
              <option v-for="t in tankOptions" :key="t" :value="t">{{ t }}</option>
            </select>
          </label>

          <label>
            {{ $t('printSentLog.checkLabel') }}
            <select v-model="checkFilter" class="vba-select">
              <option value="">{{ $t('printSentLog.checkAllOption') }}</option>
              <option value="YES">{{ $t('printSentLog.checkYesOption') }}</option>
              <option value="NO">{{ $t('printSentLog.checkNoOption') }}</option>
            </select>
          </label>

          <label>
            {{ $t('printSentLog.printStatusLabel') }}
            <select v-model="printFilter" class="vba-select">
              <option value="">{{ $t('printSentLog.printStatusAllOption') }}</option>
              <option value="PRINTED">{{ $t('printSentLog.printStatusPrintedOption') }}</option>
              <option value="PENDING">{{ $t('printSentLog.printStatusPendingOption') }}</option>
              <option value="FAILED">{{ $t('printSentLog.printStatusFailedOption') }}</option>
              <option value="CANCELLED">{{ $t('printSentLog.printStatusCancelledOption') }}</option>
              <option value="NONE">{{ $t('printSentLog.printStatusNoneOption') }}</option>
            </select>
          </label>

          <label>
            {{ $t('printSentLog.stationLabel') }}
            <select v-model="stationFilter" class="vba-select">
              <option value="">{{ $t('printSentLog.stationAllOption') }}</option>
              <option v-for="s in stationOptions" :key="s" :value="s">{{ s }}</option>
            </select>
          </label>

          <input v-model="search" class="vba-input" :placeholder="$t('printSentLog.searchPlaceholder')" />

          <button class="vba-btn" :disabled="!hasFilter" @click="resetFilters">{{ $t('printSentLog.clearFilterBtn') }}</button>
        </div>
      </div>

      <div class="table-card">
      <div class="table-scroll">
      <table class="sent-tbl">
        <thead>
          <tr>
            <th style="width: 36pt">#</th>
            <th style="width: 78pt">{{ $t('printSentLog.theadBatch') }}</th>
            <th style="width: 120pt">COLOR</th>
            <th style="width: 72pt">CODE</th>
            <th style="width: 54pt">MACHINE</th>
            <th style="width: 36pt">TANK</th>
            <th style="width: 36pt">LV</th>
            <th style="width: 48pt">CHECK</th>
            <th style="width: 90pt">IN</th>
            <th style="width: 72pt">{{ $t('printSentLog.theadStation') }}</th>
            <th style="width: 120pt">TIME3</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(d, i) in pageRows" :key="d.id">
            <!-- Ấn vào mẻ để xem công thức — xổ ngay dưới dòng, không rời trang. "Công thức" ở
                 đây LÀ đúng 2 bảng DYE/CHEM đã in trên tem thật của mẻ này (xem VbaPrintForm.vue,
                 nhãn gốc "dye stuff information"/"auxiliary information"), lấy thẳng từ
                 raw_qr_dye/raw_qr_chemical đã có sẵn trong `d` — KHÔNG gọi thêm API, vì đây mới
                 là dữ liệu công thức thật đã dùng để in, khác với bảng `recipes` (định mức chuẩn
                 duyệt theo Màu+Mã hàng, có thể chưa nhập hoặc lệch với đợt cân thực tế này). -->
            <!-- 'row-even' thay cho :nth-child(even) trong CSS: dòng công thức xổ ra chen thêm
                 một <tr> vào giữa, làm lệch phép đếm nth-child kể từ đó — vằn ca-rô phải bám theo
                 CHỈ SỐ DỮ LIỆU (i), không bám theo vị trí DOM. -->
            <tr
              class="sent-row"
              :class="{ 'row-open': expandedId === d.id, 'row-even': i % 2 === 1 }"
              @click="toggleRow(d)"
            >
              <td><span class="row-chevron">{{ expandedId === d.id ? '▾' : '▸' }}</span>{{ pageStart + i + 1 }}</td>
              <td>{{ d.batch?.legacy_batch_id || '' }}</td>
              <td>{{ d.batch?.color || '' }}</td>
              <td>{{ d.batch?.product_code || '' }}</td>
              <td>{{ d.batch?.machine?.code || '' }}</td>
              <td>{{ d.batch?.tank?.code || '' }}</td>
              <td>{{ d.batch?.level_code || '' }}</td>
              <td :class="d.scale_checked ? 'chk-yes' : 'chk-no'">{{ d.scale_checked ? '✓' : '—' }}</td>
              <td>
                <span class="print-badge" :class="'pb-' + printStatusOf(d)">{{ printLabel(printStatusOf(d)) }}</span>
                <span v-if="printCountOf(d) > 1" class="print-count">×{{ printCountOf(d) }}</span>
              </td>
              <td>{{ d.originating_station_code || '' }}</td>
              <td>{{ fmt(confirmTimeOf(d)) }}</td>
            </tr>

            <tr v-if="expandedId === d.id" :key="d.id + '-recipe'" class="recipe-row">
              <td colspan="11">
                <div class="recipe-box">
                  <template v-if="dyeLinesOf(d).length === 0 && chemLinesOf(d).length === 0">
                    {{ $t('printSentLog.recipeNoQrData') }}
                  </template>
                  <div v-else class="recipe-cols">
                    <table class="recipe-tbl">
                      <caption>{{ $t('printSentLog.recipeDyeCaption') }}</caption>
                      <thead>
                        <tr>
                          <th>{{ $t('printSentLog.recipeColRack') }}</th>
                          <th>{{ $t('printSentLog.recipeColCode') }}</th>
                          <th class="num">{{ $t('printSentLog.recipeColWeight') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(ln, li) in dyeLinesOf(d)" :key="li">
                          <td>{{ ln.rack }}</td>
                          <td class="bold">{{ ln.code }}</td>
                          <td class="num">{{ ln.weight }}</td>
                        </tr>
                        <tr v-if="dyeLinesOf(d).length === 0"><td colspan="3" class="recipe-empty">—</td></tr>
                      </tbody>
                    </table>
                    <table class="recipe-tbl">
                      <caption>{{ $t('printSentLog.recipeChemCaption') }}</caption>
                      <thead>
                        <tr>
                          <th>{{ $t('printSentLog.recipeColRack') }}</th>
                          <th>{{ $t('printSentLog.recipeColCode') }}</th>
                          <th class="num">{{ $t('printSentLog.recipeColWeight') }}</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(ln, li) in chemLinesOf(d)" :key="li">
                          <td>{{ ln.rack }}</td>
                          <td class="bold">{{ ln.code }}</td>
                          <td class="num">{{ ln.weight }}</td>
                        </tr>
                        <tr v-if="chemLinesOf(d).length === 0"><td colspan="3" class="recipe-empty">—</td></tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- In lại: nút → xổ ô nhập lý do (bắt buộc, ghi Audit Log) → bấm Xác nhận mới
                       thật sự in + gọi API. Không dùng window.prompt() — bị chặn/im lặng trong
                       một số webview nhúng (cùng lý do PrintJobHistoryTable.vue). -->
                  <div class="recipe-actions" @click.stop>
                    <button
                      v-if="reprintReasonId !== d.id"
                      type="button"
                      class="vba-btn"
                      @click="askReprint(d)"
                    >🖨 {{ $t('printSentLog.reprintButton') }}</button>

                    <div v-else class="reprint-prompt">
                      <input
                        v-model="reprintReason"
                        class="vba-input reprint-reason-input"
                        :placeholder="$t('printSentLog.reprintReasonPlaceholder')"
                        :disabled="reprinting === d.id"
                        autofocus
                        @keyup.enter="confirmReprint(d)"
                        @keyup.esc="cancelReprint"
                      />
                      <button
                        type="button"
                        class="vba-btn"
                        :disabled="!reprintReason.trim() || reprinting === d.id"
                        @click="confirmReprint(d)"
                      >{{ reprinting === d.id ? '…' : $t('common.confirm') }}</button>
                      <button
                        type="button"
                        class="vba-btn"
                        :disabled="reprinting === d.id"
                        @click="cancelReprint"
                      >{{ $t('common.cancel') }}</button>
                    </div>

                    <span v-if="reprintError && reprintReasonId === d.id" class="reprint-error">⚠ {{ reprintError }}</span>
                  </div>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="pageRows.length === 0">
            <td colspan="11" class="sent-empty">
              {{ loading ? $t('common.loading') : $t('printSentLog.noMatchMsg') }}
            </td>
          </tr>
        </tbody>
      </table>
      </div>

      <!-- Chân khung, NGOÀI .table-scroll nên không cuộn theo bảng — luôn hiện ở mọi kích cỡ
           màn hình (xem .table-card/.table-scroll bên dưới, cùng cách làm với WeighingHistory.vue). -->
      <div class="pager">
        <div class="pager-info">
          <template v-if="filtered.length">
            {{ $t('printSentLog.pagerShowing', { from: pageStart + 1, to: pageStart + pageRows.length, total: filtered.length }) }}
          </template>
          <template v-else>{{ $t('printSentLog.pagerNoRows') }}</template>
        </div>
        <div class="pager-ctrl">
          <label>
            {{ $t('printSentLog.pageSizeLabel') }}
            <select v-model.number="pageSize" class="vba-select">
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="0">{{ $t('common.all') }}</option>
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
          <span class="pager-total">{{ $t('printSentLog.pagerTotal', { page, totalPages }) }}</span>
        </div>
      </div>
      </div>

      <p class="sent-note">
        {{ $t('printSentLog.noteOriginalPrefix') }} <code>tbl_sentlog WHERE TIME3 &gt;= DateAdd('h',-48,Now()) ORDER BY TIME3 ASC</code>
        {{ $t('printSentLog.noteOriginalMiddle') }} <code>LoadSent_Last24h</code> {{ $t('printSentLog.noteOriginalSuffix') }}
        {{ $t('printSentLog.noteSortPrefix') }} <strong>{{ $t('printSentLog.noteSortBold') }}</strong> {{ $t('printSentLog.noteSortSuffix') }}
        {{ $t('printSentLog.notePrintStatusPrefix') }} <strong>{{ $t('printSentLog.notePrintStatusBold') }}</strong>{{ $t('printSentLog.notePrintStatusMiddle') }} <code>×n</code> {{ $t('printSentLog.notePrintStatusSuffix') }}
      </p>
    </div>

    <FullscreenButton variant="vba" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import echo from '../services/echo';
import FullscreenButton from '../components/FullscreenButton.vue';
import { parseRackLines } from '../utils/rackParser';
import { writeDispatchSlipToWindow } from '../utils/dispatchSlipPrint';

const { t } = useI18n({ useScope: 'global' });

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

// Map trạng thái -> key dịch (không giữ sẵn chuỗi đã dịch): phải gọi t() lúc render thì đổi
// ngôn ngữ mới cập nhật lại badge trên bảng.
const PRINT_LABEL: Record<string, string> = {
  PRINTED: 'printSentLog.printLabelPrinted',
  PENDING: 'printSentLog.printLabelPending',
  FAILED: 'printSentLog.printLabelFailed',
  CANCELLED: 'printSentLog.printLabelCancelled',
  NONE: 'printSentLog.printLabelNone',
};

const printLabel = (status: string) => t(PRINT_LABEL[status]);

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

/** Thu gọn khối lọc — mặc định mở, giống WeighingHistory.vue. */
const filtersCollapsed = ref(false);

const WINDOW_LABEL_KEY: Record<number, string> = {
  24: 'printSentLog.window24h',
  48: 'printSentLog.window48h',
  168: 'printSentLog.window7d',
  720: 'printSentLog.window30d',
  0: 'common.all',
};

/** Tóm tắt các lọc đang bật — chỉ dùng khi đã thu gọn khối lọc (xem `filtersCollapsed`). */
const activeFilterSummary = computed(() => {
  const parts: string[] = [];
  if (windowHours.value !== 48) parts.push(t(WINDOW_LABEL_KEY[windowHours.value] ?? 'printSentLog.window48h'));
  if (machineFilter.value) parts.push(t('printSentLog.summaryMachine', { value: machineFilter.value }));
  if (tankFilter.value) parts.push(t('printSentLog.summaryTank', { value: tankFilter.value }));
  if (checkFilter.value) parts.push(checkFilter.value === 'YES' ? t('printSentLog.checkYesOption') : t('printSentLog.checkNoOption'));
  if (printFilter.value) parts.push(printLabel(printFilter.value));
  if (stationFilter.value) parts.push(t('printSentLog.summaryStation', { value: stationFilter.value }));
  if (search.value.trim()) parts.push(t('printSentLog.summarySearch', { value: search.value.trim() }));
  return parts.join(' · ');
});

// Ấn vào 1 mẻ (dòng) để xem công thức — xổ ngay dưới dòng (yêu cầu 2026-08-11), không mở
// tab/trang riêng. Đọc trực tiếp raw_qr_dye/raw_qr_chemical đã có sẵn trong từng dòng (cùng
// nguồn dữ liệu đã dựng tem DF_WEIGHING_SLIP thật — xem VbaPrintForm.vue/PrintOrderEntry.vue),
// nên KHÔNG cần gọi API hay giữ trạng thái loading: parse chuỗi là xong ngay trong lúc render.
const expandedId = ref<string | null>(null);
const toggleRow = (d: any) => {
  expandedId.value = expandedId.value === d.id ? null : d.id;
};

// Dispatch có thể tự có raw_qr_dye/raw_qr_chemical (đã ghi lúc gửi lệnh), hoặc phải lấy từ batch
// gốc nếu dòng này chưa từng ghi riêng — cùng thứ tự ưu tiên với onSendPrint() ở
// PrintOrderEntry.vue để hai nơi luôn hiện đúng một dữ liệu.
const dyeLinesOf = (d: any) => parseRackLines(d.raw_qr_dye || d.batch?.raw_qr_dye);
const chemLinesOf = (d: any) => parseRackLines(d.raw_qr_chemical || d.batch?.raw_qr_chemical);

/**
 * In lại — theo ĐÚNG quy ước đã có sẵn trong app, không bịa quy trình mới:
 *  1) In ngay qua trình duyệt bằng chính hàm dựng tem đang dùng ở PrintOrderEntry.vue
 *     (`writeDispatchSlipToWindow`) — người bấm thấy kết quả ngay trên máy họ đang đứng.
 *  2) Gọi `POST /machine-dispatches/{id}/reprint` kèm lý do bắt buộc — đây là hành động
 *     Reprint phải ghi Audit Log bất biến theo CLAUDE.md mục 5. `printed_via_browser: true`
 *     đánh dấu PrintJob PRINTED ngay (giống hệt cách onConfirm() ở PrintOrderEntry.vue làm
 *     cho lần in đầu), KHÔNG đẩy job xuống hàng chờ Local Agent — nếu không Agent sẽ tưởng
 *     còn job PENDING và in TSPL trùng thêm lần nữa xuống máy in vật lý của trạm đó.
 *
 * Lý do nhập ngay trong dòng công thức đang mở (không dùng window.prompt() — bị chặn/im lặng
 * trong một số webview nhúng, xem ghi chú tương tự ở PrintJobHistoryTable.vue).
 */
const reprintReasonId = ref<string | null>(null);
const reprintReason = ref('');
const reprinting = ref<string | null>(null);
const reprintError = ref('');

function askReprint(d: any) {
  reprintReasonId.value = d.id;
  reprintReason.value = '';
  reprintError.value = '';
}

function cancelReprint() {
  reprintReasonId.value = null;
}

async function confirmReprint(d: any) {
  const reason = reprintReason.value.trim();
  if (!reason || reprinting.value) return;

  // window.open() PHẢI đồng bộ ngay trong handler click (đây là click nút "Xác nhận" của
  // ô nhập lý do) — gọi sau bất kỳ await nào thì mất "user gesture", Chrome/Edge chặn popup.
  const win = window.open('', '_blank', 'width=780,height=980');
  reprinting.value = d.id;
  try {
    if (win) {
      await writeDispatchSlipToWindow(win, {
        color: d.batch?.color || '',
        productCode: d.batch?.product_code || '',
        machineCode: d.batch?.machine?.code || '',
        tankCode: d.batch?.tank?.code || '',
        levelCode: d.batch?.level_code || '',
        rawQrDye: d.raw_qr_dye || d.batch?.raw_qr_dye || '',
        rawQrChem: d.raw_qr_chemical || d.batch?.raw_qr_chemical || '',
        batchId: d.batch?.legacy_batch_id || '',
      });
    } else {
      reprintError.value = t('printSentLog.reprintPopupBlocked');
    }

    await axios.post(`/api/machine-dispatches/${d.id}/reprint`, {
      reason,
      printed_via_browser: true,
    });
    reprintReasonId.value = null;
    fetchSentLog(); // cột IN cần cập nhật số lần in (×N) ngay
  } catch (e: any) {
    reprintError.value = e.response?.data?.message || t('printSentLog.reprintFailed');
  } finally {
    reprinting.value = null;
  }
}

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
/* Trang không tự cuộn nữa — chiếm đúng chiều cao vùng nội dung của AppLayout (.content-container,
   flex item có chiều cao xác định) và chia thành cột flex. Chỉ .table-scroll bên trong cuộn, nên
   chân khung (.pager) luôn nằm trong tầm nhìn ở mọi kích cỡ màn hình — cùng cách làm với
   WeighingHistory.vue (xem `.wh-page`/`.wh-table-scroll` ở đó). */
.vba-scroll {
  overflow: hidden;
  padding: 8px;
  background-color: #808080;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.vba-form {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
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
  flex-shrink: 0;
}

.sent-log-title { font-weight: bold; font-size: 9pt; }

.sent-log-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

/* Khối lọc: không co (flex-shrink: 0) trong điều kiện bình thường — phần nhường chỗ khi thiếu
   chiều cao là vùng cuộn của bảng (.table-scroll), không phải các ô lọc đang chọn dở. Chỉ khi màn
   hình quá thấp để lọt cả khối lọc, .filter-bar mới tự cuộn trong chính nó (xem bên dưới). */
.filters {
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex-shrink: 0;
  margin-bottom: 6px;
}

.filters-head {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.filters-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.filters-toggle-icon {
  font-size: 7pt;
  opacity: 0.7;
}

/* Tóm tắt các lọc đang bật, chỉ hiện khi đã thu gọn — nhắc bảng đang bị lọc chứ không phải trống
   trơn, để khỏi tưởng nhầm mất dữ liệu. */
.filters-summary {
  font-size: 7.5pt;
  color: #404040;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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
  min-height: 0;
  overflow-y: auto;
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

/* Khung bảng: viền + bản thân bảng cuộn RIÊNG bên trong (.table-scroll) thay vì kéo dài cả trang
   — nhờ vậy .pager (chân khung, ngoài .table-scroll) luôn đứng nguyên chỗ, không bị đẩy ra ngoài
   màn hình khi danh sách dài. min-height là sàn tối thiểu: đủ chỗ cho tiêu đề + vài dòng + chân
   khung khi màn hình quá thấp và khối lọc đang mở hết cỡ. */
.table-card {
  flex: 1;
  min-height: 140px;
  display: flex;
  flex-direction: column;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  background-color: #ffffff;
}

.table-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

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

.sent-row.row-even td { background-color: #f7f7f7; }

/* Ấn vào mẻ để xem công thức — cả hàng bấm được, không phải chỉ một nút. */
.sent-row {
  cursor: pointer;
}

.sent-row:hover td {
  background-color: #dceaf7;
}

.sent-row.row-open td {
  background-color: #cfe4f7;
}

.row-chevron {
  display: inline-block;
  width: 10px;
  color: #404040;
}

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

/* Dòng công thức xổ ra dưới mẻ đang mở — nền vàng nhạt để tách hẳn khỏi các dòng dữ liệu bình
   thường, không lẫn với vằn ca-rô của bảng chính. */
.recipe-row td {
  background-color: #ffffe0;
  padding: 6px 8px;
  cursor: default;
  white-space: normal;
}

.recipe-box {
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

/* 2 bảng DYE/CHEM cạnh nhau — đúng bố cục "dye stuff information" (trái) / "auxiliary
   information" (phải) trên tem thật (xem VbaPrintForm.vue), chỉ khác là xếp ngang thay vì
   trong khung 306pt cố định vì ở đây có cả chiều rộng bảng SENT LOG để dùng. */
.recipe-cols {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.recipe-tbl {
  flex: 1;
  min-width: 220px;
  max-width: 420px;
  border-collapse: collapse;
  background-color: #ffffff;
}

.recipe-tbl caption {
  text-align: left;
  font-weight: bold;
  padding: 2px 0 4px;
  caption-side: top;
}

.recipe-tbl th {
  background-color: #f0f0f0;
  border: 1px solid #808080;
  padding: 2px 6px;
  text-align: left;
  white-space: nowrap;
}

.recipe-tbl td {
  border: 1px solid #c0c0c0;
  padding: 2px 6px;
  white-space: nowrap;
}

.recipe-tbl .num { text-align: right; }
.recipe-tbl td.bold { font-weight: bold; color: hsl(210, 60%, 30%); }
.recipe-empty { text-align: center; color: #808080; }

.recipe-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 8px;
  padding-top: 6px;
  border-top: 1px solid #d8d0a0;
}

.reprint-prompt {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.reprint-reason-input { width: 260px; }

.reprint-error { color: #a00000; font-weight: bold; }

/* Chân khung .table-card — flex-shrink: 0 để không bao giờ bị co mất: đây chính là phần phải
   luôn nhìn thấy dù màn hình cao hay thấp. Nền + viền trên tách hẳn khỏi bảng phía trên. */
.pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
  padding: 4px 6px;
  border-top: 1px solid #808080;
  background-color: #f0f0f0;
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
  flex-shrink: 0;
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
