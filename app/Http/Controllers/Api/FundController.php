<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fund\StoreFundRequest;
use App\Http\Requests\Fund\UpdateFundRequest;
use App\Http\Resources\FundResource;
use App\Models\Fund;
use App\Services\AuditService;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FundController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected TreasuryService $treasuryService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Fund::query();

        if ($request->filled('kind')) {
            $query->where('kind', $request->input('kind'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $funds = $query->orderBy('kind')->orderBy('name')->get();

        // Calcular saldo para cada caja/grupo
        $funds->each(function (Fund $fund) {
            $fund->balance = $this->treasuryService->getFundBalance($fund->id);
        });

        return FundResource::collection($funds);
    }

    public function store(StoreFundRequest $request): JsonResponse
    {
        $fund = Fund::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        $fund->balance = '0.00';

        $this->auditService->log(
            event: 'fund.created',
            model: $fund,
            old: null,
            new: $fund->toArray(),
            userId: $request->user()->id
        );

        return (new FundResource($fund))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Fund $fund): FundResource
    {
        $fund->balance = $this->treasuryService->getFundBalance($fund->id);

        return new FundResource($fund);
    }

    public function update(UpdateFundRequest $request, Fund $fund): FundResource
    {
        $old = $fund->toArray();
        $fund->update($request->validated());
        $fund->balance = $this->treasuryService->getFundBalance($fund->id);

        $this->auditService->log(
            event: 'fund.updated',
            model: $fund,
            old: $old,
            new: $fund->toArray(),
            userId: $request->user()->id
        );

        return new FundResource($fund);
    }
}
