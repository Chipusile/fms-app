<?php

namespace App\Policies;

use App\Models\OdometerReading;
use App\Models\User;

class OdometerReadingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('odometer.view');
    }

    public function view(User $user, OdometerReading $odometerReading): bool
    {
        return $user->tenant_id === $odometerReading->tenant_id
            && $user->hasPermission('odometer.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('odometer.create');
    }

    public function resolve(User $user, OdometerReading $odometerReading): bool
    {
        return $user->tenant_id === $odometerReading->tenant_id
            && $user->hasPermission('odometer.update');
    }
}
