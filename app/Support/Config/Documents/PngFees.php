<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class PngFees
{
    public function __construct(
        public int $foreignOver12,
        public int $foreign12AndUnder,
        public int $canAdult,
        public int $canMinor,
        public int $nationalOrResident,
        public int $exemptUnderAge,
    ) {}

    /**
     * @return array{
     *     foreign_over_12: int,
     *     foreign_12_and_under: int,
     *     can_adult: int,
     *     can_minor: int,
     *     national_or_resident: int,
     *     exempt_under_age: int
     * }
     */
    public function toArray(): array
    {
        return [
            'foreign_over_12' => $this->foreignOver12,
            'foreign_12_and_under' => $this->foreign12AndUnder,
            'can_adult' => $this->canAdult,
            'can_minor' => $this->canMinor,
            'national_or_resident' => $this->nationalOrResident,
            'exempt_under_age' => $this->exemptUnderAge,
        ];
    }
}
