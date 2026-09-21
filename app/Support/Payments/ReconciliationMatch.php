<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * Reconciliation matching key: a Stripe charge's PaymentIntent against a
 * settlement row only (CARD_STRIPE / STRIPE_LINK, positive amount). Refund
 * rows carry re_… in gateway_id and must not win the bucket.
 */
final class ReconciliationMatch
{
    public static function settlementFor(string $paymentIntentId): ?Payment
    {
        $base = Payment::query()
            ->whereIn('method', [
                PaymentMethod::CardStripe,
                PaymentMethod::StripeLink,
            ])
            ->where('amount', '>', 0);

        $exact = (clone $base)->where('gateway_id', $paymentIntentId)->first();

        if ($exact instanceof Payment) {
            return $exact;
        }

        $rows = (clone $base)
            ->where('gateway_id', 'like', $paymentIntentId.'#%')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $lead = $rows->first();
        $lead->amount = (int) $rows->sum('amount');

        return $lead;
    }
}
