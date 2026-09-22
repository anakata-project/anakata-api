<?php

declare(strict_types=1);

namespace App\Enums;

enum DealType: string
{
    case Fit = 'FIT';
    case Group = 'GROUP';
    case Charter = 'CHARTER';
    case Agency = 'AGENCY';

    public function label(): string
    {
        return match ($this) {
            self::Fit => 'FIT',
            self::Group => 'Group',
            self::Charter => 'Charter',
            self::Agency => 'Agency',
        };
    }
}
