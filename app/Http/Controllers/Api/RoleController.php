<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Resources\RoleResource;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()->with('permissions')->get();

        return RoleResource::collection($roles);
    }

    public function updatePermissions(SyncRolePermissionsRequest $request, Role $role): RoleResource
    {
        $oldPermissions = $role->permissions->pluck('name')->all();
        $newPermissions = $request->input('permissions', []);

        $role->syncPermissions($newPermissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->auditService->log(
            event: 'role.permissions_changed',
            model: $role,
            old: ['permissions' => $oldPermissions],
            new: ['permissions' => $newPermissions],
            userId: $request->user()->id
        );

        $role->load('permissions');

        return new RoleResource($role);
    }
}
