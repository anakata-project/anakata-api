<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Support\Automations\AutomationRow;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AutomationRow
 */
#[SchemaName('CrmAutomationResource')]
class AutomationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        if (! $row instanceof AutomationRow) {
            return [];
        }

        $definition = $row->definition;

        return [
            'key' => $definition->key,
            'section' => $definition->section,
            'section_label' => $definition->sectionLabel,
            'name' => $definition->name,
            'subject' => $definition->subject,
            'trigger' => $definition->trigger,
            'timing' => $definition->timing,
            'location' => $definition->location,
            'audience' => $definition->audience->value,
            'kind' => $definition->kind->value,
            'switchable' => $definition->switchable,
            'locked_reason' => $definition->lockedReason,
            'built' => $definition->built,
            'not_built_note' => $definition->notBuiltNote,
            'alert_kind' => $definition->alertKind,
            'journey_key' => $definition->journeyKey,
            'enabled' => $row->enabled,
            'disabled_reason' => $row->disabledReason,
            'disabled_by' => $row->disabledBy,
            'disabled_at' => $row->disabledAt !== null ? Iso::utc($row->disabledAt) : null,
        ];
    }
}
