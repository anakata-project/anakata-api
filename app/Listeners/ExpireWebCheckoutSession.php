<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\CheckoutSessionStatus;
use App\Events\HoldExpired;
use App\Models\CheckoutSession;

final class ExpireWebCheckoutSession
{
    public function handle(HoldExpired $event): void
    {
        $holder = $event->holder;

        if (! $holder instanceof CheckoutSession) {
            return;
        }

        if ($holder->status !== CheckoutSessionStatus::Holding) {
            return;
        }

        $holder->status = CheckoutSessionStatus::Expired;
        $holder->save();
    }
}
