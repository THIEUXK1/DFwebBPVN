<?php
// backend/app/Http/Controllers/ChemicalCallController.php

namespace App\Http\Controllers;

use App\Models\MachineChemicalChannel;
use App\Models\ChemicalCallRequest;
use App\Models\ChemicalCallRequestEvent;
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
                ->first();
                
            return [
                'channel_id' => $channel->id,
                'channel_number' => $channel->channel_number,
                'machine_code' => $channel->machine ? $channel->machine->code : null,
                'chemical_code' => $channel->chemical_code,
                'is_active' => $channel->is_active,
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

        return DB::transaction(function () use ($channelId, $idempotencyKey) {
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

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_ORDERED',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => 'CREATED',
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
            $ccRequest->status = 'DONE';
            $ccRequest->confirmed_at = now();
            $ccRequest->confirmed_by_user_id = auth()->id();
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_DONE',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $ccRequest->getOriginal('status'),
                'after_status' => 'DONE',
            ]);
        });

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
            $ccRequest->status = 'CANCELLED';
            $ccRequest->cancelled_at = now();
            $ccRequest->cancelled_reason = $request->input('reason');
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_CANCELLED',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $ccRequest->getOriginal('status'),
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
            $ccRequest->status = 'RESET';
            $ccRequest->save();

            ChemicalCallRequestEvent::create([
                'request_id' => $ccRequest->id,
                'event_type' => 'CHEMICAL_CALL_RESET',
                'occurred_at' => now(),
                'actor_user_id' => auth()->id(),
                'before_status' => $ccRequest->getOriginal('status'),
                'after_status' => 'RESET',
            ]);
        });

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
        $events = ChemicalCallRequestEvent::with(['request.machine', 'request.channel', 'actorUser', 'actorWorkstation'])
            ->orderBy('occurred_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($event) {
                return [
                    'time' => $event->occurred_at->format('H:i:s d/m'),
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
