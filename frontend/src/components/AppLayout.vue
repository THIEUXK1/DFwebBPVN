<template>
  <div class="app-layout" :class="{ 'mobile-open': mobileOpen }">
    <!-- Mobile Sidebar Drawer Overlay -->
    <div class="sidebar-overlay" @click="mobileOpen = false"></div>

    <!-- Left Sidebar — CHỈ tài khoản ADMIN thấy (yêu cầu 2026-08-04). Tài khoản khách/vận hành
         chỉ còn thanh trên cùng: vẫn đổi được trạm, xem được tên trạm và đăng xuất, nhưng không
         có cây menu để đi lạc sang công đoạn khác.
         Cũng ẩn khi đang bật chế độ Toàn màn hình (xem nút ⛶ ở topbar). -->
    <aside class="sidebar" v-if="canSeeMenu && !isFullscreen">
      <div class="sidebar-header">
        <div class="logo-circle">DF</div>
        <span class="logo-text">DF Connector</span>
      </div>

      <!-- Navigation Groups -->
      <div class="nav-groups-container">
        <div v-for="group in menuGroups" :key="group.titleKey" class="nav-group">
          <div class="group-title">{{ $t(group.titleKey) }}</div>

          <router-link
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="nav-link-item"
            :class="{ 'active': $route.path === item.path }"
            @click="mobileOpen = false"
            :title="$t(item.labelKey)"
          >
            <SvgIcon :name="item.icon" size="18" />
            <span class="link-label">{{ $t(item.labelKey) }}</span>
          </router-link>
        </div>
      </div>

      <!-- Khu vực tải công cụ — cố định ở đáy sidebar, cho máy trạm gắn cân/máy in tải
           Local Agent về cài (máy trạm thường không có Internet nên không cài qua mạng
           ngoài được, phải tải trực tiếp từ máy chủ qua LAN). -->
      <div class="sidebar-footer">
        <div class="footer-title">{{ $t('layout.toolsTitle') }}</div>
        <a
          v-for="bo in agentInstallers"
          :key="bo.kind"
          :href="bo.url"
          download
          class="tool-download-link"
          :title="bo.title"
        >
          <SvgIcon name="download" size="16" />
          <span>{{ bo.label }}</span>
        </a>
      </div>
    </aside>

    <!-- Right Side Container -->
    <div class="layout-main">
      <!-- Dải mỏng thay chỗ topbar khi tài khoản trạm cân thu gọn nó (mặc định của cannho /
           canto, yêu cầu 08/08/2026). CỐ Ý không dùng nút nổi (position: fixed) đè lên nội
           dung: góc phải trên của màn cân là cụm CLEAR/SAVE/NEXT, góc trái trên là ô quét
           COLOR — nút nổi ở đâu cũng che mất một thứ đang bấm hằng ngày. -->
      <div class="topbar-collapsed" v-if="!isFullscreen && topbarCollapsed">
        <button class="topbar-toggle-btn" @click="setTopbarPref('show')" :title="$t('layout.topbarShowTitle')">
          {{ $t('layout.topbarShowLabel') }}
        </button>
        <span class="collapsed-ws">{{ currentWorkstation ? currentWorkstation.code : $t('layout.noWorkstationConfigured') }}</span>
      </div>

      <!-- Top Bar — ẩn hoàn toàn khi đang ở chế độ Toàn màn hình -->
      <header class="topbar" v-else-if="!isFullscreen">
        <div class="topbar-left">
          <!-- Mobile Menu Burger -->
          <button v-if="canSeeMenu" @click="mobileOpen = !mobileOpen" class="mobile-burger-btn">
            <SvgIcon name="menu" size="20" />
          </button>

          <!-- Breadcrumbs -->
          <div class="breadcrumb-container">
            <span class="breadcrumb-root">{{ $t('layout.breadcrumbRoot') }}</span>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">{{ currentRouteName }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Workstation Pill: mọi tài khoản đã đăng nhập đều bấm được để đổi trạm. Chỉ phiên
               kiosk (mở bằng link máy, không đăng nhập) mới bị cố định trạm theo link. -->
          <div
            class="ws-pill mr-2"
            :class="{ clickable: !isLockedStation, mismatch: capabilityMismatch }"
            @click="!isLockedStation && openWsModal()"
            :title="wsPillTitle"
          >
            <span class="ws-icon">{{ capabilityMismatch ? '⚠️' : '🖥️' }}</span>
            <span class="ws-text">{{ currentWorkstation ? currentWorkstation.code : $t('layout.noWorkstationConfigured') }}</span>
          </div>

          <!-- Realtime Status Lights -->
          <div class="status-indicators">
            <div class="status-dot-item" title="MES Connection Status">
              <span class="dot-pulse dot-green"></span>
              <span class="status-label">MES</span>
            </div>
            <div class="status-dot-item" title="Scale Connection Status">
              <span class="dot-pulse dot-green"></span>
              <span class="status-label">Scale</span>
            </div>
            <div class="status-dot-item" title="Local Scale Agent Status">
              <span class="dot-pulse dot-green"></span>
              <span class="status-label">Agent</span>
            </div>
          </div>

          <!-- Thu gọn thanh trên — chỉ tài khoản trạm cân, đường quay lại của nút "▾ Thanh trên" -->
          <button
            v-if="isScaleAccount"
            class="notif-btn"
            @click="setTopbarPref('hide')"
            :title="$t('layout.collapseTopbarTitle')"
          >
            ▴
          </button>

          <!-- Toàn màn hình: ẩn sidebar + topbar để giao diện thao tác chiếm trọn màn hình -->
          <button
            class="notif-btn"
            @click="isFullscreen = true"
            :title="$t('layout.fullscreenTitle')"
          >
            ⛶
          </button>

          <!-- Chọn ngôn ngữ hiển thị — VI mặc định (không dịch gì), EN/ZH dịch dần từng màn
               hình qua src/locales/modules/*.ts. Lưu trong localStorage (services/../i18n),
               không phụ thuộc dịch vụ bên thứ ba nào (không dùng Google Translate). -->
          <div class="lang-switch" :title="$t('layout.languageSwitchTitle')">
            <button
              v-for="opt in localeOptions"
              :key="opt.code"
              class="lang-btn"
              :class="{ active: locale === opt.code }"
              @click="changeLocale(opt.code)"
            >
              {{ opt.label }}
            </button>
          </div>

          <!-- Theme Toggle (Sáng/Tối) -->
          <button
            class="notif-btn"
            @click="toggleTheme"
            :title="themeToggleTitle"
          >
            <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="18" />
          </button>

          <!-- Notification Bell -->
          <button class="notif-btn" :title="$t('layout.notificationsTitle')">
            <SvgIcon name="bell" size="18" />
            <span class="badge-count">3</span>
          </button>

          <!-- Divider -->
          <div class="vertical-divider"></div>

          <!-- Profile Menu -->
          <div class="profile-menu">
            <div class="profile-avatar">👤</div>
            <div class="profile-info" v-if="authStore.user">
              <span class="profile-name">{{ authStore.user.display_name }}</span>
              <span class="profile-role">{{ authStore.user.roles[0] || $t('layout.defaultRole') }}</span>
            </div>
            <button @click="handleLogout" class="topbar-logout-btn" :title="$t('layout.logoutTitle')">
              <SvgIcon name="logout" size="16" />
            </button>
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <div class="content-container">
        <!-- Đang xác định trạm theo link (?ws=CODE) — chặn render nội dung cũ trong lúc chờ,
             kể cả khi currentWorkstation đang có giá trị CŨ từ màn hình trước (localStorage) -->
        <div v-if="resolvingFromLink" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>🖥️ {{ $t('layout.wsResolvingTitle', { code: wsCodeParam }) }}</h3>
          </div>
        </div>

        <!-- Link trỏ tới mã trạm không tồn tại / đã tắt -->
        <div v-else-if="!currentWorkstation && wsLinkInvalid" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>⚠️ {{ $t('layout.wsLinkInvalidTitle', { code: wsCodeParam }) }}</h3>
            <p class="text-muted mb-4">{{ $t('layout.wsLinkInvalidDesc') }}</p>
            <div class="form-group mb-4">
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">{{ $t('layout.wsSelectPlaceholder') }}</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>
            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              {{ $t('layout.wsConfirmButton') }}
            </button>
          </div>
        </div>

        <!-- Phiên kiosk mở nhầm link: trạm của link không có quyền cho màn hình đang mở.
             Chặn hẳn vì người đứng máy không tự đổi được trạm. Tài khoản đã đăng nhập KHÔNG
             rơi vào đây nữa — chỉ bị cảnh báo ở ws-pill (xem blockOnMismatch). -->
        <div v-else-if="currentWorkstation && blockOnMismatch" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>{{ $t('layout.wsMismatchTitle', { code: currentWorkstation.code }) }}</h3>
            <p class="text-muted mb-4">
              {{ $t('layout.wsMismatchDescPrefix') }}<strong>{{ currentWorkstation.name }}</strong>{{ $t('layout.wsMismatchDescMiddle') }}
              <strong>{{ currentRouteName }}</strong>{{ $t('layout.wsMismatchDescSuffix') }}
            </p>
            <div class="form-group mb-4">
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">{{ $t('layout.wsSelectPlaceholder') }}</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>
            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              {{ $t('layout.wsConfirmButton') }}
            </button>
          </div>
        </div>

        <!-- Fallback: mở thẳng URL gốc, không qua link riêng máy nào (vd: back-office bấm menu).
             Tài khoản ADMIN được bỏ qua bắt buộc chọn trạm — có đủ quyền truy cập mọi màn
             hình quản trị/báo cáo mà không cần gắn với 1 trạm vật lý cụ thể; vẫn có thể tự
             chọn trạm qua "ws-pill" trên topbar nếu cần thao tác 1 trạm cụ thể. -->
        <div v-else-if="!currentWorkstation && !authStore.isAdmin" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>{{ $t('layout.wsNoStationTitle') }}</h3>
            <p class="text-muted mb-4">{{ $t('layout.wsNoStationDesc') }}</p>

            <div class="form-group mb-4">
              <label class="lbl-large">{{ $t('layout.wsListLabel') }}</label>
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">{{ $t('layout.wsSelectPlaceholder') }}</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>

            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              {{ $t('layout.wsConfirmButton') }}
            </button>
          </div>
        </div>

        <!-- Optional Workstation Change Modal -->
        <div v-if="showWsModal" class="modal-overlay" @click.self="showWsModal = false">
          <div class="ws-modal-card">
            <div class="modal-header">
              <h3>{{ $t('layout.wsChangeModalTitle') }}</h3>
              <button @click="showWsModal = false" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-4">
                <label>{{ $t('layout.wsChangeModalSelectLabel') }}</label>
                <select v-model="selectedWsId" class="form-select">
                  <option :value="null">{{ $t('layout.wsSelectPlaceholder') }}</option>
                  <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                    {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                  </option>
                </select>
              </div>
              <div class="modal-actions">
                <button @click="showWsModal = false" class="btn btn-secondary">{{ $t('common.cancel') }}</button>
                <button @click="confirmWsSelection" class="btn btn-primary" :disabled="selectedWsId === null">
                  {{ $t('layout.wsChangeModalSave') }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <slot v-if="(currentWorkstation || authStore.isAdmin) && !resolvingFromLink && !blockOnMismatch"></slot>
      </div>
    </div>

    <!-- Nút thoát Toàn màn hình — nổi ở góc phải trên, luôn thấy được để quay lại layout bình thường -->
    <button v-if="isFullscreen" @click="isFullscreen = false" class="exit-fullscreen-btn" :title="$t('layout.exitFullscreenTitle')">
      ✕
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { useRouter, useRoute } from 'vue-router';
import SvgIcon from './SvgIcon.vue';
import {
  currentWorkstation,
  workstationsList,
  fetchWorkstations,
  setWorkstation,
  workstationMatchesRoute,
  ROUTE_CAPABILITY_MAP
} from '../services/workstation';
import { theme, toggleTheme } from '../services/theme';
import { isFullscreen, topbarPref, setTopbarPref } from '../services/layout';
import { setLocale, type AppLocale } from '../i18n';

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();
// useScope: 'global' — dùng chung 1 instance/state với i18n đăng ký ở main.ts (src/i18n/index.ts),
// không tạo local scope riêng cho component này.
const { t, locale } = useI18n({ useScope: 'global' });

// Bộ chọn ngôn ngữ ở topbar — VI mặc định (giữ nguyên chữ cũ, không dịch), EN/ZH dịch dần
// theo từng màn hình qua src/locales/modules/*.ts. Thuần nội bộ (vue-i18n), không gọi dịch vụ
// bên thứ ba nào (ADR-003 tinh thần "không rò rỉ dữ liệu vận hành ra ngoài LAN").
const localeOptions: { code: AppLocale; label: string }[] = [
  { code: 'vi', label: 'VI' },
  { code: 'en', label: 'EN' },
  { code: 'zh', label: '中' },
];
const changeLocale = (code: AppLocale) => setLocale(code);

// HAI bộ cài Local Agent ĐỘC LẬP, tách theo loại cân (yêu cầu 2026-08-03) — cân nhỏ và cân
// to là hai cái cân vật lý khác nhau, hai công đoạn khác nhau. Hai bộ khác UpgradeCode, khác
// tên service (DFAgentSmall / DFAgentLarge), khác thư mục cài, nên cài cả hai lên CÙNG một
// máy vẫn chạy song song được, và gỡ bộ này không đụng gì tới bộ kia.
//
// Cả hai chỉ làm đúng một việc: đọc cân điện tử rồi đẩy số lên backend. Phần máy in của Agent
// đã bỏ từ 2026-07-31 — cả Print Station lẫn Weighing Station đều in bằng hộp thoại in của
// trình duyệt (window.print(), xem PrintStation.vue + utils/tsplPrint.ts).
//
// Backend serve tĩnh từ public/downloads/ (xem agent/installer/DFAgentSetup.wxs +
// build.ps1). Dùng đúng host mà trình duyệt đang mở trang (giống main.ts) để máy trạm
// trong LAN tải đúng từ máy chủ. Dùng định dạng MSI (không phải Inno Setup .exe) vì bản
// .exe cũ bị Windows Defender gán nhầm nhãn "Program:Win32/Wacapew.A!ml" và tự xóa ngay
// sau khi tải — MSI + ServiceInstall/ServiceControl native không bị quét. Không hỏi Mã
// trạm/Token/URL Backend — đã đóng cứng sẵn, cài xong service chạy ngay.
//
// Tải file .cmd (route backend /downloads/agent-launcher/{kind}, xem routes/web.php) thay vì
// .msi trực tiếp — MSI không tự hiện hộp thoại UAC khi tài khoản không phải admin double-click
// (chỉ báo lỗi "không đủ quyền" rồi dừng, khác .exe), nên phải qua file .cmd nhỏ tự gọi
// Start-Process -Verb RunAs để bật đúng hộp thoại xin quyền admin.
const agentInstallersRaw = [
  { kind: 'small', labelKey: 'layout.toolSmallLabel', titleKey: 'layout.toolSmallTitle' },
  { kind: 'large', labelKey: 'layout.toolLargeLabel', titleKey: 'layout.toolLargeTitle' },
  // Cài THÊM lên chính máy cân to đã cài bộ trên — hai bộ làm hai việc, dùng chung mã trạm.
  { kind: 'large-inout', labelKey: 'layout.toolLargeInoutLabel', titleKey: 'layout.toolLargeInoutTitle' },
];
// computed (không phải hằng số) để nhãn/tooltip đổi theo ngôn ngữ đang chọn mà không cần
// remount component.
const agentInstallers = computed(() => agentInstallersRaw.map(bo => ({
  kind: bo.kind,
  label: t(bo.labelKey),
  title: t(bo.titleKey),
  url: `http://${window.location.hostname}:8500/downloads/agent-launcher/${bo.kind}`,
})));

// CHỈ phiên kiosk (mở bằng link riêng của máy, KHÔNG đăng nhập) mới bị khóa cứng trạm —
// ở đó không có ai chịu trách nhiệm chọn đúng trạm nên trạm phải do link quyết định.
//
// Đã bỏ khóa theo tài khoản (2026-08-04, yêu cầu người dùng): trước đây tài khoản gắn cứng
// trạm (canto/cannho, users.operation_client_id) bị ẩn sidebar và không bấm được nút đổi
// trạm. Nay MỌI tài khoản đã đăng nhập đều đổi trạm được như tài khoản back-office —
// trạm gán sẵn chỉ còn là giá trị mặc định lúc đăng nhập, không phải ràng buộc.
const isLockedStation = computed(() => {
  if (authStore.isAdmin) return false;
  return authStore.isKiosk;
});

// Cây menu bên trái là của riêng ADMIN (yêu cầu 2026-08-04). Mọi tài khoản khác — kể cả tài
// khoản vận hành đã đăng nhập — chỉ thấy thanh trên cùng. Cố ý TÁCH khỏi `isLockedStation`:
// hai thứ này từng dùng chung một cờ nên gỡ khóa trạm là menu tự hiện ra theo, sai ý đồ.
const canSeeMenu = computed(() => authStore.isAdmin);

/**
 * Tài khoản đứng trạm cân (cannho -> WS-SMALL-*, canto -> WS-LARGE-01). Nhận diện theo TRẠM
 * GẮN VỚI TÀI KHOẢN (`users.operation_client_id`, xem ScaleOperatorUsersSeeder), KHÔNG theo
 * route đang mở: yêu cầu là "ở tài khoản cân nhỏ/cân to", nên thợ có đi sang màn khác thì
 * thanh trên vẫn giữ nguyên nếp thu gọn, không nhảy ra nhảy vào theo từng trang.
 *
 * Trạm hiện tại (`currentWorkstation`) cố ý không tính ở đây — nó đổi được bằng tay và bằng
 * suy đoán theo IP, lấy nó làm căn cứ thì tài khoản back-office ghé trạm cân cũng bị thu gọn.
 */
