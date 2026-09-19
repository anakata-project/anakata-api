<?php

declare(strict_types=1);

namespace App\Services\Pricing;

enum QuoteType: string
{
    case Cabin = 'CABIN';
    case Charter = 'CHARTER';
}
