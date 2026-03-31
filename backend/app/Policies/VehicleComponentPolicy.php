<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleComponent;

class VehicleComponentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, VehicleComponent $vehicleComponent): bool
    {
        return $user->tenant_id === $vehicleComponent->tenant_id
            && $user->hasPermission('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.create');
    }

    public function update(User $user, VehicleComponent $vehicleComponent): bool
    {
        return $user->tenant_id === $vehicleComponent->tenant_id
            && $user->hasPermission('maintenance.update');
    }

    public function delete(User $user, VehicleComponent $vehicleComponent): bool
    {
        return $user->tenant_id === $vehicleComponent->tenant_id
            && $user->hasPermission('maintenance.delete');
    }
}
