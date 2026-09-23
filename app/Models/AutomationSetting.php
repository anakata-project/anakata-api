<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property bool $enabled
 * @property string|null $disabled_reason
 * @property int|null $disabled_by
 * @property Carbon|null $disabled_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property-read User|null $disabledBy
 */
#[Fillable([
    'key',
    'enabled',
    'disabled_reason',
    'disabled_by',
    'disabled_at',
])]
class AutomationSetting extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function historyLabel(): string
    {
        return $this->key;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }
}
