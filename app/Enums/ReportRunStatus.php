<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportRunStatus: string
{
    case Queued = 'QUEUED';
    case Ready = 'READY';
    case Failed = 'FAILED';
}
