<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class RateTerms
{
    public function __construct(
        public int $cabinDepositPct,
        public int $cabinBalanceDays,
        public int $charterDepositPct,
        public int $charterDepositBusinessDays,
        public int $charterBalanceDays,
    ) {}

    /**
     * @return array{
     *     cabin_deposit_pct: int,
     *     cabin_balance_days: int,
     *     charter_deposit_pct: int,
     *     charter_deposit_business_days: int,
     *     charter_balance_days: int
     * }
     */
    public function toArray(): array
    {
        return [
            'cabin_deposit_pct' => $this->cabinDepositPct,
            'cabin_balance_days' => $this->cabinBalanceDays,
            'charter_deposit_pct' => $this->charterDepositPct,
            'charter_deposit_business_days' => $this->charterDepositBusinessDays,
            'charter_balance_days' => $this->charterBalanceDays,
        ];
    }
}
