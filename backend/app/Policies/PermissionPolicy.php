<?php

namespace App\Policies;

use App\Models\User;

class PermissionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }
}
