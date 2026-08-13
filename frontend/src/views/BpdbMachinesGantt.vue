<template>
  <!-- pageWrapper là hằng số (không phải ref/computed đổi được sau khi mount) — quyết định
       CHỈ 1 LẦN lúc khởi tạo component, KHÔNG đổi trong suốt vòng đời trang. Trước đó từng
       dùng 1 ref đổi được (showAdminNav) để bật/tắt việc bọc AppLayout — mỗi lần đổi buộc
       Vue hủy+dựng lại toàn bộ cây con bên trong, làm DOM chứa vis-timeline (.gantt-canvas)
       bị thay bằng node rỗng mới -> biểu đồ mất trắng (đúng lỗi báo cáo "mở layout lên thì
       k thấy gì"). Cố định pageWrapper ngay từ đầu (Admin luôn được bọc sẵn AppLayout,
       chỉ ẩn/hiện sidebar+topbar qua isFullscreen — xem services/layout.ts) loại bỏ hẳn
       nguy cơ remount này, đồng thời tái dùng đúng menu điều hướng quen thuộc của cả hệ
       thống thay vì tự làm 1 panel riêng. -->
  <component :is="pageWrapper">
  <div class="gantt-page" :class="{ 'is-immersive': isBrowserFullscreen }">
    <p v-if="errorMsg" class="text-error mt-2">❌ {{ errorMsg }}</p>
    <div v-if="!bpdbConnected" class="stale-banner error-banner mt-2">
      {{ $t('bpdbMachinesGantt.bpdbDisconnected', { time: formatTime(lastSyncedAt) }) }}
    </div>
    <div v-else-if="dataStale" class="stale-banner mt-2">
      {{ $t('bpdbMachinesGantt.dataStale', { time: formatTime(lastSyncedAt), seconds: dataAgeSeconds }) }}
    </div>

    <!-- v-show (không phải v-if): giữ nguyên DOM + state của các ô lọc ngày/tìm máy khi
         vào/ra fullscreen, tránh mất giá trị người dùng đang gõ dở. -->
    <div class="toolbar mt-2" v-show="!isBrowserFullscreen">
      <div class="toolbar-group">
        <label class="field">
          <span class="field-label">{{ $t('bpdbMachinesGantt.fromDateLabel') }}</span>
          <input type="date" v-model="fromDate" class="form-select" />
        </label>
        <label class="field">
          <span class="field-label">{{ $t('bpdbMachinesGantt.toDateLabel') }}</span>
          <input type="date" v-model="toDate" class="form-select" />
        </label>
        <button class="btn btn-primary btn-sm" @click="loadGantt()" :disabled="loading">{{ $t('bpdbMachinesGantt.filterButton') }}</button>
        <label class="field">
          <span class="field-label">{{ $t('bpdbMachinesGantt.searchMachineLabel') }}</span>
          <input v-model="machineSearch" type="text" class="form-select" :placeholder="$t('bpdbMachinesGantt.searchMachinePlaceholder')" />
        </label>
      </div>
      <div class="toolbar-group">
        <button class="btn btn-secondary btn-sm" @click="loadGantt()" :disabled="loading">{{ loading ? $t('common.loading') : $t('bpdbMachinesGantt.reloadButton') }}</button>
        <button class="btn btn-secondary btn-sm" @click="moveToNow">{{ $t('bpdbMachinesGantt.moveToNowButton') }}</button>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoMove" /> {{ $t('bpdbMachinesGantt.autoScrollLabel') }}</label>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoRefresh" /> {{ $t('bpdbMachinesGantt.autoRefreshLabel') }}</label>
        <label class="realtime-toggle"><input type="checkbox" v-model="autoJumpNew" /> {{ $t('bpdbMachinesGantt.autoJumpLabel') }}</label>
        <!-- Người xem công khai (không phải Admin) không có AppLayout nên không có nút
             chuyển theme ở topbar chung — trang tự có nút riêng, dùng chung cho cả 2
             trường hợp (kể cả khi Admin đã lộ AppLayout, đỡ phải rẽ nhánh UI). -->
        <button
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="toggleTheme"
          :title="theme === 'dark' ? $t('bpdbMachinesGantt.themeToLightTitle') : $t('bpdbMachinesGantt.themeToDarkTitle')"
        >
          <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="16" />
        </button>
        <button
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="toggleBrowserFullscreen"
          :title="isBrowserFullscreen ? $t('bpdbMachinesGantt.exitBrowserFullscreenTitle') : $t('bpdbMachinesGantt.enterBrowserFullscreenTitle')"
        >
          {{ isBrowserFullscreen ? '⤢' : '⛶' }}
        </button>
        <!-- Trang public mặc định không sidebar/topbar. Với Admin đã đăng nhập, trang được
             bọc sẵn AppLayout (ẩn qua isFullscreen) — nút này chỉ bật isFullscreen=false để
             lộ ra đúng menu điều hướng quen thuộc của cả hệ thống (yêu cầu 2026-07-29: "mở
             layout hay dùng ấy, mà dữ liệu vẫn nhìn thấy"). Không hiện với người xem công
             khai (không phải Admin, lúc đó pageWrapper = 'div', không có AppLayout để mở). -->
        <button
          v-if="isAdminUser"
          class="btn btn-secondary btn-sm theme-toggle-btn"
          @click="isFullscreen = !isFullscreen"
          :title="isFullscreen ? $t('bpdbMachinesGantt.openNavMenuTitle') : $t('bpdbMachinesGantt.hideNavMenuTitle')"
        >
          <SvgIcon name="menu" size="16" />
        </button>
      </div>
    </div>

    <div class="gantt-container mt-2">
      <div id="ganttNeedle" class="gantt-needle" :style="needleStyle" :data-time="needleLabel" v-show="needleVisible"></div>
      <div ref="timelineEl" class="gantt-canvas"></div>
      <p v-if="!loading && totalRecords === 0" class="gantt-empty">{{ $t('bpdbMachinesGantt.emptyState') }}</p>
    </div>
    <p class="footnote">{{ $t('bpdbMachinesGantt.footnotePrefix') }}<strong>{{ totalRecords }}</strong>{{ $t('bpdbMachinesGantt.footnoteSuffix') }}</p>

    <!-- Đồng hồ nổi góc trên trái — chỉ hiện khi toàn màn hình (che mất đồng hồ hệ điều hành). -->
    <div v-show="isBrowserFullscreen" class="fs-clock">
      <div class="fs-clock-time">{{ clockTime }}</div>
      <div class="fs-clock-date">{{ clockDate }}</div>
    </div>

    <!-- Nút thoát nổi góc dưới phải — chỉ hiện khi đang toàn màn hình, vì lúc đó thanh công
         cụ (chứa nút ⛶) đã bị ẩn, không còn đường nào khác để thoát bằng chuột. -->
    <div v-show="isBrowserFullscreen" class="fs-exit">
      <span v-if="showF11Hint" class="fs-exit-hint">
        {{ $t('bpdbMachinesGantt.f11HintPrefix') }} <kbd>F11</kbd>
      </span>
      <button class="fs-exit-btn" @click="exitFullscreenView" :title="$t('bpdbMachinesGantt.exitFullscreenLabel')">
        <span aria-hidden="true">⤢</span> {{ $t('bpdbMachinesGantt.exitFullscreenLabel') }}
      </button>
    </div>

    <!-- Bảng chi tiết mẻ — chỉ hiện khi BẤM vào thanh Gantt (yêu cầu 2026-08-03), thay cho
         tooltip hover mặc định của vis-timeline trước đây. position: fixed nên đặt ở cuối
         cây, vị trí do detailPopupStyle tính theo điểm bấm. -->
    <div v-if="detailPopup" class="gantt-detail-popup" :style="detailPopupStyle">
      <button class="gantt-detail-close" @click="closeDetailPopup" :title="$t('bpdbMachinesGantt.closeEscTitle')">✕</button>
      <div class="gantt-detail-head">
        <span class="gantt-detail-swatch" :style="{ backgroundColor: detailPopup.barColor }"></span>
        <span class="gantt-detail-title">
          {{ detailPopup.machineCode }}<template v-if="detailPopup.tankLabel">{{ $t('bpdbMachinesGantt.tankPrefix') }}{{ detailPopup.tankLabel }}</template>
        </span>
      </div>
      <dl class="gantt-detail-list">
        <template v-if="detailPopup.color && detailPopup.productCode">
          <dt>{{ $t('bpdbMachinesGantt.colorProductLabel') }}</dt>
          <dd>{{ detailPopup.color }} - {{ detailPopup.productCode }}</dd>
        </template>
        <template v-else>
          <dt>{{ $t('bpdbMachinesGantt.rawTaskTitleLabel') }}</dt>
          <dd>{{ detailPopup.taskTitle || '—' }}</dd>
        </template>
        <dt>{{ $t('common.status') }}</dt>
        <dd>
          {{ detailPopup.statusLabel }}
          <span v-if="detailPopup.uncompleted" class="gantt-blink">{{ $t('bpdbMachinesGantt.runningNote') }}</span>
        </dd>
        <template v-if="detailPopup.mergedCount > 1">
          <dt>{{ $t('bpdbMachinesGantt.mergedCountLabel') }}</dt>
          <dd>{{ $t('bpdbMachinesGantt.mergedCountText', { count: detailPopup.mergedCount }) }}</dd>
        </template>
        <!-- Tổng số mẻ mã này đã chạy từ trước tới nay (yêu cầu 2026-08-03) — KHÁC hai
             con số phía trên: không giới hạn theo khoảng ngày đang lọc, cũng không giới
             hạn trong 1 Tank/1 máy. Gọi API riêng khi mở bảng, xem loadLotTotal().
             Nhãn "Số lần đánh mẫu" theo cách gọi của xưởng (yêu cầu 2026-08-04) — mỗi mẻ
             chạy của cùng mã màu - mã hàng là một lần đánh mẫu, nên đơn vị hiển thị là
             "lần" chứ không phải "mẻ" (yêu cầu 2026-08-06). -->
        <template v-if="detailPopup.color && detailPopup.productCode">
          <dt>{{ $t('bpdbMachinesGantt.lotTotalLabel') }}</dt>
          <dd>
            <template v-if="lotTotal?.loading">{{ $t('bpdbMachinesGantt.lotTotalLoading') }}</template>
            <template v-else-if="lotTotal?.failed">{{ $t('bpdbMachinesGantt.lotTotalFailed') }}</template>
            <template v-else-if="lotTotal">
              <strong>{{ lotTotal.total }}</strong> {{ $t('bpdbMachinesGantt.timesUnit') }}
              <span class="gantt-detail-note">
                {{ $t('bpdbMachinesGantt.allMachinesNote') }}<template v-if="lotTotal.firstRunAt">{{ $t('bpdbMachinesGantt.sinceDatePrefix') }}{{ formatDay(lotTotal.firstRunAt) }}{{ $t('bpdbMachinesGantt.sinceDateSuffix') }}</template>
              </span>
            </template>
            <template v-else>—</template>
          </dd>
          <!-- Chia theo từng máy VD (yêu cầu 2026-08-03) — cùng 1 lần đếm ở trên, không gọi
               thêm API. Máy đánh mẫu nhiều lần nhất đứng trước. -->
          <template v-if="lotTotal && !lotTotal.loading && !lotTotal.failed && lotTotal.byMachine.length">
            <dt>{{ $t('bpdbMachinesGantt.byMachineLabel') }}</dt>
            <dd>
              <span class="gantt-detail-machines">
                <span v-for="m in lotTotal.byMachine" :key="m.machineCode" class="gantt-detail-machine">
                  {{ m.machineCode }}: <strong>{{ m.count }}</strong>
                </span>
              </span>
              <span class="gantt-detail-note">{{ $t('bpdbMachinesGantt.machineCountSuffix', { count: lotTotal.byMachine.length }) }}</span>
            </dd>
          </template>
        </template>
        <dt>{{ $t('bpdbMachinesGantt.startLabel') }}</dt>
        <dd>{{ detailPopup.startText }}</dd>
        <dt>{{ $t('bpdbMachinesGantt.endLabel') }}</dt>
        <dd>
          {{ detailPopup.endText }}
          <span v-if="detailPopup.endSource === 'MES'" class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesRealTimeNote') }}</span>
          <span v-else-if="detailPopup.endSource === 'BPDB'" class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesEstimateNote') }}</span>
        </dd>
        <template v-if="detailPopup.errorMessage">
          <dt class="text-error">{{ $t('common.error') }}</dt>
          <dd class="text-error">{{ detailPopup.errorMessage }}</dd>
        </template>
      </dl>
      <div v-if="detailPopup.mesBatchNo" class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesFullInfoNote') }}</div>
    </div>

    <!-- Bảng thông tin mẻ MES cố định bên PHẢI (yêu cầu 2026-08-08) — rộng rãi, cuộn được,
         hiện khi bấm vào thanh đã ghép với MES. Tách khỏi popup nhỏ để đủ chỗ xem đầy đủ. -->
    <div v-if="mesPanelOpen && detailPopup?.mesBatchNo" class="gantt-mes-panel">
      <div class="gantt-mes-panel-head">
        <div class="gantt-mes-panel-title">
          {{ $t('bpdbMachinesGantt.mesPanelTitle') }}
          <span class="gantt-mes-panel-batch">{{ detailPopup.mesBatchNo }}</span>
        </div>
        <button type="button" class="gantt-detail-close" :aria-label="$t('common.close')" @click="mesPanelOpen = false">✕</button>
      </div>
      <div class="gantt-mes-panel-body">
        <template v-if="mesBatch?.loading">
          <div class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesLoading') }}</div>
        </template>
        <template v-else-if="mesBatch?.failed">
          <div class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesFailed') }}</div>
        </template>
        <template v-else-if="mesBatch?.raw">
          <table class="gantt-mes-table">
            <tbody>
              <tr v-for="row in mesFieldRows" :key="row.label">
                <th>{{ row.label }}</th>
                <td>{{ row.value }}</td>
              </tr>
            </tbody>
          </table>
          <details v-if="mesFieldRowsRest.length" class="gantt-mes-all">
            <summary>{{ $t('bpdbMachinesGantt.allFieldsSummary', { count: mesFieldRowsRest.length }) }}</summary>
            <table class="gantt-mes-table">
              <tbody>
                <tr v-for="row in mesFieldRowsRest" :key="row.label">
                  <th>{{ row.label }}</th>
                  <td>{{ row.value }}</td>
                </tr>
              </tbody>
            </table>
          </details>
          <div v-if="mesBatch.syncedAt" class="gantt-detail-note">{{ $t('bpdbMachinesGantt.mesSyncedAtPrefix') }}{{ formatTime(mesBatch.syncedAt) }}</div>
        </template>
      </div>
    </div>

    <!-- Panel bảng liệu RIÊNG — đặt sát bên trái panel MES (nhích về phía giữa màn hình). -->
    <div v-if="mesPanelOpen && detailPopup?.mesBatchNo" class="gantt-recipe-panel">
      <div class="gantt-mes-panel-head">
        <div class="gantt-mes-panel-title">
          {{ $t('bpdbMachinesGantt.recipeTitle') }}
          <span class="gantt-mes-panel-batch">{{ detailPopup.mesBatchNo }}</span>
        </div>
        <button type="button" class="gantt-detail-close" :aria-label="$t('common.close')" @click="mesPanelOpen = false">✕</button>
      </div>
      <div class="gantt-mes-panel-body">
        <section class="gantt-recipe">
          <div v-if="batchRecipe?.loading" class="gantt-detail-note">{{ $t('bpdbMachinesGantt.recipeLoading') }}</div>
          <template v-else>
            <div class="gantt-recipe-sub">{{ $t('bpdbMachinesGantt.recipeMesTitle') }}</div>
            <template v-if="batchRecipe?.mesFormula && (batchRecipe.mesFormula.dyes.length || batchRecipe.mesFormula.aux.length)">
              <template v-if="batchRecipe.mesFormula.dyes.length">
                <div class="gantt-recipe-group">{{ $t('bpdbMachinesGantt.recipeDyes') }}</div>
                <table class="gantt-recipe-table">
                  <thead><tr><th>{{ $t('bpdbMachinesGantt.recipeColCode') }}</th><th>{{ $t('bpdbMachinesGantt.recipeColName') }}</th><th class="num">{{ $t('bpdbMachinesGantt.recipeColAmount') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="(m, i) in batchRecipe.mesFormula.dyes" :key="'d' + i">
                      <td>{{ m.code }}</td><td class="name">{{ m.name }}</td><td class="num">{{ m.amount }} {{ m.unit }}</td>
                    </tr>
                  </tbody>
                </table>
              </template>
              <template v-if="batchRecipe.mesFormula.aux.length">
                <div class="gantt-recipe-group">{{ $t('bpdbMachinesGantt.recipeAux') }}</div>
                <table class="gantt-recipe-table">
                  <thead><tr><th>{{ $t('bpdbMachinesGantt.recipeColCode') }}</th><th>{{ $t('bpdbMachinesGantt.recipeColName') }}</th><th class="num">{{ $t('bpdbMachinesGantt.recipeColAmount') }}</th></tr></thead>
                  <tbody>
                    <tr v-for="(m, i) in batchRecipe.mesFormula.aux" :key="'a' + i">
                      <td>{{ m.code }}</td><td class="name">{{ m.name }}</td><td class="num">{{ m.amount }} {{ m.unit }}</td>
                    </tr>
                  </tbody>
                </table>
              </template>
            </template>
            <div v-else class="gantt-detail-note">{{ $t('bpdbMachinesGantt.recipeMesEmpty') }}</div>

            <div class="gantt-recipe-sub">{{ $t('bpdbMachinesGantt.recipeBpdbTitle') }}</div>
            <table v-if="batchRecipe?.bpdbDosing?.available && batchRecipe.bpdbDosing.lines.length" class="gantt-recipe-table">
              <thead><tr><th>{{ $t('bpdbMachinesGantt.recipeColRack') }}</th><th>{{ $t('bpdbMachinesGantt.recipeColCode') }}</th><th class="num">{{ $t('bpdbMachinesGantt.recipeColRequested') }}</th><th class="num">{{ $t('bpdbMachinesGantt.recipeColActual') }}</th></tr></thead>
              <tbody>
                <tr v-for="(l, i) in batchRecipe.bpdbDosing.lines" :key="'b' + i">
                  <td>{{ l.tank }}</td><td>{{ l.materialCode }}</td><td class="num">{{ l.requestedGrams }}</td><td class="num">{{ l.actualGrams }}</td>
                </tr>
              </tbody>
            </table>
            <div v-else class="gantt-detail-note">{{ $t('bpdbMachinesGantt.recipeBpdbEmpty') }}</div>
          </template>
        </section>
      </div>
    </div>
  </div>
  </component>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import axios from 'axios';
import { Timeline } from 'vis-timeline/standalone';
import * as visStandalone from 'vis-timeline/standalone';
import type { DataSet as DataSetCtor } from 'vis-data';

/*
 * DataSet phải lấy GIÁ TRỊ từ chính bản dựng standalone (cùng bundle với Timeline) — nhập từ gói
 * `vis-data` riêng sẽ ra một lớp DataSet KHÁC với lớp mà Timeline bên trong đang dùng, hai bên
 * không nhận nhau.
 *
 * Nhưng bộ khai báo của vis-timeline 8.5.2 không tái xuất được DataSet ra tới
 * `vis-timeline/standalone`: `declarations/index.d.ts` có một binding `DataSet` cục bộ (dòng 19,
 * chỉ import chứ không export), làm hỏng chuỗi `export *` — TS2459 "declares locally, but it is
 * not exported". Nên tách đôi: giá trị lấy từ standalone, KIỂU lấy từ vis-data.
 */
const DataSet = (visStandalone as unknown as { DataSet: typeof DataSetCtor }).DataSet;
import 'vis-timeline/styles/vis-timeline-graph2d.min.css';
import { isFullscreen } from '../services/layout';
import { theme, toggleTheme } from '../services/theme';
import SvgIcon from '../components/SvgIcon.vue';
import AppLayout from '../components/AppLayout.vue';
import { useAuthStore } from '../stores/auth';
import { useI18n } from 'vue-i18n';

const { t } = useI18n({ useScope: 'global' });
const authStore = useAuthStore();
// Snapshot MỘT LẦN lúc khởi tạo — KHÔNG dùng ref/computed. Trạng thái đăng nhập không đổi
// trong vòng đời trang này (không có luồng login/logout ngay trên trang Gantt), nên chốt
// cứng giá trị này đảm bảo pageWrapper bên dưới không bao giờ đổi sau khi mount (xem lý do
// tại ghi chú trong <template>).
const isAdminUser = authStore.isAdmin;
const pageWrapper = isAdminUser ? AppLayout : 'div';

// Toàn màn hình trình duyệt thật (Fullscreen API, tương đương phím F11) — khác với
// services/layout.ts isFullscreen (cơ chế ẩn/hiện sidebar+topbar của AppLayout, dùng cho
// nút "Mở menu điều hướng" ở trên).
// QUAN TRỌNG: toàn màn hình bằng phím F11 KHÔNG set document.fullscreenElement (chỉ
// Fullscreen API mới set) — nếu chỉ dựa vào nó thì bấm F11 xong trang không hề biết mình
// đang fullscreen. Media query (display-mode: fullscreen) bắt được CẢ HAI đường vào, nên
// dùng nó làm nguồn nhận biết chính.
const fullscreenMedia = window.matchMedia('(display-mode: fullscreen)');
const isBrowserFullscreen = ref(!!document.fullscreenElement || fullscreenMedia.matches);
// Nhắc phím F11 khi người dùng bấm nút thoát mà không thoát được — xem exitFullscreenView.
const showF11Hint = ref(false);

const syncBrowserFullscreenState = () => {
  const wasFullscreen = isBrowserFullscreen.value;
  isBrowserFullscreen.value = !!document.fullscreenElement || fullscreenMedia.matches;

  // Vào fullscreen thì thu gọn luôn sidebar+topbar của AppLayout (Admin có thể đang mở
  // menu điều hướng) — fullscreen mà vẫn còn menu che thì mất hết ý nghĩa.
  if (isAdminUser && !wasFullscreen && isBrowserFullscreen.value) {
    isFullscreen.value = true;
  }
  if (!isBrowserFullscreen.value) showF11Hint.value = false;

  // Khung nhìn đổi kích thước khi vào/thoát fullscreen — vis-timeline không tự vẽ lại
  // (cùng lý do với watch(isFullscreen) bên dưới, đợi 1 nhịp cho reflow xong).
  setTimeout(() => timeline?.redraw(), 60);
};

/**
 * Thoát toàn màn hình từ nút nổi góc dưới phải.
 * Chỉ thoát được bằng JS nếu ĐÃ VÀO bằng Fullscreen API (nút ⛶ trên thanh công cụ).
 * Nếu người dùng vào bằng phím F11, chuẩn bảo mật của trình duyệt KHÔNG cho script thoát —
 * document.exitFullscreen() sẽ không làm gì cả, nên phải nhắc đúng phím thay vì im lặng.
 */
const exitFullscreenView = () => {
  if (document.fullscreenElement) {
    document.exitFullscreen().catch(() => {});
    return;
  }
  showF11Hint.value = true;
  setTimeout(() => (showF11Hint.value = false), 4000);
};

// Đồng hồ góc trên trái — chỉ có ý nghĩa ở chế độ toàn màn hình: lúc đó taskbar/đồng hồ của
// hệ điều hành bị che hết, mà đây là màn hình treo theo dõi sản xuất cả ngày nên người đứng
// xa vẫn cần đọc được giờ. Timer chỉ chạy khi đang fullscreen, không để nó tick vô ích 24/7
// trên trang vốn đã nặng vì vis-timeline.
const clockNow = ref(new Date());
let clockTimer: ReturnType<typeof setInterval> | null = null;

const startClock = () => {
  if (clockTimer) return;
  clockNow.value = new Date();
  clockTimer = setInterval(() => (clockNow.value = new Date()), 1000);
};
const stopClock = () => {
  if (!clockTimer) return;
  clearInterval(clockTimer);
  clockTimer = null;
};

const clockTime = computed(() => clockNow.value.toLocaleTimeString('vi-VN', { hour12: false }));
const clockDate = computed(() => clockNow.value.toLocaleDateString('vi-VN', {
  weekday: 'long',
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
}));

watch(isBrowserFullscreen, (on) => (on ? startClock() : stopClock()), { immediate: true });
const toggleBrowserFullscreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen().catch(() => {});
  } else {
    document.exitFullscreen().catch(() => {});
  }
};

const RAW_TASK_STATUS_CODES = ['10', '20', '30', '40', '99'];

// Độ dài vẽ tối thiểu của mỗi thanh Gantt — mẻ ngắn hơn vẫn được vẽ rộng bằng đúng ngần
// này thời gian (không phải ép cứng theo px), giữ đúng bản chất "thanh dài = thời gian dài".
// Cũng chính là khoảng cách tối thiểu giữa 2 mẻ liên tiếp trên cùng Tank (xem lượt 1 của
// thuật toán xếp mẻ trong fetchGantt), nên tăng số này là tăng luôn chỗ cho nhãn.
// 2h -> 2.5h (2026-08-03): sau khi bỏ min-width theo px, 2h vẫn chưa đủ rộng cho nhãn
// "{mã màu}-{mã hàng}" ở khung 24h mặc định nên chữ bị cắt.
const MIN_VISUAL_DURATION_MS = 2.5 * 60 * 60 * 1000;

// Độ rộng cột tên máy/tank — mã máy (vd "VD006") và tên tank (vd "1A") đều ngắn, 170px dư
// quá nhiều diện tích lẽ ra dành cho phần vẽ Gantt (yêu cầu 2026-07-29). Một hằng số dùng
// chung cho cả CSS (qua v-bind trong <style>) lẫn tính vị trí kim đỏ (calculateNeedle) và
// header tìm máy, để không bao giờ bị lệch nhau. Con số này chỉ là lựa chọn thẩm mỹ (đủ chỗ
// cho mã máy + icon ghim) — KHÔNG liên quan tới lỗi "tên máy biến mất khi cuộn xuống các máy
// chưa từng vào khung nhìn" (báo cáo 2026-07-29): từng nghi ngờ do width quá hẹp nhưng đã
// kiểm chứng lại là SAI (bug tái diễn y hệt kể cả ở 250px) — nguyên nhân thật là thiếu
// z-index trên hàng mang ô tên máy, xem rule z-index bắt buộc trong <style> (tìm
// ":deep(.vis-label.gantt-machine-head) { z-index: 6; }"). Đổi số này thoải mái theo nhu cầu,
// không ảnh hưởng tới bug đó.
const LABEL_COLUMN_WIDTH = 164;
const labelColumnWidthCss = `${LABEL_COLUMN_WIDTH}px`;

// Cột nhãn chia thành 2 cột con (yêu cầu 2026-08-02): cột 1 = tên máy (chỉ có chữ trên
// đúng hàng máy), cột 2 = tên tank của máy đó. Vạch ngăn giữa 2 cột vẽ bằng pseudo-element
// trên TỪNG hàng .vis-label (xem <style>) chứ không vẽ 1 đường liền trên panel — mọi hàng
// .vis-label đều có background riêng nên đường vẽ dưới nền sẽ bị che mất.
// 110px là bề rộng tối thiểu để "VD006" + icon ghim hiện đủ: box gộp còn bị trừ 15px chừa
// mũi tên đóng/mở và ~10px padding, hẹp hơn nữa là tên máy bị cắt thành "VD…".
const MACHINE_COL_WIDTH = 110;
const machineColWidthCss = `${MACHINE_COL_WIDTH}px`;

// Chiều cao CỐ ĐỊNH của thanh mẻ và của MỌI hàng (cả hàng Máy VD lẫn hàng Tank) — yêu cầu
// 2026-08-05 "định dạng lại khoảng cách các dòng cho đồng bộ kích thước". Trước đây chiều cao
// mỗi hàng do vis-timeline tự tính theo các mẻ ĐANG NHÌN THẤY trong khung giờ hiện tại: Tank
// đang có mẻ thì cao, Tank không có mẻ nào lọt khung nhìn thì co lại chỉ bằng chiều cao nhãn
// -> danh sách so le, tệ hơn là chiều cao còn tự đổi mỗi khi cuộn ngang trục thời gian.
// Nay chốt cứng cả 3 yếu tố quyết định chiều cao hàng:
//   1. Chiều cao thanh mẻ ép bằng CSS (xem .vis-item trong <style>).
//   2. margin khai báo tường minh trong option của Timeline (không dùng mặc định 20/10/10).
//   3. groupHeightMode: 'fixed' — vis tính chiều cao theo TOÀN BỘ mẻ của Tank thay vì chỉ mẻ
//      đang lọt khung nhìn, nên cuộn ngang không còn làm hàng co giãn.
// ROW_HEIGHT phải khớp ĐÚNG công thức của thư viện (Group._calculateHeight):
// ceil(top + itemHeight + margin.item.vertical / 2), trong đó top bị kéo về đúng margin.axis
// khi margin.item.vertical > margin.axis (đúng trường hợp ở đây: 10 > 5).
const ITEM_HEIGHT = 24;
const ROW_MARGIN_AXIS = 5;
const ROW_MARGIN_ITEM_VERTICAL = 10;
const ROW_HEIGHT = ROW_MARGIN_AXIS + ITEM_HEIGHT + ROW_MARGIN_ITEM_VERTICAL / 2;
const itemHeightCss = `${ITEM_HEIGHT}px`;
const rowHeightCss = `${ROW_HEIGHT}px`;

const toDateInput = (d: Date) => d.toISOString().slice(0, 10);
const fromDate = ref(toDateInput(new Date(Date.now() - 7 * 24 * 3600 * 1000)));
const toDate = ref(toDateInput(new Date()));

const loading = ref(false);
const errorMsg = ref('');
const bpdbConnected = ref(true);
const lastSyncedAt = ref<string | null>(null);
const dataAgeSeconds = ref(0);
const dataStale = ref(false);
const totalRecords = ref(0);
const autoMove = ref(true);
const autoRefresh = ref(true);
// Tự cuộn DỌC tới mẻ vừa mới xuất hiện + đang chạy (viền đỏ nhấp nháy) mỗi lần tải lại —
// khác với autoMove (chỉ cuộn NGANG trục thời gian về hiện tại) — yêu cầu 2026-07-29: "mẻ
// nào mới và nhấp nháy thì nhảy xuống chỗ đấy, không cần cuộn chuột". Xem newlyAppearedRunningIds.
const autoJumpNew = ref(true);
const machineSearch = ref('');

const timelineEl = ref<HTMLDivElement | null>(null);
let timeline: any = null;
const groupsDataSet = new DataSet<any>();
const itemsDataSet = new DataSet<any>();
// Danh sách gốc (chưa lọc theo machineSearch) — giữ lại để lọc lại tại chỗ mỗi khi người
// dùng gõ ô tìm máy, không cần gọi lại API.
let allGroups: any[] = [];
let allItems: any[] = [];
// So sánh tập ID mẻ "đang chạy" (uncompleted, viền đỏ nhấp nháy) giữa 2 lần fetch để phát
// hiện mẻ MỚI vừa xuất hiện (không phải mọi mẻ đang chạy — mẻ đã chạy từ trước, người dùng
// đã thấy rồi, không cần tự nhảy tới nữa). isFirstFetch=true ở lần tải đầu tiên để không tự
// nhảy lung tung ngay khi vừa mở trang (lúc đó "mẻ nào cũng là mẻ mới" theo nghĩa kỹ thuật).
let previousUncompletedIds = new Set<string>();
let isFirstFetch = true;
let newlyAppearedRunningIds: string[] = [];

// Mốc "hiện tại" dùng để vẽ kim đỏ + tự cuộn — chốt theo lần đồng bộ dữ liệu gần nhất
// (lastSyncedAt trả về từ backend), KHÔNG dùng đồng hồ trình duyệt — nhất quán với cách
// các trang BPDB khác trong hệ thống báo "dữ liệu có thể đã cũ" thay vì giả vờ realtime.
const syncSnapshot = ref(new Date());
const needleVisible = ref(false);
const needleLabel = ref('');
const needleStyle = ref('left: 0px;');

const formatTime = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
};

