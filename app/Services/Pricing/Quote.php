<?php

declare(strict_types=1);

namespace App\Services\Pricing;

final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public array $lines,
        public int $total,
        public int $depositPct,
        public int $deposit,
    ) {}

    /**
     * @return array{
     *     lines: list<array{code: string, label: string, amount: int}>,
     *     total: int,
     *     deposit_pct: int,
     *     deposit: int
     * }
     */
    public function toArray(): array
    {
        return [
            'lines' => array_map(
                fn (QuoteLine $line): array => $line->toArray(),
                $this->lines,
            ),
            'total' => $this->total,
            'deposit_pct' => $this->depositPct,
            'deposit' => $this->deposit,
        ];
    }
}
