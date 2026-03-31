<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleAssignment;

class VehicleAssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vehicles.view');
    }

    public function view(User $user, VehicleAssignment $vehicleAssignment): bool
    {
        return $user->tenant_id === $vehicleAssignment->tenant_id
            && $user->hasPermission('vehicles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vehicles.assign');
    }

    public function update(User $user, VehicleAssignment $vehicleAssignment): bool
    {
        return $user->tenant_id === $vehicleAssignment->tenant_id
            && $user->hasPermission('vehicles.assign');
    }

    public function delete(User $user, VehicleAssignment $vehicleAssignment): bool
    {
        return $user->tenant_id === $vehicleAssignment->tenant_id
            && $user->hasPermission('vehicles.assign');
    }
}
