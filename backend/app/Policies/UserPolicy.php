<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $user->id !== $model->id
            && $user->hasPermission('users.delete');
    }
}
