<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleComponent extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'service_provider_id',
        'component_number',
        'component_type',
        'position_code',
        'brand',
        'model',
        'serial_number',
        'status',
        'condition_status',
        'installed_at',
        'installed_odometer',
        'expected_life_days',
        'expected_life_km',
        'reminder_days',
        'reminder_km',
        'next_replacement_at',
        'next_replacement_km',
        'warranty_expiry_date',
        'last_inspected_at',
        'removed_at',
        'removed_odometer',
        'removal_reason',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'installed_at' => 'date',
            'next_replacement_at' => 'date',
            'warranty_expiry_date' => 'date',
            'last_inspected_at' => 'date',
            'removed_at' => 'date',
            'metadata' => 'array',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
