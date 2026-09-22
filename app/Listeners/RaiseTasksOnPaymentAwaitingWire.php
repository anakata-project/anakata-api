<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentAwaitingWire;
use App\Models\Payment;
use App\Support\Crm\TaskSweep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class RaiseTasksOnPaymentAwaitingWire implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(private readonly TaskSweep $tasks) {}

    public function handle(PaymentAwaitingWire $event): void
    {
        $payment = Payment::query()->find($event->payment->id);

        if ($payment instanceof Payment) {
            $this->tasks->onPaymentAwaitingWire($payment);
        }
    }
}
