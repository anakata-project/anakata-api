<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Actions\Bookings\TransitionBooking;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Config\CurrentConfig;
use App\Support\History\History;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The only place that infers booking status from money (H4).
 * Caller must already hold the H10 locks. This class never locks.
 */
final class ApplyPaymentEffects
{
    public function __construct(
        private readonly TransitionBooking $transitions,
        private readonly CurrentConfig $config,
    ) {}

    public function handle(Booking $booking, Payment $payment): Booking
    {
        $this->guardTransaction();

        $reason = $payment->kind->label().' settled · '.$payment->reference;
        $paid = Ledger::paidFresh($booking);

        if ($paid >= $booking->depositAmount()) {
            if (in_array($booking->status, [BookingStatus::PendingPayment, BookingStatus::Requested], true)) {
                $booking = $this->transitions->handle($booking, [
                    'to' => BookingStatus::Confirmed,
                    'reason' => $reason,
                ], null, system: true);
            } elseif ($booking->status === BookingStatus::OnHoldAgency) {
                if ($booking->commission_approved) {
                    $booking = $this->transitions->handle($booking, [
                        'to' => BookingStatus::Confirmed,
                        'reason' => $reason,
                    ], null, system: true);
                } else {
                    $this->writeBlockedIfNewEpisode($booking);
                }
            }
        }

        $paid = Ledger::paidFresh($booking);

        if ($booking->balanceFresh() <= 0 && $booking->status === BookingStatus::Confirmed) {
            $booking = $this->transitions->handle($booking, [
                'to' => BookingStatus::FullyPaid,
                'reason' => $reason,
            ], null, system: true);
        }

        return $booking;
    }

    private function writeBlockedIfNewEpisode(Booking $booking): void
    {
        $decision = $booking->latestCommissionCapDecision();

        $blocked = $booking->history()->where('event', 'booking.confirmed_blocked');
        $already = $decision === null
            ? $blocked->exists()
            : $blocked->where('id', '>', $decision->id)->exists();

        if ($already) {
            return;
        }

        $pct = (int) $booking->commission_pct;
        $cap = $this->config->businessRules()->commission->capPct;

        History::record($booking, 'booking.confirmed_blocked', after: [
            'what' => 'Deposit settled — CONFIRMED blocked: commission '.$pct.' % above the '.$cap.' % cap (FIN-005)',
            'commission_pct' => $pct,
            'cap_pct' => $cap,
        ], system: true);
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
