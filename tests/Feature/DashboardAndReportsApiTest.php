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
use Tests\TestCase;

class DashboardAndReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $tesorera;
    protected User $contador;
    protected Fund $generalFund;
    protected Category $ingresoCat;
    protected Category $egresoCat;
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
        $this->ingresoCat = Category::where('flow', 'ingreso')->firstOrFail();
        $this->egresoCat = Category::where('flow', 'egreso')->firstOrFail();

        $type = EntityType::firstOrFail();
        $this->entity = Entity::create([
            'entity_type_id' => $type->id,
            'name' => 'Comedor Los Pibes',
            'is_active' => true,
        ]);

        // Cargar movimientos de prueba
        Movement::create([
            'type' => 'ingreso',
            'to_fund_id' => $this->generalFund->id,
            'category_id' => $this->ingresoCat->id,
            'amount' => '1000000.00',
            'concept' => 'Donación grande',
            'movement_date' => now()->toDateString(),
            'created_by' => $this->tesorera->id,
        ]);

        Movement::create([
            'type' => 'egreso',
            'from_fund_id' => $this->generalFund->id,
            'category_id' => $this->egresoCat->id,
            'entity_id' => $this->entity->id,
            'amount' => '250000.00',
            'concept' => 'Ayuda mensual comedor',
            'movement_date' => now()->toDateString(),
            'created_by' => $this->tesorera->id,
        ]);
    }

    public function test_dashboard_returns_metrics(): void
    {
        $response = $this->actingAs($this->tesorera, 'sanctum')->getJson('/api/dashboard');

        $response->assertOk()
            ->assertJsonPath('general_available', '750000.00')
            ->assertJsonPath('total_funds', '750000.00')
            ->assertJsonPath('month.incomes', '1000000.00')
            ->assertJsonPath('month.expenses', '250000.00')
            ->assertJsonStructure([
                'general_available',
                'month' => ['incomes', 'expenses'],
                'monthly',
                'expenses_by_entity_type',
                'latest_movements',
            ]);
    }

    public function test_reports_summary_returns_aggregated_data(): void
    {
        $response = $this->actingAs($this->contador, 'sanctum')->getJson('/api/reports/summary');

        $response->assertOk()
            ->assertJsonPath('totals.incomes', '1000000.00')
            ->assertJsonPath('totals.expenses', '250000.00')
            ->assertJsonPath('totals.net', '750000.00')
            ->assertJsonPath('totals.count_total', 2)
            ->assertJsonStructure([
                'totals' => ['incomes', 'expenses', 'net', 'count_total', 'count_voided'],
                'by_category',
                'by_entity',
            ]);
    }

    public function test_reports_export_pdf(): void
    {
        $response = $this->actingAs($this->tesorera, 'sanctum')->get('/api/reports/export?format=pdf');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'report.exported',
            'user_id' => $this->tesorera->id,
        ]);
    }

    public function test_reports_export_xlsx(): void
    {
        $response = $this->actingAs($this->contador, 'sanctum')->get('/api/reports/export?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'report.exported',
            'user_id' => $this->contador->id,
        ]);
    }
}
