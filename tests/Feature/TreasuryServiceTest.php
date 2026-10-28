<?php

namespace Tests\Feature;

use App\Enums\FundKind;
use App\Enums\MovementType;
use App\Exceptions\CannotVoidException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidMovementException;
use App\Models\Category;
use App\Models\Entity;
use App\Models\EntityType;
use App\Models\Fund;
use App\Models\Movement;
use App\Models\User;
use App\Services\TreasuryService;
use Database\Seeders\InitialCatalogsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $treasuryService;
    protected User $tesorera;
    protected Fund $generalFund;
    protected Category $ingresoCategory;
    protected Category $egresoCategory;
    protected EntityType $entityType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(InitialCatalogsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->treasuryService = app(TreasuryService::class);

        $this->tesorera = User::create([
            'name' => 'Karina Test',
            'email' => 'karina.test@devsign.ar',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $this->tesorera->assignRole('tesorera');

        $this->generalFund = Fund::where('kind', FundKind::General)->firstOrFail();
        $this->ingresoCategory = Category::where('flow', 'ingreso')->firstOrFail();
        $this->egresoCategory = Category::where('flow', 'egreso')->firstOrFail();
        $this->entityType = EntityType::firstOrFail();
    }

    /**
     * Test del flujo operativo real:
     * 1. Ingreso de $10.000.000 -> Saldo = $10.000.000
     * 2. Egreso a una Iglesia de $2.000.000 -> Saldo = $8.000.000
     * 3. Egreso a un Comedor de $500.000 -> Saldo = $7.500.000
     * 4. Egreso de gasto general sin entidad (ej: compras menores) de $100.000 -> Saldo = $7.400.000
     */
    public function test_real_treasury_flow(): void
    {
        $iglesia = Entity::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'Iglesia Central',
            'is_active' => true,
        ]);

        $comedor = Entity::create([
            'entity_type_id' => $this->entityType->id,
            'name' => 'Comedor Los Bajitos',
            'is_active' => true,
        ]);

        // 1. Ingreso $10.000.000
        $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->ingresoCategory->id,
            'amount' => '10000000.00',
            'concept' => 'Donación principal recibida',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertSame('10000000.00', $this->treasuryService->getGeneralBalance());

        // 2. Entrega a Iglesia de $2.000.000
        $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'entity_id' => $iglesia->id,
            'category_id' => $this->egresoCategory->id,
            'amount' => '2000000.00',
            'concept' => 'Ayuda enviada a Iglesia Central',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertSame('8000000.00', $this->treasuryService->getGeneralBalance());

        // 3. Entrega a Comedor de $500.000
        $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'entity_id' => $comedor->id,
            'category_id' => $this->egresoCategory->id,
            'amount' => '500000.00',
            'concept' => 'Partida alimentos',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertSame('7500000.00', $this->treasuryService->getGeneralBalance());

        // 4. Gasto general sin entidad de $100.000
        $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'entity_id' => null,
            'category_id' => $this->egresoCategory->id,
            'amount' => '100000.00',
            'concept' => 'Insumos de oficina y librería',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertSame('7400000.00', $this->treasuryService->getGeneralBalance());
    }

    public function test_cannot_expense_more_than_available_funds(): void
    {
        $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->ingresoCategory->id,
            'amount' => '5000.00',
            'concept' => 'Ingreso menor',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->expectException(InsufficientFundsException::class);

        // Intentar gastar 6000 cuando hay 5000 disponibles
        $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'category_id' => $this->egresoCategory->id,
            'amount' => '6000.00',
            'concept' => 'Gasto mayor al saldo',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);
    }

    public function test_cannot_use_mismatched_category_flow(): void
    {
        $this->expectException(InvalidMovementException::class);
        $this->expectExceptionMessage('no corresponde a un flujo de tipo ingreso');

        // Intentar registrar ingreso con categoría de egreso
        $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->egresoCategory->id,
            'amount' => '1000.00',
            'concept' => 'Categoría errónea',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);
    }

    public function test_cannot_void_income_if_it_leaves_negative_balance(): void
    {
        $income = $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->ingresoCategory->id,
            'amount' => '10000.00',
            'concept' => 'Ingreso inicial',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'category_id' => $this->egresoCategory->id,
            'amount' => '8000.00',
            'concept' => 'Gasto realizado',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        // En caja quedan 2000. Si anulamos el ingreso de 10000, el saldo quedaría en -8000
        $this->expectException(CannotVoidException::class);

        $this->treasuryService->voidMovement($income, 'Intento anular ingreso gastado', $this->tesorera);
    }

    public function test_voiding_expense_restores_available_balance(): void
    {
        $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->ingresoCategory->id,
            'amount' => '10000.00',
            'concept' => 'Ingreso inicial',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $expense = $this->treasuryService->recordMovement([
            'type' => MovementType::Egreso,
            'category_id' => $this->egresoCategory->id,
            'amount' => '3000.00',
            'concept' => 'Gasto por error',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertSame('7000.00', $this->treasuryService->getGeneralBalance());

        $this->treasuryService->voidMovement($expense, 'Transferencia duplicada / cancelada', $this->tesorera);

        // Al anular el egreso, el disponible vuelve a 10.000
        $this->assertSame('10000.00', $this->treasuryService->getGeneralBalance());
        $this->assertTrue($expense->isVoided());
    }

    public function test_audit_logs_are_registered_on_movement_actions(): void
    {
        $movement = $this->treasuryService->recordMovement([
            'type' => MovementType::Ingreso,
            'category_id' => $this->ingresoCategory->id,
            'amount' => '5000.00',
            'concept' => 'Aporte con auditoria',
            'movement_date' => '2026-10-27',
        ], $this->tesorera);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'movement.created',
            'auditable_type' => Movement::class,
            'auditable_id' => $movement->id,
            'user_id' => $this->tesorera->id,
        ]);

        $this->treasuryService->voidMovement($movement, 'Prueba auditoria', $this->tesorera);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'movement.voided',
            'auditable_type' => Movement::class,
            'auditable_id' => $movement->id,
            'user_id' => $this->tesorera->id,
        ]);
    }
}
