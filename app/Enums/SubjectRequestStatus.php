<?php

declare(strict_types=1);

namespace App\Enums;

enum SubjectRequestStatus: string
{
    case Open = 'OPEN';
    case Completed = 'COMPLETED';
    case Rejected = 'REJECTED';

    public function isClosed(): bool
    {
        return $this !== self::Open;
    }
}
