<?php

declare(strict_types=1);

namespace App\Services\Stripe;

final readonly class VerifiedStripeEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $payload,
    ) {}
}
