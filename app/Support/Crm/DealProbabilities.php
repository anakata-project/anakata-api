<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Services\Config\CurrentConfig;
use App\Support\Config\Documents\PipelineRules;

final class DealProbabilities
{
    public static function percent(DealStage $stage, ?PipelineRules $rules = null): int
    {
        $rules ??= app(CurrentConfig::class)->businessRules()->crm->pipeline;

        return match ($stage) {
            DealStage::NewLead => $rules->probabilityNewLead,
            DealStage::Qualifying => $rules->probabilityQualifying,
            DealStage::Quoted => $rules->probabilityQuoted,
            DealStage::Negotiation => $rules->probabilityNegotiation,
            DealStage::DepositPending => $rules->probabilityDepositPending,
            DealStage::BookingConfirmed, DealStage::WonCompleted => 100,
            DealStage::Lost => 0,
        };
    }

    public static function weighted(int $value, DealStage $stage, ?PipelineRules $rules = null): int
    {
        if (! $stage->weightsForecast()) {
            return 0;
        }

        return (int) round($value * self::percent($stage, $rules) / 100);
    }
}
