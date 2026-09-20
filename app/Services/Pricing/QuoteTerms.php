<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\BookingType;
use App\Services\Config\CurrentConfig;

final readonly class QuoteTerms
{
    /**
     * @param  array{deposit_pct: int, deposit_business_days: int, balance_days: int, dpng_manifest_days: int}|null  $charter
     */
    public function __construct(
        public int $balanceDays,
        public ?array $charter,
    ) {}

    public static function fromConfig(CurrentConfig $config, BookingType $type): self
    {
        $rates = $config->rates()->terms;

        if ($type === BookingType::Charter) {
            return new self(
                balanceDays: $rates->charterBalanceDays,
                charter: [
                    'deposit_pct' => $rates->charterDepositPct,
                    'deposit_business_days' => $rates->charterDepositBusinessDays,
                    'balance_days' => $rates->charterBalanceDays,
                    'dpng_manifest_days' => $config->businessRules()->manifests->dpngCharterDays,
                ],
            );
        }

        return new self(
            balanceDays: $rates->cabinBalanceDays,
            charter: null,
        );
    }

    /**
     * @return array{
     *     balance_days: int,
     *     charter: array{deposit_pct: int, deposit_business_days: int, balance_days: int, dpng_manifest_days: int}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'balance_days' => $this->balanceDays,
            'charter' => $this->charter,
        ];
    }
}
