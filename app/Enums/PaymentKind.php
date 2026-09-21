<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentKind: string
{
    case Deposit = 'DEPOSIT';
    case Balance = 'BALANCE';
    case Extras = 'EXTRAS';
    case Refund = 'REFUND';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Balance => 'Balance',
            self::Extras => 'Extras',
            self::Refund => 'Refund',
            self::Other => 'Other',
        };
    }

    public function letter(): string
    {
        return match ($this) {
            self::Deposit => 'D',
            self::Balance => 'B',
            self::Extras => 'E',
            self::Refund => 'R',
            self::Other => 'O',
        };
    }

    public function recordable(): bool
    {
        return $this !== self::Refund;
    }
}
