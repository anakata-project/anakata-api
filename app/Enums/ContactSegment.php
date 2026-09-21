<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactSegment: string
{
    case High = 'HIGH';
    case Mid = 'MID';
    case New = 'NEW';
}
