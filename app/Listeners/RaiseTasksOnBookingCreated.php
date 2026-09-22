<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Support\Crm\TaskSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseTasksOnBookingCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly TaskSweep $tasks) {}

    public function handle(BookingCreated $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if ($booking instanceof Booking) {
            $this->tasks->onBookingCreated($booking);
        }
    }
}
