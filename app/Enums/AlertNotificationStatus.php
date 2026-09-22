<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertNotificationStatus: string
{
    case Sent = 'SENT';
    case Failed = 'FAILED';
}
