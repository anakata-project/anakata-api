<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactLifecycle: string
{
    case Guest = 'GUEST';
    case Booked = 'BOOKED';
    case Sql = 'SQL';
    case PastGuest = 'PAST_GUEST';
    case Mql = 'MQL';
    case Prospect = 'PROSPECT';
    case Agent = 'AGENT';

    public function label(): string
    {
        return match ($this) {
            self::Guest => 'GUEST',
            self::Booked => 'BOOKED',
            self::Sql => 'SQL',
            self::PastGuest => 'PAST GUEST',
            self::Mql => 'MQL',
            self::Prospect => 'PROSPECT',
            self::Agent => 'AGENT',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $lifecycle): array => [
                'value' => $lifecycle->value,
                'label' => $lifecycle->label(),
            ],
            self::cases(),
        );
    }
}
