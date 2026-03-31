<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasAuditTrail, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'status',
        'is_super_admin',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected array $auditExclude = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reported_by');
    }

    public function assignedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'assigned_to');
    }

    public function inspectionsPerformed(): HasMany
    {
        return $this->hasMany(Inspection::class, 'inspected_by');
    }

    public function requestedApprovals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'requested_by');
    }

    public function decidedApprovals(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class, 'decided_by');
    }

    public function assignedWorkOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    public function requestedMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'requested_by');
    }

    public function reviewedMaintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'reviewed_by');
    }

    public function recordedMaintenance(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class, 'recorded_by');
    }

    /**
     * Get all permissions for this user through their roles.
     */
    public function permissions(): array
    {
        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return in_array($permissionSlug, $this->permissions());
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return ! empty(array_intersect($permissions, $this->permissions()));
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function scopeVisibleTo(Builder $query, ?self $actor): Builder
    {
        if (! $actor || $actor->is_super_admin) {
            return $query;
        }

        return $query->where('tenant_id', $actor->tenant_id);
    }

    public static function shouldApplyTenantScope(): bool
    {
        return false;
    }
}
