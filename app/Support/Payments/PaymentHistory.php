<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;
use App\Models\PaymentLink;

/**
 * Contract for payment ledger and payment-link history on the booking.
 */
final class PaymentHistory
{
    public const RECORDED = 'payment.recorded';

    public const SETTLED = 'payment.settled';

    public const LINK_CREATED = 'payment_link.created';

    public const LINK_CANCELLED = 'payment_link.cancelled';

    /**
     * @return array{reference: string, kind: string, method: string, amount: int, status: string}
     */
    public static function recordedPayload(Payment $payment): array
    {
        return [
            'reference' => $payment->reference,
            'kind' => $payment->kind->value,
            'method' => $payment->method->value,
            'amount' => $payment->amount,
            'status' => $payment->status->value,
        ];
    }

    /**
     * @return array{amount: int, stripe_id: string, kind: string}
     */
    public static function linkPayload(PaymentLink $link): array
    {
        return [
            'amount' => $link->amount,
            'stripe_id' => $link->stripe_id,
            'kind' => $link->kind->value,
        ];
    }
}
