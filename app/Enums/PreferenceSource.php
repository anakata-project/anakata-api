<?php

declare(strict_types=1);

namespace App\Enums;

enum PreferenceSource: string
{
    case GuestLink = 'GUEST_LINK';
    case Staff = 'STAFF';
}
