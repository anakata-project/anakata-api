<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class HoldsRules
{
    public function __construct(
        public int $webMinutes,
        public int $webExtensionMinutes,
        public int $nearTermBusinessHours,
        public int $longLeadBusinessDays,
    ) {}

    /**
     * @return array{
     *     web_minutes: int,
     *     web_extension_minutes: int,
     *     near_term_business_hours: int,
     *     long_lead_business_days: int
     * }
     */
    public function toArray(): array
    {
        return [
            'web_minutes' => $this->webMinutes,
            'web_extension_minutes' => $this->webExtensionMinutes,
            'near_term_business_hours' => $this->nearTermBusinessHours,
            'long_lead_business_days' => $this->longLeadBusinessDays,
        ];
    }
}