/** Chỉ ngày (không giờ) — dùng cho mốc "chạy lần đầu từ ..." vốn có thể cách đây nhiều năm. */
const formatDay = (iso: string | null) => {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
};

const rawTaskStatusLabel = (status: number) =>
  RAW_TASK_STATUS_CODES.includes(String(status))
    ? t(`bpdbMachinesGantt.taskStatus${status}`)
    : t('bpdbMachinesGantt.statusUnknown', { status });

// Màu thanh Gantt = màu ĐẠI DIỆN HỌ MÀU của mẻ, do backend suy ra từ mã màu
// (xem ColorCodePalette.php: BPDB/MES đều không lưu RGB thật cho chuyền VD). Nếu backend
// không giải mã được thì trả về màu xám trung tính.
const FALLBACK_BAR_COLOR = '#9AA0A6';

// Màu họ trải từ pastel rất nhạt (#EACBD1) tới gần đen (#252729) nên chữ trắng cố định sẽ
// mất hút trên các mẻ nhạt — chọn chữ đen/trắng theo độ sáng cảm nhận (công thức luminance
// của WCAG) để nhãn luôn đọc được.
const labelColorOn = (hex: string) => {
  const m = /^#?([0-9a-f]{6})$/i.exec(hex);
  if (!m) return '#fff';
  const n = parseInt(m[1], 16);
  const [r, g, b] = [(n >> 16) & 0xff, (n >> 8) & 0xff, n & 0xff];
  return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#1a1a1a' : '#fff';
};

