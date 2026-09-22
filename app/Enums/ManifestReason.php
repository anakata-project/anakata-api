<?php

declare(strict_types=1);

namespace App\Enums;

enum ManifestReason: string
{
    case First = 'FIRST';
    case PassengerChange = 'PASSENGER_CHANGE';
    case Requested = 'REQUESTED';
}
