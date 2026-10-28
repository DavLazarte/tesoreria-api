<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Karina Noble',
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'secret1234',
            'is_active' => true,
        ]);
        $user->assignRole('tesorera');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'secret1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'is_active',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'email_attempted' => 'karina.tesorera@devsign.ar',
            'event' => 'login',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Karina Noble',
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseHas('access_logs', [
            'email_attempted' => 'karina.tesorera@devsign.ar',
            'event' => 'login_failed',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::create([
            'name' => 'Usuario Inactivo',
            'email' => 'inactivo@devsign.ar',
            'password' => 'secret1234',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'inactivo@devsign.ar',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'code' => 'user_inactive',
            ]);

        $this->assertDatabaseHas('access_logs', [
            'email_attempted' => 'inactivo@devsign.ar',
            'event' => 'login_failed',
        ]);
    }

    public function test_authenticated_user_can_get_profile_via_me(): void
    {
        $user = User::create([
            'name' => 'Karina Noble',
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'secret1234',
            'is_active' => true,
        ]);
        $user->assignRole('tesorera');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'karina.tesorera@devsign.ar')
            ->assertJsonPath('user.roles.0', 'tesorera');

        $this->assertCount(11, $response->json('user.permissions'));
    }

    public function test_user_can_logout_and_token_is_revoked(): void
    {
        $user = User::create([
            'name' => 'Karina Noble',
            'email' => 'karina.tesorera@devsign.ar',
            'password' => 'secret1234',
            'is_active' => true,
        ]);

        $token = $user->createToken('test_token');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Sesión cerrada correctamente.']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $user->id,
            'event' => 'logout',
        ]);
    }
}
