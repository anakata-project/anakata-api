<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $survivor_id
 * @property int $loser_id
 * @property string $reason
 * @property int|null $merged_by
 * @property Carbon $merged_at
 * @property list<array{table: string, id: int}> $repointed_rows
 * @property array{email: string|null, phone: string|null, phone_e164: string|null}|null $merged_identifiers
 * @property array<string, mixed>|null $loser_fields
 * @property array<string, mixed> $survivor_filled
 * @property Carbon|null $undone_at
 * @property int|null $undone_by
 * @property string|null $undo_reason
 * @property Carbon|null $erased_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contact $survivor
 * @property-read Contact $loser
 * @property-read User|null $mergedBy
 * @property-read User|null $undoneByUser
 * @property-read ContactAlias|null $alias
 */
#[Fillable([
    'survivor_id',
    'loser_id',
    'reason',
    'merged_by',
    'merged_at',
    'repointed_rows',
    'merged_identifiers',
    'loser_fields',
    'survivor_filled',
    'undone_at',
    'undone_by',
    'undo_reason',
    'erased_at',
])]
class ContactMerge extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'merged_at' => 'datetime',
            'repointed_rows' => 'array',
            'merged_identifiers' => 'array',
            'loser_fields' => 'array',
            'survivor_filled' => 'array',
            'undone_at' => 'datetime',
            'erased_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function survivor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'survivor_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function loser(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'loser_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mergedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function undoneByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by');
    }

    /**
     * @return HasOne<ContactAlias, $this>
     */
    public function alias(): HasOne
    {
        return $this->hasOne(ContactAlias::class, 'merge_id');
    }

    public function historyLabel(): string
    {
        return 'merge #'.$this->id;
    }
}
