<?php

declare(strict_types=1);

namespace App\Http\Resources\Rms;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{
 *     matched: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string, booking_id?: int, reference?: string, ledger_amount?: int}>,
 *     in_gateway_not_rms: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string}>,
 *     to_review: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string, booking_id?: int, reference?: string, ledger_amount?: int}>,
 *     counts: array{matched: int, in_gateway_not_rms: int, to_review: int, gateway: int, discrepancies: int},
 *     meta: array{from: string, to: string, mode: string},
 *     note: string
 * } $resource
 */
class ReconciliationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{
     *     matched: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string, booking_id?: int, reference?: string, ledger_amount?: int}>,
     *     in_gateway_not_rms: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string}>,
     *     to_review: list<array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string, booking_id?: int, reference?: string, ledger_amount?: int}>,
     *     counts: array{matched: int, in_gateway_not_rms: int, to_review: int, gateway: int, discrepancies: int},
     *     meta: array{from: string, to: string, mode: string},
     *     note: string
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
