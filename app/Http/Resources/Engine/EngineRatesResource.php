<?php

declare(strict_types=1);

namespace App\Http\Resources\Engine;

use App\Support\Config\Documents\RatesDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RatesDocument
 */
class EngineRatesResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     currency: string,
     *     years: list<int>,
     *     suite_pp_double: array<string, int>,
     *     owner_pp_double: array<string, int>,
     *     charter_week: array<string, int>,
     *     terms: array{
     *         cabin_deposit_pct: int,
     *         cabin_balance_days: int,
     *         charter_deposit_pct: int,
     *         charter_deposit_business_days: int,
     *         charter_balance_days: int
     *     },
     *     rules: array{
     *         single_supplement_pct: int,
     *         triple_discount_pct: int,
     *         child_discount_pct: int,
     *         child_discounts_per_adult: int,
     *         child_discounts_per_cabin: int,
     *         back_to_back_pct: int,
     *         festive_supplement_pp: int,
     *         festive_supplement_charter: int
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var RatesDocument $rates */
        $rates = $this->resource;

        $years = [];
        /** @var array<string, int> $suite */
        $suite = [];
        /** @var array<string, int> $owner */
        $owner = [];
        /** @var array<string, int> $charter */
        $charter = [];

        foreach ($rates->years as $year) {
            $key = (string) $year->year;
            $years[] = $year->year;
            $suite[$key] = $year->suitePp;
            $owner[$key] = $year->ownerPp;
            $charter[$key] = $year->charterWeek;
        }

        return [
            'currency' => $rates->currency,
            'years' => $years,
            'suite_pp_double' => $suite,
            'owner_pp_double' => $owner,
            'charter_week' => $charter,
            'terms' => $rates->terms->toArray(),
            'rules' => $rates->rules->toArray(),
        ];
    }
}
