<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AgentJobsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BpdbAdminController;
use App\Http\Controllers\BpdbChemicalDemandController;
use App\Http\Controllers\BpdbMachineController;
use App\Http\Controllers\BpdbMachineFeedingController;
use App\Http\Controllers\BpdbMaterialActivityController;
use App\Http\Controllers\CalculationController;
use App\Http\Controllers\ChemicalCallController;
use App\Http\Controllers\ChemicalFormulaGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FeedOperationController;
use App\Http\Controllers\KioskSessionController;
use App\Http\Controllers\MachineDispatchController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTransportController;
use App\Http\Controllers\OperationClientAdminController;
use App\Http\Controllers\PrintJobController;
use App\Http\Controllers\ProductionBatchController;
use App\Http\Controllers\RackDispatchController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScaleMeasurementController;
use App\Http\Controllers\ScannerController;
use App\Http\Controllers\TroubleshootingController;
use App\Http\Controllers\WaterConfigController;
use App\Http\Controllers\WeighingJobController;
use App\Http\Controllers\WorkstationLocalConfigController;
use App\Http\Controllers\WorkstationRegistrationController;
use App\Http\Middleware\KioskAuthenticationMiddleware;
use Illuminate\Support\Facades\Route;

// Ping — hàng đợi cân của /weighing-station-v2 gõ cửa định kỳ để biết "đường đã thông chưa"
// trước khi đẩy cả mẻ lên (xem frontend/src/services/saveQueue.ts).
//
// CỐ Ý không đặt sau middleware auth: phiên hết hạn cũng phải ping được, nếu không mất mạng và
// hết phiên nhìn giống hệt nhau. Và 401 sẽ kích hoạt interceptor ở main.ts -> logout + reload
// trang — không được để một nhịp chạy ngầm làm việc đó.
//
// Không chạm DB: đây là câu hỏi "web còn sống không", không phải "DB còn sống không". Bắt nó
// truy vấn DB thì mỗi trạm cân mất mạng sẽ nện thêm một truy vấn mỗi 15 giây.
Route::get('/ping', fn () => response()->json(['status' => 'OK']));

// Public Auth Route
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/workstations/handshake', [WorkstationRegistrationController::class, 'handshake']);
Route::post('/kiosk/session', [KioskSessionController::class, 'establishSession']);

// Public — biểu đồ Gantt "Máy VD" (yêu cầu 2026-07-29: xem qua link, không cần đăng
// nhập, ví dụ cho màn hình TV treo xưởng). Cố ý tách endpoint riêng ngoài nhóm
// role:ADMIN thay vì gỡ middleware của route /admin/bpdb/machines/gantt — để không
// vô tình mở luôn các endpoint BPDB admin khác nếu sau này có ai copy-paste nhầm route
// này vào trong group admin. Cùng 1 controller action, không lặp logic.
Route::get('/public/bpdb-machines-gantt', [BpdbMachineController::class, 'gantt']);
// Cùng trang Gantt public ở trên: tổng số mẻ 1 mã màu-mã hàng đã chạy từ đầu tới nay,
// gọi riêng khi bấm vào 1 thanh (query quét toàn lịch sử, không nhét vào /gantt).
Route::get('/public/bpdb-machines-gantt/lot-total', [BpdbMachineController::class, 'lotTotal']);

