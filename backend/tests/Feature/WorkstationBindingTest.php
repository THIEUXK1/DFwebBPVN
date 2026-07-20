<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Workstation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;

/**
 * WS-001: a station-scoped account (created for a công đoạn, assigned by Admin) must know its
 * workstation identity from login — not from a per-browser localStorage pick.
 */
class WorkstationBindingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_returns_bound_workstation_for_a_station_account(): void
    {
        $workstation = Workstation::where('code', 'WS-DYE')->firstOrFail();

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'ws_binding_dye_operator';
        $user->display_name = 'Dye Station Operator';
        $user->password_hash = password_hash('StationPass#1', PASSWORD_BCRYPT);
        $user->is_active = true;
        $user->workstation_id = $workstation->id;
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'ws_binding_dye_operator',
            'password' => 'StationPass#1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.workstation.code', 'WS-DYE');
        $response->assertJsonPath('user.workstation.type', 'DYE_WEIGHING');
        $response->assertJsonPath('user.workstation.default_screen', '/weighing-station');
        $response->assertJsonPath('user.workstation.allowed_actions', ['SCAN_ORDER', 'WEIGH_ITEM', 'PRINT_LABEL']);
    }

    public function test_login_returns_null_workstation_for_a_back_office_account(): void
    {
        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'ws_binding_backoffice_admin';
        $user->display_name = 'Back Office Admin';
        $user->password_hash = password_hash('AdminPass#1', PASSWORD_BCRYPT);
        $user->is_active = true;
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'ADMIN'], ['name' => 'Admin'])->id);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'ws_binding_backoffice_admin',
            'password' => 'AdminPass#1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('user.workstation', null);
    }

    public function test_me_endpoint_reflects_bound_workstation(): void
    {
        $workstation = Workstation::where('code', 'TANK-01')->firstOrFail();

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'ws_binding_tank_operator';
        $user->display_name = 'Tank Station Operator';
        $user->password_hash = password_hash('StationPass#2', PASSWORD_BCRYPT);
        $user->is_active = true;
        $user->workstation_id = $workstation->id;
        $user->save();
        $user->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);

        $login = $this->postJson('/api/auth/login', ['username' => 'ws_binding_tank_operator', 'password' => 'StationPass#2']);
        $token = $login->json('access_token');

        $me = $this->withHeader('Authorization', "Bearer $token")->getJson('/api/auth/me');

        $me->assertStatus(200);
        $me->assertJsonPath('workstation.code', 'TANK-01');
        $me->assertJsonPath('workstation.type', 'TANK_RECEIVING');
    }

    public function test_deleting_workstation_unassigns_bound_users_instead_of_blocking_or_cascading(): void
    {
        $workstation = Workstation::create([
            'code' => 'WS-TEMP-DELETE-TEST',
            'name' => 'Temp station for delete test',
            'type' => 'MONITORING',
            'active' => true,
        ]);

        $user = new User();
        $user->id = (string) Str::uuid();
        $user->username = 'ws_binding_temp_user';
        $user->display_name = 'Temp User';
        $user->password_hash = password_hash('pw', PASSWORD_BCRYPT);
        $user->is_active = true;
        $user->workstation_id = $workstation->id;
        $user->save();

        $workstation->delete();

        $user->refresh();
        $this->assertNull($user->workstation_id);
        $this->assertDatabaseHas('app.users', ['id' => $user->id]);
    }
}
