<?php

declare(strict_types=1);

namespace App\Enums;

enum ClaimKind: string
{
    case Block = 'BLOCK';
    case Hold = 'HOLD';
    case Booking = 'BOOKING';
}
