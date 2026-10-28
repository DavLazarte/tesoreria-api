<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\EntityTypeController;
use App\Http\Controllers\Api\FundController;
use App\Http\Controllers\Api\MovementController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de la API de Tesorería
|--------------------------------------------------------------------------
*/

// Autenticación pública
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// Rutas protegidas por autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard
    Route::get('/dashboard', DashboardController::class)->middleware('permission:movements.view');

    // Informes y Exportación
    Route::get('/reports/summary', [ReportController::class, 'summary'])->middleware('permission:reports.view');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('permission:reports.export');

    // Movimientos y comprobantes
    Route::get('/movements', [MovementController::class, 'index'])->middleware('permission:movements.view');
    Route::post('/movements', [MovementController::class, 'store'])->middleware('permission:movements.create');
    Route::get('/movements/{movement}', [MovementController::class, 'show'])->middleware('permission:movements.view');
    Route::post('/movements/{movement}/void', [MovementController::class, 'void'])->middleware('permission:movements.void');
    Route::post('/movements/{movement}/attachments', [MovementController::class, 'storeAttachment'])->middleware('permission:movements.create');
    Route::get('/movements/{movement}/attachments/{attachment}', [MovementController::class, 'downloadAttachment'])->middleware('permission:movements.view');

    // Entidades
    Route::get('/entities', [EntityController::class, 'index'])->middleware('permission:entities.view');
    Route::get('/entities/{entity}', [EntityController::class, 'show'])->middleware('permission:entities.view');
    Route::post('/entities', [EntityController::class, 'store'])->middleware('permission:entities.manage');
    Route::put('/entities/{entity}', [EntityController::class, 'update'])->middleware('permission:entities.manage');
    Route::delete('/entities/{entity}', [EntityController::class, 'destroy'])->middleware('permission:entities.manage');

    // Tipos de Entidad
    Route::get('/entity-types', [EntityTypeController::class, 'index'])->middleware('permission:entities.view');
    Route::get('/entity-types/{entity_type}', [EntityTypeController::class, 'show'])->middleware('permission:entities.view');
    Route::post('/entity-types', [EntityTypeController::class, 'store'])->middleware('permission:entities.manage');
    Route::put('/entity-types/{entity_type}', [EntityTypeController::class, 'update'])->middleware('permission:entities.manage');

    // Categorías
    Route::get('/categories', [CategoryController::class, 'index'])->middleware('permission:entities.view');
    Route::get('/categories/{category}', [CategoryController::class, 'show'])->middleware('permission:entities.view');
    Route::post('/categories', [CategoryController::class, 'store'])->middleware('permission:entities.manage');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->middleware('permission:entities.manage');

    // Cajas (Funds)
    Route::get('/funds', [FundController::class, 'index'])->middleware('permission:funds.view');
    Route::get('/funds/{fund}', [FundController::class, 'show'])->middleware('permission:funds.view');
    Route::post('/funds', [FundController::class, 'store'])->middleware('permission:funds.manage');
    Route::put('/funds/{fund}', [FundController::class, 'update'])->middleware('permission:funds.manage');

    // Usuarios y Roles
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.manage');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:users.manage');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('permission:users.manage');

    // Auditoría y Registros de acceso
    Route::get('/audit-logs', [AuditLogController::class, 'auditLogs'])->middleware('permission:audit.view');
    Route::get('/access-logs', [AuditLogController::class, 'accessLogs'])->middleware('permission:audit.view');
});
