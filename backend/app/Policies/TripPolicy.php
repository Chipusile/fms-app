<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('trips.view');
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->tenant_id === $trip->tenant_id
            && $user->hasPermission('trips.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('trips.create');
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->tenant_id === $trip->tenant_id
            && $user->hasPermission('trips.update');
    }

    public function cancel(User $user, Trip $trip): bool
    {
        return $user->tenant_id === $trip->tenant_id
            && ($user->hasPermission('trips.delete') || $trip->requested_by === $user->id);
    }

    public function approve(User $user, Trip $trip): bool
    {
        return $user->tenant_id === $trip->tenant_id
            && $user->hasPermission('trips.approve');
    }

    public function reject(User $user, Trip $trip): bool
    {
        return $this->approve($user, $trip);
    }

    public function start(User $user, Trip $trip): bool
    {
        return $user->tenant_id === $trip->tenant_id
            && ($user->hasPermission('trips.update') || $trip->driver?->user_id === $user->id);
    }

    public function complete(User $user, Trip $trip): bool
    {
        return $this->start($user, $trip);
    }
}
