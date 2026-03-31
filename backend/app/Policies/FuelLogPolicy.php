<?php

namespace App\Policies;

use App\Models\FuelLog;
use App\Models\User;

class FuelLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('fuel.view');
    }

    public function view(User $user, FuelLog $fuelLog): bool
    {
        return $user->tenant_id === $fuelLog->tenant_id
            && $user->hasPermission('fuel.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('fuel.create');
    }

    public function update(User $user, FuelLog $fuelLog): bool
    {
        return $user->tenant_id === $fuelLog->tenant_id
            && $user->hasPermission('fuel.update');
    }

    public function delete(User $user, FuelLog $fuelLog): bool
    {
        return $user->tenant_id === $fuelLog->tenant_id
            && $user->hasPermission('fuel.delete');
    }
}
