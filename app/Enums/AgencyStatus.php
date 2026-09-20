<?php

declare(strict_types=1);

namespace App\Enums;

enum AgencyStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
}
