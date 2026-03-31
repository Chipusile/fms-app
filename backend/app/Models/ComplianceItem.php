<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplianceItem extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'compliant_type',
        'compliant_id',
        'title',
        'category',
        'reference_number',
        'issuer',
        'issue_date',
        'expiry_date',
        'reminder_days',
        'status',
        'last_reminded_at',
        'renewed_at',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'last_reminded_at' => 'datetime',
            'renewed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function compliant(): MorphTo
    {
        return $this->morphTo();
    }
}
