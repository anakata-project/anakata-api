<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\AutomationAudience;
use App\Enums\AutomationKind;
use App\Support\Automations\AutomationRow;
use App\Support\Iso;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin AutomationRow
 */
#[SchemaName('CrmAutomationResource')]
class AutomationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     key: string,
     *     section: string,
     *     section_label: string,
     *     name: string,
     *     subject: string,
     *     trigger: string,
     *     timing: string,
     *     location: string|null,
     *     audience: AutomationAudience,
     *     kind: AutomationKind,
     *     switchable: bool,
     *     locked_reason: string|null,
     *     built: bool,
     *     not_built_note: string|null,
     *     alert_kind: string|null,
     *     journey_key: string|null,
     *     enabled: bool,
     *     disabled_reason: string|null,
     *     disabled_by: string|null,
     *     disabled_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        if (! $row instanceof AutomationRow) {
            throw new LogicException('Automation resource expected a catalogue row.');
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
            'audience' => $this->audience($row),
            'kind' => $this->kind($row),
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

    private function audience(AutomationRow $row): AutomationAudience
    {
        return $row->definition->audience;
    }

    private function kind(AutomationRow $row): AutomationKind
    {
        return $row->definition->kind;
    }
}
