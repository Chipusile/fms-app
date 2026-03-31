<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdometerReading extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater;

    protected $fillable = [
        'tenant_id',
        'vehicle_id',
        'driver_id',
        'reading',
        'source',
        'source_reference_id',
        'recorded_at',
        'notes',
        'is_anomaly',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'resolved_at' => 'datetime',
            'is_anomaly' => 'boolean',
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

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
