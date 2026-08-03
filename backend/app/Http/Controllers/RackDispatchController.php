<?php
// backend/app/Http/Controllers/RackDispatchController.php

namespace App\Http\Controllers;

use App\Models\RackDispatchCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hàng đợi "SEND OVER 6" — port Mod_sendRackauto của workbook cân lớn.
 *
 * Trình duyệt KHÔNG được chạm ứng dụng pha màu (ADR-002), nên nó chỉ ghi lệnh vào đây;
 * Local Agent trên đúng máy trạm poll về rồi mô phỏng chuột + clipboard như VBA gốc.
 */
class RackDispatchController extends Controller
{
    /**
     * Trạm cân bấm OUT/IN -> tạo lệnh cho Agent.
     *
     * Idempotent theo `idempotency_key`: gọi lại cùng key (mạng chập chờn, thợ bấm hai
     * lần) chỉ trả về lệnh đã tạo, KHÔNG xếp thêm lệnh mới — bắn trùng vào hệ pha màu
     * nghĩa là cấp thừa vật tư.
     */
    public function store(Request $request)
    {
        $request->validate([
            'workstation_code' => 'required|string|max:50',
            'action' => 'required|string|in:OUT,IN',
            'racks' => 'array',
            'racks.*' => 'nullable|string|max:50',
            'idempotency_key' => 'required|string|max:191',
        ]);

        $command = DB::transaction(function () use ($request) {
            $existing = RackDispatchCommand::where('idempotency_key', $request->input('idempotency_key'))
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            // Ép đúng 6 phần tử, giữ nguyên thứ tự và ô trống: mỗi vị trí ứng với một ô
            // nhập cố định bên ứng dụng đích. Dồn mảng lại là dán lệch ô.
            $racks = array_slice(
                array_pad((array) $request->input('racks', []), RackDispatchCommand::RACK_SLOTS, ''),
                0,
                RackDispatchCommand::RACK_SLOTS
            );
            $racks = array_map(fn ($r) => trim((string) $r), $racks);

            return RackDispatchCommand::create([
                'workstation_code' => $request->input('workstation_code'),
                'action' => $request->input('action'),
                'racks' => $racks,
                'idempotency_key' => $request->input('idempotency_key'),
                'status' => RackDispatchCommand::STATUS_PENDING,
                'created_by_user_id' => optional(auth()->user())->id,
            ]);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $command,
        ], 201);
    }

    /**
     * Agent poll lệnh đang chờ của đúng máy trạm mình.
     *
     * Khoá dòng khi đổi sang SENT để hai tiến trình Agent (hoặc một Agent bị khởi động
     * hai lần) không cùng lấy được một lệnh rồi bắn hai lần vào ứng dụng pha màu.
     */
    public function pending(Request $request, string $workstationId)
    {
        $commands = DB::transaction(function () use ($workstationId) {
            $rows = RackDispatchCommand::where('workstation_code', $workstationId)
                ->where('status', RackDispatchCommand::STATUS_PENDING)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $row->status = RackDispatchCommand::STATUS_SENT;
                $row->picked_up_at = now();
                $row->save();
            }

            return $rows;
        });

        return response()->json($commands->map(fn ($c) => [
            'id' => $c->id,
            'action' => $c->action,
            'racks' => $c->racks,
        ])->values());
    }

    /**
     * Agent báo lại kết quả. Ack CẢ HAI chiều — lệnh thất bại phải để lại dấu vết
     * FAILED kèm lý do, không được im lặng rồi bị lấy lại và bắn lặp vô hạn (đúng bài
     * học đã ghi ở Worker.AcknowledgePrintJobAsync).
     */
    public function acknowledge(Request $request, string $workstationId, string $id)
    {
        $request->validate([
            'success' => 'required|boolean',
            'error_message' => 'nullable|string|max:1000',
        ]);

        $command = RackDispatchCommand::where('workstation_code', $workstationId)
            ->where('id', $id)
            ->first();

        if (!$command) {
            return response()->json(['status' => 'ERROR', 'message' => 'COMMAND_NOT_FOUND'], 404);
        }

        $command->status = $request->boolean('success')
            ? RackDispatchCommand::STATUS_DONE
            : RackDispatchCommand::STATUS_FAILED;
        $command->error_message = $request->input('error_message');
        $command->completed_at = now();
        $command->save();

        return response()->json(['status' => 'SUCCESS', 'data' => $command]);
    }
}
