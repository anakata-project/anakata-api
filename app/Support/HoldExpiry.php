<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

final readonly class HoldExpiry
{
    public const NEAR_TERM = 'NEAR_TERM';

    public const LONG_LEAD = 'LONG_LEAD';

    public function __construct(
        public CarbonImmutable $expiresAt,
        public string $rule,
    ) {}
}
