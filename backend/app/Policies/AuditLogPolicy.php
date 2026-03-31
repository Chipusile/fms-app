<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit-logs.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $auditLog->tenant_id === null
            || $auditLog->tenant_id === $user->tenant_id;
    }
}
