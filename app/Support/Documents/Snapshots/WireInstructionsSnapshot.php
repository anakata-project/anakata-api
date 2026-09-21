<?php

declare(strict_types=1);

namespace App\Support\Documents\Snapshots;

use App\Enums\BookingStatus;
use App\Enums\DocumentKind;
use App\Models\Booking;
use App\Services\Config\CurrentConfig;

final class WireInstructionsSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Booking $booking, bool $fresh): array
    {
        $facts = DocumentFacts::load($booking, $fresh);
        $issuer = $facts->issuer();
        $amount = $facts->wireAmount();
        $hours = app(CurrentConfig::class)->businessRules()->payments->wireWindowHours;
        $bank = $facts->bank();

        return [
            'document' => $facts->document(
                DocumentKind::WireInstructions->value,
                'WIRE INSTRUCTIONS',
                $facts->nextVersion(DocumentKind::WireInstructions->value),
            ),
            'reference' => $booking->displayReference(),
            'amount' => $amount,
            'amount_label' => $booking->status === BookingStatus::PendingPayment
                ? 'Deposit due'
                : 'Amount due',
            'bank' => $bank,
            'wire_window_hours' => $hours,
            'placeholders' => $bank['placeholders'],
            'footer' => [
                'email' => $issuer['email'],
                'website' => $issuer['website'],
            ],
        ];
    }
}
