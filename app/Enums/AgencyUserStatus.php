<?php

declare(strict_types=1);

namespace App\Enums;

enum AgencyUserStatus: string
{
    case InviteOnApproval = 'INVITE_ON_APPROVAL';
    case InviteOnPortalLaunch = 'INVITE_ON_PORTAL_LAUNCH';
    case Active = 'ACTIVE';
    case Disabled = 'DISABLED';
}