const isScaleAccount = computed(() => {
  if (authStore.isAdmin) return false;
  const ws = authStore.user?.workstation;
  if (!ws) return false;
  const route = ws.default_route || ws.default_screen || '';
  if (route === '/weighing-station-v2' || route === '/weighing-station-large') return true;
  const caps = ws.capability_codes || [];
  return caps.includes('SMALL_SCALE') || caps.includes('LARGE_SCALE');
});

/**
 * Tài khoản trạm cân LUÔN vào ở trạng thái thu gọn. Bấm "▾ Thanh trên" chỉ mở tạm trong phiên
 * xem hiện tại — F5 là về nếp thu gọn (xem topbarPref, không còn ghi nhớ).
 * Tài khoản khác không bao giờ bị thu gọn.
 */
const topbarCollapsed = computed(() => isScaleAccount.value && topbarPref.value !== 'show');

const mobileOpen = ref(false);

// Chế độ Toàn màn hình — ẩn sidebar + topbar để giao diện thao tác (vd trạm cân, in tem)
// chiếm trọn màn hình. State dùng chung (services/layout.ts) để các trang con (vd lưới
// máy ở ChemicalCall.vue) tự co giãn thêm cột khi có nhiều chỗ trống hơn.

const selectedWsId = ref<number | null>(null);
const showWsModal = ref(false);

