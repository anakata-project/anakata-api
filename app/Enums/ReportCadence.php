<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportCadence: string
{
    case Daily = 'DAILY';
    case Weekly = 'WEEKLY';
    case Monthly = 'MONTHLY';
    case Quarterly = 'QUARTERLY';
}
