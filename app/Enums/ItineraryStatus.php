<?php

declare(strict_types=1);

namespace App\Enums;

enum ItineraryStatus: string
{
    case Draft = 'DRAFT';
    case Published = 'PUBLISHED';
    case Hidden = 'HIDDEN';
}
