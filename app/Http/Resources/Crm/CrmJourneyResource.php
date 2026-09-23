<?php

declare(strict_types=1);

namespace App\Http\Resources\Crm;

use App\Enums\AutomationKind;
use App\Enums\JourneyStepAction;
use App\Models\Journey;
use App\Models\JourneyStep;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * @mixin Journey
 */
#[SchemaName('CrmJourneyResource')]
class CrmJourneyResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     key: string,
     *     name: string,
     *     goal: string,
     *     kind: AutomationKind,
     *     trigger: string,
     *     exit_sentence: string,
     *     suppression_sentence: string,
     *     contract: string|null,
     *     active: bool,
     *     system: bool,
     *     steps: list<array{
     *         position: int,
     *         branch: string,
     *         timing: string,
     *         name: string,
     *         template_key: string,
     *         catalogue_key: string|null,
     *         catalogue_keys: list<string>,
     *         action: JourneyStepAction,
     *         count: int
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        $journey = $this->resource;

        if (! $journey instanceof Journey) {
            throw new LogicException('Journey resource expected a journey.');
        }

        return [
            'key' => $journey->key,
            'name' => $journey->name,
            'goal' => $journey->goal,
            'kind' => $this->kind($journey),
            'trigger' => $journey->triggerLine(),
            'exit_sentence' => $journey->exit_sentence,
            'suppression_sentence' => Journey::SUPPRESSION_SENTENCE,
            'contract' => $journey->contract,
            'active' => $journey->active,
            'system' => $journey->system,
            'steps' => $this->steps($journey),
        ];
    }

    private function kind(Journey $journey): AutomationKind
    {
        return $journey->kind;
    }

    /**
     * @return list<array{
     *     position: int,
     *     branch: string,
     *     timing: string,
     *     name: string,
     *     template_key: string,
     *     catalogue_key: string|null,
     *     catalogue_keys: list<string>,
     *     action: JourneyStepAction,
     *     count: int
     * }>
     */
    private function steps(Journey $journey): array
    {
        $steps = [];

        foreach ($journey->steps as $step) {
            $steps[] = $this->step($step);
        }

        return $steps;
    }

    /**
     * @return array{
     *     position: int,
     *     branch: string,
     *     timing: string,
     *     name: string,
     *     template_key: string,
     *     catalogue_key: string|null,
     *     catalogue_keys: list<string>,
     *     action: JourneyStepAction,
     *     count: int
     * }
     */
    private function step(JourneyStep $step): array
    {
        return [
            'position' => $step->position,
            'branch' => $step->branch,
            'timing' => $step->timingLabel(),
            'name' => $step->name,
            'template_key' => $step->template_key,
            'catalogue_key' => $step->catalogue_key,
            'catalogue_keys' => $step->pointerKeys(),
            'action' => $this->stepAction($step),
            'count' => (int) ($step->getAttribute('active_count') ?? 0),
        ];
    }

    private function stepAction(JourneyStep $step): JourneyStepAction
    {
        return $step->action;
    }
}
