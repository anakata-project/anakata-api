<?php

declare(strict_types=1);

namespace App\Enums;

enum BlockReason: string
{
    case FamTrip = 'FAM_TRIP';
    case Maintenance = 'MAINTENANCE';
    case NegotiationHold = 'NEGOTIATION_HOLD';
    case Courtesy = 'COURTESY';

    public function label(): string
    {
        return match ($this) {
            self::FamTrip => 'Fam trip',
            self::Maintenance => 'Maintenance',
            self::NegotiationHold => 'Negotiation hold',
            self::Courtesy => 'Courtesy',
        };
    }
}
