<?php

declare(strict_types=1);

namespace App\Support\Crm;

use App\Enums\DealStage;
use App\Models\User;
use App\Services\Config\CurrentConfig;
use App\Support\Payments\PaymentsKpis;
use Illuminate\Support\Facades\DB;

final class PipelineBoard
{
    /**
     * @param  array{owner?: string|null, type?: string|null, q?: string|null}  $filters
     * @return array{columns: list<array<string, mixed>>, meta: array{kpis: array<string, int>}}
     */
    public static function build(User $actor, array $filters): array
    {
        $rules = app(CurrentConfig::class)->businessRules();
        $rows = self::rows($actor, $filters);
        $columns = [];

        foreach (DealStage::columns() as $stage) {
            $columns[$stage->value] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'owner' => $stage->owner(),
                'sla' => $stage->isOpen() ? DealSla::label($stage, $rules) : 'SYSTEM-SET',
                'total' => 0,
                'weighted_total' => $stage->weightsForecast() ? 0 : null,
                'deals' => [],
            ];
        }

        foreach ($rows as $row) {
            $stage = DealStage::tryFrom((string) ($row->projected_stage ?? ''));

            if (! $stage instanceof DealStage) {
                continue;
            }

            $card = DealCard::fromRow($row, $actor);
            $columns[$stage->value]['total'] += $card['value'];

            if ($stage->weightsForecast()) {
                $columns[$stage->value]['weighted_total'] += DealProbabilities::weighted($card['value'], $stage, $rules->crm->pipeline);
            }

            $columns[$stage->value]['deals'][] = $card;
        }

        return [
            'columns' => array_values($columns),
            'meta' => ['kpis' => self::kpis()],
        ];
    }

    /**
     * @return array{
     *     collected: int,
     *     scheduled_in: int,
     *     awaiting_first_payment: int,
     *     open_pipeline_count: int,
     *     open_pipeline_value: int,
     *     weighted_forecast: int,
     *     overdue: int
     * }
     */
    public static function kpis(): array
    {
        $ledger = PaymentsKpis::ledger();
        $rules = app(CurrentConfig::class)->businessRules()->crm->pipeline;
        $stage = '('.DealStages::stageSql().')';
        $value = '('.DealStages::valueSql().')';
        $open = "'NEW_LEAD','QUALIFYING','QUOTED','NEGOTIATION','DEPOSIT_PENDING'";
        $weight = "CASE {$stage}
            WHEN 'NEW_LEAD' THEN {$rules->probabilityNewLead}
            WHEN 'QUALIFYING' THEN {$rules->probabilityQualifying}
            WHEN 'QUOTED' THEN {$rules->probabilityQuoted}
            WHEN 'NEGOTIATION' THEN {$rules->probabilityNegotiation}
            WHEN 'DEPOSIT_PENDING' THEN {$rules->probabilityDepositPending}
            ELSE 0 END";

        $row = DB::table('deals')->selectRaw(
            "COALESCE(SUM(CASE WHEN {$stage} IN ({$open}) THEN 1 ELSE 0 END), 0) AS open_count,
             COALESCE(SUM(CASE WHEN {$stage} IN ({$open}) THEN {$value} ELSE 0 END), 0) AS open_value,
             COALESCE(SUM(CASE WHEN {$stage} IN ({$open}) THEN ROUND({$value} * ({$weight}) / 100) ELSE 0 END), 0) AS weighted",
        )->first();

        return [
            'collected' => $ledger['collected'],
            'scheduled_in' => PaymentsKpis::scheduledIn(),
            'awaiting_first_payment' => $ledger['pending'],
            'open_pipeline_count' => (int) ($row->open_count ?? 0),
            'open_pipeline_value' => (int) ($row->open_value ?? 0),
            'weighted_forecast' => (int) ($row->weighted ?? 0),
            'overdue' => $ledger['overdue_amount'],
        ];
    }

    /**
     * @param  array{owner?: string|null, type?: string|null, q?: string|null, stage?: string|null}  $filters
     * @return list<object>
     */
    private static function rows(User $actor, array $filters): array
    {
        return DealQuery::filtered($actor, $filters)
            ->orderBy('deals.id')
            ->get()
            ->all();
    }
}
