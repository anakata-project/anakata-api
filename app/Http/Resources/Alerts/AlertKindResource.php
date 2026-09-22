<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Enums\AlertKind;
use App\Enums\AlertSeverity;
use App\Support\Alerts\AlertKindDefinition;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AlertKindDefinition
 */
#[SchemaName('AlertKindResource')]
class AlertKindResource extends JsonResource
{
    /**
     * @return array{
     *     kind: AlertKind,
     *     label: string,
     *     severity: AlertSeverity,
     *     audience: list<array{value: string, label: string}>,
     *     condition: string,
     *     resolves_when: string,
     *     emails: bool,
     *     section: 'rms'|'crm'
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind,
            'label' => $this->label(),
            'severity' => $this->severity,
            'audience' => $this->audienceRows(),
            'condition' => $this->condition,
            'resolves_when' => $this->resolvesWhen,
            'emails' => $this->emails(),
            'section' => $this->section,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function audienceRows(): array
    {
        $rows = [];

        foreach ($this->audience as $permission) {
            $rows[] = [
                'value' => $permission->value,
                'label' => $permission->label(),
            ];
        }

        return $rows;
    }
}
