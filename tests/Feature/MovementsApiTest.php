<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Fund;
use App\Models\Movement;
use App\Models\User;
use Database\Seeders\InitialCatalogsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovementsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $tesorera;
    protected User $contador;
    protected Category $ingresoCategory;
    protected Category $egresoCategory;
    protected Entity $entity;
    protected Fund $generalFund;

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

        $this->generalFund = Fund::where('kind', 'general')->firstOrFail();
        $this->ingresoCategory = Category::where('flow', 'ingreso')->firstOrFail();
        $this->egresoCategory = Category::where('flow', 'egreso')->firstOrFail();

        $type = EntityType::firstOrFail();
        $this->entity = Entity::create([
            'entity_type_id' => $type->id,
            'name' => 'Iglesia Belén',
            'is_active' => true,
        ]);
    }

    public function test_tesorera_can_record_income_and_expense_and_list_them(): void
    {
        // 1. Registrar Ingreso
        $incomeResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-10-27',
            'amount' => '500000.00',
            'concept' => 'Donación bancaria recibida',
            'category_id' => $this->ingresoCategory->id,
            'reference' => 'TRANSF-00129',
        ]);

        $incomeResponse->assertStatus(201)
            ->assertJsonPath('data.amount', '500000.00')
            ->assertJsonPath('data.type', 'ingreso');

        $incomeId = $incomeResponse->json('data.id');

        // 2. Registrar Egreso hacia entidad
        $expenseResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'egreso',
            'movement_date' => '2026-10-27',
            'amount' => '150000.00',
            'concept' => 'Ayuda enviada a Iglesia Belén',
            'category_id' => $this->egresoCategory->id,
            'entity_id' => $this->entity->id,
            'reference' => 'RECIBO-991',
        ]);

        $expenseResponse->assertStatus(201)
            ->assertJsonPath('data.amount', '150000.00');

        // 3. Listar con filtros (ej: solo egresos)
        $listResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/movements?type=egreso');
        $listResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'egreso');

        // 4. Detalle de movimiento
        $detailResponse = $this->actingAs($this->tesorera, 'sanctum')->getJson("/api/movements/{$incomeId}");
        $detailResponse->assertOk()
            ->assertJsonPath('data.id', $incomeId)
            ->assertJsonPath('data.is_voided', false);
    }

    public function test_tesorera_can_void_movement(): void
    {
        // Registrar ingreso
        $incomeResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-10-27',
            'amount' => '100000.00',
            'concept' => 'Aporte con error',
            'category_id' => $this->ingresoCategory->id,
        ]);

        $movementId = $incomeResponse->json('data.id');

        // Anular movimiento
        $voidResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/void", [
            'reason' => 'Comprobante duplicado por error',
        ]);

        $voidResponse->assertOk()
            ->assertJsonPath('movement.is_voided', true)
            ->assertJsonPath('movement.void.reason', 'Comprobante duplicado por error');

        $this->assertDatabaseHas('movement_voids', [
            'movement_id' => $movementId,
            'reason' => 'Comprobante duplicado por error',
        ]);
    }

    public function test_contador_can_view_movements_but_cannot_create_or_void(): void
    {
        // Crear movimiento previo a través de la API o con to_fund_id
        $movement = Movement::create([
            'type' => 'ingreso',
            'movement_date' => '2026-10-27',
            'to_fund_id' => $this->generalFund->id,
            'amount' => '50000.00',
            'concept' => 'Ingreso previo',
            'category_id' => $this->ingresoCategory->id,
            'created_by' => $this->tesorera->id,
        ]);

        // Contador puede ver listado y detalle
        $this->actingAs($this->contador, 'sanctum')->getJson('/api/movements')->assertOk();
        $this->actingAs($this->contador, 'sanctum')->getJson("/api/movements/{$movement->id}")->assertOk();

        // Contador NO puede crear movimientos (403)
        $this->actingAs($this->contador, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-10-27',
            'amount' => '1000.00',
            'concept' => 'Intento contador',
            'category_id' => $this->ingresoCategory->id,
        ])->assertStatus(403);

        // Contador NO puede anular movimientos (403)
        $this->actingAs($this->contador, 'sanctum')->postJson("/api/movements/{$movement->id}/void", [
            'reason' => 'Intento contador',
        ])->assertStatus(403);
    }

    public function test_attachment_upload_and_download(): void
    {
        Storage::fake('local');

        $movement = Movement::create([
            'type' => 'ingreso',
            'movement_date' => '2026-10-27',
            'to_fund_id' => $this->generalFund->id,
            'amount' => '80000.00',
            'concept' => 'Ingreso con comprobante',
            'category_id' => $this->ingresoCategory->id,
            'created_by' => $this->tesorera->id,
        ]);

        $file = UploadedFile::fake()->create('comprobante.pdf', 250, 'application/pdf');

        // Subida
        $uploadResponse = $this->actingAs($this->tesorera, 'sanctum')->postJson(
            "/api/movements/{$movement->id}/attachments",
            ['file' => $file]
        );

        $uploadResponse->assertStatus(201)
            ->assertJsonPath('data.original_name', 'comprobante.pdf')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $attachmentId = $uploadResponse->json('data.id');

        $this->assertDatabaseHas('movement_attachments', [
            'id' => $attachmentId,
            'movement_id' => $movement->id,
            'original_name' => 'comprobante.pdf',
        ]);

        // Descarga
        $downloadResponse = $this->actingAs($this->tesorera, 'sanctum')->get(
            "/api/movements/{$movement->id}/attachments/{$attachmentId}"
        );

        $downloadResponse->assertOk();
    }
}
