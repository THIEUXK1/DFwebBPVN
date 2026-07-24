<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Machine;
use App\Models\ProductionBatch;
use App\Models\ScaleMeasurement;
use App\Models\MaterialTransport;
use App\Models\MaterialTransportEvent;
use App\Models\FeedOperation;
use App\Models\Alert;
use App\Models\Recipe;
use App\Models\PrintJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\RealtimeService;

class DashboardController extends Controller
{
    /**
     * Snapshot 1: Overall Dispatcher Overview.
     */
    public function overview(Request $request)
    {
        // Trigger alerting rules validation before serving dashboard snapshot
        RealtimeService::checkAlertRules();

        $machines = Machine::where('is_active', true)->get();
        $batches = ProductionBatch::with(['machine', 'tank'])->whereIn('status', ['NEW', 'READY_TO_WEIGH', 'WEIGHING', 'WEIGHED', 'SENT'])->get();
        $alertsCount = Alert::whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count();

        $overviewData = $machines->map(function ($machine) use ($batches) {
            $currentBatch = $batches->firstWhere('machine_id', $machine->id);
            $activeAlerts = Alert::where('machine_id', $machine->id)->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->get();
            
            return [
                'machine_id' => $machine->id,
                'machine_code' => $machine->code,
                'machine_name' => $machine->name,
                'current_batch' => $currentBatch ? [
                    'id' => $currentBatch->id,
                    'legacy_batch_id' => $currentBatch->legacy_batch_id,
                    'color' => $currentBatch->color,
                    'product_code' => $currentBatch->product_code,
                    'status' => $currentBatch->status,
                    'tank_code' => $currentBatch->tank ? $currentBatch->tank->code : null,
                    'level_code' => $currentBatch->level_code,
                ] : null,
                'alerts' => $activeAlerts,
                'status' => $currentBatch ? $currentBatch->status : 'IDLE',
            ];
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'overview' => $overviewData,
                'open_alerts_count' => $alertsCount,
            ]
        ]);
    }

    /**
     * Snapshot 2: Weighing Station Overview.
     */
    public function weighing(Request $request)
    {
        // Get all batches in queue for weighing
        $batches = ProductionBatch::with(['machine', 'tank'])
            ->whereIn('status', ['READY_TO_WEIGH', 'WEIGHING', 'WEIGHED'])
            ->get();

        $weighingData = $batches->map(function ($batch) {
            // Find completed scale measurements
            $measurements = ScaleMeasurement::where('legacy_batch_id', $batch->legacy_batch_id)->get();
            
            // Get material transport task if exists
            $transport = MaterialTransport::where('batch_id', $batch->id)->first();

            return [
                'batch_id' => $batch->id,
                'legacy_batch_id' => $batch->legacy_batch_id,
                'color' => $batch->color,
                'product_code' => $batch->product_code,
                'machine_code' => $batch->machine ? $batch->machine->code : 'N/A',
                'tank_code' => $batch->tank ? $batch->tank->code : 'N/A',
                'status' => $batch->status,
                'weighed_count' => $measurements->count(),
                'last_weighed_at' => $measurements->max('measured_at'),
                'transport_status' => $transport ? $transport->status : 'NOT_STARTED',
            ];
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $weighingData
        ]);
    }

    /**
     * Snapshot 3: Dyeing Machines detailed view.
     */
    public function machines(Request $request)
    {
        $machines = Machine::where('is_active', true)->get();
        $batches = ProductionBatch::with(['machine', 'tank'])->whereIn('status', ['WEIGHED', 'SENT', 'DONE'])->get();

        $data = $machines->map(function ($m) use ($batches) {
            $batch = $batches->firstWhere('machine_id', $m->id);
            $feedOp = $batch ? FeedOperation::where('batch_id', $batch->id)->first() : null;
            $transport = $batch ? MaterialTransport::where('batch_id', $batch->id)->first() : null;

            return [
                'machine' => $m,
                'active_batch' => $batch,
                'feed_operation' => $feedOp,
                'transport' => $transport,
            ];
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $data
        ]);
    }

    /**
     * Snapshot 4: Open Alerts Dashboard.
     */
    public function alerts(Request $request)
    {
        $alerts = Alert::with(['batch', 'machine', 'assignee'])
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->orderByRaw("CASE WHEN severity = 'CRITICAL' THEN 1 WHEN severity = 'WARNING' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $alerts
        ]);
    }

    /**
     * Snapshot 5: Management KPIs Dashboard.
     */
    public function management(Request $request)
    {
        $today = Carbon::today();
        
        $completedToday = FeedOperation::whereNotNull('completed_at')
            ->where('completed_at', '>=', $today)
            ->count();

        $activeBatches = ProductionBatch::whereIn('status', ['WEIGHING', 'WEIGHED', 'SENT'])->count();
        
        $machinesRunning = ProductionBatch::where('status', 'SENT')->count();
        $machinesWaiting = MaterialTransport::where('status', 'IN_TRANSIT')->count();

        $overdueWeighing = Alert::where('rule_code', 'WEIGH_COMP_DELAY')
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->count();

        // Calculate average transport SLA delay
        $avgMinsExpr = DB::connection()->getDriverName() === 'sqlite'
            ? '(julianday(arrived_at) - julianday(started_at)) * 1440'
            : 'EXTRACT(EPOCH FROM (arrived_at - started_at))/60';

        $avgTransTime = MaterialTransport::where('status', 'ARRIVED_AT_TANK')
            ->whereDate('arrived_at', $today)
            ->selectRaw("AVG($avgMinsExpr) as avg_mins")
            ->first();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'completed_today' => $completedToday,
                'active_batches' => $activeBatches,
                'machines_running' => $machinesRunning,
                'machines_waiting' => $machinesWaiting,
                'overdue_weighing_count' => $overdueWeighing,
                'average_transport_minutes' => round($avgTransTime->avg_mins ?? 0, 1),
                'active_alerts_critical' => Alert::where('severity', 'CRITICAL')->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count(),
                'active_alerts_warning' => Alert::where('severity', 'WARNING')->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])->count(),
            ]
        ]);
    }

    /**
     * Snapshot 6: Batch Timeline detail.
     */
    public function batchTimeline($id)
    {
        $batch = ProductionBatch::with(['machine', 'tank'])->findOrFail($id);
        
        // Find all events relating to this batch
        $timeline = [];

        // 1. Creation milestone
        $timeline[] = [
            'milestone' => 'Tạo lệnh',
            'time' => $batch->created_at,
            'actor' => 'MES System',
            'status' => 'COMPLETED',
            'notes' => 'Tạo đơn lệnh nhuộm từ MES.'
        ];

        // 2. Weighing events
        $measurements = ScaleMeasurement::where('legacy_batch_id', $batch->legacy_batch_id)
            ->orderBy('measured_at', 'asc')
            ->get();

        foreach ($measurements as $m) {
            $timeline[] = [
                'milestone' => 'Cân nguyên liệu: ' . $m->dye_code,
                'time' => $m->measured_at,
                'actor' => 'Operator Trạm Cân',
                'status' => 'COMPLETED',
                'notes' => "Cân đạt khối lượng {$m->weight} kg ({$m->material_type})."
            ];
        }

        // 3. Transport events
        $transport = MaterialTransport::where('batch_id', $batch->id)->first();
        if ($transport) {
            $tEvents = MaterialTransportEvent::where('transport_id', $transport->id)
                ->orderBy('created_at', 'asc')
                ->get();
                
            foreach ($tEvents as $evt) {
                $timeline[] = [
                    'milestone' => 'Vận chuyển: ' . $evt->status,
                    'time' => $evt->created_at,
                    'actor' => $evt->operator ? $evt->operator->display_name : 'Nhân viên giao nhận',
                    'status' => 'COMPLETED',
                    'notes' => $evt->notes
                ];
            }
        }

        // 4. Feeding milestones
        $feedOp = FeedOperation::where('batch_id', $batch->id)->first();
        if ($feedOp) {
            $timeline[] = [
                'milestone' => 'Bắt đầu nạp cấp máy',
                'time' => $feedOp->started_at,
                'actor' => 'Operator Máy Nhuộm',
                'status' => 'COMPLETED',
                'notes' => 'Khai báo sẵn sàng nạp liệu.'
            ];
            if ($feedOp->water_verified) {
                $timeline[] = [
                    'milestone' => 'Xác thực mực nước',
                    'time' => $feedOp->updated_at, // approximation
                    'actor' => 'Hệ thống van tự động',
                    'status' => 'COMPLETED',
                    'notes' => 'Đã xác thực tỉ số nạp nước máy nhuộm.'
                ];
            }
            if ($feedOp->materials_verified) {
                $timeline[] = [
                    'milestone' => 'Quét mã tem hóa chất',
                    'time' => $feedOp->updated_at,
                    'actor' => 'Operator',
                    'status' => 'COMPLETED',
                    'notes' => 'Đã quét QR thùng mẻ khớp thành công.'
                ];
            }
            if ($feedOp->override_approved) {
                $timeline[] = [
                    'milestone' => 'Ghi đè phê duyệt bảo vệ',
                    'time' => $feedOp->updated_at,
                    'actor' => 'Supervisor / Admin',
                    'status' => 'WARNING',
                    'notes' => 'Lý do: ' . $feedOp->override_reason
                ];
            }
            if ($feedOp->completed_at) {
                $timeline[] = [
                    'milestone' => 'Hoàn tất mở van cấp',
                    'time' => $feedOp->completed_at,
                    'actor' => 'Operator',
                    'status' => 'COMPLETED',
                    'notes' => 'Van cấp máy nhuộm được mở nạp thành công.'
                ];
            }
        }

        // Sort chronologically
        usort($timeline, function($a, $b) {
            return Carbon::parse($a['time'])->timestamp <=> Carbon::parse($b['time'])->timestamp;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'batch' => $batch,
                'timeline' => $timeline
            ]
        ]);
    }

    /**
     * Snapshot 7: Single machine current status.
     */
    public function machineStatus($id)
    {
        $machine = Machine::findOrFail($id);
        $currentBatch = ProductionBatch::where('machine_id', $machine->id)
            ->whereIn('status', ['NEW', 'READY_TO_WEIGH', 'WEIGHING', 'WEIGHED', 'SENT'])
            ->first();

        $activeAlerts = Alert::where('machine_id', $machine->id)
            ->whereIn('status', ['OPEN', 'ACKNOWLEDGED'])
            ->get();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'machine' => $machine,
                'current_batch' => $currentBatch,
                'alerts' => $activeAlerts,
            ]
        ]);
    }

    /**
     * Acknowledge / Resolve alerts.
     */
    public function handleAlert(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:ACKNOWLEDGE,RESOLVE',
            'notes' => 'sometimes|nullable|string',
        ]);

        $alert = Alert::findOrFail($id);
        $action = $request->input('action');
        $user = auth()->user();

        if ($action === 'ACKNOWLEDGE') {
            $alert->status = 'ACKNOWLEDGED';
            $alert->assigned_to = $user->id;
            $alert->acknowledged_at = Carbon::now();
            $alert->reason = $request->input('notes');
        } else {
            $alert->status = 'RESOLVED';
            $alert->resolved_by = $user->id;
            $alert->resolved_at = Carbon::now();
            $alert->resolution = $request->input('notes');
        }

        $alert->save();

        // Publish alert updated event
        RealtimeService::publish(
            'alert.updated',
            'Alert',
            (string)$alert->id,
            [
                'id' => $alert->id,
                'status' => $alert->status,
                'assigned_to' => $alert->assigned_to,
                'resolved_by' => $alert->resolved_by,
            ],
            $user->id,
            $alert->machine_id,
            $alert->batch_id
        );

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Cập nhật cảnh báo thành công',
            'data' => $alert
        ]);
    }
}
