<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentLinkStatus: string
{
    case Open = 'OPEN';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }
}
