<template>
  <div class="app-layout" :class="{ 'mobile-open': mobileOpen }">
    <!-- Mobile Sidebar Drawer Overlay -->
    <div class="sidebar-overlay" @click="mobileOpen = false"></div>

    <!-- Left Sidebar — hidden entirely for station-scoped accounts (1 máy tính = 1 nhiệm vụ)
         hoặc khi đang bật chế độ Toàn màn hình (xem nút ⛶ ở topbar). -->
    <aside class="sidebar" v-if="!isLockedStation && !isFullscreen">
      <div class="sidebar-header">
        <div class="logo-circle">DF</div>
        <span class="logo-text">DF Connector</span>
      </div>

      <!-- Navigation Groups -->
      <div class="nav-groups-container">
        <div v-for="group in menuGroups" :key="group.title" class="nav-group">
          <div class="group-title">{{ group.title }}</div>

          <router-link
            v-for="item in group.items"
            :key="item.path"
            :to="item.path"
            class="nav-link-item"
            :class="{ 'active': $route.path === item.path }"
            @click="mobileOpen = false"
            :title="item.label"
          >
            <SvgIcon :name="item.icon" size="18" />
            <span class="link-label">{{ item.label }}</span>
          </router-link>
        </div>
      </div>

      <!-- Khu vực tải công cụ — cố định ở đáy sidebar, cho máy trạm gắn cân/máy in tải
           Local Agent về cài (máy trạm thường không có Internet nên không cài qua mạng
           ngoài được, phải tải trực tiếp từ máy chủ qua LAN). -->
      <div class="sidebar-footer">
        <div class="footer-title">TẢI CÔNG CỤ</div>
        <a
          :href="agentInstallerUrl"
          download
          class="tool-download-link"
          title="Cài trên máy trạm có gắn cân điện tử / máy in tem"
        >
          <SvgIcon name="download" size="16" />
          <span>DF Agent (Cân &amp; In tem)</span>
        </a>
      </div>
    </aside>

    <!-- Right Side Container -->
    <div class="layout-main">
      <!-- Top Bar — ẩn hoàn toàn khi đang ở chế độ Toàn màn hình -->
      <header class="topbar" v-if="!isFullscreen">
        <div class="topbar-left">
          <!-- Mobile Menu Burger -->
          <button v-if="!isLockedStation" @click="mobileOpen = !mobileOpen" class="mobile-burger-btn">
            <SvgIcon name="menu" size="20" />
          </button>

          <!-- Breadcrumbs -->
          <div class="breadcrumb-container">
            <span class="breadcrumb-root">Hệ thống</span>
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">{{ currentRouteName }}</span>
          </div>
        </div>

        <div class="topbar-right">
          <!-- Workstation Pill: clickable to change only for back-office accounts. Station-scoped
               accounts are locked by Admin (WS-003) — no self-service switch. -->
          <div
            class="ws-pill mr-2"
            :class="{ clickable: !isLockedStation }"
            @click="!isLockedStation && openWsModal()"
            :title="isLockedStation ? 'Trạm được Admin gán cố định cho tài khoản này' : 'Đổi trạm làm việc hiện tại'"
          >
            <span class="ws-icon">🖥️</span>
            <span class="ws-text">{{ currentWorkstation ? currentWorkstation.code : 'Chưa cấu hình Trạm' }}</span>
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

          <!-- Toàn màn hình: ẩn sidebar + topbar để giao diện thao tác chiếm trọn màn hình -->
          <button
            class="notif-btn"
            @click="isFullscreen = true"
            title="Mở toàn màn hình (ẩn menu)"
          >
            ⛶
          </button>

          <!-- Theme Toggle (Sáng/Tối) -->
          <button
            class="notif-btn"
            @click="toggleTheme"
            :title="theme === 'dark' ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
          >
            <SvgIcon :name="theme === 'dark' ? 'sun' : 'moon'" size="18" />
          </button>

          <!-- Notification Bell -->
          <button class="notif-btn" title="Thông báo">
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
              <span class="profile-role">{{ authStore.user.roles[0] || 'VẬN HÀNH' }}</span>
            </div>
            <button @click="handleLogout" class="topbar-logout-btn" title="Đăng xuất">
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
            <h3>🖥️ Đang mở trạm {{ wsCodeParam }}…</h3>
          </div>
        </div>

        <!-- Link trỏ tới mã trạm không tồn tại / đã tắt -->
        <div v-else-if="!currentWorkstation && wsLinkInvalid" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>⚠️ Không tìm thấy trạm "{{ wsCodeParam }}"</h3>
            <p class="text-muted mb-4">Mã trạm trong link không tồn tại hoặc đã bị tắt. Liên hệ Admin để lấy lại đúng link, hoặc chọn trạm bên dưới.</p>
            <div class="form-group mb-4">
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">-- Chọn Trạm làm việc --</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>
            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              Xác nhận trạm làm việc
            </button>
          </div>
        </div>

        <!-- Trạm đang chọn KHÔNG có quyền cho màn hình đang mở (vd còn WS-ORDER-01 từ màn
             hình trước nhưng đang mở /print-station) — báo lỗi rõ ràng, không âm thầm dùng
             sai trạm hoặc tự chuyển trạm khác -->
        <div v-else-if="currentWorkstation && capabilityMismatch" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>⚠️ Trạm "{{ currentWorkstation.code }}" không có quyền cho màn hình này</h3>
            <p class="text-muted mb-4">
              Trạm hiện tại (<strong>{{ currentWorkstation.name }}</strong>) không có capability phù hợp với màn hình
              <strong>{{ currentRouteName }}</strong>. Chọn đúng trạm bên dưới, hoặc quay lại bằng link riêng do Admin cấp.
            </p>
            <div class="form-group mb-4">
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">-- Chọn Trạm làm việc --</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>
            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              Xác nhận trạm làm việc
            </button>
          </div>
        </div>

        <!-- Fallback: mở thẳng URL gốc, không qua link riêng máy nào (vd: back-office bấm menu).
             Tài khoản ADMIN được bỏ qua bắt buộc chọn trạm — có đủ quyền truy cập mọi màn
             hình quản trị/báo cáo mà không cần gắn với 1 trạm vật lý cụ thể; vẫn có thể tự
             chọn trạm qua "ws-pill" trên topbar nếu cần thao tác 1 trạm cụ thể. -->
        <div v-else-if="!currentWorkstation && !authStore.isAdmin" class="ws-blocker-overlay">
          <div class="ws-blocker-card">
            <h3>🖥️ CẤU HÌNH TRẠM LÀM VIỆC (WORKSTATION)</h3>
            <p class="text-muted mb-4">Trang này chưa được mở qua link riêng của một máy. Chọn trạm làm việc hiện tại của thiết bị này, hoặc dùng link riêng do Admin cấp cho từng máy.</p>

            <div class="form-group mb-4">
              <label class="lbl-large">Danh sách Trạm làm việc:</label>
              <select v-model="selectedWsId" class="form-select ws-select-large">
                <option :value="null">-- Chọn Trạm làm việc --</option>
                <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                  {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                </option>
              </select>
            </div>

            <button @click="confirmWsSelection" class="btn btn-primary w-full btn-large" :disabled="selectedWsId === null">
              Xác nhận trạm làm việc
            </button>
          </div>
        </div>

        <!-- Optional Workstation Change Modal -->
        <div v-if="showWsModal" class="modal-overlay" @click.self="showWsModal = false">
          <div class="ws-modal-card">
            <div class="modal-header">
              <h3>⚙️ Thay đổi Trạm làm việc</h3>
              <button @click="showWsModal = false" class="close-btn">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-4">
                <label>Chọn trạm mới:</label>
                <select v-model="selectedWsId" class="form-select">
                  <option :value="null">-- Chọn Trạm làm việc --</option>
                  <option v-for="ws in workstationsList" :key="ws.id" :value="ws.id">
                    {{ ws.code }} - {{ ws.name }} ({{ ws.location }})
                  </option>
                </select>
              </div>
              <div class="modal-actions">
                <button @click="showWsModal = false" class="btn btn-secondary">Hủy</button>
                <button @click="confirmWsSelection" class="btn btn-primary" :disabled="selectedWsId === null">
                  Lưu thay đổi
                </button>
              </div>
            </div>
          </div>
        </div>

        <slot v-if="(currentWorkstation || authStore.isAdmin) && !resolvingFromLink && !capabilityMismatch"></slot>
      </div>
    </div>

    <!-- Nút thoát Toàn màn hình — nổi ở góc phải trên, luôn thấy được để quay lại layout bình thường -->
    <button v-if="isFullscreen" @click="isFullscreen = false" class="exit-fullscreen-btn" title="Thoát toàn màn hình">
      ✕ Thoát toàn màn hình
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
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
import { isFullscreen } from '../services/layout';

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

// File cài đặt Local Agent được backend serve tĩnh từ public/downloads/ (xem
// backend/public/downloads/DFAgentSetup.msi) — dùng đúng host mà trình duyệt đang
// mở trang (giống main.ts) để máy trạm trong LAN tải đúng từ máy chủ.
const agentInstallerUrl = `http://${window.location.hostname}:8500/downloads/DFAgentSetup.msi`;

// Station-scoped account (WS-001) HOẶC phiên kiosk (link riêng máy, không đăng nhập):
// công đoạn được cố định theo tài khoản/link, không cho đổi tay qua dropdown.
const isLockedStation = computed(() => {
  if (authStore.isAdmin) return false;
  if (authStore.isKiosk) return true;
  const wsConfigStr = localStorage.getItem('df_workstation_config');
  let wsConfig = null;
  try {
    wsConfig = wsConfigStr ? JSON.parse(wsConfigStr) : null;
  } catch (e) {}
  return !!authStore.user?.workstation || !!(wsConfig && wsConfig.locked_to_type);
});

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

// Trạm đang chọn (từ URL hoặc còn sót lại từ màn hình trước) không có capability cho
// route hiện tại — vd currentWorkstation=WS-ORDER-01 (PRODUCTION_ORDER) nhưng route
// đang mở là /print-station (cần QR_LABEL_PRINTING). KHÔNG được âm thầm render dữ
// liệu sai trạm trong trường hợp này — phải chặn lại và báo lỗi rõ ràng.
const capabilityMismatch = computed(() => {
  // Admin không bị chặn bởi capability của trạm đang chọn — có đủ quyền xem mọi màn
  // hình bất kể trạm hiện tại (nếu có) thuộc loại gì (yêu cầu 2026-07-24).
  if (authStore.isAdmin) return false;
  if (!currentWorkstation.value) return false;
  if (!ROUTE_CAPABILITY_MAP[route.path]) return false;
  return !workstationMatchesRoute(currentWorkstation.value, route.path);
});

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
    setWorkstation(ws);
    showWsModal.value = false;
  }
};

