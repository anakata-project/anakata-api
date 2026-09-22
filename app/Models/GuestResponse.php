<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GuestResponseSource;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $guest_id
 * @property int $booking_id
 * @property int $score
 * @property int|null $recommend
 * @property string|null $why
 * @property string|null $best
 * @property string|null $better
 * @property string|null $crew
 * @property string|null $call_notes
 * @property GuestResponseSource $source
 * @property int|null $recorded_by
 * @property Carbon $responded_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Guest $guest
 * @property-read Booking $booking
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'guest_id',
    'booking_id',
    'score',
    'recommend',
    'why',
    'best',
    'better',
    'crew',
    'call_notes',
    'source',
    'recorded_by',
    'responded_at',
])]
class GuestResponse extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'recommend' => 'integer',
            'source' => GuestResponseSource::class,
            'responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Guest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
