<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\JourneyEnrolmentStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\Journeys\JourneyEngine;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $journey_id
 * @property int $contact_id
 * @property int|null $booking_id
 * @property int $booking_subject
 * @property string $branch
 * @property int $position
 * @property Carbon|null $next_due_at
 * @property JourneyEnrolmentStatus $status
 * @property string|null $exit_reason
 * @property Carbon $enrolled_at
 * @property Carbon|null $exited_at
 * @property-read Journey $journey
 * @property-read Contact $contact
 * @property-read Booking|null $booking
 */
#[Fillable([
    'journey_id',
    'contact_id',
    'booking_id',
    'branch',
    'position',
    'next_due_at',
    'status',
    'exit_reason',
    'enrolled_at',
    'exited_at',
])]
class JourneyEnrolment extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'booking_subject' => 'integer',
            'next_due_at' => 'datetime',
            'status' => JourneyEnrolmentStatus::class,
            'enrolled_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Journey, $this>
     */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<JourneySend, $this>
     */
    public function sends(): HasMany
    {
        return $this->hasMany(JourneySend::class);
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function historyLabel(): string
    {
        $this->loadMissing('journey');

        return $this->journey->name;
    }

    /**
     * Task 05 calls this when a lead is captured. There is no caller yet.
     */
    public static function onLeadCaptured(Contact $contact): void
    {
        app(JourneyEngine::class)->onLeadCaptured($contact);
    }
}
