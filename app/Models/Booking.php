<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\CalendarDate;
use App\Enums\BookingSegment;
use App\Enums\BookingStatus;
use App\Enums\BookingType;
use App\Enums\ChannelOfOrigin;
use App\Enums\MainChannel;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\SerializesDatesAsUtc;
use App\Support\BusinessTime;
use App\Support\Payments\Ledger;
use App\Support\Payments\PaymentsKpis;
use App\Support\Payments\WireWindow;
use App\Support\Rounding;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $reference
 * @property string|null $request_reference
 * @property BookingType $type
 * @property int $departure_id
 * @property int|null $cabin_id
 * @property int $contact_id
 * @property int|null $group_id
 * @property int $owner_id
 * @property int|null $agency_id
 * @property int|null $commission_pct
 * @property bool $commission_approved
 * @property int|null $commission_approved_by
 * @property Carbon|null $commission_approved_at
 * @property string|null $commission_reason
 * @property BookingStatus $status
 * @property MainChannel $main_channel
 * @property ChannelOfOrigin $channel_of_origin
 * @property int $adults
 * @property int $children
 * @property bool $back_to_back
 * @property int $rates_version_id
 * @property list<array{code: string, label: string, amount: int}> $price_lines
 * @property int $total
 * @property int $deposit_pct
 * @property int $balance_days
 * @property CarbonImmutable|null $balance_due_date_override
 * @property string|null $internal_notes
 * @property Carbon|null $deleted_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Departure $departure
 * @property-read Cabin|null $cabin
 * @property-read Contact $contact
 * @property-read Group|null $group
 * @property-read User $owner
 * @property-read Agency|null $agency
 * @property-read User|null $commissionApprovedBy
 * @property-read RateVersion $ratesVersion
 * @property-read Collection<int, CabinClaim> $claims
 * @property-read Collection<int, CabinClaim> $activeClaims
 * @property-read Collection<int, Guest> $guests
 * @property-read Collection<int, Consent> $consents
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $guests_count
 * @property-read int|null $guests_complete_count
 * @property-read Collection<int, PaymentLink> $paymentLinks
 * @property-read BookingRequest|null $bookingRequest
 * @property-read RefundRequest|null $refundRequest
 * @property-read int|null $payments_paid_sum
 * @property-read int|null $payments_pledged_sum
 * @property-read int $payments_count
 * @property-read string|null $awaiting_wire_created_at
 */
