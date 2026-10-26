<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_frontend_can_preflight_api_requests(): void
    {
        $this->call('OPTIONS', '/api/v1/auth/login', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ])->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_login_token_can_access_and_be_revoked_from_protected_routes(): void
    {
        Role::create(['name' => 'cashier']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'cashier')->firstOrFail());

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'roles']])
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($token)->getJson('/api/v1/dashboard')->assertOk();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_expired_tokens_are_rejected(): void
    {
        Role::create(['name' => 'cashier']);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'cashier')->firstOrFail());

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        ApiToken::query()->update(['expires_at' => now()->subMinute()]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_admin_role_passes_role_gates_and_inactive_users_cannot_login(): void
    {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'cashier']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/stock/balances')->assertOk();

        $newUserId = $this->postJson('/api/v1/users', [
            'name' => 'New cashier',
            'email' => 'cashier@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'roles' => ['cashier'],
        ])->assertCreated()
            ->assertJsonPath('roles.0.name', 'cashier')
            ->assertJsonMissingPath('password')
            ->json('id');

        $this->patchJson("/api/v1/users/{$newUserId}", ['is_active' => false])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $newUserId, 'is_active' => false]);

        $inactive = User::factory()->create(['is_active' => false]);
        $this->assertFalse((bool) $inactive->fresh()->is_active);
        $this->postJson('/api/v1/auth/login', [
            'email' => $inactive->email,
            'password' => 'password',
        ])->assertUnprocessable();
    }
}