// Public — 2 màn hình "Gọi hóa chất cổ điển" (/chemical-call/classic và
// /chemical-call/pending-classic), theo yêu cầu 2026-08-04: mở màn hình treo xưởng không
// cần đăng nhập, KỂ CẢ thao tác gọi và xác nhận xong.
//
// Hệ quả người dùng đã chấp nhận khi chốt: `auth()->id()` ở các action này trả null nên
// mọi lệnh gọi qua đây KHÔNG có định danh người thực hiện (`requested_by_user_id`,
// `confirmed_by_user_id`, `actor_user_id` để trống — các cột đều nullable sẵn nên không
// cần migration). Bản thân sự kiện vẫn được ghi đầy đủ, chỉ thiếu "ai làm".
//
// Tách route riêng thay vì gỡ middleware của nhóm bảo vệ — giống cách đã làm cho Gantt ở
// trên: chỉ mở ĐÚNG 4 endpoint 2 màn hình đó dùng, không mở lây các endpoint hóa chất
// khác (acknowledge/cancel/events/sửa cấu hình kênh vẫn yêu cầu đăng nhập).
Route::get('/public/chemical-channels', [ChemicalCallController::class, 'getChannels']);
Route::post('/public/chemical-call-requests', [ChemicalCallController::class, 'createRequest']);
Route::patch('/public/chemical-call-requests/{id}/complete', [ChemicalCallController::class, 'complete']);
Route::patch('/public/chemical-call-requests/{id}/reset', [ChemicalCallController::class, 'reset']);

// Public — 3 màn hình dựng lại form VBA chạy ở máy xưởng, theo yêu cầu 2026-08-04:
// /production-batches/grid (MainForm C3), /print-order-entry (TO_SEND DF002) và
// /machine-id-board (bảng thông tin đơn theo máy). Mở đúng các endpoint 3 màn đó gọi.
//
// KHÔNG mở nhóm cân (`weighing-jobs/*`, `scanner/*`): 2 trạm cân vẫn yêu cầu đăng nhập vì
// phải giữ định danh người cân và tài khoản QA/QC duyệt override dung sai (CLAUDE.md mục 5
// "Nhật ký Thay đổi"). Người dùng đã chốt ranh giới này ngày 2026-08-04.
//
// Cùng đánh đổi như nhóm hóa chất ở trên: `auth()->id()` trả null nên các thao tác ghi qua
// đây (tạo/duyệt đơn, xác nhận gửi máy) không có định danh người thực hiện.
Route::get('/public/production-batches', [ProductionBatchController::class, 'index']);
Route::post('/public/production-batches', [ProductionBatchController::class, 'store']);
Route::get('/public/machines', [ProductionBatchController::class, 'machines']);
Route::get('/public/tanks', [ProductionBatchController::class, 'tanks']);
Route::post('/public/production-batches/scan-parse', [ProductionBatchController::class, 'scanParse']);
Route::put('/public/production-batches/{id}/status', [ProductionBatchController::class, 'updateStatus']);
Route::put('/public/production-batches/{id}/tank', [ProductionBatchController::class, 'updateTank']);
Route::post('/public/production-batches/{id}/approve', [ProductionBatchController::class, 'approve']);
Route::get('/public/machine-dispatches', [MachineDispatchController::class, 'index']);
Route::get('/public/machine-dispatches/history', [MachineDispatchController::class, 'history']);
Route::post('/public/machine-dispatches/{id}/confirm', [MachineDispatchController::class, 'confirm']);
Route::patch('/public/machine-dispatches/{id}/scale-checked', [MachineDispatchController::class, 'updateScaleChecked']);

