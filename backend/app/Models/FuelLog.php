<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelLog extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'trip_id',
        'service_provider_id',
        'reference_number',
        'fuel_type',
        'quantity_liters',
        'cost_per_liter',
        'total_cost',
        'odometer_reading',
        'is_full_tank',
        'fueled_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_liters' => 'decimal:2',
            'cost_per_liter' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'is_full_tank' => 'boolean',
            'fueled_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function serviceProvider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class);
    }
}
