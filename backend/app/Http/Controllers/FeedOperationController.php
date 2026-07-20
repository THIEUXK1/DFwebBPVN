<?php

namespace App\Http\Controllers;

use App\Models\FeedOperation;
use App\Models\ProductionBatch;
use App\Models\MaterialTransport;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FeedOperationController extends Controller
{
    /**
     * List feed operations.
     */
    public function index(Request $request)
    {
        $query = FeedOperation::with(['batch.machine', 'batch.tank', 'operator', 'overrideApprovedBy']);

        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        $ops = $query->orderBy('created_at', 'desc')->get();
        return response()->json([
            'status' => 'SUCCESS',
            'data' => $ops
        ]);
    }

    /**
     * Check feed readiness rules.
     */
    public function checkReadiness($batchId)
    {
        $batch = ProductionBatch::findOrFail($batchId);
        
        // Find transport status
        $transport = MaterialTransport::where('batch_id', $batchId)->first();
        
        $transportAccepted = $transport && in_array($transport->status, ['ARRIVED_AT_TANK', 'ACCEPTED']);
        $batchWeighed = in_array($batch->status, ['WEIGHED', 'IN_TRANSIT', 'ARRIVED_AT_TANK', 'READY_TO_FEED', 'FED_TO_MACHINE']);

        $ready = $transportAccepted && $batchWeighed;

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'batch_id' => $batchId,
                'batch_status' => $batch->status,
                'transport_status' => $transport ? $transport->status : 'NONE',
                'batch_weighed' => $batchWeighed,
                'transport_accepted' => $transportAccepted,
                'ready_to_feed' => $ready
            ]
        ]);
    }

    /**
     * Start a feed operation.
     */
    public function startFeed(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|exists:production_batches,id'
        ]);

        $batchId = $request->input('batch_id');

        // Check if already feeding
        $existing = FeedOperation::where('batch_id', $batchId)->first();
        if ($existing) {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Quy trình cấp máy đã được khởi tạo.',
                'data' => $existing
            ]);
        }

        $op = FeedOperation::create([
            'batch_id' => $batchId,
            'operator_id' => Auth::id(),
            'started_at' => Carbon::now()
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã bắt đầu quy trình kiểm tra cấp máy nhuộm.',
            'data' => $op
        ], 201);
    }

    /**
     * Verify water settings.
     */
    public function verifyWater($id)
    {
        $op = FeedOperation::findOrFail($id);
        $op->water_verified = true;
        $op->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Cấu hình nước đã được kiểm tra đạt chuẩn.',
            'data' => $op
        ]);
    }

    /**
     * Verify materials by scanning QR ticket.
     */
    public function verifyMaterials(Request $request, $id)
    {
        $request->validate([
            'scan_data' => 'required|string'
        ]);

        $op = FeedOperation::with('batch')->findOrFail($id);
        $scanData = $request->input('scan_data');

        // Verify correct batch QR code
        if (!str_contains($scanData, $op->batch->legacy_batch_id)) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Quét sai mã nhãn tem! Mã QR không khớp với lô nhuộm hiện tại.'
            ], 400);
        }

        $op->materials_verified = true;
        $op->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Xác nhận nguyên liệu đã nạp đầy đủ và chính xác.',
            'data' => $op
        ]);
    }

    /**
     * Supervisor override.
     */
    public function override(Request $request, $id)
    {
        $request->validate([
            'override_reason' => 'required|string|min:5'
        ]);

        $user = Auth::user();
        
        // Require SUPERVISOR or ADMIN role
        if (!$user->hasRole('SUPERVISOR') && !$user->hasRole('ADMIN')) {
            return response()->json([
                'status' => 'FORBIDDEN',
                'message' => 'Chỉ Giám sát viên (Supervisor) hoặc Admin mới có quyền ký duyệt Override cấp máy.'
            ], 403);
        }

        $op = FeedOperation::findOrFail($id);
        $op->override_approved = true;
        $op->override_approved_by = $user->id;
        $op->override_reason = $request->input('override_reason');
        $op->save();

        // Write to audit log
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'FEED_OVERRIDE_APPROVED',
            'entity_type' => 'FeedOperation',
            'entity_id' => $op->id,
            'after_data' => [
                'batch_id' => $op->batch_id,
                'reason' => $op->override_reason
            ],
            'created_at' => Carbon::now()
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Ký duyệt Override thành công!',
            'data' => $op
        ]);
    }

    /**
     * Complete feed operation (opens chemical feeding valve).
     */
    public function completeFeed($id)
    {
        $op = FeedOperation::with('batch')->findOrFail($id);

        $materialsVerified = $op->materials_verified || in_array($op->batch->status, ['ARRIVED_AT_TANK', 'READY_TO_FEED', 'FED_TO_MACHINE', 'DONE']);
        $conditionsMet = ($op->water_verified && $materialsVerified) || $op->override_approved;

        if (!$conditionsMet) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Chưa đủ điều kiện cấp máy nhuộm! Vui lòng hoàn tất kiểm tra nước và nguyên liệu, hoặc yêu cầu Giám sát ký duyệt Override.'
            ], 400);
        }

        $now = Carbon::now();
        $op->completed_at = $now;
        $op->save();

        // Transition batch status to DONE
        $batch = $op->batch;
        $batch->status = 'DONE';
        $batch->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Van cấp hóa chất đã được mở. Cấp máy nhuộm hoàn tất!',
            'data' => $op
        ]);
    }
}
