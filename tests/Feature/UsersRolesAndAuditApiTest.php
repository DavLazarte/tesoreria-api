<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\InitialCatalogsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersRolesAndAuditApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $tesorera;
    protected User $contador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialCatalogsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->tesorera = User::create([
            'name' => 'Karina Tesorera',
            'email' => 'karina@devsign.ar',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $this->tesorera->assignRole('tesorera');

        $this->contador = User::create([
            'name' => 'Roberto Contador',
            'email' => 'roberto@devsign.ar',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $this->contador->assignRole('contador');
    }

    public function test_tesorera_can_crud_users(): void
    {
        // 1. Crear nuevo usuario
        $createResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/users', [
            'name' => 'Marcos Asistente',
            'email' => 'marcos@devsign.ar',
            'password' => 'password123',
            'role' => 'contador',
            'is_active' => true,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('data.name', 'Marcos Asistente')
            ->assertJsonPath('data.email', 'marcos@devsign.ar')
            ->assertJsonPath('data.roles.0', 'contador');

        $newUserId = $createResponse->json('data.id');

        $this->assertDatabaseHas('users', ['id' => $newUserId, 'email' => 'marcos@devsign.ar']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created']);

        // 2. Listar usuarios
        $listResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/users?role=contador');
        $listResponse->assertOk()->assertJsonCount(2, 'data'); // roberto + marcos

        // 3. Actualizar usuario (desactivar)
        $updateResponse = $this->actingAs($this->tesorera, 'sanctum')->putJson("/api/users/{$newUserId}", [
            'is_active' => false,
        ]);
        $updateResponse->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.updated']);

        // 4. Soft Delete
        $deleteResponse = $this->actingAs($this->tesorera, 'sanctum')->deleteJson("/api/users/{$newUserId}");
        $deleteResponse->assertOk();
        $this->assertSoftDeleted('users', ['id' => $newUserId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deleted']);
    }

    public function test_user_cannot_delete_their_own_account(): void
    {
        $response = $this->actingAs($this->tesorera, 'sanctum')->deleteJson("/api/users/{$this->tesorera->id}");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No podés eliminar tu propia cuenta de usuario.');
    }

    public function test_can_list_roles_and_update_permissions(): void
    {
        // 1. Listar roles
        $rolesResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/roles');
        $rolesResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'permissions'],
                ],
            ]);

        // 2. Modificar permisos de un rol
        $contadorRole = Role::findByName('contador', 'web');

        $updatePermsResponse = $this->actingAs($this->tesorera, 'sanctum')->putJson("/api/roles/{$contadorRole->id}/permissions", [
            'permissions' => ['movements.view', 'reports.view'],
        ]);

        $updatePermsResponse->assertOk();
        $this->assertEqualsCanonicalizing(
            ['movements.view', 'reports.view'],
            $contadorRole->fresh()->permissions->pluck('name')->all()
        );

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'role.permissions_changed',
        ]);
    }

    public function test_audit_logs_and_access_logs_can_be_viewed_by_authorized_users(): void
    {
        // Ambos usuarios tienen audit.view
        $auditResponse = $this->actingAs($this->contador, 'sanctum')->getJson('/api/audit-logs');
        $auditResponse->assertOk()->assertJsonStructure(['data', 'links', 'meta']);

        $accessResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/access-logs');
        $accessResponse->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_contador_cannot_manage_users(): void
    {
        // Contador NO tiene users.manage -> 403 Forbidden
        $this->actingAs($this->contador, 'sanctum')->getJson('/api/users')->assertStatus(403);
        $this->actingAs($this->contador, 'sanctum')->getJson('/api/roles')->assertStatus(403);
    }
}
