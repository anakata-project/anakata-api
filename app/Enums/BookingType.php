<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Pricing\QuoteType;

enum BookingType: string
{
    case Cabin = 'CABIN';
    case Charter = 'CHARTER';

    public function quoteType(): QuoteType
    {
        return match ($this) {
            self::Cabin => QuoteType::Cabin,
            self::Charter => QuoteType::Charter,
        };
    }
}
