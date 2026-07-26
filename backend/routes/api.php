<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceController;

// Public Auth Route
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/workstations/handshake', [\App\Http\Controllers\WorkstationRegistrationController::class, 'handshake']);
Route::post('/kiosk/session', [\App\Http\Controllers\KioskSessionController::class, 'establishSession']);

// Protected Auth Routes
Route::middleware(\App\Http\Middleware\KioskAuthenticationMiddleware::class)->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/kiosk/verify-pin', [\App\Http\Controllers\KioskSessionController::class, 'verifyManagerPin']);

    // Materials
    Route::get('/materials', [\App\Http\Controllers\MaterialController::class, 'index']);
    Route::put('/materials/{id}', [\App\Http\Controllers\MaterialController::class, 'update']);

    // Water Configs
    Route::get('/water-configs', [\App\Http\Controllers\WaterConfigController::class, 'index']);
    Route::put('/water-configs/{id}', [\App\Http\Controllers\WaterConfigController::class, 'update']);

    // Recipes
    Route::get('/recipes', [\App\Http\Controllers\RecipeController::class, 'index']);
    Route::post('/recipes', [\App\Http\Controllers\RecipeController::class, 'store']);
    Route::get('/recipes/{id}', [\App\Http\Controllers\RecipeController::class, 'show']);

    // Calculations
    Route::post('/calculations/preview', [\App\Http\Controllers\CalculationController::class, 'preview']);

    // Production Batches
    Route::get('/production-batches', [\App\Http\Controllers\ProductionBatchController::class, 'index']);
    Route::post('/production-batches', [\App\Http\Controllers\ProductionBatchController::class, 'store']);
    Route::get('/machines', [\App\Http\Controllers\ProductionBatchController::class, 'machines']);
    Route::post('/machines', [\App\Http\Controllers\ProductionBatchController::class, 'storeMachine']);
    Route::get('/tanks', [\App\Http\Controllers\ProductionBatchController::class, 'tanks']);
    Route::post('/production-batches/scan-parse', [\App\Http\Controllers\ProductionBatchController::class, 'scanParse']);
    Route::put('/production-batches/{id}/status', [\App\Http\Controllers\ProductionBatchController::class, 'updateStatus']);
    Route::put('/production-batches/{id}/tank', [\App\Http\Controllers\ProductionBatchController::class, 'updateTank']);
    Route::post('/production-batches/{id}/approve', [\App\Http\Controllers\ProductionBatchController::class, 'approve']);

    // Machine Dispatches
    Route::get('/machine-dispatches', [\App\Http\Controllers\MachineDispatchController::class, 'index']);
    Route::get('/machine-dispatches/history', [\App\Http\Controllers\MachineDispatchController::class, 'history']);
    Route::post('/machine-dispatches/{id}/claim', [\App\Http\Controllers\MachineDispatchController::class, 'claim']);
    Route::post('/machine-dispatches/{id}/release', [\App\Http\Controllers\MachineDispatchController::class, 'release']);
    Route::post('/machine-dispatches/{id}/send', [\App\Http\Controllers\MachineDispatchController::class, 'send']);
    Route::post('/machine-dispatches/{id}/confirm', [\App\Http\Controllers\MachineDispatchController::class, 'confirm']);
    Route::post('/machine-dispatches/{id}/reprint', [\App\Http\Controllers\MachineDispatchController::class, 'reprint']);
    Route::get('/machine-dispatches/{id}/preview', [\App\Http\Controllers\MachineDispatchController::class, 'previewPayload']);
    Route::post('/print-jobs/{id}/cancel', [\App\Http\Controllers\PrintJobController::class, 'cancel']);

    // Chemical Calls (Wave 2)
    Route::get('/chemical-channels', [\App\Http\Controllers\ChemicalCallController::class, 'getChannels']);
    Route::post('/chemical-channels', [\App\Http\Controllers\ChemicalCallController::class, 'storeChannel']);
    Route::patch('/chemical-channels/{id}', [\App\Http\Controllers\ChemicalCallController::class, 'updateChannel']);
    Route::post('/chemical-call-requests', [\App\Http\Controllers\ChemicalCallController::class, 'createRequest']);
    Route::patch('/chemical-call-requests/{id}/acknowledge', [\App\Http\Controllers\ChemicalCallController::class, 'acknowledge']);
    Route::patch('/chemical-call-requests/{id}/complete', [\App\Http\Controllers\ChemicalCallController::class, 'complete']);
    Route::patch('/chemical-call-requests/{id}/cancel', [\App\Http\Controllers\ChemicalCallController::class, 'cancel']);
    Route::patch('/chemical-call-requests/{id}/reset', [\App\Http\Controllers\ChemicalCallController::class, 'reset']);
    Route::get('/chemical-call-requests/{id}/events', [\App\Http\Controllers\ChemicalCallController::class, 'getEvents']);
    Route::get('/chemical-call-events', [\App\Http\Controllers\ChemicalCallController::class, 'getRecentEvents']);

    // Scale Measurements for Web App
    Route::get('/devices/readings/{workstation_id}', [\App\Http\Controllers\DeviceController::class, 'getReading']);
    Route::get('/scale-measurements', [\App\Http\Controllers\ScaleMeasurementController::class, 'index']);
    Route::post('/scale-measurements', [\App\Http\Controllers\ScaleMeasurementController::class, 'store']);
    Route::get('/scale-measurements/checker', [\App\Http\Controllers\ScaleMeasurementController::class, 'checker']);

    // Print Jobs for Web App
    Route::post('/print-jobs', [\App\Http\Controllers\PrintJobController::class, 'store']);


    // Weighing Jobs & Items (Wave 4)
    Route::get('/weighing-jobs/{id}', [\App\Http\Controllers\WeighingJobController::class, 'show']);
    Route::post('/weighing-jobs/items/{id}/weigh', [\App\Http\Controllers\WeighingJobController::class, 'weighItem'])->middleware('workstation.guard:WEIGH_ITEM');
    Route::post('/weighing-job-items/{id}/samples', [\App\Http\Controllers\WeighingJobController::class, 'pushSample']);
    Route::get('/weighing-job-items/{id}/stable-reading', [\App\Http\Controllers\WeighingJobController::class, 'getStableReading']);
    Route::post('/weighing-job-items/{id}/accept', [\App\Http\Controllers\WeighingJobController::class, 'acceptWeight']);
    Route::post('/weighing-job-items/{id}/override', [\App\Http\Controllers\WeighingJobController::class, 'overrideWeight']);
    Route::post('/weighing-jobs/{id}/print', [\App\Http\Controllers\WeighingJobController::class, 'printLabel'])->middleware('workstation.guard:PRINT_LABEL');
    Route::post('/weighing-jobs/{id}/print-slip', [\App\Http\Controllers\WeighingJobController::class, 'printSlip'])->middleware('workstation.guard:PRINT_SLIP');
    Route::get('/material-labels/search', [\App\Http\Controllers\WeighingJobController::class, 'searchLabels']);
    Route::get('/material-labels/{id}', [\App\Http\Controllers\WeighingJobController::class, 'showLabel']);
    Route::post('/material-labels/{id}/reprint', [\App\Http\Controllers\WeighingJobController::class, 'reprintLabel'])->middleware('workstation.guard:REPRINT_LABEL');

    // Material Transports
    Route::get('/material-transports', [\App\Http\Controllers\MaterialTransportController::class, 'index']);
    Route::post('/material-transports', [\App\Http\Controllers\MaterialTransportController::class, 'store']);
    Route::post('/material-transports/{id}/transit', [\App\Http\Controllers\MaterialTransportController::class, 'startTransit'])->middleware('workstation.guard:CONFIRM_TRANSIT');
    Route::post('/material-transports/{id}/arrive', [\App\Http\Controllers\MaterialTransportController::class, 'arrive'])->middleware('workstation.guard:CONFIRM_ARRIVAL');
    Route::post('/material-transports/{id}/accept', [\App\Http\Controllers\MaterialTransportController::class, 'acceptReject']);

    // Feed Operations
    Route::get('/feed-operations', [\App\Http\Controllers\FeedOperationController::class, 'index']);
    Route::get('/feed-operations/readiness/{batch_id}', [\App\Http\Controllers\FeedOperationController::class, 'checkReadiness']);
    Route::post('/feed-operations', [\App\Http\Controllers\FeedOperationController::class, 'startFeed'])->middleware('workstation.guard:CONFIRM_FEED');
    Route::post('/feed-operations/{id}/verify-water', [\App\Http\Controllers\FeedOperationController::class, 'verifyWater']);
    Route::post('/feed-operations/{id}/verify-materials', [\App\Http\Controllers\FeedOperationController::class, 'verifyMaterials']);
    Route::post('/feed-operations/{id}/override', [\App\Http\Controllers\FeedOperationController::class, 'override']);
    Route::post('/feed-operations/{id}/complete', [\App\Http\Controllers\FeedOperationController::class, 'completeFeed'])->middleware('workstation.guard:CONFIRM_FEED');

    // Troubleshooting
    Route::get('/troubleshooting/problems', [\App\Http\Controllers\TroubleshootingController::class, 'indexProblems']);
    Route::get('/troubleshooting/processes', [\App\Http\Controllers\TroubleshootingController::class, 'indexProcesses']);
    Route::get('/troubleshooting/parameters', [\App\Http\Controllers\TroubleshootingController::class, 'indexParameters']);
    Route::get('/troubleshooting/cases', [\App\Http\Controllers\TroubleshootingController::class, 'indexCases']);
    Route::get('/troubleshooting/cases/{id}', [\App\Http\Controllers\TroubleshootingController::class, 'showCase']);
    Route::post('/troubleshooting/diagnose', [\App\Http\Controllers\TroubleshootingController::class, 'diagnose']);
    Route::post('/troubleshooting/cases/{id}/resolve', [\App\Http\Controllers\TroubleshootingController::class, 'resolveCase']);

    // Dashboard Snapshot & Action routes
    Route::get('/dashboard/overview', [\App\Http\Controllers\DashboardController::class, 'overview']);
    Route::get('/dashboard/weighing', [\App\Http\Controllers\DashboardController::class, 'weighing']);
    Route::get('/dashboard/machines', [\App\Http\Controllers\DashboardController::class, 'machines']);
    Route::get('/dashboard/alerts', [\App\Http\Controllers\DashboardController::class, 'alerts']);
    Route::get('/dashboard/management', [\App\Http\Controllers\DashboardController::class, 'management']);
    Route::get('/batches/{id}/timeline', [\App\Http\Controllers\DashboardController::class, 'batchTimeline']);
    Route::get('/machines/{id}/current-status', [\App\Http\Controllers\DashboardController::class, 'machineStatus']);
    Route::post('/alerts/{id}/handle', [\App\Http\Controllers\DashboardController::class, 'handleAlert']);

    // QR Scanner & Workstation Flow routes
    Route::get('/workstations', [\App\Http\Controllers\ScannerController::class, 'listWorkstations']);
    Route::post('/workstations/{id}/heartbeat', [\App\Http\Controllers\WorkstationRegistrationController::class, 'heartbeat']);
    // Tự cấu hình cân/máy in ngay tại trạm — không qua Admin (xem ghi chú trong controller).
    Route::put('/workstations/{id}/local-device-config', [\App\Http\Controllers\WorkstationLocalConfigController::class, 'updateDeviceConfig']);
    Route::post('/scanner/scan', [\App\Http\Controllers\ScannerController::class, 'scan'])->middleware('workstation.guard:SCAN_ORDER');
    Route::post('/scanner/scan-dye-qr', [\App\Http\Controllers\ScannerController::class, 'scanRawDyeQr'])->middleware('workstation.guard:SCAN_ORDER');
    Route::post('/scanner/verify-tank', [\App\Http\Controllers\ScannerController::class, 'verifyTank'])->middleware('workstation.guard:SCAN_DUAL_VERIFY');
    Route::post('/scanner/acknowledge-order', [\App\Http\Controllers\ScannerController::class, 'acknowledgeOrder'])->middleware('workstation.guard:SCAN_ORDER');

    // Workstation/Client Admin — provisioning accounts, clients, capabilities, devices, and kiosk links
    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/workstations', [\App\Http\Controllers\OperationClientAdminController::class, 'index']);
        Route::post('/admin/workstations/{id}/users', [\App\Http\Controllers\OperationClientAdminController::class, 'createUser']);
        Route::post('/admin/workstations/register', [\App\Http\Controllers\OperationClientAdminController::class, 'register']);
        Route::put('/admin/workstations/{id}/config', [\App\Http\Controllers\OperationClientAdminController::class, 'updateConfig']);
        Route::post('/admin/workstations/{id}/test-connection', [\App\Http\Controllers\OperationClientAdminController::class, 'testConnection']);
        Route::post('/admin/workstations/{id}/suspend', [\App\Http\Controllers\OperationClientAdminController::class, 'suspend']);
        Route::post('/admin/workstations/{id}/resume', [\App\Http\Controllers\OperationClientAdminController::class, 'resume']);
        Route::post('/admin/workstations/{id}/generate-kiosk-token', [\App\Http\Controllers\OperationClientAdminController::class, 'generateKioskToken']);
        Route::post('/admin/workstations/{id}/revoke-kiosk-token', [\App\Http\Controllers\OperationClientAdminController::class, 'revokeKioskToken']);
        Route::get('/admin/devices', [\App\Http\Controllers\DeviceController::class, 'index']);

        // BPDB / JIT (Color Service) monitoring — chỉ đọc, xem colorservice-trace-report.
        Route::get('/admin/bpdb/overview', [\App\Http\Controllers\BpdbAdminController::class, 'overview']);
        Route::get('/admin/bpdb/task-links', [\App\Http\Controllers\BpdbAdminController::class, 'taskLinks']);
        Route::get('/admin/bpdb/jit-routing-rules', [\App\Http\Controllers\BpdbAdminController::class, 'jitRoutingRules']);

        Route::get('/admin/bpdb/machines/status', [\App\Http\Controllers\BpdbMachineController::class, 'status']);
        Route::get('/admin/bpdb/machines/status-summary', [\App\Http\Controllers\BpdbMachineController::class, 'statusSummary']);
        Route::get('/admin/bpdb/machines/{machineCode}/status', [\App\Http\Controllers\BpdbMachineController::class, 'show']);
        Route::get('/admin/bpdb/machines/{machineCode}/timeline', [\App\Http\Controllers\BpdbMachineController::class, 'timeline']);

        Route::get('/admin/bpdb/chemical-demand', [\App\Http\Controllers\BpdbChemicalDemandController::class, 'index']);
        Route::get('/admin/bpdb/chemical-demand/summary', [\App\Http\Controllers\BpdbChemicalDemandController::class, 'summary']);
        Route::get('/admin/bpdb/chemical-demand/{taskId}', [\App\Http\Controllers\BpdbChemicalDemandController::class, 'show']);

        Route::get('/admin/bpdb/material-activity/summary', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'summary']);
        Route::get('/admin/bpdb/material-activity/by-machine', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'byMachine']);
        Route::get('/admin/bpdb/material-activity/by-material', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'byMaterial']);
        Route::get('/admin/bpdb/material-activity/timeseries', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'timeseries']);
        Route::get('/admin/bpdb/material-activity/detail', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'detail']);
        Route::get('/admin/bpdb/material-activity/detail/export', [\App\Http\Controllers\BpdbMaterialActivityController::class, 'exportDetail']);

        Route::get('/admin/bpdb/machine-feeding/summary', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'summary']);
        Route::get('/admin/bpdb/machine-feeding/by-machine', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'byMachine']);
        Route::get('/admin/bpdb/machine-feeding/by-material', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'byMaterial']);
        Route::get('/admin/bpdb/machine-feeding/timeseries', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'timeseries']);
        Route::get('/admin/bpdb/machine-feeding/detail', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'detail']);
        Route::get('/admin/bpdb/machine-feeding/detail/export', [\App\Http\Controllers\BpdbMachineFeedingController::class, 'exportDetail']);
    });

    // Reports & Analytics (Phase 11)
    Route::get('/reports/dye-consumption', [\App\Http\Controllers\ReportController::class, 'dyeConsumption']);
    Route::get('/reports/tolerance-stats', [\App\Http\Controllers\ReportController::class, 'toleranceStats']);
    Route::get('/reports/machine-output', [\App\Http\Controllers\ReportController::class, 'machineOutput']);
    Route::get('/reports/troubleshooting-pareto', [\App\Http\Controllers\ReportController::class, 'troubleshootingPareto']);

    // Audit Log Explorer
    Route::get('/audit-logs', [\App\Http\Controllers\ReportController::class, 'auditLogs']);
    Route::get('/audit-logs/filters', [\App\Http\Controllers\ReportController::class, 'auditLogFilters']);
});

