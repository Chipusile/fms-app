<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id
            && $user->hasPermission('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.create');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id
            && $user->hasPermission('maintenance.update');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $user->tenant_id === $workOrder->tenant_id
            && $user->hasPermission('maintenance.delete');
    }

    public function start(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    public function complete(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }

    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        return $this->update($user, $workOrder);
    }
}
