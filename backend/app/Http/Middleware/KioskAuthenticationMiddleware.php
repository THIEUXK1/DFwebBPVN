<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\KioskSession;
use Symfony\Component\HttpFoundation\Response;

class KioskAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If already authenticated via Sanctum, proceed
        if (auth()->guard('sanctum')->check()) {
            auth()->shouldUse('sanctum');
            return $next($request);
        }

        // 2. Otherwise, check for Kiosk Session Token
        $token = $request->header('X-Kiosk-Session-Token') ?? $request->bearerToken();

        if ($token) {
            $session = KioskSession::with('client.capabilities', 'client.devices')
                ->where('token', $token)
                ->where('status', 'ACTIVE')
                ->where('expires_at', '>', now())
                ->first();

            if ($session && $session->client) {
                if ($session->client->status !== 'ACTIVE') {
                    return response()->json([
                        'status' => 'ERROR',
                        'message' => 'Máy trạm vận hành này đã bị vô hiệu hóa.'
                    ], 403);
                }

                // Update session heartbeat
                $session->last_activity_at = now();
                // Slide expiration window forward (e.g. 2 hours from now)
                $session->expires_at = now()->addHours(2);
                $session->save();

                // Bind attributes to the request context
                $request->attributes->set('kiosk_session', $session);
                $request->attributes->set('operation_client', $session->client);

                return $next($request);
            }
        }

        return response()->json([
            'status' => 'UNAUTHORIZED',
            'message' => 'Yêu cầu không hợp lệ. Vui lòng đăng nhập hoặc sử dụng link kiosk trạm vận hành.'
        ], 401);
    }
}
