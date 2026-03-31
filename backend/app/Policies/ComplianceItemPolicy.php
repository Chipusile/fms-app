<?php

namespace App\Policies;

use App\Models\ComplianceItem;
use App\Models\User;

class ComplianceItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('compliance.view');
    }

    public function view(User $user, ComplianceItem $complianceItem): bool
    {
        return $user->tenant_id === $complianceItem->tenant_id
            && $user->hasPermission('compliance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('compliance.create');
    }

    public function update(User $user, ComplianceItem $complianceItem): bool
    {
        return $user->tenant_id === $complianceItem->tenant_id
            && $user->hasPermission('compliance.update');
    }

    public function delete(User $user, ComplianceItem $complianceItem): bool
    {
        return $user->tenant_id === $complianceItem->tenant_id
            && $user->hasPermission('compliance.delete');
    }
}
