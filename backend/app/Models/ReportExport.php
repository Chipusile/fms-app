<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportExport extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater;

    protected $fillable = [
        'tenant_id',
        'requested_by',
        'report_type',
        'format',
        'status',
        'filters',
        'file_name',
        'file_path',
        'storage_disk',
        'mime_type',
        'row_count',
        'started_at',
        'completed_at',
        'failed_at',
        'error_message',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
