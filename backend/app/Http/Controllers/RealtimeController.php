<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RealtimeEvent;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Cache;

class RealtimeController extends Controller
{
    /**
     * Stream Server-Sent Events (SSE) to the client.
     */
    public function stream(Request $request)
    {
        // 1. Manually authenticate the token from query param (since EventSource doesn't support custom headers easily)
        $tokenStr = $request->query('token');
        $authenticated = false;

        if ($tokenStr) {
            if (str_contains($tokenStr, '|')) {
                [$id, $plain] = explode('|', $tokenStr, 2);
                $tokenModel = PersonalAccessToken::find($id);
                if ($tokenModel && hash_equals($tokenModel->token, hash('sha256', $plain))) {
                    $authenticated = true;
                }
            } else {
                // Check direct hash
                $tokenModel = PersonalAccessToken::where('token', hash('sha256', $tokenStr))->first();
                if ($tokenModel) {
                    $authenticated = true;
                }
            }
        }

        if (!$authenticated && !auth()->guard('sanctum')->check()) {
            return response()->json(['message' => 'Unauthorized realtime connection'], 401);
        }

        // 2. Prepare headers for Event Stream
        return response()->stream(function () use ($request) {
            // Disable PHP execution limits
            if (function_exists('set_time_limit')) {
                set_time_limit(0);
            }
            
            $lastEventId = (int)$request->header('Last-Event-ID', $request->query('last_event_id', 0));
            $heartbeatCounter = 0;

            while (true) {
                // Check if browser closed connection
                if (connection_aborted()) {
                    break;
                }

                // Query for new outbox events
                $events = RealtimeEvent::where('id', '>', $lastEventId)
                    ->orderBy('id', 'asc')
                    ->limit(20)
                    ->get();

                if ($events->isNotEmpty()) {
                    foreach ($events as $event) {
                        echo "id: " . $event->id . "\n";
                        echo "event: message\n";
                        echo "data: " . json_encode($event) . "\n\n";
                        $lastEventId = $event->id;
                    }
                    $heartbeatCounter = 0;
                }

                // Stream live scale weight readings (live telemetry streaming from Cache)
                $scaleData = [
                    'WS-01' => [
                        'weight' => (float)Cache::get('scale_live_weight_WS-01', 0.0),
                        'active' => Cache::has('scale_live_weight_WS-01')
                    ],
                    'WS-02' => [
                        'weight' => (float)Cache::get('scale_live_weight_WS-02', 0.0),
                        'active' => Cache::has('scale_live_weight_WS-02')
                    ]
                ];
                
                echo "event: scale_heartbeat\n";
                echo "data: " . json_encode($scaleData) . "\n\n";

                // Emit regular heartbeat to keep browser connection alive
                $heartbeatCounter++;
                if ($heartbeatCounter >= 5) {
                    echo ": heartbeat\n\n";
                    $heartbeatCounter = 0;
                }

                // Flush headers & content buffers to client
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(1);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // For Nginx support
        ]);
    }
}
