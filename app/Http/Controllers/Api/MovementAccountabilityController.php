<?php

namespace App\Http\Controllers\Api;

use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Movement\StoreAccountabilityRequest;
use App\Http\Resources\MovementAccountabilityResource;
use App\Models\AccountabilityAttachment;
use App\Models\Movement;
use App\Models\MovementAccountability;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovementAccountabilityController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Listar rendiciones de cuentas con relaciones y filtros.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MovementAccountability::query()
            ->with([
                'movement.entity.entityType',
                'movement.category',
                'attachments',
                'creator',
            ])
            ->latest('rendered_at')
            ->latest('id');

        if ($request->filled('entity_id')) {
            $query->whereHas('movement', function ($q) use ($request) {
                $q->where('entity_id', $request->input('entity_id'));
            });
        }

        if ($request->filled('movement_id')) {
            $query->where('movement_id', $request->input('movement_id'));
        }

        if ($request->filled('from')) {
            $query->where('rendered_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('rendered_at', '<=', $request->input('to'));
        }

        if ($request->filled('q')) {
            $term = '%' . $request->input('q') . '%';
            $query->where(function ($sub) use ($term) {
                $sub->where('description', 'like', $term)
                    ->orWhereHas('movement', function ($m) use ($term) {
                        $m->where('concept', 'like', $term)
                            ->orWhere('reference', 'like', $term)
                            ->orWhereHas('entity', function ($e) use ($term) {
                                $e->where('name', 'like', $term);
                            });
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

        return MovementAccountabilityResource::collection($query->paginate($perPage));
    }

    /**
     * Registrar una nueva rendición de cuentas para un movimiento de egreso.
     */
    public function store(StoreAccountabilityRequest $request, Movement $movement): JsonResponse
    {
        if ($movement->isVoided()) {
            return response()->json([
                'message' => 'No se puede rendir cuentas sobre un movimiento anulado.',
            ], 422);
        }

        if ($movement->type !== MovementType::Egreso) {
            return response()->json([
                'message' => 'Solo se pueden registrar rendiciones de cuentas en movimientos de tipo egreso.',
            ], 422);
        }

        $accountability = DB::transaction(function () use ($request, $movement) {
            $accountability = MovementAccountability::create([
                'movement_id' => $movement->id,
                'rendered_at' => $request->input('rendered_at'),
                'amount' => $request->filled('amount') ? $request->input('amount') : null,
                'description' => $request->input('description'),
                'created_by' => $request->user()->id,
            ]);

            // Cargar archivos adjuntos (boletas, tickets, facturas)
            $files = $request->file('files');
            if (empty($files) && $request->hasFile('file')) {
                $files = [$request->file('file')];
            }

            if (!empty($files)) {
                $disk = 'local';
                foreach ($files as $file) {
                    $hash = hash_file('sha256', $file->getRealPath());
                    $path = $file->store("attachments/accountabilities/{$accountability->id}", $disk);

                    AccountabilityAttachment::create([
                        'accountability_id' => $accountability->id,
                        'disk' => $disk,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size_bytes' => $file->getSize(),
                        'sha256' => $hash,
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
            }

            return $accountability;
        });

        $accountability->load(['creator', 'attachments.uploader']);

        $this->auditService->log(
            event: 'movement.accountability_created',
            model: $movement,
            old: null,
            new: [
                'accountability_id' => $accountability->id,
                'rendered_at' => $accountability->rendered_at?->format('Y-m-d'),
                'amount' => $accountability->amount,
                'description' => $accountability->description,
                'attachments_count' => $accountability->attachments->count(),
            ],
            userId: $request->user()->id
        );

        return (new MovementAccountabilityResource($accountability))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Descargar un comprobante o boleta adjunto a una rendición de cuentas.
     */
    public function downloadAttachment(
        Movement $movement,
        MovementAccountability $accountability,
        AccountabilityAttachment $attachment
    ): StreamedResponse|JsonResponse {
        if ($accountability->movement_id !== $movement->id) {
            return response()->json(['message' => 'La rendición no pertenece a este movimiento.'], 404);
        }

        if ($attachment->accountability_id !== $accountability->id) {
            return response()->json(['message' => 'El comprobante no pertenece a esta rendición de cuentas.'], 404);
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
