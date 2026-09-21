<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use Carbon\CarbonImmutable;

final readonly class CreatedCheckoutSession
{
    public function __construct(
        public string $stripeId,
        public string $url,
        public CarbonImmutable $expiresAt,
    ) {}
}
