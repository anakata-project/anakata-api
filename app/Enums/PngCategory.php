<?php

declare(strict_types=1);

namespace App\Enums;

enum PngCategory: string
{
    case Pending = 'PENDING';
    case Exempt = 'EXEMPT';
    case NationalOrResident = 'NATIONAL_OR_RESIDENT';
    case CanAdult = 'CAN_ADULT';
    case CanMinor = 'CAN_MINOR';
    case ForeignOver12 = 'FOREIGN_OVER_12';
    case Foreign12AndUnder = 'FOREIGN_12_AND_UNDER';

    public function label(int $exemptUnderAge): string
    {
        return match ($this) {
            self::Pending => 'Pending — DOB & nationality needed',
            self::Exempt => 'Exempt (under '.$exemptUnderAge.')',
            self::NationalOrResident => 'National / resident',
            self::CanAdult => 'CAN adult (>12)',
            self::CanMinor => 'CAN minor (≤12)',
            self::ForeignOver12 => 'Foreign adult (>12)',
            self::Foreign12AndUnder => 'Foreign minor (≤12)',
        };
    }
}
