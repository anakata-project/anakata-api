<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final class BookingStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public Booking $booking,
        public BookingStatus $from,
        public BookingStatus $to,
    ) {}
}
