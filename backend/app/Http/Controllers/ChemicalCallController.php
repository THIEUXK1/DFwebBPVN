<?php
// backend/app/Http/Controllers/ChemicalCallController.php

namespace App\Http\Controllers;

use App\Models\MachineChemicalChannel;
use App\Models\ChemicalCallRequest;
use App\Models\ChemicalCallRequestEvent;
use App\Models\ChemicalFormulaGroup;
use App\Events\ChemicalChannelUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ChemicalCallController extends Controller
{
    /**
     * Get list of chemical channels with their current active request.
     */
    public function getChannels(Request $request)
    {
        $channels = MachineChemicalChannel::with('machine')->get()->map(function ($channel) {
            $currentRequest = ChemicalCallRequest::where('channel_id', $channel->id)
                ->whereIn('status', ['CREATED', 'ORDERED', 'ACKNOWLEDGED', 'DONE'])
                ->orderByDesc('requested_at')
                ->first();
                
            $formulaGroup = ChemicalFormulaGroup::lookupByCombinedCode($channel->chemical_code);

            return [
                'channel_id' => $channel->id,
                'channel_number' => $channel->channel_number,
                'machine_code' => $channel->machine ? $channel->machine->code : null,
                'chemical_code' => $channel->chemical_code,
                'is_active' => $channel->is_active,
                // Công thức hiện đang active trên thùng này (tra từ chemical_code) — null
                // nếu combo mã chưa có trong chemical_formula_groups (công thức mới/chưa
                // xác nhận qua ảnh QR thật).
                'formula_qr_text' => $formulaGroup ? $formulaGroup->buildQrText() : null,
                'current_request' => $currentRequest ? [
                    'id' => $currentRequest->id,
                    'status' => $currentRequest->status,
                    'requested_at' => $currentRequest->requested_at,
                ] : null,
            ];
        });

        return response()->json($channels);
    }

    /**
     * Thêm 1 kênh phát hóa chất mới cho 1 máy — vd máy vừa lắp thêm van hóa chất mới,
     * hoặc máy mới chưa có kênh nào. channel_number không được trùng trong cùng 1 máy.
     */
    public function storeChannel(Request $request)
    {
        $request->validate([
            'machine_id' => 'required|exists:machines,id',
            'channel_number' => 'required|integer|min:1',
            'chemical_code' => 'required|string|max:100',
        ]);

        $exists = MachineChemicalChannel::where('machine_id', $request->input('machine_id'))
            ->where('channel_number', $request->input('channel_number'))
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'CHANNEL_NUMBER_ALREADY_EXISTS',
                'message' => 'Máy này đã có kênh số ' . $request->input('channel_number') . ' rồi.',
            ], 409);
        }

        $channel = MachineChemicalChannel::create([
            'machine_id' => $request->input('machine_id'),
            'channel_number' => $request->input('channel_number'),
            'chemical_code' => $request->input('chemical_code'),
            'is_active' => true,
        ]);

        event(new ChemicalChannelUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $channel->load('machine'),
        ], 201);
    }

    /**
     * Sửa kênh đã có — đổi mã hóa chất và/hoặc số kênh. Giữ nguyên máy (machine_id)
     * vì đổi máy thực chất là tạo kênh khác, nên chỉ cho sửa 2 trường này.
     */
    public function updateChannel(Request $request, $id)
    {
        $channel = MachineChemicalChannel::findOrFail($id);

        $request->validate([
            'channel_number' => 'required|integer|min:1',
            'chemical_code' => 'required|string|max:100',
        ]);

        $exists = MachineChemicalChannel::where('machine_id', $channel->machine_id)
            ->where('channel_number', $request->input('channel_number'))
            ->where('id', '!=', $channel->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => 'CHANNEL_NUMBER_ALREADY_EXISTS',
                'message' => 'Máy này đã có kênh số ' . $request->input('channel_number') . ' rồi.',
            ], 409);
        }

        $channel->update([
            'channel_number' => $request->input('channel_number'),
            'chemical_code' => $request->input('chemical_code'),
        ]);

        event(new ChemicalChannelUpdated());

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $channel->fresh()->load('machine'),
        ]);
    }

    /**
     * Create a new chemical call request.
     */
    public function createRequest(Request $request)
    {
        $request->validate([
            'channel_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!DB::table('machine_chemical_channels')->where('id', $value)->exists()) {
                        $fail('The selected channel_id is invalid.');
                    }
                }
            ],
            'idempotency_key' => 'required|string',
        ]);

        $channelId = $request->input('channel_id');
        $idempotencyKey = $request->input('idempotency_key');

        $result = DB::transaction(function () use ($channelId, $idempotencyKey) {
            // Check idempotency
            $existing = ChemicalCallRequest::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return response()->json([
                    'id' => $existing->id,
                    'status' => $existing->status,
                    'requested_at' => $existing->requested_at,
                    'reused' => true,
                ], 200);
            }

            // Check if channel is active
            $channel = MachineChemicalChannel::findOrFail($channelId);
            if (!$channel->is_active) {
                return response()->json(['error' => 'CHANNEL_INACTIVE'], 403);
            }

            // Check if there is an active request on the same channel
            $activeRequest = ChemicalCallRequest::where('channel_id', $channelId)
                ->whereIn('status', ['CREATED', 'ORDERED', 'ACKNOWLEDGED'])
                ->first();
            
            if ($activeRequest) {
                return response()->json(['error' => 'CHANNEL_ALREADY_ORDERED'], 409);
            }

            // Kiểm tra ở trên (dòng 80-86) không có lockForUpdate — 2 request đồng thời cho
            // cùng channel_id vẫn có thể cùng vượt qua. Dữ liệu được bảo vệ TUYỆT ĐỐI bởi
            // partial unique index `uq_channel_active_order` ở tầng DB (migration Wave 2,
            // dòng 64) nên KHÔNG thể tạo 2 request active cùng lúc — nhưng nếu không bắt lỗi
            // vi phạm constraint này, request thua cuộc sẽ nhận 500 thay vì 409 rõ ràng. Bắt
            // lỗi 23505 (unique_violation) và trả đúng mã lỗi nghiệp vụ — phát hiện khi review
            // code Phase E, 2026-07-17 (cùng loại race condition đã sửa ở ConfirmDispatchService).
            try {
                $ccRequest = ChemicalCallRequest::create([
                    'channel_id' => $channelId,
                    'machine_id' => $channel->machine_id,
                    'status' => 'ORDERED',
                    'requested_at' => now(),
                    'requested_by_user_id' => auth()->id(),
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                if ((string) $e->getCode() === '23505') {
                    return response()->json(['error' => 'CHANNEL_ALREADY_ORDERED'], 409);
                }
                throw $e;
            }

            // before_status ghi 'RESET' (nhóm OK) thay vì 'CREATED' (nhóm CHƯA OK) — về mặt
            // nghiệp vụ, request mới chỉ được tạo khi kênh đang ở trạng thái OK (nút xanh),
            // 'CREATED' chỉ là giá trị default của cột status ở tầng schema, không phản ánh
            // đúng trạng thái kênh trước đó nên hiển thị sai thành "CHƯA OK -> CHƯA OK".
            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_ORDERED',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => 'RESET',
                'after_status' => 'ORDERED',
            ]);

            if (request()->header('X-Remote-Operation') === 'true') {
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'REMOTE_CALL_CHEMICAL',
                    'entity_type' => 'ChemicalCallRequest',
                    'entity_id' => $ccRequest->id,
                    'client_ip' => request()->ip(),
                    'after_data' => [
                        'target_workstation_id' => request()->header('X-Target-Workstation'),
                        'remote' => true,
                        'reason' => request()->header('X-Remote-Reason') ?? 'Admin remote chemical call'
                    ]
                ]);
            }

            return response()->json([
                'id' => $ccRequest->id,
                'status' => $ccRequest->status,
                'requested_at' => $ccRequest->requested_at,
                'reused' => false,
            ], 201);
        });

        event(new ChemicalChannelUpdated());

        return $result;
    }

    /**
     * Acknowledge the chemical call request (Shift Leader / Technologist).
     */
    public function acknowledge(string $id)
    {
        $ccRequest = ChemicalCallRequest::findOrFail($id);

        if ($ccRequest->status !== 'ORDERED') {
            return response()->json(['error' => 'INVALID_STATE_TRANSITION'], 409);
        }

        DB::transaction(function () use ($ccRequest) {
            $ccRequest->status = 'ACKNOWLEDGED';
            $ccRequest->acknowledged_at = now();
            $ccRequest->acknowledged_by_user_id = auth()->id();
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_ACKNOWLEDGED',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => 'ORDERED',
                'after_status' => 'ACKNOWLEDGED',
            ]);
        });

        event(new ChemicalChannelUpdated());

        return response()->json(['id' => $ccRequest->id, 'status' => $ccRequest->status]);
    }

    /**
     * Complete the chemical call request.
     */
    public function complete(string $id)
    {
        $ccRequest = ChemicalCallRequest::findOrFail($id);

        if (!in_array($ccRequest->status, ['ORDERED', 'ACKNOWLEDGED'])) {
            return response()->json(['error' => 'INVALID_STATE_TRANSITION'], 409);
        }

        DB::transaction(function () use ($ccRequest) {
            $previousStatus = $ccRequest->status;
            $ccRequest->status = 'DONE';
            $ccRequest->confirmed_at = now();
            $ccRequest->confirmed_by_user_id = auth()->id();
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_DONE',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $previousStatus,
                'after_status' => 'DONE',
            ]);
        });

        event(new ChemicalChannelUpdated());

        return response()->json(['id' => $ccRequest->id, 'status' => $ccRequest->status]);
    }

    /**
     * Cancel the request.
     */
    public function cancel(Request $request, string $id)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $ccRequest = ChemicalCallRequest::findOrFail($id);

        if (!in_array($ccRequest->status, ['ORDERED', 'ACKNOWLEDGED'])) {
            return response()->json(['error' => 'INVALID_STATE_TRANSITION'], 409);
        }

        DB::transaction(function () use ($ccRequest, $request) {
            $previousStatus = $ccRequest->status;
            $ccRequest->status = 'CANCELLED';
            $ccRequest->cancelled_at = now();
            $ccRequest->cancelled_reason = $request->input('reason');
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_CANCELLED',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $previousStatus,
                'after_status' => 'CANCELLED',
                'note' => $request->input('reason'),
            ]);

            if (request()->header('X-Remote-Operation') === 'true') {
                \App\Models\AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'REMOTE_CANCEL_CHEMICAL',
                    'entity_type' => 'ChemicalCallRequest',
                    'entity_id' => $ccRequest->id,
                    'client_ip' => request()->ip(),
                    'after_data' => [
                        'target_workstation_id' => request()->header('X-Target-Workstation'),
                        'remote' => true,
                        'reason' => request()->header('X-Remote-Reason') ?? $request->input('reason')
                    ]
                ]);
            }
        });

        event(new ChemicalChannelUpdated());

        return response()->json(['id' => $ccRequest->id, 'status' => $ccRequest->status]);
    }

    /**
     * Reset the request.
     */
    public function reset(string $id)
    {
        $ccRequest = ChemicalCallRequest::findOrFail($id);

        if (!in_array($ccRequest->status, ['DONE', 'CANCELLED'])) {
            return response()->json(['error' => 'INVALID_STATE_TRANSITION'], 409);
        }

        DB::transaction(function () use ($ccRequest) {
            $previousStatus = $ccRequest->status;
            $ccRequest->status = 'RESET';
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_RESET',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $previousStatus,
                'after_status' => 'RESET',
            ]);
        });

        event(new ChemicalChannelUpdated());

        return response()->json(['id' => $ccRequest->id, 'status' => $ccRequest->status]);
    }

    /**
     * Get event history for a request.
     */
    public function getEvents(string $id)
    {
        $events = ChemicalCallRequestEvent::where('request_id', $id)
            ->orderBy('occurred_at', 'asc')
            ->get();
            
        return response()->json($events);
    }

    /**
     * Get recent events for all chemical calls.
     */
    public function getRecentEvents(Request $request)
    {
        // CHEMICAL_CALL_RESET là bước dọn dẹp nội bộ (DONE -> RESET) để mở lại kênh cho
        // lượt gọi mới, không phải sự kiện nghiệp vụ có ý nghĩa với người vận hành — luôn
        // đi kèm ngay sau CHEMICAL_CALL_DONE nên hiển thị ra sẽ trùng lặp và lệch nhãn
        // trạng thái (DONE và RESET cùng hiển thị "OK" ở getSimpleStatus() phía frontend,
        // khiến dòng RESET hiện thành "OK -> OK" vô nghĩa). Lọc bỏ khỏi nhật ký hoạt động.
        $events = ChemicalCallRequestEvent::with(['request.machine', 'request.channel', 'actorUser', 'actorWorkstation'])
            ->where('event_type', '!=', 'CHEMICAL_CALL_RESET')
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                return [
                    // app.timezone = UTC (lưu trữ) nhưng vận hành ở Việt Nam (UTC+7) — phải quy
                    // đổi trước khi format chuỗi hiển thị, nếu không giờ trên nhật ký sẽ lệch 7
                    // tiếng so với giờ thực tế thao tác (vd 13:44 thực tế hiển thị thành 06:44).
                    'time' => $event->occurred_at->clone()->timezone('Asia/Ho_Chi_Minh')->format('H:i:s d/m'),
                    'machine_code' => $event->request?->machine?->code,
                    'channel_number' => $event->request?->channel?->channel_number,
                    'chemical_code' => $event->request?->channel?->chemical_code,
                    'before_status' => $event->before_status,
                    'after_status' => $event->after_status,
                    'actor_username' => $event->actorUser?->username ?? 'Hệ thống',
                    'workstation_code' => $event->actorWorkstation?->code ?? 'WS-CHEMICAL-01',
                    'message' => $event->note ?? "Chuyển trạng thái từ {$event->before_status} sang {$event->after_status}",
                    'type' => $event->after_status === 'CANCELLED' ? 'warn' : ($event->after_status === 'DONE' ? 'success' : 'info')
                ];
            });

        return response()->json($events);
    }
}