// Đổ bóng cùng tông với nền (sáng viền sáng / tối viền tối) để chữ tách khỏi nền ở cả hai đầu
// dải sáng — dùng chung một bóng tối như trước sẽ làm chữ đen trên nền pastel bị nhoè.
const labelShadowFor = (textColor: string) =>
  textColor === '#fff' ? '0 1px 2px rgba(0,0,0,0.6)' : '0 1px 2px rgba(255,255,255,0.7)';

/**
 * Một thanh trên Gantt sau khi đã dựng xong — đúng hình dạng vis-timeline nhận.
 *
 * Cần khai tường minh vì `res.data` từ axios là `any`: không có kiểu ở đây thì `items` cũng thành
 * `any`, kéo theo mọi tham số của .sort/.filter/.map bên dưới mất kiểu, và `new Set(...)` ra
 * `Set<unknown>` không gán được vào `previousUncompletedIds: Set<string>`.
 */
interface GanttItem {
  id: string;
  group: string;
  start: Date;
  end: Date;
  className: string;
  style: string;
  content: string;
  // Các field dưới đây chỉ phục vụ xử lý phía client (gộp mẻ, dựng lại nhãn) — vis-timeline
  // bỏ qua field lạ nên không cần tách sang cấu trúc riêng.
  /** Khoá so sánh "2 mẻ giống nhau" = mã màu + mã hàng; rỗng khi không tách được (không gộp). */
  mergeKey: string;
  uncompleted: boolean;
  /** Giờ kết thúc THẬT (khác `end` vốn đã bị kéo tối thiểu 2.5h/lấp khoảng trống để vẽ). */
  realEnd: Date;
  /** Số mẻ đã gộp vào thanh này (1 = chưa gộp) — chỉ hiện trong bảng chi tiết, KHÔNG in lên nhãn. */
  mergedCount: number;
}

/** Nội dung bảng chi tiết mẻ — hiện khi BẤM vào thanh Gantt (xem openDetailPopup). */
interface GanttDetail {
  id: string;
  group: string;
  machineCode: string;
  tankLabel: string;
  color: string | null;
  productCode: string | null;
  taskTitle: string;
  statusLabel: string;
  uncompleted: boolean;
  startText: string;
  endText: string;
  /** Nguồn giờ kết thúc: 'MES' (thật) | 'BPDB' (ước tính giờ pha) | 'BPDB_RUNNING' (đang chạy). */
  endSource: string;
  /** Khóa mẻ MES đã ghép (null nếu chưa ghép) — dùng để tải toàn bộ thông tin mẻ khi bấm. */
  mesBatchNo: string | null;
  mesLineNo: string | null;
  errorMessage: string | null;
  barColor: string;
  /** Số mẻ đã xong liên tiếp giống nhau được gộp chung vào thanh này (1 = chưa gộp). */
  mergedCount: number;
  /** Mã máy vật lý gốc (VD007...) = định dạng SUP_Storico.Macchina, để tra cân BPDB. */
  bpdbMachineCode: string | null;
  /** Mốc bắt đầu/kết thúc ISO của thanh — dùng làm khung giờ khớp cân BPDB. */
  startIso: string | null;
  endIso: string | null;
}

const buildDetail = (item: any, realEnd: Date, barColor: string): GanttDetail => {
  // group id có dạng "{machineCode}::{tankLabel}" (xem BpdbMachineMonitoringService::
  // getGanttTimeline) — tách lại để hiển thị, không cần field riêng từ backend.
  const [machineCode, tankLabel] = String(item.group).split('::');
  return {
    id: item.id,
    group: item.group,
    machineCode,
    tankLabel: tankLabel || '',
    color: item.color ?? null,
    productCode: item.productCode ?? null,
    taskTitle: item.taskTitle ?? '',
    statusLabel: rawTaskStatusLabel(item.taskStatus),
    uncompleted: !!item.uncompleted,
    startText: formatTime(item.start),
    endText: item.uncompleted ? t('bpdbMachinesGantt.endTextNotFinished') : formatTime(realEnd.toISOString()),
    endSource: item.endSource ?? 'BPDB',
    mesBatchNo: item.mesBatchNo ?? null,
    mesLineNo: item.mesLineNo != null ? String(item.mesLineNo) : null,
    errorMessage: item.errorMessage || null,
    barColor,
    mergedCount: 1,
    bpdbMachineCode: item.machineCode ?? null,
    startIso: item.start ?? null,
    endIso: item.end ?? realEnd.toISOString(),
  };
};

// Chi tiết từng mẻ giữ NGOÀI DataSet của vis-timeline (tra theo id) — trước đây nội dung này
// nhét vào field `title` của item, tức là tooltip hover mặc định của thư viện; yêu cầu
// 2026-08-03 đổi sang "bấm mới hiện" nên item không còn `title`, popup do trang tự vẽ bằng
// template Vue (an toàn hơn: dữ liệu BPDB được Vue tự escape, không phải ghép chuỗi HTML).
const itemDetails = new Map<string, GanttDetail>();

const detailPopup = ref<GanttDetail | null>(null);
const detailPopupStyle = ref('');
// Bảng thông tin mẻ MES bên phải — mở khi bấm vào thanh đã ghép MES, đóng bằng nút ✕ riêng.
const mesPanelOpen = ref(false);

/** Tổng số mẻ của 1 mã màu - mã hàng đã chạy từ đầu tới nay (API riêng, xem loadLotTotal). */
interface LotTotal {
  /** "{mã màu}|{mã hàng}" — dùng để bỏ qua phản hồi đến muộn của mẻ đã đóng/đổi sang mẻ khác. */
  key: string;
  total: number | null;
  firstRunAt: string | null;
  /** Chia theo từng máy VD, máy chạy nhiều nhất đứng trước. */
  byMachine: { machineCode: string; count: number }[];
  loading: boolean;
  failed: boolean;
}

const lotTotal = ref<LotTotal | null>(null);
// Đếm theo MÃ (không theo mẻ) nên nhiều thanh khác nhau dùng chung 1 kết quả; giữ lại trong
// phiên xem để bấm qua lại giữa các mẻ cùng mã không gọi lại BPDB (backend cũng đã cache 5
// phút, đây là lớp chặn thứ hai ngay tại trình duyệt).
const lotTotalCache = new Map<string, { total: number; firstRunAt: string | null; byMachine: { machineCode: string; count: number }[] }>();

const lotKeyOf = (d: GanttDetail) => (d.color && d.productCode ? `${d.color}|${d.productCode}` : '');

const loadLotTotal = async (detail: GanttDetail) => {
  const key = lotKeyOf(detail);
  if (!key) {
    lotTotal.value = null;
    return;
  }
  const cached = lotTotalCache.get(key);
  if (cached) {
    lotTotal.value = { key, ...cached, loading: false, failed: false };
    return;
  }
  lotTotal.value = { key, total: null, firstRunAt: null, byMachine: [], loading: true, failed: false };
  try {
    const res = await axios.get('/api/public/bpdb-machines-gantt/lot-total', {
      params: { color: detail.color, productCode: detail.productCode },
    });
    const total = typeof res.data.total === 'number' ? res.data.total : null;
    const firstRunAt = res.data.firstRunAt ?? null;
    const byMachine = Array.isArray(res.data.byMachine) ? res.data.byMachine : [];
    // total === null nghĩa là BPDB mất kết nối lúc đếm — KHÔNG cache lại, để lần bấm sau
    // còn thử lại được khi BPDB sống lại.
    if (total !== null) lotTotalCache.set(key, { total, firstRunAt, byMachine });
    if (lotTotal.value?.key !== key) return;
    lotTotal.value = { key, total, firstRunAt, byMachine, loading: false, failed: total === null };
  } catch {
    if (lotTotal.value?.key !== key) return;
    lotTotal.value = { key, total: null, firstRunAt: null, byMachine: [], loading: false, failed: true };
  }
};

// ---- Toàn bộ thông tin mẻ trong MES (bấm vào thanh đã ghép MES) ----
// Danh sách trường ưu tiên hiển thị, theo đúng tên field của MES eBatchLine. Nhãn hiển thị lấy
// từ locale (bpdbMachinesGantt.mesFields.<key>) nên thứ tự ở đây chỉ quyết định thứ tự hiển thị.
// Field không có trong dữ liệu sẽ tự bỏ qua. Phần còn lại xem trong "Tất cả trường".
const MES_FIELD_KEYS: string[] = [
  'batchNo',
  'batchState',
  'colorCode',
  'artCode',
  'rootArt',
  'custCode',
  'endCustCode',
  'orderUcode',
  'moCode',
  'pfmPfno',
  'machineNo',
  'oldMachineNo',
  'batchTypeName',
  'batchNum',
  'clNum',
  'clWet',
  'clShift',
  'beginTime',
  'beginByName',
  'getTime',
  'getByName',
  'endTime',
  'endByName',
  'pmcDateStr',
  'priorityRemark',
  'techRemark',
  'remark',
];

interface MesBatch {
  /** "{batchNo}|{lineNo}" — bỏ qua phản hồi đến muộn khi đã bấm sang mẻ khác. */
  key: string;
  raw: Record<string, any> | null;
  syncedAt: string | null;
  loading: boolean;
  failed: boolean;
}
const mesBatch = ref<MesBatch | null>(null);
const mesBatchCache = new Map<string, { raw: Record<string, any>; syncedAt: string | null }>();

/** Các cặp nhãn→giá trị ưu tiên (chỉ trường có dữ liệu), theo thứ tự MES_FIELD_KEYS. */
const mesFieldRows = computed(() => {
  const raw = mesBatch.value?.raw;
  if (!raw) return [] as { label: string; value: string }[];
  const out: { label: string; value: string }[] = [];
  for (const key of MES_FIELD_KEYS) {
    const v = raw[key];
    if (v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '0') {
      out.push({ label: t(`bpdbMachinesGantt.mesFields.${key}`), value: String(v) });
    }
  }
  return out;
});

