<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Models\Journey;
use App\Models\JourneyStep;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Journey
 */
#[SchemaName('CrmJourneyResource')]
class CrmJourneyResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $journey = $this->resource;

        if (! $journey instanceof Journey) {
            return [];
        }

        return [
            'key' => $journey->key,
            'name' => $journey->name,
            'goal' => $journey->goal,
            'kind' => $journey->kind->value,
            'trigger' => $journey->triggerLine(),
            'exit_sentence' => $journey->exit_sentence,
            'suppression_sentence' => Journey::SUPPRESSION_SENTENCE,
            'contract' => $journey->contract,
            'active' => $journey->active,
            'system' => $journey->system,
            'steps' => $journey->steps->map(fn (JourneyStep $step): array => [
                'position' => $step->position,
                'timing' => $step->timingLabel(),
                'name' => $step->name,
                'template_key' => $step->template_key,
                'catalogue_key' => $step->catalogue_key,
                'catalogue_keys' => $step->pointerKeys(),
                'action' => $step->action->value,
                'count' => (int) ($step->getAttribute('active_count') ?? 0),
            ])->all(),
        ];
    }
}