// Route SSE cũ (/realtime/stream) đã bị GỠ BỎ 2026-07-25: vòng lặp while(true) giữ 1
// connection HTTP sống mãi khiến php artisan serve trên Windows (không có fork(), không
// có concurrency thật) bị chiếm dụng vĩnh viễn chỉ bởi 1 tab đang mở — mọi request khác
// treo vô thời hạn. Thay bằng Reverb (App\Events\RealtimeEventBroadcast, kênh
// "dashboard-events") — xem RealtimeService::publish() và frontend/src/views/Dashboard.vue.

// Device / Agent Integration Routes — xác thực bằng token workstation (agent.auth),
// KHÔNG dùng auth:sanctum (Local Agent không phải người dùng đăng nhập).
Route::post('/devices/readings', [\App\Http\Controllers\DeviceController::class, 'storeReading'])->middleware('agent.auth');
Route::get('/agents/{workstation_id}/jobs', [\App\Http\Controllers\AgentJobsController::class, 'getJobs'])->middleware('agent.auth');
Route::post('/jobs/{job_id}/ack', [\App\Http\Controllers\AgentJobsController::class, 'acknowledgeJob'])->middleware('agent.auth');
Route::post('/agents/{workstation_id}/printers', [\App\Http\Controllers\AgentJobsController::class, 'reportPrinters'])->middleware('agent.auth');

