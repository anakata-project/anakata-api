<?php

declare(strict_types=1);

namespace App\Support\Documents\Snapshots;

use App\Enums\DocumentKind;
use App\Models\Booking;

final class VoucherSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Booking $booking, bool $fresh): array
    {
        $facts = DocumentFacts::load($booking, $fresh);
        $issuer = $facts->issuer();
        $preHotel = $facts->hasPreCruiseHotel();
        $arrival = $preHotel
            ? $booking->departure->date->subDay()
            : $booking->departure->date;

        return [
            'document' => $facts->document(
                DocumentKind::Voucher->value,
                'TRANSFER VOUCHER',
                $facts->nextVersion(DocumentKind::Voucher->value),
            ),
            'reference' => $booking->displayReference(),
            'guests' => $facts->guestNames() === [] ? [$booking->contact->name] : $facts->guestNames(),
            'arrival' => $facts->shortDate($arrival),
            'transfer' => $preHotel
                ? 'Airport → hotel (pre-cruise night) → Puerto Baquerizo Moreno pier'
                : 'Airport → Puerto Baquerizo Moreno pier',
            'services' => $booking->extras->pluck('name')->values()->all(),
            'footer' => [
                'email' => $issuer['email'],
                'website' => $issuer['website'],
            ],
        ];
    }
}
