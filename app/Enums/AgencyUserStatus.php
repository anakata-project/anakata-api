<?php

declare(strict_types=1);

namespace App\Enums;

enum AgencyUserStatus: string
{
    case Pending = 'PENDING';
    case Invited = 'INVITED';
    case Active = 'ACTIVE';
    case Disabled = 'DISABLED';
}
