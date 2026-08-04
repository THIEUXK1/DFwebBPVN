<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json(['message' => 'Tài khoản không tồn tại trong hệ thống'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Tài khoản này đã bị khóa'], 401);
        }

        // Verify password hash
        if (!Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Mật khẩu không chính xác'], 401);
        }

        // Retrieve roles
        $roles = $user->roles()->pluck('code')->toArray();

        // Create Sanctum Token
        $token = $user->createToken('auth_token', $roles)->plainTextToken;

        // Log audit trail
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'LOGIN',
            'entity_type' => 'USER',
            'entity_id' => $user->id,
            'after_data' => ['username' => $user->username],
            'client_ip' => $request->ip(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'display_name' => $user->display_name,
                'roles' => $roles,
                'workstation' => $this->workstationPayload($user),
            ]
        ]);
    }

    /**
     * Station-bound accounts (created per công đoạn, per Admin's assignment) carry their
     * workstation identity from login instead of it being picked per-browser.
     */
    private function workstationPayload(User $user): ?array
    {
        $workstation = $user->workstation;
        if (!$workstation) {
            return null;
        }

        return [
            'id' => $workstation->id,
            'code' => $workstation->code,
            'name' => $workstation->name,
            'type' => $workstation->type,
            'location' => $workstation->location,
            'assigned_scale_device_id' => $workstation->assigned_scale_device_id,
            'assigned_printer_device_id' => $workstation->assigned_printer_device_id,
            'default_screen' => $workstation->default_screen,
            // router/index.ts đọc `default_route` TRƯỚC rồi mới rơi về `default_screen` — trả
            // cả hai để không phụ thuộc vào việc getDefaultScreenAttribute() còn alias hay không.
            'default_route' => $workstation->default_route,
            'allowed_actions' => $workstation->allowed_actions,
            // Thiếu trường này gây lỗi tương tự setKioskSession() (frontend): AppLayout.vue
            // chặn nhầm "trạm không có quyền" cho tài khoản gắn cứng trạm vì không tìm thấy
            // capability nào để đối chiếu (2026-07-18).
            'capability_codes' => $workstation->capability_codes,
        ];
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'LOGOUT',
                'entity_type' => 'USER',
                'entity_id' => $user->id,
                'client_ip' => $request->ip(),
            ]);
        }

        return response()->json(['message' => 'Đã đăng xuất thành công']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'roles' => $user->roles()->pluck('code')->toArray(),
            'workstation' => $this->workstationPayload($user),
        ]);
    }
}