// Link riêng cho từng máy: "?ws=WS-CHEMICAL-01" tự chọn đúng trạm đó, không cần mở
// dropdown thủ công. AppLayout KHÔNG bị remount khi điều hướng giữa các màn hình
// (router-view slot bên trong 1 layout sống suốt phiên) — vì vậy wsCodeParam và các cờ
// liên quan PHẢI là reactive theo route hiện tại, không được đọc 1 lần lúc mount.
// Trước bản vá này, chuyển màn hình qua sidebar (không có ?ws=) giữ nguyên
// currentWorkstation của màn hình TRƯỚC đó trong localStorage — gây hiện tượng mở
// /print-station nhưng banner vẫn hiện WS-ORDER-01 (báo lỗi 2026-07-18).
const wsCodeParam = computed(() => route.query.ws as string | undefined);
const resolvingFromLink = ref(!!wsCodeParam.value);
const wsLinkInvalid = ref(false);

// Trạm đang chọn không có capability cho route hiện tại — vd currentWorkstation=WS-ORDER-01
// (PRODUCTION_ORDER) nhưng route đang mở là /print-station (cần QR_LABEL_PRINTING).
const capabilityMismatch = computed(() => {
  // Admin không bị chặn bởi capability của trạm đang chọn — có đủ quyền xem mọi màn
  // hình bất kể trạm hiện tại (nếu có) thuộc loại gì (yêu cầu 2026-07-24).
  if (authStore.isAdmin) return false;
  if (!currentWorkstation.value) return false;
  if (!ROUTE_CAPABILITY_MAP[route.path]) return false;
  return !workstationMatchesRoute(currentWorkstation.value, route.path);
});

