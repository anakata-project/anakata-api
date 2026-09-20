<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use Carbon\CarbonImmutable;

final readonly class StripeCharge
{
    public function __construct(
        public string $id,
        public ?string $paymentIntentId,
        public int $amountUsd,
        public CarbonImmutable $createdAt,
        public string $description,
    ) {}
}
