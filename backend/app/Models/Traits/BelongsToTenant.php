<?php

namespace App\Models\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait for models that are scoped to a tenant.
 *
 * - Applies TenantScope globally to filter queries by tenant_id
 * - Auto-sets tenant_id on model creation from the authenticated user
 * - Provides the tenant() relationship
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        if (static::shouldApplyTenantScope()) {
            static::addGlobalScope(new TenantScope());
        }

        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $user = Auth::user();
                if ($user && $user->tenant_id) {
                    $model->tenant_id = $user->tenant_id;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function shouldApplyTenantScope(): bool
    {
        return true;
    }

    /**
     * Query without tenant scoping. Use with caution — only for
     * super admin operations or system-level queries.
     */
    public static function withoutTenantScope(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
