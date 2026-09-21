<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferChannel: string
{
    case D2C = 'D2C';
    case B2B = 'B2B';
    case All = 'ALL';

    public function label(): string
    {
        return match ($this) {
            self::D2C => 'D2C — public engine',
            self::B2B => 'B2B — agencies (not shown publicly)',
            self::All => 'All channels',
        };
    }
}
