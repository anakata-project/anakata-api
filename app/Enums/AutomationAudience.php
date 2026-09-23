<?php

declare(strict_types=1);

namespace App\Enums;

enum AutomationAudience: string
{
    case Customer = 'customer';
    case Staff = 'staff';
}
