<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class BookingChargesChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public string $reason,
    ) {}
}
