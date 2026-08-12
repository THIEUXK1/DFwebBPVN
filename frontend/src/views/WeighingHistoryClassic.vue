<template>
  <div class="wh-page">
    <!-- Khối lọc dính lại khi cuộn (position: sticky, xem CSS .wh-filters) và tự thu gọn được. -->
    <div class="wh-filters">
      <div class="wh-filters-head">
        <button
          type="button"
          class="wh-filters-toggle"
          @click="filtersCollapsed = !filtersCollapsed"
          :aria-expanded="!filtersCollapsed"
          :title="$t('weighingHistory.filtersToggleTitle')"
        >
          <span class="wh-filters-toggle-icon">{{ filtersCollapsed ? '▸' : '▾' }}</span>
          {{ $t('weighingHistory.filtersToggleLabel') }}
        </button>
        <!-- Chỉ hiện khi đã thu gọn: nhắc còn đang lọc gì, tránh người dùng quên mất bảng đang bị
             thu hẹp bởi lọc trong khi đã đóng khối lọc đi cho gọn màn hình. -->
        <span v-if="filtersCollapsed && activeFilterSummary" class="wh-filters-summary">
          {{ activeFilterSummary }}
        </span>
      </div>

      <!-- Cửa sổ bị cắt: phải nói ra ngay trong khối lọc (không phải trôi theo bảng xuống dưới),
           vì đây là lý do trực tiếp khiến bộ lọc "không tìm ra" — người dùng cần thấy nó dù đã
           thu gọn khối lọc hay đang cuộn xuống cuối bảng. -->
      <p v-if="truncated && !loading && !errorMsg" class="wh-msg warn">
        {{ $t('weighingHistory.truncatedPrefix') }}<strong>{{ $t('weighingHistory.truncatedStrong', { count: allRounds.length }) }}</strong>{{ $t('weighingHistory.truncatedSuffix') }}
      </p>

      <div v-show="!filtersCollapsed" class="wh-filters-body">
        <!-- Thống kê cân trùng: mỗi thẻ = "có bao nhiêu ĐƠN bị cân đúng N lần", trong đó hai vòng
             chỉ là cùng một đơn khi trùng cả 4 trường Màu + Mã hàng + Máy + LV (xem khoaTrung).
             Đếm trên toàn bộ cửa sổ dữ liệu đang xem, KHÔNG đếm trên kết quả đã lọc — nếu đếm
             theo kết quả lọc thì con số nhảy loạn theo từng ký tự gõ, mất ý nghĩa cảnh báo. -->
        <div v-if="!loading && !errorMsg && allRounds.length > 0" class="wh-stats">
          <button
            v-for="g in freqGroups"
            :key="g.freq"
            type="button"
            class="wh-stat"
            :class="[freqLevel(g.freq), { active: freqFilter === g.freq }]"
            :aria-pressed="freqFilter === g.freq"
            :title="$t('weighingHistory.statFilterTitle', { freq: g.freq })"
            @click="toggleFreq(g.freq)"
          >
            <span class="wh-stat-num">{{ g.products }}</span>
            <span class="wh-stat-lbl">{{ $t('weighingHistory.statLabelPrefix') }}<strong>{{ g.freq }}{{ $t('weighingHistory.statLabelSuffix') }}</strong></span>
          </button>
          <p v-if="freqGroups.length === 0" class="wh-stat-none">
            {{ $t('weighingHistory.statNoneEmpty', { count: allRounds.length }) }}
          </p>
          <p v-else class="wh-stat-none">
            {{ $t('weighingHistory.statNoneHint') }}
          </p>
        </div>

        <div class="wh-bar">
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldFrom') }}</label>
            <input type="date" v-model="filters.from" @change="reload" />
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldTo') }}</label>
            <input type="date" v-model="filters.to" @change="reload" />
          </div>
          <div class="wh-field wh-grow">
            <!-- Ô này KHÔNG gọi server: lọc ngay trên cửa sổ dữ liệu đã tải, hiện kết quả theo
                 từng ký tự gõ. Muốn với ra ngoài cửa sổ đó thì có nút riêng bên cạnh. -->
            <label>{{ $t('weighingHistory.fieldQuickSearch') }}</label>
            <input type="text" v-model="search" :placeholder="$t('weighingHistory.quickSearchPlaceholder')" />
          </div>
          <!-- Đường thoát khi thứ cần tìm nằm NGOÀI cửa sổ đã tải. Luôn hiện khi có chữ trong ô
               tìm, không đợi tới lúc "không thấy gì": lọc ra 2 dòng cũng không có nghĩa là chỉ có
               2. -->
          <button v-if="search.trim()" class="wh-btn" @click="searchOnServer" :disabled="loading">
            {{ $t('weighingHistory.searchOnServerButton') }}
          </button>
          <button class="wh-btn ghost" @click="resetFilters" :disabled="loading">{{ $t('weighingHistory.clearFiltersButton') }}</button>
          <button class="wh-btn ghost" @click="reload" :disabled="loading" :title="$t('weighingHistory.refreshButtonTitle')">
            {{ $t('weighingHistory.refreshButtonLabel') }}
          </button>
        </div>

        <!-- Lọc theo từng CỘT — cũng chạy thuần trên cửa sổ đã tải như ô tìm nhanh, gõ/chọn tới
             đâu bảng đổi tới đó. Màu và Mã hàng để ô gõ kèm gợi ý (datalist) vì số giá trị nhiều;
             Máy và LV để ô chọn vì tập giá trị hữu hạn, chọn nhanh hơn gõ. -->
        <div class="wh-bar wh-cols">
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldColor') }}</label>
            <input type="text" v-model="col.color" list="wh-colors" :placeholder="$t('weighingHistory.colFilterPlaceholder')" />
            <datalist id="wh-colors">
              <option v-for="o in colorOptions" :key="o" :value="o" />
            </datalist>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldProduct') }}</label>
            <input type="text" v-model="col.product" list="wh-products" :placeholder="$t('weighingHistory.colFilterPlaceholder')" />
            <datalist id="wh-products">
              <option v-for="o in productOptions" :key="o" :value="o" />
            </datalist>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldMachine') }}</label>
            <select v-model="col.machine">
              <option value="">{{ $t('weighingHistory.optionAllMachines') }}</option>
              <option v-for="o in machineOptions" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldLv') }}</label>
            <select v-model="col.lv">
              <option value="">{{ $t('weighingHistory.optionAllLv') }}</option>
              <option v-for="o in lvOptions" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldScale') }}</label>
            <select v-model="col.scale">
              <option value="">{{ $t('weighingHistory.optionBothScales') }}</option>
              <option value="LARGE">{{ $t('weighingHistory.optionScaleLarge') }}</option>
              <option value="SMALL">{{ $t('weighingHistory.optionScaleSmall') }}</option>
              <option value="NONE">{{ $t('weighingHistory.optionScaleNone') }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldResult') }}</label>
            <select v-model="col.result">
              <option value="">{{ $t('weighingHistory.optionAllResults') }}</option>
              <option value="BAD">{{ $t('weighingHistory.optionResultBad') }}</option>
              <option value="OK">{{ $t('weighingHistory.optionResultOk') }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>{{ $t('weighingHistory.fieldPageSize') }}</label>
            <select v-model.number="pageSize">
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="0">{{ $t('weighingHistory.optionAll') }}</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <p v-if="loading" class="wh-msg">{{ $t('weighingHistory.loading') }}</p>
    <p v-else-if="errorMsg" class="wh-msg err">{{ errorMsg }}</p>

    <template v-else>
      <!-- Cảnh báo cửa sổ bị cắt giờ nằm trong khối lọc sticky ở trên (xem "Cửa sổ bị cắt") để
           luôn nhìn thấy khi cuộn — chỗ này chỉ còn thông báo tìm-trên-toàn-bộ-lịch-sử. -->
      <p v-if="serverSearch" class="wh-msg warn">
        {{ $t('weighingHistory.resultPrefix') }}<strong>{{ $t('weighingHistory.resultStrong') }}</strong>{{ $t('weighingHistory.resultMiddle') }}{{ serverSearch }}{{ $t('weighingHistory.resultSuffix') }}
        <button class="wh-link" @click="resetFilters">{{ $t('weighingHistory.backToRecentButton') }}</button>
      </p>

      <!-- Đang lọc theo thẻ: bảng KHÔNG còn xếp thuần theo thời gian nữa mà xếp theo cụm trùng.
           Phải nói ra, nếu không người dùng sẽ tưởng cột thời gian bị sai thứ tự. -->
      <p v-if="freqFilter !== null && filtered.length > 0" class="wh-msg group-note">
        {{ $t('weighingHistory.groupNotePrefix') }}<strong>{{ $t('weighingHistory.groupNoteStrong', { count: groupCount }) }}</strong>{{ $t('weighingHistory.groupNoteSuffix', { freq: freqFilter, count: filtered.length }) }}
        <button class="wh-link" @click="toggleFreq(freqFilter)">{{ $t('weighingHistory.clearFreqFilterButton') }}</button>
      </p>

      <p v-if="filtered.length === 0" class="wh-msg">
        <template v-if="freqFilter !== null">
          {{ $t('weighingHistory.emptyFreqPrefix', { freq: freqFilter }) }}
          <button class="wh-link" @click="toggleFreq(freqFilter)">{{ $t('weighingHistory.emptyFreqLink') }}</button>.
        </template>
        <template v-else-if="search.trim()">
          {{ $t('weighingHistory.emptySearchPrefix', { query: search, count: allRounds.length }) }}<strong>{{ $t('weighingHistory.emptySearchStrong') }}</strong>{{ $t('weighingHistory.emptySearchSuffix') }}
        </template>
        <template v-else>{{ $t('weighingHistory.emptyDefault') }}</template>
      </p>
    </template>

    <!-- Khung riêng cho bảng: bản thân bảng cuộn BÊN TRONG khung (.wh-table-scroll) thay vì kéo
         dài cả trang, nên phần chuyển trang ở chân khung luôn nằm trong tầm nhìn — không phải
         cuộn hết 200 dòng mới thấy nút "Trang sau". -->
    <div v-if="!loading && !errorMsg && filtered.length > 0" class="wh-table-card">
    <div class="wh-table-scroll">
    <table class="wh-table">
      <thead>
        <tr>
          <th class="c-time">{{ $t('weighingHistory.colTime') }}</th>
          <th class="c-scale">{{ $t('weighingHistory.colScale') }}</th>
          <th>{{ $t('weighingHistory.colColor') }}</th>
          <th>{{ $t('weighingHistory.colProduct') }}</th>
          <th>{{ $t('weighingHistory.colMachine') }}</th>
          <th>{{ $t('weighingHistory.colLv') }}</th>
          <th class="c-num">{{ $t('weighingHistory.colLineCount') }}</th>
          <th class="c-num">{{ $t('weighingHistory.colAccepted') }}</th>
          <th class="c-num">{{ $t('weighingHistory.colRejected') }}</th>
          <th class="c-act"></th>
        </tr>
      </thead>
      <tbody>
        <template v-for="{ job, band, first, seq, size } in paged" :key="job.id">
          <tr
            class="wh-row"
            :class="[band, { open: expanded === job.id, 'grp-start': first }]"
            @click="toggle(job.id)"
          >
            <td class="c-time">
              <!-- Số thứ tự trong cụm: nhìn là biết ngay đây là lần cân thứ mấy của cùng một đơn,
                   không phải đếm dòng bằng mắt. -->
              <span v-if="band" class="grp-seq">{{ seq }}/{{ size }}</span>
              {{ formatTime(job.completed_at) }}
            </td>
            <!-- Mã trạm để ở `title`: cột phải đọc được từ xa nên chỉ để đúng hai chữ TO/NHỎ,
                 còn "cân ở máy nào" là thứ chỉ cần khi soi kỹ một dòng. -->
            <td class="c-scale">
              <span
                class="scale-tag"
                :class="scaleClass(job)"
                :title="job.workstation_code ? $t('weighingHistory.scaleTitleKnown', { code: job.workstation_code }) : $t('weighingHistory.scaleTitleUnknown')"
              >{{ scaleLabel(job) }}</span>
            </td>
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
                :title="$t('weighingHistory.reprintButtonTitle')"
                @click.stop="reprint(job)"
              >
                {{ reprintingId === job.id ? '…' : '🖨' }}
              </button>
              <span class="chev">{{ expanded === job.id ? '▲' : '▼' }}</span>
            </td>
          </tr>

          <tr v-if="expanded === job.id" :key="job.id + '-d'" class="wh-detail-row" :class="band">
            <td colspan="10">
              <table class="wh-detail">
                <thead>
                  <tr>
                    <th class="c-num">#</th>
                    <th>RACK</th>
                    <th>DYE CODE</th>
                    <th class="c-num">{{ $t('weighingHistory.detailColWeightTarget') }}</th>
                    <th class="c-num">{{ $t('weighingHistory.detailColWeightActual') }}</th>
                    <th class="c-num">{{ $t('weighingHistory.detailColDeviation') }}</th>
                    <th>{{ $t('weighingHistory.detailColResult') }}</th>
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
                        {{ item.process_status === 'ACCEPTED' ? $t('weighingHistory.resultAccepted') : $t('weighingHistory.resultRejected') }}
                      </span>
                      <span v-if="item.actual_weight === null" class="note">{{ $t('weighingHistory.notWeighedYet') }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
    </div>

    <!-- Phân trang thuần JS trên dữ liệu đã tải — đổi trang không gọi server, không có trạng thái
         chờ nào cả. Nằm NGOÀI .wh-table-scroll (chân khung, không cuộn theo bảng) nên luôn hiện. -->
    <div class="wh-pager">
      <span class="wh-pager-info">
        {{ $t('weighingHistory.pagerPrefix') }}<strong>{{ pageStart + 1 }}–{{ pageStart + paged.length }}</strong>{{ $t('weighingHistory.pagerSuffix', { total: filtered.length }) }}<template v-if="hasRowFilter">{{ $t('weighingHistory.pagerMatchedSuffix') }}</template>
      </span>
      <div class="wh-pager-ctrl">
        <button class="wh-btn ghost" :disabled="page <= 1" @click="page = 1" :title="$t('weighingHistory.firstPageTitle')">«</button>
        <button class="wh-btn ghost" :disabled="page <= 1" @click="page -= 1">{{ $t('weighingHistory.prevPageLabel') }}</button>
        <button
          v-for="p in pageWindow"
          :key="p"
          class="wh-btn ghost wh-pg-num"
          :class="{ active: p === page }"
          @click="page = p"
        >{{ p }}</button>
        <button class="wh-btn ghost" :disabled="page >= lastPage" @click="page += 1">{{ $t('weighingHistory.nextPageLabel') }}</button>
        <button class="wh-btn ghost" :disabled="page >= lastPage" @click="page = lastPage" :title="$t('weighingHistory.lastPageTitle')">»</button>
        <span class="wh-pager-total">{{ $t('weighingHistory.pageOfLabel', { page, last: lastPage }) }}</span>
      </div>
    </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// Bản "giao diện cổ điển" của WeighingHistory.vue (yêu cầu 2026-08-11) — CHỦ Ý KHÔNG dùng
// composable/component dùng chung: đây là bản re-skin độc lập theo lựa chọn của người dùng (route
// riêng /weighing-history/classic để dễ so sánh/rollback), giống cấu trúc ChemicalCall.vue vs
// ChemicalCallClassic.vue trong app này. Logic/script bên dưới SAO Y NGUYÊN từ WeighingHistory.vue,
// chỉ khác toàn bộ <style> ở dưới (bảng màu Windows cổ điển của MSForms, xem PrintSentLog.vue).
//
// CẢNH BÁO BẢO TRÌ: vì không dùng chung composable, mọi sửa đổi NGHIỆP VỤ ở WeighingHistory.vue
// (lọc, phân trang, gom cụm trùng, in lại...) phải tự tay chép lại sang đây — file này không tự
// đồng bộ. Nếu sau này có ≥2 bản skin nữa dùng chung logic thì mới đáng tách composable.

// Lịch sử cân của /weighing-station-v2.
//
// Mỗi dòng là MỘT VÒNG CÂN (một WeighingJob đã COMPLETED), KHÔNG phải một lô. Từ 2026-08-01
// quét lại mã sau khi đã SAVE sẽ mở một vòng cân mới thay vì tái dùng vòng cũ, nên một lô có
// thể xuất hiện nhiều lần ở đây — đó là chủ ý: các lần cân lại chính là thứ cần nhìn thấy nhất
// khi đối soát, gom theo lô sẽ giấu mất chúng.

import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
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
const WINDOW_LIMIT = 200;

const { t } = useI18n({ useScope: 'global' });

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
/** Đang lọc theo tần suất cân của mã hàng (null = không lọc). Xem `freqGroups`. */
const freqFilter = ref<number | null>(null);

/**
 * Lọc theo từng cột. Cũng chỉ chạm `allRounds`, không gọi server — cùng lý do với ô tìm nhanh.
 * `color`/`product` so khớp CHỨA (gõ một phần là ra), `machine`/`lv` so khớp ĐÚNG BẰNG vì lấy
 * thẳng từ danh sách giá trị có thật nên không có chuyện gõ nhầm nửa chừng.
 */
const col = reactive({ color: '', product: '', machine: '', lv: '', result: '', scale: '' });

const page = ref(1);
/** 0 = không chia trang (xem tất cả). */
const pageSize = ref(20);

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
    // Gõ thẳng "cân to" / "cân nhỏ" vào ô tìm nhanh cũng ra — không bắt người dùng nhớ rằng
    // muốn lọc theo loại cân thì phải xuống ô chọn riêng ở hàng dưới.
    job.workstation_code,
    job.scale_kind === 'LARGE'
      ? t('weighingHistory.scaleSearchAliasLarge')
      : job.scale_kind === 'SMALL' ? t('weighingHistory.scaleSearchAliasSmall') : '',
  ].filter(Boolean).join(' ').toLowerCase();
  // Khóa trùng tính sẵn luôn tại đây, cùng lý do với `__hay` ở trên: để trong computed thì mỗi
  // ký tự gõ vào ô tìm là tính lại cho toàn bộ cửa sổ dữ liệu.
  job.__key = khoaTrung(job);
  return job;
}

/**
 * Khóa nhận dạng "trùng nhau": ĐÚNG 4 TRƯỜNG Màu + Mã hàng + Máy + LV — cũng chính là 4 cột
 * người dùng nhìn thấy trên bảng.
 *
 * Trùng cả 4 nghĩa là cùng một việc đã bị cân đi cân lại. Khác một trường bất kỳ (khác máy, khác
 * LV) là một việc riêng, không tính trùng.
 *
 * Trả về null khi thiếu Màu hoặc Mã hàng — không có định danh thì không đủ căn cứ khẳng định
 * trùng, bỏ qua chứ không dồn vào một nhóm "—" giả. Riêng Máy và LV nếu trống thì coi chính cái
 * trống đó là một giá trị: hai vòng cùng màu cùng mã cùng bỏ trống LV vẫn là trùng nhau.
 */
function khoaTrung(job: any): string | null {
  const color = job.batch?.color;
  const code = job.batch?.product_code;
  if (!color || !code) return null;

  const machine = job.batch?.machine?.code || '';
  const lv = job.batch?.level_code || '';

  return `${color}~${code}~${machine}~${lv}`;
}

/**
 * Số vòng cân của từng đơn (theo khóa `__key`) trong cửa sổ đang xem.
 *
 * Đếm trên `allRounds` chứ không phải `filtered`: đây là nguồn của cả con số trên thẻ lẫn điều
 * kiện lọc, nếu đếm trên dữ liệu đã lọc thì bấm vào thẻ "3 lần" xong tần suất tự đổi thành 3/3 và
 * bảng tự co lại vòng vòng.
 */
const productFreq = computed(() => {
  const m = new Map<string, number>();
  for (const job of allRounds.value) {
    if (!job.__key) continue;
    m.set(job.__key, (m.get(job.__key) || 0) + 1);
  }
  return m;
});

/** Mỗi phần tử = một thẻ thống kê: có `products` đơn bị cân đúng `freq` lần. */
const freqGroups = computed(() => {
  const byFreq = new Map<number, number>();
  for (const n of productFreq.value.values()) {
    if (n < 2) continue; // Cân 1 lần là bình thường, không phải thứ cần đánh động.
    byFreq.set(n, (byFreq.get(n) || 0) + 1);
  }
  return [...byFreq.entries()]
    .sort((a, b) => a[0] - b[0])
    .map(([freq, products]) => ({ freq, products }));
});

/** Thang màu cảnh báo: 2 lần còn chấp nhận được, 3 lần đáng ngờ, từ 4 lần là đỏ. */
function freqLevel(freq: number): string {
  if (freq >= 4) return 'lv-danger';
  if (freq === 3) return 'lv-warn';
  return 'lv-mild';
}

function toggleFreq(freq: number) {
  freqFilter.value = freqFilter.value === freq ? null : freq;
  page.value = 1;
}

/**
 * Danh sách giá trị cho các ô lọc theo cột — lấy từ chính cửa sổ dữ liệu đang xem, không phải từ
 * danh mục master: đưa ra lựa chọn không có dòng nào tương ứng chỉ tổ làm người dùng chọn xong
 * thấy bảng trống.
 */
function uniqSorted(vals: any[]): string[] {
  const set = new Set(vals.map((v) => String(v ?? '').trim()).filter(Boolean));
  return [...set].sort((a, b) => a.localeCompare(b, 'vi', { numeric: true }));
}

const colorOptions = computed(() => uniqSorted(allRounds.value.map((j) => j.batch?.color)));
const productOptions = computed(() => uniqSorted(allRounds.value.map((j) => j.batch?.product_code)));
const machineOptions = computed(() => uniqSorted(allRounds.value.map((j) => j.batch?.machine?.code)));
const lvOptions = computed(() => uniqSorted(allRounds.value.map((j) => j.batch?.level_code)));

/** Có đang lọc dòng nào không — dùng cho chữ "khớp" ở thanh phân trang. */
const hasRowFilter = computed(
  () =>
    !!search.value.trim() ||
    freqFilter.value !== null ||
    !!col.color || !!col.product || !!col.machine || !!col.lv || !!col.result || !!col.scale
);

/**
 * Thu gọn khối bộ lọc (thống kê trùng + thanh ngày/tìm nhanh + lọc theo cột) — mặc định mở, người
 * dùng tự thu gọn khi cần thêm chỗ nhìn bảng, nhất là màn hình thấp/tablet đứng ngang cân.
 */
const filtersCollapsed = ref(false);
/** Đang lọc khác rỗng gì không, kể cả khoảng ngày — dùng làm tóm tắt khi đã thu gọn. */
const activeFilterSummary = computed(() => {
  const parts: string[] = [];
  if (filters.from || filters.to) parts.push(`${filters.from || '…'} → ${filters.to || '…'}`);
  if (search.value.trim()) parts.push(t('weighingHistory.summarySearch', { query: search.value.trim() }));
  if (freqFilter.value !== null) parts.push(t('weighingHistory.summaryFreq', { freq: freqFilter.value }));
  if (col.color) parts.push(t('weighingHistory.summaryColor', { value: col.color }));
  if (col.product) parts.push(t('weighingHistory.summaryProduct', { value: col.product }));
  if (col.machine) parts.push(t('weighingHistory.summaryMachine', { value: col.machine }));
  if (col.lv) parts.push(t('weighingHistory.summaryLv', { value: col.lv }));
  if (col.scale) parts.push(t('weighingHistory.summaryScale'));
  if (col.result) parts.push(col.result === 'BAD' ? t('weighingHistory.summaryResultBad') : t('weighingHistory.summaryResultOk'));
  return parts.join(' · ');
});


/**
 * Nhãn hai chữ cho cột Cân. `scale_kind` do server suy ra từ trạm đã cân (xem
 * WeighingJobController::suyLoaiCan) — null nghĩa là không đủ căn cứ, hiện "—" chứ không đoán bừa.
 */
function scaleLabel(job: any): string {
  if (job.scale_kind === 'LARGE') return t('weighingHistory.scaleTagLarge');
  if (job.scale_kind === 'SMALL') return t('weighingHistory.scaleTagSmall');
  return t('weighingHistory.scaleTagUnknown');
}

function scaleClass(job: any): string {
  if (job.scale_kind === 'LARGE') return 'sc-large';
  if (job.scale_kind === 'SMALL') return 'sc-small';
  return 'sc-unknown';
}

const filtered = computed(() => {
  let rows = allRounds.value;

  if (freqFilter.value !== null) {
    const f = freqFilter.value;
    rows = rows.filter((job) => !!job.__key && productFreq.value.get(job.__key) === f);
  }

  const color = col.color.trim().toLowerCase();
  if (color) rows = rows.filter((job) => String(job.batch?.color ?? '').toLowerCase().includes(color));

  const product = col.product.trim().toLowerCase();
  if (product) {
    rows = rows.filter((job) => String(job.batch?.product_code ?? '').toLowerCase().includes(product));
  }

  // 'NONE' = các vòng không suy ra được trạm nào (trạm đã xóa / trạm đăng ký thiếu capability) —
  // để chọn được vì đó chính là nhóm cần đi tra lại, không được lẫn vào bên nào.
  if (col.scale === 'NONE') rows = rows.filter((job) => !job.scale_kind);
  else if (col.scale) rows = rows.filter((job) => job.scale_kind === col.scale);

  if (col.machine) rows = rows.filter((job) => (job.batch?.machine?.code ?? '') === col.machine);
  if (col.lv) rows = rows.filter((job) => (job.batch?.level_code ?? '') === col.lv);

  if (col.result === 'BAD') rows = rows.filter((job) => Number(job.rejected_count) > 0);
  else if (col.result === 'OK') rows = rows.filter((job) => Number(job.rejected_count) === 0);

  const q = search.value.trim().toLowerCase();
  if (!q) return rows;
  // Nhiều từ = phải khớp TẤT CẢ, để gõ "đỏ VD10" lọc được đúng cả máy lẫn màu.
  const tokens = q.split(/\s+/);
  return rows.filter((job) => tokens.every((t) => (job.__hay || '').includes(t)));
});

/**
 * Xếp lại các vòng đã lọc thành từng CỤM TRÙNG rồi mới đưa ra bảng.
 *
 * Danh sách gốc xếp theo thời gian, nên hai vòng của cùng một đơn có thể cách nhau vài chục dòng —
 * đúng thứ người dùng đang cần so lại thì nằm xa nhau nhất. Khi đã bấm vào thẻ tần suất thì mục
 * đích không còn là xem theo dòng thời gian nữa mà là đối chiếu từng cụm, nên ở chế độ đó đổi
 * luôn cách xếp (và có dòng chú thích trên bảng nói rõ điều này).
 *
 * Thứ tự cụm bám theo thứ tự xuất hiện trong danh sách gốc (Map giữ thứ tự chèn), tức cụm có vòng
 * mới nhất đứng trước — không đảo lộn cảm giác "mới nhất ở trên". Trong mỗi cụm cũng vẫn là mới
 * trước cũ sau.
 *
 * `band` là tên lớp nền luân phiên để hai cụm cạnh nhau không dính vào nhau về mặt thị giác;
 * chuỗi rỗng nghĩa là không ở chế độ gom cụm.
 */
type DongBang = { job: any; band: string; first: boolean; seq: number; size: number };

const grouped = computed<DongBang[]>(() => {
  if (freqFilter.value === null) {
    return filtered.value.map((job) => ({ job, band: '', first: false, seq: 0, size: 0 }));
  }

  const cum = new Map<string, any[]>();
  for (const job of filtered.value) {
    const g = cum.get(job.__key);
    if (g) g.push(job);
    else cum.set(job.__key, [job]);
  }

  const out: DongBang[] = [];
  let i = 0;
  for (const g of cum.values()) {
    const band = i % 2 === 0 ? 'grp-a' : 'grp-b';
    g.forEach((job, k) => out.push({ job, band, first: k === 0, seq: k + 1, size: g.length }));
    i++;
  }
  return out;
});

/** Số ĐƠN (không phải số vòng) đang hiện — dùng cho dòng chú thích trên bảng. */
const groupCount = computed(() => {
  if (freqFilter.value === null) return 0;
  return new Set(filtered.value.map((job) => job.__key)).size;
});

/**
 * Chia trang. Ở chế độ gom cụm thì cắt theo RANH GIỚI CỤM chứ không cắt cứng mỗi `pageSize` dòng:
 * một cụm bị xé đúng chỗ chuyển trang là mất sạch ý nghĩa của việc xếp liền nhau — người dùng
 * thấy 2 vòng ở cuối trang này, 2 vòng còn lại ở đầu trang sau, không đối chiếu được gì.
 * Đổi lại, trang ở chế độ này có thể dài hơn `pageSize` một chút (tối đa là dư một cụm).
 */
const pages = computed<DongBang[][]>(() => {
  const rows = grouped.value;
  const size = pageSize.value;
  const out: DongBang[][] = [];

  if (size === 0) return [rows];

  if (freqFilter.value === null) {
    for (let i = 0; i < rows.length; i += size) out.push(rows.slice(i, i + size));
    return out.length ? out : [[]];
  }

  let cur: DongBang[] = [];
  for (const r of rows) {
    if (r.first && cur.length >= size) {
      out.push(cur);
      cur = [];
    }
    cur.push(r);
  }
  if (cur.length) out.push(cur);
  return out.length ? out : [[]];
});

const lastPage = computed(() => pages.value.length);
const paged = computed(() => pages.value[page.value - 1] || []);

/**
 * Số dòng đứng trước trang hiện tại. Cộng dồn chiều dài các trang trước chứ KHÔNG lấy
 * `(page-1) * pageSize`: ở chế độ gom cụm các trang dài ngắn khác nhau, nhân ra sẽ lệch.
 */
const pageStart = computed(() =>
  pages.value.slice(0, page.value - 1).reduce((n, p) => n + p.length, 0)
);

/** Tối đa 7 nút số quanh trang hiện tại, để thanh phân trang không dài vô tận. */
const pageWindow = computed(() => {
  const total = lastPage.value;
  const span = Math.min(7, total);
  let start = Math.max(1, page.value - Math.floor(span / 2));
  start = Math.min(start, total - span + 1);
  return Array.from({ length: span }, (_, i) => start + i);
});

// Lọc lại làm số trang co lại — đang đứng ở trang 5 mà chỉ còn 2 trang thì bảng trống trơn.
watch(filtered, () => {
  if (page.value > lastPage.value) page.value = 1;
});

// Đổi bộ lọc cột hay số dòng mỗi trang thì về trang 1: giữ nguyên trang cũ hầu như luôn rơi ra
// ngoài kết quả mới.
watch([() => col.color, () => col.product, () => col.machine, () => col.lv, () => col.result, () => col.scale, pageSize], () => {
  page.value = 1;
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
  // Cửa sổ dữ liệu đổi thì tần suất tính lại từ đầu — giữ lại lựa chọn cũ sẽ thành lọc theo một
  // con số không còn thẻ nào tương ứng trên màn hình. Bộ lọc cột cũng vậy: mã máy/LV vừa chọn có
  // thể không còn dòng nào trong cửa sổ mới, người dùng chỉ thấy bảng trống mà không rõ vì sao.
  freqFilter.value = null;
  clearColFilters();
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
    errorMsg.value = err.response?.data?.message || t('weighingHistory.errorLoadFailed');
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

function clearColFilters() {
  col.color = '';
  col.product = '';
  col.machine = '';
  col.lv = '';
  col.result = '';
  col.scale = '';
}

function resetFilters() {
  filters.from = '';
  filters.to = '';
  search.value = '';
  clearColFilters();
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
    alert(err.response?.data?.message || t('weighingHistory.errorReprintFailed'));
  } finally {
    reprintingId.value = null;
  }
}

onMounted(() => load());
</script>

<style scoped>
/* ============================================================================================
 * GIAO DIỆN CỔ ĐIỂN (Windows Classic / MSForms) — cùng bảng màu với PrintSentLog.vue,
 * PrintOrderEntry.vue, VbaPrintForm.vue: nền xám #808080, mặt hộp thoại #f0f0f0, viền 3D
 * (sáng trên-trái/tối dưới-phải = nổi, ngược lại = lõm), font Tahoma 8pt, KHÔNG bo góc,
 * KHÔNG đổ bóng, KHÔNG dark mode (Windows 95 không có khái niệm đó).
 *
 * Toàn bộ SELECTOR giữ nguyên tên/cấu trúc như WeighingHistory.vue để dễ đối chiếu — chỉ giá trị
 * màu/viền/font đổi. Các thuộc tính layout (display/flex/position/overflow/z-index/kích thước)
 * KHÔNG đổi, vì kiến trúc cuộn-trong-khung/tiêu đề dính/chân khung cố định vẫn cần y nguyên.
 * ============================================================================================ */

.wh-page {
  padding: 10px;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  background-color: #808080;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  color: #000000;
}

.wh-filters {
  display: flex;
  flex-direction: column;
  min-height: 0;
  flex-shrink: 0;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  box-shadow: 0 0 0 1px #404040;
  padding: 8px;
  margin-bottom: 8px;
}

.wh-filters-body {
  min-height: 0;
  overflow-y: auto;
}

.wh-filters-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-bottom: 6px;
}

.wh-filters-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  color: #000000;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  font-weight: bold;
  cursor: pointer;
}

