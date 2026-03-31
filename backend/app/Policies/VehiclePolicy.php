<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicles.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->tenant_id === $vehicle->tenant_id
            && $user->hasPermission('vehicles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicles.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->tenant_id === $vehicle->tenant_id
            && $user->hasPermission('vehicles.update');
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->tenant_id === $vehicle->tenant_id
            && $user->hasPermission('vehicles.delete');
    }
}
