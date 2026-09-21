<?php

declare(strict_types=1);

namespace App\Services\Stripe;

final readonly class RetrievedCheckoutSession
{
    public function __construct(
        public string $stripeId,
        public string $status,
        public ?string $paymentIntentId = null,
    ) {}
}
