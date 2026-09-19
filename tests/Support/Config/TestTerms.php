<?php

declare(strict_types=1);

namespace Tests\Support\Config;

final readonly class TestTerms
{
    public function __construct(
        public int $cabinDepositPct,
    ) {}
}
