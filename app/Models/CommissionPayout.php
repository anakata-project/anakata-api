<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property int $amount
 * @property CarbonImmutable $paid_on
 * @property string $bank_reference
 * @property int|null $recorded_by
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'booking_id',
    'amount',
    'paid_on',
    'bank_reference',
    'recorded_by',
])]
class CommissionPayout extends Model
{
    use HasAuditColumns, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => CalendarDate::class,
        ];
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

    /**
     * @return array{amount: int, paid_on: string, bank_reference: string}
     */
    public function toArrayForApi(): array
    {
        return [
            'amount' => $this->amount,
            'paid_on' => $this->paid_on->toDateString(),
            'bank_reference' => $this->bank_reference,
        ];
    }
}
