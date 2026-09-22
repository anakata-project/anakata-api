<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\BusinessTime;
use App\Support\Payments\WireWindow;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $booking_id
 * @property PaymentKind $kind
 * @property PaymentMethod $method
 * @property int $amount
 * @property string $reference
 * @property string|null $gateway_id
 * @property PaymentStatus $status
 * @property CarbonImmutable $paid_at
 * @property int|null $recorded_by
 * @property string|null $note
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $booking
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'booking_id',
    'kind',
    'method',
    'amount',
    'reference',
    'gateway_id',
    'status',
    'paid_at',
    'recorded_by',
    'note',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc;

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if ($payment->getAttribute('paid_at') === null) {
                $payment->setAttribute('paid_at', BusinessTime::now()->toDateString());
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PaymentKind::class,
            'method' => PaymentMethod::class,
            'amount' => 'integer',
            'status' => PaymentStatus::class,
            'paid_at' => CalendarDate::class,
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
     * @param  Builder<self>  $query
     */
    public function scopeCountingAsPaid(Builder $query): void
    {
        $query->whereIn('status', PaymentStatus::paidValues());
    }

    /**
     * Awaiting-wire payments whose window has passed.
     * Matches WireWindow::endsAtFor() < now(): calendar hours from created_at, not business hours.
     *
     * @param  Builder<self>  $query
     */
    public function scopePastWireWindow(Builder $query): void
    {
        $query
            ->where('payments.status', PaymentStatus::AwaitingWire->value)
            ->where('payments.created_at', '<', now()->subHours(WireWindow::hours()));
    }

    public function historyLabel(): string
    {
        return $this->reference;
    }
}
