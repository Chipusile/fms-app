<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\User;

class DriverPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('drivers.view');
    }

    public function view(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $user->hasPermission('drivers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('drivers.create');
    }

    public function update(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $user->hasPermission('drivers.update');
    }

    public function delete(User $user, Driver $driver): bool
    {
        return $user->tenant_id === $driver->tenant_id
            && $user->hasPermission('drivers.delete');
    }
}
