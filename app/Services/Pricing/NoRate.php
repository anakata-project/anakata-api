<?php

declare(strict_types=1);

namespace App\Services\Pricing;

final readonly class NoRate
{
    public function __construct(
        public string $reason,
    ) {}

    /**
     * @return array{reason: string}
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason,
        ];
    }
}
