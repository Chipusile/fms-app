<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleType;

class VehicleTypePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicle-types.view');
    }

    public function view(User $user, VehicleType $vehicleType): bool
    {
        return $user->tenant_id === $vehicleType->tenant_id
            && $user->hasPermission('vehicle-types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicle-types.create');
    }

    public function update(User $user, VehicleType $vehicleType): bool
    {
        return $user->tenant_id === $vehicleType->tenant_id
            && $user->hasPermission('vehicle-types.update');
    }

    public function delete(User $user, VehicleType $vehicleType): bool
    {
        return $user->tenant_id === $vehicleType->tenant_id
            && $user->hasPermission('vehicle-types.delete');
    }
}
