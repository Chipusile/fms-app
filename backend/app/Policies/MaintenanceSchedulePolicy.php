<?php

namespace App\Policies;

use App\Models\MaintenanceSchedule;
use App\Models\User;

class MaintenanceSchedulePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->tenant_id === $maintenanceSchedule->tenant_id
            && $user->hasPermission('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.create');
    }

    public function update(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->tenant_id === $maintenanceSchedule->tenant_id
            && $user->hasPermission('maintenance.update');
    }

    public function delete(User $user, MaintenanceSchedule $maintenanceSchedule): bool
    {
        return $user->tenant_id === $maintenanceSchedule->tenant_id
            && $user->hasPermission('maintenance.delete');
    }
}
