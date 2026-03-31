<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('incidents.view');
    }

    public function view(User $user, Incident $incident): bool
    {
        return $user->tenant_id === $incident->tenant_id
            && $user->hasPermission('incidents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('incidents.create');
    }

    public function update(User $user, Incident $incident): bool
    {
        return $user->tenant_id === $incident->tenant_id
            && $user->hasPermission('incidents.update');
    }

    public function resolve(User $user, Incident $incident): bool
    {
        return $this->update($user, $incident);
    }

    public function close(User $user, Incident $incident): bool
    {
        return $this->update($user, $incident);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return $user->tenant_id === $incident->tenant_id
            && $user->hasPermission('incidents.delete');
    }
}
