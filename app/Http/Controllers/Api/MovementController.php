<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Movement\StoreAttachmentRequest;
use App\Http\Requests\Movement\StoreMovementRequest;
use App\Http\Requests\Movement\VoidMovementRequest;
use App\Http\Resources\MovementAttachmentResource;
use App\Http\Resources\MovementResource;
use App\Models\Movement;
use App\Models\MovementAttachment;
use App\Services\AuditService;
use App\Services\TreasuryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovementController extends Controller
{
    public function __construct(
        protected TreasuryService $treasuryService,
        protected AuditService $auditService
    ) {}

    /**
     * Listado de movimientos con filtros y paginación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Movement::query()
            ->with(['fromFund', 'toFund', 'entity.entityType', 'category', 'creator', 'void.user', 'attachments']);

        // Filtro por fecha desde / hasta
        if ($request->filled('from')) {
            $query->whereDate('movement_date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('movement_date', '<=', $request->input('to'));
        }

        // Filtro por tipo (ingreso, egreso)
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Filtro por entidad
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        // Filtro por categoría
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filtro por anulado (voided: 1 / 0)
        if ($request->has('voided')) {
            $isVoided = filter_var($request->input('voided'), FILTER_VALIDATE_BOOLEAN);
            if ($isVoided) {
                $query->has('void');
            } else {
                $query->doesntHave('void');
            }
        }

        // Búsqueda textual por concepto o referencia
        if ($request->filled('q')) {
            $search = '%'.$request->input('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('concept', 'like', $search)
                    ->orWhere('reference', 'like', $search)
                    ->orWhere('note', 'like', $search);
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $movements = $query->orderByDesc('movement_date')->orderByDesc('id')->paginate($perPage);

        return MovementResource::collection($movements);
    }

    /**
     * Registrar un nuevo movimiento de fondos.
     */
    public function store(StoreMovementRequest $request): JsonResponse
    {
        $movement = $this->treasuryService->recordMovement(
            data: $request->validated(),
            user: $request->user()
        );

        $movement->load(['fromFund', 'toFund', 'entity.entityType', 'category', 'creator']);

        return (new MovementResource($movement))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ver detalle completo de un movimiento.
     */
    public function show(Movement $movement): MovementResource
    {
        $movement->load(['fromFund', 'toFund', 'entity.entityType', 'category', 'creator', 'void.user', 'attachments.uploader']);

        return new MovementResource($movement);
    }

    /**
     * Anular un movimiento de fondos existente.
     */
    public function void(VoidMovementRequest $request, Movement $movement): JsonResponse
    {
        $this->treasuryService->voidMovement(
            movement: $movement,
            reason: $request->input('reason'),
            user: $request->user()
        );

        $movement->load(['fromFund', 'toFund', 'entity.entityType', 'category', 'creator', 'void.user', 'attachments.uploader']);

        return response()->json([
            'message' => 'Movimiento anulado correctamente.',
            'movement' => new MovementResource($movement),
        ]);
    }

    /**
     * Adjuntar comprobante (PDF o imagen) en storage privado.
     */
    public function storeAttachment(StoreAttachmentRequest $request, Movement $movement): JsonResponse
    {
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());

        $disk = 'local';
        $path = $file->store("attachments/movements/{$movement->id}", $disk);

        $attachment = MovementAttachment::create([
            'movement_id' => $movement->id,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'sha256' => $hash,
            'uploaded_by' => $request->user()->id,
        ]);

        $this->auditService->log(
            event: 'movement.attachment_uploaded',
            model: $movement,
            old: null,
            new: [
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->original_name,
                'size_bytes' => $attachment->size_bytes,
            ],
            userId: $request->user()->id
        );

        return (new MovementAttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Descargar o visualizar comprobante adjunto.
     */
    public function downloadAttachment(Movement $movement, MovementAttachment $attachment): StreamedResponse|JsonResponse
    {
        if ($attachment->movement_id !== $movement->id) {
            return response()->json(['message' => 'El comprobante no pertenece a este movimiento.'], 404);
        }

        if (!Storage::disk($attachment->disk)->exists($attachment->path)) {
            return response()->json(['message' => 'El archivo físico no fue encontrado en el servidor.'], 404);
        }

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }
}
