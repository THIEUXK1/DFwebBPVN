<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Workstation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xác thực Local Agent (không phải người dùng) — dùng lại registration_token của
 * app.workstations (đã có sẵn cho handshake trình duyệt kiosk qua WorkstationGuard)
 * làm credential rút gọn cho Agent, thay vì bảng device_credentials riêng theo
 * local-agent-architecture.md Mục 4.2 (đề xuất đầy đủ — chưa xây: chưa có credential
 * theo từng thiết bị vật lý, chưa rotation, chưa HMAC ký payload).
 *
 * Header: X-Workstation-Token (cùng tên với WorkstationGuard để 1 token workstation
 * dùng chung được cho cả trình duyệt kiosk lẫn Local Agent chạy trên cùng máy).
 */
class AgentAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // Cùng quy ước với WorkstationGuard: cho qua trong môi trường test trừ khi
        // ép buộc bằng header, để không phá vỡ các test hiện có dựa vào hành vi cũ.
        if (app()->environment('testing') && !$request->hasHeader('X-Enforce-Workstation-Guard')) {
            return $next($request);
        }

        $token = $request->header('X-Workstation-Token');

        if (!$token) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Thiếu X-Workstation-Token — Local Agent phải xác thực bằng token workstation đã đăng ký.'
            ], 401);
        }

        $workstation = Workstation::where('registration_token_hash', hash('sha256', $token))->first();

        if (!$workstation) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Token workstation không hợp lệ.'
            ], 401);
        }

        if (!$workstation->active) {
            return response()->json([
                'status' => 'ERROR',
                'message' => "Máy trạm '{$workstation->code}' đã bị vô hiệu hóa."
            ], 403);
        }

        // Whitelist: workstation_id gửi trong route/body phải khớp đúng workstation
        // sở hữu token này — chống trường hợp token của trạm A bị lộ và dùng để ghi
        // dữ liệu giả mạo dưới workstation_id của trạm B.
        $claimedId = $request->route('workstation_id') ?? $request->input('workstation_id');
        if ($claimedId !== null && (string)$claimedId !== (string)$workstation->code && (string)$claimedId !== (string)$workstation->id) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'workstation_id không khớp với token đã xác thực.'
            ], 403);
        }

        $request->attributes->set('agent_workstation', $workstation);

        return $next($request);
    }
}
