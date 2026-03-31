<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that automatically filters queries by the authenticated user's tenant.
 *
 * Applied via the BelongsToTenant trait. Super admins (is_super_admin = true)
 * bypass this scope entirely to allow cross-tenant operations.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        // Super admins bypass tenant scoping
        if ($user->is_super_admin) {
            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $user->tenant_id);
    }
}
