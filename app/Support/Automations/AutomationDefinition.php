<?php

declare(strict_types=1);

namespace App\Support\Automations;

use App\Enums\AutomationAudience;
use App\Enums\AutomationKind;

final readonly class AutomationDefinition
{
    public function __construct(
        public string $key,
        public string $section,
        public string $sectionLabel,
        public string $name,
        public string $subject,
        public string $trigger,
        public string $timing,
        public ?string $location,
        public AutomationAudience $audience,
        public AutomationKind $kind,
        public bool $switchable,
        public ?string $lockedReason,
        public bool $built,
        public ?string $notBuiltNote,
        public ?string $alertKind,
        public ?string $journeyKey,
    ) {}
}
