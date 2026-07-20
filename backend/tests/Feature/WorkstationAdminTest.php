<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

class WorkstationAdminTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('ws_admin_tester', 'ADMIN');
        $this->operator = $this->makeUser('ws_admin_operator_tester', 'OPERATOR');
    }

    private function makeUser(string $username, string $roleCode): User
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = $username;
        $user->display_name = $username;
        $user->password_hash = password_hash('password', PASSWORD_BCRYPT);
        $user->is_active = true;
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id);
        return $user;
    }

    public function test_non_admin_cannot_list_workstations_for_provisioning(): void
    {
        $response = $this->actingAs($this->operator)->getJson('/api/admin/workstations');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_workstations_with_bound_users(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/workstations');

        $response->assertStatus(200);
        $codes = collect($response->json('data'))->pluck('code');
        $this->assertTrue($codes->contains('WS-DYE'));
    }

    public function test_admin_can_create_a_station_account_bound_to_a_workstation(): void
    {
        $workstation = Workstation::where('code', 'WS-CHEM')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson("/api/admin/workstations/{$workstation->id}/users", [
            'username' => 'ws_admin_new_chem_operator',
            'display_name' => 'Chem Station Operator',
            'password' => 'ChemPass#1',
            'role' => 'OPERATOR',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('app.users', [
            'username' => 'ws_admin_new_chem_operator',
            'operation_client_id' => $workstation->id,
        ]);

        $newUser = User::where('username', 'ws_admin_new_chem_operator')->firstOrFail();
        $this->assertTrue($newUser->hasRole('OPERATOR'));

        $this->assertDatabaseHas('app.audit_logs', [
            'action' => 'CREATE_STATION_ACCOUNT',
            'entity_id' => $newUser->id,
        ]);

        // The account must actually be able to log in and immediately know its workstation.
        $login = $this->postJson('/api/auth/login', [
            'username' => 'ws_admin_new_chem_operator',
            'password' => 'ChemPass#1',
        ]);
        $login->assertStatus(200);
        $login->assertJsonPath('user.workstation.code', 'WS-CHEM');
    }

    public function test_non_admin_cannot_create_a_station_account(): void
    {
        $workstation = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $response = $this->actingAs($this->operator)->postJson("/api/admin/workstations/{$workstation->id}/users", [
            'username' => 'should_not_be_created',
            'display_name' => 'Nope',
            'password' => 'password123',
            'role' => 'OPERATOR',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('app.users', ['username' => 'should_not_be_created']);
    }

    public function test_cannot_create_a_station_account_with_admin_role(): void
    {
        $workstation = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson("/api/admin/workstations/{$workstation->id}/users", [
            'username' => 'should_not_allow_admin_role',
            'display_name' => 'Nope',
            'password' => 'password123',
            'role' => 'ADMIN',
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_username_is_rejected(): void
    {
        $workstation = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson("/api/admin/workstations/{$workstation->id}/users", [
            'username' => $this->operator->username,
            'display_name' => 'Duplicate',
            'password' => 'password123',
            'role' => 'OPERATOR',
        ]);

        $response->assertStatus(422);
    }
}
