<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResponse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'inspection_id',
        'inspection_template_item_id',
        'item_label',
        'response_value',
        'is_pass',
        'defect_severity',
        'defect_summary',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'response_value' => 'array',
            'is_pass' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function templateItem(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplateItem::class, 'inspection_template_item_id');
    }
}
