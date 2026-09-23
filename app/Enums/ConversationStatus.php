<?php

declare(strict_types=1);

namespace App\Enums;

enum ConversationStatus: string
{
    case Open = 'OPEN';
    case Closed = 'CLOSED';
}
