<?php

declare(strict_types=1);

namespace App\Services\Pricing;

final readonly class QuoteLine
{
    public function __construct(
        public string $code,
        public string $label,
        public int $amount,
    ) {}

    /**
     * @return array{code: string, label: string, amount: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'amount' => $this->amount,
        ];
    }
}
