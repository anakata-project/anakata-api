<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Services\Config\CurrentConfig;
use App\Support\BusinessHours;
use App\Support\Config\Documents\BusinessRulesDocument;
use App\Support\Config\Documents\PipelineRules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class DealSla
{
    /**
     * @return array{state: string|null, label: string}
     */
    public static function forStage(?DealStage $stage, ?CarbonInterface $enteredAt, ?CarbonInterface $now = null): array
    {
        if (! $stage instanceof DealStage || ! $stage->isOpen() || ! $enteredAt instanceof CarbonInterface) {
            return ['state' => null, 'label' => 'SYSTEM-SET'];
        }

        $rules = app(CurrentConfig::class)->businessRules();
        $assessed = self::assess($stage, $enteredAt, $now ?? CarbonImmutable::now(), $rules);

        return [
            'state' => $assessed,
            'label' => self::label($stage, $rules),
        ];
    }

    public static function assess(
        DealStage $stage,
        CarbonInterface $enteredAt,
        CarbonInterface $now,
        BusinessRulesDocument $rules,
    ): string {
        $hours = BusinessHours::fromDocument($rules);
        $pipeline = $rules->crm->pipeline;
        $deadline = self::deadline($stage, $enteredAt, $hours, $pipeline, $rules->sla->responseHours);

        if ($now->greaterThanOrEqualTo($deadline)) {
            return 'bad';
        }

        $span = $enteredAt->diffInSeconds($deadline, false);

        if ($span <= 0) {
            return 'bad';
        }

        $elapsed = $enteredAt->diffInSeconds($now, false);

        return ($elapsed / $span) >= 0.75 ? 'warn' : 'ok';
    }

    public static function deadline(
        DealStage $stage,
        CarbonInterface $enteredAt,
        BusinessHours $hours,
        PipelineRules $pipeline,
        int $responseHours,
    ): CarbonImmutable {
        return match ($stage) {
            DealStage::NewLead => $hours->addBusinessHours($enteredAt, $pipeline->slaNewLeadBusinessHours),
            DealStage::Qualifying => $hours->endOfNthBusinessDay($enteredAt, $pipeline->slaQualifyingBusinessDays),
            DealStage::Quoted => $hours->addBusinessHours($enteredAt, $responseHours),
            DealStage::Negotiation => $hours->endOfNthBusinessDay($enteredAt, $pipeline->slaNegotiationBusinessDays),
            default => CarbonImmutable::instance($enteredAt),
        };
    }

    public static function label(DealStage $stage, BusinessRulesDocument $rules): string
    {
        $pipeline = $rules->crm->pipeline;

        return match ($stage) {
            DealStage::NewLead => $pipeline->slaNewLeadBusinessHours.' business hours',
            DealStage::Qualifying => $pipeline->slaQualifyingBusinessDays.' business days',
            DealStage::Quoted => $rules->sla->responseHours.' business hours',
            DealStage::Negotiation => $pipeline->slaNegotiationBusinessDays.' business days',
            default => 'SYSTEM-SET',
        };
    }
}
