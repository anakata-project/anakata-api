<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Support\Config\Change;
use App\Support\Config\Warning;

final readonly class ValidationReport
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  list<Warning>  $warnings
     * @param  list<Change>  $changes
     */
    public function __construct(
        public array $errors,
        public array $warnings,
        public array $changes,
    ) {}

    /**
     * @return array{
     *     errors: array<string, list<string>>,
     *     warnings: list<array{path: string, message: string}>,
     *     changes: list<array{path: string, label: string, from: mixed, to: mixed}>
     * }
     */
    public function toArray(): array
    {
        return [
            'errors' => $this->errors,
            'warnings' => array_map(
                fn (Warning $warning): array => $warning->toArray(),
                $this->warnings,
            ),
            'changes' => array_map(
                fn (Change $change): array => $change->toArray(),
                $this->changes,
            ),
        ];
    }
}
