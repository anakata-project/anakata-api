<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only place that infers booking status from money (H4).
 * Caller must already hold the H10 locks. This class never locks.
 */
final class ApplyPaymentEffects
{
    public function __construct(private readonly TransitionBooking $transitions) {}

    public function handle(Booking $booking, Payment $payment): Booking
    {
        $this->guardTransaction();

        $reason = $payment->kind->label().' settled · '.$payment->reference;
        $paid = Ledger::paidFresh($booking);

        if ($paid >= $booking->depositAmount() && $booking->status === BookingStatus::PendingPayment) {
            $booking = $this->transitions->handle($booking, [
                'to' => BookingStatus::Confirmed,
                'reason' => $reason,
            ], null, system: true);
        }

        // TransitionBooking::acquire() writes status on a fresh instance.
        // Re-read before the balance check or a covering payment stays PENDING_PAYMENT.
        $paid = Ledger::paidFresh($booking);

        // TODO(task 04): also treat ON_HOLD_AGENCY after the commission-cap override.
        if ($booking->total - $paid <= 0 && $booking->status === BookingStatus::Confirmed) {
            $booking = $this->transitions->handle($booking, [
                'to' => BookingStatus::FullyPaid,
                'reason' => $reason,
            ], null, system: true);
        }

        return $booking;
    }

    private function guardTransaction(): void
    {
        if (DB::transactionLevel() > 0) {
            return;
        }

        if (app()->environment(['local', 'testing'])) {
            throw new RuntimeException('ApplyPaymentEffects must run inside a transaction.');
        }

        Log::warning('ApplyPaymentEffects was called outside a database transaction.');
    }
}
