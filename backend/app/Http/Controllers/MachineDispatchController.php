<?php

namespace App\Http\Controllers;

use App\Models\MachineDispatch;
use App\Models\ProductionBatch;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MachineDispatchController extends Controller
{
    /**
     * List active machine dispatches.
     */
    public function index(Request $request)
    {
        $query = MachineDispatch::query()
            ->with(['batch.machine', 'batch.tank', 'lockedByUser'])
            ->whereIn('queue_state', ['INPUT', 'WAITING', 'TO_SEND', 'PROCESSING', 'ERROR']);

        if ($request->has('machine_id')) {
            $machineId = $request->input('machine_id');
            $query->whereHas('batch', function($q) use ($machineId) {
                $q->where('machine_id', $machineId);
            });
        }

        $dispatches = $query->orderBy('created_at', 'desc')->get();
        return response()->json($dispatches);
    }

    /**
     * Lịch sử in tem — trả về đủ 3 tầng theo yêu cầu 2026-07-18, KHÔNG gộp vào 1 danh
     * sách mơ hồ:
     *   A. Bản thân dòng MachineDispatch (đơn đã CONFIRMED) — mã đơn/màu/máy/tank/
     *      thời gian chuyển/trạm gửi (originating_station_code)/trạng thái chờ in.
     *   B. print_jobs[] lồng bên trong — mỗi lần "In tem"/"In lại" là 1 PrintJob riêng
     *      (lúc tạo, máy in dự kiến, trạng thái QUEUED/PENDING/PRINTED/FAILED/
     *      CANCELLED, nội dung lỗi qua events[] cùng cấp).
     *   C. print_jobs[].attempts[] lồng trong từng print job — lần gửi xuống máy in
     *      thật (print_attempts: đã gửi lúc nào, thành công/thất bại, số lần, lỗi).
     * Admin dùng CHUNG endpoint này (không có logic riêng) — chỉ khác ở chỗ có thể lọc
     * theo station_code/print_status/from/to/q mà Trạm in thường không cần dùng tới.
     */
    public function history(Request $request)
    {
        $query = MachineDispatch::query()
            ->with([
                'batch.machine',
                'batch.tank',
                'printJobs' => fn ($q) => $q->orderBy('created_at', 'asc'),
                'printJobs.attempts',
                'printJobs.events' => fn ($q) => $q->orderBy('event_time', 'asc'),
            ])
            ->where('queue_state', 'CONFIRMED');

        if ($request->has('machine_id')) {
            $machineId = $request->input('machine_id');
            $query->whereHas('batch', function ($q) use ($machineId) {
                $q->where('machine_id', $machineId);
            });
        }

        // station_code: khớp HOẶC trạm đã gửi lệnh (duyệt đơn) HOẶC trạm đã thực hiện in
        // — Admin cần xem theo cả 2 chiều, Trạm in bình thường không truyền filter này.
        if ($request->filled('station_code')) {
            $stationCode = $request->input('station_code');
            $query->where(function ($q) use ($stationCode) {
                $q->where('originating_station_code', $stationCode)
                    ->orWhereHas('printJobs', fn ($pj) => $pj->where('workstation_id', $stationCode));
            });
        }

        if ($request->filled('print_status')) {
            $printStatus = $request->input('print_status');
            $query->whereHas('printJobs', fn ($pj) => $pj->where('status', $printStatus));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->whereHas('batch', function ($b) use ($q) {
                $b->where('legacy_batch_id', 'like', "%{$q}%")
                    ->orWhere('color', 'like', "%{$q}%")
                    ->orWhere('product_code', 'like', "%{$q}%");
            });
        }

        $dispatches = $query->orderBy('created_at', 'desc')->limit(100)->get();

        return response()->json(['status' => 'SUCCESS', 'data' => $dispatches]);
    }

    /**
     * Claim lock on a machine dispatch.
     */
    public function claim(Request $request, $id)
    {
        $user = auth()->user();
        $dispatch = MachineDispatch::findOrFail($id);

        if ($dispatch->locked_by !== null && $dispatch->locked_by !== $user->id) {
            $lockAgeSeconds = abs(Carbon::now()->diffInSeconds($dispatch->locked_at));
            \Log::info("Lock debug: now=" . Carbon::now()->toIso8601String() . ", locked_at=" . $dispatch->locked_at->toIso8601String() . ", diff=" . $lockAgeSeconds);
            if ($lockAgeSeconds < 300) { // 5 minutes lock duration
                return response()->json([
                    'status' => 'ERROR',
                    'message' => 'Bản ghi này đang bị khóa bởi vận hành viên khác',
                    'locked_by_user' => $dispatch->lockedByUser?->display_name ?? 'Người dùng khác',
                    'lock_expires_in' => 300 - $lockAgeSeconds
                ], 409);
            }
            
            // If lock expired, we log lock override (cướp khóa)
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'LOCK_OVERRIDE',
                'entity_type' => 'machine_dispatches',
                'entity_id' => $dispatch->id,
                'before_data' => ['locked_by' => $dispatch->locked_by, 'locked_at' => $dispatch->locked_at],
                'after_data' => ['locked_by' => $user->id, 'locked_at' => Carbon::now()],
                'client_ip' => $request->ip()
            ]);
        }

        $dispatch->locked_by = $user->id;
        $dispatch->locked_at = Carbon::now();
        $dispatch->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Khóa thành công bản ghi điều phối',
            'data' => $dispatch->load('lockedByUser')
        ]);
    }

    /**
     * Release lock on a machine dispatch.
     */
    public function release(Request $request, $id)
    {
        $user = auth()->user();
        $dispatch = MachineDispatch::findOrFail($id);

        if ($dispatch->locked_by === null) {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Bản ghi không bị khóa'
            ]);
        }

        // Validate rights to release the lock: lock owner or Admin/Shift Leader
        $isAdminOrShiftLeader = array_intersect(['ADMIN', 'SHIFT_LEADER'], $user->roles ?? []);
        
        if ($dispatch->locked_by !== $user->id && empty($isAdminOrShiftLeader)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Bạn không có quyền mở khóa bản ghi của người khác'
            ], 403);
        }

        $isForceUnlock = $dispatch->locked_by !== $user->id;
        $beforeData = ['locked_by' => $dispatch->locked_by, 'locked_at' => $dispatch->locked_at];

        $dispatch->locked_by = null;
        $dispatch->locked_at = null;
        $dispatch->save();

        if ($isForceUnlock) {
            $reason = $request->input('reason', 'Giải phóng khóa bắt buộc bởi quản lý ca.');
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'FORCE_UNLOCK',
                'entity_type' => 'machine_dispatches',
                'entity_id' => $dispatch->id,
                'before_data' => $beforeData,
                'after_data' => ['locked_by' => null, 'locked_at' => null, 'reason' => $reason],
                'client_ip' => $request->ip()
            ]);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã giải phóng khóa bản ghi điều phối',
            'data' => $dispatch
        ]);
    }

    /**
     * Dispatch / Send command to automated machine.
     */
    public function send(Request $request, $id)
    {
        $user = auth()->user();
        $dispatch = MachineDispatch::findOrFail($id);

        // Check if the current user holds the lock
        if ($dispatch->locked_by !== $user->id) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Bạn phải nhận khóa bản ghi điều phối (Claim Lock) trước khi gửi máy'
            ], 403);
        }

        return DB::transaction(function() use ($dispatch, $user, $request) {
            // Update dispatch queue state
            $dispatch->queue_state = 'SENT';
            $dispatch->is_sent = true;
            $dispatch->sent_at = Carbon::now();
            $dispatch->locked_by = null;
            $dispatch->locked_at = null;
            $dispatch->save();

            // Update associated production batch status
            if ($dispatch->batch) {
                $dispatch->batch->status = 'SENT';
                $dispatch->batch->save();
            }

            // Simulate writing back commands (e.g. nạp van hóa chất tự động)
            $machineCode = $dispatch->batch?->machine?->code ?? 'VD-UNKNOWN';
            
            // Log the dispatch event in AuditLog
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'DISPATCH_TO_MACHINE',
                'entity_type' => 'machine_dispatches',
                'entity_id' => $dispatch->id,
                'before_data' => ['queue_state' => 'INPUT', 'is_sent' => false],
                'after_data' => [
                    'queue_state' => 'SENT',
                    'is_sent' => true,
                    'machine_code' => $machineCode,
                    'sent_at' => Carbon::now()
                ],
                'client_ip' => $request->ip()
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'message' => "Đã gửi lệnh điều phối mẻ thành công tới máy {$machineCode}!",
                'data' => $dispatch
            ]);
        });
    }

    /**
     * Confirm dispatch row, run routing, generate QRs, and spool print job (WS-002/003).
     */
    public function confirm(Request $request, $id)
    {
        $request->validate([
            'idempotency_key' => 'required|string',
            'workstation_id' => 'sometimes|string',
            'printer_address' => 'sometimes|string',
            'printer_type' => 'sometimes|string|in:USB,LAN',
            'scale_check_policy' => 'sometimes|string',
        ]);

        $service = app(\App\Services\ConfirmDispatchService::class);

        try {
            $result = $service->confirm(
                $id,
                $request->input('idempotency_key'),
                $request->only(['workstation_id', 'printer_address', 'printer_type', 'scale_check_policy'])
            );

            if (request()->header('X-Remote-Operation') === 'true') {
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'REMOTE_CONFIRM_DISPATCH',
                    'entity_type' => 'machine_dispatches',
                    'entity_id' => $id,
                    'client_ip' => $request->ip(),
                    'after_data' => [
                        'target_workstation_id' => $request->header('X-Target-Workstation'),
                        'remote' => true,
                        'reason' => $request->header('X-Remote-Reason') ?? 'Admin remote confirm dispatch'
                    ]
                ]);
            }

            return response()->json([
                'status' => 'SUCCESS',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * In lại tem cho 1 đơn ĐÃ in (yêu cầu 2026-07-18: tier B/C lịch sử in phải phân
     * biệt được lần in đầu và lần in lại + lý do). Không tính lại routing/QR — dùng
     * đúng QrPayload đã sinh lần đầu.
     */
    public function reprint(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'workstation_id' => 'sometimes|string',
            'printer_address' => 'sometimes|string',
            'printer_type' => 'sometimes|string|in:USB,LAN',
        ]);

        try {
            $job = app(\App\Services\ConfirmDispatchService::class)->reprint(
                $id,
                $request->only(['workstation_id', 'printer_address', 'printer_type', 'reason'])
            );

            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Đã tạo lệnh in lại.',
                'data' => $job,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Preview routing decision and QR payloads without committing confirmation.
     */
    public function previewPayload($id)
    {
        $dispatch = MachineDispatch::findOrFail($id);
        $routingService = app(\App\Services\WarehouseRoutingService::class);
        
        // Temporarily calculate routing
        $tempDecision = $routingService->calculateRouting($dispatch);
        
        // Mock QR Payloads
        $dyeData = "DF:DYE:" . $dispatch->id . ":" . ($dispatch->color ?? 'UNKNOWN');
        $chemData = "DF:CHEM:" . $dispatch->id . ":" . ($tempDecision->route ?? 'UNKNOWN');

        // Delete the temporary record so we don't pollute database on preview
        $tempDecision->delete();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'mode' => $tempDecision->mode,
                'route' => $tempDecision->route,
                'matched_rule' => $tempDecision->matched_rule,
                'needs_manual_review' => $tempDecision->needs_manual_review,
                'warnings' => $tempDecision->warnings,
                'previews' => [
                    ['type' => 'DYE', 'payload' => $dyeData],
                    ['type' => 'CHEM', 'payload' => $chemData]
                ]
            ]
        ]);
    }
}
