<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class AlertsRules
{
    public function __construct(
        public int $lowOccupancyPct,
        public int $lowOccupancyDaysBefore,
    ) {}

    /**
     * @return array{low_occupancy_pct: int, low_occupancy_days_before: int}
     */
    public function toArray(): array
    {
        return [
            'low_occupancy_pct' => $this->lowOccupancyPct,
            'low_occupancy_days_before' => $this->lowOccupancyDaysBefore,
        ];
    }
}
