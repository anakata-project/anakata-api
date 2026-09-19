<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CalendarSettings
{
    public function __construct(
        public string $defaultSearchFrom,
        public string $defaultSearchTo,
        public int $defaultAdults,
        public int $horizonMonths,
    ) {}

    /**
     * @return array{
     *     default_search_from: string,
     *     default_search_to: string,
     *     default_adults: int,
     *     horizon_months: int
     * }
     */
    public function toArray(): array
    {
        return [
            'default_search_from' => $this->defaultSearchFrom,
            'default_search_to' => $this->defaultSearchTo,
            'default_adults' => $this->defaultAdults,
            'horizon_months' => $this->horizonMonths,
        ];
    }
}
