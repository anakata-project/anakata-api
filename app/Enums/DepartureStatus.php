<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartureStatus: string
{
    case OnSale = 'ON_SALE';
    case Closed = 'CLOSED';
    case Hidden = 'HIDDEN';
    case Charter = 'CHARTER';

    public function label(): string
    {
        return match ($this) {
            self::OnSale => 'On sale',
            self::Closed => 'Closed to sale',
            self::Hidden => 'Hidden',
            self::Charter => 'Charter only',
        };
    }
}
