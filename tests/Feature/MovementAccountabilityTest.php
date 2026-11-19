<?php

namespace Tests\Feature;

use App\Models\AuditLog;
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

class MovementAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $tesorera;
    protected User $contador;
    protected Fund $generalFund;
    protected Category $ingresoCategory;
    protected Category $egresoCategory;
    protected Entity $entity;

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
            'name' => 'Comedor Los Bajitos',
            'is_active' => true,
        ]);
    }

    public function test_tesorera_can_record_accountability_with_vouchers_on_egreso(): void
    {
        Storage::fake('local');

        // 1. Crear ingreso para saldo y luego egreso
        $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-11-10',
            'amount' => '300000.00',
            'concept' => 'Donación inicial',
            'category_id' => $this->ingresoCategory->id,
        ])->assertStatus(201);

        $expenseRes = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'egreso',
            'movement_date' => '2026-11-12',
            'amount' => '100000.00',
            'concept' => 'Entrega de fondos para alimentos',
            'category_id' => $this->egresoCategory->id,
            'entity_id' => $this->entity->id,
        ]);
        $expenseRes->assertStatus(201);
        $movementId = $expenseRes->json('data.id');

        // 2. Registrar rendición de cuentas con 2 comprobantes
        $file1 = UploadedFile::fake()->create('factura_super.pdf', 300, 'application/pdf');
        $file2 = UploadedFile::fake()->image('ticket_verduleria.jpg');

        $accRes = $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/accountabilities", [
            'rendered_at' => '2026-11-18',
            'amount' => '100000.00',
            'description' => 'Compra de 20 bolsas de fideos, arroz, carne y verduras según boletas adjuntas.',
            'files' => [$file1, $file2],
        ]);

        $accRes->assertStatus(201)
            ->assertJsonPath('data.movement_id', $movementId)
            ->assertJsonPath('data.amount', '100000.00')
            ->assertJsonPath('data.description', 'Compra de 20 bolsas de fideos, arroz, carne y verduras según boletas adjuntas.');

        $this->assertCount(2, $accRes->json('data.attachments'));
        $attachmentId = $accRes->json('data.attachments.0.id');
        $accountabilityId = $accRes->json('data.id');

        // 3. Verificar auditoría
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'movement.accountability_created',
            'auditable_type' => Movement::class,
            'auditable_id' => $movementId,
        ]);

        // 4. Contador puede ver el movimiento y sus rendiciones
        $showRes = $this->actingAs($this->contador, 'sanctum')->getJson("/api/movements/{$movementId}");
        $showRes->assertStatus(200);
        $this->assertCount(1, $showRes->json('data.accountabilities'));
        $this->assertCount(2, $showRes->json('data.accountabilities.0.attachments'));

        // 5. Descargar comprobante
        $downloadRes = $this->actingAs($this->contador, 'sanctum')->get(
            "/api/movements/{$movementId}/accountabilities/{$accountabilityId}/attachments/{$attachmentId}"
        );
        $downloadRes->assertStatus(200);
    }

    public function test_cannot_record_accountability_on_income(): void
    {
        $incomeRes = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-11-10',
            'amount' => '50000.00',
            'concept' => 'Donación recibida',
            'category_id' => $this->ingresoCategory->id,
        ]);
        $incomeRes->assertStatus(201);
        $movementId = $incomeRes->json('data.id');

        $res = $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/accountabilities", [
            'rendered_at' => '2026-11-12',
            'description' => 'Intento de rendición',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('message', 'Solo se pueden registrar rendiciones de cuentas en movimientos de tipo egreso.');
    }

    public function test_cannot_record_accountability_on_voided_movement(): void
    {
        $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-11-10',
            'amount' => '100000.00',
            'concept' => 'Fondo disponible',
            'category_id' => $this->ingresoCategory->id,
        ]);

        $expenseRes = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'egreso',
            'movement_date' => '2026-11-11',
            'amount' => '20000.00',
            'concept' => 'Entrega que luego se anula',
            'category_id' => $this->egresoCategory->id,
        ]);
        $expenseRes->assertStatus(201);
        $movementId = $expenseRes->json('data.id');

        // Anular
        $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/void", [
            'reason' => 'Error de carga inicial',
        ])->assertStatus(200);

        // Intentar rendir
        $res = $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/accountabilities", [
            'rendered_at' => '2026-11-12',
            'description' => 'Rendición sobre movimiento anulado',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('message', 'No se puede rendir cuentas sobre un movimiento anulado.');
    }

    public function test_can_list_accountabilities_with_filters(): void
    {
        // 1. Ingreso y Egreso
        $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'ingreso',
            'movement_date' => '2026-11-10',
            'amount' => '100000.00',
            'concept' => 'Fondo disponible',
            'category_id' => $this->ingresoCategory->id,
        ]);

        $expenseRes = $this->actingAs($this->tesorera, 'sanctum')->postJson('/api/movements', [
            'type' => 'egreso',
            'movement_date' => '2026-11-11',
            'amount' => '30000.00',
            'concept' => 'Entrega para insumos',
            'category_id' => $this->egresoCategory->id,
            'entity_id' => $this->entity->id,
        ]);
        $movementId = $expenseRes->json('data.id');

        // Rendir
        $this->actingAs($this->tesorera, 'sanctum')->postJson("/api/movements/{$movementId}/accountabilities", [
            'rendered_at' => '2026-11-12',
            'amount' => '15000.00',
            'description' => 'Facturas de carne y verdulería',
        ])->assertStatus(201);

        // Listar rendiciones (tesorera o contador)
        $listRes = $this->actingAs($this->contador, 'sanctum')->getJson('/api/accountabilities');
        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', '15000.00')
            ->assertJsonPath('data.0.description', 'Facturas de carne y verdulería')
            ->assertJsonPath('data.0.movement.id', $movementId);

        // Filtro por término
        $searchRes = $this->actingAs($this->contador, 'sanctum')->getJson('/api/accountabilities?q=verdulería');
        $searchRes->assertStatus(200)->assertJsonCount(1, 'data');

        $emptyRes = $this->actingAs($this->contador, 'sanctum')->getJson('/api/accountabilities?q=inexistente');
        $emptyRes->assertStatus(200)->assertJsonCount(0, 'data');
    }
}