/** Toàn bộ trường còn lại (không nằm trong danh sách ưu tiên, có dữ liệu) cho phần "xem tất cả". */
const mesFieldRowsRest = computed(() => {
  const raw = mesBatch.value?.raw;
  if (!raw) return [] as { label: string; value: string }[];
  const shown = new Set(MES_FIELD_KEYS);
  const out: { label: string; value: string }[] = [];
  for (const k of Object.keys(raw)) {
    if (shown.has(k)) continue;
    const v = raw[k];
    if (v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '0') {
      out.push({ label: k, value: String(v) });
    }
  }
  return out;
});

const loadMesBatch = async (detail: GanttDetail) => {
  if (!detail.mesBatchNo) {
    mesBatch.value = null;
    return;
  }
  const lineNo = detail.mesLineNo ?? '1';
  const key = `${detail.mesBatchNo}|${lineNo}`;
  const cached = mesBatchCache.get(key);
  if (cached) {
    mesBatch.value = { key, ...cached, loading: false, failed: false };
    return;
  }
  mesBatch.value = { key, raw: null, syncedAt: null, loading: true, failed: false };
  try {
    const res = await axios.get('/api/public/bpdb-machines-gantt/mes-batch', {
      params: { batchNo: detail.mesBatchNo, lineNo },
    });
    const raw = res.data?.data?.raw ?? null;
    const syncedAt = res.data?.data?.syncedAt ?? null;
    if (raw) mesBatchCache.set(key, { raw, syncedAt });
    if (mesBatch.value?.key !== key) return;
    mesBatch.value = { key, raw, syncedAt, loading: false, failed: raw === null };
  } catch {
    if (mesBatch.value?.key !== key) return;
    mesBatch.value = { key, raw: null, syncedAt: null, loading: false, failed: true };
  }
};

// Bảng liệu (song song MES + BPDB) hiển thị trong panel bên phải khi bấm vào mẻ.
interface RecipeMaterial {
  code: string | null;
  name: string | null;
  amount: number | null;
  amount3: number | null;
  unit: string | null;
  kind: string;
}
interface DosingLine {
  materialCode: string | null;
  tank: string | number | null;
  requestedGrams: number | null;
  actualGrams: number | null;
}
interface BatchRecipe {
  key: string;
  loading: boolean;
  mesFormula: { pfmPfno: string; syncedAt: string | null; dyes: RecipeMaterial[]; aux: RecipeMaterial[] } | null;
  bpdbDosing: { available: boolean; lineCount: number; lines: DosingLine[] } | null;
}
const batchRecipe = ref<BatchRecipe | null>(null);
const batchRecipeCache = new Map<string, { mesFormula: BatchRecipe['mesFormula']; bpdbDosing: BatchRecipe['bpdbDosing'] }>();

const loadBatchRecipe = async (detail: GanttDetail) => {
  const key = detail.id;
  const cached = batchRecipeCache.get(key);
  if (cached) {
    batchRecipe.value = { key, loading: false, ...cached };
    return;
  }
  batchRecipe.value = { key, loading: true, mesFormula: null, bpdbDosing: null };
  try {
    const res = await axios.get('/api/public/bpdb-machines-gantt/batch-recipe', {
      params: {
        mesBatchNo: detail.mesBatchNo ?? '',
        mesLineNo: detail.mesLineNo ?? '',
        machineCode: detail.bpdbMachineCode ?? '',
        taskTitle: detail.taskTitle ?? '',
        start: detail.startIso ?? '',
        end: detail.endIso ?? '',
      },
    });
    const data = res.data?.data ?? {};
    const payload = { mesFormula: data.mesFormula ?? null, bpdbDosing: data.bpdbDosing ?? null };
    batchRecipeCache.set(key, payload);
    if (batchRecipe.value?.key !== key) return;
    batchRecipe.value = { key, loading: false, ...payload };
  } catch {
    if (batchRecipe.value?.key !== key) return;
    batchRecipe.value = { key, loading: false, mesFormula: null, bpdbDosing: null };
  }
};

const closeDetailPopup = () => {
  detailPopup.value = null;
  lotTotal.value = null;
  mesBatch.value = null;
  batchRecipe.value = null;
  mesPanelOpen.value = false;
};

// Ước lượng khổ popup để kẹp trong khung nhìn — chỉ dùng cho phép tính vị trí, kích thước
// thật do CSS quyết định (xem .gantt-detail-popup).
const DETAIL_POPUP_W = 330;
const DETAIL_POPUP_H = 250;

const openDetailPopup = (id: string, pageX: number, pageY: number) => {
  const detail = itemDetails.get(id);
  if (!detail) return;
  // position: fixed nên phải quy về toạ độ khung nhìn (vis-timeline trả pageX/pageY).
  const x = pageX - window.scrollX;
  const y = pageY - window.scrollY;
  const left = Math.max(8, Math.min(x + 14, window.innerWidth - DETAIL_POPUP_W - 8));
  const top = y + 14 + DETAIL_POPUP_H > window.innerHeight
    ? Math.max(8, y - 14 - DETAIL_POPUP_H)
    : y + 14;
  detailPopupStyle.value = `left: ${left}px; top: ${top}px;`;
  detailPopup.value = detail;
  loadLotTotal(detail);
  loadMesBatch(detail);
  loadBatchRecipe(detail);
  // Mở/đóng bảng MES bên phải theo việc mẻ có ghép được MES hay không.
  mesPanelOpen.value = !!detail.mesBatchNo;
};

// Bấm ra ngoài popup thì đóng. Bỏ qua thao tác bấm bên trong vùng biểu đồ — sự kiện 'click'
// của vis-timeline đã tự quyết định mở mẻ khác hay đóng, xử lý thêm ở đây chỉ gây tranh chấp.
const closeDetailOnOutsideClick = (event: MouseEvent) => {
  if (!detailPopup.value) return;
  const target = event.target as HTMLElement | null;
  if (!target) return;
  if (target.closest('.gantt-detail-popup')) return;
  if (target.closest('.gantt-mes-panel')) return;
  if (target.closest('.gantt-recipe-panel')) return;
  if (timelineEl.value?.contains(target)) return;
  closeDetailPopup();
};

const closeDetailOnEscape = (event: KeyboardEvent) => {
  if (event.key === 'Escape') closeDetailPopup();
};

const fetchGantt = async () => {
  const res = await axios.get('/api/public/bpdb-machines-gantt', { params: { fromDate: fromDate.value, toDate: toDate.value } });
  bpdbConnected.value = res.data.bpdbConnected;
  lastSyncedAt.value = res.data.lastSyncedAt ?? null;
  dataAgeSeconds.value = Math.round(res.data.dataAgeSeconds ?? 0);
  dataStale.value = !!res.data.stale;
  totalRecords.value = res.data.totalRecords ?? 0;
  syncSnapshot.value = new Date();

  // groups gồm cả group cha (Máy VD, có nestedGroups) lẫn group con (từng Tank thật của
  // máy đó) — vis-timeline tự vẽ Tank thành các hàng con thụt vào dưới Máy VD, đóng/mở
  // được (xem getGanttTimeline() backend).
  const groups = (res.data.groups || []).map((g: any) => ({
    id: g.id,
    content: g.content,
    order: g.order,
    nestedGroups: g.nestedGroups && g.nestedGroups.length ? g.nestedGroups : undefined,
  }));
  itemDetails.clear();
  let items: GanttItem[] = (res.data.items || []).map((it: any): GanttItem => {
    const start = new Date(it.start);
    const realEnd = it.uncompleted ? syncSnapshot.value : new Date(it.end);
    // Độ dài hiển thị tối thiểu 2h (yêu cầu 2026-07-29) — mẻ chạy thật sự ngắn hơn vẫn được
    // vẽ thanh rộng bằng đúng 2h theo tỉ lệ thời gian hiện tại (co giãn đúng theo zoom, khác
    // với ép min-width theo px cố định), đủ chỗ cho nhãn trong đa số trường hợp mà vẫn giữ
    // đúng bản chất "biểu thị theo thời gian" của Gantt chart. Chỉ ảnh hưởng bề rộng vẽ —
    // bảng chi tiết vẫn dùng realEnd (giờ kết thúc thật) để không hiển thị sai lệch dữ liệu.
    const displayEnd = realEnd.getTime() - start.getTime() < MIN_VISUAL_DURATION_MS
      ? new Date(start.getTime() + MIN_VISUAL_DURATION_MS)
      : realEnd;
    const label = it.color && it.productCode ? `${it.color}-${it.productCode}` : (it.taskTitle || '—');
    const color = it.colorHex || FALLBACK_BAR_COLOR;
    const textColor = labelColorOn(color);
    const textShadow = labelShadowFor(textColor);
    // realEnd (giờ kết thúc THẬT) chỉ dùng cho bảng chi tiết — bề rộng vẽ dùng displayEnd.
    itemDetails.set(it.id, buildDetail(it, realEnd, color));
    return {
      id: it.id,
      group: it.group,
      start,
      end: displayEnd,
      // Đang chạy = viền đỏ nhấp nháy; đã ghép được giờ kết thúc THẬT từ MES = viền xanh
      // dương nhấp nháy (yêu cầu 2026-08-08). Hai trạng thái loại trừ nhau (mẻ ghép MES
      // luôn uncompleted=false).
      className: it.uncompleted ? 'gantt-item-running' : (it.endSource === 'MES' ? 'gantt-item-mes' : ''),
      style: `background-color: ${color}; color: ${textColor};`,
      // Nền màu đặt luôn trên nhãn (thẻ) chứ không chỉ trên thanh ngoài — nhãn nay bị cắt gọn
      // trong lòng thanh (xem CSS .gantt-item-label), tô cùng màu để không lộ vệt nền khác
      // màu giữa chữ và mép thanh.
      content: `<div class="gantt-item-label" style="background-color: ${color}; color: ${textColor}; text-shadow: ${textShadow};">${label}</div>`,
      // Chỉ gộp được khi tách được ĐỦ mã màu + mã hàng — TaskTitle thô không tách được thì
      // để rỗng, thà vẽ tách rời còn hơn gộp nhầm 2 mẻ khác nhau.
      mergeKey: it.color && it.productCode ? `${it.color}|${it.productCode}` : '',
      uncompleted: !!it.uncompleted,
      realEnd,
      mergedCount: 1,
    };
  });

  // Xếp các mẻ trên CÙNG 1 Tank thành chuỗi liền mạch, TUYỆT ĐỐI không đè lên nhau (yêu cầu
  // 2026-08-03) — chấp nhận vẽ SAI giờ: mẻ nào không đủ chỗ thì bị ĐẨY về phía trước (sớm hơn
  // giờ thật), lan truyền tiếp sang các mẻ trước nó. Kết hợp với yêu cầu 2026-07-29 trước đó
  // ("nếu có khoảng trống giữa các mẻ thì kéo dài chạm tới mẻ bên phải gần nhất"): khoảng
  // trống vẫn được lấp, chỉ khác là giờ BẮT ĐẦU nay cũng được phép dịch, không chỉ giờ kết thúc.
  // Từ 2026-08-05, gốc toạ độ để xếp là KIM ĐỎ (giờ hiện tại) chứ không còn là mẻ sớm nhất:
  // mẻ đã xong dồn về bên trái kim, mẻ đang chạy đẩy sang bên phải kim (xem 3 lượt bên dưới).
  //
  // Vì sao bản trước vẫn đè dù đã ép end = start của mẻ kế tiếp: hai mẻ bắt đầu cách nhau vài
  // phút (hoặc trùng giờ) tạo ra thanh gần như rộng 0, nhưng CSS còn ép min-width theo px và
  // cho nhãn tràn ra ngoài -> phần vẽ thật vẫn phủ lên mẻ bên phải. Nay bề rộng TỐI THIỂU
  // được bảo đảm ngay trong dữ liệu (theo THỜI GIAN, co giãn đúng theo zoom) nên CSS không
  // cần ép min-width/tràn nhãn nữa (xem .vis-item trong <style>).
  //
  // Bảng chi tiết (bấm vào thanh) vẫn lấy giờ THẬT từ itemDetails — dữ liệu không sai lệch,
  // chỉ riêng vị trí VẼ mới bị dịch.
  const itemsByTank = new Map<string, typeof items>();
  for (const item of items) {
    if (!itemsByTank.has(item.group)) itemsByTank.set(item.group, []);
    itemsByTank.get(item.group)!.push(item);
  }
  // Lượt 0 — GỘP các mẻ ĐÃ XONG liên tiếp giống hệt nhau trên cùng 1 Tank thành 1 thanh duy
  // nhất (yêu cầu 2026-08-03): cùng mã màu + mã hàng và nằm kề nhau trong chuỗi thời gian của
  // Tank đó. Mẻ ĐANG CHẠY không bao giờ bị gộp (kể cả khi trùng mã với mẻ trước) — nó phải
  // giữ riêng viền đỏ nhấp nháy và chưa có giờ kết thúc thật. Thanh gộp lấy giờ bắt đầu của
  // mẻ đầu, giờ kết thúc của mẻ cuối; nhãn giữ nguyên "{mã màu}-{mã hàng}", KHÔNG ghi thêm
  // số mẻ (yêu cầu 2026-08-03) — số mẻ chỉ hiện trong bảng chi tiết khi bấm vào thanh.
  // Phải chạy TRƯỚC lượt 1/lượt 2 bên dưới: gộp xong mới biết còn bao nhiêu thanh thật sự
  // cần chừa chỗ tối thiểu, tránh đẩy giờ sang phải một cách vô ích.
  for (const [groupId, tankItems] of itemsByTank) {
    tankItems.sort((a, b) => a.start.getTime() - b.start.getTime());
    const merged: GanttItem[] = [];
    for (const item of tankItems) {
      const prev = merged[merged.length - 1];
      const canMerge = !!prev && !prev.uncompleted && !item.uncompleted
        && prev.mergeKey !== '' && prev.mergeKey === item.mergeKey;
      if (!canMerge) {
        merged.push(item);
        continue;
      }
      prev.mergedCount++;
      if (item.end.getTime() > prev.end.getTime()) prev.end = item.end;
      if (item.realEnd.getTime() > prev.realEnd.getTime()) prev.realEnd = item.realEnd;
      const detail = itemDetails.get(prev.id);
      if (detail) {
        detail.mergedCount = prev.mergedCount;
        detail.endText = formatTime(prev.realEnd.toISOString());
      }
      // Mẻ bị nuốt không còn thanh riêng để bấm vào nữa -> bỏ luôn chi tiết cho khỏi rác.
      itemDetails.delete(item.id);
    }
    itemsByTank.set(groupId, merged);
  }
  items = Array.from(itemsByTank.values()).flat();

  // Mốc "hiện tại" của biểu đồ = đúng vị trí kim đỏ đang vẽ (xem calculateNeedle, cùng dùng
  // syncSnapshot chứ không dùng đồng hồ trình duyệt). Yêu cầu 2026-08-05: mẻ CHƯA có giờ kết
  // thúc (đang chạy) LUÔN nằm hẳn bên PHẢI kim đỏ, mẻ đã xong LUÔN nằm hẳn bên TRÁI — được
  // phép đẩy các mẻ khác trên cùng hàng lùi về trái để lấy chỗ. Nhờ vậy nhìn vào biểu đồ là
  // biết ngay Tank nào đang chạy (thanh nằm sau vạch giờ) mà không phải dò từng thanh.
  const nowMs = syncSnapshot.value.getTime();

  for (const tankItems of itemsByTank.values()) {
    // Tách theo TRẠNG THÁI chứ không theo thứ tự thời gian: dữ liệu BPDB thỉnh thoảng có mẻ
    // đang chạy bắt đầu SỚM hơn một mẻ đã xong trên cùng Tank (mẻ chạy dài, mẻ ngắn xen vào),
    // nếu cắt theo vị trí trong mảng thì mẻ đã xong đó sẽ bị đẩy nhầm sang phải kim đỏ.
    const doneItems = tankItems.filter(it => !it.uncompleted);
    const runningItems = tankItems.filter(it => it.uncompleted);

    // Lượt 1 — mẻ ĐÃ XONG: xếp NGƯỢC từ kim đỏ lùi về quá khứ. Mẻ cuối cùng kết thúc muộn
    // nhất là tại kim đỏ; mẻ nào không đủ chỗ thì bị ĐẨY SANG TRÁI (sớm hơn giờ thật), lan
    // truyền tiếp sang các mẻ trước nó — vẫn giữ nguyên độ dài và khoảng cách tối thiểu
    // MIN_VISUAL_DURATION_MS nên không mẻ nào bị bóp hẹp hay đè lên nhau.
    let limit = nowMs;
    for (let i = doneItems.length - 1; i >= 0; i--) {
      const item = doneItems[i];
      const duration = item.end.getTime() - item.start.getTime();
      const latestStart = limit - Math.max(duration, MIN_VISUAL_DURATION_MS);
      if (item.start.getTime() > latestStart) {
        item.start = new Date(latestStart);
        item.end = new Date(latestStart + duration);
      }
      limit = item.start.getTime();
    }

    // Lượt 2 — mẻ ĐANG CHẠY: xếp XUÔI từ kim đỏ về phía tương lai, thanh nằm TRỌN bên phải
    // vạch giờ. Bề rộng giữ nguyên = thời gian đã chạy được tính tới lúc đồng bộ (mẻ vừa mở
    // thì đúng bằng MIN_VISUAL_DURATION_MS), nên thanh dài vẫn nói lên mẻ đã chạy lâu — chỉ
    // khác là phần vẽ đó nay đổ về phía tương lai thay vì về quá khứ.
    let cursor = nowMs;
    for (const item of runningItems) {
      const duration = item.end.getTime() - item.start.getTime();
      const startMs = Math.max(item.start.getTime(), cursor);
      item.start = new Date(startMs);
      item.end = new Date(startMs + duration);
      cursor = startMs + MIN_VISUAL_DURATION_MS;
    }

    // Lượt 3 — lấp khoảng trống: mỗi mẻ kéo dài chạm đúng giờ bắt đầu (đã dịch) của mẻ kế
    // tiếp CÙNG NHÓM. Nhờ 2 lượt trên, khoảng cách đó luôn >= MIN_VISUAL_DURATION_MS nên
    // không mẻ nào bị bóp hẹp lại. Mẻ đã xong CUỐI CÙNG của mỗi Tank LUÔN kéo tới chạm kim
    // đỏ (yêu cầu 2026-08-06), kể cả khi Tank đó không có mẻ nào đang chạy — trước đây chỉ
    // kéo khi Tank đang chạy nên hàng nào nghỉ sẽ hở một khoảng trống tới vạch giờ. Không
    // lấn sang mẻ đang chạy vì lượt 2 đã đẩy mọi mẻ đang chạy sang hẳn bên phải kim.
    for (let i = 0; i < doneItems.length - 1; i++) {
      doneItems[i].end = doneItems[i + 1].start;
    }
    const lastDone = doneItems[doneItems.length - 1];
    if (lastDone) lastDone.end = new Date(nowMs);
    for (let i = 0; i < runningItems.length - 1; i++) {
      runningItems[i].end = runningItems[i + 1].start;
    }
  }

  // Phát hiện mẻ ĐANG CHẠY mới xuất hiện so với lần fetch trước (xem khai báo
  // newlyAppearedRunningIds phía trên <script>) — dùng cho tính năng "Tự nhảy tới mẻ mới".
  const currentUncompletedIds = new Set(items.filter(it => it.className === 'gantt-item-running').map(it => it.id));
  newlyAppearedRunningIds = isFirstFetch
    ? []
    : [...currentUncompletedIds].filter(id => !previousUncompletedIds.has(id));
  previousUncompletedIds = currentUncompletedIds;
  isFirstFetch = false;

  allGroups = groups;
  allItems = items;
  applyMachineFilter();
};

