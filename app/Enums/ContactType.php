<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactType: string
{
    case DirectPassenger = 'DIRECT_PASSENGER';
    case TravelAgent = 'TRAVEL_AGENT';
    case CorporateCharter = 'CORPORATE_CHARTER';

    public function label(): string
    {
        return match ($this) {
            self::DirectPassenger => 'Direct Passenger',
            self::TravelAgent => 'Travel Agent',
            self::CorporateCharter => 'Corporate / Charter',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
