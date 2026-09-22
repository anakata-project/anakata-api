<?php

declare(strict_types=1);

namespace App\Support\Config\Documents;

final readonly class PrivacyRules
{
    public function __construct(
        public int $requestSlaDays,
    ) {}

    /**
     * @return array{request_sla_days: int}
     */
    public function toArray(): array
    {
        return [
            'request_sla_days' => $this->requestSlaDays,
        ];
    }
}