// Protected Auth Routes
Route::middleware(KioskAuthenticationMiddleware::class)->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/kiosk/verify-pin', [KioskSessionController::class, 'verifyManagerPin']);

    // Materials
    Route::get('/materials', [MaterialController::class, 'index']);
    Route::put('/materials/{id}', [MaterialController::class, 'update']);

    // Water Configs
    Route::get('/water-configs', [WaterConfigController::class, 'index']);
    Route::put('/water-configs/{id}', [WaterConfigController::class, 'update']);

    // Recipes
    Route::get('/recipes', [RecipeController::class, 'index']);
    Route::post('/recipes', [RecipeController::class, 'store']);
    Route::get('/recipes/{id}', [RecipeController::class, 'show']);

    // Calculations
    Route::post('/calculations/preview', [CalculationController::class, 'preview']);

    // Production Batches
    Route::get('/production-batches', [ProductionBatchController::class, 'index']);
    Route::post('/production-batches', [ProductionBatchController::class, 'store']);
    Route::get('/machines', [ProductionBatchController::class, 'machines']);
    Route::post('/machines', [ProductionBatchController::class, 'storeMachine']);
    Route::get('/tanks', [ProductionBatchController::class, 'tanks']);
    Route::post('/production-batches/scan-parse', [ProductionBatchController::class, 'scanParse']);
    Route::put('/production-batches/{id}/status', [ProductionBatchController::class, 'updateStatus']);
    Route::put('/production-batches/{id}/tank', [ProductionBatchController::class, 'updateTank']);
    Route::post('/production-batches/{id}/approve', [ProductionBatchController::class, 'approve']);

    // Machine Dispatches
    Route::get('/machine-dispatches', [MachineDispatchController::class, 'index']);
    Route::get('/machine-dispatches/history', [MachineDispatchController::class, 'history']);
    Route::post('/machine-dispatches/{id}/claim', [MachineDispatchController::class, 'claim']);
    Route::post('/machine-dispatches/{id}/release', [MachineDispatchController::class, 'release']);
    Route::post('/machine-dispatches/{id}/send', [MachineDispatchController::class, 'send']);
    Route::post('/machine-dispatches/{id}/confirm', [MachineDispatchController::class, 'confirm']);
    Route::patch('/machine-dispatches/{id}/ever-printed', [MachineDispatchController::class, 'markEverPrinted']);
    Route::patch('/machine-dispatches/{id}/scale-checked', [MachineDispatchController::class, 'updateScaleChecked']);
    Route::post('/machine-dispatches/{id}/reprint', [MachineDispatchController::class, 'reprint']);
    Route::get('/machine-dispatches/{id}/preview', [MachineDispatchController::class, 'previewPayload']);

    // SEND OVER 6 — trạm cân lớn xếp lệnh gửi mã rack sang hệ pha màu (Mod_sendRackauto).
    Route::post('/rack-dispatch', [RackDispatchController::class, 'store']);
    // Màn hình hỏi lại kết quả THẬT (Agent đã thực hiện chưa) — xem RackDispatchController::show.
    Route::get('/rack-dispatch/{id}', [RackDispatchController::class, 'show']);
    Route::post('/print-jobs/{id}/cancel', [PrintJobController::class, 'cancel']);

    // Chemical Calls (Wave 2)
    Route::get('/chemical-channels', [ChemicalCallController::class, 'getChannels']);
    Route::post('/chemical-channels', [ChemicalCallController::class, 'storeChannel']);
    Route::patch('/chemical-channels/{id}', [ChemicalCallController::class, 'updateChannel']);
    Route::post('/chemical-call-requests', [ChemicalCallController::class, 'createRequest']);
    Route::patch('/chemical-call-requests/{id}/acknowledge', [ChemicalCallController::class, 'acknowledge']);
    Route::patch('/chemical-call-requests/{id}/complete', [ChemicalCallController::class, 'complete']);
    Route::patch('/chemical-call-requests/{id}/cancel', [ChemicalCallController::class, 'cancel']);
    Route::patch('/chemical-call-requests/{id}/reset', [ChemicalCallController::class, 'reset']);
    Route::get('/chemical-call-requests/{id}/events', [ChemicalCallController::class, 'getEvents']);
    Route::get('/chemical-call-events', [ChemicalCallController::class, 'getRecentEvents']);

    // Công thức "Báo phát AC" xác nhận từ QR thật (2026-07-28) — thùng tự tra ra công
    // thức đang active qua chemical_code (xem ChemicalCallController::getChannels và
    // ChemicalFormulaGroup::lookupByCombinedCode), không cần cấu hình tay theo máy nữa.
    Route::get('/chemical-formula-groups', [ChemicalFormulaGroupController::class, 'index']);

    // "May toi dang ngoi la tram nao?" — tra ve tram ma Agent tren CHINH may nay da tu dang ky
    // (nhan dien qua IP nguon). Nho vay cai Agent xong la may tu co tram rieng, khong phai khai
    // tay trong Quan ly Workstation. Xem DeviceController::whoami.
    Route::get('/workstations/whoami', [DeviceController::class, 'whoami']);

    // Scale Measurements for Web App
    Route::get('/devices/readings/{workstation_id}', [DeviceController::class, 'getReading']);
    Route::get('/scale-measurements', [ScaleMeasurementController::class, 'index']);
    Route::post('/scale-measurements', [ScaleMeasurementController::class, 'store']);
    Route::get('/scale-measurements/checker', [ScaleMeasurementController::class, 'checker']);

    // Print Jobs for Web App
    Route::post('/print-jobs', [PrintJobController::class, 'store']);

    // Weighing Jobs & Items (Wave 4)
    // Route "active" phải khai báo TRƯỚC "{id}" — Laravel khớp route theo thứ tự khai báo,
    // nếu để sau thì "active" sẽ bị "{id}" nuốt mất (gọi show('active') rồi 404 vì không
    // phải UUID hợp lệ).
    Route::get('/weighing-jobs/active', [WeighingJobController::class, 'activeForWorkstation']);
    // Lịch sử cân — phải đứng TRƯỚC /weighing-jobs/{id}, nếu không "history" bị nuốt thành {id}.
    Route::get('/weighing-jobs/history', [WeighingJobController::class, 'history']);
    Route::get('/weighing-jobs/{id}', [WeighingJobController::class, 'show']);
    Route::post('/weighing-jobs/items/{id}/weigh', [WeighingJobController::class, 'weighItem'])->middleware('workstation.guard:WEIGH_ITEM');
    // Lưu cả mẻ 1 lần (VBA scaleform.btnSave_Click) — dùng ở /weighing-station-v2.
    Route::post('/weighing-jobs/{id}/weigh-batch', [WeighingJobController::class, 'weighBatch'])->middleware('workstation.guard:WEIGH_ITEM');
    Route::post('/weighing-jobs/{id}/restart', [WeighingJobController::class, 'restart'])->middleware('workstation.guard:WEIGH_ITEM');
    Route::post('/weighing-jobs/{id}/cancel', [WeighingJobController::class, 'cancel'])->middleware('workstation.guard:WEIGH_ITEM');
    Route::post('/weighing-jobs/{id}/print', [WeighingJobController::class, 'printLabel'])->middleware('workstation.guard:PRINT_LABEL');
    Route::post('/weighing-jobs/{id}/print-slip', [WeighingJobController::class, 'printSlip'])->middleware('workstation.guard:PRINT_SLIP');
    Route::get('/material-labels/search', [WeighingJobController::class, 'searchLabels']);
    Route::get('/material-labels/{id}', [WeighingJobController::class, 'showLabel']);
    Route::post('/material-labels/{id}/reprint', [WeighingJobController::class, 'reprintLabel'])->middleware('workstation.guard:REPRINT_LABEL');

    // Material Transports
    Route::get('/material-transports', [MaterialTransportController::class, 'index']);
    Route::post('/material-transports', [MaterialTransportController::class, 'store']);
    Route::post('/material-transports/{id}/transit', [MaterialTransportController::class, 'startTransit'])->middleware('workstation.guard:CONFIRM_TRANSIT');
    Route::post('/material-transports/{id}/arrive', [MaterialTransportController::class, 'arrive'])->middleware('workstation.guard:CONFIRM_ARRIVAL');
    Route::post('/material-transports/{id}/accept', [MaterialTransportController::class, 'acceptReject']);

    // Feed Operations
    Route::get('/feed-operations', [FeedOperationController::class, 'index']);
    Route::get('/feed-operations/readiness/{batch_id}', [FeedOperationController::class, 'checkReadiness']);
    Route::post('/feed-operations', [FeedOperationController::class, 'startFeed'])->middleware('workstation.guard:CONFIRM_FEED');
    Route::post('/feed-operations/{id}/verify-water', [FeedOperationController::class, 'verifyWater']);
    Route::post('/feed-operations/{id}/verify-materials', [FeedOperationController::class, 'verifyMaterials']);
    Route::post('/feed-operations/{id}/override', [FeedOperationController::class, 'override']);
    Route::post('/feed-operations/{id}/complete', [FeedOperationController::class, 'completeFeed'])->middleware('workstation.guard:CONFIRM_FEED');

    // Troubleshooting
    Route::get('/troubleshooting/problems', [TroubleshootingController::class, 'indexProblems']);
    Route::get('/troubleshooting/processes', [TroubleshootingController::class, 'indexProcesses']);
    Route::get('/troubleshooting/parameters', [TroubleshootingController::class, 'indexParameters']);
    Route::get('/troubleshooting/cases', [TroubleshootingController::class, 'indexCases']);
    Route::get('/troubleshooting/cases/{id}', [TroubleshootingController::class, 'showCase']);
    Route::post('/troubleshooting/diagnose', [TroubleshootingController::class, 'diagnose']);
    Route::post('/troubleshooting/cases/{id}/resolve', [TroubleshootingController::class, 'resolveCase']);

    // Dashboard Snapshot & Action routes
    Route::get('/dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('/dashboard/weighing', [DashboardController::class, 'weighing']);
    Route::get('/dashboard/machines', [DashboardController::class, 'machines']);
    Route::get('/dashboard/alerts', [DashboardController::class, 'alerts']);
    Route::get('/dashboard/management', [DashboardController::class, 'management']);
    Route::get('/batches/{id}/timeline', [DashboardController::class, 'batchTimeline']);
    Route::get('/machines/{id}/current-status', [DashboardController::class, 'machineStatus']);
    Route::post('/alerts/{id}/handle', [DashboardController::class, 'handleAlert']);

    // QR Scanner & Workstation Flow routes
    Route::get('/workstations', [ScannerController::class, 'listWorkstations']);
    Route::post('/workstations/{id}/heartbeat', [WorkstationRegistrationController::class, 'heartbeat']);
    // Tự cấu hình cân/máy in ngay tại trạm — không qua Admin (xem ghi chú trong controller).
    Route::put('/workstations/{id}/local-device-config', [WorkstationLocalConfigController::class, 'updateDeviceConfig']);
    Route::post('/scanner/scan', [ScannerController::class, 'scan'])->middleware('workstation.guard:SCAN_ORDER');
    Route::post('/scanner/scan-dye-qr', [ScannerController::class, 'scanRawDyeQr'])->middleware('workstation.guard:SCAN_ORDER');
    // Một lệnh duy nhất cho cả mẻ cân của /weighing-station-v2: mở lệnh sản xuất + tạo vòng cân
    // + ghi số cân + dựng phiếu in. Màn hình đó không gọi /scan-dye-qr nữa — nó tự đọc chuỗi QR
    // bằng JS và chỉ chạm mạng đúng một lần lúc bấm SAVE (xem ScannerController::weighFromQr).
    Route::post('/scanner/weigh-from-qr', [ScannerController::class, 'weighFromQr'])->middleware('workstation.guard:SCAN_ORDER');
    Route::post('/scanner/verify-tank', [ScannerController::class, 'verifyTank'])->middleware('workstation.guard:SCAN_DUAL_VERIFY');
    Route::post('/scanner/acknowledge-order', [ScannerController::class, 'acknowledgeOrder'])->middleware('workstation.guard:SCAN_ORDER');

    // Workstation/Client Admin — provisioning accounts, clients, capabilities, devices, and kiosk links
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/workstations', [OperationClientAdminController::class, 'index']);
        Route::post('/admin/workstations/{id}/users', [OperationClientAdminController::class, 'createUser']);
        Route::post('/admin/workstations/register', [OperationClientAdminController::class, 'register']);
        Route::put('/admin/workstations/{id}/config', [OperationClientAdminController::class, 'updateConfig']);
        Route::post('/admin/workstations/{id}/test-connection', [OperationClientAdminController::class, 'testConnection']);
        Route::post('/admin/workstations/{id}/suspend', [OperationClientAdminController::class, 'suspend']);
        Route::post('/admin/workstations/{id}/resume', [OperationClientAdminController::class, 'resume']);
        Route::post('/admin/workstations/{id}/generate-kiosk-token', [OperationClientAdminController::class, 'generateKioskToken']);
        Route::post('/admin/workstations/{id}/revoke-kiosk-token', [OperationClientAdminController::class, 'revokeKioskToken']);
        Route::get('/admin/devices', [DeviceController::class, 'index']);

        // BPDB / JIT (Color Service) monitoring — chỉ đọc, xem colorservice-trace-report.
        Route::get('/admin/bpdb/overview', [BpdbAdminController::class, 'overview']);
        Route::get('/admin/bpdb/task-links', [BpdbAdminController::class, 'taskLinks']);
        Route::get('/admin/bpdb/jit-routing-rules', [BpdbAdminController::class, 'jitRoutingRules']);

        Route::get('/admin/bpdb/machines/status', [BpdbMachineController::class, 'status']);
        Route::get('/admin/bpdb/machines/status-summary', [BpdbMachineController::class, 'statusSummary']);
        Route::get('/admin/bpdb/machines/gantt', [BpdbMachineController::class, 'gantt']);
        Route::get('/admin/bpdb/machines/{machineCode}/status', [BpdbMachineController::class, 'show']);
        Route::get('/admin/bpdb/machines/{machineCode}/timeline', [BpdbMachineController::class, 'timeline']);

        Route::get('/admin/bpdb/chemical-demand', [BpdbChemicalDemandController::class, 'index']);
        Route::get('/admin/bpdb/chemical-demand/summary', [BpdbChemicalDemandController::class, 'summary']);
        Route::get('/admin/bpdb/chemical-demand/{taskId}', [BpdbChemicalDemandController::class, 'show']);

        Route::get('/admin/bpdb/material-activity/summary', [BpdbMaterialActivityController::class, 'summary']);
        Route::get('/admin/bpdb/material-activity/by-machine', [BpdbMaterialActivityController::class, 'byMachine']);
        Route::get('/admin/bpdb/material-activity/by-material', [BpdbMaterialActivityController::class, 'byMaterial']);
        Route::get('/admin/bpdb/material-activity/timeseries', [BpdbMaterialActivityController::class, 'timeseries']);
        Route::get('/admin/bpdb/material-activity/detail', [BpdbMaterialActivityController::class, 'detail']);
        Route::get('/admin/bpdb/material-activity/detail/export', [BpdbMaterialActivityController::class, 'exportDetail']);

        Route::get('/admin/bpdb/machine-feeding/summary', [BpdbMachineFeedingController::class, 'summary']);
        Route::get('/admin/bpdb/machine-feeding/by-machine', [BpdbMachineFeedingController::class, 'byMachine']);
        Route::get('/admin/bpdb/machine-feeding/by-material', [BpdbMachineFeedingController::class, 'byMaterial']);
        Route::get('/admin/bpdb/machine-feeding/timeseries', [BpdbMachineFeedingController::class, 'timeseries']);
        Route::get('/admin/bpdb/machine-feeding/detail', [BpdbMachineFeedingController::class, 'detail']);
        Route::get('/admin/bpdb/machine-feeding/detail/export', [BpdbMachineFeedingController::class, 'exportDetail']);
    });

    // Reports & Analytics (Phase 11)
    Route::get('/reports/dye-consumption', [ReportController::class, 'dyeConsumption']);
    Route::get('/reports/tolerance-stats', [ReportController::class, 'toleranceStats']);
    Route::get('/reports/machine-output', [ReportController::class, 'machineOutput']);
    Route::get('/reports/troubleshooting-pareto', [ReportController::class, 'troubleshootingPareto']);

    // Audit Log Explorer
    Route::get('/audit-logs', [ReportController::class, 'auditLogs']);
    Route::get('/audit-logs/filters', [ReportController::class, 'auditLogFilters']);
});

