<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use Database\Factories\BookingExtraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property string $code
 * @property string $name
 * @property string $unit
 * @property int $qty
 * @property int $rate_usd
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 */
#[Fillable([
    'booking_id',
    'code',
    'name',
    'unit',
    'qty',
    'rate_usd',
    'note',
])]
class BookingExtra extends Model
{
    /** @use HasFactory<BookingExtraFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'rate_usd' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function amount(): int
    {
        return $this->qty * $this->rate_usd;
    }

    public function historyLabel(): string
    {
        return $this->name;
    }
}
