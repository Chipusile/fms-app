<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->orderBy(
                $this->sortColumn($request, ['name', 'email', 'status', 'created_at', 'last_login_at'], 'name'),
                $this->sortDirection($request)
            )
            ->paginate($this->perPage($request, 15));

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
        $this->enforceTenantPlanLimit(
            $request,
            'users',
            User::query()->visibleTo($request->user())->count()
        );

        [$user, $invitation, $plainToken] = DB::transaction(function () use ($request) {
            $payload = $request->safe()->except('role_ids');
            $payload['password'] = Str::password(32);
            $payload['status'] = UserStatus::PendingActivation;
            $payload['email_verified_at'] = null;

            $user = User::create($payload);

            if ($request->has('role_ids')) {
                $user->roles()->sync($request->input('role_ids'));
            }

            UserInvitation::where('user_id', $user->id)
                ->whereNull('accepted_at')
                ->update(['accepted_at' => now()]);

            $plainToken = Str::random(64);
            $invitation = UserInvitation::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'invited_by' => $request->user()->id,
                'email' => $user->email,
                'token_hash' => hash('sha256', $plainToken),
                'role_ids' => $request->input('role_ids', []),
                'expires_at' => now()->addDays(7),
            ]);

            return [$user, $invitation, $plainToken];
        });

        $user->notify(new UserInvitationNotification($invitation, $plainToken));

        return ApiResponse::created(
            new UserResource($user->load('roles')),
            'User invited successfully.'
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