// Máy đã ghim (bấm icon 📌 trên tiêu đề máy) — id máy, không phải Tank. Sống độc lập với
// allGroups/allItems nên không mất khi auto tải lại 30s hay đổi ô tìm kiếm.
const pinnedMachineIds = ref<Set<string>>(new Set());

const togglePinMachine = (machineId: string) => {
  if (!machineId) return;
  const next = new Set(pinnedMachineIds.value);
  if (next.has(machineId)) next.delete(machineId);
  else next.add(machineId);
  pinnedMachineIds.value = next;
  applyMachineFilter();
};

// Lọc theo tên/mã máy gõ ở ô tìm kiếm (header cột tên máy) — khớp theo group CHA (Máy VD,
// có nestedGroups), giữ nguyên toàn bộ Tank con của máy khớp chứ không lọc riêng từng Tank
// (đúng nhu cầu "tìm tên máy", không phải "tìm tank"). Lọc tại chỗ trên dữ liệu đã tải,
// không gọi lại API. Đồng thời vẽ lại icon ghim + đẩy máy đã ghim lên đầu danh sách mỗi
// khi hàm này chạy lại (yêu cầu 2026-07-29).
const applyMachineFilter = () => {
  // PHẢI đọc scrollTop NGAY ĐẦU hàm, trước mọi thao tác trên groupsDataSet/itemsDataSet bên
  // dưới — itemsDataSet.clear()/.add() một mình nó đã đủ khiến vis-timeline tự đổi scrollTop
  // (đồng bộ lại panel trái/giữa trong lúc vẽ lại item), nên nếu đọc SAU các dòng đó thì giá
  // trị đọc được đã bị sai từ trước khi kịp lưu (đã đo thực nghiệm bằng cách chặn setter
  // scrollTop: bug thật nằm ở chỗ đọc trễ, không phải ở chỗ khôi phục trễ như tưởng ban đầu).
  const savedScrollTop = timeline
    ? (timelineEl.value?.querySelector('.vis-panel.vis-left') as HTMLElement | null)?.scrollTop ?? 0
    : 0;
  const q = machineSearch.value.trim().toLowerCase();
  let visibleGroups = allGroups;
  if (q) {
    const matchedMachineIds = new Set(
      allGroups.filter(g => g.nestedGroups && String(g.content).toLowerCase().includes(q)).map(g => g.id)
    );
    visibleGroups = allGroups.filter(g => {
      if (g.nestedGroups) return matchedMachineIds.has(g.id);
      return matchedMachineIds.has(String(g.id).split('::')[0]);
    });
  }
  const visibleGroupIds = new Set(visibleGroups.map(g => g.id));
  const visibleItems = allItems.filter(it => visibleGroupIds.has(it.group));

  // CHỈ vẽ hàng Tank — hàng riêng của Máy VD (group cha có nestedGroups) đã bị bỏ hẳn khỏi
  // biểu đồ (yêu cầu 2026-08-05): nó không chứa thanh mẻ nào, chỉ tạo một dải trống ngăn giữa
  // các cụm máy. Tên máy nay nằm trong ô gộp vẽ đè lên chính hàng Tank ĐẦU TIÊN của máy đó và
  // kéo dài xuống hết cụm (chiều cao do capNhatOGopMay() đo).
  // ĐÁNH ĐỔI đã chấp nhận: không còn group cha nên mất luôn mũi tên đóng/mở gọn từng máy của
  // vis-timeline (tính năng đó gắn liền với nestedGroups). Ghim máy và tìm máy vẫn nguyên vẹn.
  // allGroups VẪN giữ đủ cả group cha — cần chúng để biết TÊN máy và để ô tìm kiếm khớp theo
  // tên máy; chỉ khác là chúng không được nạp vào vis nữa.
  const machineGroupById = new Map<string, any>(
    allGroups.filter(g => g.nestedGroups).map(g => [String(g.id), g])
  );
  // Hàng Tank đầu tiên của mỗi máy = hàng mang ô tên máy. Dựa vào thứ tự duyệt của
  // visibleGroups (giữ nguyên thứ tự backend trả về: 4 Tank rồi tới group cha, máy này nối
  // tiếp máy kia) nên hàng bắt gặp đầu tiên luôn là hàng trên cùng của cụm.
  const seenMachines = new Set<string>();
  const renderedGroups = visibleGroups.filter(g => !g.nestedGroups).map(g => {
    const machineId = String(g.id).split('::')[0];
    const machineGroup = machineGroupById.get(machineId);
    const machineName = machineGroup ? String(machineGroup.content) : machineId;
    const isPinned = pinnedMachineIds.value.has(machineId);
    const isMachineHead = !seenMachines.has(machineId);
    if (isMachineHead) seenMachines.add(machineId);

    // Ô tên máy GỘP theo chiều dọc, phủ luôn các hàng Tank còn lại của máy đó (yêu cầu
    // 2026-08-02) — box absolute, chiều cao do capNhatOGopMay() đo lại sau mỗi lần vis vẽ
    // xong. Icon ghim đặt bên PHẢI tên máy cho dễ ấn (yêu cầu 2026-07-29).
    const machineCard = isMachineHead
      ? `<span class="gantt-machine-merged"><span class="gantt-machine-row"><span class="gantt-machine-name" title="${machineName}">${machineName}</span><span class="gantt-pin-btn${isPinned ? ' is-pinned' : ''}" data-machine-id="${machineId}" title="${isPinned ? t('bpdbMachinesGantt.unpinTitle') : t('bpdbMachinesGantt.pinTitle')}">📌</span></span></span>`
      : '';

    return {
      ...g,
      // Máy đã ghim trừ hẳn 100000 vào order cho TOÀN BỘ hàng Tank của máy đó (danh sách gốc
      // chỉ vài chục máy, order gốc luôn nhỏ hơn nhiều) — cả cụm cùng nhảy lên đầu và giữ
      // nguyên thứ tự tương đối bên trong cụm lẫn giữa các máy cùng nhóm ghim/chưa ghim.
      order: isPinned ? g.order - 100000 : g.order,
      // Hàng mang ô tên máy phải có z-index (xem rule .gantt-machine-head trong <style>).
      className: isMachineHead ? 'gantt-machine-head' : '',
      // Tên tank bọc trong thẻ riêng (.gantt-tank-pill) thay vì tô thẳng lên .vis-inner: vis
      // lấy .vis-inner.clientHeight làm chiều cao TỐI THIỂU của hàng, nên .vis-inner phải là
      // ô cao cố định ROW_HEIGHT trong suốt (kể cả Tank rỗng cũng cao bằng hàng khác), còn
      // hình viên thuốc thì nằm ở thẻ con — xem <style>.
      content: `${machineCard}<span class="gantt-tank-pill">${g.content}</span>`,
    };
  });

  groupsDataSet.clear();
  groupsDataSet.add(renderedGroups);
  itemsDataSet.clear();
  itemsDataSet.add(visibleItems);
  totalRecords.value = visibleItems.length;

  // Bảng chi tiết đang mở phải bám theo dữ liệu vừa nạp: mẻ bị lọc mất (tìm máy) hoặc biến
  // khỏi kết quả sau lần Auto tải lại 30s thì đóng lại, còn thì thay bằng bản mới nhất (mẻ
  // đang chạy có thể đã đổi trạng thái/có lỗi mới) — nếu không, popup sẽ đứng im hiển thị
  // dữ liệu cũ trong khi thanh Gantt phía sau đã đổi.
  if (detailPopup.value) {
    const stillVisible = visibleItems.some(it => it.id === detailPopup.value!.id);
    const fresh = itemDetails.get(detailPopup.value.id);
    if (!stillVisible || !fresh) closeDetailPopup();
    else {
      detailPopup.value = fresh;
      // Cùng id thì gần như luôn cùng mã, nhưng vẫn kiểm tra khoá: nếu khác (hoặc lần trước
      // đếm hụt do BPDB rớt) thì đếm lại, còn trùng thì lấy ngay từ lotTotalCache.
      if (lotTotal.value?.key !== lotKeyOf(fresh)) loadLotTotal(fresh);
      const mesKey = fresh.mesBatchNo ? `${fresh.mesBatchNo}|${fresh.mesLineNo ?? '1'}` : '';
      if ((mesBatch.value?.key ?? '') !== mesKey) loadMesBatch(fresh);
    }
  }

  // clear()+add() phát sự kiện 'add' bình thường (đủ để vẽ lại nội dung/nhãn — search và
  // icon ghim đã chạy đúng qua đường này), nhưng THỨ TỰ hàng dựa trên field order chỉ được
  // tính lại chắc chắn khi gọi setGroups() — API chính thức của vis-timeline để "nạp lại
  // toàn bộ", đảm bảo _orderGroups() chạy lại (đúng lỗi báo cáo: bấm ghim không đẩy máy lên
  // đầu — icon đổi màu nhưng vị trí hàng không đổi).
  if (timeline) {
    // setGroups() dựng lại DOM panel trái từ đầu -> tự kéo scrollTop dọc về 0, làm mất vị
    // trí đang xem mỗi lần Auto tải lại 30s chạy ngầm (báo cáo: đang xem máy ở dưới danh
    // sách, cứ 30s bị kéo ngược lên đầu). Chỉ ảnh hưởng cuộn DỌC (panel trái, danh sách
    // Máy VD/Tank) — cuộn NGANG (trục thời gian, do "Auto cuộn"/nút "Về hiện tại" điều
    // khiển riêng qua timeline.setWindow) không liên quan, không đổi. savedScrollTop đã đọc
    // sẵn ở ĐẦU hàm applyMachineFilter (xem ghi chú tại đó) — dùng lại ở đây, không đọc lại.
    timeline.setGroups(groupsDataSet);
    if (savedScrollTop > 0) {
      // PHẢI truy lại .vis-panel.vis-left SAU setGroups(), không dùng lại tham chiếu cũ —
      // setGroups() dựng lại panel trái thành node DOM MỚI, gán scrollTop lên node cũ (đã
      // rời khỏi cây DOM) không có tác dụng gì. Và KHÔNG đủ để chỉ gán 1 lần ngay sau
      // setGroups()/1 requestAnimationFrame — vis-timeline tự chạy thêm 1 vòng redraw nội bộ
      // ngay sau đó (đồng bộ lại scroll giữa panel trái và panel giữa) ghi đè scrollTop vừa
      // set về một giá trị khác (đã đo thực nghiệm: set xong đúng, ~150-200ms sau tự nhảy về
      // giá trị sai). Phải đợi đúng sự kiện 'changed' (thư viện tự phát sau khi redraw nội bộ
      // ổn định — xem body.emitter.emit('changed') trong core.js) rồi gán lại LẦN NỮA mới giữ
      // được, không phải đoán mò theo thời gian (setTimeout cố định dễ vỡ nếu máy chậm/nhanh
      // khác nhau).
      const restoreScroll = () => {
        const freshLeftPanel = timelineEl.value?.querySelector('.vis-panel.vis-left') as HTMLElement | null;
        if (freshLeftPanel) freshLeftPanel.scrollTop = savedScrollTop;
      };
      restoreScroll();
      timeline.once('changed', restoreScroll);
    }
  }
};

