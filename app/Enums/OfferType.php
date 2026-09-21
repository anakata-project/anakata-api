<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferType: string
{
    case Credit = 'CREDIT';
    case Amount = 'AMT';
    case Percent = 'PCT';
    case Value = 'VALUE';
    case Commission = 'COMM';

    public function isPriceAffecting(): bool
    {
        return $this !== self::Value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Credit => 'Ancillary credit (USD / cabin)',
            self::Amount => 'Amount off (USD / cabin)',
            self::Percent => 'Percent off cabin rate',
            self::Value => 'Value-add (no price change)',
            self::Commission => 'Extra partner commission (%)',
        };
    }
}
