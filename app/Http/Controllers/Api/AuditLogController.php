<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccessLogResource;
use App\Http\Resources\AuditLogResource;
use App\Models\AccessLog;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    /**
     * Listado de auditoría de acciones sobre datos de negocio (audit_logs).
     */
    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->with('user');

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
        $logs = $query->orderByDesc('id')->paginate($perPage);

        return AuditLogResource::collection($logs);
    }

    /**
     * Listado de accesos al sistema (access_logs).
     */
    public function accessLogs(Request $request): AnonymousResourceCollection
    {
        $query = AccessLog::query()->with('user');

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 25)));
        $logs = $query->orderByDesc('id')->paginate($perPage);

        return AccessLogResource::collection($logs);
    }
}
