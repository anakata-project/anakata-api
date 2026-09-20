<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class HoldsRules
{
    /**
     * @param  list<int>  $businessDays
     * @param  list<string>  $holidays
     */
    public function __construct(
        public int $webMinutes,
        public int $webExtensionMinutes,
        public int $nearTermBusinessHours,
        public int $longLeadBusinessDays,
        public array $businessDays,
        public string $businessDayStart,
        public string $businessDayEnd,
        public array $holidays,
        public int $nearTermMaxDays,
    ) {}

    /**
     * @return array{
     *     web_minutes: int,
     *     web_extension_minutes: int,
     *     near_term_business_hours: int,
     *     long_lead_business_days: int,
     *     business_days: list<int>,
     *     business_day_start: string,
     *     business_day_end: string,
     *     holidays: list<string>,
     *     near_term_max_days: int
     * }
     */
    public function toArray(): array
    {
        return [
            'web_minutes' => $this->webMinutes,
            'web_extension_minutes' => $this->webExtensionMinutes,
            'near_term_business_hours' => $this->nearTermBusinessHours,
            'long_lead_business_days' => $this->longLeadBusinessDays,
            'business_days' => $this->businessDays,
            'business_day_start' => $this->businessDayStart,
            'business_day_end' => $this->businessDayEnd,
            'holidays' => $this->holidays,
            'near_term_max_days' => $this->nearTermMaxDays,
        ];
    }
}
