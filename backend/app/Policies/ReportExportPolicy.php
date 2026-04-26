<?php

namespace App\Policies;

use App\Models\ReportExport;
use App\Models\User;

class ReportExportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('reports.view');
    }

    public function view(User $user, ReportExport $reportExport): bool
    {
        return $user->tenant_id === $reportExport->tenant_id
            && $user->hasPermission('reports.view')
            && ($reportExport->requested_by === $user->id || $user->hasPermission('reports.view-all'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('reports.export');
    }

    public function download(User $user, ReportExport $reportExport): bool
    {
        return $user->tenant_id === $reportExport->tenant_id
            && $user->hasPermission('reports.export')
            && ($reportExport->requested_by === $user->id || $user->hasPermission('reports.view-all'));
    }
}
