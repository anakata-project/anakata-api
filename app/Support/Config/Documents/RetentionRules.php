<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class RetentionRules
{
    public function __construct(
        public int $passportMonthsAfterCruise,
        public int $medicalDaysAfterCruise,
    ) {}

    /**
     * @return array{passport_months_after_cruise: int, medical_days_after_cruise: int}
     */
    public function toArray(): array
    {
        return [
            'passport_months_after_cruise' => $this->passportMonthsAfterCruise,
            'medical_days_after_cruise' => $this->medicalDaysAfterCruise,
        ];
    }
}
