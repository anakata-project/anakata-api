<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CancellationBand
{
    public function __construct(
        public int $minDays,
        public int $penaltyPct,
    ) {}

    /**
     * @return array{min_days: int, penalty_pct: int}
     */
    public function toArray(): array
    {
        return [
            'min_days' => $this->minDays,
            'penalty_pct' => $this->penaltyPct,
        ];
    }
}
