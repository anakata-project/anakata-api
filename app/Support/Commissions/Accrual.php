<?php

declare(strict_types=1);

namespace App\Support\Commissions;

use App\Enums\BookingStatus;
use App\Enums\CommissionAccrualStatus;
use App\Models\Booking;
use App\Support\BusinessTime;
use App\Support\Config\Documents\BusinessRulesDocument;
use Carbon\CarbonImmutable;

final class Accrual
{
    public static function payableDate(Booking $booking, BusinessRulesDocument $rules): CarbonImmutable
    {
        $booking->loadMissing('departure');

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $booking->departure->date->toDateString());

        if (! $date instanceof CarbonImmutable) {
            throw new \RuntimeException('Departure date must be Y-m-d.');
        }

        return $date->addDays($rules->commission->payableDaysAfterCruise);
    }

    public static function status(Booking $booking, BusinessRulesDocument $rules): CommissionAccrualStatus
    {
        if (in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::CancelledPostpaid], true)) {
            return CommissionAccrualStatus::Cancelled;
        }

        $cap = $rules->commission->capPct;

        if ($booking->commission_pct !== null
            && $booking->commission_pct > $cap
            && ! $booking->commission_approved
        ) {
            return CommissionAccrualStatus::Blocked;
        }

        $today = BusinessTime::now()->toDateString();
        $payable = self::payableDate($booking, $rules)->toDateString();

        if ($booking->status === BookingStatus::Completed && $payable <= $today) {
            return CommissionAccrualStatus::Payable;
        }

        return CommissionAccrualStatus::Accrued;
    }
}
