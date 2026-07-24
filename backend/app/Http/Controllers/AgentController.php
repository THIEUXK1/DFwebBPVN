<?php
// backend/app/Http/Controllers/AgentController.php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\DeviceHeartbeat;
use App\Models\DeviceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AgentController extends Controller
{
    /**
     * Registers a physical device.
     */
    public function register(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:devices,code',
            'device_type' => 'required|string|in:SCALE,PRINTER,SCANNER,LOCAL_AGENT',
            'driver_protocol' => 'sometimes|string',
            'agent_version' => 'sometimes|string',
            'configuration' => 'sometimes|array',
        ]);

        $device = Device::create([
            'id' => (string) Str::uuid(),
            'code' => $request->input('code'),
            'device_type' => $request->input('device_type'),
            'driver_protocol' => $request->input('driver_protocol'),
            'agent_version' => $request->input('agent_version'),
            'configuration' => $request->input('configuration', []),
            'status' => 'ACTIVE',
            'is_enabled' => true,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'device_id' => $device->id,
            'code' => $device->code,
        ], 201);
    }

    /**
     * Records a device heartbeat.
     */
    public function heartbeat(Request $request, $deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $request->validate([
            'status' => 'required|string|in:OK,WARNING,ERROR,OFFLINE',
            'agent_timestamp' => 'sometimes|string',
            'payload' => 'sometimes|array',
        ]);

        $agentTime = $request->input('agent_timestamp') ? Carbon::parse($request->input('agent_timestamp')) : null;

        DB::transaction(function () use ($device, $request, $agentTime) {
            $device->last_heartbeat_at = now();
            $device->status = $request->input('status');
            $device->save();

            DeviceHeartbeat::create([
                'device_id' => $device->id,
                'received_at' => now(),
                'agent_timestamp' => $agentTime,
                'status' => $device->status,
                'payload' => $request->input('payload', []),
            ]);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Heartbeat received'
        ]);
    }

    /**
     * Reports a device hardware event (e.g. printer error, paper jam).
     */
    public function logEvent(Request $request, $deviceId)
    {
        $device = Device::findOrFail($deviceId);

        $request->validate([
            'event_type' => 'required|string',
            'occurred_at' => 'sometimes|string',
            'detail' => 'sometimes|array',
        ]);

        $occurredAt = $request->input('occurred_at') ? Carbon::parse($request->input('occurred_at')) : now();

        $event = DeviceEvent::create([
            'device_id' => $device->id,
            'event_type' => $request->input('event_type'),
            'occurred_at' => $occurredAt,
            'detail' => $request->input('detail', []),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'event_id' => $event->id
        ], 201);
    }

}
