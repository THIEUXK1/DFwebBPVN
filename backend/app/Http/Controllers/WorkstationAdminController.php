<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Workstation;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * WS-003: Admin-only provisioning of station-scoped accounts (một tài khoản = một công đoạn).
 * Scope is deliberately narrow — creating new station accounts only. Reassigning existing
 * accounts, editing device bindings, and workstation CRUD are out of scope for this iteration.
 */
class WorkstationAdminController extends Controller
{
    /**
     * Station accounts are floor-operator logins bound to exactly one workstation; ADMIN is
     * excluded because binding an admin account to a single kiosk would defeat the purpose of
     * admin's back-office, cross-station access.
     */
    private const ASSIGNABLE_ROLES = ['OPERATOR', 'SUPERVISOR', 'TECHNOLOGIST'];

    /**
     * List all workstations with their currently bound accounts, for the Admin card grid.
     */
    public function index()
    {
        $workstations = Workstation::with(['users' => function ($q) {
            $q->select('id', 'username', 'display_name', 'is_active', 'workstation_id')
              ->with('roles:code');
        }])->orderBy('id')->get()->map(function ($ws) {
            // Count waiting jobs based on type
            $waitingJobs = 0;
            if (in_array($ws->workstation_type, ['SMALL_SCALE', 'LARGE_SCALE'])) {
                $waitingJobs = \App\Models\WeighingJob::where('workstation_type', $ws->workstation_type)
                    ->whereIn('status', ['PENDING', 'RECEIVED', 'IN_PROGRESS'])
                    ->count();
            } else if ($ws->workstation_type === 'QR_LABEL_PRINTING') {
                $waitingJobs = \App\Models\PrintJob::where('status', 'PENDING')->count();
            } else if ($ws->workstation_type === 'CHEMICAL_CALL') {
                $waitingJobs = \App\Models\ChemicalCallRequest::whereIn('status', ['ORDERED', 'ACKNOWLEDGED'])->count();
            } else if ($ws->workstation_type === 'PRODUCTION_ORDER') {
                $waitingJobs = \App\Models\MachineDispatch::where('queue_state', 'INPUT')->count();
            }

            // Count active errors
            $errorsCount = is_array($ws->active_errors) ? count($ws->active_errors) : 0;

            // Active feature flags
            $flags = \App\Models\FeatureFlag::where('value', true)->pluck('key')->toArray();

            $data = $ws->toArray();
            $data['waiting_jobs_count'] = $waitingJobs;
            $data['errors_count'] = $errorsCount;
            $data['active_flags'] = $flags;

            return $data;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $workstations,
        ]);
    }

    /**
     * Create a new station-scoped account bound to the given workstation.
     */
    public function createUser(Request $request, $id)
    {
        $workstation = Workstation::findOrFail($id);

        $request->validate([
            'username' => 'required|string|max:100|unique:users,username',
            'display_name' => 'required|string|max:200',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:' . implode(',', self::ASSIGNABLE_ROLES),
        ]);

        $user = DB::transaction(function () use ($request, $workstation) {
            $user = new User();
            $user->id = (string) Str::uuid();
            $user->username = $request->input('username');
            $user->display_name = $request->input('display_name');
            $user->password_hash = Hash::make($request->input('password'));
            $user->is_active = true;
            $user->workstation_id = $workstation->id;
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
                    'workstation_id' => $workstation->id,
                    'workstation_code' => $workstation->code,
                ],
                'client_ip' => request()->ip(),
            ]);

            return $user;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'message' => "Đã tạo tài khoản trạm '{$user->username}' cho công đoạn {$workstation->name}.",
            'data' => $user->load('roles:code'),
        ], 201);
    }
}
