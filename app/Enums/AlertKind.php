<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertKind: string
{
    case OverdueBalance = 'OVERDUE_BALANCE';
    case CommissionCap = 'COMMISSION_CAP';
    case WireNotReceived = 'WIRE_NOT_RECEIVED';
    case SlaBreach = 'SLA_BREACH';
    case DeliveryFailed = 'DELIVERY_FAILED';

    public function label(): string
    {
        return match ($this) {
            self::OverdueBalance => 'Overdue balance',
            self::CommissionCap => 'Commission cap',
            self::WireNotReceived => 'Wire not received',
            self::SlaBreach => 'SLA breach',
            self::DeliveryFailed => 'Delivery failed',
        };
    }
}