watch(machineSearch, applyMachineFilter);

const loadGantt = async () => {
  loading.value = true;
  errorMsg.value = '';
  try {
    await fetchGantt();
    if (!timeline) {
      await nextTick();
      initTimeline();
    }
    calculateNeedle();
    // Tự cuộn DỌC tới mẻ vừa mới xuất hiện + đang chạy — gọi SAU cùng (sau khi
    // applyMachineFilter()/setGroups() ở trong fetchGantt() đã chạy xong và đã tự khôi phục
    // scrollTop cũ) vì focus() cần đi ghi đè lên đúng vị trí khôi phục đó khi có mẻ mới, chứ
    // không phải tranh chấp/bị khôi phục ghi đè ngược lại. timeline.focus() là API chính
    // thức của vis-timeline — tự tính cuộn dọc đúng theo Tank đang thuộc (kể cả khi Tank đó
    // đang ở trong 1 Máy VD đã đóng gọn) và cuộn ngang bám theo mẻ đó, KHÔNG zoom sát vào
    // (zoom:false) để giữ nguyên độ rộng khung giờ 24h đang xem.
    if (autoJumpNew.value && newlyAppearedRunningIds.length && timeline) {
      const idsToFocus = newlyAppearedRunningIds;
      // Đợi 1 nhịp trước khi focus() — itemsDataSet.add() ở applyMachineFilter() vừa chạy
      // xong lúc setGroups()/redraw còn đang xử lý bất đồng bộ, mẻ mới có thể CHƯA kịp có
      // đại diện DOM/nội bộ trong itemSet lúc focus() cần tới (this.itemSet.items[id]) —
      // gọi ngay lập tức focus() im lặng không cuộn gì cả (đã đo thực nghiệm). requestAnimationFrame
      // đủ để đợi qua vòng redraw đó.
      requestAnimationFrame(() => timeline?.focus(idsToFocus, { zoom: false }));
    }
  } catch (e: any) {
    errorMsg.value = e.response?.data?.message || t('bpdbMachinesGantt.errorFallback');
  } finally {
    loading.value = false;
  }
};

const initTimeline = () => {
  if (!timelineEl.value) return;
  timeline = new Timeline(timelineEl.value, itemsDataSet, groupsDataSet, {
    // stack:false (yêu cầu 2026-07-29) — 1 Tank chỉ có đúng 1 hàng; các mẻ trùng thời gian
    // trong cùng Tank đó vẽ đè lên nhau ngay trên hàng đó thay vì tự tách thêm hàng phụ
    // (mặc định stack:true của vis-timeline). Đè lên nhau không mất dữ liệu — mỗi mẻ vẫn
    // bấm/hover riêng được để xem tooltip.
    stack: false,
    // Không dùng cơ chế "chọn item" của vis-timeline (viền/nền xanh mặc định của .vis-selected
    // đè lên màu họ màu của mẻ) — trang tự vẽ bảng chi tiết khi bấm, xem sự kiện 'click' bên
    // dưới. Sự kiện 'click' vẫn bắn bình thường khi selectable: false.
    selectable: false,
    // Chiều cao mọi hàng bằng nhau — xem ghi chú tại ITEM_HEIGHT/ROW_HEIGHT phía trên.
    // 'fixed' = tính chiều cao nhóm theo TOÀN BỘ mẻ của nhóm, không phải chỉ mẻ đang lọt
    // khung nhìn (mặc định 'auto'), nên cuộn ngang trục thời gian không còn làm hàng co giãn.
    groupHeightMode: 'fixed',
    margin: { axis: ROW_MARGIN_AXIS, item: { horizontal: 0, vertical: ROW_MARGIN_ITEM_VERTICAL } },
    groupOrder: 'order',
    orientation: 'top',
    zoomKey: 'ctrlKey',
    height: '100%',
    // BẮT BUỘC khi height là giá trị cố định (không phải 'auto') — thiếu option này,
    // vis-timeline âm thầm CẮT các hàng vượt quá chiều cao container thay vì cho cuộn
    // (đúng lỗi báo cáo 2026-07-29: không cuộn xuống được để xem hết Máy VD/Tank).
    verticalScroll: true,
    showCurrentTime: false,
    locale: 'vi',
    start: new Date(syncSnapshot.value.getTime() - 12 * 3600 * 1000),
    end: new Date(syncSnapshot.value.getTime() + 12 * 3600 * 1000),
    // KHÔNG khai báo option `tooltip`: item không còn field `title` (chi tiết mẻ nay hiện khi
    // BẤM — xem itemDetails/openDetailPopup), nên tooltip hover mặc định của thư viện không
    // còn nội dung để hiện.
    //
    // vis-timeline mặc định lọc XSS trên MỌI content HTML tự cung cấp (group content, item
    // content) — bộ lọc mặc định của thư viện `xss` xóa sạch class/data-*/title, khiến
    // .gantt-machine-row/.gantt-machine-name/.gantt-pin-btn và data-machine-id biến mất khỏi
    // DOM thật dù HTML string vẫn đúng (đúng lỗi báo cáo 2026-07-29: icon ghim bấm không ăn —
    // do data-machine-id đã bị lọc mất, không phải do logic click sai). Phải khai báo rõ
    // whiteList đủ các thẻ/thuộc tính đang thực sự dùng (span cho group content, div cho nhãn
    // thanh Gantt) thay vì tắt hẳn xss — tắt hẳn sẽ mất luôn bảo vệ cho nội dung có lẫn dữ
    // liệu từ BPDB. Thiếu 1 thẻ nào trong whiteList sẽ khiến thẻ đó hiện ra dạng chữ HTML thô
    // thay vì bị render (đã gặp khi mới thêm whiteList chỉ có span — content thanh Gantt vỡ
    // thành text thô).
    xss: {
      filterOptions: {
        whiteList: {
          span: ['class', 'title', 'data-machine-id'],
          div: ['class', 'style'],
        },
      },
    },
  } as any);
  timeline.on('rangechange', calculateNeedle);
  timeline.on('rangechanged', calculateNeedle);
  // Bấm vào thanh mẻ -> hiện bảng chi tiết; bấm vào chỗ trống trong vùng biểu đồ -> đóng.
  // Dùng sự kiện 'click' của vis-timeline (không tự bắt DOM) vì nó trả sẵn id item nằm dưới
  // con trỏ, xử lý đúng cả trường hợp các mẻ vẽ đè lên nhau do stack: false.
  timeline.on('click', (props: any) => {
    if (props?.item == null) {
      closeDetailPopup();
      return;
    }
    // props.pageX/pageY lấy từ event gốc; với sự kiện cảm ứng do hammerjs tổng hợp, toạ độ
    // nằm ở event.center — lấy dự phòng để popup không rơi về góc trên trái trên tablet.
    const pageX = props.pageX ?? (props.event?.center?.x ?? 0) + window.scrollX;
    const pageY = props.pageY ?? (props.event?.center?.y ?? 0) + window.scrollY;
    openDetailPopup(String(props.item), pageX, pageY);
  });
  // 'changed' bắn sau MỖI lần vis-timeline vẽ xong (đổi nhóm, đổi item, zoom, cuộn) — đúng
  // thời điểm chiều cao thật của các hàng đã chốt để đo lại ô gộp.
  timeline.on('changed', capNhatOGopMay);

  // Icon ghim (.gantt-pin-btn) được vẽ bằng HTML thô trong content của group (vis-timeline
  // không có slot/sự kiện riêng cho nhãn group) — bắt sự kiện ở đây bằng delegation.
  // vis-timeline dùng hammerjs để nhận diện tap/đóng-mở nhóm, hammerjs lắng nghe mousedown/
  // touchstart trực tiếp trên .vis-panel.vis-left (bubble phase, KHÔNG phải click) — nên
  // phải chặn từ mousedown/touchstart ở PHA CAPTURE, sớm hơn hammerjs, mới ngăn được nó
  // toggle đóng/mở nhóm. CHỈ stopPropagation, KHÔNG preventDefault ở bước này — gọi
  // preventDefault trên touchstart sẽ khiến trình duyệt (trên thiết bị/màn hình cảm ứng)
  // không bắn sự kiện 'click' tổng hợp nữa, làm icon ghim im re không phản ứng gì (đúng lỗi
  // báo cáo). Việc toggle ghim thật sự vẫn xử lý ở 'click' bên dưới.
  const stopIfPinButton = (event: Event) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.gantt-pin-btn')) return;
    event.stopPropagation();
  };
  timelineEl.value.addEventListener('mousedown', stopIfPinButton, true);
  timelineEl.value.addEventListener('touchstart', stopIfPinButton, true);
  timelineEl.value.addEventListener('click', (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    const btn = target.closest('.gantt-pin-btn') as HTMLElement | null;
    if (!btn) return;
    event.stopPropagation();
    event.preventDefault();
    togglePinMachine(btn.dataset.machineId || '');
  }, true);
};

// Kéo dài ô tên máy (cột 1) xuống hết các hàng Tank của chính máy đó, tạo hiệu ứng "gộp ô"
// như bảng có rowspan. HTML/CSS thuần không làm được vì mỗi hàng .vis-label là một khối
// riêng, chiều cao lại thay đổi theo số mẻ đang vẽ trên từng Tank — nên phải đo bằng JS.
// Chỉ ghi style.height của một box absolute (không nằm trong luồng bố cục) nên không làm
// vis-timeline phải tính lại chiều cao hàng, không tạo vòng lặp vẽ lại.
const capNhatOGopMay = () => {
  const labels = Array.from(
    timelineEl.value?.querySelectorAll('.vis-labelset .vis-label') ?? []
  ) as HTMLElement[];
  for (let i = 0; i < labels.length; i++) {
    const box = labels[i].querySelector<HTMLElement>('.gantt-machine-merged');
    if (!box) continue;
    let tong = labels[i].offsetHeight;
    // Mọi hàng sau đó đều là Tank CÙNG MÁY cho tới khi gặp hàng mang ô tên máy kế tiếp. Từ
    // 2026-08-05 không còn hàng riêng của Máy VD nên không thể nhận biết bằng class
    // .vis-nested-group như trước — mốc phân cụm nay chính là sự có mặt của ô gộp.
    for (let j = i + 1; j < labels.length && !labels[j].querySelector('.gantt-machine-merged'); j++) {
      tong += labels[j].offsetHeight;
    }
    // Trừ 4px (kèm top:2px trong CSS) để 2 thẻ máy liền nhau có khe hở, không dính thành
    // một khối dài liên tục.
    box.style.height = `${Math.max(tong - 4, 0)}px`;
  }
};

const calculateNeedle = () => {
  if (!timeline) return;
  const range = timeline.getWindow();
  const startMs = range.start.getTime();
  const endMs = range.end.getTime();
  const syncMs = syncSnapshot.value.getTime();
  if (syncMs < startMs || syncMs > endMs) {
    needleVisible.value = false;
    return;
  }
  const pct = ((syncMs - startMs) / (endMs - startMs)) * 100;
  needleVisible.value = true;
  needleStyle.value = `left: calc(${LABEL_COLUMN_WIDTH}px + (100% - ${LABEL_COLUMN_WIDTH}px) * ${pct / 100});`;
  needleLabel.value = formatTime(syncSnapshot.value.toISOString());
};

const moveToNow = () => {
  if (!timeline) return;
  timeline.setWindow(
    new Date(syncSnapshot.value.getTime() - 12 * 3600 * 1000),
    new Date(syncSnapshot.value.getTime() + 12 * 3600 * 1000),
    { animation: { duration: 500, easingFunction: 'easeInOutQuad' } }
  );
};

// Chỉ có hiệu lực thị giác khi isAdminUser (trang được bọc AppLayout) — bật/tắt
// isFullscreen đổi kích thước khung .content-container (ẩn/hiện sidebar+topbar), vis-timeline
// không tự vẽ lại theo nên phải ép redraw().
watch(isFullscreen, () => setTimeout(() => timeline?.redraw(), 60));

let refreshTimer: ReturnType<typeof setInterval> | null = null;
let moveTimer: ReturnType<typeof setInterval> | null = null;
// isFullscreen là singleton DÙNG CHUNG toàn app (services/layout.ts) — nếu Admin điều
// hướng sang trang Gantt bằng router-link từ trang khác (không reload trang, singleton giữ
// nguyên giá trị cũ), phải lưu lại giá trị đó để khôi phục khi rời trang, tránh làm "rò rỉ"
// trạng thái ẩn/hiện sidebar sang các trang khác trong cùng phiên SPA.
const previousIsFullscreen = isFullscreen.value;

onMounted(async () => {
  if (isAdminUser) {
    // Mặc định ẩn sidebar+topbar khi vừa vào trang Gantt — giữ đúng giao diện gọn quen
    // thuộc của trang public, chỉ lộ ra khi Admin bấm nút "Mở menu điều hướng".
    isFullscreen.value = true;
  }
  await loadGantt();
  refreshTimer = setInterval(() => {
    if (autoRefresh.value) loadGantt();
  }, 30000);
  moveTimer = setInterval(() => {
    if (autoMove.value && timeline) moveToNow();
  }, 5000);
  document.addEventListener('fullscreenchange', syncBrowserFullscreenState);
  fullscreenMedia.addEventListener('change', syncBrowserFullscreenState);
  document.addEventListener('mousedown', closeDetailOnOutsideClick);
  document.addEventListener('keydown', closeDetailOnEscape);
});

