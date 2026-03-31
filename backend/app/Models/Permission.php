<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permissions are global (not tenant-scoped). They define what actions
 * exist in the system. Roles (which ARE tenant-scoped) group permissions.
 *
 * Convention: slug format is "module.action" e.g. "vehicles.create"
 */
class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role')
            ->withTimestamps();
    }
}
