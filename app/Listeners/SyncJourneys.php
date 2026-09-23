<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgencyApproved;
use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Events\DealMarkedLost;
use App\Events\PaymentSettled;
use App\Support\Journeys\JourneyEngine;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SyncJourneys implements ShouldQueue
{
    public function __construct(private readonly JourneyEngine $engine) {}

    public function handle(BookingCreated|BookingStatusChanged|PaymentSettled|AgencyApproved|DealMarkedLost $event): void
    {
        match (true) {
            $event instanceof BookingCreated => $this->engine->onBookingCreated($event),
            $event instanceof BookingStatusChanged => $this->engine->onBookingStatusChanged($event),
            $event instanceof PaymentSettled => $this->engine->onPaymentSettled($event),
            $event instanceof AgencyApproved => $this->engine->onAgencyApproved($event),
            $event instanceof DealMarkedLost => $this->engine->onDealMarkedLost($event),
        };
    }
}
