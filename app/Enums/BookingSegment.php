<?php

declare(strict_types=1);

namespace App\Enums;

enum BookingSegment: string
{
    case Charter = 'CHARTER';
    case B2B = 'B2B';
    case D2C = 'D2C';
}
