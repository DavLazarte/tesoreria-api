<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\InitialCatalogsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogsApiTest extends TestCase
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

    public function test_tesorera_can_crud_entities(): void
    {
        $type = EntityType::firstOrFail();

        // 1. Create
        $response = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/entities', [
            'entity_type_id' => $type->id,
            'name' => 'Iglesia Belén',
            'phone' => '381-123456',
            'email' => 'belen@iglesia.org',
            'address' => 'Av. Mitre 500',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Iglesia Belén');

        $entityId = $response->json('data.id');

        $this->assertDatabaseHas('entities', ['id' => $entityId, 'name' => 'Iglesia Belén']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'entity.created', 'auditable_id' => $entityId]);

        // 2. Read / List con filtro
        $listResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/entities?q=Belén');
        $listResponse->assertOk()->assertJsonCount(1, 'data');

        // 3. Update
        $updateResponse = $this->actingAs($this->tesorera, 'sanctum')->putJson("/api/entities/{$entityId}", [
            'name' => 'Iglesia Belén Central',
        ]);
        $updateResponse->assertOk()->assertJsonPath('data.name', 'Iglesia Belén Central');
        $this->assertDatabaseHas('audit_logs', ['event' => 'entity.updated', 'auditable_id' => $entityId]);

        // 4. Soft Delete
        $deleteResponse = $this->actingAs($this->tesorera, 'sanctum')->deleteJson("/api/entities/{$entityId}");
        $deleteResponse->assertOk();
        $this->assertSoftDeleted('entities', ['id' => $entityId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'entity.deleted', 'auditable_id' => $entityId]);
    }

    public function test_contador_can_view_entities_but_cannot_modify(): void
    {
        $type = EntityType::firstOrFail();
        $entity = Entity::create([
            'entity_type_id' => $type->id,
            'name' => 'Comedor La Esperanza',
            'is_active' => true,
        ]);

        // Contador tiene entities.view -> Puede listar y ver
        $this->actingAs($this->contador, 'sanctum')->getJson('/api/entities')->assertOk();
        $this->actingAs($this->contador, 'sanctum')->getJson("/api/entities/{$entity->id}")->assertOk();

        // Contador NO tiene entities.manage -> Debe recibir 403 Forbidden
        $this->actingAs($this->contador, 'sanctum')->postJson('/api/entities', [
            'entity_type_id' => $type->id,
            'name' => 'Intento Prohibido',
        ])->assertStatus(403);

        $this->actingAs($this->contador, 'sanctum')->putJson("/api/entities/{$entity->id}", [
            'name' => 'Nuevo Nombre',
        ])->assertStatus(403);

        $this->actingAs($this->contador, 'sanctum')->deleteJson("/api/entities/{$entity->id}")->assertStatus(403);
    }

    public function test_can_list_and_create_categories(): void
    {
        $response = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/categories', [
            'name' => 'Subsidio Municipal',
            'flow' => 'ingreso',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Subsidio Municipal')
            ->assertJsonPath('data.flow', 'ingreso');

        $this->assertDatabaseHas('categories', ['name' => 'Subsidio Municipal', 'flow' => 'ingreso']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'category.created']);

        $list = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/categories?flow=ingreso');
        $list->assertOk();
    }

    public function test_funds_endpoint_returns_calculated_balance(): void
    {
        $response = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/funds');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'kind',
                        'balance',
                    ],
                ],
            ]);
    }
}