// Route SSE cũ (/realtime/stream) đã bị GỠ BỎ 2026-07-25: vòng lặp while(true) giữ 1
// connection HTTP sống mãi khiến php artisan serve trên Windows (không có fork(), không
// có concurrency thật) bị chiếm dụng vĩnh viễn chỉ bởi 1 tab đang mở — mọi request khác
// treo vô thời hạn. Thay bằng Reverb (App\Events\RealtimeEventBroadcast, kênh
// "dashboard-events") — xem RealtimeService::publish() và frontend/src/views/Dashboard.vue.

// Device / Agent Integration Routes — xác thực bằng token workstation (agent.auth),
// KHÔNG dùng auth:sanctum (Local Agent không phải người dùng đăng nhập).
Route::post('/devices/readings', [DeviceController::class, 'storeReading'])->middleware('agent.auth');
// Bao danh "may nay la tram nao" — KHONG kem so can. Tach khoi /devices/readings de tram van
// hien ra khi PuTTY chua ghi dung file log Agent dang doc (loi bo cai can to, 2026-08-04).
Route::post('/devices/hello', [DeviceController::class, 'hello'])->middleware('agent.auth');
Route::get('/agents/{workstation_id}/jobs', [AgentJobsController::class, 'getJobs'])->middleware('agent.auth');
Route::post('/jobs/{job_id}/ack', [AgentJobsController::class, 'acknowledgeJob'])->middleware('agent.auth');
Route::post('/agents/{workstation_id}/printers', [AgentJobsController::class, 'reportPrinters'])->middleware('agent.auth');
// SEND OVER 6 — Agent lấy lệnh gửi rack rồi báo lại kết quả.
Route::get('/agents/{workstation_id}/rack-commands', [RackDispatchController::class, 'pending'])->middleware('agent.auth');
Route::post('/agents/{workstation_id}/rack-commands/{id}/ack', [RackDispatchController::class, 'acknowledge'])->middleware('agent.auth');

