<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentStatus;
use App\Models\Booking;

/**
 * The only place that sums a booking's ledger.
 *
 * `paid()` / `pledged()` may use a `withSum` aggregate on list reads.
 * Writers (task 02 `ApplyPaymentEffects`) must call `paidFresh()` /
 * `pledgedFresh()` so they never see a stale aggregate on the same instance
 * after inserting a row.
 */
final class Ledger
{
    /** @var list<string> */
    private const AGGREGATE_ATTRIBUTES = [
        'payments_paid_sum',
        'payments_pledged_sum',
        'payments_count',
        'awaiting_wire_created_at',
    ];

    public static function paid(Booking $booking): int
    {
        if (! array_key_exists('payments_paid_sum', $booking->getAttributes())) {
            return self::paidFresh($booking);
        }

        return (int) $booking->getAttribute('payments_paid_sum');
    }

    public static function pledged(Booking $booking): int
    {
        if (! array_key_exists('payments_pledged_sum', $booking->getAttributes())) {
            return self::pledgedFresh($booking);
        }

        return (int) $booking->getAttribute('payments_pledged_sum');
    }

    public static function paidFresh(Booking $booking): int
    {
        return (int) $booking->payments()->countingAsPaid()->sum('amount');
    }

    public static function pledgedFresh(Booking $booking): int
    {
        return (int) $booking->payments()
            ->where('status', PaymentStatus::AwaitingWire)
            ->sum('amount');
    }

    public static function forgetAggregates(Booking $booking): void
    {
        foreach (self::AGGREGATE_ATTRIBUTES as $attribute) {
            if (array_key_exists($attribute, $booking->getAttributes())) {
                $booking->offsetUnset($attribute);
            }
        }
    }
}
