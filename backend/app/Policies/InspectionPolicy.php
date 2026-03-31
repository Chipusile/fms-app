<?php

namespace App\Policies;

use App\Models\Inspection;
use App\Models\User;

class InspectionPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inspections.view');
    }

    public function view(User $user, Inspection $inspection): bool
    {
        return $user->tenant_id === $inspection->tenant_id
            && $user->hasPermission('inspections.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inspections.create');
    }

    public function close(User $user, Inspection $inspection): bool
    {
        return $user->tenant_id === $inspection->tenant_id
            && $user->hasPermission('inspections.update');
    }
}
