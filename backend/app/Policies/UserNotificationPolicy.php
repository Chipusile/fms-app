<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNotification;

class UserNotificationPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('notifications.view');
    }

    public function view(User $user, UserNotification $userNotification): bool
    {
        return $user->tenant_id === $userNotification->tenant_id
            && $user->id === $userNotification->user_id
            && $user->hasPermission('notifications.view');
    }

    public function update(User $user, UserNotification $userNotification): bool
    {
        return $user->tenant_id === $userNotification->tenant_id
            && $user->id === $userNotification->user_id
            && $user->hasPermission('notifications.update');
    }
}
