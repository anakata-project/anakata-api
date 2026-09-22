<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Crm\OpenDealForBooking;
use App\Events\BookingCreated;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class OpenDealOnBookingCreated implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly OpenDealForBooking $deals) {}

    public function handle(BookingCreated $event): void
    {
        $booking = Booking::query()->find($event->booking->id);

        if (! $booking instanceof Booking) {
            return;
        }

        $this->deals->handle($booking);
    }
}
