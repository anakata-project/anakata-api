<?php

declare(strict_types=1);

namespace App\Services\Pricing;

final readonly class QuoteInput
{
    public function __construct(
        public int $year,
        public QuoteType $type,
        public ?CabinCategory $category = null,
        public int $adults = 0,
        public int $children = 0,
        public bool $festive = false,
        public bool $backToBack = false,
    ) {}
}
