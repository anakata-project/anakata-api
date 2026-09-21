<?php

declare(strict_types=1);

namespace App\Enums;

enum ScheduledRunOutcome: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
