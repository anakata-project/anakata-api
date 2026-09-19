<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class RateYear
{
    public function __construct(
        public int $year,
        public int $suitePp,
        public int $ownerPp,
        public int $charterWeek,
    ) {}

    /**
     * @return array{year: int, suite_pp: int, owner_pp: int, charter_week: int}
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'suite_pp' => $this->suitePp,
            'owner_pp' => $this->ownerPp,
            'charter_week' => $this->charterWeek,
        ];
    }

    public function price(string $category): int
    {
        return match ($category) {
            'SUITE' => $this->suitePp,
            'OWNER' => $this->ownerPp,
            'CHARTER' => $this->charterWeek,
            default => 0,
        };
    }
}
