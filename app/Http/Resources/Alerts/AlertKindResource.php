<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Enums\Permission;
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
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'kind' => $this->kind->value,
            'label' => $this->label(),
            'severity' => $this->severity->value,
            'audience' => array_map(fn (Permission $permission): array => [
                'value' => $permission->value,
                'label' => $permission->label(),
            ], $this->audience),
            'condition' => $this->condition,
            'resolves_when' => $this->resolvesWhen,
            'emails' => $this->emails(),
            'section' => $this->section,
        ];
    }
}
