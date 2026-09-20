<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CabinCategory;
use App\Enums\PreferredChannel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $departure_id
 * @property CabinCategory $cabin_category
 * @property int $contact_id
 * @property int $adults
 * @property int $children
 * @property string|null $notes
 * @property Carbon|null $notified_at
 * @property int|null $notified_by
 * @property PreferredChannel|null $notified_channel
 * @property Carbon|null $removed_at
 * @property int|null $removed_by
 * @property string|null $removed_reason
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Departure $departure
 * @property-read Contact $contact
 * @property-read User|null $notifiedBy
 * @property-read User|null $removedBy
 */
#[Fillable([
    'departure_id',
    'cabin_category',
    'contact_id',
    'adults',
    'children',
    'notes',
    'notified_at',
    'notified_by',
    'notified_channel',
    'removed_at',
    'removed_by',
    'removed_reason',
    'created_at',
])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    public ?int $queuePosition = null;

    public bool $cabinIsAvailable = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cabin_category' => CabinCategory::class,
            'adults' => 'integer',
            'children' => 'integer',
            'notified_at' => 'datetime',
            'notified_channel' => PreferredChannel::class,
            'removed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Departure, $this>
     */
    public function departure(): BelongsTo
    {
        return $this->belongsTo(Departure::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function notifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    public function isActive(): bool
    {
        return $this->removed_at === null;
    }

    public function historyLabel(): string
    {
        $this->loadMissing('contact');

        return $this->contact->name;
    }
}
