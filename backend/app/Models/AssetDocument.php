<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasAuditTrail;
use App\Models\Traits\HasCreatorAndUpdater;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetDocument extends Model
{
    use BelongsToTenant, HasAuditTrail, HasCreatorAndUpdater, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'documentable_type',
        'documentable_id',
        'name',
        'document_type',
        'document_number',
        'file_name',
        'file_path',
        'storage_disk',
        'mime_type',
        'file_size',
        'scan_status',
        'scanned_at',
        'scan_error',
        'issue_date',
        'expiry_date',
        'status',
        'metadata',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'scanned_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, class-string<EloquentModel>>
     */
    public static function documentableMap(): array
    {
        return config('fleet.asset_document.documentables', []);
    }

    public static function resolveDocumentableClass(string $alias): ?string
    {
        return static::documentableMap()[$alias] ?? null;
    }

    public static function resolveDocumentableAlias(?string $className): ?string
    {
        if (! $className) {
            return null;
        }

        $alias = array_search($className, static::documentableMap(), true);

        return $alias === false ? null : $alias;
    }
}
