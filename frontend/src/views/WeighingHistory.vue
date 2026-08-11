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
        ⚠ Chỉ đang xem <strong>{{ allRounds.length }} vòng cân gần nhất</strong> — còn nữa ở phía
        trước. Thu hẹp khoảng ngày, hoặc bấm “Tìm trên toàn bộ lịch sử”.
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
            :title="`Lọc các vòng cân của đơn (trùng Màu + Mã hàng + Máy + LV) cân ${g.freq} lần`"
            @click="toggleFreq(g.freq)"
          >
            <span class="wh-stat-num">{{ g.products }}</span>
            <span class="wh-stat-lbl">đơn cân <strong>{{ g.freq }} lần</strong></span>
          </button>
          <p v-if="freqGroups.length === 0" class="wh-stat-none">
            Không có đơn nào bị cân trùng trong {{ allRounds.length }} vòng đang xem.
          </p>
          <p v-else class="wh-stat-none">
            Trùng = giống cả 4: Màu, Mã hàng, Máy, LV.
          </p>
        </div>

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
            <!-- Ô này KHÔNG gọi server: lọc ngay trên cửa sổ dữ liệu đã tải, hiện kết quả theo
                 từng ký tự gõ. Muốn với ra ngoài cửa sổ đó thì có nút riêng bên cạnh. -->
            <label>Tìm nhanh (không cần chờ)</label>
            <input type="text" v-model="search" placeholder="Màu / mã hàng / mã lô / máy / LV…" />
          </div>
          <!-- Đường thoát khi thứ cần tìm nằm NGOÀI cửa sổ đã tải. Luôn hiện khi có chữ trong ô
               tìm, không đợi tới lúc "không thấy gì": lọc ra 2 dòng cũng không có nghĩa là chỉ có
               2. -->
          <button v-if="search.trim()" class="wh-btn" @click="searchOnServer" :disabled="loading">
            🔎 Tìm trên toàn bộ lịch sử
          </button>
          <button class="wh-btn ghost" @click="resetFilters" :disabled="loading">Xoá lọc</button>
          <button class="wh-btn ghost" @click="reload" :disabled="loading" title="Tải lại dữ liệu mới nhất">
            ⟳ Làm mới
          </button>
        </div>

        <!-- Lọc theo từng CỘT — cũng chạy thuần trên cửa sổ đã tải như ô tìm nhanh, gõ/chọn tới
             đâu bảng đổi tới đó. Màu và Mã hàng để ô gõ kèm gợi ý (datalist) vì số giá trị nhiều;
             Máy và LV để ô chọn vì tập giá trị hữu hạn, chọn nhanh hơn gõ. -->
        <div class="wh-bar wh-cols">
          <div class="wh-field">
            <label>Màu</label>
            <input type="text" v-model="col.color" list="wh-colors" placeholder="Tất cả" />
            <datalist id="wh-colors">
              <option v-for="o in colorOptions" :key="o" :value="o" />
            </datalist>
          </div>
          <div class="wh-field">
            <label>Mã hàng</label>
            <input type="text" v-model="col.product" list="wh-products" placeholder="Tất cả" />
            <datalist id="wh-products">
              <option v-for="o in productOptions" :key="o" :value="o" />
            </datalist>
          </div>
          <div class="wh-field">
            <label>Máy</label>
            <select v-model="col.machine">
              <option value="">— Tất cả máy —</option>
              <option v-for="o in machineOptions" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>LV</label>
            <select v-model="col.lv">
              <option value="">— Tất cả LV —</option>
              <option v-for="o in lvOptions" :key="o" :value="o">{{ o }}</option>
            </select>
          </div>
          <div class="wh-field">
            <label>Cân</label>
            <select v-model="col.scale">
              <option value="">— Cả 2 cân —</option>
              <option value="LARGE">Cân to</option>
              <option value="SMALL">Cân nhỏ</option>
              <option value="NONE">Không rõ</option>
            </select>
          </div>
          <div class="wh-field">
            <label>Kết quả</label>
            <select v-model="col.result">
              <option value="">— Tất cả —</option>
              <option value="BAD">Có dòng KHÔNG ĐẠT</option>
              <option value="OK">Toàn bộ ĐẠT</option>
            </select>
          </div>
          <div class="wh-field">
            <label>Mỗi trang</label>
            <select v-model.number="pageSize">
              <option :value="20">20</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="0">Tất cả</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <p v-if="loading" class="wh-msg">Đang tải…</p>
    <p v-else-if="errorMsg" class="wh-msg err">{{ errorMsg }}</p>

    <template v-else>
      <!-- Cảnh báo cửa sổ bị cắt giờ nằm trong khối lọc sticky ở trên (xem "Cửa sổ bị cắt") để
           luôn nhìn thấy khi cuộn — chỗ này chỉ còn thông báo tìm-trên-toàn-bộ-lịch-sử. -->
      <p v-if="serverSearch" class="wh-msg warn">
        🔎 Kết quả tìm <strong>trên toàn bộ lịch sử</strong> cho “{{ serverSearch }}” —
        <button class="wh-link" @click="resetFilters">quay lại danh sách gần nhất</button>
      </p>

      <!-- Đang lọc theo thẻ: bảng KHÔNG còn xếp thuần theo thời gian nữa mà xếp theo cụm trùng.
           Phải nói ra, nếu không người dùng sẽ tưởng cột thời gian bị sai thứ tự. -->
      <p v-if="freqFilter !== null && filtered.length > 0" class="wh-msg group-note">
        Đang xem <strong>{{ groupCount }} đơn</strong> bị cân {{ freqFilter }} lần
        ({{ filtered.length }} vòng) — các vòng của cùng một đơn được xếp liền nhau, mỗi cụm một
        nền riêng. <button class="wh-link" @click="toggleFreq(freqFilter)">Bỏ lọc</button>
      </p>

      <p v-if="filtered.length === 0" class="wh-msg">
        <template v-if="freqFilter !== null">
          Không có vòng cân nào của đơn cân {{ freqFilter }} lần khớp điều kiện còn lại —
          <button class="wh-link" @click="toggleFreq(freqFilter)">bỏ lọc tần suất</button>.
        </template>
        <template v-else-if="search.trim()">
          Không có vòng cân nào khớp “{{ search }}” trong {{ allRounds.length }} vòng đã tải —
          thử nút <strong>🔎 Tìm trên toàn bộ lịch sử</strong> ở trên.
        </template>
        <template v-else>Không có vòng cân nào khớp điều kiện lọc.</template>
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
          <th class="c-time">Thời điểm cân</th>
          <th class="c-scale">Cân</th>
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
                :title="job.workstation_code ? `Trạm ${job.workstation_code}` : 'Không xác định được trạm đã cân'"
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
                title="In lại phiếu cân của vòng này"
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
    </div>

    <!-- Phân trang thuần JS trên dữ liệu đã tải — đổi trang không gọi server, không có trạng thái
         chờ nào cả. Nằm NGOÀI .wh-table-scroll (chân khung, không cuộn theo bảng) nên luôn hiện. -->
    <div class="wh-pager">
      <span class="wh-pager-info">
        Hiển thị <strong>{{ pageStart + 1 }}–{{ pageStart + paged.length }}</strong> /
        {{ filtered.length }} vòng cân<template v-if="hasRowFilter"> khớp</template>
      </span>
      <div class="wh-pager-ctrl">
        <button class="wh-btn ghost" :disabled="page <= 1" @click="page = 1" title="Trang đầu">«</button>
        <button class="wh-btn ghost" :disabled="page <= 1" @click="page -= 1">‹ Trước</button>
        <button
          v-for="p in pageWindow"
          :key="p"
          class="wh-btn ghost wh-pg-num"
          :class="{ active: p === page }"
          @click="page = p"
        >{{ p }}</button>
        <button class="wh-btn ghost" :disabled="page >= lastPage" @click="page += 1">Sau ›</button>
        <button class="wh-btn ghost" :disabled="page >= lastPage" @click="page = lastPage" title="Trang cuối">»</button>
        <span class="wh-pager-total">Trang {{ page }}/{{ lastPage }}</span>
      </div>
    </div>
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
    job.scale_kind === 'LARGE' ? 'cân to canto' : job.scale_kind === 'SMALL' ? 'cân nhỏ cannho' : '',
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
  if (search.value.trim()) parts.push(`tìm “${search.value.trim()}”`);
  if (freqFilter.value !== null) parts.push(`cân ${freqFilter.value} lần`);
  if (col.color) parts.push(`màu ${col.color}`);
  if (col.product) parts.push(`mã ${col.product}`);
  if (col.machine) parts.push(`máy ${col.machine}`);
  if (col.lv) parts.push(`LV ${col.lv}`);
  if (col.scale) parts.push('cân riêng');
  if (col.result) parts.push(col.result === 'BAD' ? 'có KHÔNG ĐẠT' : 'toàn ĐẠT');
  return parts.join(' · ');
});


