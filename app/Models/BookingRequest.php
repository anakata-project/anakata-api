<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HoldRule;
use App\Enums\PreferredChannel;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\BookingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property PreferredChannel $preferred_channel
 * @property bool $travel_advisor
 * @property string|null $notes
 * @property Carbon $submitted_at
 * @property Carbon $sla_due_at
 * @property HoldRule $hold_rule
 * @property Carbon|null $hold_expired_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
#[Fillable([
    'booking_id',
    'preferred_channel',
    'travel_advisor',
    'notes',
    'submitted_at',
    'sla_due_at',
    'hold_rule',
    'hold_expired_at',
])]
class BookingRequest extends Model
{
    /** @use HasFactory<BookingRequestFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_channel' => PreferredChannel::class,
            'travel_advisor' => 'boolean',
            'submitted_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'hold_rule' => HoldRule::class,
            'hold_expired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
