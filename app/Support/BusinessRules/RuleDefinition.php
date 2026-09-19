<?php

declare(strict_types=1);

namespace App\Support\BusinessRules;

use App\Enums\RuleGroup;
use App\Enums\RuleStatus;
use App\Enums\RuleWhere;

final readonly class RuleDefinition
{
    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public string $key,
        public RuleGroup $group,
        public string $sourceCode,
        public string $name,
        public RuleStatus $status,
        public RuleWhere $where,
        public array $paths,
        public string $sourceDisplay,
        public mixed $sourceValue,
        public string $usedIn,
        public ?string $lockReason = null,
        public ?string $note = null,
        public ?string $link = null,
    ) {}
}
