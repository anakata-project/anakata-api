<?php

declare(strict_types=1);

namespace App\Services\Stripe;

final readonly class CreatedPaymentLink
{
    public function __construct(
        public string $stripeId,
        public string $url,
    ) {}
}
