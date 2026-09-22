<?php

declare(strict_types=1);

namespace App\Enums;

enum GuestResponseSource: string
{
    case GuestLink = 'GUEST_LINK';
    case Staff = 'STAFF';
}