const wsPillTitle = computed(() => {
  if (isLockedStation.value) return t('layout.wsPillKioskTitle');
  if (capabilityMismatch.value) return t('layout.wsPillMismatchTitle', { code: currentWorkstation.value?.code ?? '' });
  return t('layout.wsPillChangeTitle');
});

const themeToggleTitle = computed(() =>
  theme.value === 'dark' ? t('layout.themeToLightTitle') : t('layout.themeToDarkTitle')
);

// Chặn CỨNG (không render nội dung) chỉ còn áp dụng cho phiên kiosk — ở đó trạm do link của
// máy quyết định, lệch capability nghĩa là link sai, người đứng máy không tự sửa được.
//
// Tài khoản đã đăng nhập thì KHÔNG chặn nữa (yêu cầu 2026-08-04: "1 tài khoản chọn trạm nào
// cũng được"). Người chọn trạm là người chịu trách nhiệm; đổi lại vẫn cảnh báo bằng ws-pill
// đỏ trên topbar để không âm thầm ghi dữ liệu dưới tên một trạm sai loại.
const blockOnMismatch = computed(() => authStore.isKiosk && capabilityMismatch.value);

async function resolveWorkstationForRoute() {
  const code = wsCodeParam.value;
  if (code) {
    resolvingFromLink.value = true;
    if (workstationsList.value.length === 0) {
      await fetchWorkstations();
    }
    const match = workstationsList.value.find(w => w.code === code);
    if (match) {
      setWorkstation(match);
      wsLinkInvalid.value = false;
    } else {
      wsLinkInvalid.value = true;
    }
    resolvingFromLink.value = false;
  } else {
    wsLinkInvalid.value = false;
  }

  if (currentWorkstation.value) {
    selectedWsId.value = currentWorkstation.value.id;
  }
}

