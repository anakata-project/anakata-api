<?php

declare(strict_types=1);

namespace App\Support\Agencies;

use App\Models\Agency;
use App\Support\Config\Documents\RatesDocument;

final class PortalPreview
{
    /**
     * @return array{commission_pct: int, net_rates: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>}
     */
    public static function for(Agency $agency, RatesDocument $rates): array
    {
        $net = [];

        foreach ($rates->years as $year) {
            $net[] = [
                'year' => $year->year,
                'suite_pp' => $agency->netOf($year->suitePp),
                'owner_pp' => $agency->netOf($year->ownerPp),
                'charter_week' => $agency->netOf($year->charterWeek),
            ];
        }

        return [
            'commission_pct' => $agency->commission_pct,
            'net_rates' => $net,
        ];
    }
}
