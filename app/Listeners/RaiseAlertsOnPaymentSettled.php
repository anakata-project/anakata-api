<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentSettled;
use App\Models\Payment;
use App\Support\Alerts\AlertSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseAlertsOnPaymentSettled implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly AlertSweep $alerts) {}

    public function handle(PaymentSettled $event): void
    {
        $payment = Payment::query()->find($event->payment->id);

        if ($payment instanceof Payment) {
            $this->alerts->onPaymentSettled($payment);
        }
    }
}
