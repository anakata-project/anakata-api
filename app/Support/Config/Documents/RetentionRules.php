<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class RetentionRules
{
    public function __construct(
        public int $passportMonthsAfterCruise,
        public int $medicalDaysAfterCruise,
        public int $behaviouralRawMonths,
        public int $behaviouralUnstitchedDays,
    ) {}

    /**
     * @return array{passport_months_after_cruise: int, medical_days_after_cruise: int, behavioural_raw_months: int, behavioural_unstitched_days: int}
     */
    public function toArray(): array
    {
        return [
            'passport_months_after_cruise' => $this->passportMonthsAfterCruise,
            'medical_days_after_cruise' => $this->medicalDaysAfterCruise,
            'behavioural_raw_months' => $this->behaviouralRawMonths,
            'behavioural_unstitched_days' => $this->behaviouralUnstitchedDays,
        ];
    }
}
