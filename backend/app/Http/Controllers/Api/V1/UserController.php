<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->visibleTo($request->user())
            ->with('roles')
            ->when($request->input('filter.status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('filter.role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('slug', $role)))
            ->when($request->input('search'), fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            }))
            ->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))
            ->paginate($request->input('per_page', 15));

        return ApiResponse::success(
            UserResource::collection($users),
            meta: [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ]
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except('role_ids'));

            if ($request->has('role_ids')) {
                $user->roles()->sync($request->input('role_ids'));
            }

            return $user;
        });

        return ApiResponse::created(
            new UserResource($user->load('roles'))
        );
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return ApiResponse::success(
            new UserResource($user->load('roles.permissions', 'tenant'))
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        DB::transaction(function () use ($request, $user) {
            $user->update($request->safe()->except('role_ids'));

            if ($request->has('role_ids')) {
                $user->roles()->sync($request->input('role_ids'));
            }
        });

        return ApiResponse::success(
            new UserResource($user->fresh()->load('roles')),
            'User updated successfully.'
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->id === auth()->id()) {
            return ApiResponse::error('You cannot delete your own account.', 422);
        }

        $user->delete(); // Soft delete

        return ApiResponse::noContent('User deactivated successfully.');
    }
}
