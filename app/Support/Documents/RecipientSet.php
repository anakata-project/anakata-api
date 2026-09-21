<?php

declare(strict_types=1);

namespace App\Support\Documents;

final readonly class RecipientSet
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     */
    public function __construct(
        public array $to,
        public array $cc,
        public ?string $blockedReason,
    ) {}

    public function usable(): bool
    {
        return $this->blockedReason === null && $this->to !== [];
    }
}
