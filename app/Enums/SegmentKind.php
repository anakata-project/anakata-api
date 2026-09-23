<?php

declare(strict_types=1);

namespace App\Enums;

enum SegmentKind: string
{
    case Marketing = 'MARKETING';
    case Operational = 'OPERATIONAL';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Operational => 'Operational',
        };
    }
}
