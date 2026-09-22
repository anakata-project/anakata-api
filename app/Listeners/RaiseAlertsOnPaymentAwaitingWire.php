<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentAwaitingWire;
use App\Models\Payment;
use App\Support\Alerts\AlertSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseAlertsOnPaymentAwaitingWire implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly AlertSweep $alerts) {}

    public function handle(PaymentAwaitingWire $event): void
    {
        $payment = Payment::query()->find($event->payment->id);

        if ($payment instanceof Payment) {
            $this->alerts->onPaymentAwaitingWire($payment);
        }
    }
}
