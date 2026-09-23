<?php

declare(strict_types=1);

namespace App\Enums;

enum SegmentDimension: string
{
    case Behaviour = 'BEHAVIOUR';
    case Interest = 'INTEREST';
    case Location = 'LOCATION';
    case Profile = 'PROFILE';
    case Promotion = 'PROMOTION';
}
