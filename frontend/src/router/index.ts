import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { ROUTE_CAPABILITY_MAP } from '../services/workstation';

// Mọi view đều nạp lười `() => import(...)`. Trước 2026-08-02 có 29 view import tĩnh, dồn
// toàn bộ ứng dụng vào một chunk ~692 kB — mở BẤT KỲ trang nào cũng phải tải xong cả 29 màn
// hình mới hiện được. Nạp lười cắt chunk chung xuống còn phần lõi, mỗi trang chỉ tải đúng
// mã của nó. KHÔNG import tĩnh view mới ở đây nữa.
const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/Login.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/workstation-setup',
    name: 'WorkstationKioskSetup',
    component: () => import('../views/WorkstationKioskSetup.vue'),
    meta: { requiresAuth: false }
  },
  {
    path: '/operate/c/:clientCode/:kioskToken',
    name: 'KioskLanding',
    component: () => import('../views/KioskLanding.vue'),
    meta: { requiresAuth: false }
  },
  // Link tắt cho môi trường test/demo — trỏ thẳng vào đúng link kiosk đầy đủ ở trên
  // (không cần đăng nhập). Chỉ nên dùng nội bộ khi test, KHÔNG phát cho máy trạm thật
  // vì mã trạm/token bị lộ ngay trên URL rút gọn.
  { path: '/order', redirect: '/operate/c/WS-ORDER-01/WS-TOKEN-ORDER-01' },
  { path: '/print', redirect: '/operate/c/WS-PRINT-01/WS-TOKEN-PRINT-01' },
  // Cân nhỏ (<6kg) / cân lớn (>=6kg) — đúng 2 workbook VBA vật lý tách riêng (4.semiauto-small
  // scale.xlsm vs 5.Semiauto-lockmove SEND OVER6.xlsm).
  { path: '/scalesmin', redirect: '/operate/c/WS-SMALL-01/WS-TOKEN-SMALL-01' },
  { path: '/scalesmax', redirect: '/operate/c/WS-LARGE-01/WS-TOKEN-LARGE-01' },
  {
    path: '/operate/menu',
    name: 'KioskMenu',
    component: () => import('../views/KioskMenu.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/chemical-call',
    name: 'ChemicalCall',
    component: () => import('../views/ChemicalCall.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/chemical-call/monitor',
    name: 'ChemicalCallMonitor',
    component: () => import('../views/ChemicalCallMonitor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/chemical-call/pending',
    name: 'ChemicalCallPending',
    component: () => import('../views/ChemicalCallPending.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/troubleshooting',
    name: 'Troubleshooting',
    component: () => import('../views/Troubleshooting.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/production-batches',
    name: 'ProductionBatches',
    component: () => import('../views/ProductionBatches.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/production-batches/list',
    name: 'ProductionBatchesList',
    component: () => import('../views/ProductionBatchesList.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/production-batches/grid',
    name: 'ProductionBatchesGrid',
    component: () => import('../views/ProductionBatchesGrid.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/print-order-entry',
    name: 'PrintOrderEntry',
    component: () => import('../views/PrintOrderEntry.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/order-scan',
    name: 'OrderScan',
    component: () => import('../views/OrderScan.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/weighing-station',
    name: 'WeighingStation',
    component: () => import('../views/WeighingStation.vue'),
    meta: { requiresAuth: true }
  },
  // Bản dựng lại của màn hình cân, chạy song song với /weighing-station (bản cũ giữ nguyên
  // để đối chiếu và để sản xuất vẫn dùng được trong lúc bản mới còn dang dở).
  {
    path: '/weighing-station-v2',
    name: 'WeighingStationV2',
    component: () => import('../views/WeighingStationV2.vue'),
    meta: { requiresAuth: true }
  },
  // TRẠM CÂN TO (>=6kg) — màn hình RIÊNG, port từ workbook VBA "5.Semiauto- lockmove SEND OVER6".
  // Không dùng chung component với /weighing-station-v2 (workbook cân nhỏ): hai trạm là hai công
  // đoạn vật lý khác nhau, và cân to có thêm khối "SEND OVER 6" gửi mã rack sang hệ pha màu.
  {
    path: '/weighing-station-large',
    name: 'WeighingStationLarge',
    component: () => import('../views/WeighingStationLarge.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/weighing-history',
    name: 'WeighingHistory',
    component: () => import('../views/WeighingHistory.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/print-station',
    name: 'PrintStation',
    component: () => import('../views/PrintStation.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/material-transports',
    name: 'MaterialTransfer',
    component: () => import('../views/MaterialTransfer.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/feeding-monitor',
    name: 'FeedingMonitor',
    component: () => import('../views/FeedingMonitor.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/machine-queue',
    name: 'MachineQueue',
    component: () => import('../views/MachineQueue.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/materials',
    name: 'Materials',
    component: () => import('../views/Materials.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/water-configs',
    name: 'WaterConfigs',
    component: () => import('../views/WaterConfigs.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/recipes',
    name: 'Recipes',
    component: () => import('../views/Recipes.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/machines-tanks',
    name: 'MachinesTanks',
    component: () => import('../views/MachinesTanks.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('../views/Reports.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/audit-logs',
    name: 'AuditLogExplorer',
    component: () => import('../views/AuditLogExplorer.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/workstation-admin',
    name: 'WorkstationAdmin',
    component: () => import('../views/WorkstationAdmin.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/print-history-admin',
    name: 'PrintHistoryAdmin',
    component: () => import('../views/PrintHistoryAdmin.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/bpdb-admin',
    name: 'BpdbAdmin',
    component: () => import('../views/BpdbAdmin.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/bpdb-machines',
    name: 'BpdbMachines',
    component: () => import('../views/BpdbMachines.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    // Lazy-load riêng route này — vis-timeline (~600KB) không nên cộng vào bundle
    // chính mà mọi trạm kiosk vận hành đều phải tải (xem CLAUDE.md mục "Wide Layout...
    // tối ưu hiển thị nhà xưởng"). requiresAuth:false theo yêu cầu 2026-07-29 — trang
    // xem công khai qua link, không cần đăng nhập (App.vue vì vậy KHÔNG bọc AppLayout
    // cho route này -> không sidebar/topbar, trang tự lo giao diện của chính nó). API
    // BPDB tương ứng cũng phải là endpoint /api/public/... (xem routes/api.php) vì
    // endpoint admin cũ vẫn yêu cầu đăng nhập ở tầng backend.
    path: '/bpdb-machines/gantt',
    name: 'BpdbMachinesGantt',
    component: () => import('../views/BpdbMachinesGantt.vue'),
    meta: { requiresAuth: false }
  },
  // Fallback redirect
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

router.beforeEach((to, _from, next) => {
  const authStore = useAuthStore();
  authStore.initialize();

  if (to.name === 'KioskLanding') {
    next();
    return;
  }

  // Đã bỏ chặn bắt buộc "/workstation-setup" (nhập registration token do Admin cấp) —
  // theo yêu cầu 2026-07-18: vào thẳng giao diện, không qua bước đăng ký nào. Việc chọn
  // trạm làm việc nay chỉ còn 1 cơ chế: dropdown đơn giản trong AppLayout.vue (chọn từ
  // danh sách qua services/workstation.ts, không cần token) — hiển thị khi
  // currentWorkstation chưa có, không còn ép qua WorkstationKioskSetup.vue.

  const requiresAuth = to.matched.some(record => record.meta.requiresAuth);
  const requiresAdmin = to.matched.some(record => record.meta.requiresAdmin);

  if (requiresAuth && !authStore.isAuthenticated) {
    next('/login');
    return;
  }

  // Link riêng từng máy ("?ws=WS-ORDER-01") là định danh máy theo URL — quyền ưu tiên
  // cao nhất, không bị ghi đè bởi lockedScreen của tài khoản (bug 2026-07-18: mở link
  // /production-batches?ws=WS-ORDER-01 khi đăng nhập bằng tài khoản có workstation binding
  // khác bị điều hướng ngược về lockedScreen, mất luôn query ws). Vẫn giữ nguyên chặn
  // requiresAuth/requiresAdmin ở trên — chỉ bỏ qua khối resolve lockedScreen bên dưới.
  if (to.query.ws && requiresAuth && (!requiresAdmin || authStore.isAdmin)) {
    next();
    return;
  }

  // Resolve locked screen from local workstation config OR user's database binding OR kiosk client
  const lockedScreen = authStore.isKiosk
    ? (authStore.kioskClient?.default_route || '/operate/menu')
    : (authStore.user?.workstation?.default_route || authStore.user?.workstation?.default_screen || null);

  if (to.name === 'Login' && authStore.isAuthenticated) {
    next(lockedScreen || '/');
    return;
  }

  if (requiresAdmin && !authStore.isAdmin) {
    next(lockedScreen || '/');
    return;
  }

  if (authStore.isKiosk) {
    // Restrict kiosk routing to allowed capabilities — dùng chung ROUTE_CAPABILITY_MAP với
    // AppLayout.vue để tránh 2 nơi định nghĩa map lệch nhau (bug CHEMICAL_CALL trỏ nhầm
    // route feeding-monitor, phát hiện 2026-07-18).
    const allowedRoutes = ['/operate/menu', '/login'];
    const client = authStore.kioskClient;
    if (client) {
      const clientCodes = (client.capabilities || []).map((c: any) => c.code);
      Object.entries(ROUTE_CAPABILITY_MAP).forEach(([path, requiredCodes]) => {
        if (requiredCodes.some(code => clientCodes.includes(code))) {
          allowedRoutes.push(path);
        }
      });
    }

    if (requiresAuth && !allowedRoutes.includes(to.path) && to.name !== 'KioskMenu') {
      next(lockedScreen);
      return;
    }
  } else {
    // Admin không bị khóa cứng vào 1 màn hình theo workstation binding — dùng được toàn
    // bộ route mà tài khoản có quyền, không cần "chọn trạm" trước (yêu cầu 2026-07-24).
    // Quy tắc khóa màn hình này chỉ áp dụng cho tài khoản vận hành (OPERATOR) gắn cứng
    // công đoạn theo đúng mô hình "1 máy tính = 1 công đoạn".
    if (requiresAuth && !authStore.isAdmin && lockedScreen && to.path !== lockedScreen) {
      next(lockedScreen);
      return;
    }
  }

  next();
});

export default router;
