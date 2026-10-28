<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntityType\StoreEntityTypeRequest;
use App\Http\Requests\EntityType\UpdateEntityTypeRequest;
use App\Http\Resources\EntityTypeResource;
use App\Models\EntityType;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntityTypeController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = EntityType::query()->withCount('entities');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return EntityTypeResource::collection($query->orderBy('name')->get());
    }

    public function store(StoreEntityTypeRequest $request): JsonResponse
    {
        $entityType = EntityType::create($request->validated());

        $this->auditService->log(
            event: 'entity_type.created',
            model: $entityType,
            old: null,
            new: $entityType->toArray(),
            userId: $request->user()->id
        );

        return (new EntityTypeResource($entityType))
            ->response()
            ->setStatusCode(201);
    }

    public function show(EntityType $entityType): EntityTypeResource
    {
        $entityType->loadCount('entities');

        return new EntityTypeResource($entityType);
    }

    public function update(UpdateEntityTypeRequest $request, EntityType $entityType): EntityTypeResource
    {
        $old = $entityType->toArray();
        $entityType->update($request->validated());

        $this->auditService->log(
            event: 'entity_type.updated',
            model: $entityType,
            old: $old,
            new: $entityType->toArray(),
            userId: $request->user()->id
        );

        return new EntityTypeResource($entityType);
    }
}
