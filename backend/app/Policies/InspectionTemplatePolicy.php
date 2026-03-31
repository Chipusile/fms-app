<?php

namespace App\Policies;

use App\Models\InspectionTemplate;
use App\Models\User;

class InspectionTemplatePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inspection-templates.view');
    }

    public function view(User $user, InspectionTemplate $inspectionTemplate): bool
    {
        return $user->tenant_id === $inspectionTemplate->tenant_id
            && $user->hasPermission('inspection-templates.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inspection-templates.create');
    }

    public function update(User $user, InspectionTemplate $inspectionTemplate): bool
    {
        return $user->tenant_id === $inspectionTemplate->tenant_id
            && $user->hasPermission('inspection-templates.update');
    }

    public function delete(User $user, InspectionTemplate $inspectionTemplate): bool
    {
        return $user->tenant_id === $inspectionTemplate->tenant_id
            && $user->hasPermission('inspection-templates.delete');
    }
}
