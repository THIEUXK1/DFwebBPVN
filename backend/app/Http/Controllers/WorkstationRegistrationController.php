<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workstation;
use App\Models\WorkstationAllowedAction;
use App\Models\WorkstationRoleAssignment;
use App\Models\WorkstationNetworkHistory;
use App\Models\DeviceAssignment;
use App\Models\AuditLog;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WorkstationRegistrationController extends Controller
{
    /**
     * Admin: Register a new workstation and generate a registration token.
     */
    public function register(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:operation_clients,code',
            'name' => 'required|string|max:200',
            'workstation_type' => 'required|string|max:50',
            'location' => 'nullable|string|max:100',
            'default_route' => 'nullable|string|max:100',
            'registered_ip' => 'nullable|ip',
            'mac_address' => 'nullable|string|max:50',
        ]);

        $token = 'WS-' . Str::upper(Str::random(12)) . '-' . Str::upper(Str::random(12));

        $workstation = DB::transaction(function () use ($request, $token) {
            $defaultRoute = $request->input('default_route') ?? $this->getDefaultRouteForType($request->workstation_type);
            
            $workstation = Workstation::create([
                'code' => $request->code,
                'name' => $request->name,
                'workstation_type' => $request->workstation_type,
                'type' => $request->workstation_type, // backward compatibility
                'location' => $request->location ?? 'Khu vực sản xuất',
                'default_route' => $defaultRoute,
                'default_screen' => $defaultRoute, // backward compatibility
                'active' => true,
                'locked_to_type' => false,
                'registered_ip' => $request->input('registered_ip'),
                'configuration' => [
                    'mac_address' => $request->input('mac_address')
                ],
                'registration_token_hash' => hash('sha256', $token),
            ]);

            // Seed default capabilities for backward compatibility
            $mappedCap = null;
            $type = $request->workstation_type;
            if (in_array($type, ['DYE_WEIGHING', 'CHEMICAL_WEIGHING', 'A11_WEIGHING', 'DLG_WEIGHING', 'SMALL_SCALE'])) {
                $mappedCap = 'SMALL_SCALE';
            } elseif ($type === 'LABEL_PRINTING' || $type === 'PRINT_STATION' || $type === 'QR_LABEL_PRINTING') {
                $mappedCap = 'QR_LABEL_PRINTING';
            } elseif ($type === 'ORDER_SCAN' || $type === 'ORDER_DESK' || $type === 'PRODUCTION_ORDER') {
                $mappedCap = 'PRODUCTION_ORDER';
            } elseif ($type === 'TANK_RECEIVING' || $type === 'MACHINE_FEEDING' || $type === 'CHEMICAL_CALL') {
                $mappedCap = 'CHEMICAL_CALL';
            } elseif ($type === 'LARGE_SCALE') {
                $mappedCap = 'LARGE_SCALE';
            }

            if ($mappedCap) {
                $workstation->default_capability = $mappedCap;
                $workstation->save();

                $cap = \App\Models\Capability::where('code', $mappedCap)->first();
                if ($cap) {
                    $workstation->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);
                }
                
                $deviceCaps = ['SCAN_QR', 'LOCAL_AGENT'];
                if (in_array($mappedCap, ['SMALL_SCALE', 'LARGE_SCALE'])) {
                    $deviceCaps[] = 'WEIGH';
                    $deviceCaps[] = 'PRINT';
                } elseif ($mappedCap === 'QR_LABEL_PRINTING') {
                    $deviceCaps[] = 'PRINT';
                }
                foreach ($deviceCaps as $cc) {
                    $c = \App\Models\Capability::where('code', $cc)->first();
                    if ($c) {
                        $workstation->capabilities()->syncWithoutDetaching([$c->id => ['enabled' => true]]);
                    }
                }
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'REGISTER_WORKSTATION',
                'entity_type' => 'Workstation',
                'entity_id' => $workstation->id,
                'after_data' => [
                    'code' => $workstation->code,
                    'workstation_type' => $workstation->workstation_type,
                ],
                'client_ip' => $request->ip(),
            ]);

            return $workstation;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'token' => $token,
            'data' => $workstation
        ], 201);
    }

    /**
     * Kiosk Client: Handshake using the registration token.
     */
    public function handshake(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'hostname' => 'nullable|string|max:100',
            'mac_address' => 'nullable|string|max:50',
        ]);

        $tokenHash = hash('sha256', $request->token);
        $workstation = Workstation::where('registration_token_hash', $tokenHash)->first();

        if (!$workstation) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Registration token không hợp lệ hoặc trạm chưa được đăng ký.'
            ], 401);
        }

        if (!$workstation->active) {
            return response()->json([
                'status' => 'ERROR',
                'message' => 'Trạm làm việc này đã bị vô hiệu hóa.'
            ], 403);
        }

        $ip = $request->ip();
        $oldIp = $workstation->last_ip;

        DB::transaction(function () use ($workstation, $request, $ip) {
            $workstation->last_ip = $ip;
            if (!$workstation->registered_ip) {
                $workstation->registered_ip = $ip;
            }
            $workstation->hostname = $request->hostname;
            $workstation->last_seen_at = now();

            $config = $workstation->configuration ?? [];
            $config['mac_address'] = $request->mac_address;
            $workstation->configuration = $config;
            $workstation->save();

            // Update client details

            // Audit change of IP if it changed
            AuditLog::create([
                'action' => 'WORKSTATION_HANDSHAKE',
                'entity_type' => 'Workstation',
                'entity_id' => $workstation->id,
                'after_data' => [
                    'code' => $workstation->code,
                    'ip' => $ip,
                    'hostname' => $request->hostname,
                    'mac' => $request->mac_address
                ],
                'client_ip' => $ip
            ]);
        });

        $allowed_actions = $workstation->allowed_actions;
        if (empty($allowed_actions)) {
            $allowed_actions = $this->getDefaultActionsForType($workstation->workstation_type ?? '');
        }

        return response()->json([
            'status' => 'SUCCESS',
            'workstation' => [
                'id' => $workstation->id,
                'code' => $workstation->code,
                'name' => $workstation->name,
                'workstation_type' => $workstation->workstation_type,
                'location' => $workstation->location,
                'locked_to_type' => $workstation->locked_to_type,
                'default_route' => $workstation->default_route,
                'allowed_actions' => $allowed_actions,
                'assigned_scale_device_id' => $workstation->assigned_scale_device_id,
                'assigned_printer_device_id' => $workstation->assigned_printer_device_id,
            ]
        ]);
    }

    /**
     * Admin: Save detailed config for a workstation.
     */
    public function updateConfig(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:200',
            'workstation_type' => 'required|string|max:50',
            'default_route' => 'required|string|max:100',
            'locked_to_type' => 'required|boolean',
            'location' => 'nullable|string|max:100',
            'allowed_actions' => 'array',
            'allowed_roles' => 'array',
            'devices' => 'array', // array of ['device_id' => '...', 'assignment_type' => '...']
        ]);

        DB::transaction(function () use ($workstation, $request) {
            $oldData = $workstation->toArray();

            $workstation->name = $request->name;
            $workstation->workstation_type = $request->workstation_type;
            $workstation->type = $request->workstation_type; // backward compatibility
            $workstation->default_route = $request->default_route;
            $workstation->default_screen = $request->default_route; // backward compatibility
            $workstation->locked_to_type = $request->locked_to_type;
            $workstation->location = $request->location;
            
            // Map devices to scale/printer properties for backward compatibility
            if ($request->has('devices')) {
                foreach ($request->devices as $dev) {
                    if ($dev['assignment_type'] === 'SCALE') {
                        $workstation->assigned_scale_device_id = $dev['device_id'];
                    }
                    if ($dev['assignment_type'] === 'PRINTER') {
                        $workstation->assigned_printer_device_id = $dev['device_id'];
                    }
                }
            }
            $workstation->save();

            // Sync Allowed Roles (Allowed Actions are now dynamic based on capabilities)

            // Sync Allowed Roles
            $workstation->roleAssignments()->delete();
            if ($request->has('allowed_roles')) {
                foreach ($request->allowed_roles as $roleCode) {
                    $role = Role::where('code', $roleCode)->first();
                    if ($role) {
                        WorkstationRoleAssignment::create([
                            'workstation_id' => $workstation->id,
                            'role_id' => $role->id
                        ]);
                    }
                }
            }

            // Sync Devices
            DeviceAssignment::where('workstation_id', $workstation->id)->delete();
            if ($request->has('devices')) {
                foreach ($request->devices as $dev) {
                    DeviceAssignment::create([
                        'workstation_id' => $workstation->id,
                        'device_id' => $dev['device_id'],
                        'assignment_type' => $dev['assignment_type'],
                        'active' => true,
                        'assigned_at' => now(),
                    ]);
                }
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'UPDATE_WORKSTATION_CONFIG',
                'entity_type' => 'Workstation',
                'entity_id' => $workstation->id,
                'before_data' => $oldData,
                'after_data' => $workstation->fresh()->toArray(),
                'client_ip' => $request->ip(),
            ]);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Cập nhật cấu hình trạm thành công.',
            'data' => $workstation->load(['allowedActionItems', 'roleAssignments.role', 'deviceAssignments'])
        ]);
    }

    /**
     * Admin: Test Connection to a device.
     */
    public function testConnection(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);
        $request->validate([
            'device_id' => 'required|string',
            'assignment_type' => 'required|string|in:SCALE,PRINTER,SCANNER',
        ]);

        // Simulating the device connection check
        // In real pilot, this would send a WebSocket command to the Agent
        $success = true; // Assume success for simulation

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'TEST_DEVICE_CONNECTION',
            'entity_type' => 'Workstation',
            'entity_id' => $workstation->id,
            'after_data' => [
                'device_id' => $request->device_id,
                'assignment_type' => $request->assignment_type,
                'status' => $success ? 'ONLINE' : 'OFFLINE'
            ],
            'client_ip' => $request->ip()
        ]);

        return response()->json([
            'status' => $success ? 'SUCCESS' : 'ERROR',
            'message' => $success ? "Thiết bị '{$request->device_id}' phản hồi tốt (ONLINE)." : "Không thể kết nối đến thiết bị '{$request->device_id}'."
        ]);
    }

    /**
     * Get default route for workstation type.
     */
    private function getDefaultRouteForType(string $type): string
    {
        $routes = [
            'ORDER_SCAN' => '/order-scan',
            'DYE_WEIGHING' => '/weighing-station',
            'CHEMICAL_WEIGHING' => '/weighing-station',
            'A11_WEIGHING' => '/weighing-station',
            'DLG_WEIGHING' => '/weighing-station',
            'LABEL_PRINTING' => '/print-station',
            'MATERIAL_TRANSFER' => '/material-transports',
            'TANK_RECEIVING' => '/feeding-monitor',
            'MACHINE_FEEDING' => '/feeding-monitor',
            'MONITORING' => '/'
        ];

        return $routes[$type] ?? '/';
    }

    /**
     * Get default actions for workstation type.
     */
    private function getDefaultActionsForType(string $type): array
    {
        $actions = [
            'ORDER_SCAN' => ['SCAN_ORDER'],
            'DYE_WEIGHING' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
            'CHEMICAL_WEIGHING' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
            'A11_WEIGHING' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
            'DLG_WEIGHING' => ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL'],
            'LABEL_PRINTING' => ['SCAN_LABEL', 'REPRINT_LABEL'],
            'MATERIAL_TRANSFER' => ['SCAN_LABEL', 'CONFIRM_TRANSIT'],
            'TANK_RECEIVING' => ['SCAN_DUAL_VERIFY', 'CONFIRM_ARRIVAL'],
            'MACHINE_FEEDING' => ['VERIFY_READINESS', 'CONFIRM_FEED', 'OVERRIDE_FEED'],
            'MONITORING' => ['VIEW_DASHBOARD']
        ];

        return $actions[$type] ?? [];
    }

    /**
     * Helper to get network segment from IP.
     */
    private function getNetworkSegment(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0/24';
        }
        return 'unknown';
    }

    /**
     * Workstation Heartbeat ping.
     */
    public function heartbeat(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);

        $request->validate([
            'agent_version' => 'nullable|string|max:50',
            'active_errors' => 'nullable|array',
        ]);

        $workstation->last_heartbeat = now();
        $workstation->last_seen_at = now();
        if ($request->has('agent_version')) {
            $workstation->agent_version = $request->input('agent_version');
        }
        if ($request->has('active_errors')) {
            $workstation->active_errors = $request->input('active_errors');
        }
        $workstation->save();

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Heartbeat updated successfully.',
            'data' => [
                'id' => $workstation->id,
                'last_heartbeat' => $workstation->last_heartbeat,
                'suspended' => $workstation->suspended,
            ]
        ]);
    }

    /**
     * Suspend a workstation.
     */
    public function suspend(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);
        $workstation->suspended = true;
        $workstation->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'SUSPEND_WORKSTATION',
            'entity_type' => 'Workstation',
            'entity_id' => $workstation->id,
            'client_ip' => $request->ip(),
            'after_data' => [
                'code' => $workstation->code,
                'suspended' => true,
            ]
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Máy trạm {$workstation->name} đã bị tạm dừng.",
            'data' => $workstation
        ]);
    }

    /**
     * Resume a workstation.
     */
    public function resume(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);
        $workstation->suspended = false;
        $workstation->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'RESUME_WORKSTATION',
            'entity_type' => 'Workstation',
            'entity_id' => $workstation->id,
            'client_ip' => $request->ip(),
            'after_data' => [
                'code' => $workstation->code,
                'suspended' => false,
            ]
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Máy trạm {$workstation->name} đã hoạt động trở lại.",
            'data' => $workstation
        ]);
    }
}
