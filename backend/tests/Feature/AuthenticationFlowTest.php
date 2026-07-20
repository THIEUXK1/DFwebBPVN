<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Regression coverage for the missing-`personal_access_tokens`-table incident: the checked-in
 * Sanctum migration used the default $table->morphs('tokenable') (bigint), which is incompatible
 * with this project's UUID-keyed App\Models\User and caused /api/auth/login to fail with a 500 on
 * the dev database. These tests deliberately go through the real HTTP login endpoint and a real
 * Sanctum-issued token — NOT Sanctum::actingAs()/actingAs(), which bypass token creation entirely
 * and would not have caught this class of bug.
 */
class AuthenticationFlowTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private string $plainPassword = 'RealLogin#Test2026';

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User();
        $this->user->id = (string) Str::uuid();
        $this->user->username = 'auth_flow_tester';
        $this->user->display_name = 'Auth Flow Tester';
        $this->user->password_hash = password_hash($this->plainPassword, PASSWORD_BCRYPT);
        $this->user->is_active = true;
        $this->user->save();
        $this->user->roles()->attach(Role::firstOrCreate(['code' => 'OPERATOR'], ['name' => 'Operator'])->id);
    }

    public function test_personal_access_tokens_table_exists_with_uuid_compatible_tokenable_id(): void
    {
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));

        $columnType = DB::selectOne(
            "SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'personal_access_tokens' AND column_name = 'tokenable_id'"
        );

        $this->assertNotNull($columnType, 'tokenable_id column must exist');
        $this->assertEquals('uuid', $columnType->data_type, 'tokenable_id must be UUID-compatible to match app.users.id, not the Sanctum default bigint');
    }

    public function test_real_login_creates_a_persisted_token(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'auth_flow_tester',
            'password' => $this->plainPassword,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'token_type', 'user' => ['id', 'username', 'display_name', 'roles']]);

        $token = $response->json('access_token');
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'tokenable_type' => User::class,
            'name' => 'auth_token',
        ]);
    }

    public function test_token_from_real_login_can_access_a_protected_endpoint(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'username' => 'auth_flow_tester',
            'password' => $this->plainPassword,
        ]);
        $token = $login->json('access_token');

        $me = $this->withHeader('Authorization', "Bearer $token")->getJson('/api/auth/me');

        $me->assertStatus(200);
        $me->assertJsonPath('username', 'auth_flow_tester');
    }

    public function test_wrong_password_returns_401_and_creates_no_token(): void
    {
        $before = DB::table('personal_access_tokens')->where('tokenable_id', $this->user->id)->count();

        $response = $this->postJson('/api/auth/login', [
            'username' => 'auth_flow_tester',
            'password' => 'not-the-right-password',
        ]);

        $response->assertStatus(401);

        $after = DB::table('personal_access_tokens')->where('tokenable_id', $this->user->id)->count();
        $this->assertEquals($before, $after);
    }

    public function test_nonexistent_user_returns_401_not_500(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'this_user_does_not_exist',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Asserts DB deletion rather than replaying the token on a second simulated request: Sanctum's
     * guard falls back to config('sanctum.guard') = ['web'] (a session guard) before checking the
     * bearer token, and Laravel's test client shares one container/session across sequential calls
     * within a single test method — so a second in-process request here is not a reliable signal.
     * The real end-to-end behavior (revoked token -> 401 on a genuinely separate HTTP connection)
     * was verified manually against a running `php artisan serve` instance; see session-log.md.
     */
    public function test_revoked_token_is_deleted_from_storage_on_logout(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'username' => 'auth_flow_tester',
            'password' => $this->plainPassword,
        ]);
        $token = $login->json('access_token');
        $tokenId = explode('|', $token)[0];

        $this->withHeader('Authorization', "Bearer $token")->postJson('/api/auth/logout')->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}
