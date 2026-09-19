<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class DiscountsRules
{
    public function __construct(
        public int $onlineDepositDiscountPct,
        public ?int $maxTotalDiscountPct,
    ) {}

    /**
     * @return array{online_deposit_discount_pct: int, max_total_discount_pct: int|null}
     */
    public function toArray(): array
    {
        return [
            'online_deposit_discount_pct' => $this->onlineDepositDiscountPct,
            'max_total_discount_pct' => $this->maxTotalDiscountPct,
        ];
    }
}
