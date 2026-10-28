<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('roles');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('role')) {
            $query->role($request->input('role'));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->input('q').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        $users = $query->orderBy('name')->paginate((int) $request->input('per_page', 20));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->assignRole($role);
        $user->load('roles');

        $this->auditService->log(
            event: 'user.created',
            model: $user,
            old: null,
            new: [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role,
                'is_active' => (bool) $user->is_active,
            ],
            userId: $request->user()->id
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $user->load('roles');

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('name')->all(),
            'is_active' => (bool) $user->is_active,
        ];

        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
            unset($data['role']);
        }

        $user->update($data);
        $user->load('roles');

        $this->auditService->log(
            event: 'user.updated',
            model: $user,
            old: $old,
            new: [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
                'is_active' => (bool) $user->is_active,
            ],
            userId: $request->user()->id
        );

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'No podés eliminar tu propia cuenta de usuario.',
            ], 422);
        }

        $old = $user->toArray();
        $user->delete();

        $this->auditService->log(
            event: 'user.deleted',
            model: $user,
            old: $old,
            new: ['deleted_at' => now()->toIso8601String()],
            userId: $request->user()->id
        );

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }
}