onUnmounted(() => {
  if (refreshTimer) clearInterval(refreshTimer);
  if (moveTimer) clearInterval(moveTimer);
  document.removeEventListener('fullscreenchange', syncBrowserFullscreenState);
  fullscreenMedia.removeEventListener('change', syncBrowserFullscreenState);
  document.removeEventListener('mousedown', closeDetailOnOutsideClick);
  document.removeEventListener('keydown', closeDetailOnEscape);
  stopClock();
  if (isAdminUser) isFullscreen.value = previousIsFullscreen;
  timeline?.destroy();
});
</script>

<style scoped>
.gantt-page { padding: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }

/* Nút thoát toàn màn hình — position: fixed để bám góc màn hình chứ không trôi theo trang.
   z-index phải cao hơn mọi lớp của vis-timeline (cao nhất trong file này là 6 ở
   .gantt-machine-head) và hơn cả tooltip, nếu không sẽ bị thanh Gantt đè mất. */
/* Đồng hồ toàn màn hình. Cùng z-index với nút thoát để luôn nổi trên vis-timeline.
   font-variant-numeric: tabular-nums giữ bề rộng chữ số cố định — thiếu nó thì đồng hồ
   giật ngang mỗi giây khi chữ số đổi (1 hẹp hơn 8 ở font Inter). */
.fs-clock {
  position: fixed;
  left: 18px;
  top: 14px;
  z-index: 3000;
  padding: 8px 16px;
  border-radius: 12px;
  background: var(--bg-card);
  border: 1px solid var(--border-card);
  box-shadow: var(--shadow-lg);
  opacity: 0.9;
  text-align: center;
  pointer-events: none;
}
.fs-clock-time {
  font-family: 'Outfit', sans-serif;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
  letter-spacing: 0.02em;
  font-variant-numeric: tabular-nums;
  color: var(--text-title);
}
.fs-clock-date {
  margin-top: 2px;
  font-size: 0.78rem;
  font-weight: 500;
  color: var(--text-muted);
  text-transform: capitalize;
}

.fs-exit {
  position: fixed;
  right: 18px;
  bottom: 18px;
  z-index: 3000;
  display: flex;
  align-items: center;
  gap: 10px;
}
.fs-exit-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 999px;
  border: 1px solid var(--border-card);
  background: var(--bg-card);
  color: var(--text-title);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: var(--shadow-lg);
  /* Mờ sẵn để không che dữ liệu khi treo màn hình theo dõi cả ngày, rõ hẳn khi rê chuột. */
  opacity: 0.5;
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fs-exit-btn:hover { opacity: 1; transform: translateY(-1px); }
.fs-exit-hint {
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--bg-popover);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  font-size: 0.8rem;
  box-shadow: var(--shadow-md);
  white-space: nowrap;
}
.fs-exit-hint kbd {
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid var(--border-card-hover);
  background: var(--bg-main);
  font-family: inherit;
  font-weight: 700;
}

