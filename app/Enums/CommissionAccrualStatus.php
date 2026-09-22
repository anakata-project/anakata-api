<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionAccrualStatus: string
{
    case EarnedOnCompletion = 'EARNED_ON_COMPLETION';
    case Payable = 'PAYABLE';
    case Paid = 'PAID';
    case Blocked = 'BLOCKED';
    case Cancelled = 'CANCELLED';
}
