<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class PaymentSettled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public Payment $payment,
    ) {}
}
