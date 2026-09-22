<?php

declare(strict_types=1);

namespace App\Enums;

enum AlertSeverity: string
{
    case Info = 'INFO';
    case Warn = 'WARN';
    case Critical = 'CRITICAL';
}
