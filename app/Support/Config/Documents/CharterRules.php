<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class CharterRules
{
    public function __construct(
        public int $depositBusinessDays,
        public int $proposalValidBusinessDays,
    ) {}

    /**
     * @return array{deposit_business_days: int, proposal_valid_business_days: int}
     */
    public function toArray(): array
    {
        return [
            'deposit_business_days' => $this->depositBusinessDays,
            'proposal_valid_business_days' => $this->proposalValidBusinessDays,
        ];
    }
}
