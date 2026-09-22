<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealStage;
use App\Enums\DealType;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_id
 * @property int|null $owner_id
 * @property string $title
 * @property DealType $type
 * @property DealStage|null $stage
 * @property Carbon $stage_entered_at
 * @property int|null $estimate
 * @property string|null $lost_reason
 * @property int|null $booking_id
 * @property int|null $group_id
 * @property int|null $charter_enquiry_id
 * @property string|null $notes
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Contact $contact
 * @property-read User|null $owner
 * @property-read Booking|null $booking
 * @property-read Group|null $group
 */
#[Fillable([
    'contact_id',
    'owner_id',
    'title',
    'type',
    'stage',
    'stage_entered_at',
    'estimate',
    'lost_reason',
    'booking_id',
    'group_id',
    'charter_enquiry_id',
    'notes',
])]
class Deal extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DealType::class,
            'stage' => DealStage::class,
            'stage_entered_at' => 'datetime',
            'estimate' => 'integer',
        ];
    }

    public function isBound(): bool
    {
        return $this->booking_id !== null || $this->group_id !== null;
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function historyLabel(): string
    {
        return $this->title;
    }
}
