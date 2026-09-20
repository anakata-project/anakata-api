<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * Contract for task 02: one `payment.recorded` entry on the booking per row.
 */
final class PaymentHistory
{
    public const RECORDED = 'payment.recorded';

    public const SETTLED = 'payment.settled';

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
}
