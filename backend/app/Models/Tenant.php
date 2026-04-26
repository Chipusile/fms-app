<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Models\Traits\HasAuditTrail;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasAuditTrail, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'status',
        'plan_id',
        'trial_ends_at',
        'stripe_customer_id',
        'stripe_subscription_id',
        'subscription_status',
        'settings',
        'logo_path',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone',
        'email',
        'website',
        'timezone',
        'currency',
        'date_format',
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'trial_ends_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->trial_ends_at?->isFuture()) {
            return true;
        }

        return in_array($this->subscription_status, ['active', 'trialing'], true)
            || $this->subscription_status === null;
    }

    public function planLimit(string $resource): ?int
    {
        $limit = config("fleet.plans.{$this->plan_id}.limits.{$resource}");

        return is_numeric($limit) ? (int) $limit : null;
    }
}