#[Fillable([
    'reference',
    'request_reference',
    'type',
    'departure_id',
    'cabin_id',
    'contact_id',
    'group_id',
    'owner_id',
    'agency_id',
    'commission_pct',
    'commission_approved',
    'commission_approved_by',
    'commission_approved_at',
    'commission_reason',
    'status',
    'main_channel',
    'channel_of_origin',
    'adults',
    'children',
    'back_to_back',
    'rates_version_id',
    'price_lines',
    'total',
    'deposit_pct',
    'balance_days',
    'balance_due_date_override',
    'internal_notes',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasAuditColumns, HasFactory, SerializesDatesAsUtc, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BookingType::class,
            'status' => BookingStatus::class,
            'main_channel' => MainChannel::class,
            'channel_of_origin' => ChannelOfOrigin::class,
            'commission_pct' => 'integer',
            'commission_approved' => 'boolean',
            'commission_approved_at' => 'datetime',
            'adults' => 'integer',
            'children' => 'integer',
            'back_to_back' => 'boolean',
            'price_lines' => 'array',
            'total' => 'integer',
            'deposit_pct' => 'integer',
            'balance_days' => 'integer',
            'balance_due_date_override' => CalendarDate::class,
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
     * @return BelongsTo<Cabin, $this>
     */
    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function commissionApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commission_approved_by');
    }

    /**
     * @return BelongsTo<RateVersion, $this>
     */
    public function ratesVersion(): BelongsTo
    {
        return $this->belongsTo(RateVersion::class, 'rates_version_id');
    }

    /**
     * @return HasOne<BookingRequest, $this>
     */
    public function bookingRequest(): HasOne
    {
        return $this->hasOne(BookingRequest::class);
    }

    /**
     * @return HasOne<RefundRequest, $this>
     */
    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class);
    }

    /**
     * @return HasMany<Guest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class)->orderBy('position');
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<PaymentLink, $this>
     */
    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentLink::class)->orderByDesc('id');
    }

    /**
     * @return MorphMany<CabinClaim, $this>
     */
    public function claims(): MorphMany
    {
        return $this->morphMany(CabinClaim::class, 'holder');
    }

    /**
     * @return MorphMany<CabinClaim, $this>
     */
    public function activeClaims(): MorphMany
    {
        return $this->claims()->whereNull('released_at');
    }

    /**
     * @return MorphMany<ChangeHistory, $this>
     */
    public function history(): MorphMany
    {
        return $this->morphMany(ChangeHistory::class, 'subject');
    }

    public function displayReference(): ?string
    {
        return $this->reference ?? $this->request_reference;
    }

    public function partyLabel(): string
    {
        $label = $this->adults.' AD';

        if ($this->children > 0) {
            $label .= ' + '.$this->children.' CH';
        }

        return $label;
    }

    public function segment(): BookingSegment
    {
        if ($this->type !== BookingType::Cabin) {
            return BookingSegment::Charter;
        }

        return $this->main_channel->segment();
    }

    public function balance(): int
    {
        return $this->total - Ledger::paid($this);
    }

    public function balanceDueDate(): CarbonImmutable
    {
        if ($this->balance_due_date_override instanceof CarbonImmutable) {
            return $this->balance_due_date_override;
        }

        $this->loadMissing('departure');

        return $this->departure->date->subDays($this->balance_days);
    }

    public function isOverdue(): bool
    {
        if (! in_array($this->status, [BookingStatus::Confirmed, BookingStatus::OnHoldAgency], true)) {
            return false;
        }

        if ($this->balance() <= 0) {
            return false;
        }

        return BusinessTime::now()->toDateString() > $this->balanceDueDate()->toDateString();
    }

    public function overdueDays(): ?int
    {
        if (! $this->isOverdue()) {
            return null;
        }

        return (int) $this->balanceDueDate()->diffInDays(BusinessTime::now()->toDateString());
    }

    public function overdueSince(): ?CarbonImmutable
    {
        if (! $this->isOverdue()) {
            return null;
        }

        return $this->balanceDueDate();
    }

    public function wireWindowEndsAt(): ?CarbonImmutable
    {
        $started = $this->awaitingWireStartedAt();

        if ($started === null) {
            return null;
        }

        return WireWindow::endsAt($started);
    }

    public function dueDateChangedAt(): CarbonImmutable
    {
        if ($this->balance_due_date_override instanceof CarbonImmutable) {
            $extended = $this->history()
                ->where('event', 'booking.overdue_extended')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($extended !== null) {
                return CarbonImmutable::instance($extended->created_at);
            }

            return CarbonImmutable::instance($this->updated_at);
        }

        return CarbonImmutable::instance($this->created_at);
    }

    /**
     * SQL fragment: total minus payments that count as paid (paidValues()).
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function balanceSql(): array
    {
        $paid = PaymentStatus::paidValues();
        $placeholders = implode(', ', array_fill(0, count($paid), '?'));

        return [
            'bookings.total - COALESCE((
                SELECT SUM(payments.amount) FROM payments
                WHERE payments.booking_id = bookings.id
                  AND payments.status IN ('.$placeholders.')
            ), 0)',
            $paid,
        ];
    }

    /**
     * Owing bookings for the Payments & Revenue pending table.
     * Same set as PaymentsKpis::owingStatuses() with a positive balance.
     *
     * @param  Builder<self>  $query
     */
    public function scopePendingPayment(Builder $query): void
    {
        [$balanceSql, $paid] = self::balanceSql();

        $query
            ->whereIn('bookings.status', PaymentsKpis::owingStatuses())
            ->whereRaw('('.$balanceSql.') > 0', $paid);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $today = BusinessTime::now()->toDateString();
        [$balanceSql, $paid] = self::balanceSql();

        $query
            ->whereIn('bookings.status', [
                BookingStatus::Confirmed->value,
                BookingStatus::OnHoldAgency->value,
            ])
            ->whereRaw('('.$balanceSql.') > 0', $paid)
            ->whereRaw(
                '? > COALESCE(bookings.balance_due_date_override, DATE_SUB((
                    SELECT departures.date FROM departures WHERE departures.id = bookings.departure_id
                ), INTERVAL bookings.balance_days DAY))',
                [$today],
            );
    }

    public function depositAmount(): int
    {
        return Rounding::halfUp($this->total * $this->deposit_pct / 100);
    }

    public function commissionAmount(): int
    {
        if ($this->commission_pct === null) {
            return 0;
        }

        return Rounding::halfUp($this->total * $this->commission_pct / 100);
    }

    /**
     * Latest cap approve/reject row, if any. A new episode starts after this id
     * (same-second payments must not reuse the previous blocked entry).
     */
    public function latestCommissionCapDecision(): ?ChangeHistory
    {
        return $this->history()
            ->whereIn('event', ['booking.commission_approved', 'booking.commission_rejected'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }

    public function holdExpired(): bool
    {
        if ($this->status !== BookingStatus::Requested) {
            return false;
        }

        $this->loadMissing('bookingRequest');

        return $this->bookingRequest?->hold_expired_at !== null;
    }

    public function occupiesInventory(): bool
    {
        return $this->status->holdsInventory() && ! $this->holdExpired();
    }

    public function cabinLabel(): string
    {
        if ($this->type === BookingType::Charter) {
            return 'Full yacht';
        }

        $this->loadMissing('cabin');

        $cabin = $this->cabin;

        return $cabin instanceof Cabin ? $cabin->label : 'Cabin';
    }

    public function historyLabel(): string
    {
        return $this->displayReference() ?? 'booking';
    }

    /**
     * @param  Builder<self>  $query
     */
    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithGuestSummary(Builder $query): void
    {
        $query
            ->withCount('guests')
            ->withCount(['guests as guests_complete_count' => fn ($guests) => $guests->complete()]);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithLedgerAggregates(Builder $query): void
    {
        $query
            ->withSum(
                ['payments as payments_paid_sum' => fn ($payments) => $payments->countingAsPaid()],
                'amount',
            )
            ->withSum(
                ['payments as payments_pledged_sum' => fn ($payments) => $payments->where('status', PaymentStatus::AwaitingWire)],
                'amount',
            )
            ->withCount('payments')
            ->withMin([
                'payments as awaiting_wire_created_at' => fn ($payments) => $payments->where('status', PaymentStatus::AwaitingWire),
            ], 'created_at');
    }

    private function awaitingWireStartedAt(): ?CarbonImmutable
    {
        if (array_key_exists('awaiting_wire_created_at', $this->getAttributes())) {
            $aggregated = $this->getAttribute('awaiting_wire_created_at');

            if ($aggregated instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($aggregated);
            }

            if (is_string($aggregated) && $aggregated !== '') {
                return CarbonImmutable::parse($aggregated);
            }

            return null;
        }

        $this->loadMissing('payments');

        $started = $this->payments
            ->first(fn (Payment $payment): bool => $payment->status === PaymentStatus::AwaitingWire)
            ?->created_at;

        return $started instanceof \DateTimeInterface
            ? CarbonImmutable::instance($started)
            : null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOfSegment(Builder $query, BookingSegment $segment): void
    {
        if ($segment === BookingSegment::Charter) {
            $query->where('type', BookingType::Charter);

            return;
        }

        $b2b = array_values(array_map(
            fn (MainChannel $channel): string => $channel->value,
            array_filter(
                MainChannel::cases(),
                fn (MainChannel $channel): bool => $channel->segment() === BookingSegment::B2B,
            ),
        ));

        $query->where('type', BookingType::Cabin);

        if ($segment === BookingSegment::B2B) {
            $query->whereIn('main_channel', $b2b);

            return;
        }

        $query->whereNotIn('main_channel', $b2b);
    }
}
