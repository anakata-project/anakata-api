<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class RateRules
{
    public function __construct(
        public int $singleSupplementPct,
        public int $tripleDiscountPct,
        public int $childDiscountPct,
        public int $childDiscountsPerAdult,
        public int $childDiscountsPerCabin,
        public int $backToBackPct,
        public int $festiveSupplementPp,
        public int $festiveSupplementCharter,
    ) {}

    /**
     * @return array{
     *     single_supplement_pct: int,
     *     triple_discount_pct: int,
     *     child_discount_pct: int,
     *     child_discounts_per_adult: int,
     *     child_discounts_per_cabin: int,
     *     back_to_back_pct: int,
     *     festive_supplement_pp: int,
     *     festive_supplement_charter: int
     * }
     */
    public function toArray(): array
    {
        return [
            'single_supplement_pct' => $this->singleSupplementPct,
            'triple_discount_pct' => $this->tripleDiscountPct,
            'child_discount_pct' => $this->childDiscountPct,
            'child_discounts_per_adult' => $this->childDiscountsPerAdult,
            'child_discounts_per_cabin' => $this->childDiscountsPerCabin,
            'back_to_back_pct' => $this->backToBackPct,
            'festive_supplement_pp' => $this->festiveSupplementPp,
            'festive_supplement_charter' => $this->festiveSupplementCharter,
        ];
    }
}
