<?php

declare(strict_types=1);

namespace App\Support\Documents\Snapshots;

use App\Enums\DocumentKind;
use App\Models\Booking;
use App\Models\Payment;

final class ReceiptSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Booking $booking, Payment $payment, bool $fresh): array
    {
        $facts = DocumentFacts::load($booking, $fresh);
        $issuer = $facts->issuer();
        $paidThrough = $facts->paidThrough($payment);

        return [
            'document' => $facts->document(
                DocumentKind::Receipt->value,
                'PAYMENT CONFIRMATION',
                1,
            ),
            'reference' => $payment->reference,
            'booking_reference' => $booking->displayReference(),
            'date' => $facts->shortDate($payment->paid_at),
            'kind' => $payment->kind->label(),
            'method' => $payment->method->label(),
            'amount' => $payment->amount,
            'balance_after' => $facts->chargesTotal - $paidThrough,
            'footer' => [
                'email' => $issuer['email'],
                'website' => $issuer['website'],
            ],
        ];
    }
}
