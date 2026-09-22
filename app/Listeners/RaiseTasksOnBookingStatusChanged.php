<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Support\Crm\TaskSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseTasksOnBookingStatusChanged implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly TaskSweep $tasks) {}

    public function handle(BookingStatusChanged $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if ($booking instanceof Booking) {
            $this->tasks->onBookingStatusChanged($booking, $event->from, $event->to);
        }
    }
}
