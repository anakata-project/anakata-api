<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Open = 'OPEN';
    case Done = 'DONE';
    case AutoClosed = 'AUTO_CLOSED';
    case Cancelled = 'CANCELLED';

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
