<?php

declare(strict_types=1);

namespace App\Enums;

enum MainChannel: string
{
    case D2C = 'D2C';
    case B2B = 'B2B';
    case B2BTravelAdvisor = 'B2B – Travel Advisor';
    case B2BTourOperator = 'B2B – Tour Operator';
    case B2BCorporate = 'B2B – Corporate';
    case WholesaleDistribution = 'Wholesale / Distribution';
    case Partners = 'Partners';
    case Other = 'Other';

    public function label(): string
    {
        return $this->value;
    }

    public function isTrade(): bool
    {
        return str_starts_with($this->value, 'B2B')
            || str_starts_with($this->value, 'Wholesale');
    }

    public function segment(): BookingSegment
    {
        if (str_starts_with($this->value, 'B2B')
            || str_starts_with($this->value, 'Wholesale')
            || $this === self::Partners) {
            return BookingSegment::B2B;
        }

        return BookingSegment::D2C;
    }
}
