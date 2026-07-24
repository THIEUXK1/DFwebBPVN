<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperationClient;
use App\Models\KioskSession;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KioskSessionController extends Controller
{
    /**
     * Establish a kiosk session using client code and kiosk token.
     */
    public function establishSession(Request $request)
    {
        $request->validate([
            'client_code' => 'required|string',
            'kiosk_token' => 'required|string',
            'browser_fingerprint' => 'nullable|string',
        ]);

        $clientCode = $request->input('client_code');
        $token = $request->input('kiosk_token');
        $tokenHash = hash('sha256', $token);

        $client = OperationClient::with(['capabilities', 'devices'])
            ->where('code', $clientCode)
            ->where('kiosk_token_hash', $tokenHash)
            ->first();

        if (!$client) {
            // Check backward compatibility: does it match registration token?
            $client = OperationClient::with(['capabilities', 'devices'])
                ->where('code', $clientCode)
                ->where('registration_token_hash', $tokenHash)
                ->first();
        }

        if (!$client) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã trạm hoặc token kích hoạt không hợp lệ.'
            ], 401);
        }

        if ($client->status !== 'ACTIVE') {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Trạm vận hành này đã bị vô hiệu hóa.'
            ], 403);
        }

        if ($client->kiosk_token_expires_at && $client->kiosk_token_expires_at->isPast()) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token kích hoạt trạm đã hết hạn. Vui lòng liên hệ Admin để tạo lại.'
            ], 403);
        }

        if (!$client->kiosk_token_active) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token kích hoạt trạm đã bị vô hiệu hóa.'
            ], 403);
        }

        // Generate dynamic kiosk session token
        $sessionToken = 'KS-' . Str::random(50);
        $expiresAt = now()->addHours(2); // 2 hours sliding window

        $session = KioskSession::create([
            'operation_client_id' => $client->id,
            'token' => $sessionToken,
            'started_at' => now(),
            'last_activity_at' => now(),
            'expires_at' => $expiresAt,
            'status' => 'ACTIVE',
            'remote_ip' => $request->ip(),
            'browser_fingerprint' => $request->input('browser_fingerprint'),
        ]);

        // Audit Log
        AuditLog::create([
            'actor_type' => 'KIOSK_CLIENT',
            'actor_id' => (string)$client->id,
            'kiosk_session_id' => $session->id,
            'action' => 'KIOSK_LOGIN',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'after_data' => [
                'code' => $client->code,
                'session_id' => $session->id,
                'ip' => $request->ip()
            ],
            'client_ip' => $request->ip()
        ]);

        // Resolve capabilities and devices structured
        $capabilitiesList = $client->capabilities->map(function ($cap) {
            return [
                'code' => $cap->code,
                'name' => $cap->name,
                'category' => $cap->category,
                'enabled' => $cap->pivot->enabled,
                'configuration' => $cap->pivot->configuration_json,
            ];
        })->toArray();

        $devicesList = $client->devices->map(function ($dev) {
            return [
                'id' => $dev->id,
                'code' => $dev->code,
                'device_type' => $dev->device_type,
                'driver_protocol' => $dev->driver_protocol,
                'status' => $dev->status,
                'role' => $dev->pivot->device_role,
                'is_default' => $dev->pivot->is_default,
                'priority' => $dev->pivot->priority,
                'enabled' => $dev->pivot->enabled,
                'configuration' => $dev->configuration,
            ];
        })->toArray();

        return response()->json([
            'status' => 'SUCCESS',
            'session_token' => $sessionToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'client' => [
                'id' => $client->id,
                'code' => $client->code,
                'name' => $client->name,
                'location' => $client->location,
                'status' => $client->status,
                'default_capability' => $client->default_capability,
                'default_route' => $client->default_route,
                'capabilities' => $capabilitiesList,
                'devices' => $devicesList,
            ]
        ]);
    }

    /**
     * Verify manager PIN for override or sensitive actions.
     */
    public function verifyManagerPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string',
        ]);

        $user = User::verifyManagerPin($request->input('pin'));

        if (!$user) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Mã PIN giám sát không đúng hoặc tài khoản không có quyền.'
            ], 401);
        }

        return response()->json([
            'status' => 'SUCCESS',
            'manager' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
            ]
        ]);
    }
}
