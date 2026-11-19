<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MovementResource;
use App\Models\Movement;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService
    ) {}

    /**
     * Datos consolidados para el Panel / Dashboard de la tesorera.
     */
    public function __invoke(Request $request): JsonResponse
    {
        // 1. Saldo disponible real en Caja General
        $generalAvailable = $this->treasuryService->getGeneralBalance();

        // 2. Gastos reales (egresos no anulados) del mes en curso
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $expensesMonth = Movement::query()
            ->where('type', 'egreso')
            ->whereDoesntHave('void')
            ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $incomesMonth = Movement::query()
            ->where('type', 'ingreso')
            ->whereDoesntHave('void')
            ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Rendiciones de cuenta registradas en el mes
        $renderedMonth = \App\Models\MovementAccountability::query()
            ->whereBetween('rendered_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        // Egresos del mes pendientes de rendición
        $pendingAccountabilitiesCount = Movement::query()
            ->where('type', 'egreso')
            ->whereDoesntHave('void')
            ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
            ->doesntHave('accountabilities')
            ->count();

        // 3. Evolución mensual de los últimos 6 meses (ingresos vs egresos)
        $sixMonthsAgo = Carbon::now()->subMonths(5)->startOfMonth()->toDateString();

        $monthlyMovements = Movement::query()
            ->whereDoesntHave('void')
            ->where('movement_date', '>=', $sixMonthsAgo)
            ->selectRaw("DATE_FORMAT(movement_date, '%Y-%m') as ym")
            ->selectRaw("SUM(CASE WHEN type = 'ingreso' THEN amount ELSE 0 END) as total_incomes")
            ->selectRaw("SUM(CASE WHEN type = 'egreso' THEN amount ELSE 0 END) as total_expenses")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        $monthly = $monthlyMovements->map(function ($item) {
            return [
                'month' => $item->ym,
                'ingresos' => number_format((float) $item->total_incomes, 2, '.', ''),
                'egresos' => number_format((float) $item->total_expenses, 2, '.', ''),
            ];
        });

        // 4. Egresos agrupados por Tipo de Entidad (mes actual)
        $expensesByEntityType = DB::table('movements as m')
            ->leftJoin('movement_voids as v', 'v.movement_id', '=', 'm.id')
            ->leftJoin('entities as e', 'e.id', '=', 'm.entity_id')
            ->leftJoin('entity_types as et', 'et.id', '=', 'e.entity_type_id')
            ->whereNull('v.id')
            ->where('m.type', 'egreso')
            ->whereBetween('m.movement_date', [$startOfMonth, $endOfMonth])
            ->selectRaw("COALESCE(et.name, 'Gastos generales / Propios') as entity_type_name")
            ->selectRaw("SUM(m.amount) as total_amount")
            ->groupBy('entity_type_name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($r) => [
                'type' => $r->entity_type_name,
                'amount' => number_format((float) $r->total_amount, 2, '.', ''),
            ]);

        // 5. Últimos 10 movimientos
        $latestMovements = Movement::query()
            ->with(['entity', 'category', 'creator', 'void'])
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        // 6. Principales entidades destinatarias de fondos (Top 5 con entregas y rendiciones)
        $monthMovementsWithEntity = Movement::query()
            ->where('type', 'egreso')
            ->whereNotNull('entity_id')
            ->whereDoesntHave('void')
            ->whereBetween('movement_date', [$startOfMonth, $endOfMonth])
            ->with(['entity.entityType', 'accountabilities'])
            ->get();

        $topEntitiesSource = $monthMovementsWithEntity->isNotEmpty()
            ? $monthMovementsWithEntity
            : Movement::query()
                ->where('type', 'egreso')
                ->whereNotNull('entity_id')
                ->whereDoesntHave('void')
                ->with(['entity.entityType', 'accountabilities'])
                ->get();

        $topEntities = $topEntitiesSource->groupBy('entity_id')->map(function ($movs) {
            $first = $movs->first();
            $entity = $first->entity;
            $delivered = $movs->sum('amount');
            $rendered = $movs->flatMap->accountabilities->sum('amount');
            $pending = max(0, $delivered - $rendered);

            return [
                'id' => $entity->id,
                'name' => $entity->name,
                'type' => $entity->entityType?->name ?? 'Entidad',
                'delivered' => number_format((float) $delivered, 2, '.', ''),
                'rendered' => number_format((float) $rendered, 2, '.', ''),
                'pending' => number_format((float) $pending, 2, '.', ''),
                'movements_count' => $movs->count(),
            ];
        })->sortByDesc(fn ($item) => (float) $item['delivered'])->values()->take(5);

        return response()->json([
            'general_available' => $generalAvailable,
            'total_funds' => $generalAvailable, // En etapa 1 con Caja única, coincide exactamente
            'month' => [
                'incomes' => number_format((float) $incomesMonth, 2, '.', ''),
                'expenses' => number_format((float) $expensesMonth, 2, '.', ''),
                'rendered' => number_format((float) $renderedMonth, 2, '.', ''),
                'pending_count' => $pendingAccountabilitiesCount,
            ],
            'monthly' => $monthly,
            'expenses_by_entity_type' => $expensesByEntityType,
            'top_entities' => $topEntities,
            'latest_movements' => MovementResource::collection($latestMovements),
        ]);
    }
}
