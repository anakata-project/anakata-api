<?php

declare(strict_types=1);

namespace App\Support\Documents\Snapshots;

use App\Enums\DocumentKind;
use App\Models\Booking;

final class PretripSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Booking $booking, bool $fresh): array
    {
        $facts = DocumentFacts::load($booking, $fresh);
        $itinerary = $booking->departure->itinerary;
        $description = $itinerary->long_description !== ''
            ? $itinerary->long_description
            : $itinerary->card_description;

        return [
            'document' => $facts->document(
                DocumentKind::Pretrip->value,
                'YOUR EXPEDITION',
                $facts->nextVersion(DocumentKind::Pretrip->value),
            ),
            'reference' => $booking->displayReference(),
            'itinerary_name' => $itinerary->name,
            'subtitle' => $itinerary->name.' · '.$facts->shortDate($booking->departure->date),
            'date' => $facts->shortDate($booking->departure->date),
            'description' => $description,
            'day_plan' => $itinerary->day_plan,
            'included' => $itinerary->included,
            'excluded' => $itinerary->excluded,
            'before_you_travel' => 'Weather and packing list: [content pending — guest experience team]. Your preferences questionnaire arrives with this itinerary.',
        ];
    }
}
