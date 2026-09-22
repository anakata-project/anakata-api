<?php

declare(strict_types=1);

namespace App\Enums;

enum CampaignStatus: string
{
    case Active = 'ACTIVE';
    case Archived = 'ARCHIVED';
}
