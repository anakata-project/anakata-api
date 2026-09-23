<?php

declare(strict_types=1);

namespace App\Support\Metrics;

/**
 * Inclusive Galápagos calendar dates. Each metric states which column it applies this window to.
 */
final readonly class MetricWindow
{
    public function __construct(
        public string $from,
        public string $to,
    ) {}

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