.wh-filters-toggle:hover {
  background-color: #e4e4e4;
}

.wh-filters-toggle:active {
  border-color: #808080 #ffffff #ffffff #808080;
}

.wh-filters-toggle-icon {
  font-size: 8pt;
}

.wh-filters-summary {
  font-size: 8pt;
  color: #404040;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wh-stats {
  display: flex;
  align-items: stretch;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 8px;
}

.wh-stat {
  display: flex;
  align-items: baseline;
  gap: 6px;
  padding: 5px 10px;
  background-color: #f0f0f0;
  border: 1px solid #808080;
  color: #000000;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  cursor: pointer;
}

.wh-stat:hover {
  background-color: #e8e8e8;
}

.wh-stat-num {
  font-size: 13pt;
  font-weight: bold;
  font-variant-numeric: tabular-nums;
}

.wh-stat-lbl {
  font-size: 8pt;
}

/* Bảng màu cảnh báo phẳng (không tint trong suốt) — cùng gam với badge trạng thái in ở
   PrintSentLog.vue (.pb-PENDING/.pb-FAILED) để hai màn Classic dùng chung ngôn ngữ màu. */
.wh-stat.lv-mild {
  background-color: #dce6f7;
  border-color: #7a8fae;
}

.wh-stat.lv-warn {
  background-color: #fff2cc;
  border-color: #b8860b;
}

