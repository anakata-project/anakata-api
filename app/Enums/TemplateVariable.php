<?php

declare(strict_types=1);

namespace App\Enums;

enum TemplateVariable: string
{
    case FirstName = 'first_name';
    case BookingReference = 'booking_reference';
    case DepartureDate = 'departure_date';
    case ItineraryName = 'itinerary_name';
    case BalanceDueDate = 'balance_due_date';
    case DepositLink = 'deposit_link';
    case CompleteLink = 'complete_link';
    case UnsubscribeLink = 'unsubscribe_link';

    public function isLink(): bool
    {
        return match ($this) {
            self::DepositLink, self::CompleteLink, self::UnsubscribeLink => true,
            default => false,
        };
    }
}
