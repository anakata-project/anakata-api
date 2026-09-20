<?php

declare(strict_types=1);

namespace App\Enums;

enum CabinState: string
{
    case Free = 'FREE';
    case Held = 'HELD';
    case Sold = 'SOLD';
    case Blocked = 'BLOCKED';
}