.wh-stat.lv-danger {
  background-color: #f8d0d0;
  border-color: #a00000;
}

/* Đang lọc theo thẻ nào phải nhìn ra ngay — đổi sang viền LÕM (giống nút bấm giữ), thay vì
   viền đậm màu như bản hiện đại. */
.wh-stat.active {
  border-width: 1px;
  border-color: #808080 #ffffff #ffffff #808080;
  box-shadow: inset 1px 1px 0 #606060;
}

.wh-stat-none {
  margin: 0;
  align-self: center;
  font-size: 8pt;
  color: #404040;
}

.wh-bar {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.wh-field {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.wh-grow {
  flex: 1;
  min-width: 200px;
}

.wh-field label {
  font-size: 8pt;
  font-weight: bold;
}

.wh-field input,
.wh-field select {
  padding: 2px 4px;
  background-color: #ffffff;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
  color: #000000;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  outline: none;
}

.wh-cols .wh-field {
  flex: 1;
  min-width: 120px;
}

/* Mọi nút đều đồng dạng "raised" cổ điển — Windows 95 không phân biệt nút chính/phụ bằng màu. */
.wh-btn {
  padding: 3px 12px;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  color: #000000;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  font-weight: bold;
  cursor: pointer;
}

.wh-btn:active:not(:disabled) {
  border-color: #808080 #ffffff #ffffff #808080;
}

.wh-btn.ghost {
  font-weight: normal;
}

.wh-btn:disabled {
  color: #808080;
  text-shadow: 1px 1px 0 #ffffff;
  cursor: not-allowed;
}

.wh-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
  padding: 6px 8px;
  background-color: #f0f0f0;
  border-top: 1px solid #808080;
  font-size: 8pt;
}

.wh-pager-ctrl {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.wh-pager-ctrl .wh-btn {
  padding: 2px 8px;
}

.wh-pg-num {
  min-width: 26px;
  padding: 2px 6px;
  font-variant-numeric: tabular-nums;
}

.wh-pg-num.active {
  border-color: #808080 #ffffff #ffffff #808080;
  background-color: #dcdcdc;
}

.wh-pager-total {
  margin-left: 4px;
  color: #404040;
}

.wh-msg {
  padding: 20px;
  text-align: center;
  color: #404040;
  flex-shrink: 0;
}

.wh-msg.err {
  color: #a00000;
  font-weight: bold;
}

/* Khung bảng: viền LÕM (như một listbox/ô nhập cổ điển) thay vì bo góc + đổ bóng hiện đại. */
.wh-table-card {
  flex: 1;
  min-height: 160px;
  display: flex;
  flex-direction: column;
  background-color: #ffffff;
  border: 1px solid;
  border-color: #808080 #ffffff #ffffff #808080;
}

.wh-table-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
}

.wh-table {
  width: 100%;
  border-collapse: collapse;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #ffffff;
}

.wh-table th,
.wh-table td {
  padding: 3px 6px;
  border: 1px solid #c0c0c0;
  text-align: left;
}

.wh-table thead th {
  font-size: 8pt;
  font-weight: bold;
  color: #000000;
  border: 1px solid #808080;
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: #f0f0f0;
}

.wh-row {
  cursor: pointer;
}

/* Vệt chọn kiểu Access/Excel cổ điển — vàng nhạt, KHÔNG cần override màu chữ con bên trong
   (khác lựa chọn xanh navy #000080 của Windows Explorer: navy sẽ nuốt mất màu .ok/.bad/.scale-tag
   vốn đã có nền/màu riêng, phải ghi đè từng cái mới đọc được — vàng nhạt tránh hẳn vụ đó). */
.wh-row:hover td,
.wh-row.open td {
  background-color: #ffffcc;
}

/* Nền luân phiên theo CỤM (không phải theo dòng chẵn/lẻ) — trắng/xám nhạt cổ điển thay vì tint
   xanh trong suốt của bản hiện đại. */
.wh-table tbody tr.grp-a td {
  background-color: #ffffff;
}

.wh-table tbody tr.grp-b td {
  background-color: #f0f0f0;
}

.wh-table tbody tr.wh-row.grp-a:hover td,
.wh-table tbody tr.wh-row.grp-b:hover td,
.wh-table tbody tr.wh-row.grp-a.open td,
.wh-table tbody tr.wh-row.grp-b.open td {
  background-color: #ffffcc;
}

/* Vạch mở đầu mỗi cụm — đường kẻ đen đậm kiểu lưới Excel, không phải viền xám mảnh. */
.wh-table tbody tr.grp-start td {
  border-top: 2px solid #000000;
}

.grp-seq {
  display: inline-block;
  min-width: 28px;
  margin-right: 6px;
  padding: 0 4px;
  background-color: #e4e4e4;
  border: 1px solid #808080;
  font-size: 7.5pt;
  font-weight: bold;
  font-variant-numeric: tabular-nums;
  text-align: center;
}

.wh-msg.group-note {
  padding: 6px 10px;
  margin-bottom: 8px;
  text-align: left;
  background-color: #dce6f7;
  border: 1px solid #7a8fae;
  color: #000000;
  font-size: 8pt;
}

.c-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.c-time {
  white-space: nowrap;
}

.c-scale {
  width: 58px;
  white-space: nowrap;
}

.scale-tag {
  display: inline-block;
  min-width: 38px;
  padding: 1px 5px;
  border: 1px solid #808080;
  font-size: 7.5pt;
  font-weight: bold;
  text-align: center;
}

.scale-tag.sc-large {
  background-color: #d7d3ff;
  color: #241a70;
}

.scale-tag.sc-small {
  background-color: #cdeeff;
  color: #0b5f8a;
}

.scale-tag.sc-unknown {
  background-color: #e4e4e4;
  color: #606060;
}

.c-act {
  width: 66px;
  text-align: right;
  white-space: nowrap;
}

.chev {
  margin-left: 6px;
  color: #606060;
}

.wh-print {
  padding: 2px 6px;
  background-color: #f0f0f0;
  border: 1px solid;
  border-color: #ffffff #808080 #808080 #ffffff;
  color: #000000;
  font-size: 8pt;
  line-height: 1;
  cursor: pointer;
}

.wh-print:active:not(:disabled) {
  border-color: #808080 #ffffff #ffffff #808080;
}

.wh-print:disabled {
  color: #808080;
  cursor: wait;
}

/* Đường dẫn kiểu trang web cổ điển — xanh gạch chân, không phải nút phẳng hiện đại. */
.wh-link {
  border: none;
  background: none;
  padding: 0;
  color: #0000ee;
  font: inherit;
  font-weight: bold;
  text-decoration: underline;
  cursor: pointer;
}

.wh-msg.warn {
  padding: 6px 10px;
  margin-bottom: 8px;
  text-align: left;
  background-color: #fff2cc;
  border: 1px solid #b8860b;
  color: #000000;
  font-size: 8pt;
}

.strong {
  font-weight: bold;
}

.ok {
  color: #008000;
}

.bad {
  color: #a00000;
  font-weight: bold;
}

.wh-detail-row td {
  padding: 0 0 8px 0;
  background-color: #f7f7f7;
}

.wh-detail {
  width: 100%;
  border-collapse: collapse;
  font: 8pt Tahoma, 'MS Sans Serif', sans-serif;
  background-color: #ffffff;
}

.wh-detail th,
.wh-detail td {
  padding: 3px 6px;
  border: 1px solid #c0c0c0;
  text-align: left;
}

.wh-detail thead th {
  font-size: 8pt;
  font-weight: bold;
  background-color: #f0f0f0;
  border: 1px solid #808080;
}

.tag {
  display: inline-block;
  padding: 0 5px;
  border: 1px solid #808080;
  font-size: 7.5pt;
  font-weight: bold;
}

.tag.ok {
  background-color: #d6f0d6;
  border-color: #008000;
  color: #005000;
}

.tag.bad {
  background-color: #f8d0d0;
  border-color: #a00000;
  color: #a00000;
}

.note {
  margin-left: 4px;
  font-size: 7.5pt;
  color: #606060;
}
</style>
