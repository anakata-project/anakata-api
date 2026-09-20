<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Booking;
use App\Models\Payment;

final readonly class RecordedPayment
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Payment $payment,
        public Booking $booking,
        public array $warnings,
    ) {}
}
