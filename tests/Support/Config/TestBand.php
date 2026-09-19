<?php

declare(strict_types=1);

namespace Tests\Support\Config;

final readonly class TestBand
{
    public function __construct(
        public int $min,
        public int $pct,
    ) {}
}
