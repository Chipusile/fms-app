<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->tenant_id === $role->tenant_id
            && $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->tenant_id === $role->tenant_id
            && $user->hasPermission('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->tenant_id === $role->tenant_id
            && ! $role->is_system
            && $user->hasPermission('roles.delete');
    }
}
