<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class ManifestsRules
{
    public function __construct(
        public int $dpngFitDays,
        public int $dpngCharterDays,
        public int $captainDays,
        public int $chaseDaysBeforeDue,
    ) {}

    /**
     * @return array{dpng_fit_days: int, dpng_charter_days: int, captain_days: int, chase_days_before_due: int}
     */
    public function toArray(): array
    {
        return [
            'dpng_fit_days' => $this->dpngFitDays,
            'dpng_charter_days' => $this->dpngCharterDays,
            'captain_days' => $this->captainDays,
            'chase_days_before_due' => $this->chaseDaysBeforeDue,
        ];
    }
}
