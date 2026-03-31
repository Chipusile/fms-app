<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant-scoped key-value settings store.
 *
 * Supports grouping by 'group' column for logical organisation
 * (e.g. 'general', 'notifications', 'fleet', 'maintenance').
 * The 'value' column stores JSON, allowing complex config values.
 */
class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'group',
        'key',
        'value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Get a setting value for the current tenant.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    public static function getTenantValue(int $tenantId, string $key, mixed $default = null): mixed
    {
        $setting = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    /**
     * Set a setting value for the current tenant.
     */
    public static function setValue(string $key, mixed $value, ?string $group = null): static
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );
    }
}