onMounted(async () => {
  await fetchWorkstations();
  await resolveWorkstationForRoute();
});

// Điều hướng giữa các màn hình (sidebar, router-link) không remount AppLayout — phải
// tự tái xác thực ws mỗi lần route đổi, kể cả khi không có ?ws= trên URL mới.
watch(() => route.fullPath, () => {
  resolveWorkstationForRoute();
});

const openWsModal = () => {
  selectedWsId.value = currentWorkstation.value ? currentWorkstation.value.id : null;
  showWsModal.value = true;
};

const confirmWsSelection = () => {
  const ws = workstationsList.value.find(w => w.id === selectedWsId.value);
  if (ws) {
    // `manual: true` = ghim lại, sống qua F5 và không bị trạm của tài khoản / whoami-theo-IP
    // đè lên ở lần nạp trang sau (yêu cầu 2026-08-04).
    setWorkstation(ws, { manual: true });
    showWsModal.value = false;
  }
};

const menuGroupsRaw = [
  {
    titleKey: 'layout.groupOperations',
    items: [
      { path: '/', labelKey: 'layout.menuDashboard', icon: 'dashboard' },
      // 3 màn hình dựng lại đúng workbook VBA đang chạy ngoài xưởng — dùng hằng ngày nhiều nhất
      // nên để ngay đầu nhóm Vận hành (yêu cầu 2026-08-04), trước đây nằm trong nhóm Công nghệ.
      // MainForm (bản dựng lại Workbook C3) là màn hình nhập đơn CHÍNH.
      { path: '/production-batches/grid', labelKey: 'layout.menuMainForm', icon: 'batch' },
      // Dựng lại UserForm TO_SEND của workbook DF002 (PRINTER LANDSCAPE / jit qr sending).
      { path: '/print-order-entry', labelKey: 'layout.menuPrintOrderEntry', icon: 'recipe' },
      // Dựng lại UserForm `scaleform` của workbook "QR PRINTER-send to access- NEW 9ROWS BIG QR".
      { path: '/qr-printer', labelKey: 'layout.menuQrPrinter', icon: 'recipe' },
      // Bản web của sheet "sent" (Mod_load_sentlog_sheet) — đơn đã tích & đã bấm OK.
      { path: '/print-sent-log', labelKey: 'layout.menuPrintSentLog', icon: 'recipe' },
      // Dựng lại UserForm "mainform" của workbook MACHINE_ID_LOCKED — bảng treo xưởng,
      // chỉ đọc: 18 máy VD × (4 thùng đã gửi trong 24h + 6 đơn đang chờ).
      { path: '/machine-id-board', labelKey: 'layout.menuMachineIdBoard', icon: 'dashboard' },
      // Cặp "Cân nhỏ" (<6kg) / "Cân to" (>=6kg) — đúng 2 workbook VBA vật lý tách riêng
      // (4.semiauto-small scale.xlsm vs 5.Semiauto-lockmove SEND OVER6.xlsm), tức 2 công đoạn
      // và 2 máy trạm khác nhau ngoài xưởng.
      //
      // Đã bỏ adminOnly (2026-08-04): default_route của WS-SMALL-* / WS-LARGE-01 nay trỏ đúng
      // 2 route này, nên tài khoản trạm cân (cannho/canto) vào thẳng màn hình của mình sau khi
      // đăng nhập. Cờ adminOnly ở đây giờ cũng gần như vô nghĩa với các mục còn lại: chỉ ADMIN
      // mới thấy sidebar (canSeeMenu), tài khoản khác không có menu để mà lọc.
      { path: '/weighing-station-v2', labelKey: 'layout.menuWeighingSmall', icon: 'scale' },
      { path: '/weighing-station-large', labelKey: 'layout.menuWeighingLarge', icon: 'scale' },
      { path: '/weighing-history', labelKey: 'layout.menuWeighingHistory', icon: 'scale', adminOnly: true },
      { path: '/chemical-call', labelKey: 'layout.menuChemicalCall', icon: 'recipe' },
      { path: '/chemical-call/monitor', labelKey: 'layout.menuChemicalMonitor', icon: 'dashboard' },
      { path: '/chemical-call/pending', labelKey: 'layout.menuChemicalPending', icon: 'bell' },
      { path: '/chemical-call/classic', labelKey: 'layout.menuChemicalClassic', icon: 'recipe' },
      { path: '/chemical-call/pending-classic', labelKey: 'layout.menuChemicalPendingClassic', icon: 'bell' }
    ]
  },
  {
    titleKey: 'layout.groupTechnology',
    items: [
      // Các màn hình bản web (không port từ workbook nào) — chuyển hẳn khỏi nhóm Vận hành sang
      // đây theo yêu cầu 2026-08-04, xếp cạnh "Quét đơn (bản web)" cho cùng một mạch bản web.
      { path: '/order-scan', labelKey: 'layout.menuOrderScan', icon: 'search' },
      { path: '/weighing-station', labelKey: 'layout.menuWeighingStation', icon: 'scale' },
      { path: '/print-station', labelKey: 'layout.menuPrintStation', icon: 'recipe' },
      { path: '/material-transports', labelKey: 'layout.menuMaterialTransports', icon: 'transfer' },
      { path: '/feeding-monitor', labelKey: 'layout.menuFeedingMonitor', icon: 'feed' },
      { path: '/production-batches', labelKey: 'layout.menuProductionBatchesScan', icon: 'batch' },
      { path: '/production-batches/list', labelKey: 'layout.menuProductionBatchesList', icon: 'batch' },
      { path: '/machine-queue', labelKey: 'layout.menuMachineQueue', icon: 'queue' },
      { path: '/materials', labelKey: 'layout.menuMaterials', icon: 'material' },
      { path: '/water-configs', labelKey: 'layout.menuWaterConfigs', icon: 'water' },
      { path: '/recipes', labelKey: 'layout.menuRecipes', icon: 'recipe' },
      { path: '/machines-tanks', labelKey: 'layout.menuMachinesTanks', icon: 'batch' }
    ]
  },
  {
    titleKey: 'layout.groupReports',
    items: [
      { path: '/troubleshooting', labelKey: 'layout.menuTroubleshooting', icon: 'tool' },
      { path: '/reports', labelKey: 'layout.menuReports', icon: 'report' },
      { path: '/audit-logs', labelKey: 'layout.menuAuditLogs', icon: 'audit' }
    ]
  },
  {
    titleKey: 'layout.groupAdmin',
    items: [
      { path: '/workstation-admin', labelKey: 'layout.menuWorkstationAdmin', icon: 'settings', adminOnly: true },
      { path: '/print-history-admin', labelKey: 'layout.menuPrintHistoryAdmin', icon: 'recipe', adminOnly: true },
      { path: '/bpdb-admin', labelKey: 'layout.menuBpdbAdmin', icon: 'settings', adminOnly: true },
      { path: '/bpdb-machines', labelKey: 'layout.menuBpdbMachines', icon: 'batch', adminOnly: true },
      { path: '/bpdb-machines/gantt', labelKey: 'layout.menuBpdbGantt', icon: 'queue', adminOnly: true },
      // Bản TEST tạm (yêu cầu 2026-08-11) — thử tính năng đẩy tín hiệu mẻ vừa chạy sang
      // /machine-id-board (nhấp nháy đỏ) trước khi gộp vào route Gantt chính thức ở trên.
      { path: '/bpdb-machines/gantt-test', labelKey: 'layout.menuBpdbGanttTest', icon: 'queue', adminOnly: true }
    ]
  }
];

