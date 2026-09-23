<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentLinkStatus;
use App\Models\Booking;
use App\Models\PaymentLink;
use App\Services\Stripe\StripeGateway;
use Illuminate\Validation\ValidationException;

final class IssuePaymentLink
{
    public function __construct(private readonly StripeGateway $stripe) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Booking $booking, array $data): PaymentLink
    {
        $this->guardBooking($booking);

        $kind = $data['kind'] instanceof PaymentKind
            ? $data['kind']
            : PaymentKind::from((string) $data['kind']);

        $amount = array_key_exists('amount', $data) && $data['amount'] !== null
            ? (int) $data['amount']
            : $this->defaultAmount($booking, $kind);

        $this->guardOpenLink($booking, $kind);
        $this->guardAmount($booking, $amount);

        $created = $this->stripe->createPaymentLink($booking, $kind, $amount);

        return PaymentLink::query()->create([
            'booking_id' => $booking->id,
            'kind' => $kind,
            'amount' => $amount,
            'stripe_id' => $created->stripeId,
            'url' => $created->url,
            'status' => PaymentLinkStatus::Open,
        ]);
    }

    private function defaultAmount(Booking $booking, PaymentKind $kind): int
    {
        return $kind === PaymentKind::Deposit
            ? $booking->depositAmount()
            : $booking->balanceFresh();
    }

    private function guardBooking(Booking $booking): void
    {
        if (in_array($booking->status, [
            BookingStatus::Cancelled,
            BookingStatus::CancelledPostpaid,
            BookingStatus::Released,
        ], true)) {
            throw ValidationException::withMessages([
                'booking' => ['A payment link cannot be created for a cancelled or released booking.'],
            ]);
        }

        if ($booking->balanceFresh() <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['This booking has no outstanding balance.'],
            ]);
        }
    }

    private function guardAmount(Booking $booking, int $amount): void
    {
        if ($amount < 1 || $amount > $booking->balanceFresh()) {
            throw ValidationException::withMessages([
                'amount' => ['The amount must be at least 1 and not more than the outstanding balance.'],
            ]);
        }
    }

    private function guardOpenLink(Booking $booking, PaymentKind $kind): void
    {
        $exists = PaymentLink::query()
            ->where('booking_id', $booking->id)
            ->where('kind', $kind)
            ->where('status', PaymentLinkStatus::Open)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'kind' => ['An open '.$kind->label().' link already exists — cancel it first.'],
            ]);
        }
    }
}
