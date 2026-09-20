<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionAccrualStatus: string
{
    case Accrued = 'ACCRUED';
    case Payable = 'PAYABLE';
    case Blocked = 'BLOCKED';
    case Cancelled = 'CANCELLED';
}