const menuGroups = computed(() =>
  menuGroupsRaw
    .map(group => ({
      ...group,
      items: group.items.filter(item => !item.adminOnly || authStore.isAdmin)
    }))
    .filter(group => group.items.length > 0)
);

const currentRouteName = computed(() => {
  const nameMap: Record<string, string> = {
    '/': t('layout.routeNames.root'),
    '/weighing-station': t('layout.routeNames.weighingStation'),
    '/weighing-station-v2': t('layout.routeNames.weighingStationV2'),
    '/weighing-station-large': t('layout.routeNames.weighingStationLarge'),
    '/weighing-history': t('layout.routeNames.weighingHistory'),
    '/material-transports': t('layout.routeNames.materialTransports'),
    '/feeding-monitor': t('layout.routeNames.feedingMonitor'),
    '/production-batches': t('layout.routeNames.productionBatches'),
    '/production-batches/list': t('layout.routeNames.productionBatchesList'),
    '/production-batches/grid': t('layout.routeNames.productionBatchesGrid'),
    '/print-order-entry': t('layout.routeNames.printOrderEntry'),
    '/qr-printer': t('layout.routeNames.qrPrinter'),
    '/copower-print': t('layout.routeNames.copowerPrint'),
    '/print-sent-log': t('layout.routeNames.printSentLog'),
    '/machine-id-board': t('layout.routeNames.machineIdBoard'),
    '/machine-queue': t('layout.routeNames.machineQueue'),
    '/materials': t('layout.routeNames.materials'),
    '/water-configs': t('layout.routeNames.waterConfigs'),
    '/recipes': t('layout.routeNames.recipes'),
    '/machines-tanks': t('layout.routeNames.machinesTanks'),
    '/troubleshooting': t('layout.routeNames.troubleshooting'),
    '/reports': t('layout.routeNames.reports'),
    '/audit-logs': t('layout.routeNames.auditLogs'),
    '/workstation-admin': t('layout.routeNames.workstationAdmin'),
    '/print-history-admin': t('layout.routeNames.printHistoryAdmin'),
    '/bpdb-admin': t('layout.routeNames.bpdbAdmin'),
    '/bpdb-machines': t('layout.routeNames.bpdbMachines'),
    '/bpdb-machines/gantt': t('layout.routeNames.bpdbGantt'),
    '/bpdb-machines/gantt-test': t('layout.routeNames.bpdbGanttTest'),
    '/order-scan': t('layout.routeNames.orderScan'),
    '/print-station': t('layout.routeNames.printStation'),
    '/chemical-call': t('layout.routeNames.chemicalCall'),
    '/chemical-call/monitor': t('layout.routeNames.chemicalMonitor'),
    '/chemical-call/pending': t('layout.routeNames.chemicalPending'),
    '/chemical-call/classic': t('layout.routeNames.chemicalClassic'),
    '/chemical-call/pending-classic': t('layout.routeNames.chemicalPendingClassic')
  };
  return nameMap[route.path] || t('layout.routeFallback');
});

