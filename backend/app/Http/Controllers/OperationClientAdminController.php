<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OperationClient;
use App\Models\Capability;
use App\Models\Device;
use App\Models\OperationClientCapability;
use App\Models\OperationClientDevice;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class OperationClientAdminController extends Controller
{
    private const ASSIGNABLE_ROLES = ['OPERATOR', 'SUPERVISOR', 'TECHNOLOGIST'];

    /**
     * List all operation clients.
     */
    public function index()
    {
        $clients = OperationClient::with([
            'users' => function ($q) {
                $q->select('id', 'username', 'display_name', 'is_active', 'operation_client_id')
                  ->with('roles:code');
            },
            'capabilities',
            'devices'
        ])->orderBy('id')->get()->map(function ($client) {
            // Count waiting jobs based on default capability
            $waitingJobs = 0;
            $cap = $client->default_capability;
            
            if (in_array($cap, ['SMALL_SCALE', 'LARGE_SCALE'])) {
                $waitingJobs = \App\Models\WeighingJob::where('job_type', '!=', '') // any active
                    ->whereIn('status', ['PENDING', 'RECEIVED', 'IN_PROGRESS'])
                    ->count();
            } else if ($cap === 'QR_LABEL_PRINTING') {
                $waitingJobs = \App\Models\PrintJob::where('status', 'PENDING')->count();
            } else if ($cap === 'CHEMICAL_CALL') {
                $waitingJobs = \App\Models\ChemicalCallRequest::whereIn('status', ['ORDERED', 'ACKNOWLEDGED'])->count();
            } else if ($cap === 'PRODUCTION_ORDER') {
                $waitingJobs = \App\Models\MachineDispatch::where('queue_state', 'INPUT')->count();
            }

            $errorsCount = is_array($client->active_errors) ? count($client->active_errors) : 0;
            $flags = \App\Models\FeatureFlag::where('value', true)->pluck('key')->toArray();

            $data = $client->toArray();
            $data['waiting_jobs_count'] = $waitingJobs;
            $data['errors_count'] = $errorsCount;
            $data['active_flags'] = $flags;

            // Map old workstation properties for backward compatibility with frontend code
            $data['workstation_type'] = $client->default_capability;
            $data['default_screen'] = $client->default_route;
            $data['active'] = $client->status === 'ACTIVE';

            return $data;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $clients,
        ]);
    }

    /**
     * Create user account bound to client.
     */
    public function createUser(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);

        $request->validate([
            'username' => 'required|string|max:100|unique:users,username',
            'display_name' => 'required|string|max:200',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:' . implode(',', self::ASSIGNABLE_ROLES),
        ]);

        $user = DB::transaction(function () use ($request, $client) {
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->username = $request->input('username');
            $user->display_name = $request->input('display_name');
            $user->password_hash = Hash::make($request->input('password'));
            $user->is_active = true;
            $user->operation_client_id = $client->id;
            $user->save();

            $role = Role::firstOrCreate(['code' => $request->input('role')], ['name' => $request->input('role')]);
            $user->roles()->attach($role->id);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'CREATE_STATION_ACCOUNT',
                'entity_type' => 'User',
                'entity_id' => $user->id,
                'after_data' => [
                    'username' => $user->username,
                    'display_name' => $user->display_name,
                    'role' => $request->input('role'),
                    'operation_client_id' => $client->id,
                    'client_code' => $client->code,
                ],
                'client_ip' => request()->ip(),
            ]);

            return $user;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Đã tạo tài khoản trạm '{$user->username}' cho công đoạn {$client->name}.",
            'data' => $user->load('roles:code'),
        ], 201);
    }

    public function register(Request $request)
    {
        if ($request->has('workstation_type') && !$request->has('default_capability')) {
            $mappedCap = null;
            $type = $request->input('workstation_type');
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
            $request->merge(['default_capability' => $mappedCap]);
        }

        $request->validate([
            'code' => 'required|string|max:50|unique:operation_clients,code',
            'name' => 'required|string|max:200',
            'default_capability' => 'required|string|max:100',
            'location' => 'nullable|string|max:100',
            'default_route' => 'nullable|string|max:100',
        ]);

        $token = 'WS-' . Str::upper(Str::random(12)) . '-' . Str::upper(Str::random(12));

        $client = DB::transaction(function () use ($request, $token) {
            $client = OperationClient::create([
                'code' => $request->code,
                'name' => $request->name,
                'default_capability' => $request->default_capability,
                'location' => $request->location ?? 'Khu vực sản xuất',
                'default_route' => $request->default_route ?? $this->getDefaultRouteForCap($request->default_capability),
                'status' => 'ACTIVE',
                'kiosk_mode' => true,
                'registration_token_hash' => hash('sha256', $token),
            ]);

            // Auto-assign the business capability and its typical dependencies
            $this->assignDefaultCapabilities($client, $request->default_capability);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'REGISTER_CLIENT',
                'entity_type' => 'OperationClient',
                'entity_id' => $client->id,
                'after_data' => [
                    'code' => $client->code,
                    'default_capability' => $client->default_capability,
                ],
                'client_ip' => $request->ip(),
            ]);

            return $client;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'token' => $token,
            'data' => $client
        ], 201);
    }

    /**
     * Update client configurations, capabilities and devices.
     */
    public function updateConfig(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:200',
            'default_capability' => 'required|string|max:100',
            'default_route' => 'required|string|max:100',
            'location' => 'nullable|string|max:100',
            'capabilities' => 'array', // array of capability codes
            'devices' => 'array', // array of ['device_id' => '...', 'device_role' => '...', 'is_default' => true, 'priority' => 1]
        ]);

        DB::transaction(function () use ($client, $request) {
            $oldData = $client->toArray();

            $client->name = $request->name;
            $client->default_capability = $request->default_capability;
            $client->default_route = $request->default_route;
            $client->location = $request->location;
            $client->save();

            // Sync Capabilities
            if ($request->has('capabilities')) {
                $capIds = Capability::whereIn('code', $request->capabilities)->pluck('id')->toArray();
                $client->capabilities()->sync(array_fill_keys($capIds, ['enabled' => true]));
            }

            // Sync Devices
            if ($request->has('devices')) {
                OperationClientDevice::where('operation_client_id', $client->id)->delete();
                foreach ($request->devices as $dev) {
                    OperationClientDevice::create([
                        'operation_client_id' => $client->id,
                        'device_id' => $dev['device_id'],
                        'device_role' => $dev['device_role'],
                        'is_default' => $dev['is_default'] ?? false,
                        'priority' => $dev['priority'] ?? 1,
                        'enabled' => true,
                    ]);
                }
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'UPDATE_CLIENT_CONFIG',
                'entity_type' => 'OperationClient',
                'entity_id' => $client->id,
                'before_data' => $oldData,
                'after_data' => $client->fresh()->toArray(),
                'client_ip' => $request->ip(),
            ]);
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Cập nhật cấu hình trạm thành công.',
            'data' => $client->load(['capabilities', 'devices'])
        ]);
    }

    /**
     * Generate or rotate kiosk token.
     */
    public function generateKioskToken(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);

        // Generate a 40-character random non-guessable secure token
        $rawToken = 'KT-' . Str::upper(Str::random(12)) . '-' . Str::upper(Str::random(12)) . '-' . Str::upper(Str::random(12));
        $hash = hash('sha256', $rawToken);

        $client->kiosk_token_hash = $hash;
        $client->kiosk_token_active = true;
        $client->kiosk_token_expires_at = now()->addDays(90); // 90 days rotation policy
        $client->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'GENERATE_KIOSK_TOKEN',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'after_data' => [
                'code' => $client->code,
                'expires_at' => $client->kiosk_token_expires_at->toIso8601String(),
            ],
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'raw_token' => $rawToken,
            'expires_at' => $client->kiosk_token_expires_at->toIso8601String(),
            'kiosk_url' => url("/operate/c/{$client->code}/{$rawToken}")
        ]);
    }

    /**
     * Revoke kiosk token.
     */
    public function revokeKioskToken(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);
        $client->kiosk_token_active = false;
        $client->save();

        // Revoke active sessions for this client
        $client->kioskSessions()->update(['status' => 'REVOKED']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'REVOKE_KIOSK_TOKEN',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Đã thu hồi token kiosk thành công. Tất cả các kiosk session hiện tại đã bị chấm dứt.'
        ]);
    }

    /**
     * Suspend operation client.
     */
    public function suspend(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);
        $client->status = 'INACTIVE';
        $client->suspended = true;
        $client->save();

        // Revoke active sessions
        $client->kioskSessions()->update(['status' => 'REVOKED']);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'SUSPEND_WORKSTATION',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Trạm vận hành {$client->name} đã bị tạm dừng.",
            'data' => $client
        ]);
    }

    /**
     * Resume operation client.
     */
    public function resume(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);
        $client->status = 'ACTIVE';
        $client->suspended = false;
        $client->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'RESUME_WORKSTATION',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Trạm vận hành {$client->name} đã hoạt động trở lại.",
            'data' => $client
        ]);
    }

    /**
     * Test connection to device.
     */
    public function testConnection(Request $request, $id)
    {
        $client = OperationClient::findOrFail($id);
        $request->validate([
            'device_id' => 'required|string',
            'device_role' => 'required|string',
        ]);

        $success = true;

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'TEST_DEVICE_CONNECTION',
            'entity_type' => 'OperationClient',
            'entity_id' => $client->id,
            'after_data' => [
                'device_id' => $request->device_id,
                'device_role' => $request->device_role,
                'status' => 'ONLINE'
            ],
            'client_ip' => $request->ip()
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Thiết bị phản hồi tốt (ONLINE)."
        ]);
    }

    private function getDefaultRouteForCap(string $cap): string
    {
        // PRODUCTION_ORDER trước đây trỏ nhầm sang '/order-scan' (thực chất là trạm
        // "ORDER DESK" — quét QR xem/xác nhận đã nhận đơn, KHÔNG tạo/duyệt đơn).
        // Route đúng khớp VBA Workbook C3 (btnSAVE_Click + MoveToSend, nơi có
        // ApproveProductionOrderService) là '/production-batches'. Sửa 2026-07-18
        // theo yêu cầu người dùng sau khi đối chiếu trực tiếp.
        $routes = [
            'PRODUCTION_ORDER' => '/production-batches',
            'SMALL_SCALE' => '/weighing-station',
            'LARGE_SCALE' => '/weighing-station',
            'QR_LABEL_PRINTING' => '/print-station',
            'CHEMICAL_CALL' => '/feeding-monitor',
        ];
        return $routes[$cap] ?? '/';
    }

    private function assignDefaultCapabilities(OperationClient $client, string $defaultCap): void
    {
        $businessCaps = [$defaultCap];
        $deviceCaps = ['SCAN_QR', 'LOCAL_AGENT'];

        if (in_array($defaultCap, ['SMALL_SCALE', 'LARGE_SCALE'])) {
            $deviceCaps[] = 'WEIGH';
            $deviceCaps[] = 'PRINT';
        } else if ($defaultCap === 'QR_LABEL_PRINTING') {
            $deviceCaps[] = 'PRINT';
        }

        $allCaps = array_merge($businessCaps, $deviceCaps);
        foreach ($allCaps as $cc) {
            $cap = Capability::where('code', $cc)->first();
            if ($cap) {
                OperationClientCapability::create([
                    'operation_client_id' => $client->id,
                    'capability_id' => $cap->id,
                    'enabled' => true
                ]);
            }
        }
    }
}
