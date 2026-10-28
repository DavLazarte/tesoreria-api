<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Registra un evento de auditoría en audit_logs.
     *
     * @param string $event Nombre del evento (ej: 'movement.created', 'movement.voided')
     * @param Model|null $model Modelo afectado
     * @param array<string, mixed>|null $old Valores anteriores
     * @param array<string, mixed>|null $new Valores nuevos
     * @param int|null $userId Usuario que ejecuta la acción (por defecto Auth::id())
     */
    public function log(
        string $event,
        ?Model $model = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
        ?string $modelType = null
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $model ? get_class($model) : ($modelType ?? 'System'),
            'auditable_id' => $model?->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
