<?php

declare(strict_types=1);

namespace App\Enums;

enum HoldRule: string
{
    case NearTerm = 'NEAR_TERM';
    case LongLead = 'LONG_LEAD';
}
