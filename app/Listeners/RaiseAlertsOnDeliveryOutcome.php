<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\DeliveryOutcomeRecorded;
use App\Models\Delivery;
use App\Support\Alerts\AlertSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseAlertsOnDeliveryOutcome implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly AlertSweep $alerts) {}

    public function handle(DeliveryOutcomeRecorded $event): void
    {
        $delivery = Delivery::query()->find($event->delivery->id);

        if ($delivery instanceof Delivery) {
            $this->alerts->onDeliveryRecorded($delivery);
        }
    }
}
