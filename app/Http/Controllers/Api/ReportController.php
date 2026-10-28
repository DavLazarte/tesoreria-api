<?php

namespace App\Http\Controllers\Api;

use App\Exports\MovementsExport;
use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Services\AuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Resumen y estadísticas para informes filtrados por fecha, tipo, entidad o categoría.
     */
    public function summary(Request $request): JsonResponse
    {
        $query = $this->buildFilteredQuery($request);

        // Totales generales filtrados (no anulados)
        $activeQuery = (clone $query)->whereDoesntHave('void');

        $totalIncomes = (clone $activeQuery)->where('type', 'ingreso')->sum('amount');
        $totalExpenses = (clone $activeQuery)->where('type', 'egreso')->sum('amount');
        $totalIncomesStr = number_format((float) $totalIncomes, 2, '.', '');
        $totalExpensesStr = number_format((float) $totalExpenses, 2, '.', '');
        $netBalanceStr = bcsub($totalIncomesStr, $totalExpensesStr, 2);

        // Desglose por Categoría
        $byCategory = DB::table('movements as m')
            ->leftJoin('movement_voids as v', 'v.movement_id', '=', 'm.id')
            ->join('categories as c', 'c.id', '=', 'm.category_id')
            ->whereNull('v.id')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('m.movement_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('m.movement_date', '<=', $request->input('to')))
            ->when($request->filled('type'), fn ($q) => $q->where('m.type', $request->input('type')))
            ->select('c.name as category_name', 'c.flow')
            ->selectRaw('SUM(m.amount) as total_amount')
            ->selectRaw('COUNT(m.id) as count')
            ->groupBy('c.name', 'c.flow')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category_name,
                'flow' => $r->flow,
                'total' => number_format((float) $r->total_amount, 2, '.', ''),
                'count' => (int) $r->count,
            ]);

        // Desglose por Entidad
        $byEntity = DB::table('movements as m')
            ->leftJoin('movement_voids as v', 'v.movement_id', '=', 'm.id')
            ->join('entities as e', 'e.id', '=', 'm.entity_id')
            ->join('entity_types as et', 'et.id', '=', 'e.entity_type_id')
            ->whereNull('v.id')
            ->when($request->filled('from'), fn ($q) => $q->whereDate('m.movement_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('m.movement_date', '<=', $request->input('to')))
            ->when($request->filled('type'), fn ($q) => $q->where('m.type', $request->input('type')))
            ->select('e.id as entity_id', 'e.name as entity_name', 'et.name as entity_type_name')
            ->selectRaw('SUM(m.amount) as total_amount')
            ->selectRaw('COUNT(m.id) as count')
            ->groupBy('e.id', 'e.name', 'et.name')
            ->orderByDesc('total_amount')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'entity_id' => $r->entity_id,
                'entity_name' => $r->entity_name,
                'entity_type' => $r->entity_type_name,
                'total' => number_format((float) $r->total_amount, 2, '.', ''),
                'count' => (int) $r->count,
            ]);

        return response()->json([
            'totals' => [
                'incomes' => $totalIncomesStr,
                'expenses' => $totalExpensesStr,
                'net' => $netBalanceStr,
                'count_total' => $query->count(),
                'count_voided' => (clone $query)->has('void')->count(),
            ],
            'by_category' => $byCategory,
            'by_entity' => $byEntity,
        ]);
    }

    /**
     * Exportación de informe en PDF o Excel (.xlsx).
     */
    public function export(Request $request): Response|BinaryFileResponse|JsonResponse
    {
        $format = strtolower((string) $request->input('format', 'pdf'));

        if (!in_array($format, ['pdf', 'xlsx'], true)) {
            return response()->json(['message' => 'Formato no soportado. Elija pdf o xlsx.'], 422);
        }

        $query = $this->buildFilteredQuery($request);
        $movements = $query->with(['category', 'entity', 'creator', 'void'])->orderBy('movement_date')->get();

        // Registrar auditoría de la exportación
        $this->auditService->log(
            event: 'report.exported',
            model: null,
            old: null,
            new: [
                'format' => $format,
                'filters' => $request->only(['from', 'to', 'type', 'category_id', 'entity_id']),
                'count' => $movements->count(),
            ],
            userId: $request->user()->id,
            modelType: 'Report'
        );

        $nowStr = Carbon::now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(
                new MovementsExport($movements),
                "informe_movimientos_{$nowStr}.xlsx"
            );
        }

        // Formato PDF
        $activeMovements = $movements->where(fn ($m) => !$m->isVoided());
        $totalIncomes = $activeMovements->where('type', 'ingreso')->sum('amount');
        $totalExpenses = $activeMovements->where('type', 'egreso')->sum('amount');
        $totalIncomesStr = number_format((float) $totalIncomes, 2, '.', '');
        $totalExpensesStr = number_format((float) $totalExpenses, 2, '.', '');
        $netBalanceStr = bcsub($totalIncomesStr, $totalExpensesStr, 2);

        $from = $request->input('from', 'Inicio');
        $to = $request->input('to', 'Hoy');

        $pdf = Pdf::loadView('reports.movements_pdf', [
            'movements' => $movements,
            'period' => "{$from} al {$to}",
            'generated_at' => Carbon::now()->format('d/m/Y H:i'),
            'user_name' => $request->user()->name,
            'total_incomes' => $totalIncomesStr,
            'total_expenses' => $totalExpensesStr,
            'net_balance' => $netBalanceStr,
        ])->setPaper('a4', 'landscape');

        return $pdf->download("informe_movimientos_{$nowStr}.pdf");
    }

    /**
     * Construye la consulta filtrada según los parámetros recibidos.
     */
    protected function buildFilteredQuery(Request $request): Builder
    {
        $query = Movement::query();

        if ($request->filled('from')) {
            $query->whereDate('movement_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('movement_date', '<=', $request->input('to'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        return $query;
    }
}
