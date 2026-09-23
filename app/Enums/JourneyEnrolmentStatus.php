<?php

declare(strict_types=1);

namespace App\Enums;

enum JourneyEnrolmentStatus: string
{
    case Active = 'ACTIVE';
    case Exited = 'EXITED';
    case Suppressed = 'SUPPRESSED';
    case Completed = 'COMPLETED';
}