const handleLogout = () => {
  authStore.logout();
  router.push('/login');
};
</script>

<style scoped>
.app-layout {
  display: flex;
  height: 100vh;
  overflow: hidden;
  background-color: var(--bg-main);
  color: var(--text-body);
}

/* Sidebar Styling — chiều cao cố định bằng viewport, thanh kéo riêng nằm trong
   .nav-groups-container nên menu KHÔNG bị kéo theo khi cuộn vùng nội dung bên phải. */
.sidebar {
  width: 260px;
  height: 100%;
  background-color: var(--bg-sidebar);
  border-right: 1px solid var(--border-divider);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 100;
}

.sidebar-header {
  height: 70px;
  padding: 0 24px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--border-divider);
}

.logo-circle {
  width: 32px;
  height: 32px;
  border-radius: var(--radius-md);
  background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
  color: var(--text-white);
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: var(--shadow-sm);
  flex-shrink: 0;
}

.logo-text {
  font-family: 'Outfit', sans-serif;
  font-weight: 700;
  font-size: 1.15rem;
  color: var(--text-title);
  white-space: nowrap;
}

.nav-groups-container {
  flex: 1;
  overflow-y: auto;
  padding: 24px 12px;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

/* Khu vực tải công cụ — cố định ở đáy sidebar (flex-shrink: 0), không cuộn theo
   menu điều hướng phía trên. */
.sidebar-footer {
  flex-shrink: 0;
  padding: 12px;
  border-top: 1px solid var(--border-divider);
}

.footer-title {
  font-family: 'Outfit', sans-serif;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-disabled);
  padding: 0 12px 6px 12px;
  letter-spacing: 0.08em;
}

.tool-download-link {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  height: 40px;
  padding: 0 12px;
  color: var(--text-body);
  text-decoration: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  font-size: 0.9rem;
  font-family: inherit;
  background: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.tool-download-link:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}

.group-title {
  font-family: 'Outfit', sans-serif;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-disabled);
  padding: 0 12px 6px 12px;
  letter-spacing: 0.08em;
}

.group-divider {
  height: 1px;
  background-color: var(--border-divider);
  margin: 10px 8px;
}

.nav-link-item {
  display: flex;
  align-items: center;
  gap: 12px;
  height: 40px;
  padding: 0 12px;
  color: var(--text-body);
  text-decoration: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.nav-link-item:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}

.nav-link-item.active {
  background-color: var(--primary-bg);
  color: var(--primary-hover);
  font-weight: 600;
}

/* Layout Main Right side — chiều cao đầy đủ, không tự cuộn (chỉ .content-container
   cuộn bên trong) để topbar luôn cố định và không bị kéo theo view. */
.layout-main {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

/* Topbar Styling */
.topbar {
  height: 70px;
  flex-shrink: 0;
  background-color: var(--bg-topbar);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  z-index: 90;
}

/* Dải mỏng thay chỗ topbar khi thu gọn — 24px so với 70px của topbar đầy đủ. Vẫn là một
   khối trong luồng bố cục (không phải nút nổi) nên nội dung bên dưới không bị che. */
.topbar-collapsed {
  height: 24px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 0 10px;
  background-color: var(--bg-topbar);
  border-bottom: 1px solid var(--border-divider);
  z-index: 90;
}

.topbar-toggle-btn {
  height: 18px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  color: var(--text-body);
  font-size: 11px;
  font-weight: 700;
  line-height: 1;
  cursor: pointer;
}

.topbar-toggle-btn:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}

/* Mã trạm là thứ DUY NHẤT giữ lại trên dải mỏng: cân sai trạm là dữ liệu ghi sai chỗ, đó là
   thông tin không được phép biến mất cùng thanh trên. */
.collapsed-ws {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
  /* Không có min-width:0 thì flex item không co nhỏ hơn nội dung bên trong — tiêu đề
     breadcrumb dài (vd "Danh sách Hóa chất Đang chờ Xử lý") sẽ tự xuống dòng thứ 2 khi
     topbar hết chỗ, tràn ra ngoài khung .topbar cao cố định 70px (trông như "văng xuống"). */
  min-width: 0;
  flex: 1 1 auto;
}

.mobile-burger-btn {
  display: none;
  cursor: pointer;
  color: var(--text-title);
  flex-shrink: 0;
}

.breadcrumb-container {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
  min-width: 0;
  overflow: hidden;
}

.breadcrumb-root {
  color: var(--text-muted);
  flex-shrink: 0;
}

.breadcrumb-separator {
  color: var(--text-disabled);
  flex-shrink: 0;
}

.breadcrumb-current {
  color: var(--text-title);
  font-weight: 600;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 20px;
  /* Giữ nguyên kích thước, không bị bóp — toàn bộ phần co lại nhường cho breadcrumb
     bên trái (đã có ellipsis) khi topbar hết chỗ. */
  flex-shrink: 0;
}

/* Realtime status indicators */
.status-indicators {
  display: flex;
  gap: 16px;
}

.status-dot-item {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--bg-card);
  border: 1px solid var(--border-divider);
  padding: 4px 10px;
  border-radius: var(--radius-full);
}