.stale-banner {
  background: rgba(202, 138, 4, 0.12);
  border: 1px solid rgba(202, 138, 4, 0.3);
  color: #92400e;
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.82rem;
}
.stale-banner.error-banner { background: rgba(220, 38, 38, 0.1); border-color: rgba(220, 38, 38, 0.3); color: #991b1b; }

.toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 0.75rem;
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 0.75rem 1rem;
}
.toolbar-group { display: flex; align-items: flex-end; gap: 0.6rem; flex-wrap: wrap; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field-label { font-size: 0.72rem; color: var(--text-muted, #6b7280); font-weight: 600; }
.realtime-toggle {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  cursor: pointer;
  font-size: 0.8rem;
  color: var(--text-body, #374151);
  height: 34px;
}

.gantt-container {
  background: var(--bg-card, #fff);
  border: 1px solid var(--border-color, #e2e8f0);
  border-radius: 10px;
  padding: 0.75rem;
  position: relative;
  height: calc(100vh - 180px);
  min-height: 560px;
}

/* Toàn màn hình: trang tự trải kín viewport thay vì giữ chiều cao tính theo công thức cố
   định (calc(100vh - 180px) trừ hao cho thanh công cụ — mà thanh công cụ lúc này đã ẩn, để
   nguyên sẽ chừa một dải trống vô nghĩa ở đáy).

   Dùng position: fixed + inset: 0 chứ không phải height: 100vh, vì trang có 2 kiểu bọc khác
   nhau (Admin nằm trong AppLayout .content-container có padding 24px và overflow riêng;
   người xem công khai thì không) — height: 100vh trong khung có padding sẽ tràn ra ngoài
   gây thanh cuộn. inset: 0 bám thẳng viewport nên đúng cho cả hai.

   z-index 2500: cao hơn nút "✕ Thoát toàn màn hình" của AppLayout (2000) để nó không hiện
   chồng lên — nếu không sẽ có 2 nút thoát gần trùng tên ở 2 góc, gây rối. Nút thoát của
   trang này (.fs-exit, z-index 3000) là con của .gantt-page nên vẫn nổi lên trên. */
.gantt-page.is-immersive {
  position: fixed;
  inset: 0;
  z-index: 2500;
  padding: 0.5rem;
  gap: 0.35rem;
  background: var(--bg-main);
}
.gantt-page.is-immersive .gantt-container {
  flex: 1 1 auto;
  height: auto;
  /* Bỏ min-height 560px: trên màn hình thấp nó sẽ đẩy nội dung vượt khỏi viewport.
     min-height: 0 là bắt buộc để flex item co được nhỏ hơn nội dung bên trong. */
  min-height: 0;
}
.gantt-canvas { width: 100%; height: 100%; }
.gantt-empty {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted, #6b7280);
  font-size: 0.9rem;
  pointer-events: none;
}
.footnote { font-size: 0.75rem; color: var(--text-muted, #6b7280); margin: 0; }

.gantt-needle {
  position: absolute;
  top: 8px;
  bottom: 8px;
  width: 2px;
  background-color: #f43f5e;
  z-index: 50;
  pointer-events: none;
  box-shadow: 0 0 6px #f43f5e;
}
.gantt-needle::before {
  content: attr(data-time);
  position: absolute;
  top: -18px;
  left: 50%;
  transform: translateX(-50%);
  background: #f43f5e;
  color: white;
  font-size: 10px;
  padding: 1px 5px;
  border-radius: 3px;
  font-weight: bold;
  white-space: nowrap;
}

/* Thư viện vis-timeline không tự đặt màu chữ/nền theo theme sáng/tối của app — nếu
   không ép rõ ràng, tên Máy VD/Tank kế thừa màu chữ từ theme tối (gần trắng) trong khi
   panel nhãn của thư viện vẫn nền trắng mặc định -> chữ trắng trên nền trắng, không đọc
   được (đúng lỗi báo cáo 2026-07-29 "không nhìn thấy tên của các tank"). */
/* Ép độ rộng cột nhãn qua .vis-panel.vis-left (khung cuộn dọc thật) thay vì trực tiếp lên
   .vis-labelset/.vis-label — chỉ để tránh xung đột với width tường minh mà vis-timeline tự
   gán cho .vis-label (xem rule .vis-label .vis-inner phía dưới), không liên quan gì
   tới lỗi "tên máy biến mất khi cuộn" — lỗi đó do thiếu z-index, xem rule bắt buộc
   ":deep(.vis-label.gantt-machine-head) { z-index: 6; }" bên dưới. */
:deep(.vis-panel.vis-left) { width: v-bind(labelColumnWidthCss) !important; }
:deep(.vis-panel) { background-color: var(--bg-card) !important; }
/* Cột tên Máy VD/Tank có nền riêng (--gantt-label-bg), không cùng màu với vùng biểu đồ — cùng
   màu thì mất ranh giới, mắt phải tự dò theo hàng. Dùng biến riêng chứ KHÔNG dùng --bg-sidebar:
   ở chế độ sáng --bg-sidebar là trắng, trùng đúng nền chart (xem chú thích tại style.css). */
:deep(.vis-panel.vis-left) {
  background-color: var(--gantt-label-bg) !important;
  border-right: 1px solid var(--border-card-hover) !important;
}
:deep(.vis-label),
:deep(.vis-label .vis-inner) {
  background-color: var(--gantt-label-bg) !important;
  color: var(--text-title) !important;
}
/* z-index (KHÔNG kèm position:sticky) trên hàng mang ô tên máy — bắt buộc phải có, không
   phải để ghim/hiệu ứng thị giác. Đã kiểm chứng bằng thực nghiệm (Chromium headless, cuộn
   chuột thật, lặp lại nhiều lần): thiếu z-index này, vis-timeline vẽ SAI hoàn toàn các hàng
   tên máy (không riêng máy nào) CHƯA từng lọt vào khung nhìn ban đầu — tên máy biến mất khỏi
   pixel vẽ ra dù DOM/computed style vẫn báo bình thường (đúng lỗi báo cáo 2026-07-29 "từ
   VD006 => VD013 không thấy tên máy", tái diễn ở BẤT KỲ máy nào ngoài khung nhìn lúc mở
   trang, không cố định ở VD006-013 — vị trí cụ thể phụ thuộc kích thước màn hình/vị trí
   cuộn). Chỉ z-index đơn thuần (Chromium tự thăng cấp compositing layer) là đủ để buộc
   trình duyệt vẽ lại đúng — KHÔNG cần position:sticky cho việc này (đã thử width nhỏ hơn,
   translateZ, will-change trên container: đều không ăn thua; chỉ z-index trên chính hàng
   mới hết). Xem thêm ghi chú tại LABEL_COLUMN_WIDTH phía trên <script>.
   Trước 2026-08-05 rule này bám vào .vis-nesting-group (hàng riêng của Máy VD); nay hàng đó
   đã bị bỏ nên chuyển sang class .gantt-machine-head do applyMachineFilter gán cho hàng Tank
   đầu tiên của mỗi máy — đúng hàng đang mang ô tên máy, tức đúng chỗ bug xảy ra. */
:deep(.vis-label.gantt-machine-head) { z-index: 6; }
/* GHI CHÚ LỊCH SỬ: trước 2026-08-02 hàng tên máy được ghim sticky (class .gantt-machine-sticky,
   chỉ cho máy > 2 Tank — máy 1-2 Tank mà ghim thì Tank đầu bị header dính đè khuất, lỗi báo cáo
   "A1 tank đang bị khuất" 2026-07-29). Đã BỎ hẳn khi chuyển sang ô gộp cột 1: box gộp là con
   absolute của chính hàng đó, hàng sticky thì box trượt theo và che hết tên Tank bên dưới.
   Nếu sau này muốn ghim lại, phải tách box gộp ra một lớp overlay riêng trong .vis-labelset.
   Rule z-index phía trên KHÔNG liên quan tới ghim và phải giữ nguyên — đó là fix bug vẽ sai,
   không phải hiệu ứng UI. */

/* Ô GỘP cột 1: một khối duy nhất cho cả máy, cao bằng hàng máy + toàn bộ hàng Tank của nó
   (chiều cao do capNhatOGopMay() đo và gán inline). Nhờ hàng máy đã có z-index:6, khối này
   nằm trên các hàng Tank phía dưới nên che luôn đường kẻ ngang giữa chúng -> nhìn đúng như
   ô đã merge trong bảng.
   - left: 4px — trước 2026-08-05 phải để 15px chừa chỗ cho mũi tên đóng/mở ▼ mà vis vẽ ở đầu
     hàng Máy VD; bỏ hàng đó thì cũng hết mũi tên, thẻ máy lấy lại được 11px bề ngang.
   - Chừa 5px bên phải để không đè lên vạch ngăn 2 cột. */
:deep(.gantt-machine-merged) {
  position: absolute;
  top: 2px;
  left: 4px;
  width: calc(v-bind(machineColWidthCss) - 9px);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
  padding: 0 6px;
  /* Thẻ máy nổi lên trên nền cột nhãn ở cả 2 theme: sáng = trắng trên xám nhạt, tối = 20%
     trên 13%. Không cần đổ bóng. */
  background: var(--gantt-card-bg);
  border: 1px solid var(--border-card-hover);
  border-radius: 6px;
  z-index: 2;
}

/* Vạch ngăn cột "Máy" và cột "Tank". Vẽ trên từng hàng (.vis-label đã sẵn position:relative
   theo CSS gốc vis-timeline) nên nối lại thành một đường dọc liền mạch, và luôn nằm TRÊN nền
   của hàng — khác với cách vẽ gradient nền lên .vis-labelset (bị chính background của từng
   .vis-label che mất). */
/* Dùng ::after, KHÔNG dùng ::before: vis-timeline dùng `.vis-label.vis-nesting-group:before`
   để vẽ mũi tên ▼/▶ đóng-mở nhóm (xem vis-timeline-graph2d.css). Từ 2026-08-05 biểu đồ không
   còn nhóm lồng nên cũng hết mũi tên, nhưng vẫn giữ ::after — không có lý do gì để đổi, và
   nếu sau này khôi phục nhóm lồng thì khỏi vấp lại đúng cái bẫy đó. */
:deep(.vis-label)::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: v-bind(machineColWidthCss);
  width: 1px;
  background: var(--border-card-hover);
  pointer-events: none;
  /* Giữ vạch nổi trên nội dung của hàng (viên thuốc tank bắt đầu ngay sát mép vạch). */
  z-index: 1;
}
:deep(.gantt-machine-row) {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  width: 100%;
}
/* Icon ghim tách khỏi luồng, nằm góc trên phải thẻ máy — để tên máy được canh giữa thật sự
   thay vì bị icon đẩy lệch sang trái. */
:deep(.gantt-machine-merged .gantt-pin-btn) {
  position: absolute;
  top: 1px;
  right: 2px;
}
:deep(.gantt-machine-name) {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  /* Trước 2026-08-05 chữ đậm là do rule .vis-nesting-group (hàng riêng của Máy VD) — bỏ hàng
     đó thì phải đặt lại ngay trên chính tên máy. */
  font-weight: 700;
}
:deep(.gantt-pin-btn) {
  flex: 0 0 auto;
  cursor: pointer;
  opacity: 0.35;
  font-size: 0.85rem;
  line-height: 1;
  padding: 2px;
  user-select: none;
}
:deep(.gantt-pin-btn:hover) { opacity: 0.75; }
:deep(.gantt-pin-btn.is-pinned) { opacity: 1; }
/* GHI CHÚ LỊCH SỬ: trước 2026-08-05, .vis-inner của hàng Tank CHÍNH LÀ viên thuốc (pill), được
   định vị tuyệt đối và canh giữa hàng bằng top:50% + translateY(-50%) — vì hồi đó chiều cao
   hàng còn co giãn theo số mẻ đang hiển thị, phải canh động mới không bị trôi. Nay chiều cao
   hàng đã cố định (ROW_HEIGHT) nên không cần trò định vị tuyệt đối đó nữa, và cũng KHÔNG dùng
   được: chiều cao hàng lấy theo clientHeight của .vis-inner, mà .vis-inner thì đang phải mang
   hình viên thuốc.
   Tank nằm hẳn ở CỘT 2: trước đây phải dịch mép trái .vis-inner (không dùng padding-left) vì
   .vis-inner tự tô nền, đệm trái thì nền vẫn phủ qua vạch ngăn 2 cột. Nay nền đã chuyển sang
   thẻ con nên padding-left là đủ và đơn giản hơn. */
/* .vis-inner của hàng Tank nay chỉ là Ô CHỨA trong suốt, cao đúng ROW_HEIGHT (bắt buộc: vis
   lấy clientHeight của chính phần tử này làm chiều cao tối thiểu của hàng — xem ghi chú tại
   ROW_HEIGHT phía trên <script>, và ghi chú tại chỗ bọc .gantt-tank-pill trong
   applyMachineFilter). Hình viên thuốc chuyển hẳn sang thẻ con .gantt-tank-pill.
   Đệm trái đúng bằng bề rộng cột tên máy để viên thuốc nằm gọn trong CỘT 2; nền trong suốt
   nên không còn nguy cơ phủ qua vạch ngăn 2 cột như hồi .vis-inner còn tự tô nền.
   Selector là .vis-label (không còn .vis-nested-group): bỏ group cha thì vis không gán class
   .vis-nested-group cho hàng nào nữa, mà nay MỌI hàng đều là hàng Tank nên rule dùng chung. */
:deep(.vis-label .vis-inner) {
  font-size: 0.82rem;
  position: static;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  width: 100%;
  height: v-bind(rowHeightCss);
  padding: 0 0 0 calc(v-bind(machineColWidthCss) + 7px) !important;
  background: transparent !important;
  border: none;
}
:deep(.gantt-tank-pill) {
  padding: 1px 9px;
  background: var(--gantt-card-bg);
  border: 1px solid var(--border-card-hover);
  border-radius: 999px;
  font-weight: 600;
  line-height: 1.45;
}
:deep(.vis-time-axis .vis-text) { color: var(--text-body) !important; font-size: 0.78rem; }
/* Lưới giờ dùng --gantt-grid (đậm hơn hẳn --border-divider): ở chế độ tối nền card là 16% mà
   divider chỉ 21% — chênh 5% thì gần như không thấy vạch giờ nào. */
:deep(.vis-time-axis .vis-grid.vis-minor) { border-color: var(--gantt-grid) !important; }
:deep(.vis-time-axis .vis-grid.vis-major) { border-color: var(--border-card-hover) !important; }
:deep(.vis-grid.vis-vertical) { border-color: var(--gantt-grid) !important; }
:deep(.vis-panel.vis-center) { border-color: var(--border-card) !important; }
/* Trục thời gian tách nền khỏi vùng biểu đồ để hàng giờ không lẫn vào các thanh mẻ. */
:deep(.vis-panel.vis-top) {
  background-color: var(--gantt-label-bg) !important;
  border-bottom: 1px solid var(--border-card-hover) !important;
}
/* Đường kẻ ngang giữa các hàng Tank. Mặc định vis-timeline để #bfbfbf cứng — chói ở nền tối,
   lại quá nhạt so với lưới giờ ở nền sáng. */
:deep(.vis-foreground .vis-group) { border-bottom: 1px solid var(--gantt-grid) !important; }
/* Thanh mẻ giữ ĐÚNG bề rộng theo thời gian, không ép min-width theo px và không cho nhãn
   tràn ra ngoài nữa (bỏ 2026-08-03). Bản trước ép `min-width: max(70px, max-content)` +
   `overflow: visible` để nhãn luôn đọc được, đánh đổi là mẻ ngắn đứng sát nhau đè lên nhau —
   nay yêu cầu là KHÔNG BAO GIỜ đè, kể cả phải vẽ sai giờ. Bề rộng tối thiểu đã được bảo đảm
   ngay trong dữ liệu (MIN_VISUAL_DURATION_MS + đẩy mẻ sang phải, xem fetchGantt), tức là
   tính theo THỜI GIAN nên co giãn đúng theo zoom; ở khung 24h mặc định, 2.5h đủ chỗ cho
   nhãn. Zoom quá xa thì nhãn bị cắt bớt — bấm vào thanh vẫn xem được đầy đủ ở bảng chi tiết. */
:deep(.vis-item) {
  border-radius: 4px !important;
  font-size: 12px !important;
  font-weight: 600 !important;
  /* Chiều cao CỐ ĐỊNH (border-box, để viền 1px của mẻ thường và viền 2px của mẻ đang chạy cho
     ra cùng một chiều cao ngoài) — vis đo offsetHeight của chính thẻ này rồi mới suy ra chiều
     cao hàng, nên đây là gốc của việc mọi hàng bằng nhau. Xem ITEM_HEIGHT/ROW_HEIGHT. */
  height: v-bind(itemHeightCss) !important;
  box-sizing: border-box !important;
  /* Viền thanh mẻ đảo màu theo theme (xem --gantt-item-border): nền sáng mà viền trắng thì
     các thanh màu nhạt (vàng/be) chảy nhoè vào nền trắng, không thấy mép thanh. */
  border: 1px solid var(--gantt-item-border) !important;
  /* 2px để mẻ hẹp bất thường vẫn còn 1 vệt nhìn thấy/bấm được, thay vì biến mất hẳn. */
  min-width: 2px !important;
  box-shadow: 0 1px 3px rgba(0,0,0,0.18);
}
:deep(.vis-item-overflow) { overflow: hidden !important; height: 100%; }
/* Nhãn lấp đầy chiều cao thanh và tự canh giữa (flex trên .vis-item-content) thay vì dựa vào
   padding — padding cố định sẽ cho ra 2 chiều cao khác nhau giữa mẻ thường (viền 1px) và mẻ
   đang chạy (viền 2px). KHÔNG đụng tới transform/left của .vis-item-content: thư viện dùng
   translateX để giữ nhãn trong tầm nhìn khi thanh trôi một phần ra ngoài khung. */
:deep(.vis-item .vis-item-content) {
  height: 100%;
  padding: 0 !important;
  display: flex;
  align-items: center;
}
:deep(.gantt-item-label) {
  display: block;
  width: 100%;
  border-radius: 4px;
  padding: 0 7px;
  /* color + text-shadow đặt inline theo độ sáng của màu mẻ (labelColorOn) — thanh Gantt nay
     mang màu thật của họ màu, trải từ pastel rất nhạt tới gần đen, nên không thể cố định
     chữ trắng + đổ bóng tối như trước. */
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  position: relative;
  z-index: 1;
}
/* Thanh mẻ giờ là thứ bấm được (mở bảng chi tiết) — con trỏ phải nói lên điều đó, nếu không
   người dùng quen tooltip hover cũ sẽ không biết là phải bấm. */
:deep(.vis-item) { cursor: pointer; }

/* Bảng chi tiết mẻ (thay tooltip hover cũ). z-index cao hơn mọi lớp của vis-timeline và cả
   nút thoát/đồng hồ toàn màn hình (3000) để không bị che ở chế độ treo màn hình. */
.gantt-detail-popup {
  position: fixed;
  z-index: 3100;
  width: 330px;
  max-width: calc(100vw - 16px);
  padding: 12px 14px 12px;
  border-radius: 10px;
  background: var(--bg-card, #fff);
  color: var(--text-title, #111827);
  border: 1px solid var(--border-card, #e2e8f0);
  box-shadow: 0 12px 30px rgba(0,0,0,0.28);
  font-size: 0.8rem;
  line-height: 1.5;
  /* Thông tin MES có thể nhiều dòng — cho cả popup cuộn thay vì tràn khỏi khung nhìn. */
  max-height: calc(100vh - 24px);
  overflow-y: auto;
}
.gantt-detail-close {
  position: absolute;
  top: 6px;
  right: 8px;
  border: none;
  background: transparent;
  color: var(--text-secondary, #6b7280);
  font-size: 0.9rem;
  line-height: 1;
  padding: 4px 6px;
  border-radius: 6px;
  cursor: pointer;
}
.gantt-detail-close:hover { background: var(--border-card-hover, #e5e7eb); }
.gantt-detail-head {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-right: 22px;
  margin-bottom: 8px;
  border-bottom: 1px solid var(--border-card, #e2e8f0);
  padding-bottom: 8px;
}
.gantt-detail-swatch {
  width: 14px;
  height: 14px;
  border-radius: 4px;
  flex: 0 0 auto;
  border: 1px solid rgba(0,0,0,0.2);
}
.gantt-detail-title { font-weight: 700; }
.gantt-detail-list {
  display: grid;
  grid-template-columns: auto 1fr;
  column-gap: 10px;
  row-gap: 4px;
  margin: 0;
}
.gantt-detail-list dt {
  font-weight: 600;
  color: var(--text-secondary, #6b7280);
  white-space: nowrap;
}
.gantt-detail-list dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.gantt-detail-note {
  display: block;
  color: var(--text-secondary, #6b7280);
  font-size: 0.72rem;
}
.gantt-mes-block {
  margin-top: 8px;
  padding-top: 8px;
  border-top: 1px solid var(--border-card, #e2e8f0);
}
.gantt-mes-title {
  font-weight: 700;
  margin-bottom: 6px;
}
.gantt-mes-all {
  margin-top: 6px;
}
.gantt-mes-all summary {
  cursor: pointer;
  color: var(--text-secondary, #6b7280);
  font-size: 0.74rem;
  margin-bottom: 4px;
}
/* Bảng thông tin mẻ MES cố định bên phải màn hình. */
/* Panel bảng liệu riêng — nằm SÁT BÊN TRÁI panel MES (right = đúng bề rộng panel MES),
   nên nhích về phía giữa màn hình như yêu cầu. z-index thấp hơn panel MES 1 bậc. */
.gantt-recipe-panel {
  position: fixed;
  top: 0;
  right: 400px;
  height: 100vh;
  width: 380px;
  max-width: 46vw;
  z-index: 3049;
  display: flex;
  flex-direction: column;
  background: var(--bg-card, #fff);
  color: var(--text-title, #111827);
  border-left: 1px solid var(--border-card, #e2e8f0);
  box-shadow: -12px 0 30px rgba(0,0,0,0.18);
  font-size: 0.82rem;
}
.gantt-mes-panel {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  width: 400px;
  max-width: 92vw;
  z-index: 3050;
  display: flex;
  flex-direction: column;
  background: var(--bg-card, #fff);
  color: var(--text-title, #111827);
  border-left: 1px solid var(--border-card, #e2e8f0);
  box-shadow: -12px 0 30px rgba(0,0,0,0.22);
  font-size: 0.82rem;
}
.gantt-mes-panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--border-card, #e2e8f0);
}
.gantt-mes-panel-title {
  font-weight: 700;
  font-size: 0.95rem;
}
.gantt-mes-panel-batch {
  margin-left: 8px;
  font-weight: 600;
  color: var(--text-secondary, #6b7280);
}
.gantt-mes-panel-body {
  padding: 10px 14px 20px;
  overflow-y: auto;
}
/* Bảng liệu (công thức MES + cân thật BPDB) — đặt trên cùng panel, nổi bật hơn phần trường MES. */
.gantt-recipe {
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 2px solid var(--border-card, #eef2f7);
}
.gantt-recipe-title {
  font-weight: 700;
  font-size: 1.02rem;
  margin-bottom: 6px;
}
.gantt-recipe-sub {
  font-weight: 600;
  color: var(--text-secondary, #6b7280);
  margin: 10px 0 4px;
}
.gantt-recipe-group {
  font-weight: 600;
  margin: 6px 0 2px;
}
.gantt-recipe-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 6px;
  border: 1px solid var(--border-strong, #cbd5e1);
}
.gantt-recipe-table th,
.gantt-recipe-table td {
  text-align: left;
  padding: 4px 8px;
  border: 1px solid var(--border-strong, #cbd5e1);
  font-size: 0.9rem;
}
.gantt-recipe-table th {
  font-weight: 600;
  color: var(--text-secondary, #6b7280);
  background: var(--bg-subtle, #f1f5f9);
  white-space: nowrap;
}
.gantt-recipe-table td.name {
  overflow-wrap: anywhere;
  color: var(--text-secondary, #6b7280);
}
.gantt-recipe-table th.num,
.gantt-recipe-table td.num {
  text-align: right;
  white-space: nowrap;
}
.gantt-mes-table {
  width: 100%;
  border-collapse: collapse;
}
.gantt-mes-table th,
.gantt-mes-table td {
  text-align: left;
  vertical-align: top;
  padding: 6px 8px;
  border-bottom: 1px solid var(--border-card, #eef2f7);
}
.gantt-mes-table th {
  width: 40%;
  font-weight: 600;
  color: var(--text-secondary, #6b7280);
  white-space: normal;
}
.gantt-mes-table td {
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}
/* Danh sách máy có thể dài (1 mã chạy trên chục máy) — cho cuộn trong ô thay vì đẩy popup
   cao quá khung nhìn, vì vị trí popup đã chốt lúc bấm (xem openDetailPopup). */
.gantt-detail-machines {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 6px;
  max-height: 92px;
  overflow-y: auto;
}
.gantt-detail-machine {
  padding: 0 6px;
  border-radius: 999px;
  background: var(--bg-hover, rgba(148, 163, 184, 0.18));
  white-space: nowrap;
}
:deep(.gantt-item-running) {
  border: 2px solid #ef4444 !important;
  animation: ganttPulse 0.9s infinite alternate ease-in-out;
}
@keyframes ganttPulse {
  from { box-shadow: 0 0 2px rgba(239,68,68,0.3); opacity: 0.8; }
  to { box-shadow: 0 0 14px rgba(239,68,68,0.9); opacity: 1; }
}
/* Mẻ đã ghép được giờ kết thúc THẬT từ MES — viền xanh dương nhấp nháy (yêu cầu 2026-08-08). */
:deep(.gantt-item-mes) {
  border: 2px solid #2563eb !important;
  animation: ganttPulseMes 0.9s infinite alternate ease-in-out;
}
@keyframes ganttPulseMes {
  from { box-shadow: 0 0 2px rgba(37,99,235,0.3); opacity: 0.85; }
  to { box-shadow: 0 0 14px rgba(37,99,235,0.9); opacity: 1; }
}
:deep(.gantt-blink) { color: #f43f5e; font-weight: 700; }

</style>
