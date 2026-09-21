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
 * @property int $alias_id
 * @property int $contact_id
 * @property int $merge_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contact $aliased
 * @property-read Contact $survivor
 * @property-read ContactMerge $merge
 */
#[Fillable([
    'alias_id',
    'contact_id',
    'merge_id',
])]
class ContactAlias extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function aliased(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'alias_id');
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function survivor(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * @return BelongsTo<ContactMerge, $this>
     */
    public function merge(): BelongsTo
    {
        return $this->belongsTo(ContactMerge::class, 'merge_id');
    }

    public function historyLabel(): string
    {
        return 'alias #'.$this->alias_id;
    }
}
