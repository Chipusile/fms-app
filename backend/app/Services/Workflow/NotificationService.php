<?php

namespace App\Services\Workflow;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationService
{
    /**
     * @param  iterable<User>  $users
     */
    public function notifyUsers(
        iterable $users,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null,
        ?Model $related = null,
        array $metadata = [],
    ): void {
        foreach ($users as $user) {
            $this->notifyUser($user, $type, $title, $body, $actionUrl, $related, $metadata);
        }
    }

    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $body,
        ?string $actionUrl = null,
        ?Model $related = null,
        array $metadata = [],
    ): UserNotification {
        $existing = UserNotification::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('status', 'unread')
            ->where('related_type', $related?->getMorphClass())
            ->where('related_id', $related?->getKey())
            ->first();

        if ($existing) {
            $existing->update([
                'title' => $title,
                'body' => $body,
                'action_url' => $actionUrl,
                'metadata' => $metadata,
            ]);

            return $existing->fresh(['user']);
        }

        return UserNotification::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'status' => 'unread',
            'metadata' => $metadata,
        ])->fresh(['user']);
    }

    public function markRead(UserNotification $notification): UserNotification
    {
        if ($notification->status === 'unread') {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return $notification->fresh(['user']);
    }

    public function acknowledge(UserNotification $notification): UserNotification
    {
        $notification->update([
            'status' => 'acknowledged',
            'read_at' => $notification->read_at ?? now(),
            'acknowledged_at' => now(),
        ]);

        return $notification->fresh(['user']);
    }

    /**
     * @return Collection<int, User>
     */
    public function recipientsWithPermission(int $tenantId, string $permissionSlug, ?int $excludeUserId = null): Collection
    {
        return User::withoutGlobalScopes()
            ->with('roles.permissions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->when($excludeUserId, fn ($query) => $query->whereKeyNot($excludeUserId))
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($permissionSlug))
            ->values();
    }
}
