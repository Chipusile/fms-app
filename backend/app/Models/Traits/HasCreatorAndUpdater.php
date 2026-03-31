<?php

namespace App\Models\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically sets created_by and updated_by on model events.
 */
trait HasCreatorAndUpdater
{
    public static function bootHasCreatorAndUpdater(): void
    {
        static::creating(function ($model) {
            if (! $model->created_by && Auth::id()) {
                $model->created_by = Auth::id();
            }
            if (! $model->updated_by && Auth::id()) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::id()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
