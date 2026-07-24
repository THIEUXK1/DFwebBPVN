<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Workstation;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class WorkstationSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $operator;
    private Role $adminRole;
    private Role $operatorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin_user', 'ADMIN');
        $this->operator = $this->makeUser('operator_user', 'OPERATOR');
    }

    private function makeUser(string $username, string $roleCode): User
    {
        $user = new User();
        $user->id = (string) \Illuminate\Support\Str::uuid();
        $user->username = $username;
        $user->display_name = $username;
        $user->password_hash = bcrypt('password');
        $user->is_active = true;
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id);
        return $user;
    }

    /**
     * Test admin can register a new workstation and get a token.
     */
    public function test_admin_can_register_workstation()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/workstations/register', [
                'code' => 'WS-TEST-REG-01',
                'name' => 'Trạm kiểm thử đăng ký',
                'workstation_type' => 'DYE_WEIGHING',
                'location' => 'Phòng lab',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['status', 'token', 'data']);

        $token = $response->json('token');
        $this->assertNotNull($token);
        $this->assertDatabaseHas('app.operation_clients', [
            'code' => 'WS-TEST-REG-01',
            'registration_token_hash' => hash('sha256', $token)
        ]);
    }

    /**
     * Test client handshake with registration token.
     */
    public function test_client_handshake_with_token()
    {
        // Setup workstation first
        $token = 'WS-XYZ-123-ABC';
        $workstation = Workstation::create([
            'code' => 'WS-TEST-HS-01',
            'name' => 'Trạm kiểm thử handshake',
            'workstation_type' => 'DYE_WEIGHING',
            'type' => 'DYE_WEIGHING',
            'location' => 'Phòng lab',
            'active' => true,
            'registration_token_hash' => hash('sha256', $token),
        ]);

        $response = $this->postJson('/api/workstations/handshake', [
            'token' => $token,
            'hostname' => 'TEST-PC-01',
            'mac_address' => '00:1A:2B:3C:4D:5E'
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'SUCCESS')
            ->assertJsonStructure(['workstation' => ['id', 'code', 'name', 'workstation_type', 'default_route', 'allowed_actions']]);

        // Check DB updated
        $this->assertDatabaseHas('app.operation_clients', [
            'code' => 'WS-TEST-HS-01',
            'hostname' => 'TEST-PC-01',
        ]);
    }

    /**
     * Test client handshake fails with wrong token.
     */
    public function test_handshake_fails_with_invalid_token()
    {
        $response = $this->postJson('/api/workstations/handshake', [
            'token' => 'INVALID-TOKEN',
            'hostname' => 'TEST-PC-01',
            'mac_address' => '00:1A:2B:3C:4D:5E'
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('status', 'ERROR');
    }

    /**
     * Test api request checks workstation.guard middleware.
     */
    public function test_api_checks_workstation_guard_middleware()
    {
        // Endpoints using workstation.guard (e.g. /api/scanner/scan requires SCAN_ORDER action)
        // Calling it with operator but NO workstation headers should return 403
        $response = $this->actingAs($this->operator)
            ->withHeader('X-Enforce-Workstation-Guard', 'true')
            ->postJson('/api/scanner/scan', [
                'barcode' => 'BATCH-001'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'ERROR')
            ->assertJsonPath('message', 'Yêu cầu này bắt buộc phải được gửi từ máy trạm có cấu hình hợp lệ.');
    }

    /**
     * Test api succeeds if correct workstation token is provided.
     */
    public function test_api_succeeds_with_correct_workstation_token()
    {
        // 1. Setup workstation and allowed action
        $token = 'WS-SCAN-TOKEN-01';
        $workstation = Workstation::create([
            'code' => 'WS-SCAN-01',
            'name' => 'Trạm Quét',
            'workstation_type' => 'ORDER_SCAN',
            'type' => 'ORDER_SCAN',
            'active' => true,
            'registration_token_hash' => hash('sha256', $token)
        ]);

        $cap = \App\Models\Capability::firstOrCreate(['code' => 'PRODUCTION_ORDER'], ['name' => 'Production Order']);
        $workstation->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);

        // 2. Call API with X-Workstation-Token header
        $response = $this->actingAs($this->operator)
            ->withHeaders([
                'X-Workstation-Token' => $token,
                'X-Workstation-Code' => 'WS-SCAN-01'
            ])
            ->postJson('/api/scanner/scan', [
                'barcode' => 'BATCH-001'
            ]);

        // Should NOT return 403 from workstation guard (it might return 404/other if scanner controller expects more setup, but not 403 workstation guard error)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test api fails if workstation does not have allowed action.
     */
    public function test_api_fails_if_workstation_lacks_action()
    {
        $token = 'WS-NOACTION-TOKEN';
        $workstation = Workstation::create([
            'code' => 'WS-NOACTION-01',
            'name' => 'Trạm rỗng',
            'workstation_type' => 'ORDER_SCAN',
            'type' => 'ORDER_SCAN',
            'active' => true,
            'registration_token_hash' => hash('sha256', $token)
        ]);

        // Add a different action so it doesn't fall back to defaults
        $cap = \App\Models\Capability::firstOrCreate(['code' => 'QR_LABEL_PRINTING'], ['name' => 'QR Label Printing']);
        $workstation->capabilities()->syncWithoutDetaching([$cap->id => ['enabled' => true]]);

        $response = $this->actingAs($this->operator)
            ->withHeaders([
                'X-Workstation-Token' => $token,
                'X-Workstation-Code' => 'WS-NOACTION-01'
            ])
            ->postJson('/api/scanner/scan', [
                'barcode' => 'BATCH-001'
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'ERROR')
            ->assertJsonPath('message', "Máy trạm 'WS-NOACTION-01' không có quyền thực hiện hành động này (SCAN_ORDER).");
    }
}
