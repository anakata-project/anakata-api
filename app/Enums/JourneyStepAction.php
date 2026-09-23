<?php

declare(strict_types=1);

namespace App\Enums;

enum JourneyStepAction: string
{
    case Send = 'send';
    case Pointer = 'pointer';
    case Task = 'task';
}
