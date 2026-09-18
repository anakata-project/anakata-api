<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait HasAuditColumns
{
    protected static function bootHasAuditColumns(): void
    {
        static::creating(function (Model $model): void {
            $actorId = Auth::id();

            $model->setAttribute('created_by', $model->getAttribute('created_by') ?? $actorId);
            $model->setAttribute('updated_by', $model->getAttribute('updated_by') ?? $actorId);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('updated_by')) {
                return;
            }

            $model->setAttribute('updated_by', Auth::id());
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