.dot-pulse {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  position: relative;
}

.dot-green {
  background-color: var(--status-green);
  box-shadow: 0 0 8px var(--status-green);
}

.status-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
}

/* Notif button */
.notif-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
  color: var(--text-body);
  cursor: pointer;
  position: relative;
}

.notif-btn:hover {
  background-color: var(--bg-card-hover);
  color: var(--text-title);
}

/* Bộ chọn ngôn ngữ VI/EN/ZH — 3 nút dạng pill trong 1 khung, giống kiểu .status-dot-item để
   đồng bộ chiều cao với các nút notif-btn (36px) đứng cạnh. */
.lang-switch {
  display: flex;
  align-items: center;
  gap: 2px;
  height: 36px;
  padding: 3px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-divider);
}

.lang-btn {
  height: 100%;
  min-width: 28px;
  padding: 0 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  border-radius: var(--radius-full);
  background: none;
  color: var(--text-muted);
  font-size: 12px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.lang-btn:hover {
  color: var(--text-title);
}

.lang-btn.active {
  background-color: var(--primary-bg);
  color: var(--primary-hover);
}

.badge-count {
  position: absolute;
  top: -4px;
  right: -4px;
  background-color: var(--status-red);
  color: var(--text-white);
  font-size: 10px;
  font-weight: 800;
  height: 16px;
  min-width: 16px;
  padding: 0 4px;
  border-radius: var(--radius-full);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid var(--bg-topbar);
}

.vertical-divider {
  width: 1px;
  height: 24px;
  background-color: var(--border-divider);
}

/* Profile menu section */
.profile-menu {
  display: flex;
  align-items: center;
  gap: 12px;
}

.profile-avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card-hover);
  border: 1px solid var(--border-divider);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}

.profile-info {
  display: flex;
  flex-direction: column;
}

.profile-name {
  font-weight: 600;
  color: var(--text-title);
  font-size: 0.9rem;
  line-height: 1.2;
}

.profile-role {
  font-size: 10px;
  color: var(--status-blue);
  font-weight: 700;
  text-transform: uppercase;
}

.topbar-logout-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-md);
  cursor: pointer;
  color: var(--text-muted);
}

.topbar-logout-btn:hover {
  background-color: var(--status-red-bg);
  color: var(--status-red);
}

/* Content Area Container */
.content-container {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
}

/* Nút thoát Toàn màn hình — nổi cố định ở góc phải trên, luôn nằm trên mọi nội dung.
   Chỉ để dấu ✕ (nhãn chữ đầy đủ nằm ở tooltip): nút này che nội dung ở góc phải trên
   suốt thời gian toàn màn hình nên phải chiếm ít chỗ nhất có thể. */
.exit-fullscreen-btn {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  padding: 0;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  font-size: 0.95rem;
  line-height: 1;
  font-weight: 600;
  box-shadow: var(--shadow-lg);
  cursor: pointer;
  opacity: 0.85;
  transition: opacity 0.2s ease;
}

.exit-fullscreen-btn:hover {
  opacity: 1;
  border-color: var(--status-red);
  color: var(--status-red);
}

/* Tablet & Mobile responsive styles */
@media (max-width: 1024px) {
  .mobile-burger-btn {
    display: block;
  }
  
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    bottom: 0;
    transform: translateX(-100%);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }

  .mobile-open .sidebar {
    transform: translateX(0);
  }

  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    z-index: 99;
  }

  .mobile-open .sidebar-overlay {
    display: block;
  }

  .status-indicators {
    display: none; /* Hide status dot lights on small screens to save space */
  }
}

/* Workstation Pill & Blocker Styling */
.ws-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  background-color: var(--bg-sidebar);
  border: 1px solid var(--border-divider);
  padding: 6px 12px;
  border-radius: var(--radius-full);
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-title);
  transition: all 0.2s;
}
.ws-pill:hover {
  border-color: var(--status-blue);
  background-color: var(--bg-card-hover);
}
/* Trạm đang chọn không đúng loại cho màn hình đang mở. Từ 2026-08-04 việc này KHÔNG còn chặn
   nội dung với tài khoản đã đăng nhập (họ được tự do chọn trạm), nên tín hiệu cảnh báo phải
   nằm ở đây — nếu không thao tác cân/in sẽ ghi dưới tên một trạm sai loại mà không ai biết. */
.ws-pill.mismatch {
  color: var(--status-orange);
  border-color: var(--status-orange-border);
  background-color: var(--status-orange-bg);
}
.ws-blocker-overlay {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  min-height: 400px;
  backdrop-filter: blur(10px);
}
.ws-blocker-card {
  width: 100%;
  max-width: 480px;
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  box-shadow: var(--shadow-lg);
  border-radius: var(--radius-lg);
  padding: var(--space-2xl);
  text-align: center;
}
.ws-blocker-card h3 {
  color: var(--text-title);
  font-size: 1.2rem;
  margin-bottom: 12px;
}
.lbl-large {
  font-size: 0.95rem;
  font-weight: 600;
  display: block;
  text-align: left;
  margin-bottom: 8px;
}
.ws-select-large {
  font-size: 1.1rem;
  padding: 10px 14px;
}
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0,0,0,0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.ws-modal-card {
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 420px;
  box-shadow: var(--shadow-xl);
}
.modal-header {
  padding: var(--space-xl);
  border-bottom: 1px solid var(--border-divider);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.1rem;
}
.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: var(--text-muted);
  cursor: pointer;
}
.modal-body {
  padding: var(--space-xl);
}
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 24px;
}
</style>
