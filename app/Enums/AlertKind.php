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
    case ConfirmedAtDeparture = 'CONFIRMED_AT_DEPARTURE';
    case LedgerDrift = 'LEDGER_DRIFT';
    case CommissionLeakage = 'COMMISSION_LEAKAGE';
    case LowOccupancy = 'LOW_OCCUPANCY';
    case ManifestDataOverdue = 'MANIFEST_DATA_OVERDUE';
    case NpsLow = 'NPS_LOW';
    case ReportFailed = 'REPORT_FAILED';
    case CharterDepositDue = 'CHARTER_DEPOSIT_DUE';

    public function label(): string
    {
        return match ($this) {
            self::OverdueBalance => 'Overdue balance',
            self::CommissionCap => 'Commission cap',
            self::WireNotReceived => 'Wire not received',
            self::SlaBreach => 'SLA breach',
            self::DeliveryFailed => 'Delivery failed',
            self::ConfirmedAtDeparture => 'Confirmed at departure',
            self::LedgerDrift => 'Ledger drift',
            self::CommissionLeakage => 'Commission leakage',
            self::LowOccupancy => 'Low occupancy',
            self::ManifestDataOverdue => 'Manifest data overdue',
            self::NpsLow => 'NPS below threshold',
            self::ReportFailed => 'Report failed',
            self::CharterDepositDue => 'Charter deposit due',
        };
    }
}
