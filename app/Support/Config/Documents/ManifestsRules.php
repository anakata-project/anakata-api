<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class ManifestsRules
{
    public function __construct(
        public int $dpngFitDays,
        public int $dpngCharterDays,
    ) {}

    /**
     * @return array{dpng_fit_days: int, dpng_charter_days: int}
     */
    public function toArray(): array
    {
        return [
            'dpng_fit_days' => $this->dpngFitDays,
            'dpng_charter_days' => $this->dpngCharterDays,
        ];
    }
}