const menuGroupsRaw = [
  {
    title: 'VẬN HÀNH',
    items: [
      { path: '/', label: 'Giám sát', icon: 'dashboard' },
      { path: '/order-scan', label: 'Quét đơn QR', icon: 'search' },
      { path: '/weighing-station', label: 'Trạm cân', icon: 'scale' },
      { path: '/print-station', label: 'In tem', icon: 'recipe' },
      { path: '/material-transports', label: 'Vận chuyển', icon: 'transfer' },
      { path: '/feeding-monitor', label: 'Cấp máy', icon: 'feed' },
      { path: '/chemical-call', label: 'Gọi hóa chất', icon: 'recipe' },
      { path: '/chemical-call/monitor', label: 'Giám sát Hóa chất', icon: 'dashboard' }
    ]
  },
  {
    title: 'CÔNG NGHỆ',
    items: [
      { path: '/production-batches', label: 'Quét đơn (Lô SX)', icon: 'batch' },
      { path: '/production-batches/list', label: 'Danh sách Lô SX', icon: 'batch' },
      { path: '/machine-queue', label: 'Điều phối máy', icon: 'queue' },
      { path: '/materials', label: 'Vật tư', icon: 'material' },
      { path: '/water-configs', label: 'Cấu hình nước', icon: 'water' },
      { path: '/recipes', label: 'Công thức', icon: 'recipe' }
    ]
  },
  {
    title: 'BÁO CÁO & SỰ CỐ',
    items: [
      { path: '/troubleshooting', label: 'Chẩn đoán sự cố', icon: 'tool' },
      { path: '/reports', label: 'Báo cáo & Phân tích', icon: 'report' },
      { path: '/audit-logs', label: 'Audit Log', icon: 'audit' }
    ]
  },
  {
    title: 'QUẢN TRỊ',
    items: [
      { path: '/workstation-admin', label: 'Workstation & Tài khoản', icon: 'settings', adminOnly: true },
      { path: '/print-history-admin', label: 'Lịch sử in tem', icon: 'recipe', adminOnly: true },
      { path: '/bpdb-admin', label: 'BPDB / JIT (Color Service)', icon: 'settings', adminOnly: true }
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
    '/': 'Giám sát trạm cân',
    '/weighing-station': 'Quản lý Trạm cân',
    '/material-transports': 'Giám sát Vận chuyển',
    '/feeding-monitor': 'Kiểm soát Cấp máy',
    '/production-batches': 'Trạm Quét đơn sản xuất',
    '/production-batches/list': 'Danh sách Lô sản xuất',
    '/machine-queue': 'Hàng chờ Điều phối',
    '/materials': 'Danh mục Vật tư',
    '/water-configs': 'Cấu hình Mực nước',
    '/recipes': 'Công thức sản xuất',
    '/troubleshooting': 'Chẩn đoán Sự cố',
    '/reports': 'Báo cáo & Phân tích',
    '/audit-logs': 'Audit Log Explorer',
    '/workstation-admin': 'Quản lý Workstation & Tài khoản',
    '/print-history-admin': 'Lịch sử in tem — Toàn hệ thống',
    '/bpdb-admin': 'Giám sát tích hợp Color Service (BPDB/JIT)',
    '/order-scan': 'Trạm Quét đơn QR',
    '/print-station': 'Trạm In tem',
    '/chemical-call': 'Trạm Gọi Hóa chất',
    '/chemical-call/monitor': 'Giám sát Hệ thống Gọi Hóa chất'
  };
  return nameMap[route.path] || 'Trang chủ';
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
  height: 40px;
  padding: 0 12px;
  color: var(--text-body);
  text-decoration: none;
  border-radius: var(--radius-md);
  font-weight: 500;
  font-size: 0.9rem;
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

.topbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.mobile-burger-btn {
  display: none;
  cursor: pointer;
  color: var(--text-title);
}

.breadcrumb-container {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.9rem;
}

.breadcrumb-root {
  color: var(--text-muted);
}

.breadcrumb-separator {
  color: var(--text-disabled);
}

.breadcrumb-current {
  color: var(--text-title);
  font-weight: 600;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 20px;
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

/* Nút thoát Toàn màn hình — nổi cố định ở góc phải trên, luôn nằm trên mọi nội dung */
.exit-fullscreen-btn {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 2000;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: var(--radius-full);
  background-color: var(--bg-card);
  border: 1px solid var(--border-card);
  color: var(--text-body);
  font-size: 0.85rem;
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
