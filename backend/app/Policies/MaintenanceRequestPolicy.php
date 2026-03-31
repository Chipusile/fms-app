<?php

namespace App\Policies;

use App\Models\MaintenanceRequest;
use App\Models\User;

class MaintenanceRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->tenant_id === $maintenanceRequest->tenant_id
            && $user->hasPermission('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.create');
    }

    public function update(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->tenant_id === $maintenanceRequest->tenant_id
            && $user->hasPermission('maintenance.update');
    }

    public function approve(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->tenant_id === $maintenanceRequest->tenant_id
            && $user->hasPermission('maintenance.approve');
    }

    public function convert(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $this->approve($user, $maintenanceRequest);
    }

    public function delete(User $user, MaintenanceRequest $maintenanceRequest): bool
    {
        return $user->tenant_id === $maintenanceRequest->tenant_id
            && $user->hasPermission('maintenance.delete');
    }
}
