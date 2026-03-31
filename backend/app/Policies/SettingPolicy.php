<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('settings.view');
    }

    public function updateAny(User $user): bool
    {
        return $user->hasPermission('settings.update');
    }
}
