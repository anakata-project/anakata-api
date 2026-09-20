<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Services\Stripe\StripeCharge;
use App\Services\Stripe\StripeGateway;
use App\Support\BusinessTime;
use App\Support\Iso;

final class ReconciliationReport
{
    public const WIRES_NOTE = 'Wire transfers are not in Stripe. They reconcile against the OpCo bank statement (bank details pending LEG-004).';

    public function __construct(private readonly StripeGateway $stripe) {}

    /**
     * @return array{
     *     matched: list<array<string, mixed>>,
     *     in_gateway_not_rms: list<array<string, mixed>>,
     *     to_review: list<array<string, mixed>>,
     *     counts: array{matched: int, in_gateway_not_rms: int, to_review: int},
     *     meta: array{from: string, to: string, mode: string},
     *     note: string
     * }
     */
    public function forWindow(string $from, string $to): array
    {
        $fromUtc = BusinessTime::dayStartUtc($from);
        $toUtc = BusinessTime::dayEndUtc($to);
        $charges = $this->stripe->listCharges($fromUtc, $toUtc);

        $matched = [];
        $unmatched = [];
        $review = [];

        foreach ($charges as $charge) {
            $row = $this->row($charge);
            $intent = $charge->paymentIntentId;

            if ($intent === null || $intent === '') {
                $unmatched[] = $row;

                continue;
            }

            $settlement = ReconciliationMatch::settlementFor($intent);

            if ($settlement === null) {
                $unmatched[] = $row;

                continue;
            }

            $row['booking_id'] = $settlement->booking_id;
            $row['reference'] = $settlement->reference;
            $row['ledger_amount'] = $settlement->amount;

            if ($settlement->amount === $charge->amountUsd) {
                $matched[] = $row;
            } else {
                $review[] = $row;
            }
        }

        return [
            'matched' => $matched,
            'in_gateway_not_rms' => $unmatched,
            'to_review' => $review,
            'counts' => [
                'matched' => count($matched),
                'in_gateway_not_rms' => count($unmatched),
                'to_review' => count($review),
            ],
            'meta' => [
                'from' => $from,
                'to' => $to,
                'mode' => (string) config('services.stripe.mode'),
            ],
            'note' => self::WIRES_NOTE,
        ];
    }

    /**
     * @return array{gateway: string, stripe_id: string, payment_intent: string|null, date: string, amount: int, description: string}
     */
    private function row(StripeCharge $charge): array
    {
        return [
            'gateway' => 'Stripe',
            'stripe_id' => $charge->id,
            'payment_intent' => $charge->paymentIntentId,
            'date' => Iso::utc($charge->createdAt),
            'amount' => $charge->amountUsd,
            'description' => $charge->description,
        ];
    }
}
