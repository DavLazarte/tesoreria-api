<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Http\Resources\EntityResource;
use App\Models\Entity;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EntityController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Listar entidades con filtros y paginación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Entity::query()->with(['entityType', 'group'])->withCount('movements');

        if ($request->filled('type_id')) {
            $query->where('entity_type_id', $request->input('type_id'));
        }

        if ($request->filled('group_id')) {
            $query->where('group_id', $request->input('group_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->input('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('document_number', 'like', $search)
                    ->orWhere('phone', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $entities = $query->orderBy('name')->paginate($perPage);

        return EntityResource::collection($entities);
    }

    /**
     * Crear una nueva entidad.
     */
    public function store(StoreEntityRequest $request): JsonResponse
    {
        $entity = Entity::create($request->validated());
        $entity->load(['entityType', 'group']);

        $this->auditService->log(
            event: 'entity.created',
            model: $entity,
            old: null,
            new: $entity->toArray(),
            userId: $request->user()->id
        );

        return (new EntityResource($entity))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ver detalle de una entidad.
     */
    public function show(Entity $entity): EntityResource
    {
        $entity->load(['entityType', 'group'])->loadCount('movements');

        return new EntityResource($entity);
    }

    /**
     * Actualizar una entidad.
     */
    public function update(UpdateEntityRequest $request, Entity $entity): EntityResource
    {
        $old = $entity->toArray();
        $entity->update($request->validated());
        $entity->load(['entityType', 'group']);

        $this->auditService->log(
            event: 'entity.updated',
            model: $entity,
            old: $old,
            new: $entity->toArray(),
            userId: $request->user()->id
        );

        return new EntityResource($entity);
    }

    /**
     * Eliminación lógica (soft delete) de una entidad.
     */
    public function destroy(Request $request, Entity $entity): JsonResponse
    {
        $old = $entity->toArray();
        $entity->delete();

        $this->auditService->log(
            event: 'entity.deleted',
            model: $entity,
            old: $old,
            new: ['deleted_at' => now()->toIso8601String()],
            userId: $request->user()->id
        );

        return response()->json([
            'message' => 'Entidad eliminada correctamente.',
        ]);
    }
}
