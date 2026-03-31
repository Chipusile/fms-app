<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;

class ApprovalRequestPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('approvals.view');
    }

    public function view(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->tenant_id === $approvalRequest->tenant_id
            && $user->hasPermission('approvals.view');
    }

    public function decide(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->tenant_id === $approvalRequest->tenant_id
            && $user->hasPermission('approvals.decide');
    }
}
