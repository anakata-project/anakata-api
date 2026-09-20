<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Settled = 'SETTLED';
    case AwaitingWire = 'AWAITING_WIRE';
    case Refunded = 'REFUNDED';

    public function label(): string
    {
        return match ($this) {
            self::Settled => 'Settled',
            self::AwaitingWire => 'Awaiting wire',
            self::Refunded => 'Refunded',
        };
    }

    public function countsAsPaid(): bool
    {
        return $this !== self::AwaitingWire;
    }

    /**
     * @return list<string>
     */
    public static function paidValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->countsAsPaid()),
        ));
    }
}