/**
 * Nhãn hai chữ cho cột Cân. `scale_kind` do server suy ra từ trạm đã cân (xem
 * WeighingJobController::suyLoaiCan) — null nghĩa là không đủ căn cứ, hiện "—" chứ không đoán bừa.
 */
function scaleLabel(job: any): string {
  if (job.scale_kind === 'LARGE') return 'TO';
  if (job.scale_kind === 'SMALL') return 'NHỎ';
  return '—';
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
    alert(err.response?.data?.message || 'Không in lại được phiếu cân.');
  } finally {
    reprintingId.value = null;
  }
}

onMounted(() => load());
</script>

<style scoped>
/* Trang chiếm đúng vùng nội dung của AppLayout (.content-container, flex item có chiều cao xác
   định) và KHÔNG tự kéo dài: chỉ vùng bảng bên trong được cuộn. Nhờ vậy chân khung — thanh chuyển
   trang — luôn nằm trong tầm nhìn ở mọi kích cỡ màn hình, không phụ thuộc số dòng hay chiều cao
   khối lọc. */
.wh-page {
  padding: 16px;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

/* Khối lọc nằm cố định ở đầu cột flex (không cần sticky vì trang không tự cuộn). Bình thường
   chiếm đúng chiều cao nội dung; chỉ khi màn hình quá thấp mới co lại và cho phần thân tự cuộn
   (.wh-filters-body) — thà bộ lọc có thanh cuộn riêng còn hơn đẩy bảng và thanh chuyển trang ra
   ngoài tầm nhìn. */
.wh-filters {
  display: flex;
  flex-direction: column;
  min-height: 0;
  background-color: var(--bg-main);
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
  padding: 4px 10px;
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 6px;
  background: transparent;
  color: inherit;
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}

.wh-filters-toggle:hover {
  background: var(--bg-card-hover);
}

.wh-filters-toggle-icon {
  font-size: 11px;
  opacity: 0.7;
}

/* Tóm tắt các lọc đang bật, chỉ hiện khi đã thu gọn — nhắc là bảng đang bị lọc chứ không phải
   trống trơn, để khỏi tưởng nhầm mất dữ liệu. */
.wh-filters-summary {
  font-size: 12px;
  opacity: 0.75;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.wh-stats {
  display: flex;
  align-items: stretch;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 12px;
}

.wh-stat {
  display: flex;
  align-items: baseline;
  gap: 8px;
  padding: 8px 14px;
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 8px;
  background: transparent;
  color: inherit;
  font: inherit;
  cursor: pointer;
  transition: box-shadow 0.12s, transform 0.12s, background 0.12s;
}

.wh-stat:hover {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.14);
  transform: translateY(-1px);
}

.wh-stat-num {
  font-size: 20px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.wh-stat-lbl {
  font-size: 12px;
  opacity: 0.85;
}

.wh-stat.lv-mild {
  background: rgba(10, 92, 255, 0.08);
  border-color: rgba(10, 92, 255, 0.35);
}
.wh-stat.lv-mild .wh-stat-num {
  color: #0a5cff;
}

.wh-stat.lv-warn {
  background: rgba(250, 173, 20, 0.14);
  border-color: rgba(250, 173, 20, 0.5);
}
.wh-stat.lv-warn .wh-stat-num {
  color: #b06f00;
}

.wh-stat.lv-danger {
  background: rgba(212, 0, 0, 0.12);
  border-color: rgba(212, 0, 0, 0.45);
}
.wh-stat.lv-danger .wh-stat-num {
  color: #d40000;
}

/* Đang lọc theo thẻ nào phải nhìn ra ngay từ xa — viền đậm + nền đặc, không chỉ đổi sắc nhẹ. */
.wh-stat.active {
  border-width: 2px;
  padding: 7px 13px;
  box-shadow: inset 0 0 0 1px currentColor;
}

.wh-stat.lv-mild.active {
  background: rgba(10, 92, 255, 0.2);
  border-color: #0a5cff;
}

.wh-stat.lv-warn.active {
  background: rgba(250, 173, 20, 0.32);
  border-color: #b06f00;
}

.wh-stat.lv-danger.active {
  background: rgba(212, 0, 0, 0.24);
  border-color: #d40000;
}

.wh-stat-none {
  margin: 0;
  align-self: center;
  font-size: 12px;
  opacity: 0.6;
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

.wh-field input,
.wh-field select {
  height: 36px;
  padding: 0 10px;
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 6px;
  background: var(--input-bg, #fff);
  color: inherit;
  font-size: 14px;
}

/* Hàng lọc theo cột: mỗi ô co giãn nhưng không hẹp tới mức không đọc được giá trị đang chọn. */
.wh-cols .wh-field {
  flex: 1;
  min-width: 130px;
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

/* Chân khung .wh-table-card: viền trên tách khỏi bảng, nền đục để không lẫn với hàng cuối phía
   trên, và flex-shrink: 0 để không bao giờ bị co mất khi màn hình thấp — đây chính là phần phải
   luôn nhìn thấy. */
.wh-pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  flex-shrink: 0;
  padding: 10px 14px;
  border-top: 1px solid var(--border-color, #c9c9c9);
  background: var(--bg-card);
  font-size: 13px;
}

.wh-pager-ctrl {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.wh-pager-ctrl .wh-btn {
  height: 32px;
  padding: 0 12px;
}

.wh-pg-num {
  min-width: 32px;
  padding: 0 8px;
  font-variant-numeric: tabular-nums;
}

.wh-pg-num.active {
  background: #0a5cff;
  border-color: #0a5cff;
  color: #fff;
}

.wh-pager-total {
  margin-left: 4px;
  opacity: 0.75;
}

.wh-msg {
  padding: 24px;
  text-align: center;
  opacity: 0.75;
  /* Là flex item của .wh-page — không cho co, chữ bị bóp lại là mất dòng chứ không xuống dòng. */
  flex-shrink: 0;
}

.wh-msg.err {
  color: #d40000;
  font-weight: 600;
}

/* Khung bảng: viền + bo góc, ăn hết chiều cao còn thừa của trang (flex: 1) và tự chia thành hai
   phần — vùng cuộn ở trên, thanh chuyển trang ở dưới. min-height: 0 là bắt buộc: mặc định flex
   item không co nhỏ hơn nội dung, thiếu nó thì bảng 200 dòng vẫn đẩy chân khung ra ngoài màn hình
   đúng như trước khi có khung. */
.wh-table-card {
  flex: 1;
  /* Sàn chiều cao: đủ cho tiêu đề + vài dòng + chân khung. Đây là thứ giữ cho thanh chuyển trang
     không bị bóp mất khi màn hình thấp và khối lọc đang mở hết cỡ. */
  min-height: 160px;
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border-color, #c9c9c9);
  border-radius: 10px;
  overflow: hidden;
  background: var(--bg-card);
}

/* Vùng cuộn co giãn theo chỗ trống thật (không phải 60vh cố định): màn hình cao thì thấy nhiều
   dòng hơn, màn hình thấp / khối lọc đang mở thì tự thu lại — chân khung vẫn ở nguyên chỗ. */
.wh-table-scroll {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
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
  /* Không dùng opacity ở đây: opacity làm mờ luôn background-color sticky bên dưới,
     khiến chữ các dòng cuộn qua đè xuyên lên tiêu đề. Dùng màu chữ nhạt sẵn có thay thế. */
  color: var(--text-muted);
  /* Cuộn bảng mà không mất tiêu đề — nền phải đục để chữ các dòng bên dưới không đè xuyên qua
     khi cuộn tới. top: 0 vì ancestor cuộn giờ là .wh-table-scroll (khung bảng riêng), KHÔNG
     phải .content-container nữa — không cần bù trừ chiều cao khối lọc như trước. */
  position: sticky;
  top: 0;
  z-index: 2;
  background-color: var(--bg-main);
}

.wh-row {
  cursor: pointer;
}

.wh-row:hover,
.wh-row.open {
  background: rgba(10, 92, 255, 0.07);
}

/* Nền luân phiên theo CỤM (không phải theo dòng chẵn/lẻ): mắt phải nhận ra ranh giới cụm ngay
   cả khi hai cụm cạnh nhau có cùng số dòng. */
.wh-table tbody tr.grp-a td {
  background: rgba(10, 92, 255, 0.05);
}

.wh-table tbody tr.grp-b td {
  background: rgba(120, 120, 120, 0.09);
}

/* Nền cụm nằm trên <td>, mà hiệu ứng hover của .wh-row lại nằm trên <tr> — td vẽ đè lên tr nên
   không nhắc lại ở đây thì cả hàng banded sẽ mất phản hồi hover/mở. */
.wh-table tbody tr.wh-row.grp-a:hover td,
.wh-table tbody tr.wh-row.grp-b:hover td,
.wh-table tbody tr.wh-row.grp-a.open td,
.wh-table tbody tr.wh-row.grp-b.open td {
  background: rgba(10, 92, 255, 0.18);
}

/* Vạch mở đầu mỗi cụm — đường kẻ đậm chạy hết chiều ngang bảng. */
.wh-table tbody tr.grp-start td {
  border-top: 2px solid var(--border-color, #b9b9b9);
}

.grp-seq {
  display: inline-block;
  min-width: 30px;
  margin-right: 8px;
  padding: 1px 5px;
  border-radius: 4px;
  background: rgba(0, 0, 0, 0.1);
  font-size: 11px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  text-align: center;
}

.wh-msg.group-note {
  padding: 8px 12px;
  margin-bottom: 10px;
  text-align: left;
  border-radius: 6px;
  background: rgba(10, 92, 255, 0.08);
  border: 1px solid rgba(10, 92, 255, 0.3);
  opacity: 1;
  font-size: 13px;
}

.c-num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.c-time {
  white-space: nowrap;
}

/* Cột Cân: cố định hẹp, nhãn chỉ 2 chữ nên không cần chỗ co giãn. */
.c-scale {
  width: 62px;
  white-space: nowrap;
}

.scale-tag {
  display: inline-block;
  min-width: 42px;
  padding: 2px 7px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.3px;
  text-align: center;
}

/* Hai loại cân phải khác nhau cả về MÀU lẫn CHỮ — nếu chỉ khác chữ thì lướt mắt qua bảng dài
   không tách được cụm nào của cân nào. */
.scale-tag.sc-large {
  background: #e2e0ff;
  color: #3b2fb8;
}

.scale-tag.sc-small {
  background: #d9eefb;
  color: #0b5f8a;
}

.scale-tag.sc-unknown {
  background: rgba(0, 0, 0, 0.08);
  opacity: 0.7;
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
  .wh-stat.lv-mild .wh-stat-num {
    color: #7fb0ff;
  }
  .wh-stat.lv-warn .wh-stat-num {
    color: #ffc857;
  }
  .wh-stat.lv-danger .wh-stat-num {
    color: #ff9d9d;
  }
  .wh-stat.lv-warn.active {
    border-color: #ffc857;
  }
  .wh-stat.lv-danger.active {
    border-color: #ff9d9d;
  }
  .wh-table tbody tr.grp-a td {
    background: rgba(90, 150, 255, 0.12);
  }
  .wh-table tbody tr.grp-b td {
    background: rgba(255, 255, 255, 0.06);
  }
  .grp-seq {
    background: rgba(255, 255, 255, 0.16);
  }
  .scale-tag.sc-large {
    background: rgba(120, 108, 255, 0.28);
    color: #cfc9ff;
  }
  .scale-tag.sc-small {
    background: rgba(40, 140, 200, 0.28);
    color: #bfe4f7;
  }
  .scale-tag.sc-unknown {
    background: rgba(255, 255, 255, 0.12);
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
