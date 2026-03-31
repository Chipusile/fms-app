<?php

namespace App\Policies;

use App\Models\ServiceProvider;
use App\Models\User;

class ServiceProviderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('vendors.view');
    }

    public function view(User $user, ServiceProvider $serviceProvider): bool
    {
        return $user->tenant_id === $serviceProvider->tenant_id
            && $user->hasPermission('vendors.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('vendors.create');
    }

    public function update(User $user, ServiceProvider $serviceProvider): bool
    {
        return $user->tenant_id === $serviceProvider->tenant_id
            && $user->hasPermission('vendors.update');
    }

    public function delete(User $user, ServiceProvider $serviceProvider): bool
    {
        return $user->tenant_id === $serviceProvider->tenant_id
            && $user->hasPermission('vendors.delete');
    }
}
