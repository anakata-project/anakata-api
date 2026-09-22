<?php

declare(strict_types=1);

namespace App\Support\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Enums\Permission;

final readonly class AlertKindDefinition
{
    /**
     * @param  list<Permission>  $audience
     * @param  'rms'|'crm'  $section
     */
    public function __construct(
        public AlertKind $kind,
        public AlertSeverity $severity,
        public array $audience,
        public string $condition,
        public string $resolvesWhen,
        public string $section,
    ) {}

    public function emails(): bool
    {
        return $this->severity === AlertSeverity::Critical;
    }

    public function label(): string
    {
        return $this->kind->label();
    }
}
