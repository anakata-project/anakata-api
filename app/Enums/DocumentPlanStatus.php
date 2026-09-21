<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentPlanStatus: string
{
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Blocked = 'BLOCKED';
    case Scheduled = 'SCHEDULED';
    case Waiting = 'WAITING';
    case NotNeeded = 'NOT NEEDED';
    case NotContracted = 'NOT CONTRACTED';
    case Due = 'DUE';
}
