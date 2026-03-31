<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\StoreRoleRequest;
use App\Http\Requests\Api\V1\UpdateRoleRequest;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions')
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            RoleResource::collection($roles),
            meta: [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ]
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $role = DB::transaction(function () use ($request) {
            $role = Role::create($request->safe()->except('permission_ids'));

            if ($request->has('permission_ids')) {
                $role->permissions()->sync($request->input('permission_ids'));
            }

            return $role;
        });

        return ApiResponse::created(
            new RoleResource($role->load('permissions'))
        );
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return ApiResponse::success(
            new RoleResource($role->load('permissions'))
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        if ($role->is_system) {
            return ApiResponse::error('System roles cannot be modified.', 422);
        }

        DB::transaction(function () use ($request, $role) {
            $role->update($request->safe()->except('permission_ids'));

            if ($request->has('permission_ids')) {
                $role->permissions()->sync($request->input('permission_ids'));
            }
        });

        return ApiResponse::success(
            new RoleResource($role->fresh()->load('permissions')),
            'Role updated successfully.'
        );
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if ($role->is_system) {
            return ApiResponse::error('System roles cannot be deleted.', 422);
        }

        if ($role->users()->exists()) {
            return ApiResponse::error('Cannot delete a role that is assigned to users.', 422);
        }

        $role->delete();

        return ApiResponse::noContent('Role deleted successfully.');
    }
}