// AgentController (device_id-based, theo đúng local-agent-architecture.md Mục 4 —
// hiện Local Agent .NET CHƯA gọi tới nhóm route này, chỉ dùng 3 route phía trên).
// Trước đây nằm sai trong nhóm auth:sanctum (yêu cầu token người dùng — vi phạm
// nguyên tắc "Agent không dùng tài khoản người dùng"); đã chuyển ra đây và gắn
// agent.auth cho các route đại diện lưu lượng thiết bị đang chạy (không áp cho
// 'register' vì đó là bước thiết lập danh tính ban đầu, chưa có token để xuất trình).
Route::post('/agents/register', [AgentController::class, 'register']);
Route::post('/agents/{device_id}/heartbeat', [AgentController::class, 'heartbeat'])->middleware('agent.auth');
Route::post('/agents/{device_id}/event', [AgentController::class, 'logEvent'])->middleware('agent.auth');
// Đã gỡ getPrintJobs/acknowledgePrintJob trùng chức năng với AgentJobsController ở
// trên (dòng 159-160) — Worker.cs (.NET Agent thật) chỉ gọi nhóm route đó, 2 route
// này chưa từng được gọi, khiến print_attempts không bao giờ được ghi dù đã in thật
// (phát hiện khi rà soát pipeline "C. Lịch sử in thực tế", 2026-07-18).
