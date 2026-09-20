<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use App\Services\Pricing\QuotedParty;
use App\Services\Pricing\ReservationQuote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReservationQuote
 */
class ReservationQuoteResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     departure_id: int,
     *     type: string,
     *     back_to_back: bool,
     *     cabins: list<array{
     *         cabin_code: string|null,
     *         cabin_label: string,
     *         adults: int,
     *         children: int,
     *         available: bool,
     *         quote: array{lines: list<array{code: string, label: string, amount: int}>, total: int, deposit_pct: int, deposit: int}|null,
     *         errors: list<string>,
     *         warnings: list<string>
     *     }>,
     *     total: int|null,
     *     deposit: int|null,
     *     warnings: list<string>
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var ReservationQuote $quote */
        $quote = $this->resource;

        return [
            'departure_id' => $quote->departure->id,
            'type' => $quote->type->value,
            'back_to_back' => $quote->backToBack,
            'cabins' => array_map(
                fn (QuotedParty $party): array => [
                    'cabin_code' => $party->cabinCode,
                    'cabin_label' => $party->cabinLabel,
                    'adults' => $party->adults,
                    'children' => $party->children,
                    'available' => $party->available,
                    'quote' => $party->quote?->toArray(),
                    'errors' => $party->errors,
                    'warnings' => $party->warnings,
                ],
                $quote->parties,
            ),
            'total' => $quote->total(),
            'deposit' => $quote->deposit(),
            'warnings' => $quote->warnings,
        ];
    }
}
