<?php

namespace App\Models\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that should be audit-logged on create, update, and delete.
 *
 * Automatically logs changes via model observers. The trait registers
 * boot hooks that fire audit events for each lifecycle event.
 */
trait HasAuditTrail
{
    public static function bootHasAuditTrail(): void
    {
        static::created(function ($model) {
            static::logAuditEvent($model, 'created');
        });

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                static::logAuditEvent($model, 'updated', $model->getChanges(), $model->getOriginal());
            }
        });

        static::deleted(function ($model) {
            $event = $model->isForceDeleting() ? 'force_deleted' : 'deleted';
            static::logAuditEvent($model, $event);
        });
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected static function logAuditEvent(
        $model,
        string $event,
        ?array $newValues = null,
        ?array $oldValues = null
    ): void {
        $user = Auth::user();

        // Filter out values we don't want to log
        $sensitiveFields = $model->auditExclude ?? ['password', 'remember_token'];
        $newValues = $newValues ? array_diff_key($newValues, array_flip($sensitiveFields)) : null;
        $oldValues = $oldValues ? array_diff_key(
            array_intersect_key($oldValues, $newValues ?? []),
            array_flip($sensitiveFields)
        ) : null;

        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $model->tenant_id ?? $user?->tenant_id,
            'user_id' => $user?->id,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues ?: ($event === 'created' ? $model->getAttributes() : null),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
