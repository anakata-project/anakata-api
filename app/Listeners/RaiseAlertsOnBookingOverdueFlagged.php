<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingOverdueFlagged;
use App\Models\Booking;
use App\Support\Alerts\AlertSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseAlertsOnBookingOverdueFlagged implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly AlertSweep $alerts) {}

    public function handle(BookingOverdueFlagged $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if ($booking instanceof Booking) {
            $this->alerts->onOverdueFlagged($booking);
        }
    }
}
