<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;

class RatesCurrentResource extends ConfigCurrentResource
{
    /**
     * @return array{
     *     version: int,
     *     document: array{
     *         currency: string,
     *         years: list<array{year: int, suite_pp: int, owner_pp: int, charter_week: int}>,
     *         terms: array{
     *             cabin_deposit_pct: int,
     *             cabin_balance_days: int,
     *             charter_deposit_pct: int,
     *             charter_deposit_business_days: int,
     *             charter_balance_days: int
     *         },
     *         rules: array{
     *             single_supplement_pct: int,
     *             triple_discount_pct: int,
     *             child_discount_pct: int,
     *             child_discounts_per_adult: int,
     *             child_discounts_per_cabin: int,
     *             back_to_back_pct: int,
     *             festive_supplement_pp: int,
     *             festive_supplement_charter: int
     *         }
     *     },
     *     published_at: string,
     *     published_by: array{id: int, name: string}|null,
     *     approval_reference: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request);
    }
}
