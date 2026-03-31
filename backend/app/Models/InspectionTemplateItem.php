<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionTemplateItem extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater;

    protected $fillable = [
        'tenant_id',
        'inspection_template_id',
        'title',
        'description',
        'response_type',
        'is_required',
        'triggers_defect_on_fail',
        'sort_order',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'triggers_defect_on_fail' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }
}
