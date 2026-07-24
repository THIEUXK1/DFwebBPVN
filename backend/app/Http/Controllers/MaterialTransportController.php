<?php

namespace App\Http\Controllers;

use App\Models\MaterialTransport;
use App\Models\MaterialTransportEvent;
use App\Models\ProductionBatch;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class MaterialTransportController extends Controller
{
    /**
     * List material transports.
     */
    public function index(Request $request)
    {
        $query = MaterialTransport::with(['batch.machine', 'batch.tank', 'events.operator']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        $transports = $query->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'SUCCESS',
            'data' => $transports
        ]);
    }

    /**
     * Create / initialize a transport task for a weighed batch.
     */
    public function store(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:production_batches,id',
            'workstation_id' => 'required|string|max:50',
        ]);

        $batchId = $request->input('batch_id');
        $workstationId = $request->input('workstation_id');

        // Check if transport already exists
        $existing = MaterialTransport::where('batch_id', $batchId)->first();
        if ($existing) {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Nhiệm vụ vận chuyển đã tồn tại.',
                'data' => $existing
            ]);
        }

        $batch = ProductionBatch::with('machine')->findOrFail($batchId);

        // Dynamic SLA calculation based on Machine Line
        $slaMinutes = 15; // default 15 minutes for standard lines
        if ($batch->machine) {
            $code = strtoupper($batch->machine->code);
            // If tank line (T5-T8), SLA is 25 minutes due to longer distance
            if (str_starts_with($code, 'T5') || str_starts_with($code, 'T6') || str_starts_with($code, 'T7') || str_starts_with($code, 'T8')) {
                $slaMinutes = 25;
            }
        }

        $transport = MaterialTransport::create([
            'batch_id' => $batchId,
            'workstation_id' => $workstationId,
            'status' => 'READY_FOR_TRANSFER',
            'sla_minutes' => $slaMinutes,
        ]);

        // Log initial event
        MaterialTransportEvent::create([
            'transport_id' => $transport->id,
            'status' => 'READY_FOR_TRANSFER',
            'operator_id' => Auth::id(),
            'notes' => 'Khởi tạo nhiệm vụ vận chuyển từ trạm ' . $workstationId
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã tạo nhiệm vụ vận chuyển thành công',
            'data' => $transport
        ], 201);
    }

    /**
     * Start the transit (Status: IN_TRANSIT).
     */
    public function startTransit($id)
    {
        $transport = MaterialTransport::findOrFail($id);

        if ($transport->status !== 'READY_FOR_TRANSFER') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Trạng thái không hợp lệ để bắt đầu vận chuyển.'
            ], 400);
        }

        $transport->status = 'IN_TRANSIT';
        $transport->started_at = Carbon::now();
        $transport->save();

        MaterialTransportEvent::create([
            'transport_id' => $transport->id,
            'status' => 'IN_TRANSIT',
            'operator_id' => Auth::id(),
            'notes' => 'Nguyên liệu đang trên đường vận chuyển.'
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đang vận chuyển...',
            'data' => $transport
        ]);
    }

    /**
     * Confirm arrival at tank (Status: ARRIVED_AT_TANK) with barcode scanner verification and SLA check.
     */
    public function arrive(Request $request, $id)
    {
        $transport = MaterialTransport::with('batch')->findOrFail($id);

        if ($transport->status !== 'IN_TRANSIT') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mẻ cân chưa được chuyển đi hoặc đã giao tới thùng.'
            ], 400);
        }

        $request->validate([
            'scan_data' => 'required|string',
            'delay_reason' => 'sometimes|nullable|string'
        ]);

        $scanData = $request->input('scan_data');

        // Verify QR destination match: Scan data must contain batch's legacy_batch_id
        if (!str_contains($scanData, $transport->batch->legacy_batch_id)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Quét sai nhãn! Mã QR quét không khớp với mã Lô vận chuyển này.'
            ], 400);
        }

        // SLA validation check
        $now = Carbon::now();
        $started = $transport->started_at ?? $now;
        $elapsedMinutes = $started->diffInMinutes($now);

        $delayReason = $request->input('delay_reason');

        if ($elapsedMinutes > $transport->sla_minutes && empty($delayReason)) {
            return response()->json([
                'status' => 'SLA_BREACH',
                'message' => "Cảnh báo: Thời gian vận chuyển ({$elapsedMinutes} phút) vượt quá SLA định mức ({$transport->sla_minutes} phút). Vui lòng nhập lý do trễ hạn để tiếp tục.",
                'elapsed_minutes' => $elapsedMinutes,
                'sla_minutes' => $transport->sla_minutes
            ], 422);
        }

        // Update transport record
        $transport->status = 'ARRIVED_AT_TANK';
        $transport->arrived_at = $now;
        if (!empty($delayReason)) {
            $transport->delay_reason = $delayReason;
        }
        $transport->save();

        // Update production batch status
        $batch = $transport->batch;
        $batch->status = 'WEIGHED'; // Or keep WEIGHED / ARRIVED state
        $batch->save();

        MaterialTransportEvent::create([
            'transport_id' => $transport->id,
            'status' => 'ARRIVED_AT_TANK',
            'operator_id' => Auth::id(),
            'notes' => 'Đã giao tới thùng máy nhuộm. Xác thực QR thành công.' . ($delayReason ? ' Lý do trễ: ' . $delayReason : '')
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Giao nhận tại thùng thành công!',
            'data' => $transport
        ]);
    }

    /**
     * Accept or reject material at tank.
     */
    public function acceptReject(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:ACCEPTED,REJECTED',
            'notes' => 'sometimes|nullable|string'
        ]);

        $transport = MaterialTransport::findOrFail($id);

        if ($transport->status !== 'ARRIVED_AT_TANK') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Nguyên liệu chưa tới thùng máy để nghiệm thu.'
            ], 400);
        }

        $status = $request->input('status');
        $transport->status = $status;
        $transport->save();

        MaterialTransportEvent::create([
            'transport_id' => $transport->id,
            'status' => $status,
            'operator_id' => Auth::id(),
            'notes' => $request->input('notes', 'Xác nhận trạng thái: ' . $status)
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Nghiệm thu nguyên liệu: ' . $status,
            'data' => $transport
        ]);
    }
}