// AgentController (device_id-based, theo đúng local-agent-architecture.md Mục 4 —
// hiện Local Agent .NET CHƯA gọi tới nhóm route này, chỉ dùng 3 route phía trên).
// Trước đây nằm sai trong nhóm auth:sanctum (yêu cầu token người dùng — vi phạm
// nguyên tắc "Agent không dùng tài khoản người dùng"); đã chuyển ra đây và gắn
// agent.auth cho các route đại diện lưu lượng thiết bị đang chạy (không áp cho
// 'register' vì đó là bước thiết lập danh tính ban đầu, chưa có token để xuất trình).
Route::post('/agents/register', [\App\Http\Controllers\AgentController::class, 'register']);
Route::post('/agents/{device_id}/heartbeat', [\App\Http\Controllers\AgentController::class, 'heartbeat'])->middleware('agent.auth');
Route::post('/agents/{device_id}/event', [\App\Http\Controllers\AgentController::class, 'logEvent'])->middleware('agent.auth');
// Đã gỡ getPrintJobs/acknowledgePrintJob trùng chức năng với AgentJobsController ở
// trên (dòng 159-160) — Worker.cs (.NET Agent thật) chỉ gọi nhóm route đó, 2 route
// này chưa từng được gọi, khiến print_attempts không bao giờ được ghi dù đã in thật
// (phát hiện khi rà soát pipeline "C. Lịch sử in thực tế", 2026-07-18).
