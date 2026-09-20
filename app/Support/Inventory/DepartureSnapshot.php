<?php

declare(strict_types=1);

namespace App\Support\Inventory;

final readonly class DepartureSnapshot
{
    /**
     * @param  list<array{cabin: array{code: string, label: string, category: string}, state: string, claim: array{kind: string, hold_type: string|null, expires_at: string|null, holder: array{type: string, id: int, reference: string|null, label: string|null}}|null}>  $cabins
     * @param  array{sold: int, held: int, blocked: int, free: int, suites_free: int, owner_free: bool}  $counts
     * @param  array{code: string, text: string, tone: string}  $engineLabel
     * @param  array{date_and_yacht: bool, delete: bool, reason: string|null}  $locks
     */
    public function __construct(
        public array $cabins,
        public array $counts,
        public array $engineLabel,
        public array $locks,
    ) {}
}
