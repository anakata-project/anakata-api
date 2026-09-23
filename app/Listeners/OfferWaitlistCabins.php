<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AvailabilityChanged;
use App\Events\BookingStatusChanged;
use App\Events\HoldExpired;
use App\Support\Waitlist\WaitlistOffers;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class OfferWaitlistCabins implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly WaitlistOffers $offers) {}

    public function handle(AvailabilityChanged|HoldExpired|BookingStatusChanged $event): void
    {
        if ($event instanceof AvailabilityChanged) {
            $this->offers->forDepartures($event->departureIds);

            return;
        }

        if ($event instanceof HoldExpired) {
            $this->offers->forDepartures([(int) $event->claim->departure_id]);

            return;
        }

        if ($event->to->holdsInventory()) {
            return;
        }

        $this->offers->forDepartures([(int) $event->booking->departure_id]);
    }
}
